<?php

namespace Eep\Service;

use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\ResultSet\ResultSet;
use PhpOffice\PhpWord\TemplateProcessor;

/**
 * Genera la carta de examinadores a partir de una plantilla .docx con
 * placeholders ${nombre_variable} (PHPWord TemplateProcessor).
 *
 * Resuelve la plantilla aplicable (específica del tipo de examen o
 * genérica), recolecta los datos del proceso/estudiante/terna y produce
 * un archivo en public/archivos/cartas-examinadores/proceso-{cod}.docx.
 *
 * Inserta el registro en examen_carta_examinadores.
 */
class CartaGenerator
{
    /** @var AdapterInterface */
    private $adapter;

    /** Ruta absoluta a la raíz del proyecto (sin slash final). */
    private $rutaProyecto;

    public function __construct(AdapterInterface $adapter, string $rutaProyecto)
    {
        $this->adapter      = $adapter;
        $this->rutaProyecto = rtrim($rutaProyecto, '/');
    }

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

    /**
     * Genera la carta para un proceso ya aprobado. Devuelve el cod_carta
     * insertado en examen_carta_examinadores.
     */
    public function generar(int $codProceso, int $codCicloAprobacion, int $codUsuarioGenera): int
    {
        // Si ya existe una carta para este proceso, devolverla (idempotente).
        $existente = $this->execute(
            'SELECT cod_carta FROM examen_carta_examinadores
              WHERE cod_proceso = :proceso LIMIT 1',
            ['proceso' => $codProceso]
        );
        if (!empty($existente)) {
            return (int) $existente[0]['cod_carta'];
        }

        $datosProceso = $this->getDatosProceso($codProceso);
        if ($datosProceso === null) {
            throw new \RuntimeException('Proceso no encontrado al generar carta: ' . $codProceso);
        }

        $plantilla = $this->getPlantillaParaTipo((int) $datosProceso['cod_tipo_examen']);
        if ($plantilla === null) {
            throw new \RuntimeException('No hay plantilla registrada para el tipo de examen.');
        }

        $rutaPlantilla = $this->rutaProyecto . '/' . ltrim($plantilla['archivo_plantilla'], '/');
        if (!is_file($rutaPlantilla)) {
            throw new \RuntimeException('Plantilla .docx no encontrada en disco: ' . $rutaPlantilla);
        }

        $valores      = $this->construirValores($datosProceso);
        $rutaRelativa = 'public/archivos/cartas-examinadores/proceso-' . $codProceso . '.docx';
        $rutaSalida   = $this->rutaProyecto . '/' . $rutaRelativa;

        $dirSalida = dirname($rutaSalida);
        if (!is_dir($dirSalida)) {
            @mkdir($dirSalida, 0775, true);
        }

        $processor = new TemplateProcessor($rutaPlantilla);
        foreach ($valores as $placeholder => $valor) {
            $processor->setValue($placeholder, $valor);
        }
        $processor->saveAs($rutaSalida);

        $this->exec(
            'INSERT INTO examen_carta_examinadores
                 (cod_proceso, cod_ciclo_aprobacion, cod_plantilla,
                  archivo_generado, estado, generada_por)
             VALUES (:proceso, :ciclo, :plantilla, :archivo, :estado, :usuario)',
            [
                'proceso'   => $codProceso,
                'ciclo'     => $codCicloAprobacion,
                'plantilla' => $plantilla['cod_plantilla'],
                'archivo'   => $rutaRelativa,
                'estado'    => 'generada',
                'usuario'   => $codUsuarioGenera,
            ]
        );

        return (int) $this->adapter->getDriver()->getLastGeneratedValue();
    }

    /**
     * Plantilla aplicable: específica del tipo si existe y está activa,
     * en caso contrario la genérica (cod_tipo_examen IS NULL).
     */
    private function getPlantillaParaTipo(int $codTipoExamen): ?array
    {
        $sql = 'SELECT cod_plantilla, cod_tipo_examen, nombre, archivo_plantilla
                FROM examen_carta_plantilla
                WHERE activo = 1
                  AND (cod_tipo_examen = :tipo OR cod_tipo_examen IS NULL)
                ORDER BY cod_tipo_examen IS NULL ASC
                LIMIT 1';
        $result = $this->execute($sql, ['tipo' => $codTipoExamen]);
        return $result[0] ?? null;
    }

    /**
     * Recolecta datos del proceso, estudiante, tipo de examen y terna.
     */
    private function getDatosProceso(int $codProceso): ?array
    {
        $sql = 'SELECT ep.cod_proceso,
                       ep.cod_tipo_examen,
                       ep.fecha_examen,
                       ep.hora_inicio_examen,
                       et.nombre AS tipo_examen,
                       u.cod_usuario,
                       u.nombres,
                       u.apellidos,
                       u.registro_academico,
                       u.cui
                FROM examen_proceso ep
                JOIN examen_tipo et ON et.cod_tipo_examen = ep.cod_tipo_examen
                JOIN usuario u      ON u.cod_usuario      = ep.cod_usuario
                WHERE ep.cod_proceso = :proceso
                LIMIT 1';
        $result = $this->execute($sql, ['proceso' => $codProceso]);
        if (empty($result)) {
            return null;
        }
        $proceso = $result[0];

        $terna = $this->execute(
            'SELECT nombre_examinador, numero_colegiado, tipo_examinador, posicion
               FROM examen_terna
              WHERE cod_proceso = :proceso
              ORDER BY posicion ASC',
            ['proceso' => $codProceso]
        );
        $proceso['terna'] = $terna;
        return $proceso;
    }

    /**
     * Construye el mapa placeholder=>valor pasado al TemplateProcessor.
     * Cualquier placeholder definido en data/plantillas/.../README.md
     * tiene su valor aquí; si no hay dato en BD, se envía cadena vacía.
     */
    private function construirValores(array $proceso): array
    {
        $nombreEstudiante = trim(($proceso['nombres'] ?? '') . ' ' . ($proceso['apellidos'] ?? ''));

        // Por convención: posicion 1 = examinador 1, 2 = examinador 2,
        // 3 = examinador 3. Si se utilizan otras posiciones (asesor, etc.)
        // en el catálogo, el orden lo determina la columna 'posicion'.
        $examinadores = [1 => ['', ''], 2 => ['', ''], 3 => ['', '']];
        $asesor = '';
        foreach ($proceso['terna'] as $miembro) {
            $pos = (int) $miembro['posicion'];
            if (isset($examinadores[$pos])) {
                $examinadores[$pos] = [
                    (string) $miembro['nombre_examinador'],
                    (string) ($miembro['numero_colegiado'] ?? ''),
                ];
            }
            // Heurística simple: si en el futuro se agrega un rol 'asesor'
            // con tipo_examinador específico, se mapea aquí.
            if (strtolower((string) $miembro['tipo_examinador']) === 'asesor') {
                $asesor = (string) $miembro['nombre_examinador'];
            }
        }

        $fechaExamen = !empty($proceso['fecha_examen'])
            ? date('d/m/Y', strtotime($proceso['fecha_examen']))
            : '';
        $horaExamen = !empty($proceso['hora_inicio_examen'])
            ? date('H:i', strtotime($proceso['hora_inicio_examen']))
            : '';

        $coordinadorNombre = $this->getNombreUsuarioGenerador();

        return [
            'estudiante_nombre'        => $nombreEstudiante,
            'estudiante_carnet'        => (string) ($proceso['registro_academico'] ?? ''),
            'estudiante_cui'           => (string) ($proceso['cui'] ?? ''),
            'titulo_trabajo'           => '',
            'tipo_examen'              => (string) ($proceso['tipo_examen'] ?? ''),
            'fecha_examen'             => $fechaExamen,
            'hora_examen'              => $horaExamen,
            'asesor_nombre'            => $asesor,
            'examinador_1_nombre'      => $examinadores[1][0],
            'examinador_1_colegiado'   => $examinadores[1][1],
            'examinador_2_nombre'      => $examinadores[2][0],
            'examinador_2_colegiado'   => $examinadores[2][1],
            'examinador_3_nombre'      => $examinadores[3][0],
            'examinador_3_colegiado'   => $examinadores[3][1],
            'coordinador_nombre'       => $coordinadorNombre,
            'fecha_emision_carta'      => date('d/m/Y'),
        ];
    }

    /**
     * Nombre del coordinador que aprueba. Hoy no se persiste aún (el
     * registro se hace en aprobarTrabajo via examen_carta_examinadores
     * después). Para evitar acoplar más, se deja vacío y se rellena
     * desde la sesión en una mejora posterior.
     */
    private function getNombreUsuarioGenerador(): string
    {
        return '';
    }
}
