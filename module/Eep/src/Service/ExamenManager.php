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
    const TIPO_PUBLICO_GENERAL = 3;

    public function getTiposExamen(): array
    {
        $sql = 'SELECT cod_tipo_examen, nombre, descripcion
                FROM examen_tipo
                WHERE activo = 1
                ORDER BY nombre';

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
                    CONCAT(u.nombres, ' ', u.apellidos) AS nombre_completo,
                    u.correo,
                    c.nombre_actual AS carrera,
                    p.descripcion AS pensum
                FROM usuario u
                INNER JOIN usuario_rol ur ON ur.cod_usuario = u.cod_usuario AND ur.cod_rol = 6
                LEFT JOIN inscripcion i ON i.cod_usuario = u.cod_usuario
                LEFT JOIN pensum p ON p.cod_pensum = i.cod_pensum
                LEFT JOIN carrera c ON c.cod_carrera = p.cod_carrera
                WHERE (
                    u.registro_academico LIKE :termino_exacto
                    OR u.nombres LIKE :termino_like
                    OR u.apellidos LIKE :termino_like
                    OR CONCAT(u.nombres, ' ', u.apellidos) LIKE :termino_like
                )
                ORDER BY u.apellidos, u.nombres
                LIMIT 20";

        return $this->execute($sql, [
            'termino_exacto' => $termino,
            'termino_like'   => '%' . $termino . '%'
        ]);
    }

    /**
     * Verifica si un estudiante ya tiene un proceso de graduación activo
     * (no cancelado y no finalizado).
     *
     * @param int $codUsuario  Código del estudiante
     * @return array|null      Proceso activo si existe, null si no
     */
    public function getProcesoActivoEstudiante(int $codUsuario): ?array
    {
        $sql = 'SELECT ep.cod_proceso, et.nombre AS tipo_examen, epc.nombre AS paso_actual,
                       epc.fase AS fase_actual
                FROM examen_proceso ep
                JOIN examen_tipo et ON et.cod_tipo_examen = ep.cod_tipo_examen
                LEFT JOIN examen_paso_catalogo epc ON epc.cod_paso = ep.cod_paso_actual
                WHERE ep.cod_usuario = :usuario
                  AND ep.cancelado = 0
                  AND ep.cod_paso_actual IS NOT NULL
                ORDER BY ep.fecha_solicitud DESC
                LIMIT 1';

        $result = $this->execute($sql, ['usuario' => $codUsuario]);
        return $result[0] ?? null;
    }

    /**
     * Inicia un nuevo proceso de examen para un estudiante.
     * T-13
     */
    public function iniciarProceso(int $codUsuario, int $codTipoExamen, int $userAdminId): int
    {
        error_log("DEBUG user id: ".print_r($userAdminId, true));
        // 1. Obtener el primer paso del catálogo para este tipo de examen
        $sqlPaso = 'SELECT cod_paso 
                    FROM examen_paso_catalogo 
                    WHERE (cod_tipo_examen = :tipo OR cod_tipo_examen IS NULL) 
                      AND fase = \'examen_privado\'
                      AND numero_orden = 1 
                      AND activo = 1 
                    LIMIT 1';
        $resPaso = $this->execute($sqlPaso, ['tipo' => $codTipoExamen]);
        $primerPaso = !empty($resPaso) ? $resPaso[0]['cod_paso'] : 1; // Default fallback

        // 2. Crear el registro maestro del proceso
        $sqlMaster = 'INSERT INTO examen_proceso (cod_usuario, cod_tipo_examen, cod_paso_actual, registrado_por)
                      VALUES (:usuario, :tipo, :paso, :registrado_por)';
        $this->adapter->createStatement($sqlMaster, [
            'usuario' => $codUsuario,
            'tipo'    => $codTipoExamen,
            'paso'    => $primerPaso,
            'registrado_por' => $userAdminId
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
            'tipo_evento' => 'otro',
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
                       epc.fase,
                       epp.fecha_completado
                FROM examen_proceso_paso epp
                INNER JOIN examen_paso_catalogo epc ON epc.cod_paso = epp.cod_paso
                WHERE epp.cod_proceso = :proceso
                  AND epp.fecha_completado IS NOT NULL
                ORDER BY epc.numero_orden ASC';

        $rows = $this->execute($sql, ['proceso' => $codProceso]);

        // Indexar por "fase_numeroOrden" para evitar colisión cuando privado y
        // general comparten el mismo numero_orden (1-4).
        // Ej: ['examen_privado_1' => '2026-03-01', 'examen_general_1' => '2026-04-10']
        $result = [];
        foreach ($rows as $row) {
            $key = $row['fase'] . '_' . $row['numero_orden'];
            $result[$key] = $row['fecha_completado'];
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
                    epc.fase               AS fase_paso_actual,
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

        $offset = ($pagina - 1) * $limite;

        $whereEstado = '';
        if ($estado) {
            $whereEstado = 'AND ep.estado = :estado';
        }

        $whereTipo = '';
        // Inicializar params aquí para poder agregar fase_tipo antes
        $params = [];
        if ($codTipoExamen) {
            // Filtrar por tipo de examen basado en la fase actual del paso
            // Tipo 3 (Público General) = fase examen_general
            // Tipos 1-2 (Privado) = fase examen_privado
            if ($codTipoExamen == 3) {
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

        $sql = "SELECT
                    ep.cod_proceso,
                    ep.cod_usuario,
                    u.nombres,
                    u.apellidos,
                    u.registro_academico,
                    -- Tipo de examen de la fase actual (3 para general, original para privado)
                    CASE
                        WHEN epc.fase = 'examen_general' THEN 3
                        ELSE ep.cod_tipo_examen
                    END AS tipo_cod_examen,
                    -- Nombre del tipo según la fase actual
                    CASE
                        WHEN epc.fase = 'examen_general' THEN 'Público General'
                        ELSE et.nombre
                    END AS tipo_examen,
                    ep.fecha_solicitud,
                    ep.cod_paso_actual,
                    epc.numero_orden,
                    epc.fase AS fase_actual,
                    CASE
                        WHEN ep.cod_paso_actual IS NULL THEN 'completado'
                        ELSE COALESCE(epp.estado, 'pendiente')
                    END AS estado_paso,
                    ep.cancelado,
                    (
                        SELECT MAX(epp2.fecha_completado)
                        FROM examen_proceso_paso epp2
                        WHERE epp2.cod_proceso = ep.cod_proceso
                    ) AS fecha_completado
                FROM examen_proceso ep
                JOIN usuario u ON u.cod_usuario = ep.cod_usuario
                JOIN examen_tipo et ON et.cod_tipo_examen = ep.cod_tipo_examen
                LEFT JOIN examen_paso_catalogo epc ON epc.cod_paso = ep.cod_paso_actual
                LEFT JOIN examen_proceso_paso epp ON epp.cod_proceso = ep.cod_proceso
                    AND epp.cod_paso = ep.cod_paso_actual
                WHERE ep.cancelado = 0
                $whereEstado
                $whereTipo
                $wherePaso
                ORDER BY ep.fecha_solicitud DESC
                LIMIT $limite OFFSET $offset";

        if ($estado) {
            $params['estado'] = $estado;
        }
        // Solo agregar cod_tipo_examen cuando el filtro lo usa (no para tipo 3 que usa solo fase)
        if ($codTipoExamen && $codTipoExamen != 3) {
            $params['cod_tipo_examen'] = $codTipoExamen;
        }
        if ($numeroPaso !== null) {
            $params['numero_paso'] = $numeroPaso;
        }

        $procesos = $this->execute($sql, $params);

        // Contar total para paginación
        $sqlCount = "SELECT COUNT(*) AS total
                     FROM examen_proceso ep
                     LEFT JOIN examen_paso_catalogo epc ON epc.cod_paso = ep.cod_paso_actual
                     WHERE ep.cancelado = 0
                     $whereEstado
                     $whereTipo
                     $wherePaso";

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
    public function guardarTerna(int $codProceso, array $terna, int $codUsuario, string $fase = 'examen_privado'): bool
    {
        foreach ($terna as $datos) {
            $sql = 'INSERT INTO examen_terna 
                        (cod_proceso, fase, nombre_examinador, numero_colegiado, correo, tipo_examinador, posicion, registrado_por)
                    VALUES 
                        (:proceso, :fase, :nombre, :colegiado, :correo, :tipo, :posicion, :usuario)
                    ON DUPLICATE KEY UPDATE 
                        nombre_examinador = VALUES(nombre_examinador),
                        numero_colegiado = VALUES(numero_colegiado), 
                        correo = VALUES(correo),
                        tipo_examinador = VALUES(tipo_examinador),
                        registrado_por = VALUES(registrado_por)';

            $params = [
                'proceso'   => $codProceso,
                'fase'      => $fase,
                'nombre'    => $datos['nombre'],
                'colegiado' => $datos['colegiado'] ?? null,
                'correo'    => $datos['correo'] ?? null,
                'tipo'      => $datos['tipo_examinador'],
                'posicion'  => (int)$datos['posicion'],
                'usuario'   => $codUsuario
            ];

            $this->adapter->createStatement($sql, $params)->execute();
        }

        return true;
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
     * Obtiene los examinadores asignados y la programación del examen.
     * La terna es compartida entre ambas fases (una sola por proceso),
     * pero la fecha/hora se obtiene de la columna correspondiente a la fase.
     * T-07
     *
     * @param int    $codProceso  ID del proceso
     * @param string $fase        Fase actual ('examen_privado' o 'examen_general')
     */
    public function getTerna(int $codProceso, string $fase = 'examen_privado'): array
    {
        // 1. Obtener los examinadores de la tabla terna filtrados por fase
        // Ahora las ternas son independientes: examen_privado y examen_general pueden tener ternas diferentes
        $sqlTerna = 'SELECT 
                        nombre_examinador,
                        numero_colegiado,
                        correo,
                        tipo_examinador,
                        posicion
                    FROM examen_terna 
                    WHERE cod_proceso = :proceso 
                      AND fase = :fase';

        $rows = $this->execute($sqlTerna, ['proceso' => $codProceso, 'fase' => $fase]);
        
        // 2. Obtener fecha y hora según la fase
        if ($fase === 'examen_general') {
            $sqlProceso = 'SELECT fecha_examen_general AS fecha,
                                  hora_examen_general AS hora
                           FROM examen_proceso 
                           WHERE cod_proceso = :proceso 
                           LIMIT 1';
        } else {
            $sqlProceso = 'SELECT fecha_examen_privado AS fecha,
                                  hora_examen_privado AS hora
                           FROM examen_proceso 
                           WHERE cod_proceso = :proceso 
                           LIMIT 1';
        }

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
            $terna['examinadores'][] = [
                'nombre'    => $row['nombre_examinador'],
                'colegiado' => $row['numero_colegiado'],
                'correo'    => $row['correo'],
                'tipo'      => $row['tipo_examinador'],
                'posicion'  => $row['posicion']
            ];
        }

        return $terna;
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
                    MAX(epp.fecha_completado) AS fecha_completado
                FROM examen_proceso ep
                JOIN usuario u ON u.cod_usuario = ep.cod_usuario
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
                GROUP BY ep.cod_proceso, u.nombres, u.apellidos, u.registro_academico, u.correo, c.nombre_actual, ep.fecha_solicitud
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
}