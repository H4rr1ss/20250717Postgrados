<?php

namespace Eep\Service;

use Zend\Db\Adapter\Adapter;
use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Expression;
use Eep\Entity\Result as R;

class EvaluacionDocenteManager extends Manager {

    public function getCursosPendientes($userCode): R {
        $res = new R();
        $table = new TableGateway('asignacion', $this->dbAdapter);

        $select = $table->getSql()->select();
        $select->join(['h' => 'horario'], 'asignacion.cod_horario = h.cod_horario', [
            'cod_horario', 'seccion', 'fecha_inicio', 'fecha_fin', 'anio', 'mes',
        ]);
        $select->join(['cp' => 'curso_pensum'], 'h.cod_pensum = cp.cod_pensum AND h.cod_curso = cp.cod_curso', ['nombre_curso' => 'nombre']);
        $select->join(['u' => 'usuario'], 'h.cod_usuario_catedratico = u.cod_usuario', ['nombre_docente' => new Expression("CONCAT(u.nombres, ' ', u.apellidos)")], Select::JOIN_LEFT);
        $select->join(['er' => 'evaluacion_respuesta'], 'asignacion.cod_horario = er.cod_horario AND asignacion.cod_usuario = er.cod_usuario_estudiante', ['id'], Select::JOIN_LEFT);
        $select->join(['ac' => 'asignacion_carrera'], 'asignacion.cod_usuario = ac.cod_usuario', ['fecha_cohorte'], Select::JOIN_LEFT);
        $select->where(['asignacion.cod_usuario' => $userCode]);
        $select->where('h.fecha_fin <= CURDATE()');
        $select->where('er.id IS NULL');
        $select->where('(h.fecha_cohorte = ac.fecha_cohorte OR h.fecha_cohorte IS NULL)');
        $select->where('h.fecha_fin < DATE_FORMAT(CURDATE(), "%Y-%m-01")');
        $select->order('h.fecha_fin DESC');

        try {
            $result = $table->selectWith($select)->toArray();
            $res->success();
            $res->setObj($result);
        } catch (\Exception $ex) {
            $res->failure('No se pudieron consultar los cursos pendientes de evaluación', $ex);
        }
        return $res;
    }

    public function getHistorial($userCode): R {
        $res = new R();
        $table = new TableGateway('evaluacion_respuesta', $this->dbAdapter);
        $select = $table->getSql()->select();
        $select->join(['h' => 'horario'], 'evaluacion_respuesta.cod_horario = h.cod_horario', ['seccion', 'anio', 'mes']);
        $select->join(['cp' => 'curso_pensum'], 'h.cod_pensum = cp.cod_pensum AND h.cod_curso = cp.cod_curso', ['nombre_curso' => 'nombre']);
        $select->join(['u' => 'usuario'], 'h.cod_usuario_catedratico = u.cod_usuario', ['nombre_docente' => new Expression("CONCAT(u.nombres, ' ', u.apellidos)")], Select::JOIN_LEFT);
        $select->where(['evaluacion_respuesta.cod_usuario_estudiante' => $userCode]);
        $select->order('evaluacion_respuesta.fecha_evaluacion DESC');
        try {
            $result = $table->selectWith($select)->toArray();
            $res->success();
            $res->setObj($result);
        } catch (\Exception $ex) {
            $res->failure('No se pudieron consultar las evaluaciones realizadas', $ex);
        }
        return $res;
    }

    public function getPreguntas(): R {
        $res = new R();
        $table = new TableGateway('evaluacion_seccion', $this->dbAdapter);
        $select = $table->getSql()->select();
        $select->join(['p' => 'evaluacion_pregunta'], 'evaluacion_seccion.id = p.id_seccion', ['id_pregunta' => 'id', 'texto', 'tipo', 'orden_pregunta' => 'orden']);
        $select->where(['evaluacion_seccion.activa' => 1, 'p.activa' => 1]);
        $select->order('evaluacion_seccion.orden ASC');
        $select->order('p.orden ASC');
        try {
            $rows = $table->selectWith($select)->toArray();
            $secciones = [];
            foreach ($rows as $row) {
                $seccionNombre = $row['nombre'];
                if (!isset($secciones[$seccionNombre])) {
                    $secciones[$seccionNombre] = ['categoria' => $seccionNombre, 'preguntas' => []];
                }
                $secciones[$seccionNombre]['preguntas'][] = ['id' => $row['id_pregunta'], 'texto' => $row['texto'], 'tipo' => $row['tipo']];
            }
            $res->success();
            $res->setObj(array_values($secciones));
        } catch (\Exception $ex) {
            $res->failure('No se pudieron consultar las preguntas de evaluación', $ex);
        }
        return $res;
    }

    public function guardarEvaluacion($userCode, $codHorario, array $respuestas): R {
        $res = new R();
        $res->success();
        $asignacionTable = new TableGateway('asignacion', $this->dbAdapter);
        $asignacion = $asignacionTable->select(['cod_usuario' => $userCode, 'cod_horario' => $codHorario])->current();
        if ($asignacion === null) {
            $res->failure('El estudiante no está asignado al curso indicado.');
            return $res;
        }
        $respuestaTable = new TableGateway('evaluacion_respuesta', $this->dbAdapter);
        $existe = $respuestaTable->select(['cod_horario' => $codHorario, 'cod_usuario_estudiante' => $userCode])->current();
        if ($existe !== null) {
            $res->failure('Ya existe una evaluación registrada para este curso.');
            return $res;
        }
        $horarioTable = new TableGateway('horario', $this->dbAdapter);
        $horario = $horarioTable->select(['cod_horario' => $codHorario])->current();
        if ($horario === null) {
            $res->failure('El curso no existe.');
            return $res;
        }
        if ($horario['fecha_fin'] > date('Y-m-d')) {
            $res->failure('No puede evaluar un curso que aún no ha finalizado.');
            return $res;
        }
        $this->beginTransaction();
        try {
            $respuestaTable->insert(['cod_horario' => $codHorario, 'cod_usuario_estudiante' => $userCode, 'fecha_evaluacion' => date('Y-m-d H:i:s')]);
            $idEvaluacion = $respuestaTable->getLastInsertValue();
            $detalleTable = new TableGateway('evaluacion_respuesta_detalle', $this->dbAdapter);
            foreach ($respuestas as $idPregunta => $valorRespuesta) {
                $detalleTable->insert(['id_evaluacion_respuesta' => $idEvaluacion, 'id_pregunta' => (int) $idPregunta, 'respuesta' => $valorRespuesta]);
            }
            $this->commit();
            $res->addMsg('Evaluación guardada correctamente.');
        } catch (\Exception $ex) {
            $this->rollback();
            $res->failure('Error al guardar la evaluación', $ex);
        }
        return $res;
    }

    public function getResumenEstudiante($userCode): R {
        $res = new R();
        try {
            $table = new TableGateway('evaluacion_respuesta', $this->dbAdapter);
            $select = $table->getSql()->select();
            $select->join(['h' => 'horario'], 'evaluacion_respuesta.cod_horario = h.cod_horario', []);
            $select->join(['ac' => 'asignacion_carrera'], 'evaluacion_respuesta.cod_usuario_estudiante = ac.cod_usuario', [], Select::JOIN_LEFT);
            $select->where(['evaluacion_respuesta.cod_usuario_estudiante' => $userCode]);
            $select->where('(h.fecha_cohorte = ac.fecha_cohorte OR h.fecha_cohorte IS NULL)');
            $select->where('h.fecha_fin < DATE_FORMAT(CURDATE(), "%Y-%m-01")');
            $evaluacionesCompletadas = $table->selectWith($select)->count();
            $pendientes = $this->getCursosPendientes($userCode);
            $totalPendientes = ($pendientes->get() && is_array($pendientes->getObj())) ? count($pendientes->getObj()) : 0;
            $total = $evaluacionesCompletadas + $totalPendientes;
            $progreso = $total > 0 ? round(($evaluacionesCompletadas / $total) * 100) : 0;
            $res->success();
            $res->setObj(['completadas' => $evaluacionesCompletadas, 'pendientes' => $totalPendientes, 'progreso' => $progreso]);
        } catch (\Exception $ex) {
            $res->failure('No se pudo obtener el resumen del estudiante', $ex);
        }
        return $res;
    }

    public function getPeriodosEvaluacion(): R {
        $res = new R();
        try {
            $table = new TableGateway('evaluacion_respuesta', $this->dbAdapter);
            $select = $table->getSql()->select();
            $select->join(['h' => 'horario'], 'evaluacion_respuesta.cod_horario = h.cod_horario', ['anio', 'mes']);
            $select->columns([]);
            $select->quantifier(Select::QUANTIFIER_DISTINCT);
            $select->order('h.anio DESC');
            $select->order('h.mes DESC');
            $result = $table->selectWith($select)->toArray();
            $res->success();
            $res->setObj($result);
        } catch (\Exception $ex) {
            $res->failure('No se pudieron consultar los períodos de evaluación', $ex);
        }
        return $res;
    }

    public function getDocentesEvaluados(): R {
        $res = new R();
        try {
            $sql = "
                SELECT DISTINCT
                    u.cod_usuario,
                    CONCAT(u.nombres, ' ', u.apellidos) as nombre_docente
                FROM evaluacion_respuesta er
                JOIN horario h ON er.cod_horario = h.cod_horario
                JOIN usuario u ON h.cod_usuario_catedratico = u.cod_usuario
                ORDER BY nombre_docente ASC
            ";
            $stmt = $this->dbAdapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
            $res->success();
            $res->setObj($stmt->toArray());
        } catch (\Exception $ex) {
            $res->failure('No se pudieron consultar los docentes', $ex);
        }
        return $res;
    }

    public function getReportePorDocente($anio = null, $mes = null, $codDocente = null, $codHorario = null): R {
        $res = new R();
        try {
            $anioFilter = ($anio !== null && $anio !== '') ? (int) $anio : null;
            $mesFilter = ($mes !== null && $mes !== '') ? (int) $mes : null;
            $docenteFilter = ($codDocente !== null && $codDocente !== '') ? (int) $codDocente : null;
            $cursoFilter = ($codHorario !== null && $codHorario !== '') ? (int) $codHorario : null;

            $sql = "
                SELECT
                    h.cod_horario,
                    h.seccion,
                    h.anio,
                    h.mes,
                    cp.nombre as nombre_curso,
                    u.cod_usuario as cod_docente,
                    CONCAT(u.nombres, ' ', u.apellidos) as nombre_docente,
                    COUNT(er.id) as total_evaluaciones
                FROM horario h
                JOIN curso_pensum cp ON h.cod_pensum = cp.cod_pensum AND h.cod_curso = cp.cod_curso
                LEFT JOIN usuario u ON h.cod_usuario_catedratico = u.cod_usuario
                JOIN evaluacion_respuesta er ON h.cod_horario = er.cod_horario
                WHERE 1=1
                " . ($anioFilter !== null ? " AND h.anio = " . $anioFilter : "") . "
                " . ($mesFilter !== null ? " AND h.mes = " . $mesFilter : "") . "
                " . ($docenteFilter !== null ? " AND u.cod_usuario = " . $docenteFilter : "") . "
                " . ($cursoFilter !== null ? " AND h.cod_horario = " . $cursoFilter : "") . "
                GROUP BY h.cod_horario, h.seccion, h.anio, h.mes, cp.nombre, u.cod_usuario
                ORDER BY nombre_docente, h.anio DESC, h.mes DESC, cp.nombre
            ";
            $stmt = $this->dbAdapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
            $cursos = $stmt->toArray();

            if (empty($cursos)) {
                $res->success();
                $res->setObj([]);
                return $res;
            }

            $horarioIds = array_column($cursos, 'cod_horario');
            $inClause = implode(',', array_map('intval', $horarioIds));

            $sqlPreguntas = "
                SELECT 
                    er.cod_horario,
                    p.id as id_pregunta,
                    p.texto,
                    p.tipo,
                    COUNT(erd.id) as count_respuestas,
                    AVG(CASE WHEN p.tipo = 'escala10' THEN CAST(erd.respuesta AS DECIMAL(10,2)) END) as promedio,
                    SUM(CASE WHEN p.tipo = 'boolean' AND erd.respuesta = 'si' THEN 1 ELSE 0 END) as count_si,
                    SUM(CASE WHEN p.tipo = 'boolean' THEN 1 ELSE 0 END) as count_boolean_total
                FROM evaluacion_respuesta er
                JOIN evaluacion_respuesta_detalle erd ON er.id = erd.id_evaluacion_respuesta
                JOIN evaluacion_pregunta p ON erd.id_pregunta = p.id
                WHERE er.cod_horario IN ($inClause)
                GROUP BY er.cod_horario, p.id
                ORDER BY er.cod_horario, p.orden
            ";
            $stmtPreguntas = $this->dbAdapter->query($sqlPreguntas, Adapter::QUERY_MODE_EXECUTE);
            $preguntasRows = $stmtPreguntas->toArray();

            $sqlDistribucion = "
                SELECT
                    er.cod_horario,
                    p.id as id_pregunta,
                    erd.respuesta as valor,
                    COUNT(*) as cantidad
                FROM evaluacion_respuesta er
                JOIN evaluacion_respuesta_detalle erd ON er.id = erd.id_evaluacion_respuesta
                JOIN evaluacion_pregunta p ON erd.id_pregunta = p.id
                WHERE er.cod_horario IN ($inClause)
                  AND p.tipo = 'escala10'
                GROUP BY er.cod_horario, p.id, erd.respuesta
                ORDER BY er.cod_horario, p.id, CAST(erd.respuesta AS UNSIGNED)
            ";
            $stmtDistribucion = $this->dbAdapter->query($sqlDistribucion, Adapter::QUERY_MODE_EXECUTE);
            $distribucionRows = $stmtDistribucion->toArray();

            $distribucionMap = [];
            foreach ($distribucionRows as $row) {
                $key = (int) $row['cod_horario'];
                $idPregunta = (int) $row['id_pregunta'];
                $valor = (int) $row['valor'];
                if (!isset($distribucionMap[$key])) {
                    $distribucionMap[$key] = [];
                }
                if (!isset($distribucionMap[$key][$idPregunta])) {
                    $distribucionMap[$key][$idPregunta] = [];
                }
                $distribucionMap[$key][$idPregunta][$valor] = (int) $row['cantidad'];
            }

            $sqlComentarios = "
                SELECT
                    er.cod_horario,
                    p.id as id_pregunta,
                    erd.respuesta as comentario
                FROM evaluacion_respuesta er
                JOIN evaluacion_respuesta_detalle erd ON er.id = erd.id_evaluacion_respuesta
                JOIN evaluacion_pregunta p ON erd.id_pregunta = p.id
                WHERE er.cod_horario IN ($inClause)
                  AND p.tipo = 'texto'
                  AND erd.respuesta IS NOT NULL
                  AND TRIM(erd.respuesta) != ''
                ORDER BY er.cod_horario, p.id
            ";
            $stmtComentarios = $this->dbAdapter->query($sqlComentarios, Adapter::QUERY_MODE_EXECUTE);
            $comentariosRows = $stmtComentarios->toArray();

            $reporte = [];
            foreach ($cursos as $curso) {
                $key = (int) $curso['cod_horario'];
                $reporte[$key] = [
                    'cod_horario' => $key,
                    'nombre_curso' => $curso['nombre_curso'],
                    'seccion' => $curso['seccion'],
                    'anio' => $curso['anio'],
                    'mes' => $curso['mes'],
                    'nombre_docente' => $curso['nombre_docente'] ?? 'Docente no asignado',
                    'cod_docente' => $curso['cod_docente'],
                    'total_evaluaciones' => (int) $curso['total_evaluaciones'],
                    'preguntas' => [],
                    'comentarios' => [],
                ];
            }

            foreach ($preguntasRows as $row) {
                $key = (int) $row['cod_horario'];
                if (!isset($reporte[$key])) {
                    continue;
                }
                $idPregunta = (int) $row['id_pregunta'];
                $distribucion = $distribucionMap[$key][$idPregunta] ?? [];
                $reporte[$key]['preguntas'][] = [
                    'id_pregunta' => $idPregunta,
                    'texto' => $row['texto'],
                    'tipo' => $row['tipo'],
                    'count_respuestas' => (int) $row['count_respuestas'],
                    'promedio' => $row['promedio'] !== null ? round((float) $row['promedio'], 2) : null,
                    'count_si' => (int) $row['count_si'],
                    'count_boolean_total' => (int) $row['count_boolean_total'],
                    'distribucion' => $distribucion,
                ];
            }

            foreach ($comentariosRows as $row) {
                $key = (int) $row['cod_horario'];
                if (!isset($reporte[$key])) {
                    continue;
                }
                $reporte[$key]['comentarios'][] = [
                    'id_pregunta' => (int) $row['id_pregunta'],
                    'comentario' => $row['comentario'],
                ];
            }

            foreach ($reporte as $key => &$item) {
                $suma = 0;
                $count = 0;
                foreach ($item['preguntas'] as $pregunta) {
                    if ($pregunta['tipo'] === 'escala10' && $pregunta['promedio'] !== null) {
                        $suma += $pregunta['promedio'];
                        $count++;
                    }
                }
                if ($count > 0) {
                    $nota = round(($suma / $count) * 10, 2);
                    $item['nota_final_100'] = $nota;
                    if ($nota >= 90) {
                        $item['mensaje_rendimiento'] = 'Sobresaliente';
                    } elseif ($nota >= 70) {
                        $item['mensaje_rendimiento'] = 'Satisfactorio';
                    } elseif ($nota >= 60) {
                        $item['mensaje_rendimiento'] = 'Regular';
                    } else {
                        $item['mensaje_rendimiento'] = 'Necesita mejora';
                    }
                } else {
                    $item['nota_final_100'] = null;
                    $item['mensaje_rendimiento'] = 'Sin evaluación numérica';
                }
            }
            unset($item);

            $res->success();
            $res->setObj(array_values($reporte));
        } catch (\Exception $ex) {
            $res->failure('No se pudo generar el reporte por docente', $ex);
        }
        return $res;
    }

    public function getEvaluacionesDetalle($anio = null, $mes = null, $codDocente = null, $codHorario = null): R {
        $res = new R();
        try {
            $anioFilter = ($anio !== null && $anio !== '') ? (int) $anio : null;
            $mesFilter = ($mes !== null && $mes !== '') ? (int) $mes : null;
            $docenteFilter = ($codDocente !== null && $codDocente !== '') ? (int) $codDocente : null;
            $cursoFilter = ($codHorario !== null && $codHorario !== '') ? (int) $codHorario : null;

            $sql = "
                SELECT
                    er.id,
                    er.fecha_evaluacion,
                    h.cod_horario,
                    h.anio,
                    h.mes,
                    h.seccion,
                    cp.nombre as nombre_curso,
                    CONCAT(u.nombres, ' ', u.apellidos) as nombre_docente,
                    p.id as id_pregunta,
                    p.texto,
                    p.tipo,
                    p.orden,
                    erd.respuesta
                FROM evaluacion_respuesta er
                JOIN horario h ON er.cod_horario = h.cod_horario
                JOIN curso_pensum cp ON h.cod_pensum = cp.cod_pensum AND h.cod_curso = cp.cod_curso
                LEFT JOIN usuario u ON h.cod_usuario_catedratico = u.cod_usuario
                JOIN evaluacion_respuesta_detalle erd ON er.id = erd.id_evaluacion_respuesta
                JOIN evaluacion_pregunta p ON erd.id_pregunta = p.id
                WHERE 1=1
                " . ($anioFilter !== null ? " AND h.anio = " . $anioFilter : "") . "
                " . ($mesFilter !== null ? " AND h.mes = " . $mesFilter : "") . "
                " . ($docenteFilter !== null ? " AND u.cod_usuario = " . $docenteFilter : "") . "
                " . ($cursoFilter !== null ? " AND h.cod_horario = " . $cursoFilter : "") . "
                ORDER BY er.fecha_evaluacion, h.anio DESC, h.mes DESC, p.orden
            ";
            $stmt = $this->dbAdapter->query($sql, Adapter::QUERY_MODE_EXECUTE);
            $rows = $stmt->toArray();

            $evaluaciones = [];
            foreach ($rows as $row) {
                $id = (int) $row['id'];
                if (!isset($evaluaciones[$id])) {
                    $evaluaciones[$id] = [
                        'id' => $id,
                        'fecha_evaluacion' => $row['fecha_evaluacion'],
                        'cod_horario' => (int) $row['cod_horario'],
                        'nombre_docente' => $row['nombre_docente'] ?? 'Docente no asignado',
                        'nombre_curso' => $row['nombre_curso'],
                        'seccion' => $row['seccion'],
                        'anio' => $row['anio'],
                        'mes' => $row['mes'],
                        'respuestas' => [],
                    ];
                }
                $evaluaciones[$id]['respuestas'][$row['id_pregunta']] = [
                    'texto' => $row['texto'],
                    'tipo' => $row['tipo'],
                    'valor' => $row['respuesta'],
                ];
            }

            $res->success();
            $res->setObj(array_values($evaluaciones));
        } catch (\Exception $ex) {
            $res->failure('No se pudo generar el detalle de evaluaciones', $ex);
        }
        return $res;
    }
}
