<?php

namespace Eep\Service;

use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\ResultSet\ResultSet;

/**
 * Maneja la Fase 6 del proceso de graduación:
 * "Autorización de Impresión del Proyecto de Graduación".
 *
 * Concentra la lógica de cuatro recursos GLOBALES (compartidos por todos
 * los procesos del paso 6) más el estado por proceso:
 *
 *   1) Instrucciones (un único registro editable por el director).
 *   2) Documentos de soporte (logos, escudos, guías visuales).
 *   3) Catálogo de profesionales calificados (licenciados en letras).
 *   4) Cartas tipo .docx para descarga del estudiante.
 *   5) Miembros de junta directiva (informativo).
 *   6) Estado por proceso: profesional seleccionado, descargas
 *      confirmadas y aprobación del director.
 *
 * Reglas de negocio:
 *   - La aprobación de la fase exige que el estudiante haya seleccionado
 *     un profesional calificado y que el proceso esté en
 *     fase 'autorizacion_impresion'. La aprobación final dispara el
 *     avance al paso 1 de 'examen_general' a través de ExamenManager.
 *   - Los archivos físicos se guardan en
 *     public/archivos/autorizacion-impresion/{documentos-soporte,cartas-descarga}/
 *     con nombre <md5>.<ext>. El controller los descarga sirviendo
 *     bytes; nunca expone la ruta física.
 */
class AutorizacionImpresionManager
{
    /** Fase canónica del paso 6 en examen_paso_catalogo.fase. */
    public const FASE = 'autorizacion_impresion';

    /** Numero_orden del paso 6 dentro de la fase. */
    public const NUMERO_ORDEN = 6;

    /** Formatos permitidos para documentos de soporte. */
    private const FORMATOS_DOCUMENTO_SOPORTE = ['jpg', 'jpeg', 'png', 'pdf'];

    /** Formatos permitidos para cartas descargables. */
    private const FORMATOS_CARTA_DESCARGA = ['docx', 'doc', 'pdf'];

    /** Tamaño máximo por archivo (MB). */
    private const TAMANO_MAX_MB = 10;

    /** Subdirectorios bajo public/archivos/autorizacion-impresion/. */
    public const SUBDIR_DOCUMENTOS = 'documentos-soporte';
    public const SUBDIR_CARTAS     = 'cartas-descarga';

    /** @var AdapterInterface */
    private $adapter;

    /** @var ExamenManager Servicio de transición entre pasos. */
    private $examenManager;

    public function __construct(AdapterInterface $adapter, ExamenManager $examenManager)
    {
        $this->adapter       = $adapter;
        $this->examenManager = $examenManager;
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

    public function getFormatosDocumentoSoporte(): array
    {
        return self::FORMATOS_DOCUMENTO_SOPORTE;
    }

    public function getFormatosCartaDescarga(): array
    {
        return self::FORMATOS_CARTA_DESCARGA;
    }

    public function getTamanoMaxBytes(): int
    {
        return self::TAMANO_MAX_MB * 1024 * 1024;
    }

    public function getTamanoMaxMb(): int
    {
        return self::TAMANO_MAX_MB;
    }

    // ----------------------------------------------------------------
    // 1) Instrucciones globales (single-row, por parte)
    // ----------------------------------------------------------------

    /**
     * Obtiene las instrucciones de una parte específica.
     * @param int $parte 1 = Autorización de Imprímase, 2 = Entrega de Proyecto
     */
    public function getInstrucciones(int $parte = 1): ?string
    {
        $columna = $parte === 2 ? 'instrucciones_parte2' : 'instrucciones_parte1';
        $rows = $this->execute(
            "SELECT {$columna} AS instrucciones FROM examen_autorizacion_config WHERE cod_config = 1 LIMIT 1"
        );
        if (empty($rows)) {
            return null;
        }
        return $rows[0]['instrucciones'];
    }

    /**
     * Obtiene las instrucciones de ambas partes.
     * @return array ['parte1' => string|null, 'parte2' => string|null]
     */
    public function getInstruccionesAmbas(): array
    {
        $rows = $this->execute(
            'SELECT instrucciones_parte1, instrucciones_parte2 FROM examen_autorizacion_config WHERE cod_config = 1 LIMIT 1'
        );
        if (empty($rows)) {
            return ['parte1' => null, 'parte2' => null];
        }
        return [
            'parte1' => $rows[0]['instrucciones_parte1'],
            'parte2' => $rows[0]['instrucciones_parte2'],
        ];
    }

    /**
     * Sanitiza HTML permitiendo solo tags seguros para instrucciones enriquecidas.
     * Usa HTMLPurifier si está disponible, o strip_tags como fallback.
     */
    private function purificarHtml(string $html): string
    {
        // Tags permitidos para el editor WYSIWYG (formato básico de texto)
        $allowedHtml = 'p[style],br,strong,b,em,i,u,ul,ol,li,h4,h5,h6,a[href|target|title|style]';

        // Usar HTMLPurifier si está disponible (más seguro)
        if (class_exists('HTMLPurifier')) {
            $config = \HTMLPurifier_Config::create(null);
            $config->set('Core.Encoding', 'UTF-8');
            $config->set('HTML.Doctype', 'HTML 4.01 Transitional');
            $config->set('HTML.Allowed', $allowedHtml);
            $config->set('Attr.AllowedFrameTargets', ['_blank', '_self']);
            $config->set('AutoFormat.RemoveEmpty', true);
            // Desactivar caché para evitar problemas de permisos
            $config->set('Cache.DefinitionImpl', null);

            $purifier = new \HTMLPurifier($config);
            return $purifier->purify($html);
        }

        // Fallback: strip_tags básico (menos seguro pero funcional)
        $tagsPermitidos = '<p><br><strong><b><em><i><u><ul><ol><li><h4><h5><h6><a>';
        return strip_tags($html, $tagsPermitidos);
    }

    /**
     * Guarda instrucciones de una parte específica.
     * @param int $parte 1 o 2
     */
    public function guardarInstrucciones(string $instrucciones, int $codUsuario, int $parte = 1): bool
    {
        // Sanitizar HTML antes de guardar para prevenir XSS
        $htmlLimpio = $this->purificarHtml($instrucciones);
        $columna = $parte === 2 ? 'instrucciones_parte2' : 'instrucciones_parte1';

        $this->exec(
            "UPDATE examen_autorizacion_config
                SET {$columna} = :instrucciones,
                    updated_by = :usuario
              WHERE cod_config = 1",
            ['instrucciones' => $htmlLimpio, 'usuario' => $codUsuario]
        );
        return true;
    }

    // ----------------------------------------------------------------
    // 2) Documentos de soporte (logos, escudos)
    // ----------------------------------------------------------------

    public function getDocumentosSoporte(bool $soloActivos = true): array
    {
        $where = $soloActivos ? 'WHERE activo = 1' : '';
        return $this->execute(
            "SELECT cod_documento, titulo, descripcion, archivo_md5, extension,
                    nombre_original, tamano_bytes, activo,
                    subido_por, fecha_subida
               FROM examen_autorizacion_documento_soporte
                $where
              ORDER BY fecha_subida DESC"
        );
    }

    public function getDocumentoSoportePorMd5(string $md5): ?array
    {
        $rows = $this->execute(
            'SELECT cod_documento, archivo_md5, extension, nombre_original, activo
               FROM examen_autorizacion_documento_soporte
              WHERE archivo_md5 = :md5
              LIMIT 1',
            ['md5' => $md5]
        );
        return $rows[0] ?? null;
    }

    public function guardarDocumentoSoporte(array $data): int
    {
        $this->exec(
            'INSERT INTO examen_autorizacion_documento_soporte
                 (titulo, descripcion, archivo_md5, extension, nombre_original,
                  tamano_bytes, subido_por)
             VALUES
                 (:titulo, :descripcion, :md5, :ext, :nombre,
                  :tamano, :usuario)',
            [
                'titulo'      => $data['titulo'],
                'descripcion' => $data['descripcion'] ?? null,
                'md5'         => $data['archivo_md5'],
                'ext'         => $data['extension'],
                'nombre'      => $data['nombre_original'] ?? null,
                'tamano'      => $data['tamano_bytes'] ?? null,
                'usuario'     => $data['subido_por'],
            ]
        );
        return (int) $this->adapter->getDriver()->getLastGeneratedValue();
    }

    public function eliminarDocumentoSoporte(int $codDocumento, string $rutaBase): bool
    {
        $rows = $this->execute(
            'SELECT archivo_md5, extension
               FROM examen_autorizacion_documento_soporte
              WHERE cod_documento = :cod LIMIT 1',
            ['cod' => $codDocumento]
        );
        if (empty($rows)) {
            return false;
        }
        $ruta = rtrim($rutaBase, '/') . '/' . $rows[0]['archivo_md5'] . '.' . $rows[0]['extension'];
        if (is_file($ruta)) {
            @unlink($ruta);
        }
        $this->exec(
            'DELETE FROM examen_autorizacion_documento_soporte WHERE cod_documento = :cod',
            ['cod' => $codDocumento]
        );
        return true;
    }

    // ----------------------------------------------------------------
    // 3) Profesionales calificados
    // ----------------------------------------------------------------

    public function getProfesionales(bool $soloActivos = true): array
    {
        $where = $soloActivos ? 'WHERE activo = 1' : '';
        return $this->execute(
            "SELECT cod_profesional, nombre_completo, correo, telefono,
                    activo, creado_por, fecha_creacion
               FROM examen_profesional_calificado
               $where
              ORDER BY nombre_completo ASC"
        );
    }

    public function getProfesional(int $codProfesional): ?array
    {
        $rows = $this->execute(
            'SELECT cod_profesional, nombre_completo, correo, telefono, activo
               FROM examen_profesional_calificado
              WHERE cod_profesional = :cod LIMIT 1',
            ['cod' => $codProfesional]
        );
        return $rows[0] ?? null;
    }

    /**
     * Upsert. Si llega cod_profesional > 0 actualiza; si no, inserta.
     */
    public function guardarProfesional(array $data): int
    {
        $codProfesional = (int) ($data['cod_profesional'] ?? 0);

        if ($codProfesional > 0) {
            $this->exec(
                'UPDATE examen_profesional_calificado
                    SET nombre_completo = :nombre,
                        correo          = :correo,
                        telefono        = :telefono,
                        activo          = :activo
                  WHERE cod_profesional = :cod',
                [
                    'nombre'   => $data['nombre_completo'],
                    'correo'   => $data['correo']   ?? null,
                    'telefono' => $data['telefono'] ?? null,
                    'activo'   => isset($data['activo']) ? (int) $data['activo'] : 1,
                    'cod'      => $codProfesional,
                ]
            );
            return $codProfesional;
        }

        $this->exec(
            'INSERT INTO examen_profesional_calificado
                 (nombre_completo, correo, telefono, activo, creado_por)
             VALUES
                 (:nombre, :correo, :telefono, 1, :usuario)',
            [
                'nombre'   => $data['nombre_completo'],
                'correo'   => $data['correo']   ?? null,
                'telefono' => $data['telefono'] ?? null,
                'usuario'  => $data['creado_por'],
            ]
        );
        return (int) $this->adapter->getDriver()->getLastGeneratedValue();
    }

    public function eliminarProfesional(int $codProfesional): bool
    {
        // Soft delete: marcar como inactivo. Hay procesos que pueden estar
        // referenciando esta fila (cod_profesional en examen_autorizacion_proceso).
        $this->exec(
            'UPDATE examen_profesional_calificado SET activo = 0 WHERE cod_profesional = :cod',
            ['cod' => $codProfesional]
        );
        return true;
    }

    // ----------------------------------------------------------------
    // 4) Cartas de descarga (.docx genéricas)
    // ----------------------------------------------------------------

    public function getCartasDescarga(bool $soloActivas = true): array
    {
        $where = $soloActivas ? 'WHERE activo = 1' : '';
        return $this->execute(
            "SELECT cod_carta, titulo, descripcion, archivo_md5, extension,
                    nombre_original, tamano_bytes, activo,
                    subido_por, fecha_subida
               FROM examen_carta_descarga
                $where
              ORDER BY fecha_subida DESC"
        );
    }

    public function getCartaDescargaPorMd5(string $md5): ?array
    {
        $rows = $this->execute(
            'SELECT cod_carta, archivo_md5, extension, nombre_original, activo
               FROM examen_carta_descarga
              WHERE archivo_md5 = :md5 LIMIT 1',
            ['md5' => $md5]
        );
        return $rows[0] ?? null;
    }

    public function guardarCartaDescarga(array $data): int
    {
        $this->exec(
            'INSERT INTO examen_carta_descarga
                 (titulo, descripcion, archivo_md5, extension, nombre_original,
                  tamano_bytes, subido_por)
             VALUES
                 (:titulo, :descripcion, :md5, :ext, :nombre,
                  :tamano, :usuario)',
            [
                'titulo'      => $data['titulo'],
                'descripcion' => $data['descripcion'] ?? null,
                'md5'         => $data['archivo_md5'],
                'ext'         => $data['extension'],
                'nombre'      => $data['nombre_original'],
                'tamano'      => $data['tamano_bytes'] ?? null,
                'usuario'     => $data['subido_por'],
            ]
        );
        return (int) $this->adapter->getDriver()->getLastGeneratedValue();
    }

    public function eliminarCartaDescarga(int $codCarta, string $rutaBase): bool
    {
        $rows = $this->execute(
            'SELECT archivo_md5, extension FROM examen_carta_descarga
              WHERE cod_carta = :cod LIMIT 1',
            ['cod' => $codCarta]
        );
        if (empty($rows)) {
            return false;
        }
        $ruta = rtrim($rutaBase, '/') . '/' . $rows[0]['archivo_md5'] . '.' . $rows[0]['extension'];
        if (is_file($ruta)) {
            @unlink($ruta);
        }
        $this->exec(
            'DELETE FROM examen_carta_descarga WHERE cod_carta = :cod',
            ['cod' => $codCarta]
        );
        return true;
    }

    // ----------------------------------------------------------------
    // 5) Junta directiva (informativo)
    // ----------------------------------------------------------------

    public function getMiembrosJunta(bool $soloActivos = true): array
    {
        $where = $soloActivos ? 'WHERE activo = 1' : '';
        return $this->execute(
            "SELECT cod_miembro, nombre_completo, puesto,
                    activo, creado_por, fecha_creacion
               FROM examen_junta_directiva
                $where
              ORDER BY fecha_creacion ASC, nombre_completo ASC"
        );
    }

    public function getMiembroJunta(int $codMiembro): ?array
    {
        $rows = $this->execute(
            'SELECT cod_miembro, nombre_completo, puesto, activo
               FROM examen_junta_directiva
              WHERE cod_miembro = :cod LIMIT 1',
            ['cod' => $codMiembro]
        );
        return $rows[0] ?? null;
    }

    public function guardarMiembroJunta(array $data): int
    {
        $codMiembro = (int) ($data['cod_miembro'] ?? 0);

        if ($codMiembro > 0) {
            $this->exec(
                'UPDATE examen_junta_directiva
                    SET nombre_completo = :nombre,
                        puesto          = :puesto,
                        activo          = :activo
                  WHERE cod_miembro = :cod',
                [
                    'nombre' => $data['nombre_completo'],
                    'puesto' => $data['puesto'],
                    'activo' => isset($data['activo']) ? (int) $data['activo'] : 1,
                    'cod'    => $codMiembro,
                ]
            );
            return $codMiembro;
        }

        $this->exec(
            'INSERT INTO examen_junta_directiva
                 (nombre_completo, puesto, activo, creado_por)
             VALUES
                 (:nombre, :puesto, 1, :usuario)',
            [
                'nombre'  => $data['nombre_completo'],
                'puesto'  => $data['puesto'],
                'usuario' => $data['creado_por'],
            ]
        );
        return (int) $this->adapter->getDriver()->getLastGeneratedValue();
    }

    public function eliminarMiembroJunta(int $codMiembro): bool
    {
        $this->exec(
            'DELETE FROM examen_junta_directiva WHERE cod_miembro = :cod',
            ['cod' => $codMiembro]
        );
        return true;
    }

    // ----------------------------------------------------------------
    // 6) Estado por proceso
    // ----------------------------------------------------------------

    /**
     * Lista de procesos actualmente en fase 'autorizacion_impresion'
     * con sus datos básicos para el listado del director.
     * Incluye sub_paso para distinguir Parte 1 y Parte 2.
     */
    public function getProcesosEnFase(): array
    {
        $sql = "SELECT ep.cod_proceso,
                       ep.cod_usuario,
                       u.nombres,
                       u.apellidos,
                       u.registro_academico,
                       et.nombre AS tipo_examen,
                       ep.fecha_solicitud,
                       eap.cod_profesional,
                       prof.nombre_completo AS profesional_nombre,
                       eap.sub_paso,
                       eap.estado AS estado_autorizacion,
                       eap.fecha_aprobacion
                  FROM examen_proceso ep
                  JOIN usuario u ON u.cod_usuario = ep.cod_usuario
                  JOIN examen_tipo et ON et.cod_tipo_examen = ep.cod_tipo_examen
                  JOIN examen_paso_catalogo epc ON epc.cod_paso = ep.cod_paso_actual
                  LEFT JOIN examen_autorizacion_proceso eap ON eap.cod_proceso = ep.cod_proceso
                  LEFT JOIN examen_profesional_calificado prof ON prof.cod_profesional = eap.cod_profesional
                 WHERE ep.cancelado = 0
                   AND epc.fase = :fase
                 ORDER BY eap.sub_paso ASC, ep.fecha_solicitud DESC";
        return $this->execute($sql, ['fase' => self::FASE]);
    }

    /**
     * Retorna el registro de autorización por proceso. Si no existe, lo
     * crea con valores por defecto (pendiente, sin profesional, sub_paso=1).
     */
    public function getOrCreateEstadoProceso(int $codProceso): array
    {
        $rows = $this->execute(
            'SELECT cod_autorizacion, cod_proceso, cod_profesional, sub_paso,
                    estado, fecha_aprobacion, aprobado_por, observaciones
               FROM examen_autorizacion_proceso
              WHERE cod_proceso = :proceso LIMIT 1',
            ['proceso' => $codProceso]
        );
        if (!empty($rows)) {
            return $rows[0];
        }
        $this->exec(
            'INSERT INTO examen_autorizacion_proceso (cod_proceso, sub_paso) VALUES (:proceso, 1)',
            ['proceso' => $codProceso]
        );
        return $this->getOrCreateEstadoProceso($codProceso);
    }

    public function getEstadoProceso(int $codProceso): ?array
    {
        $rows = $this->execute(
            'SELECT cod_autorizacion, cod_proceso, cod_profesional, sub_paso,
                    estado, fecha_aprobacion, aprobado_por, observaciones
               FROM examen_autorizacion_proceso
              WHERE cod_proceso = :proceso LIMIT 1',
            ['proceso' => $codProceso]
        );
        return $rows[0] ?? null;
    }

    /**
     * El estudiante elige (o cambia) el profesional calificado. Sólo válido
     * en Parte 1 (sub_paso=1) y mientras el estado del proceso siga 'pendiente'.
     */
    public function seleccionarProfesional(int $codProceso, int $codProfesional): void
    {
        $estado = $this->getOrCreateEstadoProceso($codProceso);
        if ($estado['estado'] !== 'pendiente') {
            throw new \RuntimeException('La fase ya fue aprobada; no se puede cambiar el profesional.');
        }
        if ((int) ($estado['sub_paso'] ?? 1) !== 1) {
            throw new \RuntimeException('La Parte 1 ya fue completada; no se puede cambiar el profesional.');
        }
        $prof = $this->getProfesional($codProfesional);
        if (!$prof || (int) $prof['activo'] !== 1) {
            throw new \RuntimeException('Profesional no válido o inactivo.');
        }
        $this->exec(
            'UPDATE examen_autorizacion_proceso
                SET cod_profesional = :prof
              WHERE cod_proceso = :proceso',
            ['prof' => $codProfesional, 'proceso' => $codProceso]
        );
    }

    /**
     * Verifica que el proceso esté actualmente en fase autorizacion_impresion.
     */
    public function procesoEstaEnFase(int $codProceso): bool
    {
        $rows = $this->execute(
            'SELECT epc.fase
               FROM examen_proceso ep
               JOIN examen_paso_catalogo epc ON epc.cod_paso = ep.cod_paso_actual
              WHERE ep.cod_proceso = :proceso LIMIT 1',
            ['proceso' => $codProceso]
        );
        return !empty($rows) && $rows[0]['fase'] === self::FASE;
    }

    /**
     * Aprobación del director para Parte 1 o Parte 2 del paso 6.
     *
     * Parte 1 (sub_paso=1):
     *   - Requiere que el estudiante haya seleccionado un profesional.
     *   - Avanza a Parte 2 (sub_paso=2), NO a examen_general.
     *
     * Parte 2 (sub_paso=2):
     *   - Marca estado='aprobado' y avanza a examen_general.
     *
     * @return array Con claves: cod_proceso, sub_paso, avanzado, message
     */
    public function aprobarRevisionPresencial(
        int $codProceso,
        int $codUsuarioDirector,
        ?string $observaciones = null
    ): array {
        if (!$this->procesoEstaEnFase($codProceso)) {
            throw new \RuntimeException('El proceso no está en la fase de Autorización de Impresión.');
        }
        $estado = $this->getOrCreateEstadoProceso($codProceso);
        
        if ($estado['estado'] === 'aprobado') {
            throw new \RuntimeException('La fase ya fue aprobada anteriormente.');
        }

        $subPasoActual = (int) ($estado['sub_paso'] ?? 1);

        if ($subPasoActual === 1) {
            // ============ PARTE 1 ============
            // Validar que el estudiante haya seleccionado un profesional
            if (empty($estado['cod_profesional'])) {
                throw new \RuntimeException('El estudiante aún no ha seleccionado un profesional calificado.');
            }

            // Avanzar a Parte 2, NO a examen_general
            $obsTexto = $observaciones ? "\n[Parte 1] " . $observaciones : '';
            $this->exec(
                'UPDATE examen_autorizacion_proceso
                    SET sub_paso      = 2,
                        observaciones = CONCAT(COALESCE(observaciones, ""), :obs)
                  WHERE cod_proceso = :proceso',
                [
                    'obs'     => $obsTexto,
                    'proceso' => $codProceso,
                ]
            );

            return [
                'cod_proceso' => $codProceso,
                'sub_paso'    => 2,
                'avanzado'    => false,
                'message'     => 'Parte 1 aprobada. El proceso avanza a la Parte 2.',
            ];

        } else {
            // ============ PARTE 2 ============
            // Aprobar y avanzar a examen_general
            $obsTexto = $observaciones ? "\n[Parte 2] " . $observaciones : '';
            $this->exec(
                'UPDATE examen_autorizacion_proceso
                    SET estado           = "aprobado",
                        fecha_aprobacion = CURRENT_TIMESTAMP,
                        aprobado_por     = :usuario,
                        observaciones    = CONCAT(COALESCE(observaciones, ""), :obs)
                  WHERE cod_proceso = :proceso',
                [
                    'usuario' => $codUsuarioDirector,
                    'obs'     => $obsTexto,
                    'proceso' => $codProceso,
                ]
            );

            // Avanzar al paso 1 de examen_general
            $avanzado = $this->examenManager->avanzarPaso($codProceso, $codUsuarioDirector);

            return [
                'cod_proceso' => $codProceso,
                'sub_paso'    => 2,
                'avanzado'    => $avanzado,
                'message'     => 'Parte 2 aprobada. El proceso avanza a Examen General.',
            ];
        }
    }
}
