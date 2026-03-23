<?php

namespace Eep\Service;

use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\Sql\Sql;
use Zend\Db\ResultSet\ResultSet;
use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Expression;

class ExamenManager
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

    public function getTiposExamen(): array
    {
        $sql = 'SELECT cod_tipo_examen, nombre, descripcion
                FROM examen_tipo
                WHERE activo = 1
                ORDER BY nombre';

        return $this->execute($sql);
    }

    /**
     * Inicia un nuevo proceso de examen para un estudiante.
     * T-13
     */
    public function iniciarProceso(int $codUsuario, int $codTipoExamen): int
    {
        // 1. Obtener el primer paso del catálogo para este tipo de examen
        $sqlPaso = 'SELECT cod_paso 
                    FROM examen_paso_catalogo 
                    WHERE (cod_tipo_examen = :tipo OR cod_tipo_examen IS NULL) 
                      AND numero_orden = 1 
                      AND activo = 1 
                    LIMIT 1';
        $resPaso = $this->execute($sqlPaso, ['tipo' => $codTipoExamen]);
        $primerPaso = !empty($resPaso) ? $resPaso[0]['cod_paso'] : 1; // Default fallback

        // 2. Crear el registro maestro del proceso
        $sqlMaster = 'INSERT INTO examen_proceso (cod_usuario, cod_tipo_examen, cod_paso_actual)
                      VALUES (:usuario, :tipo, :paso)';
        $this->adapter->createStatement($sqlMaster, [
            'usuario' => $codUsuario,
            'tipo'    => $codTipoExamen,
            'paso'    => $primerPaso
        ])->execute();

        $codProceso = $this->adapter->getDriver()->getLastGeneratedValue();

        // 3. Iniciar el primer paso técnicamente
        $sqlPrimerPaso = 'INSERT INTO examen_proceso_paso (cod_proceso, cod_paso, fecha_inicio)
                          VALUES (:proceso, :paso, CURRENT_TIMESTAMP)';
        $this->adapter->createStatement($sqlPrimerPaso, [
            'proceso' => $codProceso,
            'paso'    => $primerPaso
        ])->execute();

        // 4. Registrar en el historial de auditoría
        $this->registrarHistorial([
            'cod_proceso' => $codProceso,
            'cod_usuario' => $codUsuario,
            'tipo_evento' => 'inicio_proceso',
            'descripcion' => 'Iniciando proceso de graduación tipo ID: ' . $codTipoExamen,
            'datos_nuevos' => ['cod_tipo_examen' => $codTipoExamen, 'cod_paso_inicial' => $primerPaso]
        ]);

        return (int) $codProceso;
    }

    /**
     * Retorna el numero_orden y fecha_completado de cada paso completado
     * para el proceso más reciente del estudiante identificado por carne.
     * El resultado viene ordenado por numero_orden ascendente.
     */
    /**
     * Retorna las fechas de completado de cada paso indexadas por numero_orden.
     * Ej: [1 => '2026-02-10 09:15:00', 2 => '2026-02-15 14:30:00']
     */
    public function getFechasPasosCompletado(int $codProceso): array
    {
        $sql = 'SELECT epc.numero_orden,
                       epp.fecha_completado
                FROM examen_proceso_paso epp
                INNER JOIN examen_paso_catalogo epc ON epc.cod_paso = epp.cod_paso
                WHERE epp.cod_proceso = :proceso
                  AND epp.fecha_completado IS NOT NULL
                ORDER BY epc.numero_orden ASC';

        $rows = $this->execute($sql, ['proceso' => $codProceso]);

        // Indexar por numero_orden para acceso directo desde el controlador
        $result = [];
        foreach ($rows as $row) {
            $result[$row['numero_orden']] = $row['fecha_completado'];
        }
        return $result;
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
     * Obtiene los requisitos digitales de un paso junto con el documento subido
     * (si existe) para el proceso dado. Retorna un registro por requisito.
     */
    public function getDocumentosYRequisitos(int $codProceso, int $codPaso, int $codTipoExamen): array
    {
        $sql = "SELECT
                    erd.cod_requisito,
                    erd.nombre            AS nombre_requisito,
                    erd.descripcion,
                    erd.formatos_permitidos,
                    erd.tamano_max_mb,
                    erd.obligatorio,
                    ed.cod_documento,
                    ed.nombre_original,
                    ed.version,
                    er.fecha_revision,
                    da.drive_web_view_link,
                    er.estado             AS estado_revision,
                    er.motivo_rechazo
                FROM examen_requisito_documento erd
                LEFT JOIN examen_documento ed ON ed.cod_requisito = erd.cod_requisito
                    AND ed.cod_proceso = :proceso
                    AND ed.es_version_actual = 1
                    AND ed.eliminado = 0
                LEFT JOIN drive_archivo da ON da.cod_documento = ed.cod_documento
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

    /**
     * Verifica si TODOS los requisitos digitales obligatorios de un paso/proceso
     * ya cuentan con un documento cargado y una revisión en estado 'aprobado'.
     * Retorna true si el paso está completamente aprobado, false en caso contrario.
     */
    public function todosRequisitosAceptados(int $codProceso, int $codPaso, int $codTipoExamen): bool
    {
        // 1. Total de requisitos obligatorios para este paso y tipo de examen
        $tableReq    = new TableGateway('examen_requisito_documento', $this->adapter);
        $selectTotal = $tableReq->getSql()->select();
        $selectTotal->columns(['total' => new Expression('COUNT(*)')]);
        $selectTotal->where([
            'cod_paso'        => $codPaso,
            'cod_tipo_examen' => $codTipoExamen,
            'obligatorio'     => 1,
            'activo'          => 1,
        ]);
        $resTotal = $tableReq->selectWith($selectTotal)->toArray();
        $total    = (int)($resTotal[0]['total'] ?? 0);

        // Sin requisitos obligatorios => se considera completo
        if ($total === 0) {
            return true;
        }

        // 2. Cuántos de esos requisitos tienen un documento en versión actual
        //    con una revisión aprobada
        $tableRed        = new TableGateway(['erd' => 'examen_requisito_documento'], $this->adapter);
        $selectAprobados = $tableRed->getSql()->select();
        $selectAprobados->columns(['aprobados' => new Expression('COUNT(*)')]);

        // Documento actual
        $selectAprobados->join(
            ['ed' => 'examen_documento'],
            new Expression(
                'ed.cod_requisito = erd.cod_requisito
                AND ed.cod_proceso = ' . (int)$codProceso . '
                AND ed.es_version_actual = 1
                AND ed.eliminado = 0'
            ),
            [],
            Select::JOIN_INNER
        );

        // Última revisión (corregido)
        $selectAprobados->join(
            ['er' => 'examen_revision_documento'],
            new Expression(
                'er.cod_documento = ed.cod_documento
                AND er.fecha_revision = (
                    SELECT MAX(er2.fecha_revision)
                    FROM examen_revision_documento er2
                    WHERE er2.cod_documento = ed.cod_documento
                )'
            ),
            [],
            Select::JOIN_INNER
        );

        // Filtros
        $selectAprobados->where([
            'erd.cod_paso'        => $codPaso,
            'erd.cod_tipo_examen' => $codTipoExamen,
            'erd.obligatorio'     => 1,
            'erd.activo'          => 1,
            'er.estado'           => 'aprobado',
        ]);

        $resAprobados = $tableRed->selectWith($selectAprobados)->toArray();
        $aprobados    = (int)($resAprobados[0]['aprobados'] ?? 0);

        return $aprobados === $total;
    }

    // Funcion para obtener datos genericos de procesos de examen con filtros y paginación.
    public function getProcesos(array $filtros = []): array
    {
        $pagina = $filtros['pagina'] ?? 1;
        $limite = $filtros['limite'] ?? 20;
        $estado = $filtros['estado'] ?? null;

        $offset = ($pagina - 1) * $limite;

        $whereEstado = '';
        if ($estado) {
            $whereEstado = 'AND ep.estado = :estado';
        }

        $sql = "SELECT
                    ep.cod_proceso,
                    ep.cod_usuario,
                    u.nombres,
                    u.apellidos,
                    u.registro_academico,
                    et.cod_tipo_examen   AS tipo_cod_examen,
                    et.nombre            AS tipo_examen,
                    ep.fecha_solicitud,
                    ep.cod_paso_actual,
                    COALESCE(epp.estado, 'pendiente') AS estado_paso,
                    ep.cancelado
                FROM examen_proceso ep
                JOIN usuario u ON u.cod_usuario = ep.cod_usuario
                JOIN examen_tipo et ON et.cod_tipo_examen = ep.cod_tipo_examen
                LEFT JOIN examen_proceso_paso epp ON epp.cod_proceso = ep.cod_proceso
                    AND epp.cod_paso = ep.cod_paso_actual
                WHERE ep.cancelado = 0
                $whereEstado
                ORDER BY ep.fecha_solicitud DESC
                LIMIT $limite OFFSET $offset";

        $params = [];
        if ($estado) {
            $params['estado'] = $estado;
        }

        $procesos = $this->execute($sql, $params);

        // Contar total para paginación
        $sqlCount = "SELECT COUNT(*) AS total
                     FROM examen_proceso ep
                     WHERE ep.cancelado = 0
                     $whereEstado";

        $countResult = $this->execute($sqlCount, $params);
        $total = $countResult[0]['total'] ?? 0;

        return [
            'procesos' => $procesos,
            'total' => $total,
            'pagina' => $pagina,
            'limite' => $limite,
            'paginas_total' => ceil($total / $limite)
        ];
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
     * Obtiene la información académica de un estudiante asociado a un proceso de examen.
     * T-15
     */
    public function getEstudiantePorProceso(int $codProceso): ?array
    {
        $sql = 'SELECT
                    u.cod_usuario,
                    u.registro_academico,
                    u.cui,
                    CONCAT(u.nombres, " ", u.apellidos) AS nombre_completo,
                    u.correo,
                    u.telefono,
                    p.descripcion  AS pensum_nombre,
                    c.nombre_actual AS carrera
                FROM examen_proceso ep
                JOIN usuario u        ON u.cod_usuario  = ep.cod_usuario
                LEFT JOIN inscripcion i ON i.cod_usuario = u.cod_usuario
                LEFT JOIN pensum p     ON p.cod_pensum  = i.cod_pensum
                LEFT JOIN carrera c    ON c.cod_carrera = p.cod_carrera
                WHERE ep.cod_proceso = :proceso
                LIMIT 1';

        $result = $this->execute($sql, ['proceso' => $codProceso]);
        return $result[0] ?? null;
    }

    /**
     * Obtiene el catálogo de requisitos (documentos) para un paso y tipo de examen específicos.
     * T-05
     */
    public function getRequisitosDocumento(int $codPaso, int $codTipoExamen = null): array
    {
        $sql = 'SELECT cod_requisito, nombre, descripcion, tipo_entrega, obligatorio, formatos_permitidos, tamano_max_mb
                FROM examen_requisito_documento
                WHERE cod_paso = :paso 
                  AND (cod_tipo_examen = :tipo OR cod_tipo_examen IS NULL)
                  AND activo = 1
                ORDER BY orden_display ASC';

        return $this->execute($sql, ['paso' => $codPaso, 'tipo' => $codTipoExamen]);
    }

    /**
     * T-22.1: Gestión administrativa de requisitos
     */
    public function getTodosRequisitos($examenTipo): array
    {
        $sql = 'SELECT cod_requisito, nombre, descripcion 
                FROM examen_requisito_documento 
                WHERE activo = 1 AND cod_tipo_examen = :tipo';

        return $this->execute($sql, ['tipo' => $examenTipo]);
    }

    // funcion que retorna el nombre de examen por medio de codigo tipo de examen
    public function getNombreTipoExamen(int $codTipoExamen): ?string
    {
        $sql = 'SELECT nombre FROM examen_tipo WHERE cod_tipo_examen = :tipo';
        $result = $this->execute($sql, ['tipo' => $codTipoExamen]);
        return $result[0]['nombre'] ?? null;
    }

    public function upsertRequisito($data): int
    {
        if (isset($data['id']) && (int)$data['id'] > 0) {
            $this->execute('UPDATE examen_requisito_documento SET nombre = :nombre, descripcion = :descripcion WHERE cod_requisito = :id', [
                'id' => $data['id'],
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion']
            ]);
            return (int)$data['id'];
        } else {
            $this->execute('INSERT INTO examen_requisito_documento (nombre, descripcion, cod_tipo_examen, cod_paso, activo) VALUES (:nombre, :descripcion, :tipo, :paso, 1)', [
                'nombre'          => $data['nombre'],
                'descripcion'     => $data['descripcion'],
                'tipo'            => $data['cod_tipo_examen'] ?? null,
                'paso'            => $data['cod_paso']
            ]);
            return $this->getLastInsertId();
        }
    }

    public function desactivarRequisito(int $id): bool
    {
        return (bool)$this->execute('UPDATE examen_requisito_documento SET activo = 0 WHERE cod_requisito = :id', ['id' => $id]);
    }

    /**
     * Obtiene los documentos subidos para un proceso (versión actual) y su última revisión.
     * T-05
     */
    public function getDocumentosProceso(int $codProceso): array
    {
        $sql = 'SELECT 
                    ed.cod_documento,
                    ed.cod_requisito,
                    ed.nombre_original,
                    ed.version,
                    ed.fecha_subida,
                    da.drive_file_id,
                    da.drive_web_view_link,
                    er.estado AS estado_revision,
                    er.motivo_rechazo,
                    er.fecha_revision
                FROM examen_documento ed
                LEFT JOIN drive_archivo da ON da.cod_documento = ed.cod_documento
                LEFT JOIN examen_revision_documento er ON er.cod_documento = ed.cod_documento
                WHERE ed.cod_proceso = :proceso
                  AND ed.es_version_actual = 1
                  AND ed.eliminado = 0';

        return $this->execute($sql, ['proceso' => $codProceso]);
    }

    /**
     * Obtiene el checklist de documentos físicos recibidos para el Paso 2.
     * T-06
     */
    public function getDocumentosFisicos(int $codProceso, int $codTipoExamen = null): array
    {
        $sql = 'SELECT 
                    erd.cod_requisito,
                    erd.nombre,
                    erd.descripcion,
                    COALESCE(edf.recibido, 0) AS recibido,
                    edf.fecha_recepcion,
                    edf.observaciones,
                    CONCAT(u.nombres, " ", u.apellidos) AS recibido_por_nombre
                FROM examen_requisito_documento erd
                LEFT JOIN examen_documento_fisico edf ON edf.cod_requisito = erd.cod_requisito 
                    AND edf.cod_proceso = :proceso
                LEFT JOIN usuario u ON u.cod_usuario = edf.recibido_por
                WHERE erd.cod_paso = 2 
                  AND erd.tipo_entrega = "fisico"
                  AND erd.cod_tipo_examen = :tipo
                  AND erd.activo = 1
                ORDER BY erd.orden_display ASC';

        return $this->execute($sql, ['proceso' => $codProceso, 'tipo' => $codTipoExamen]);
    }

    /**
     * Obtiene los examinadores asignados y la programación del examen.
     * T-07
     */
    public function getTerna(int $codProceso): array
    {
        $sql = 'SELECT 
                    rol,
                    nombre_examinador,
                    numero_colegiado,
                    correo,
                    fecha_examen,
                    hora_inicio
                FROM examen_terna 
                WHERE cod_proceso = :proceso';

        $rows = $this->execute($sql, ['proceso' => $codProceso]);
        
        $terna = [
            'examinadores' => [],
            'programacion' => [
                'fecha' => null,
                'hora'  => null
            ]
        ];

        foreach ($rows as $row) {
            $terna['examinadores'][$row['rol']] = [
                'nombre'    => $row['nombre_examinador'],
                'colegiado' => $row['numero_colegiado'],
                'correo'    => $row['correo']
            ];
            
            // La fecha y hora son compartidas por todos los miembros de la terna en un mismo proceso
            if ($row['fecha_examen']) {
                $terna['programacion']['fecha'] = $row['fecha_examen'];
                $terna['programacion']['hora']  = $row['hora_inicio'];
            }
        }

        return $terna;
    }

    /**
     * Obtiene el paso actual del proceso de examen y su información de catálogo.
     * T-08
     */
    public function getPasoActual(int $codProceso): ?array
    {
        $sql = 'SELECT 
                    ep.cod_paso_actual,
                    epc.nombre AS nombre_paso,
                    epc.numero_orden,
                    epc.template_parcial,
                    epp.estado,
                    epp.fecha_inicio,
                    epp.fecha_completado
                FROM examen_proceso ep
                LEFT JOIN examen_paso_catalogo epc ON epc.cod_paso = ep.cod_paso_actual
                LEFT JOIN examen_proceso_paso epp ON epp.cod_proceso = ep.cod_proceso 
                    AND epp.cod_paso = ep.cod_paso_actual
                WHERE ep.cod_proceso = :proceso';

        $result = $this->execute($sql, ['proceso' => $codProceso]);
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
     * Registra un nuevo documento en la base de datos (con enlace a Google Drive).
     * T-09/T-14
     */
    public function guardarDocumentoDb(array $data): int
    {
        $sql = 'INSERT INTO examen_documento 
                    (cod_proceso, cod_requisito, drive_file_id, drive_view_link, drive_download_link, nombre_archivo, mime_type, tamano_bytes, subido_por)
                VALUES 
                    (:proceso, :requisito, :drive_id, :view_link, :download_link, :nombre, :mime, :tamano, :usuario)';
        
        $params = [
            'proceso'       => $data['cod_proceso'],
            'requisito'     => $data['cod_requisito'],
            'drive_id'      => $data['drive_file_id'],
            'view_link'     => $data['drive_view_link'],
            'download_link' => $data['drive_download_link'],
            'nombre'        => $data['nombre_archivo'],
            'mime'          => $data['mime_type'],
            'tamano'        => $data['tamano_bytes'],
            'usuario'       => $data['subido_por']
        ];

        $this->adapter->createStatement($sql, $params)->execute();
        return (int) $this->adapter->getDriver()->getLastGeneratedValue();
    }

    /**
     * Registra la revisión de un documento (Paso 1).
     * T-09
     */
    public function guardarRevisionDocumento(array $data): bool
    {
        $sql = 'INSERT INTO examen_revision_documento 
                    (cod_documento, cod_proceso, cod_requisito, estado, motivo_rechazo, revisado_por)
                VALUES 
                    (:doc, :proceso, :req, :estado, :motivo, :usuario)';
        
        $params = [
            'doc'     => $data['cod_documento'],
            'proceso' => $data['cod_proceso'],
            'req'     => $data['cod_requisito'],
            'estado'  => $data['estado'],
            'motivo'  => $data['motivo_rechazo'] ?? null,
            'usuario' => $data['revisado_por']
        ];

        $statement = $this->adapter->createStatement($sql, $params);
        return (bool) $statement->execute()->getAffectedRows();
    }

    /**
     * Guarda/Actualiza la revisión de múltiples requisitos en bloque (Paso 1).
     * Usa UPSERT: si ya existe una revisión para (cod_proceso, cod_requisito),
     * la actualiza; de lo contrario, inserta una nueva.
     * T-25
     */
    public function guardarRevisionesBulk(int $codProceso, array $requisitos, int $codUsuario): bool
    {
        foreach ($requisitos as $req) {
            $codRequisito = (int) $req['cod_requisito'];
            $codDocumento = !empty($req['cod_documento']) ? (int) $req['cod_documento'] : null;
            $estado       = $req['estado_evaluacion'] ?? 'pendiente';
            $motivo       = !empty($req['observacion']) ? $req['observacion'] : null;

            if ($codDocumento !== null) {
                // Validación de negocio
                if ($estado === 'rechazado' && empty($motivo)) {
                    throw new \Exception('Debe indicar motivo de rechazo');
                }

                $sqlInsert = '
                    INSERT INTO examen_revision_documento
                        (cod_documento, cod_proceso, cod_requisito, estado, motivo_rechazo, revisado_por)
                    VALUES
                        (:doc, :proceso, :req, :estado, :motivo, :usuario)
                ';

                $this->adapter->createStatement($sqlInsert, [
                    'doc'     => $codDocumento,
                    'proceso' => $codProceso,
                    'req'     => $codRequisito,
                    'estado'  => $estado,
                    'motivo'  => $motivo,
                    'usuario' => $codUsuario,
                ])->execute();
            }
        }
        return true;
    }

    /**
     * Guarda el checklist de recepción de documentos físicos (Paso 2).
     * T-09
     */
    public function guardarDocumentacionFisica(int $codProceso, array $documentos, int $codUsuario): bool
    {
        foreach ($documentos as $req) {
            $sql = 'INSERT INTO examen_documento_fisico 
                        (cod_proceso, cod_requisito, recibido, fecha_recepcion, observaciones, recibido_por)
                    VALUES 
                        (:proceso, :req, :recibido, :fecha, :obs, :usuario)
                    ON DUPLICATE KEY UPDATE 
                        recibido = VALUES(recibido), 
                        fecha_recepcion = VALUES(fecha_recepcion), 
                        observaciones = VALUES(observaciones),
                        recibido_por = VALUES(recibido_por)';
            
            $params = [
                'proceso'  => $codProceso,
                'req'      => $req['cod_requisito'],
                'recibido' => $req['recibido'] ? 1 : 0,
                'fecha'    => $req['recibido'] ? date('Y-m-d H:i:s') : null,
                'obs'      => $req['observaciones'] ?? null,
                'usuario'  => $codUsuario
            ];

            $this->adapter->createStatement($sql, $params)->execute();
        }
        return true;
    }

    /**
     * Guarda la terna de examinadores y la programación del examen (Paso 3).
     * T-09
     */
    public function guardarTerna(int $codProceso, array $terna, array $programacion, int $codUsuario): bool
    {
        foreach ($terna as $rol => $datos) {
            $sql = 'INSERT INTO examen_terna 
                        (cod_proceso, rol, nombre_examinador, numero_colegiado, correo, fecha_examen, hora_inicio, registrado_por)
                    VALUES 
                        (:proceso, :rol, :nombre, :colegiado, :correo, :fecha, :hora, :usuario)
                    ON DUPLICATE KEY UPDATE 
                        nombre_examinador = VALUES(nombre_examinador), 
                        numero_colegiado = VALUES(numero_colegiado), 
                        correo = VALUES(correo),
                        fecha_examen = VALUES(fecha_examen),
                        hora_inicio = VALUES(hora_inicio),
                        registrado_por = VALUES(registrado_por)';
            
            $params = [
                'proceso'   => $codProceso,
                'rol'       => $rol,
                'nombre'    => $datos['nombre'],
                'colegiado' => $datos['colegiado'] ?? null,
                'correo'    => $datos['correo'] ?? null,
                'fecha'     => $programacion['fecha'] ?? null,
                'hora'      => $programacion['hora'] ?? null,
                'usuario'   => $codUsuario
            ];

            $this->adapter->createStatement($sql, $params)->execute();
        }
        return true;
    }

    /**
     * Avanza el proceso al siguiente paso definido en el catálogo.
     * T-10
     */
    public function avanzarPaso(int $codProceso, int $codPasoActual): bool
    {
        $role = $this->layout()->role;
        $userRolId     = $role->getCode();

        // 1. Obtener el orden del paso actual
        $sqlActual = 'SELECT cod_tipo_examen, numero_orden FROM examen_paso_catalogo WHERE cod_paso = :paso';
        $resActual = $this->execute($sqlActual, ['paso' => $codPasoActual]);
        if (empty($resActual)) return false;

        $tipoExamen = $resActual[0]['cod_tipo_examen']; // puede ser NULL
        $ordenActual = $resActual[0]['numero_orden'];

        // 2. Cerrar el paso actual
        $sqlCerrar = 'UPDATE examen_proceso_paso 
                      SET fecha_completado = CURRENT_TIMESTAMP, 
                          estado = "completado",
                          completado_por = :usuario
                      WHERE cod_proceso = :proceso AND cod_paso = :paso';

        $this->adapter->createStatement($sqlCerrar, [
            'proceso' => $codProceso,
            'paso'    => $codPasoActual,
            'usuario' => $userRolId
        ])->execute();

        // 3. Buscar el siguiente paso en el orden
        $sqlSiguiente = 'SELECT cod_paso FROM examen_paso_catalogo 
                         WHERE (cod_tipo_examen = :tipo OR cod_tipo_examen IS NULL) 
                           AND numero_orden = :siguiente 
                           AND activo = 1';
        $resSiguiente = $this->execute($sqlSiguiente, ['tipo' => $tipoExamen, 'siguiente' => $ordenActual + 1]);

        if (!empty($resSiguiente)) {
            $codSiguiente = $resSiguiente[0]['cod_paso'];

            // 4. Actualizar el proceso maestro
            $sqlMaster = 'UPDATE examen_proceso SET cod_paso_actual = :siguiente WHERE cod_proceso = :proceso';
            $this->adapter->createStatement($sqlMaster, ['siguiente' => $codSiguiente, 'proceso' => $codProceso])->execute();

            // 5. Iniciar el nuevo paso
            $sqlNuevo = 'INSERT INTO examen_proceso_paso (cod_proceso, cod_paso, estado, fecha_inicio)
                         VALUES (:proceso, :paso, "en_progreso", CURRENT_TIMESTAMP)
                         ON DUPLICATE KEY UPDATE estado = "en_progreso", fecha_inicio = CURRENT_TIMESTAMP';
            $this->adapter->createStatement($sqlNuevo, ['proceso' => $codProceso, 'paso' => $codSiguiente])->execute();
            
            return true;
        }

        // Si no hay siguiente paso, el proceso finaliza
        $sqlFin = 'UPDATE examen_proceso SET cod_paso_actual = NULL WHERE cod_proceso = :proceso';
        $this->adapter->createStatement($sqlFin, ['proceso' => $codProceso])->execute();
        return true;
    }

    /**
     * Registra un evento en la tabla de auditoría (examen_historial).
     * T-10
     */
    public function registrarHistorial(array $data): void
    {
        $sql = 'INSERT INTO examen_historial 
                    (cod_proceso, cod_usuario, tipo_evento, descripcion, datos_anteriores, datos_nuevos, ip_address, user_agent)
                VALUES 
                    (:proceso, :usuario, :evento, :desc, :ant, :nue, :ip, :ua)';
        
        $params = [
            'proceso' => $data['cod_proceso'],
            'usuario' => $data['cod_usuario'],
            'evento'  => $data['tipo_evento'],
            'desc'    => $data['descripcion']      ?? null,
            'ant'     => isset($data['datos_anteriores']) ? json_encode($data['datos_anteriores']) : null,
            'nue'     => isset($data['datos_nuevos'])     ? json_encode($data['datos_nuevos'])     : null,
            'ip'      => $data['ip_address']      ?? null,
            'ua'      => $data['user_agent']      ?? null
        ];

        $this->adapter->createStatement($sql, $params)->execute();
    }
}