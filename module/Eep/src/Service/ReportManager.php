<?php

namespace Eep\Service;

use Zend\Db\TableGateway\TableGateway;
use Eep\Entity\Order;
use Eep\Entity\OrderDetail;
use Eep\Entity\User;
use Eep\Entity\Table;
use Eep\Entity\Timetable;
use Eep\Entity\Result as R;
use Zend\Db\Sql\Expression;
use Zend\Db\Adapter\Exception\InvalidQueryException;
use SIIF\Model\SIIFOrdenPago;
use Zend\Db\Sql\Select;
use Spipu\Html2Pdf\Html2Pdf;
use Spipu\Html2Pdf\Exception\Html2PdfException;
use Zend\View\Renderer\PhpRenderer;
use Eep\Service\AssignmentManager as AM;
use Eep\Form\AssignmentForm;

class ReportManager extends Manager {

    private function moneyFormat($value) {
        return /* 'Q.' . */ number_format($value, 2);
    }

    public function getGralReport($degree, $pensum, $cohort, $startDate, $finishDate): R {
        if (empty($pensum)) {
            if (empty($degree)) {
                //HIGHEST LEVEL REPORT
                $res = $this->getAllDegreesReport($cohort, $startDate, $finishDate);
            } else {
                //DEGREE LEVEL REPORT
                $res = $this->getDegreeReport($degree, $cohort, $startDate, $finishDate);
            }
        } else {
            //PENSUM LEVEL REPORT
            $res = $this->getPensumReport($pensum, $cohort, $startDate, $finishDate);
        }
        return $res;
    }

    private function getAllDegreesReport($cohort, $startDate, $finishDate): R {
        $res = new R();
        $headers = ['Estudio de Postgrados', 'Ingresos (Q.)', 'Acumulado (Q.)'];
        $alignment = [Table::LEFT, Table::RIGHT, Table::RIGHT];
        $report = new Table('Reporte General - Estudios de Postgrado', $headers, $alignment);
        //GENERAL SELECT
        $table = new TableGateway(['ga' => 'grado_academico'], $this->dbAdapter);
        $select = $table->getSql()->select();
        $select->columns([]);
        //DOWNSCALING TO PAYMENT ORDER
        $select->join(['ca' => 'carrera'], 'ga.cod_grado = ca.cod_grado', []);
        $select->join(['p' => 'pensum'], 'ca.cod_carrera = p.cod_carrera', []);
        $select->join(['h' => 'horario'], 'p.cod_pensum = h.cod_pensum', []);
        $select->join(['cop' => 'cursos_orden_pago'], 'h.cod_horario = cop.cod_horario', []);
        $select->join(['o' => 'orden_pago'], 'cop.cod_orden = o.cod_orden', []);
        $select->where([
            'o.pagada' => 1, //TRUE
            "o.fecha_pago >= '$startDate'",
            "o.fecha_pago <= '$finishDate'"
        ]);
        if ($cohort != null) {
            $select->where([
                'h.fecha_cohorte' => $cohort
            ]);
        }
        $select->group('ga.cod_grado');
        $select->order('ga.cod_grado ASC');
        $select->columns([
            'grade' => 'nombre',
            'income' => new Expression('SUM(cop.monto)')
        ]);
        try {
            //CLEANING DATA
            $resultData = $table->selectWith($select)->toArray();
            $accumulated = 0;
            foreach ($resultData as $rowData) {
                $gradeName = $rowData['grade'];
                $ammount = $rowData['income'];
                $accumulated += $ammount;
                $row = [$gradeName, $this->moneyFormat($ammount), $this->moneyFormat($accumulated)];
                $report->addRow($row);
            }
            //CREATING FOOTER
            $footer = ['TOTALES', $this->moneyFormat($accumulated), ''];
            $report->addFooter($footer, null);
            $res->success();
            $res->setObj($report);
        } catch (\Exception $ex) {
            $res->addMsg('No se pudo generar el reporte general');
        }
        return $res;
    }

    private function getDegreeReport($degree, $cohort, $startDate, $finishDate): R {
        $res = new R();
        //GETTING DEGREE NAME
        $degreeTable = new TableGateway('grado_academico', $this->dbAdapter);
        $degreeResult = $degreeTable->select([
            'cod_grado' => $degree
        ]);
        if ($degreeResult->count() == 0) {
            $res->addMsg('No existe el grado seleccionado');
        } else {
            $degreeName = $degreeResult->current()['nombre'];
            $headers = ['Código Carrera', 'Nombre de la Carrera', 'Ingresos (Q.)', 'Acumulado (Q.)'];
            $alignment = [Table::LEFT, Table::LEFT, Table::RIGHT, Table::RIGHT];
            $report = new Table('Reporte de Grado - ' . $degreeName, $headers, $alignment);
            //GENERAL SELECT
            $table = new TableGateway(['ca' => 'carrera'], $this->dbAdapter);
            $select = $table->getSql()->select();
            $select->columns([]);
            //DOWNSCALING TO PAYMENT ORDER
            $select->join(['p' => 'pensum'], 'ca.cod_carrera = p.cod_carrera', []);
            $select->join(['h' => 'horario'], 'p.cod_pensum = h.cod_pensum', []);
            $select->join(['cop' => 'cursos_orden_pago'], 'h.cod_horario = cop.cod_horario', []);
            $select->join(['o' => 'orden_pago'], 'cop.cod_orden = o.cod_orden', []);
            $select->where([
                'ca.cod_grado' => $degree,
                'o.pagada' => 1, //TRUE
                "o.fecha_pago >= '$startDate'",
                "o.fecha_pago <= '$finishDate'"
            ]);
            if ($cohort != null) {
                $select->where([
                    'h.fecha_cohorte' => $cohort
                ]);
            }
            $select->group('ca.cod_carrera');
            $select->order('ca.cod_carrera ASC');
            $select->columns([
                'cod_carrera',
                'career_name' => 'nombre_actual',
                'income' => new Expression('SUM(cop.monto)')
            ]);
            try {
                //CLEANING DATA
                $resultData = $table->selectWith($select)->toArray();
                $accumulated = 0;
                foreach ($resultData as $rowData) {
                    $careerCode = $rowData['cod_carrera'];
                    $careerName = $rowData['career_name'];
                    $ammount = $rowData['income'];
                    $accumulated += $ammount;
                    $row = [$careerCode, $careerName, $this->moneyFormat($ammount), $this->moneyFormat($accumulated)];
                    $report->addRow($row);
                }
                //CREATING FOOTER
                $footer = ['TOTALES', $this->moneyFormat($accumulated), ''];
                $report->addFooter($footer, null);
                $res->success();
                $res->setObj($report);
            } catch (\Exception $ex) {
                $res->addMsg('No se pudo generar el reporte');
            }
        }
        return $res;
    }

    public function getPensumReport($pensum, $cohort, $startDate, $finishDate): R {
        $res = new R();
        //GETTING PENSUM NAME
        $pensumTable = new TableGateway('pensum', $this->dbAdapter);
        $pensumResult = $pensumTable->select([
            'cod_pensum' => $pensum
        ]);
        if ($pensumResult->count() == 0) {
            $res->addMsg('No existe el pensum seleccionado');
        } else {
            $pensumData = $pensumResult->current();
            $pensumDescription = $pensumData['descripcion'];
            $pensumCode = $pensumData['cod_pensum'];
            $pensumCareerCode = $pensumData['cod_carrera'];
            $headers = ['Código Curso', 'Nombre del Curso', 'Ingresos (Q.)', 'Acumulado (Q.)'];
            $alignment = [Table::LEFT, Table::LEFT, Table::RIGHT, Table::RIGHT];
            $report = new Table("Reporte de Carrera Cód. $pensumCareerCode - Pensum Cód. $pensumCode - $pensumDescription", $headers, $alignment);
            //GENERAL SELECT
            $table = new TableGateway(['cp' => 'curso_pensum'], $this->dbAdapter);
            $select = $table->getSql()->select();
            $select->columns([]);
            //DOWNSCALING TO PAYMENT ORDER
            $select->join(['h' => 'horario'], 'cp.cod_pensum = h.cod_pensum and cp.cod_curso = h.cod_curso', []);
            $select->join(['cop' => 'cursos_orden_pago'], 'h.cod_horario = cop.cod_horario', []);
            $select->join(['o' => 'orden_pago'], 'cop.cod_orden = o.cod_orden', []);
            $select->where([
                'cp.cod_pensum' => $pensum,
                'o.pagada' => 1, //TRUE
                "o.fecha_pago >= '$startDate'",
                "o.fecha_pago <= '$finishDate'"
            ]);
            if ($cohort != null) {
                $select->where([
                    'h.fecha_cohorte' => $cohort
                ]);
            }
            $select->group('h.cod_curso');
            $select->order('h.cod_curso ASC');
            $select->columns([
                'cod_curso',
                'nombre_curso' => 'nombre',
                'income' => new Expression('SUM(cop.monto)')
            ]);
            try {
                //CLEANING DATA
                $resultData = $table->selectWith($select)->toArray();
                $accumulated = 0;
                foreach ($resultData as $rowData) {
                    $courseCode = $rowData['cod_curso'];
                    $courseName = $rowData['nombre_curso'];
                    $ammount = $rowData['income'];
                    $accumulated += $ammount;
                    $row = [$courseCode, $courseName, $this->moneyFormat($ammount), $this->moneyFormat($accumulated)];
                    $report->addRow($row);
                }
                //CREATING FOOTER
                $footer = ['TOTALES', $this->moneyFormat($accumulated), ''];
                $report->addFooter($footer, null);
                $res->success();
                $res->setObj($report);
            } catch (\Exception $ex) {
                $res->addMsg('No se pudo generar el reporte' . $ex->getMessage());
            }
        }
        return $res;
    }

    public function getCourseReport($course, $pensum, $cohort, $startDate, $finishDate): R {
        $res = $this->getCourseTimetableReport($startDate, $finishDate, $cohort, $course, $pensum);
        return $res;
    }

    public function getTimetableReport($timetable, $startDate, $finishDate): R {
        $res = $this->getCourseTimetableReport($startDate, $finishDate, null, null, null, $timetable);
        return $res;
    }

    private function getCourseTimetableReport($startDate, $finishDate, $cohort, $courseCode, $pensumCode, $timetableCode = null): R {
        $res = new R();
        //GETTING COURSE NAME
        $courseTable = new TableGateway('curso_pensum', $this->dbAdapter);
        if ($timetableCode != null) {
            $select = $courseTable->getSql()->select();
            $select->join(['h' => 'horario'], 'curso_pensum.cod_pensum = h.cod_pensum and curso_pensum.cod_curso = h.cod_curso');
            $select->where([
                'cod_horario' => $timetableCode
            ]);
            $courseResult = $courseTable->selectWith($select);
        } else {
            $courseResult = $courseTable->select([
                'cod_curso' => $courseCode,
                'cod_pensum' => $pensumCode
            ]);
        }
        if ($courseResult->count() == 0) {
            $res->addMsg('No existe el ' . ($timetableCode == null) ? 'curso' : 'horario' . ' seleccionado');
        } else {
            //GETTING FORM TITLE
            $courseData = $courseResult->current();
            $courseCode = $courseData['cod_curso'];
            $courseName = $courseData['nombre'];
            if ($timetableCode != null) {
                $month = AssignmentForm::MONTHS[$courseData['mes']];
                $section = $courseData['seccion'];
                $cohortDate = date('d/m/Y', strtotime($courseData['fecha_cohorte']));
                $courseName = "$courseName - Sección $section [$month] - Cohorte: $cohortDate";
            }
            $title = "$courseCode - $courseName";
            //CREATING REPORT OBJECT
            $headers = [];
            $alignment = [];
            if ($timetableCode == null) {
                //SPECIFYING SECTION
                $headers = ['Sección', 'Mes Impartido'];
                $alignment = [Table::LEFT, Table::LEFT];
                if ($cohort == null) {
                    $headers [] = 'Cohorte';
                    $alignment[] = Table::RIGHT;
                }
            }
            $headers = array_merge($headers, ['CUI/Pasaporte', 'Registro Académico', 'Nombres', 'Apellidos', 'Banco', 'No. Orden', 'No. Boleta', 'Fecha de Pago', 'Fecha Asignación', 'Monto (Q.)', 'Acumulado (Q.)']);
            $alignment = array_merge($alignment, [Table::LEFT, Table::LEFT, Table::LEFT, Table::LEFT, Table::CENTER, Table::CENTER, Table::CENTER, Table::RIGHT, Table::RIGHT, Table::RIGHT, Table::RIGHT]);
            $report = new Table($title, $headers, $alignment);
            $report->setStrippedTable(false);
            //GENERAL SELECT
            $table = new TableGateway(['h' => 'horario'], $this->dbAdapter);
            $select = $table->getSql()->select();
            //DOWNSCALING TO PAYMENT ORDER
            $select->join(['cop' => 'cursos_orden_pago'], 'h.cod_horario = cop.cod_horario', ['monto']);
            $select->join(['o' => 'orden_pago'], 'cop.cod_orden = o.cod_orden');
            $select->join(['b' => 'banco'], 'o.cod_banco = b.cod_banco', ['banco' => 'nombre'], Select::JOIN_LEFT);
            $select->join(['u' => 'usuario'], 'o.cod_usuario = u.cod_usuario');
            $select->join(['a' => 'asignacion'], 'h.cod_horario = a.cod_horario and u.cod_usuario = a.cod_usuario', ['fecha_asignacion', 'valida'], Select::JOIN_LEFT);
            //FILTERING DATA
            $select->where([
                "( (o.fecha_pago >= '$startDate' and o.fecha_pago <= '$finishDate')"
                . " or "
                . "(o.fecha_generacion >= '$startDate' and o.fecha_generacion <= '$finishDate') )",
                '(o.pagada = 1 or o.activa = 1)'
            ]);
            if ($timetableCode != null) {
                $select->where([
                    'h.cod_horario' => $timetableCode
                ]);
            } else {
                if ($cohort != null) {
                    $select->where([
                        'h.fecha_cohorte' => $cohort
                    ]);
                }
                if($pensumCode!= null){
                    $select->where([
                        'h.cod_pensum' => $pensumCode
                    ]);
                }
                $select->where([
                    'h.cod_curso' => $courseCode
                ]);
            }
            //ORDERING DATA
            $select->order('h.fecha_inicio ASC');
            $select->order('h.seccion ASC');
            $select->order('h.fecha_cohorte ASC');
            $select->order('u.nombres ASC');
            $select->order('o.fecha_pago ASC');
            $select->order('a.fecha_asignacion ASC');
            try {
                //CLEANING DATA AND FILLING TABLE
                $resultData = $table->selectWith($select)->toArray();
                $accumulated = 0;
                $notPayedAmmount = 0;
                $payedAmmount = 0;
                $assignedStudents = 0;
                foreach ($resultData as $data) {
                    $cui = $data['cui'];
                    $passport = $data['pasaporte'];
                    $academicRegistry = $data['registro_academico'];
                    $names = $data['nombres'];
                    $lastNames = $data['apellidos'];
                    $bank = $data['banco'];
                    $orderCode = $data['cod_orden'];
                    $ballotCode = $data['cod_boleta'];
                    $paymentDate = empty($data['fecha_pago']) ? null : date('d/m/Y', strtotime($data['fecha_pago']));
                    $assignmentDate = empty($data['fecha_asignacion']) ? null : date('d/m/Y', strtotime($data['fecha_asignacion']));
                    $valid = empty($data['valida']) ? false : true;
                    $ammount = $data['monto'];
                    $accumulated += $ammount;
                    if ($data['pagada'] == true) {
                        $payedAmmount += $ammount;
                    } else {
                        $notPayedAmmount += $ammount;
                    }
                    if ($assignmentDate != null) {
                        $assignedStudents ++;
                    }
                    $row = [$cui ?? $passport, $academicRegistry, $names, $lastNames, $bank, $orderCode, $ballotCode, $paymentDate, $assignmentDate, $this->moneyFormat($ammount), $this->moneyFormat($accumulated)];
                    if ($timetableCode == null) {
                        if (empty($cohort)) {
                            $cohortDate = empty($data['fecha_cohorte']) ? '' : date('d/m/Y', strtotime($data['fecha_cohorte']));
                            $row = array_merge([$cohortDate], $row);
                        }
                        $section = $data['seccion'];
                        $month = AssignmentForm::MONTHS[$data['mes']];
                        $row = array_merge([$section, $month], $row);
                    }
                    //SETTING COLOR
                    /*
                     * PAYED AND ASSIGNED: GREEN
                     * PENDING TO PAY: YELLOW
                     * PAYED NOT ASSIGNED: RED
                     * COURSE INVALIDATED: BLUE
                     */

                    if (empty($assignmentDate)) {
                        if (empty($paymentDate)) {
                            $color = Table::WARNING;
                        } else {
                            $color = Table::DANGER;
                        }
                    } else {
                        if ($valid) {
                            $color = Table::SUCCESS;
                        } else {
                            $color = Table::BG_DANGER;
                        }
                    }
                    $report->addRow($row, $color);
                }
                //CREATING FOOTER
                $footer = ['TOTAL GENERAL', "$assignedStudents Estudiante" . (($assignedStudents != 1) ? 's' : ''), $this->moneyFormat($accumulated), null];
                $report->addFooter($footer, null);
                $footer = ['TOTAL PAGADO', $this->moneyFormat($payedAmmount), null];
                $report->addFooter($footer, [null, Table::SUCCESS]);
                $footer = ['TOTAL PENDIENTE', $this->moneyFormat($notPayedAmmount), null];
                $report->addFooter($footer, [null, Table::WARNING]);
                $res->success();
                $res->setObj($report);
            } catch (\Exception $ex) {
                $res->addMsg('No se pudo generar el reporte' . $ex->getMessage());
            }
        }
        return $res;
    }

}
