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

    /**
     * Código del tipo de examen "Público General" (examen general).
     * Se usa como constante porque este tipo aplica a todas las maestrías
     * cuando el proceso entra en la fase examen_general.
     */
    const TIPO_PUBLICO_GENERAL = 99;

    public function getTiposExamen(): array
    {
        $sql = 'SELECT et.cod_tipo_examen, et.nombre, et.descripcion, et.cod_carrera,
                        nc.nombre AS carrera_nombre
                FROM examen_tipo et
                LEFT JOIN nombre_carrera nc ON nc.cod_carrera = et.cod_carrera
                WHERE et.activo = 1
                ORDER BY et.cod_tipo_examen = ' . self::TIPO_PUBLICO_GENERAL . ' ASC, nc.nombre ASC';

        return $this->execute($sql);
    }

    /**
     * Determina el cod_tipo_examen a usar según la fase actual del proceso.
     *
     * - Para examen_privado: usa el tipo asignado al proceso (1=Privado General,
     *   2=Privado Gerencia), ya que cada maestría tiene sus propios requisitos.
     * - Para examen_general: SIEMPRE retorna tipo 3 (Público General), que es
     *   el único tipo de examen general en la institución.
     * - Para otras fases (carta_examinadores, autorizacion_impresion): retorna
     *   el tipo del proceso (no aplican requisitos por fase en esos pasos).
     */
    public function getTipoExamenParaFase(int $codTipoExamenProceso, string $fase): int
    {
        if ($fase === 'examen_general') {
            return self::TIPO_PUBLICO_GENERAL;
        }
        return $codTipoExamenProceso;
    }

    /**
     * Obtiene el cod_paso del catálogo para una fase y número de orden dados.
     * Ejemplo: getCodPasoPorFaseYOrden('examen_general', 1) retorna 6.
     *
     * @param string $fase         Fase del proceso (examen_privado, examen_general, etc.)
     * @param int    $numeroOrden  Número de orden dentro de la fase (1, 2, 3, 4)
     * @return int|null            cod_paso del catálogo, o null si no existe
     */
    public function getCodPasoPorFaseYOrden(string $fase, int $numeroOrden): ?int
    {
        $sql = 'SELECT cod_paso
                FROM examen_paso_catalogo
                WHERE fase = :fase
                  AND numero_orden = :orden
                  AND (cod_tipo_examen IS NULL)
                  AND activo = 1
                LIMIT 1';

        $result = $this->execute($sql, ['fase' => $fase, 'orden' => $numeroOrden]);
        return !empty($result) ? (int) $result[0]['cod_paso'] : null;
    }

    /**
     * Busca estudiantes por registro académico o nombre para asignarlos
     * a un proceso de graduación. Solo retorna usuarios con rol Estudiante (cod_rol = 6).
     *
     * @param string $termino  Texto de búsqueda (registro académico, nombre o apellido)
     * @return array           Lista de estudiantes encontrados
     */
    public function buscarEstudiantesParaGraduacion(string $termino): array
    {
        $termino = trim($termino);
        if (empty($termino)) {
            return [];
        }

        $sql = "SELECT DISTINCT
                    u.cod_usuario,
                    u.registro_academico,
                    u.cui,
                    u.nombres,
                    u.apellidos,
                    u.nombre_completo
                    u.registro_academico,
                    et.cod_tipo_examen     AS tipo_cod_examen,
                    et.nombre              AS tipo_examen,
                    ep.fecha_solicitud,
                    ep.cod_paso_actual,
                    epc.nombre             AS nombre_paso_actual,
                    epc.numero_orden,
                    epc.fase               AS fase_paso_actual,
                    ep.tema_tesis,
                    COALESCE(epp.estado, 'pendiente') AS estado_paso,
                    ep.cancelado,
                    ep.hora_apertura_evaluacion,
                    ep.hora_cierre_evaluacion,
                    ep.fecha_examen_privado,
                    ep.hora_examen_privado,
                    ep.numero_acta,
                    ep.estado_acta,
                    ep.fecha_generacion_acta
                FROM examen_proceso ep
                JOIN usuario u              ON u.cod_usuario      = ep.cod_usuario
                JOIN examen_tipo et         ON et.cod_tipo_examen = ep.cod_tipo_examen
                LEFT JOIN examen_paso_catalogo epc ON epc.cod_paso = ep.cod_paso_actual
                LEFT JOIN examen_proceso_paso epp  ON epp.cod_proceso = ep.cod_proceso
                    AND epp.cod_paso = ep.cod_paso_actual
                WHERE ep.cod_proceso = :proceso
                LIMIT 1";

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
                    erd.archivo_apoyo,
                    erd.formatos_permitidos,
                    erd.tamano_max_mb,
                    erd.obligatorio,
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
                LEFT JOIN examen_revision_documento er ON er.cod_revision = (
                    SELECT er2.cod_revision
                    FROM examen_revision_documento er2
                    WHERE er2.cod_documento = ed.cod_documento
                    ORDER BY er2.fecha_revision DESC, er2.cod_revision DESC
                    LIMIT 1
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
        $pagina        = $filtros['pagina'] ?? 1;
        $limite        = $filtros['limite'] ?? 20;
        $estado        = $filtros['estado'] ?? null;
        $codTipoExamen = $filtros['cod_tipo_examen'] ?? null;
        $numeroPaso    = isset($filtros['numero_paso']) ? (int) $filtros['numero_paso'] : null;
        $busqueda      = !empty($filtros['busqueda']) ? trim($filtros['busqueda']) : null;

        $offset = ($pagina - 1) * $limite;

        $whereEstado = '';
        if ($estado) {
            $whereEstado = 'AND ep.estado = :estado';
        }

        $whereCancelado = 'AND ep.cancelado = 0';
        $whereEstadoPaso = '';
        if (!empty($filtros['estado_paso'])) {
            switch ($filtros['estado_paso']) {
                case 'activo':
                    $whereEstadoPaso = 'AND ep.cod_paso_actual IS NOT NULL';
                    break;
                case 'finalizado':
                    $whereEstadoPaso = 'AND ep.cod_paso_actual IS NULL';
                    break;
                case 'cancelado':
                    $whereCancelado = '';
                    $whereEstadoPaso = 'AND ep.cancelado = 1';
                    break;
                case 'completado':
                    $whereEstadoPaso = 'AND ep.cod_paso_actual IS NULL';
                    break;
                case 'en_progreso':
                    $whereEstadoPaso = 'AND epp.estado = "en_progreso"';
                    break;
                case 'pendiente':
                    $whereEstadoPaso = 'AND ep.cod_paso_actual IS NOT NULL AND (epp.estado IS NULL OR epp.estado = "pendiente")';
                    break;
                case 'rechazado':
                    $whereEstadoPaso = 'AND epp.estado = "rechazado"';
                    break;
            }
        }

        $whereTipo = '';
        // Inicializar params aquí para poder agregar fase_tipo antes
        $params = [];
        if ($codTipoExamen) {
            // Filtrar por tipo de examen basado en la fase actual del paso
            // Tipo 99 (Público General) = fase examen_general
            // Tipos 1-2 (Privado) = fase examen_privado
            if ($codTipoExamen == self::TIPO_PUBLICO_GENERAL) {
                $whereTipo = 'AND epc.fase = :fase_tipo';
                $params['fase_tipo'] = 'examen_general';
            } else {
                $whereTipo = 'AND epc.fase = :fase_tipo AND ep.cod_tipo_examen = :cod_tipo_examen';
                $params['fase_tipo'] = 'examen_privado';
            }
        }

        $wherePaso = '';
        if ($numeroPaso !== null) {
            $wherePaso = 'AND epc.numero_orden = :numero_paso';
        }

        $whereBusqueda = '';
        if ($busqueda) {
            $whereBusqueda = 'AND (
                u.nombres LIKE :busqueda
                OR u.apellidos LIKE :busqueda
                OR u.registro_academico LIKE :busqueda
            )';
            $params['busqueda'] = '%' . $busqueda . '%';
        }

        $sql = "SELECT
                    ep.cod_proceso,
                    ep.cod_usuario,
                    CONCAT(u.nombres, ' ', u.apellidos) AS nombre_completo,
                    u.nombres,
                    u.apellidos,
                    u.registro_academico,
                    u.sexo,
                    u.correo,
                    u.telefono,
                    p.descripcion  AS pensum_nombre,
                    et.cod_carrera,
                    c.nombre_actual AS carrera
                FROM examen_proceso ep
                JOIN usuario u        ON u.cod_usuario  = ep.cod_usuario
                JOIN examen_tipo et   ON et.cod_tipo_examen = ep.cod_tipo_examen
                LEFT JOIN carrera c   ON c.cod_carrera = et.cod_carrera
                LEFT JOIN inscripcion i ON i.cod_usuario = u.cod_usuario
                LEFT JOIN pensum p     ON p.cod_pensum  = i.cod_pensum
                WHERE ep.cod_proceso = :proceso
                LIMIT 1";

        $result = $this->execute($sql, ['proceso' => $codProceso]);
        return $result[0] ?? null;
    }

    /**
     * Obtiene el catálogo de requisitos (documentos) para un paso y tipo de examen específicos.
     * Filtra por cod_paso Y cod_tipo_examen para obtener solo los requisitos
     * que corresponden a la fase correcta del proceso.
     * T-05
     */
    public function getRequisitosDocumento(int $codPaso, int $codTipoExamen): array
    {
        $sql = 'SELECT cod_requisito, nombre, descripcion, archivo_apoyo, tipo_entrega, obligatorio, formatos_permitidos, tamano_max_mb
                FROM examen_requisito_documento
                WHERE cod_paso = :paso
                  AND cod_tipo_examen = :tipo
                  AND activo = 1
                ORDER BY orden_display ASC';

        return $this->execute($sql, ['paso' => $codPaso, 'tipo' => $codTipoExamen]);
    }

    /**
     * T-22.1: Gestión administrativa de requisitos
     */
    public function getTodosRequisitos($examenTipo): array
    {
        $sql = 'SELECT cod_requisito, nombre, descripcion, archivo_apoyo 
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

    public function getInstruccionesEntregaFisica(int $codTipoExamen): ?string
    {
        $sql = 'SELECT instrucciones_entrega_fisica FROM examen_tipo WHERE cod_tipo_examen = :tipo';
        $result = $this->execute($sql, ['tipo' => $codTipoExamen]);
        return $result[0]['instrucciones_entrega_fisica'] ?? null;
    }

    public function guardarInstruccionesEntregaFisica(int $codTipoExamen, ?string $instrucciones): bool
    {
        $this->execute(
            'UPDATE examen_tipo SET instrucciones_entrega_fisica = :instrucciones WHERE cod_tipo_examen = :tipo',
            ['tipo' => $codTipoExamen, 'instrucciones' => $instrucciones]
        );
        return true;
    }

    public function upsertRequisito($data): int
    {
        if (isset($data['id']) && (int)$data['id'] > 0) {
            $sql = 'UPDATE examen_requisito_documento SET nombre = :nombre, descripcion = :descripcion';
            $params = [
                'id' => $data['id'],
                'nombre' => $data['nombre'],
                'descripcion' => $data['descripcion'],
            ];

            if (array_key_exists('archivo_apoyo', $data)) {
                $sql .= ', archivo_apoyo = :archivo';
                $params['archivo'] = $data['archivo_apoyo'] ?? null;
            }

            $sql .= ', formatos_permitidos = :formatos, tamano_max_mb = :tamano, tipo_entrega = :tipo_entrega, obligatorio = :obligatorio WHERE cod_requisito = :id';
            $params['formatos'] = $data['formatos_permitidos'] ?? null;
            $params['tamano'] = $data['tamano_max_mb'] ?? 10;
            $params['tipo_entrega'] = $data['tipo_entrega'] ?? 'digital';
            $params['obligatorio'] = $data['obligatorio'] ?? 1;

            $this->execute($sql, $params);
            return (int)$data['id'];
        } else {
            $columns = 'nombre, descripcion, cod_tipo_examen, cod_paso, formatos_permitidos, tamano_max_mb';
            $values  = ':nombre, :descripcion, :tipo, :paso, :formatos, :tamano';
            $params = [
                'nombre'          => $data['nombre'],
                'descripcion'     => $data['descripcion'],
                'tipo'            => $data['cod_tipo_examen'] ?? null,
                'paso'            => $data['cod_paso'],
                'formatos'        => $data['formatos_permitidos'] ?? null,
                'tamano'          => $data['tamano_max_mb'] ?? 10,
            ];

            if (array_key_exists('archivo_apoyo', $data)) {
                $columns .= ', archivo_apoyo';
                $values  .= ', :archivo';
                $params['archivo'] = $data['archivo_apoyo'] ?? null;
            }

            $columns .= ', tipo_entrega, obligatorio, activo';
            $values  .= ', :tipo_entrega, :obligatorio, 1';
            $params['tipo_entrega'] = $data['tipo_entrega'] ?? 'digital';
            $params['obligatorio']  = $data['obligatorio'] ?? 1;

            $this->execute("INSERT INTO examen_requisito_documento ({$columns}) VALUES ({$values})", $params);
            return (int) $this->adapter->getDriver()->getLastGeneratedValue();
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
        $sql = "SELECT 
                    ed.cod_documento,
                    ed.cod_requisito,
                    ed.nombre_original,
                    ed.version,
                    ed.fecha_subida,
                    al.nombre_md5,
                    al.extension,
                    CONCAT('/student-graduation/ver-documento?h=', al.nombre_md5) AS url_ver,
                    er.estado AS estado_revision,
                    er.motivo_rechazo,
                    er.fecha_revision
                FROM examen_documento ed
                LEFT JOIN archivo_local al ON al.cod_documento = ed.cod_documento
                LEFT JOIN examen_revision_documento er ON er.cod_documento = ed.cod_documento
                WHERE ed.cod_proceso = :proceso
                  AND ed.es_version_actual = 1
                  AND ed.eliminado = 0";

        return $this->execute($sql, ['proceso' => $codProceso]);
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

                // Actualizar si ya existe una revisión para este documento,
                // insertar si no existe (evita duplicados sin requerir UNIQUE KEY).
                $sqlUpdate = '
                    UPDATE examen_revision_documento
                    SET estado         = :estado,
                        motivo_rechazo = :motivo,
                        revisado_por   = :usuario,
                        fecha_revision = CURRENT_TIMESTAMP
                    WHERE cod_documento = :doc
                    ORDER BY fecha_revision DESC
                    LIMIT 1
                ';
                $result = $this->adapter->createStatement($sqlUpdate, [
                    'doc'     => $codDocumento,
                    'estado'  => $estado,
                    'motivo'  => $motivo,
                    'usuario' => $codUsuario,
                ])->execute();

                if ($result->getAffectedRows() == 0) {
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
        }
        return true;
    }

    // * MANEJO DE DOCUMENTACION FISICA (ADMINISTRACION) ------------------------

    /**
     * Inicializacion de proceso para documentos físicos (Paso 2).
     * T-09
     */
    public function InitDocumentacionFisica(int $codProceso, array $documentos): bool
    {
        foreach ($documentos as $req) {
            $sql = 'INSERT INTO examen_documento_fisico 
                        (cod_proceso, cod_requisito)
                    VALUES 
                        (:proceso, :req)';
            
            $params = [
                'proceso'  => $codProceso,
                'req'      => $req['cod_requisito']
            ];

            $this->adapter->createStatement($sql, $params)->execute();
        }
        return true;
    }

    /**
     * Obtiene el checklist de documentos físicos recibidos para el Paso 2.
     * T-06
     */
    public function getDocumentosFisicos(int $codProceso, int $codTipoExamen): array
    {
        $sql = 'SELECT 
                    erd.cod_requisito,
                    erd.nombre,
                    edf.recibido AS estado,
                    edf.fecha_recepcion
                FROM examen_requisito_documento erd
                LEFT JOIN examen_documento_fisico edf
                    ON edf.cod_requisito = erd.cod_requisito 
                    AND edf.cod_proceso = :proceso
                WHERE
                    erd.cod_tipo_examen = :tipo
                    AND erd.activo = 1
                ORDER BY erd.orden_display ASC';

        return $this->execute($sql, ['proceso' => $codProceso, 'tipo' => $codTipoExamen]);
    }
    
    /**
     * Guarda el checklist de recepción de documentos físicos (Paso 2).
     * T-09
     */
    public function guardarDocumentacionFisica(int $codProceso, array $documentos, int $codUsuario): bool
    {
        foreach ($documentos as $req) {
            $sql = 'UPDATE examen_documento_fisico 
                    SET recibido = :recibido,
                        fecha_recepcion = :fecha,
                        recibido_por = :usuario
                    WHERE cod_proceso = :proceso AND cod_requisito = :req';
            
            $params = [
                'proceso'  => $codProceso,
                'req'      => $req['cod_requisito'],
                'recibido' => $req['recibido'] ? 1 : 0,
                'fecha'    => $req['recibido'] ? date('Y-m-d H:i:s') : null,
                'usuario'  => $codUsuario
            ];

            $this->adapter->createStatement($sql, $params)->execute();
        }
        return true;
    }
    // * -------------------------------------------------------------------------------

    /**
     * Guarda la terna de examinadores y la programación del examen (Paso 3).
     * T-09
     * 
     * @param int    $codProceso  ID del proceso de graduación
     * @param array  $terna       Array de examinadores (nombre, colegiado, correo, tipo, posicion)
     * @param int    $codUsuario  ID del usuario que registra la terna
     * @param string $fase        Fase del examen: 'examen_privado' o 'examen_general'
     */
    public function guardarTerna(int $codProceso, array $terna, int $codUsuario): bool
    {
        foreach ($terna as $datos) {
            $tipo = $datos['tipo_examinador'] ?? 'externo';
            $codUsuarioExaminador = !empty($datos['cod_usuario']) ? (int)$datos['cod_usuario'] : null;
            $nombre = $datos['nombre'] ?? null;
            $colegiado = $datos['colegiado'] ?? null;
            $titulo = $datos['titulo'] ?? null;
            $correo = $datos['correo'] ?? null;

            // 1. Buscar o crear en el catálogo de examinadores
            $codExaminador = $this->buscarOCrearExaminador(
                $tipo,
                $codUsuarioExaminador,
                $nombre,
                $colegiado,
                $titulo,
                $correo
            );

            if (!$codExaminador) {
                continue;
            }

            // 2. Guardar en la terna (relación) — solo examen_privado
            $sql = 'INSERT INTO examen_terna
                        (cod_proceso, cod_examinador, posicion, registrado_por)
                    VALUES
                        (:proceso, :examinador, :posicion, :usuario)
                    ON DUPLICATE KEY UPDATE
                        cod_examinador = VALUES(cod_examinador),
                        registrado_por = VALUES(registrado_por)';

            $params = [
                'proceso'    => $codProceso,
                'examinador' => $codExaminador,
                'posicion'   => (int)$datos['posicion'],
                'usuario'    => $codUsuario
            ];

            $this->adapter->createStatement($sql, $params)->execute();
        }

        return true;
    }

    /**
     * Busca o crea un examinador en el catálogo examen_examinador.
     * Para internos: busca por cod_usuario. Para externos: busca por nombre+colegiado.
     *
     * @return int|null cod_examinador o null si falla
     */
    private function buscarOCrearExaminador(
        string $tipo,
        ?int $codUsuario,
        ?string $nombre,
        ?string $colegiado,
        ?string $tituloProfesional,
        ?string $correo
    ): ?int {
        // Buscar existente
        if ($tipo === 'interno' && $codUsuario) {
            $sql = 'SELECT cod_examinador FROM examen_examinador
                    WHERE cod_usuario = :cod_usuario AND tipo_examinador = "interno"
                    LIMIT 1';
            $res = $this->execute($sql, ['cod_usuario' => $codUsuario]);
            if (!empty($res[0]['cod_examinador'])) {
                return (int)$res[0]['cod_examinador'];
            }
        } else {
            $sql = 'SELECT cod_examinador FROM examen_examinador
                    WHERE nombre_examinador = :nombre
                      AND (numero_colegiado = :colegiado OR (numero_colegiado IS NULL AND :colegiado IS NULL))
                      AND tipo_examinador = "externo"
                    LIMIT 1';
            $res = $this->execute($sql, [
                'nombre'    => $nombre,
                'colegiado' => $colegiado
            ]);
            if (!empty($res[0]['cod_examinador'])) {
                $codExaminador = (int)$res[0]['cod_examinador'];
                // Actualizar título si cambió
                if ($tituloProfesional !== null) {
                    $this->adapter->createStatement(
                        'UPDATE examen_examinador SET titulo_profesional = :titulo WHERE cod_examinador = :cod_examinador',
                        ['titulo' => $tituloProfesional, 'cod_examinador' => $codExaminador]
                    )->execute();
                }
                return $codExaminador;
            }
        }

        // Crear nuevo
        $sql = 'INSERT INTO examen_examinador
                    (cod_usuario, nombre_examinador, numero_colegiado, titulo_profesional, correo, tipo_examinador)
                VALUES
                    (:cod_usuario, :nombre, :colegiado, :titulo, :correo, :tipo)';

        $this->adapter->createStatement($sql, [
            'cod_usuario' => $codUsuario,
            'nombre'      => $nombre,
            'colegiado'   => $colegiado,
            'titulo'      => $tituloProfesional,
            'correo'      => $correo,
            'tipo'        => $tipo
        ])->execute();

        return (int)$this->adapter->getDriver()->getLastGeneratedValue();
    }

    /**
     * Obtiene la lista de docentes internos (rol = 5 o 11) para usar en dropdown.
     * Solo usuarios con número de colegiado son elegibles como examinadores.
     */
    public function getDocentes(): array
    {
        $sql = 'SELECT
                    u.cod_usuario,
                    CONCAT(u.nombres, " ", u.apellidos) AS nombre_completo,
                    u.numero_colegiado AS colegiado,
                    u.titulo_profesional AS titulo,
                    u.correo
                FROM usuario u
                JOIN usuario_rol ur ON ur.cod_usuario = u.cod_usuario
                WHERE ur.cod_rol IN (5, 11)
                GROUP BY u.cod_usuario
                ORDER BY u.nombres, u.apellidos';

        return $this->execute($sql);
    }

    /**
     * Obtiene los datos de un docente interno por su cod_usuario.
     */
    public function getDocentePorCodUsuario(int $codUsuario): ?array
    {
        $sql = 'SELECT
                    u.cod_usuario,
                    CONCAT(u.nombres, " ", u.apellidos) AS nombre_completo,
                    u.numero_colegiado AS colegiado,
                    u.titulo_profesional AS titulo,
                    u.correo
                FROM usuario u
                JOIN usuario_rol ur ON ur.cod_usuario = u.cod_usuario
                WHERE ur.cod_rol IN (5, 11)
                  AND u.cod_usuario = :cod_usuario
                LIMIT 1';

        $result = $this->execute($sql, ['cod_usuario' => $codUsuario]);
        return $result[0] ?? null;
    }

    /**
     * Verifica cuáles procesos NO tienen madrina/padrino configurado.
     * Devuelve array con cod_proceso, nombres y apellidos de los que faltan.
     */
    public function verificarMadrinaPadrinoPorProcesos(array $codProcesos): array
    {
        if (empty($codProcesos)) {
            return [];
        }

        $placeholders = implode(',', array_fill(0, count($codProcesos), '?'));
        $sql = "SELECT
                    ep.cod_proceso,
                    u.nombres,
                    u.apellidos
                FROM examen_proceso ep
                JOIN usuario u ON u.cod_usuario = ep.cod_usuario
                LEFT JOIN examen_madrina_padrino emp ON emp.cod_proceso = ep.cod_proceso
                WHERE ep.cod_proceso IN ($placeholders)
                  AND emp.cod_madrina_padrino IS NULL";

        return $this->execute($sql, $codProcesos);
    }

    /**
     * Sustituye un examinador en la terna de un proceso.
     * Solo permite cuando la evaluación está pendiente (no abierta).
     *
     * @param int $codProceso
     * @param int $posicion Posición del examinador a sustituir (1-3)
     * @param array $datosNuevoExaminador ['tipo', 'cod_usuario', 'nombre', 'colegiado', 'titulo', 'correo']
     * @return array ['success' => bool, 'message' => string, 'cod_examinador' => int|null]
     */
    public function sustituirExaminador(int $codProceso, int $posicion, array $datosNuevoExaminador): array
    {
        // Validar que la evaluación esté pendiente (no abierta)
        $estado = $this->getEstadoEvaluacion($codProceso);
        if (!$estado) {
            return ['success' => false, 'message' => 'Proceso no encontrado.', 'cod_examinador' => null];
        }

        if (!empty($estado['hora_apertura_evaluacion'])) {
            return ['success' => false, 'message' => 'No se puede sustituir examinadores porque la evaluación ya fue abierta.', 'cod_examinador' => null];
        }

        if ($posicion < 1 || $posicion > 3) {
            return ['success' => false, 'message' => 'Posición de examinador inválida.', 'cod_examinador' => null];
        }

        // Solo permitir examinadores internos
        $tipo = $datosNuevoExaminador['tipo'] ?? 'externo';
        if ($tipo !== 'interno') {
            return ['success' => false, 'message' => 'Solo se permiten examinadores internos.', 'cod_examinador' => null];
        }

        $codUsuario = !empty($datosNuevoExaminador['cod_usuario']) ? (int)$datosNuevoExaminador['cod_usuario'] : null;
        $colegiado = $datosNuevoExaminador['colegiado'] ?? null;
        $titulo = $datosNuevoExaminador['titulo'] ?? null;
        $correo = $datosNuevoExaminador['correo'] ?? null;

        // ... (resto del código del método, no se modifica)
    }

    /**
     * Guarda la programación (fecha y hora) del examen en la columna
     * correspondiente según la fase.
     *
     * - fase = 'examen_privado'  → fecha_examen_privado / hora_examen_privado
     * - fase = 'examen_general'  → fecha_examen_general / hora_examen_general
     */
    public function guardarProgramacionTerna(int $codProceso, array $programacion, int $codUsuario, string $fase = 'examen_privado'): bool
    {
        // Determinar las columnas correctas según la fase
        if ($fase === 'examen_general') {
            $colFecha = 'fecha_examen_general';
            $colHora  = 'hora_examen_general';
        } else {
            $colFecha = 'fecha_examen_privado';
            $colHora  = 'hora_examen_privado';
        }

        $sql = "UPDATE examen_proceso 
                SET {$colFecha} = :fecha, {$colHora} = :hora
                WHERE cod_proceso = :proceso";

        $params = [
            'proceso' => $codProceso,
            'fecha'   => !empty($programacion['fecha']) ? $programacion['fecha'] : null,
            'hora'    => !empty($programacion['hora'])  ? $programacion['hora']  : null
        ];

        $statement = $this->adapter->createStatement($sql, $params);
        $result = $statement->execute();
        
        return (bool) $result->getAffectedRows();
    }

    /**
     * Obtiene los examinadores asignados y la programación del examen privado.
     * La terna solo existe en la fase examen_privado.
     * T-07
     *
     * @param int $codProceso ID del proceso
     */
    public function getTerna(int $codProceso): array
    {
        // 1. Obtener los examinadores de la tabla terna con JOIN al catálogo
        // Para internos: nombre, correo, colegiado vienen de usuario
        // Para externos: vienen de examen_examinador
        $sqlTerna = 'SELECT
                        et.posicion,
                        eex.cod_examinador,
                        eex.tipo_examinador,
                        eex.cod_usuario,
                        COALESCE(u.nombres, eex.nombre_examinador) AS nombre_examinador,
                        COALESCE(u.apellidos, "") AS apellidos,
                        COALESCE(u.numero_colegiado, eex.numero_colegiado) AS numero_colegiado,
                        COALESCE(u.titulo_profesional, eex.titulo_profesional) AS titulo_profesional,
                        COALESCE(u.correo, eex.correo) AS correo
                    FROM examen_terna et
                    JOIN examen_examinador eex ON eex.cod_examinador = et.cod_examinador
                    LEFT JOIN usuario u ON u.cod_usuario = eex.cod_usuario
                    WHERE et.cod_proceso = :proceso
                    ORDER BY et.posicion';

        $rows = $this->execute($sqlTerna, ['proceso' => $codProceso]);

        // 2. Obtener fecha y hora del examen privado
        $sqlProceso = 'SELECT fecha_examen_privado AS fecha,
                              hora_examen_privado AS hora
                       FROM examen_proceso
                       WHERE cod_proceso = :proceso
                       LIMIT 1';

        $resProceso = $this->execute($sqlProceso, ['proceso' => $codProceso]);
        $prog = $resProceso[0] ?? ['fecha' => null, 'hora' => null];

        $terna = [
            'examinadores' => [],
            'programacion' => [
                'fecha' => $prog['fecha'],
                'hora'  => $prog['hora']
            ]
        ];

        foreach ($rows as $row) {
            $nombreCompleto = trim($row['nombre_examinador'] . ' ' . $row['apellidos']);
            
            $terna['examinadores'][] = [
                'cod_examinador' => $row['cod_examinador'],
                'cod_usuario'    => $row['cod_usuario'],
                'nombre'         => $nombreCompleto,
                'colegiado'      => $row['numero_colegiado'],
                'titulo'         => $row['titulo_profesional'],
                'correo'         => $row['correo'],
                'tipo'           => $row['tipo_examinador'],
                'posicion'       => $row['posicion']
            ];
        }

        return $terna;
    }

    /**
     * Obtiene el cod_usuario del examinador en una posición dada.
     * Retorna int|null (null si no tiene usuario de sistema, ej. externo).
     */
    public function getCodUsuarioExaminador(int $codProceso, int $posicion): ?int
    {
        $sql = 'SELECT eex.cod_usuario
                FROM examen_terna et
                JOIN examen_examinador eex ON eex.cod_examinador = et.cod_examinador
                WHERE et.cod_proceso = :proceso
                  AND et.posicion = :posicion
                LIMIT 1';
        $rows = $this->execute($sql, ['proceso' => $codProceso, 'posicion' => $posicion]);
        if (empty($rows)) {
            return null;
        }
        $codUsuario = $rows[0]['cod_usuario'];
        return $codUsuario !== null ? (int) $codUsuario : null;
    }

    /**
     * Avanza el proceso al siguiente paso definido en el catálogo.
     * T-10
     */
    public function avanzarPaso(int $codProceso, int $userAdminId): bool
    {
        // 0. Obtener el codPasoActual del proceso para validar que el paso que se intenta cerrar es el correcto
        $sqlValidar = 'SELECT cod_paso_actual FROM examen_proceso WHERE cod_proceso = :proceso';
        $resValidar = $this->execute($sqlValidar, ['proceso' => $codProceso]);

        // Validación adicional: si el proceso ya no tiene paso actual, no se puede avanzar
        if (empty($resValidar)) {
            return false;
        }

        $codPasoActual = (int)$resValidar[0]['cod_paso_actual'];

        // 1. Obtener el orden y fase del paso actual
        $sqlActual = 'SELECT cod_tipo_examen, numero_orden, fase FROM examen_paso_catalogo WHERE cod_paso = :paso';
        $resActual = $this->execute($sqlActual, ['paso' => $codPasoActual]);
        if (empty($resActual)) return false;
        $tipoExamen  = (int)$resActual[0]['cod_tipo_examen'];
        $ordenActual = (int)$resActual[0]['numero_orden'];
        $faseActual  = $resActual[0]['fase'];

        // 2. Cerrar el paso actual
        $sqlCerrar = 'UPDATE examen_proceso_paso 
                      SET fecha_completado = CURRENT_TIMESTAMP, 
                          estado = "completado",
                          completado_por = :usuario
                      WHERE cod_proceso = :proceso AND cod_paso = :paso';

        $this->adapter->createStatement($sqlCerrar, [
            'proceso' => $codProceso,
            'paso'    => $codPasoActual,
            'usuario' => $userAdminId
        ])->execute();

        error_log("DEBUG user 1: ".print_r($codProceso, true));
        error_log("DEBUG user 2: ".print_r($codPasoActual, true));
        error_log("DEBUG user 3: ".print_r($userAdminId, true));

        // 3. Determinar el siguiente paso según la fase actual
        //    - examen_privado         paso 1-3 → mismo fase, numero_orden + 1
        //    - examen_privado         paso 4   → carta_examinadores (su único paso)
        //    - carta_examinadores              → autorizacion_impresion (paso 6)
        //    - autorizacion_impresion          → examen_general, numero_orden = 1
        //    - examen_general         paso 1-3 → mismo fase, numero_orden + 1
        //    - examen_general         paso 4   → fin del proceso
        if ($faseActual === 'examen_privado' && $ordenActual < 4) {
            $sqlSiguiente = 'SELECT cod_paso FROM examen_paso_catalogo
                             WHERE fase = "examen_privado"
                               AND numero_orden = :siguiente
                               AND (cod_tipo_examen = :tipo OR cod_tipo_examen IS NULL)
                               AND activo = 1 LIMIT 1';
            $resSiguiente = $this->execute($sqlSiguiente, ['tipo' => $tipoExamen, 'siguiente' => $ordenActual + 1]);
        } elseif ($faseActual === 'examen_privado' && $ordenActual === 4) {
            error_log("[avanzarPaso] Buscando paso 5 (carta_examinadores). TipoExamen={$tipoExamen}");
            $sqlSiguiente = 'SELECT cod_paso, nombre, numero_orden, fase, cod_tipo_examen, activo 
                             FROM examen_paso_catalogo
                             WHERE fase = "carta_examinadores"
                               AND activo = 1 
                             ORDER BY numero_orden 
                             LIMIT 1';
            $resSiguiente = $this->execute($sqlSiguiente, []);
            error_log("[avanzarPaso] Resultado busqueda carta_examinadores: " . json_encode($resSiguiente));
        } elseif ($faseActual === 'carta_examinadores') {
            error_log("[avanzarPaso] Buscando paso 6 (autorizacion_impresion). TipoExamen={$tipoExamen}");
            // De carta_examinadores avanza al paso 6 (autorizacion_impresion)
            $sqlSiguiente = 'SELECT cod_paso, nombre, numero_orden, fase 
                             FROM examen_paso_catalogo
                             WHERE fase = "autorizacion_impresion"
                               AND activo = 1 
                             ORDER BY numero_orden 
                             LIMIT 1';
            $resSiguiente = $this->execute($sqlSiguiente, []);
            error_log("[avanzarPaso] Resultado busqueda autorizacion_impresion: " . json_encode($resSiguiente));
        } elseif ($faseActual === 'autorizacion_impresion') {
            // De autorizacion_impresion avanza al paso 1 de examen_general
            $sqlSiguiente = 'SELECT cod_paso FROM examen_paso_catalogo
                             WHERE fase = "examen_general"
                               AND numero_orden = 1
                               AND (cod_tipo_examen = :tipo OR cod_tipo_examen IS NULL)
                               AND activo = 1 LIMIT 1';
            $resSiguiente = $this->execute($sqlSiguiente, ['tipo' => $tipoExamen]);
        } elseif ($faseActual === 'examen_general' && $ordenActual < 4) {
            $sqlSiguiente = 'SELECT cod_paso FROM examen_paso_catalogo
                             WHERE fase = "examen_general"
                               AND numero_orden = :siguiente
                               AND (cod_tipo_examen = :tipo OR cod_tipo_examen IS NULL)
                               AND activo = 1 LIMIT 1';
            $resSiguiente = $this->execute($sqlSiguiente, ['tipo' => $tipoExamen, 'siguiente' => $ordenActual + 1]);
        } else {
            // examen_general paso 4 → fin
            $resSiguiente = [];
        }

        if (!empty($resSiguiente)) {
            $codSiguiente = $resSiguiente[0]['cod_paso'];
            error_log("[avanzarPaso] Avanzando al paso siguiente: cod_paso={$codSiguiente}");

            // 4. Actualizar el proceso maestro
            $sqlMaster = 'UPDATE examen_proceso SET cod_paso_actual = :siguiente WHERE cod_proceso = :proceso';
            $this->adapter->createStatement($sqlMaster, ['siguiente' => $codSiguiente, 'proceso' => $codProceso])->execute();

            // 5. Iniciar el nuevo paso
            $sqlNuevo = 'INSERT INTO examen_proceso_paso (cod_proceso, cod_paso, estado, fecha_inicio)
                         VALUES (:proceso, :paso, "en_progreso", CURRENT_TIMESTAMP)
                         ON DUPLICATE KEY UPDATE estado = "en_progreso", fecha_inicio = CURRENT_TIMESTAMP';
            $this->adapter->createStatement($sqlNuevo, ['proceso' => $codProceso, 'paso' => $codSiguiente])->execute();
            
            error_log("[avanzarPaso] Paso siguiente iniciado correctamente");
            return true;
        }

        // Si no hay siguiente paso, el proceso finaliza
        error_log("[avanzarPaso] No se encontró siguiente paso. Finalizando proceso.");
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

    /**
     * Obtiene los procesos de examen general (tipo 3) que completaron
     * el paso 2 (entrega de documentación) y están listos para recibir
     * la notificación grupal de acto de graduación.
     */
    public function getProcesosGeneralCompletados(): array
    {
        $sql = 'SELECT
                    ep.cod_proceso,
                    ep.cod_usuario,
                    u.nombres,
                    u.apellidos,
                    u.registro_academico,
                    u.correo,
                    c.nombre_actual AS carrera,
                    ep.fecha_solicitud,
                    MAX(epp.fecha_completado) AS fecha_completado,
                    CASE WHEN emp.cod_madrina_padrino IS NOT NULL THEN 1 ELSE 0 END AS tiene_madrina,
                    emp.nombre AS madrina_nombre,
                    emp.titulo_profesional AS madrina_titulo,
                    emp.tipo AS madrina_tipo
                FROM examen_proceso ep
                JOIN usuario u ON u.cod_usuario = ep.cod_usuario
                LEFT JOIN examen_madrina_padrino emp ON emp.cod_proceso = ep.cod_proceso
                LEFT JOIN carrera c ON c.cod_carrera = (
                    SELECT p.cod_carrera FROM asignacion_carrera ac
                    JOIN pensum p ON p.cod_pensum = ac.cod_pensum
                    WHERE ac.cod_usuario = u.cod_usuario LIMIT 1
                )
                JOIN examen_proceso_paso epp ON epp.cod_proceso = ep.cod_proceso
                JOIN examen_paso_catalogo epc ON epc.cod_paso = epp.cod_paso
                WHERE ep.cancelado = 0
                  AND epc.fase = "examen_general"
                  AND epp.estado = "completado"
                  AND ep.cod_paso_actual IS NULL
                  AND ep.fecha_examen_general IS NULL
                GROUP BY ep.cod_proceso, u.nombres, u.apellidos, u.registro_academico, u.correo, c.nombre_actual, ep.fecha_solicitud, emp.cod_madrina_padrino, emp.nombre, emp.titulo_profesional, emp.tipo
                ORDER BY u.apellidos, u.nombres';

        return $this->execute($sql, []);
    }

    /**
     * Cuenta cuántos procesos activos tienen TODOS los requisitos digitales
     * del paso actual ya subidos por el estudiante.
     */
    public function contarProcesosConDocumentacionCompleta(): int
    {
        $sql = 'SELECT
                    ep.cod_proceso,
                    ep.cod_paso_actual,
                    ep.cod_tipo_examen,
                    (SELECT COUNT(*)
                     FROM examen_requisito_documento erd
                     WHERE erd.cod_paso = ep.cod_paso_actual
                       AND erd.cod_tipo_examen = ep.cod_tipo_examen
                       AND erd.activo = 1
                       AND erd.tipo_entrega = "digital") AS req_total,
                    (SELECT COUNT(DISTINCT ed.cod_requisito)
                     FROM examen_documento ed
                     WHERE ed.cod_proceso = ep.cod_proceso
                       AND ed.es_version_actual = 1
                       AND ed.eliminado = 0
                       AND ed.cod_requisito IN (
                           SELECT erd2.cod_requisito
                           FROM examen_requisito_documento erd2
                           WHERE erd2.cod_paso = ep.cod_paso_actual
                             AND erd2.cod_tipo_examen = ep.cod_tipo_examen
                             AND erd2.activo = 1
                             AND erd2.tipo_entrega = "digital"
                       )) AS docs_subidos
                FROM examen_proceso ep
                JOIN examen_paso_catalogo epc ON epc.cod_paso = ep.cod_paso_actual
                WHERE ep.cancelado = 0
                  AND ep.cod_paso_actual IS NOT NULL
                  AND epc.fase IN ("examen_privado", "examen_general")';

        $rows = $this->execute($sql, []);

        $completos = 0;
        foreach ($rows as $row) {
            $reqTotal    = (int) ($row['req_total'] ?? 0);
            $docsSubidos = (int) ($row['docs_subidos'] ?? 0);
            if ($reqTotal > 0 && $docsSubidos === $reqTotal) {
                $completos++;
            }
        }
        return $completos;
    }

    // ================================================================
    // MATRIZ DE EVALUACIÓN DEL EXAMEN PRIVADO
    // ================================================================

    public function getMatrizTipos(): array
    {
        return $this->execute(
            'SELECT cod_matriz_tipo, nombre, descripcion, activo FROM examen_matriz_tipo WHERE activo = 1 ORDER BY nombre'
        );
    }

    public function getMatrizPreguntas(int $codMatrizTipo): array
    {
        return $this->execute(
            'SELECT cod_pregunta, cod_matriz_tipo, numero_orden, texto_pregunta, tipo_campo, punteo_maximo
             FROM examen_matriz_pregunta
             WHERE cod_matriz_tipo = :tipo AND activo = 1
             ORDER BY numero_orden ASC',
            ['tipo' => $codMatrizTipo]
        );
    }

    public function getMatrizTipoPorCarrera(int $codCarrera): ?int
    {
        $result = $this->execute(
            'SELECT cod_matriz_tipo FROM examen_matriz_tipo WHERE cod_carrera = :carrera AND activo = 1 LIMIT 1',
            ['carrera' => $codCarrera]
        );
        return !empty($result) ? (int) $result[0]['cod_matriz_tipo'] : null;
    }

    public function getTemaTesis(int $codProceso): ?string
    {
        $result = $this->execute(
            'SELECT tema_tesis FROM examen_proceso WHERE cod_proceso = :proceso LIMIT 1',
            ['proceso' => $codProceso]
        );
        return $result[0]['tema_tesis'] ?? null;
    }

    public function guardarTemaTesis(int $codProceso, ?string $tema): bool
    {
        $this->execute(
            'UPDATE examen_proceso SET tema_tesis = :tema WHERE cod_proceso = :proceso',
            ['tema' => $tema, 'proceso' => $codProceso]
        );
        return true;
    }

    public function getMatrizEvaluacion(int $codProceso, int $posExaminador): ?array
    {
        $eval = $this->execute(
            'SELECT cod_evaluacion, cod_proceso, posicion_examinador, evaluado_por,
                    fecha_evaluacion, observaciones_generales
             FROM examen_matriz_evaluacion
             WHERE cod_proceso = :proceso AND posicion_examinador = :pos
             LIMIT 1',
            ['proceso' => $codProceso, 'pos' => $posExaminador]
        );
        if (empty($eval)) {
            return null;
        }
        $codEvaluacion = (int) $eval[0]['cod_evaluacion'];
        $respuestas = $this->execute(
            'SELECT r.cod_pregunta, r.punteo, r.respuesta_texto,
                    p.numero_orden, p.texto_pregunta, p.tipo_campo, p.punteo_maximo
             FROM examen_matriz_respuesta r
             JOIN examen_matriz_pregunta p ON p.cod_pregunta = r.cod_pregunta
             WHERE r.cod_evaluacion = :eval',
            ['eval' => $codEvaluacion]
        );
        $eval[0]['respuestas'] = $respuestas;
        return $eval[0];
    }

    public function guardarMatrizEvaluacion(array $data): int
    {
        $codProceso = (int) $data['cod_proceso'];
        $posExaminador = (int) $data['posicion_examinador'];
        $evaluadoPor = (int) $data['evaluado_por'];
        $observaciones = $data['observaciones_generales'] ?? null;
        $respuestas = $data['respuestas'] ?? [];

        $existente = $this->execute(
            'SELECT cod_evaluacion FROM examen_matriz_evaluacion
             WHERE cod_proceso = :proceso AND posicion_examinador = :pos
             LIMIT 1',
            ['proceso' => $codProceso, 'pos' => $posExaminador]
        );

        if (!empty($existente)) {
            $codEvaluacion = (int) $existente[0]['cod_evaluacion'];
            $this->adapter->createStatement(
                'UPDATE examen_matriz_evaluacion
                 SET evaluado_por = :usuario,
                     observaciones_generales = :obs,
                     fecha_evaluacion = CURRENT_TIMESTAMP
                 WHERE cod_evaluacion = :cod',
                ['usuario' => $evaluadoPor, 'obs' => $observaciones, 'cod' => $codEvaluacion]
            )->execute();
            $this->adapter->createStatement(
                'DELETE FROM examen_matriz_respuesta WHERE cod_evaluacion = :cod',
                ['cod' => $codEvaluacion]
            )->execute();
        } else {
            $this->adapter->createStatement(
                'INSERT INTO examen_matriz_evaluacion
                 (cod_proceso, posicion_examinador, evaluado_por, observaciones_generales)
                 VALUES (:proceso, :pos, :usuario, :obs)',
                ['proceso' => $codProceso, 'pos' => $posExaminador, 'usuario' => $evaluadoPor, 'obs' => $observaciones]
            )->execute();
            $codEvaluacion = (int) $this->adapter->getDriver()->getLastGeneratedValue();
        }

        foreach ($respuestas as $r) {
            $codPregunta = (int) $r['cod_pregunta'];
            $tipo = $r['tipo_campo'] ?? 'numero';
            $punteo = ($tipo === 'numero' && isset($r['punteo']) && $r['punteo'] !== '')
                ? (float) $r['punteo']
                : null;
            $texto = ($tipo === 'texto' && isset($r['respuesta_texto']) && $r['respuesta_texto'] !== '')
                ? $r['respuesta_texto']
                : null;

            $this->adapter->createStatement(
                'INSERT INTO examen_matriz_respuesta
                 (cod_evaluacion, cod_pregunta, punteo, respuesta_texto)
                 VALUES (:eval, :preg, :punteo, :texto)',
                ['eval' => $codEvaluacion, 'preg' => $codPregunta, 'punteo' => $punteo, 'texto' => $texto]
            )->execute();
        }

        return $codEvaluacion;
    }

    public function getResumenEvaluaciones(int $codProceso): array
    {
        $result = [];
        for ($i = 1; $i <= 3; $i++) {
            $eval = $this->getMatrizEvaluacion($codProceso, $i);
            $result[$i] = $eval;
        }
        return $result;
    }

    /**
     * Obtiene la suma de punteos por examinador.
     * Solo suma respuestas tipo 'numero' con punteo no nulo.
     * Retorna array con clave posicion => suma, o null si no evaluó.
     */
    public function getNotasExaminadores(int $codProceso): array
    {
        $notas = [];
        for ($pos = 1; $pos <= 3; $pos++) {
            $eval = $this->execute(
                'SELECT cod_evaluacion FROM examen_matriz_evaluacion
                 WHERE cod_proceso = :proceso AND posicion_examinador = :pos
                 LIMIT 1',
                ['proceso' => (int) $codProceso, 'pos' => (int) $pos]
            );
            if (empty($eval)) {
                $notas[$pos] = null;
                continue;
            }
            $codEvaluacion = (int) $eval[0]['cod_evaluacion'];
            $suma = $this->execute(
                'SELECT COALESCE(SUM(r.punteo), 0) AS total
                 FROM examen_matriz_respuesta r
                 JOIN examen_matriz_pregunta p ON p.cod_pregunta = r.cod_pregunta
                 WHERE r.cod_evaluacion = :eval AND p.tipo_campo = :tipo',
                ['eval' => $codEvaluacion, 'tipo' => 'numero']
            );
            $notas[$pos] = isset($suma[0]['total']) ? (float) $suma[0]['total'] : null;
        }
        return $notas;
    }

    /**
     * Obtiene las observaciones/correcciones generales de cada examinador.
     * Retorna array con clave posicion => observaciones, o null si no evaluó.
     */
    public function getObservacionesExaminadores(int $codProceso): array
    {
        $observaciones = [];
        for ($pos = 1; $pos <= 3; $pos++) {
            $eval = $this->execute(
                'SELECT observaciones_generales
                 FROM examen_matriz_evaluacion
                 WHERE cod_proceso = :proceso AND posicion_examinador = :pos
                 LIMIT 1',
                ['proceso' => $codProceso, 'pos' => $pos]
            );
            if (empty($eval)) {
                $observaciones[$pos] = null;
                continue;
            }
            $observaciones[$pos] = $eval[0]['observaciones_generales'] ?? null;
        }
        return $observaciones;
    }

    public function getProcesosEvaluables(array $filtros = []): array
    {
        $pagina = $filtros['pagina'] ?? 1;
        $limite = $filtros['limite'] ?? 20;
        $offset = ($pagina - 1) * $limite;

        $sql = "SELECT
                    ep.cod_proceso,
                    ep.cod_usuario,
                    ep.tema_tesis,
                    u.nombres,
                    u.apellidos,
                    u.registro_academico,
                    et.nombre AS tipo_examen,
                    c.nombre_actual AS carrera,
                    ep.fecha_solicitud,
                    epc.fase AS fase_actual,
                    epc.numero_orden AS paso_actual_orden,
                    ep.codigo_evaluacion,
                    ep.hora_apertura_evaluacion,
                    ep.hora_cierre_evaluacion,
                    ep.numero_reprogramacion,
                    ep.ex1_completado,
                    ep.ex2_completado,
                    ep.ex3_completado,
                    (
                        SELECT COUNT(DISTINCT eme.posicion_examinador)
                        FROM examen_matriz_evaluacion eme
                        WHERE eme.cod_proceso = ep.cod_proceso
                    ) AS evaluaciones_completadas
                FROM examen_proceso ep
                JOIN usuario u ON u.cod_usuario = ep.cod_usuario
                JOIN examen_tipo et ON et.cod_tipo_examen = ep.cod_tipo_examen
                LEFT JOIN carrera c ON c.cod_carrera = et.cod_carrera
                JOIN examen_paso_catalogo epc ON epc.cod_paso = ep.cod_paso_actual
                WHERE ep.cancelado = 0
                  AND EXISTS (
                      SELECT 1 FROM examen_proceso_paso epp4
                      JOIN examen_paso_catalogo epc4 ON epc4.cod_paso = epp4.cod_paso
                      WHERE epp4.cod_proceso = ep.cod_proceso
                        AND epc4.fase = 'examen_privado'
                        AND epc4.numero_orden = 4
                        AND epp4.estado = 'completado'
                  )
                ORDER BY ep.fecha_solicitud DESC
                LIMIT {$limite} OFFSET {$offset}";

        $procesos = $this->execute($sql);

        $sqlCount = "SELECT COUNT(*) AS total
                     FROM examen_proceso ep
                     WHERE ep.cancelado = 0
                       AND EXISTS (
                           SELECT 1 FROM examen_proceso_paso epp4
                           JOIN examen_paso_catalogo epc4 ON epc4.cod_paso = epp4.cod_paso
                           WHERE epp4.cod_proceso = ep.cod_proceso
                             AND epc4.fase = 'examen_privado'
                             AND epc4.numero_orden = 4
                             AND epp4.estado = 'completado'
                       )";
        $countResult = $this->execute($sqlCount);
        $total = (int) ($countResult[0]['total'] ?? 0);

        return [
            'procesos' => $procesos,
            'total' => $total,
            'pagina' => $pagina,
            'limite' => $limite,
            'paginas_total' => ceil($total / $limite)
        ];
    }

    /**
     * Genera un código de 8 dígitos para la evaluación de un proceso.
     * Guarda el código, hora_apertura=NOW(), resetea ex1/ex2/ex3=0.
     */
    public function abrirEvaluacion(int $codProceso): string
    {
        $estado = $this->getEstadoEvaluacion($codProceso);
        if (!$estado) {
            throw new \RuntimeException('Proceso no encontrado.');
        }

        if (!empty($estado['hora_cierre_evaluacion'])) {
            throw new \RuntimeException('La evaluación ya fue cerrada y no puede reabrirse.');
        }

        if (!empty($estado['codigo_evaluacion'])) {
            throw new \RuntimeException('La evaluación ya está abierta.');
        }

        $codigo = str_pad((string) random_int(10000000, 99999999), 8, '0', STR_PAD_LEFT);

        $sql = "UPDATE examen_proceso
                SET codigo_evaluacion = :codigo,
                    hora_apertura_evaluacion = NOW(),
                    hora_cierre_evaluacion = NULL,
                    ex1_completado = 0,
                    ex2_completado = 0,
                    ex3_completado = 0
                WHERE cod_proceso = :proceso";

        $this->adapter->createStatement($sql, [
            'codigo'  => $codigo,
            'proceso' => $codProceso
        ])->execute();

        return $codigo;
    }

    /**
     * Cierra la evaluación: hora_cierre=NOW(), codigo_evaluacion=NULL.
     * Requiere que al menos 2 examinadores hayan completado la evaluación.
     *
     * @throws RuntimeException si menos de 2 examinadores han completado
     */
    public function cerrarEvaluacion(int $codProceso): bool
    {
        $estado = $this->getEstadoEvaluacion($codProceso);
        if (!$estado) {
            throw new \RuntimeException('Proceso no encontrado.');
        }

        $completados = 0;
        for ($i = 1; $i <= 3; $i++) {
            if (!empty($estado["ex{$i}_completado"])) {
                $completados++;
            }
        }

        if ($completados < 2) {
            throw new \RuntimeException(
                "No se puede cerrar la evaluación. Debe haber calificación de al menos 2 examinadores. " .
                "Actualmente: {$completados} completado(s)."
            );
        }

        $sql = "UPDATE examen_proceso
                SET hora_cierre_evaluacion = NOW(),
                    codigo_evaluacion = NULL
                WHERE cod_proceso = :proceso";

        $result = $this->adapter->createStatement($sql, [
            'proceso' => $codProceso
        ])->execute();

        return (bool) $result->getAffectedRows();
    }

    /**
     * Verifica que el código coincida y que la evaluación esté abierta.
     * La evaluación está abierta si tiene hora_apertura pero no hora_cierre.
     */
    public function validarCodigo(int $codProceso, string $codigo): bool
    {
        $sql = "SELECT 1 FROM examen_proceso
                WHERE cod_proceso = :proceso
                  AND codigo_evaluacion = :codigo
                  AND hora_apertura_evaluacion IS NOT NULL
                  AND hora_cierre_evaluacion IS NULL
                LIMIT 1";

        $rows = $this->execute($sql, [
            'proceso' => $codProceso,
            'codigo'  => $codigo
        ]);

        return !empty($rows);
    }

    /**
     * Retorna el estado actual de la evaluación (código, horas, completados, reprogramaciones).
     */
    public function getEstadoEvaluacion(int $codProceso): ?array
    {
        $sql = "SELECT codigo_evaluacion,
                       hora_apertura_evaluacion,
                       hora_cierre_evaluacion,
                       ex1_completado,
                       ex2_completado,
                       ex3_completado,
                       numero_reprogramacion,
                       reprogramacion_autorizada_por,
                       numero_acta,
                       estado_acta,
                       fecha_generacion_acta
                FROM examen_proceso
                WHERE cod_proceso = :proceso
                LIMIT 1";

        $rows = $this->execute($sql, ['proceso' => $codProceso]);
        return $rows[0] ?? null;
    }

    /**
     * Reprograma un examen privado cerrado.
     * Elimina evaluaciones anteriores, resetea estado, actualiza fecha/hora.
     * @throws RuntimeException si ya se reprogramó 2 veces o no está cerrado
     */
    public function reprogramarExamenPrivado(int $codProceso, string $nuevaFecha, string $nuevaHora, int $directorId): bool
    {
        $estado = $this->getEstadoEvaluacion($codProceso);
        if (!$estado) {
            throw new \RuntimeException('Proceso no encontrado.');
        }

        $numeroReprog = (int) ($estado['numero_reprogramacion'] ?? 0);
        if ($numeroReprog >= 2) {
            throw new \RuntimeException('Límite de reprogramaciones alcanzado (máximo 2).');
        }

        // 1. Eliminar evaluaciones anteriores (hard delete)
        $evaluaciones = $this->execute(
            'SELECT cod_evaluacion FROM examen_matriz_evaluacion WHERE cod_proceso = :proceso',
            ['proceso' => $codProceso]
        );

        foreach ($evaluaciones as $eval) {
            $codEval = (int) $eval['cod_evaluacion'];
            $this->adapter->createStatement(
                'DELETE FROM examen_matriz_respuesta WHERE cod_evaluacion = :eval',
                ['eval' => $codEval]
            )->execute();
            $this->adapter->createStatement(
                'DELETE FROM examen_matriz_evaluacion WHERE cod_evaluacion = :eval',
                ['eval' => $codEval]
            )->execute();
        }

        // 2. Resetear estado y actualizar fecha/hora
        $sql = "UPDATE examen_proceso
                SET codigo_evaluacion = NULL,
                    hora_apertura_evaluacion = NULL,
                    hora_cierre_evaluacion = NULL,
                    ex1_completado = 0,
                    ex2_completado = 0,
                    ex3_completado = 0,
                    fecha_examen_privado = :fecha,
                    hora_examen_privado = :hora,
                    numero_reprogramacion = numero_reprogramacion + 1,
                    reprogramacion_autorizada_por = :director
                WHERE cod_proceso = :proceso";

        $this->adapter->createStatement($sql, [
            'fecha'    => $nuevaFecha,
            'hora'     => $nuevaHora,
            'director' => $directorId,
            'proceso'  => $codProceso,
        ])->execute();

        return true;
    }

    /**
     * Retorna la terna con sus nombres para un proceso específico.
     */
    public function getTernaParaEvaluacion(int $codProceso): array
    {
        return $this->getTerna($codProceso);
    }

    /**
     * Marca un examinador como completado en examen_proceso.
     */
    public function marcarExaminadorCompletado(int $codProceso, int $posicion): bool
    {
        if ($posicion < 1 || $posicion > 3) {
            return false;
        }

        $columna = "ex{$posicion}_completado";
        $sql = "UPDATE examen_proceso
                SET {$columna} = 1
                WHERE cod_proceso = :proceso";

        $result = $this->adapter->createStatement($sql, [
            'proceso' => $codProceso
        ])->execute();

        return (bool) $result->getAffectedRows();
    }

    /**
     * Genera el siguiente correlativo de acta de forma global por año.
     * Todas las maestrías comparten el mismo contador.
     *
     * @param int $anio Año del acta
     * @return array ['numero_acta' => '001-2026', 'correlativo' => 1, 'anio' => 2026]
     */
    public function generarNumeroActa(int $anio): array
    {
        $table = new TableGateway('examen_acta_correlativo', $this->adapter);

        // Buscar el último correlativo para este año
        $result = $table->select(['anio' => $anio]);
        $row = $result->current();

        if ($row) {
            $nuevoCorrelativo = (int)$row['ultimo_correlativo'] + 1;
            // Actualizar
            $table->update(
                ['ultimo_correlativo' => $nuevoCorrelativo],
                ['anio' => $anio]
            );
        } else {
            $nuevoCorrelativo = 1;
            // Insertar nuevo registro
            $table->insert([
                'anio' => $anio,
                'ultimo_correlativo' => $nuevoCorrelativo
            ]);
        }

        // Formato con ceros a la izquierda: 001-2026
        $numeroActa = sprintf('%03d-%d', $nuevoCorrelativo, $anio);

        return [
            'numero_acta' => $numeroActa,
            'correlativo' => $nuevoCorrelativo,
            'anio' => $anio
        ];
    }

    /**
     * Registra el acta generada en el proceso de examen.
     *
     * @param int $codProceso
     * @param string $numeroActa
     * @param int $correlativo
     * @param int $anio
     * @param string $estado 'aprobado' o 'reprobado'
     * @return bool
     */
    public function registrarActaProceso(
        int $codProceso,
        string $numeroActa,
        int $correlativo,
        int $anio,
        string $estado
    ): bool {
        $table = new TableGateway('examen_proceso', $this->adapter);

        try {
            $table->update([
                'numero_acta' => $numeroActa,
                'anio_acta' => $anio,
                'correlativo_acta' => $correlativo,
                'fecha_generacion_acta' => date('Y-m-d H:i:s'),
                'estado_acta' => $estado
            ], [
                'cod_proceso' => $codProceso
            ]);
            return true;
        } catch (\Exception $e) {
            return false;
        }
    }

    /**
     * Obtiene el nombre del Secretario de Examen Privado (rol 11).
     * Busca el primer usuario activo con ese rol.
     *
     * @return string Nombre completo del secretario o 'Secretario Académico' si no existe
     */
    public function getNombreSecretarioExamenPrivado(): string
    {
        $sql = 'SELECT u.nombres, u.apellidos, u.titulo_profesional
                FROM usuario u
                JOIN usuario_rol ur ON ur.cod_usuario = u.cod_usuario
                WHERE ur.cod_rol = :rol
                LIMIT 1';

        $result = $this->execute($sql, ['rol' => 11]);
        if (empty($result)) {
            return 'Secretario Académico';
        }

        $titulo = trim($result[0]['titulo_profesional'] ?? '');
        $nombreCompleto = trim($result[0]['nombres'] . ' ' . $result[0]['apellidos']);

        return $titulo !== '' ? $titulo . ' ' . $nombreCompleto : $nombreCompleto;
    }

    /**
     * Obtiene los datos completos del Secretario de Examen Privado (rol 11)
     * para usar en la sustitución de examinadores.
     *
     * @return array|null Datos del secretario o null si no existe
     */
    public function getSecretarioParaSustitucion(): ?array
    {
        $sql = 'SELECT
                    u.cod_usuario,
                    CONCAT(u.nombres, " ", u.apellidos) AS nombre_completo,
                    u.numero_colegiado AS colegiado,
                    u.titulo_profesional AS titulo,
                    u.correo
                FROM usuario u
                JOIN usuario_rol ur ON ur.cod_usuario = u.cod_usuario
                WHERE ur.cod_rol = :rol
                LIMIT 1';

        $result = $this->execute($sql, ['rol' => 11]);
        return $result[0] ?? null;
    }

    /**
     * Lista los procesos en fase examen_general que ya tienen notificación
     * grupal enviada (fecha_examen_general no nula).
     */
    public function getProcesosConNotificacionGeneral(): array
    {
        $sql = 'SELECT
                    ep.cod_proceso,
                    ep.cod_usuario,
                    u.nombres,
                    u.apellidos,
                    u.registro_academico,
                    u.sexo,
                    et.nombre AS tipo_examen,
                    ep.fecha_solicitud,
                    ep.tema_tesis,
                    ep.fecha_examen_general,
                    ep.hora_examen_general,
                    c.nombre_actual AS carrera,
                    eag.numero_acta AS acta_generada
                FROM examen_proceso ep
                JOIN usuario u ON u.cod_usuario = ep.cod_usuario
                JOIN examen_tipo et ON et.cod_tipo_examen = ep.cod_tipo_examen
                LEFT JOIN carrera c ON c.cod_carrera = et.cod_carrera
                LEFT JOIN examen_acta_general eag ON eag.cod_proceso = ep.cod_proceso
                WHERE ep.cancelado = 0
                  AND ep.cod_paso_actual IS NULL
                  AND ep.fecha_examen_general IS NOT NULL
                ORDER BY ep.fecha_examen_general, u.apellidos, u.nombres';

        return $this->execute($sql, []);
    }

    /**
     * Verifica si un proceso ya tiene acta general generada.
     */
    public function yaTieneActaGeneral(int $codProceso): bool
    {
        $sql = 'SELECT COUNT(*) AS total FROM examen_acta_general WHERE cod_proceso = :proceso';
        $result = $this->execute($sql, ['proceso' => $codProceso]);
        return (int) ($result[0]['total'] ?? 0) > 0;
    }

    /**
     * Obtiene los datos necesarios para el formulario del acta general.
     * Incluye datos compartidos del acto grupal si ya existe.
     */
    public function getDatosActaGeneral(int $codProceso): ?array
    {
        $sql = 'SELECT
                    ep.cod_proceso,
                    ep.cod_usuario,
                    u.nombre_completo,
                    u.nombres,
                    u.apellidos,
                    u.registro_academico,
                    u.sexo,
                    et.nombre AS tipo_examen,
                    c.nombre_actual AS carrera,
                    ep.tema_tesis,
                    ep.fecha_examen_general,
                    ep.hora_examen_general,
                    eag.cod_acta,
                    eag.numero_acta,
                    eag.numero_recibo,
                    eag.promedio,
                    eag.acuerdo_decanato,
                    emp.tipo AS madrina_tipo,
                    emp.nombre AS madrina_nombre,
                    emp.titulo_profesional AS madrina_titulo,
                    eagod.cod_acto_graduacion,
                    eagod.lugar AS lugar_acto,
                    eagod.examinador_1,
                    eagod.examinador_2,
                    eagod.examinador_3,
                    eagod.hora_firma
                FROM examen_proceso ep
                JOIN usuario u ON u.cod_usuario = ep.cod_usuario
                JOIN examen_tipo et ON et.cod_tipo_examen = ep.cod_tipo_examen
                LEFT JOIN carrera c ON c.cod_carrera = et.cod_carrera
                LEFT JOIN examen_acta_general eag ON eag.cod_proceso = ep.cod_proceso
                LEFT JOIN examen_acto_graduacion eagod
                    ON eagod.fecha_acto = ep.fecha_examen_general
                    AND eagod.hora_acto = ep.hora_examen_general
                LEFT JOIN examen_madrina_padrino emp ON emp.cod_proceso = ep.cod_proceso
                WHERE ep.cod_proceso = :proceso
                LIMIT 1';

        $result = $this->execute($sql, ['proceso' => $codProceso]);
        return $result[0] ?? null;
    }

    /**
     * Obtiene o crea el acto de graduación compartido para una fecha/hora.
     * Todos los estudiantes con la misma fecha y hora comparten el mismo acto.
     * Si ya existe y se proporcionan datos nuevos, actualiza la fila.
     */
    public function obtenerOCrearActoGraduacion(string $fecha, string $hora, array $datos): int
    {
        // Buscar acto grupal existente por fecha y hora
        $sql = 'SELECT cod_acto_graduacion FROM examen_acto_graduacion WHERE fecha_acto = :fecha AND hora_acto = :hora LIMIT 1';
        $result = $this->execute($sql, ['fecha' => $fecha, 'hora' => $hora]);

        if (!empty($result)) {
            $codActo = (int) $result[0]['cod_acto_graduacion'];

            // Si se proporcionan datos nuevos, actualizar el acto existente
            $camposActualizar = [];
            $params = ['id' => $codActo];

            if (isset($datos['lugar']) && $datos['lugar'] !== '') {
                $camposActualizar[] = 'lugar = :lugar';
                $params['lugar'] = $datos['lugar'];
            }
            if (isset($datos['examinador_1']) && $datos['examinador_1'] !== '') {
                $camposActualizar[] = 'examinador_1 = :ex1';
                $params['ex1'] = $datos['examinador_1'];
            }
            if (isset($datos['examinador_2']) && $datos['examinador_2'] !== '') {
                $camposActualizar[] = 'examinador_2 = :ex2';
                $params['ex2'] = $datos['examinador_2'];
            }
            if (isset($datos['examinador_3']) && $datos['examinador_3'] !== '') {
                $camposActualizar[] = 'examinador_3 = :ex3';
                $params['ex3'] = $datos['examinador_3'];
            }
            if (isset($datos['hora_firma']) && $datos['hora_firma'] !== '') {
                $camposActualizar[] = 'hora_firma = :hora_firma';
                $params['hora_firma'] = $datos['hora_firma'];
            }

            if (!empty($camposActualizar)) {
                $sqlUpdate = 'UPDATE examen_acto_graduacion SET ' . implode(', ', $camposActualizar) . ' WHERE cod_acto_graduacion = :id';
                $this->execute($sqlUpdate, $params);
            }

            return $codActo;
        }

        // Crear nuevo acto grupal
        $this->execute(
            'INSERT INTO examen_acto_graduacion (
                fecha_acto, hora_acto, lugar, examinador_1, examinador_2, examinador_3, hora_firma
            ) VALUES (
                :fecha, :hora, :lugar, :ex1, :ex2, :ex3, :hora_firma
            )',
            [
                'fecha'      => $fecha,
                'hora'       => $hora,
                'lugar'      => $datos['lugar'] ?? '',
                'ex1'        => $datos['examinador_1'] ?? null,
                'ex2'        => $datos['examinador_2'] ?? null,
                'ex3'        => $datos['examinador_3'] ?? null,
                'hora_firma' => $datos['hora_firma'] ?? null,
            ]
        );

        return (int) $this->adapter->getDriver()->getLastGeneratedValue();
    }

    /**
     * Guarda el acta general en la base de datos.
     * Primero obtiene/crea el acto grupal compartido, luego inserta el acta individual.
     * Reutiliza el correlativo global examen_acta_correlativo.
     */
    public function guardarActaGeneral(array $datos): int
    {
        $anio = (int) date('Y');
        $actaNum = $this->generarNumeroActa($anio);

        // Obtener o crear el acto grupal compartido
        $codActoGraduacion = $this->obtenerOCrearActoGraduacion(
            $datos['fecha_examen'],
            $datos['hora_examen'],
            $datos
        );

        $params = [
            'cod_acto_graduacion' => $codActoGraduacion,
            'cod_proceso'         => $datos['cod_proceso'],
            'numero_acta'         => $actaNum['numero_acta'],
            'anio_acta'           => $actaNum['anio'],
            'correlativo_acta'    => $actaNum['correlativo'],
            'numero_recibo'       => $datos['numero_recibo'] ?? null,
            'promedio'            => $datos['promedio'] ?? null,
            'acuerdo_decanato'    => $datos['acuerdo_decanato'] ?? null,
            'generado_por'        => $datos['generado_por'] ?? null,
        ];

        $this->execute(
            'INSERT INTO examen_acta_general (
                cod_acto_graduacion, cod_proceso, numero_acta, anio_acta, correlativo_acta,
                numero_recibo, promedio, acuerdo_decanato, generado_por
            ) VALUES (
                :cod_acto_graduacion, :cod_proceso, :numero_acta, :anio_acta, :correlativo_acta,
                :numero_recibo, :promedio, :acuerdo_decanato, :generado_por
            )',
            $params
        );

        $lastId = $this->adapter->getDriver()->getLastGeneratedValue();
        return (int) $lastId;
    }

    // ── Actas de Examen Privado ───────────────────────

    /**
     * Obtiene el acta de examen privado por proceso.
     */
    public function getActaPrivado(int $codProceso): ?array
    {
        $sql = 'SELECT * FROM examen_acta_privado WHERE cod_proceso = :proceso LIMIT 1';
        $result = $this->execute($sql, ['proceso' => $codProceso]);
        return $result[0] ?? null;
    }

    /**
     * Verifica si un proceso ya tiene acta privada generada.
     */
    public function actaPrivadoExiste(int $codProceso): bool
    {
        $sql = 'SELECT COUNT(*) AS total FROM examen_acta_privado WHERE cod_proceso = :proceso';
        $result = $this->execute($sql, ['proceso' => $codProceso]);
        return (int) ($result[0]['total'] ?? 0) > 0;
    }

    /**
     * Guarda el acta de examen privado en la tabla dedicada.
     * Genera correlativo global si no existe.
     */
    public function guardarActaPrivado(array $datos): int
    {
        $anio = (int) date('Y');
        $actaNum = $this->generarNumeroActa($anio);

        $params = [
            'cod_proceso'           => $datos['cod_proceso'],
            'numero_acta'           => $actaNum['numero_acta'],
            'anio_acta'             => $actaNum['anio'],
            'correlativo_acta'      => $actaNum['correlativo'],
            'recibo'                => $datos['recibo'] ?? null,
            'nota_final'            => $datos['nota_final'] ?? null,
            'estado'                => $datos['estado'] ?? null,
            'examinador_1'          => $datos['examinador_1'] ?? null,
            'examinador_2'          => $datos['examinador_2'] ?? null,
            'examinador_3'          => $datos['examinador_3'] ?? null,
            'fecha_examen'          => $datos['fecha_examen'] ?? null,
            'hora_examen'           => $datos['hora_examen'] ?? null,
            'hora_firma'            => $datos['hora_firma'] ?? null,
            'lugar'                 => $datos['lugar'] ?? null,
            'justificacion_modalidad' => $datos['justificacion_modalidad'] ?? null,
            'generado_por'          => $datos['generado_por'] ?? null,
        ];

        $this->execute(
            'INSERT INTO examen_acta_privado (
                cod_proceso, numero_acta, anio_acta, correlativo_acta, recibo,
                nota_final, estado, examinador_1, examinador_2, examinador_3,
                fecha_examen, hora_examen, hora_firma, lugar,
                justificacion_modalidad, generado_por
            ) VALUES (
                :cod_proceso, :numero_acta, :anio_acta, :correlativo_acta, :recibo,
                :nota_final, :estado, :examinador_1, :examinador_2, :examinador_3,
                :fecha_examen, :hora_examen, :hora_firma, :lugar,
                :justificacion_modalidad, :generado_por
            )',
            $params
        );

        return (int) $this->adapter->getDriver()->getLastGeneratedValue();
    }
}