<?php

namespace Eep\Service;

use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Sql\Sql;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Expression;

class StudentGraduationManager
{
    private AdapterInterface $adapter;

    public function __construct(AdapterInterface $adapter)
    {
        $this->adapter = $adapter;
    }

    private function execute(string $sql, array $params = []): array
    {
        $statement = $this->adapter->createStatement($sql, $params);
        $result    = $statement->execute();
        $resultSet = new ResultSet(ResultSet::TYPE_ARRAY);
        $resultSet->initialize($result);
        return $resultSet->toArray();
    }

    /**
     * Obtiene un único proceso de examen por su cod_proceso.
     * Retorna null si no existe.
     */
    public function getProceso(int $codProceso): ?array
    {
        $sql = 'SELECT
                    ep.cod_proceso,
                    ep.cod_usuario,
                    u.nombres,
                    u.apellidos,
                    u.registro_academico,
                    et.cod_tipo_examen     AS tipo_cod_examen,
                    et.nombre              AS tipo_examen,
                    ep.fecha_solicitud,
                    ep.cod_paso_actual,
                    epc.nombre             AS nombre_paso_actual,
                    epc.numero_orden,
                    COALESCE(epp.estado, "pendiente") AS estado_paso,
                    ep.cancelado
                FROM examen_proceso ep
                JOIN usuario u              ON u.cod_usuario      = ep.cod_usuario
                JOIN examen_tipo et         ON et.cod_tipo_examen = ep.cod_tipo_examen
                LEFT JOIN examen_paso_catalogo epc ON epc.cod_paso = ep.cod_paso_actual
                LEFT JOIN examen_proceso_paso epp  ON epp.cod_proceso = ep.cod_proceso
                    AND epp.cod_paso = ep.cod_paso_actual
                WHERE ep.cod_proceso = :proceso
                LIMIT 1';

        $result = $this->execute($sql, ['proceso' => $codProceso]);
        return $result[0] ?? null;
    }

    /**
     * Obtiene la información académica completa de un estudiante para el panel de revisión.
     * T-04
     */
    public function getEstudiante(string $carne): ?array
    {
        $sql = 'SELECT 
                    u.cod_usuario,
                    u.registro_academico,
                    u.cui,
                    CONCAT(u.nombres, " ", u.apellidos) AS nombre_completo,
                    u.correo,
                    u.telefono,
                    p.descripcion AS pensum_nombre,
                    c.nombre_actual AS carrera
                FROM usuario u
                LEFT JOIN inscripcion i ON i.cod_usuario = u.cod_usuario
                LEFT JOIN pensum p ON p.cod_pensum = i.cod_pensum
                LEFT JOIN carrera c ON c.cod_carrera = p.cod_carrera
                WHERE u.registro_academico = :carne';

        $result = $this->execute($sql, ['carne' => $carne]);
        return $result[0] ?? null;
    }

    /**
     * Valida si un paso permite subida de documentos o modificaciones (bloqueo automático).
     * Retorna true si el paso está abierto, false si ya fue completado/cerrado.
     * T-08
     */
    public function puedeSubir(int $codProceso, int $codPaso): bool
    {
        $sql = 'SELECT fecha_completado 
                FROM examen_proceso_paso 
                WHERE cod_proceso = :proceso 
                  AND cod_paso = :paso';

        $result = $this->execute($sql, ['proceso' => $codProceso, 'paso' => $codPaso]);
        
        // Si no existe el registro del paso, está abierto por defecto
        if (empty($result)) {
            return true;
        }

        // Si fecha_completado es NULL, el paso sigue abierto
        return $result[0]['fecha_completado'] === null;
    }

    /**
     * Busca un archivo por su hash MD5 y verifica que pertenece al usuario indicado.
     * Retorna null si el archivo no existe o el usuario no tiene acceso.
     * T-09
     */
    public function getArchivoByHash(string $hash): ?array
    {
        $sql = 'SELECT
                    al.nombre_md5,
                    al.extension,
                    ed.nombre_original
                FROM archivo_local al
                JOIN examen_documento ed ON ed.cod_documento = al.cod_documento
                WHERE al.nombre_md5 = :hash
                  AND ed.eliminado  = 0
                LIMIT 1';

        $result = $this->execute($sql, ['hash' => $hash]);
        return $result[0] ?? null;
    }

    /**
     * Retorna los datos de un requisito verificando que pertenezca al paso actual
     * del proceso indicado. Retorna null si el requisito no es válido para ese proceso.
     * Usado para validación de seguridad en la subida de documentos.
     * T-09
     */
    public function getRequisitoInfo(int $codProceso, int $codRequisito): ?array
    {
        $sql = 'SELECT
                    erd.cod_requisito,
                    erd.formatos_permitidos,
                    erd.tamano_max_mb,
                    erd.tipo_entrega
                FROM examen_requisito_documento erd
                JOIN examen_proceso ep ON ep.cod_proceso = :proceso
                WHERE erd.cod_requisito = :requisito
                  AND erd.cod_paso = ep.cod_paso_actual
                  AND erd.tipo_entrega = :digital
                  AND erd.activo = 1
                LIMIT 1';

        $result = $this->execute($sql, [
            'proceso'   => $codProceso,
            'requisito' => $codRequisito,
            'digital'   => 'digital',
        ]);

        return $result[0] ?? null;
    }

    /**
     * Registra un nuevo documento en la base de datos (almacenamiento local).
     * Maneja versionado: marca la versión anterior como histórica antes de insertar.
     * Inserta en examen_documento y luego en archivo_local.
     * T-09/T-14
     */
    public function guardarDocumentoDb(array $data): int
    {
        // 1. Marcar versión anterior como histórica (si existe)
        $this->adapter->createStatement(
            'UPDATE examen_documento
             SET es_version_actual = 0
             WHERE cod_proceso = :proceso
               AND cod_requisito = :requisito
               AND es_version_actual = 1',
            ['proceso' => $data['cod_proceso'], 'requisito' => $data['cod_requisito']]
        )->execute();

        // 2. Calcular número de versión siguiente
        $versionResult = $this->execute(
            'SELECT COALESCE(MAX(version), 0) + 1 AS siguiente
             FROM examen_documento
             WHERE cod_proceso = :proceso AND cod_requisito = :requisito',
            ['proceso' => $data['cod_proceso'], 'requisito' => $data['cod_requisito']]
        );
        $version = (int) ($versionResult[0]['siguiente'] ?? 1);

        // 3. Insertar registro principal del documento
        $this->adapter->createStatement(
            'INSERT INTO examen_documento
                 (cod_proceso, cod_requisito, version, es_version_actual,
                  archivo_nombre, nombre_original, mime_type, tamano_bytes, checksum_sha256, subido_por)
             VALUES
                 (:proceso, :requisito, :version, 1,
                  :archivo_nombre, :nombre_original, :mime, :tamano, :checksum, :usuario)',
            [
                'proceso'         => $data['cod_proceso'],
                'requisito'       => $data['cod_requisito'],
                'version'         => $version,
                'archivo_nombre'  => $data['archivo_nombre'],
                'nombre_original' => $data['nombre_original'],
                'mime'            => $data['mime_type'],
                'tamano'          => $data['tamano_bytes'],
                'checksum'        => $data['checksum_sha256'],
                'usuario'         => $data['subido_por'],
            ]
        )->execute();

        $codDocumento = (int) $this->adapter->getDriver()->getLastGeneratedValue();

        // 4. Insertar metadata del archivo local
        $this->adapter->createStatement(
            'INSERT INTO archivo_local (cod_documento, nombre_md5, extension)
             VALUES (:cod_documento, :nombre_md5, :extension)',
            [
                'cod_documento' => $codDocumento,
                'nombre_md5'    => $data['archivo_nombre'],
                'extension'     => $data['extension'],
            ]
        )->execute();

        return $codDocumento;
    }

    /**
     * Obtiene el proceso activo del estudiante con información de todos los pasos.
     * Retorna el proceso con sus pasos y el progreso de cada uno.
     */
    public function getProcesoEstudiante(int $codUsuario): ?array
    {
        // Obtener el proceso activo del estudiante
        $sqlProceso = 'SELECT
                ep.cod_proceso,
                ep.cod_usuario,
                et.cod_tipo_examen,
                et.nombre AS tipo_examen,
                ep.fecha_solicitud,
                ep.cod_paso_actual,
                ep.cancelado
            FROM examen_proceso ep
            JOIN examen_tipo et ON et.cod_tipo_examen = ep.cod_tipo_examen
            WHERE ep.cod_usuario = :usuario
              AND ep.cancelado = 0
            ORDER BY ep.fecha_solicitud DESC
            LIMIT 1';

        $procesosResult = $this->execute($sqlProceso, ['usuario' => $codUsuario]);
        
        if (empty($procesosResult)) {
            return null;
        }

        $proceso = $procesosResult[0];

        // Obtener todos los pasos del catálogo para este tipo de examen
        $sqlPasos = 'SELECT
                epc.cod_paso,
                epc.numero_orden,
                epc.nombre,
                epc.es_ultimo_paso,
                COALESCE(epp.estado, "pendiente") AS estado,
                epp.fecha_inicio,
                epp.fecha_completado,
                epp.observaciones
            FROM examen_paso_catalogo epc
            LEFT JOIN examen_proceso_paso epp 
                ON epp.cod_paso = epc.cod_paso 
                AND epp.cod_proceso = :proceso
            WHERE epc.cod_tipo_examen IS NULL 
               OR epc.cod_tipo_examen = :tipo_examen
            ORDER BY epc.numero_orden ASC';

        $pasos = $this->execute($sqlPasos, [
            'proceso' => $proceso['cod_proceso'],
            'tipo_examen' => $proceso['cod_tipo_examen']
        ]);

        // Calcular el progreso total
        $totalPasos = count($pasos);
        $pasosCompletados = 0;
        $pasoActualOrden = 0;

        foreach ($pasos as &$paso) {
            if ($paso['estado'] === 'completado') {
                $pasosCompletados++;
            }
            if ($paso['cod_paso'] == $proceso['cod_paso_actual']) {
                $pasoActualOrden = $paso['numero_orden'];
            }
            // Calcular progreso individual del paso
            $paso['progreso'] = $this->calcularProgresoPaso($paso['estado']);
        }

        $proceso['pasos'] = $pasos;
        $proceso['total_pasos'] = $totalPasos;
        $proceso['pasos_completados'] = $pasosCompletados;
        $proceso['progreso_total'] = $totalPasos > 0 ? round(($pasosCompletados / $totalPasos) * 100) : 0;
        $proceso['paso_actual_orden'] = $pasoActualOrden;

        return $proceso;
    }

    /**
     * Calcula el porcentaje de progreso de un paso basado en su estado.
     */
    private function calcularProgresoPaso(string $estado): int
    {
        switch ($estado) {
            case 'completado':
                return 100;
            case 'en_progreso':
                return 50;
            case 'rechazado':
                return 25;
            case 'pendiente':
            default:
                return 0;
        }
    }

    /**
     * Obtiene los requisitos digitales de un paso junto con el documento subido
     * (si existe) para el proceso dado. Retorna un registro por requisito.
     */
    public function getRequisitosDigitales(int $codProceso, int $codPaso, int $codTipoExamen): array
    {
        $sql = "SELECT
                    erd.cod_requisito,
                    erd.nombre            AS nombre_requisito,
                    erd.descripcion,
                    erd.formatos_permitidos,
                    erd.tamano_max_mb,
                    ed.cod_documento,
                    ed.nombre_original,
                    ed.version,
                    er.fecha_revision,
                    CONCAT('/student-graduation/ver-documento?h=', al.nombre_md5) AS url_ver,
                    er.estado             AS estado_revision,
                    er.motivo_rechazo
                FROM examen_requisito_documento erd
                LEFT JOIN examen_documento ed ON ed.cod_requisito = erd.cod_requisito
                    AND ed.cod_proceso = :proceso
                    AND ed.es_version_actual = 1
                    AND ed.eliminado = 0
                LEFT JOIN archivo_local al ON al.cod_documento = ed.cod_documento
                LEFT JOIN examen_revision_documento er ON er.cod_documento = ed.cod_documento
                AND er.fecha_revision = (
                    SELECT MAX(er2.fecha_revision)
                    FROM examen_revision_documento er2
                    WHERE er2.cod_documento = ed.cod_documento
                )
                WHERE erd.cod_paso = :paso
                  AND erd.tipo_entrega = 'digital'
                  AND erd.cod_tipo_examen = :tipo
                  AND erd.activo = 1
                ORDER BY erd.orden_display ASC";

        return $this->execute($sql, ['proceso' => $codProceso, 'paso' => $codPaso, 'tipo' => $codTipoExamen]);
    }
}