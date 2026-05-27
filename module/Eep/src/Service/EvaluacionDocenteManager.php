<?php

namespace Eep\Service;

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
}
