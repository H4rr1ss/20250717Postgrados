<?php

namespace Eep\Service;

use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\ResultSet\ResultSet;

/**
 * Maneja el Paso 5 del proceso de graduación: bitácora de correcciones
 * y generación final de la carta de examinadores.
 *
 * MODELO (revisión 2026-05-15 — simplificado):
 *   - Todo el intercambio del trabajo corregido entre estudiante y
 *     examinador ocurre por CORREO ELECTRÓNICO externo a la plataforma.
 *     La plataforma NO almacena el PDF del trabajo ni los comentarios
 *     del examinador.
 *   - El estudiante sube evidencias (capturas de correo, PDFs) a la
 *     bitácora de la plataforma en examen_correccion_evidencia.
 *     Cada entrada es un registro simple con archivo + descripción
 *     opcional + fecha.
 *   - Existe un único ciclo interno (cod_ciclo) que se mantiene en
 *     estado 'pendiente_revision' durante todo el proceso. El usuario
 *     nunca ve el concepto de "ciclo".
 *   - Cuando el examinador considera el trabajo listo, hace clic en
 *     "Aprobar" en la plataforma → aprobarTrabajo() cierra el ciclo
 *     como 'aprobado' y genera la carta de examinadores.
 *
 * Flujo simplificado:
 *   1) iniciarPasoCarta()  -> crea ciclo #1 (pendiente_revision).
 *   2) Estudiante sube evidencias con adjuntarEvidencia() a voluntad.
 *   3) Director aprueba con aprobarTrabajo() -> carta generada.
 */
class CartaExaminadoresManager
{
    /** Nombre canónico del paso 5 según examen_paso_catalogo. */
    private const NOMBRE_PASO_5 = 'Carta de Examinadores';

    /** Formatos válidos para evidencias (capturas de correos). */
    private const FORMATOS_EVIDENCIA = ['jpg', 'jpeg', 'png', 'pdf'];

    /** Tamaño máximo de una evidencia en MB. */
    private const EVIDENCIA_TAMANO_MAX_MB = 5;

    /** @var AdapterInterface */
    private $adapter;

    /** @var CartaGenerator */
    private $cartaGenerator;

    public function __construct(AdapterInterface $adapter, CartaGenerator $cartaGenerator)
    {
        $this->adapter        = $adapter;
        $this->cartaGenerator = $cartaGenerator;
    }

    // ----------------------------------------------------------------
    // Helpers
    // ----------------------------------------------------------------

    private function execute(string $sql, array $params = []): array
    {
        $statement = $this->adapter->createStatement($sql, $params);
        $result    = $statement->execute();
        $resultSet = new ResultSet(ResultSet::TYPE_ARRAY);
        $resultSet->initialize($result);
        return $resultSet->toArray();
    }

    private function exec(string $sql, array $params = []): void
    {
        $this->adapter->createStatement($sql, $params)->execute();
    }

    public function getCodPaso5(int $codTipoExamen): ?int
    {
        $sql = 'SELECT cod_paso
                FROM examen_paso_catalogo
                WHERE nombre = :nombre
                  AND (cod_tipo_examen = :tipo OR cod_tipo_examen IS NULL)
                ORDER BY cod_tipo_examen IS NULL ASC
                LIMIT 1';
        $result = $this->execute($sql, ['nombre' => self::NOMBRE_PASO_5, 'tipo' => $codTipoExamen]);
        return isset($result[0]['cod_paso']) ? (int) $result[0]['cod_paso'] : null;
    }

    public function getFormatosEvidencia(): array
    {
        return self::FORMATOS_EVIDENCIA;
    }

    public function getTamanoMaxEvidenciaBytes(): int
    {
        return self::EVIDENCIA_TAMANO_MAX_MB * 1024 * 1024;
    }

    // ----------------------------------------------------------------
    // Lectura
    // ----------------------------------------------------------------

    /**
     * Ciclo más reciente del proceso (cualquier estado).
     */
    public function getCicloActual(int $codProceso): ?array
    {
        $sql = 'SELECT cod_ciclo, cod_proceso, estado,
                       revisado_por, fecha_revision, created_at
                FROM examen_correccion_ciclo
                WHERE cod_proceso = :proceso
                LIMIT 1';
        $result = $this->execute($sql, ['proceso' => $codProceso]);
        return $result[0] ?? null;
    }

    /**
     * Evidencias adjuntas a un ciclo (no eliminadas).
     */
    public function getEvidenciasDeCiclo(int $codCiclo): array
    {
        $sql = 'SELECT cod_evidencia, cod_ciclo, archivo_md5, extension,
                       nombre_original, tamano_bytes, descripcion,
                       subido_por, fecha_subida
                FROM examen_correccion_evidencia
                WHERE cod_ciclo = :ciclo
                  AND eliminado = 0
                ORDER BY fecha_subida ASC';
        return $this->execute($sql, ['ciclo' => $codCiclo]);
    }

    /**
     * Mapa cod_ciclo => evidencias, para mostrar el historial completo.
     * Se mantiene por compatibilidad interna; preferir getEvidenciasPlanas().
     */
    public function getEvidenciasPorCiclo(int $codProceso): array
    {
        $sql = 'SELECT ev.cod_evidencia, ev.cod_ciclo, ev.archivo_md5, ev.extension,
                       ev.nombre_original, ev.tamano_bytes, ev.descripcion,
                       ev.subido_por, ev.fecha_subida
                FROM examen_correccion_evidencia ev
                JOIN examen_correccion_ciclo ec ON ec.cod_ciclo = ev.cod_ciclo
                WHERE ec.cod_proceso = :proceso
                  AND ev.eliminado = 0
                ORDER BY ev.cod_ciclo ASC, ev.fecha_subida ASC';
        $rows = $this->execute($sql, ['proceso' => $codProceso]);
        $agrupado = [];
        foreach ($rows as $r) {
            $agrupado[(int) $r['cod_ciclo']][] = $r;
        }
        return $agrupado;
    }

    /**
     * Lista plana de todas las evidencias del proceso ordenada por fecha.
     * Usada para la bitácora del estudiante y la vista de solo-lectura del
     * director. No distingue ciclos — el usuario nunca ve el concepto.
     */
    public function getEvidenciasPlanas(int $codProceso): array
    {
        $sql = 'SELECT ev.cod_evidencia, ev.cod_ciclo, ev.archivo_md5, ev.extension,
                       ev.nombre_original, ev.tamano_bytes, ev.descripcion,
                       ev.subido_por, ev.fecha_subida
                FROM examen_correccion_evidencia ev
                JOIN examen_correccion_ciclo ec ON ec.cod_ciclo = ev.cod_ciclo
                WHERE ec.cod_proceso = :proceso
                  AND ev.eliminado = 0
                ORDER BY ev.fecha_subida ASC';
        return $this->execute($sql, ['proceso' => $codProceso]);
    }

    public function getCartaPorProceso(int $codProceso): ?array
    {
        // La carta solo "existe" si el ciclo de correcciones fue aprobado.
        // De lo contrario, el paso sigue en revisión.
        $ciclo = $this->getCicloActual($codProceso);
        if ($ciclo === null || $ciclo['estado'] !== 'aprobado') {
            return null;
        }

        $ruta = $this->cartaGenerator->obtenerRutaPlantilla();
        if ($ruta === null) {
            return null;
        }
        return [
            'cod_proceso'      => $codProceso,
            'archivo_generado' => $ruta,
            'estado'           => 'generada',
        ];
    }

    public function getEvidenciaPorMd5(string $md5): ?array
    {
        $sql = 'SELECT cod_evidencia, cod_ciclo, archivo_md5, extension,
                       nombre_original, eliminado
                FROM examen_correccion_evidencia
                WHERE archivo_md5 = :md5
                LIMIT 1';
        $result = $this->execute($sql, ['md5' => $md5]);
        return $result[0] ?? null;
    }

    /**
     * Verifica que un ciclo pertenezca al proceso dado.
     */
    public function cicloPerteneceAProceso(int $codCiclo, int $codProceso): bool
    {
        $sql = 'SELECT 1 FROM examen_correccion_ciclo
                WHERE cod_ciclo = :ciclo AND cod_proceso = :proceso LIMIT 1';
        $result = $this->execute($sql, ['ciclo' => $codCiclo, 'proceso' => $codProceso]);
        return !empty($result);
    }

    /**
     * Verifica que una evidencia pertenezca a un ciclo del proceso dado.
     */
    public function evidenciaPerteneceAProceso(int $codEvidencia, int $codProceso): bool
    {
        $sql = 'SELECT 1
                  FROM examen_correccion_evidencia ev
                  JOIN examen_correccion_ciclo ec ON ec.cod_ciclo = ev.cod_ciclo
                 WHERE ev.cod_evidencia = :evidencia
                   AND ec.cod_proceso   = :proceso
                 LIMIT 1';
        $result = $this->execute($sql, ['evidencia' => $codEvidencia, 'proceso' => $codProceso]);
        return !empty($result);
    }

    // ----------------------------------------------------------------
    // Mutaciones — ciclos
    // ----------------------------------------------------------------

    /**
     * Crea el primer ciclo si no existe. Idempotente.
     */
    public function iniciarPasoCarta(int $codProceso): array
    {
        $ciclo = $this->getCicloActual($codProceso);
        if ($ciclo !== null) {
            return $ciclo;
        }

        $this->exec(
            'INSERT INTO examen_correccion_ciclo (cod_proceso, estado)
             VALUES (:proceso, :estado)',
            ['proceso' => $codProceso, 'estado' => 'pendiente_revision']
        );
        $this->upsertProcesoPaso($codProceso, 'en_progreso');

        return $this->getCicloActual($codProceso);
    }

    /**
     * Cierra el ciclo abierto como 'aprobado' y dispara la carta.
     */
    public function aprobarTrabajo(int $codProceso, int $codUsuarioCoordinador): array
    {
        $ciclo = $this->getCicloActual($codProceso);
        if ($ciclo === null || $ciclo['estado'] !== 'pendiente_revision') {
            throw new \RuntimeException('No hay un ciclo abierto para aprobar.');
        }

        $this->exec(
            'UPDATE examen_correccion_ciclo
                SET estado         = :estado,
                    revisado_por   = :usuario,
                    fecha_revision = CURRENT_TIMESTAMP
              WHERE cod_ciclo = :ciclo',
            [
                'estado'  => 'aprobado',
                'usuario' => $codUsuarioCoordinador,
                'ciclo'   => $ciclo['cod_ciclo'],
            ]
        );

        // Marca el paso 5 como completado. Después se mueve cod_paso_actual
        // al paso 6 (autorizacion_impresion) para que el flujo continúe.
        $this->upsertProcesoPaso($codProceso, 'completado', true);
        $this->avanzarAPaso6($codProceso);

        $rutaCarta = $this->cartaGenerator->generar(
            $codProceso,
            (int) $ciclo['cod_ciclo'],
            $codUsuarioCoordinador
        );

        return [
            'cod_ciclo' => (int) $ciclo['cod_ciclo'],
            'ruta_carta' => $rutaCarta,
        ];
    }

    /**
     * Tras aprobar el paso 5, mueve el proceso al paso 6
     * (autorizacion_impresion). Crea la fila en examen_proceso_paso e
     * inicializa el registro en examen_autorizacion_proceso.
     */
    private function avanzarAPaso6(int $codProceso): void
    {
        $rows = $this->execute(
            'SELECT cod_paso FROM examen_paso_catalogo
              WHERE fase = "autorizacion_impresion"
                AND activo = 1
              LIMIT 1'
        );
        if (empty($rows)) {
            // Si la fase no está habilitada (migración no aplicada), no rompemos
            // el flujo: el proceso queda cerrado como antes.
            return;
        }
        $codPaso6 = (int) $rows[0]['cod_paso'];

        // Apuntar el proceso maestro al paso 6
        $this->exec(
            'UPDATE examen_proceso SET cod_paso_actual = :paso WHERE cod_proceso = :proceso',
            ['paso' => $codPaso6, 'proceso' => $codProceso]
        );

        // Iniciar (o reiniciar) la fila de seguimiento del paso 6
        $this->exec(
            'INSERT INTO examen_proceso_paso (cod_proceso, cod_paso, estado, fecha_inicio)
             VALUES (:proceso, :paso, "en_progreso", CURRENT_TIMESTAMP)
             ON DUPLICATE KEY UPDATE estado = "en_progreso", fecha_inicio = CURRENT_TIMESTAMP',
            ['proceso' => $codProceso, 'paso' => $codPaso6]
        );

        // Pre-crear el registro de autorización para el proceso (vacío)
        $this->exec(
            'INSERT IGNORE INTO examen_autorizacion_proceso (cod_proceso) VALUES (:proceso)',
            ['proceso' => $codProceso]
        );
    }

    // ----------------------------------------------------------------
    // Mutaciones — evidencias
    // ----------------------------------------------------------------

    /**
     * Registra una evidencia ya guardada en disco. El controller debe
     * haber movido el archivo a /var/www/public/archivos/<md5>.<ext>
     * antes de llamar a este método.
     */
    public function adjuntarEvidencia(array $data): int
    {
        $this->exec(
            'INSERT INTO examen_correccion_evidencia
                 (cod_ciclo, archivo_md5, extension, nombre_original,
                  tamano_bytes, descripcion, subido_por)
             VALUES
                 (:ciclo, :md5, :ext, :nombre, :tamano, :desc, :usuario)',
            [
                'ciclo'   => $data['cod_ciclo'],
                'md5'     => $data['archivo_md5'],
                'ext'     => $data['extension'],
                'nombre'  => $data['nombre_original'] ?? null,
                'tamano'  => $data['tamano_bytes'] ?? null,
                'desc'    => $data['descripcion'] ?? null,
                'usuario' => $data['subido_por'],
            ]
        );
        return (int) $this->adapter->getDriver()->getLastGeneratedValue();
    }

    /**
     * Soft-delete de una evidencia + borrado del archivo físico.
     */
    public function eliminarEvidencia(int $codEvidencia, string $rutaArchivosBase): void
    {
        $rows = $this->execute(
            'SELECT archivo_md5, extension, eliminado
               FROM examen_correccion_evidencia
              WHERE cod_evidencia = :cod
              LIMIT 1',
            ['cod' => $codEvidencia]
        );
        if (empty($rows) || (int) $rows[0]['eliminado'] === 1) {
            return;
        }
        $ruta = rtrim($rutaArchivosBase, '/') . '/' . $rows[0]['archivo_md5'] . '.' . $rows[0]['extension'];
        if (is_file($ruta)) {
            @unlink($ruta);
        }
        $this->exec(
            'UPDATE examen_correccion_evidencia SET eliminado = 1 WHERE cod_evidencia = :cod',
            ['cod' => $codEvidencia]
        );
    }

    // ----------------------------------------------------------------
    // Estado del paso
    // ----------------------------------------------------------------

    private function upsertProcesoPaso(int $codProceso, string $estado, bool $completado = false): void
    {
        $resTipo = $this->execute(
            'SELECT cod_tipo_examen FROM examen_proceso WHERE cod_proceso = :proceso LIMIT 1',
            ['proceso' => $codProceso]
        );
        if (empty($resTipo)) {
            return;
        }
        $codPaso = $this->getCodPaso5((int) $resTipo[0]['cod_tipo_examen']);
        if ($codPaso === null) {
            return;
        }

        $existente = $this->execute(
            'SELECT cod_proceso_paso FROM examen_proceso_paso
              WHERE cod_proceso = :proceso AND cod_paso = :paso LIMIT 1',
            ['proceso' => $codProceso, 'paso' => $codPaso]
        );

        if (empty($existente)) {
            $this->exec(
                'INSERT INTO examen_proceso_paso (cod_proceso, cod_paso, estado, fecha_completado)
                 VALUES (:proceso, :paso, :estado, :completado)',
                [
                    'proceso'    => $codProceso,
                    'paso'       => $codPaso,
                    'estado'     => $estado,
                    'completado' => $completado ? date('Y-m-d H:i:s') : null,
                ]
            );
        } else {
            $this->exec(
                'UPDATE examen_proceso_paso
                    SET estado = :estado,
                        fecha_completado = ' . ($completado ? 'CURRENT_TIMESTAMP' : 'fecha_completado') . '
                  WHERE cod_proceso = :proceso AND cod_paso = :paso',
                ['estado' => $estado, 'proceso' => $codProceso, 'paso' => $codPaso]
            );
        }

        if ($completado) {
            $this->exec(
                'UPDATE examen_proceso SET cod_paso_actual = NULL WHERE cod_proceso = :proceso',
                ['proceso' => $codProceso]
            );
        } else {
            $this->exec(
                'UPDATE examen_proceso SET cod_paso_actual = :paso WHERE cod_proceso = :proceso',
                ['paso' => $codPaso, 'proceso' => $codProceso]
            );
        }
    }
}
