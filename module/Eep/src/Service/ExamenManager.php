<?php

namespace Eep\Service;

use Laminas\Db\Adapter\AdapterInterface;
use Laminas\Db\Sql\Sql;
use Laminas\Db\ResultSet\ResultSet;

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
                    et.nombre AS tipo_examen,
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
                    c.nombre_carrera AS carrera,
                    p.anio AS pensum_anio,
                    p.descripcion AS pensum_nombre,
                    co.nombre AS cohorte_nombre,
                    co.fecha_inicio AS cohorte_fecha
                FROM usuario u
                LEFT JOIN inscripcion i ON i.cod_usuario = u.cod_usuario
                LEFT JOIN carrera c ON c.cod_carrera = i.cod_carrera
                LEFT JOIN pensum p ON p.cod_pensum = i.cod_pensum
                LEFT JOIN cohorte co ON co.cod_cohorte = i.cod_cohorte
                WHERE u.registro_academico = :carne
                ORDER BY i.anio DESC
                LIMIT 1';

        $result = $this->execute($sql, ['carne' => $carne]);
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
                  AND (erd.cod_tipo_examen = :tipo OR erd.cod_tipo_examen IS NULL)
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
                JOIN examen_paso_catalogo epc ON epc.cod_paso = ep.cod_paso_actual
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
    public function avanzarPaso(int $codProceso, int $codPasoActual, int $codUsuario): bool
    {
        // 1. Obtener el orden del paso actual
        $sqlActual = 'SELECT cod_tipo_examen, numero_orden FROM examen_paso_catalogo WHERE cod_paso = :paso';
        $resActual = $this->execute($sqlActual, ['paso' => $codPasoActual]);
        if (empty($resActual)) return false;

        $tipoExamen = $resActual[0]['cod_tipo_examen'];
        $ordenActual = $resActual[0]['numero_orden'];

        // 2. Cerrar el paso actual
        $sqlCerrar = 'UPDATE examen_proceso_paso 
                      SET fecha_completado = CURRENT_TIMESTAMP, 
                          estado = "completado",
                          completado_por = :usuario
                      WHERE cod_proceso = :proceso AND cod_paso = :paso';
        $this->adapter->createStatement($sqlCerrar, ['proceso' => $codProceso, 'paso' => $codPasoActual, 'usuario' => $codUsuario])->execute();

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