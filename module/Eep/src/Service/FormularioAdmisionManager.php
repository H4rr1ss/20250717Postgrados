<?php

namespace Eep\Service;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Expression;
use Eep\Entity\Result as R;
use Eep\Entity\FormularioAdmision;
use Eep\Entity\Aspirante;
use Eep\Entity\RespuestaAspirante;
use Eep\Entity\CampoFormulario;

class FormularioAdmisionManager extends Manager {
    /**
     * Archiva (deshabilita) un formulario de admisión
     */
    public function archivarFormulario($idFormulario) {
        $res = new R();
        try {
            $table = new TableGateway('formulario_admision', $this->dbAdapter);
            $updateResult = $table->update(['activo' => 0], ['id_formulario' => $idFormulario]);
            if ($updateResult) {
                $res->success();
            } else {
                $res->failure('No se pudo archivar el formulario (no se encontró o ya estaba archivado)');
            }
        } catch (\Exception $ex) {
            $res->failure('Error al archivar el formulario: ' . $ex->getMessage());
        }
        return $res;
    }
    /**
     * Elimina un formulario y sus datos relacionados
     */
    public function eliminarFormulario($idFormulario) {
        $res = new R();
        try {
            // Eliminar respuestas asociadas
            $respTable = new TableGateway('respuesta_aspirante', $this->dbAdapter);
            $respTable->delete(['id_formulario' => $idFormulario]);
            // Eliminar campos de formulario
            $campoTable = new TableGateway('campo_formulario', $this->dbAdapter);
            $campoTable->delete(['id_formulario' => $idFormulario]);
            // Eliminar el formulario
            $formTable = new TableGateway('formulario_admision', $this->dbAdapter);
            $deleted = $formTable->delete(['id_formulario' => $idFormulario]);
            if ($deleted > 0) {
                $res->success();
            } else {
                $res->failure('No se encontró el formulario para eliminar');
            }
        } catch (\Exception $ex) {
            $res->failure('Error al eliminar el formulario: ' . $ex->getMessage());
        }
        return $res;
    }
    
    // MÉTODOS PARA VISTA 1: Lista de formularios
    
    /**
     * Obtiene formularios activos con cantidad de respuestas
     */
    public function getFormulariosActivos() {
        $res = new R();
        try {
            $table = new TableGateway(['f' => 'formulario_admision'], $this->dbAdapter);
            $select = $table->getSql()->select();
            
            // JOIN para contar respuestas
            $select->join(['r' => 'respuesta_aspirante'], 'f.id_formulario = r.id_formulario', 
                         ['total_respuestas' => new Expression('COUNT(r.id_respuesta)')], Select::JOIN_LEFT);
            
            // JOIN para obtener datos del creador
            $select->join(['u' => 'usuario'], 'f.creado_por = u.cod_usuario', 
                         ['creador_nombres' => 'nombres', 'creador_apellidos' => 'apellidos'], Select::JOIN_LEFT);
            
            $select->where(['f.activo' => 1]);
            $select->group('f.id_formulario');
            $select->order('f.fecha_creacion DESC');
            
            $result = $table->selectWith($select)->toArray();
            
            $formularios = [];
            foreach ($result as $row) {
                $formularios[] = new FormularioAdmision($row);
            }
            
            $res->success();
            $res->setObj($formularios);
            
        } catch (\Exception $ex) {
            $res->failure('No se pudieron obtener los formularios activos: ' . $ex->getMessage());
        }
        
        return $res;
    }
    
    /**
     * Obtiene formularios archivados
     */
    public function getFormulariosArchivados() {
        $res = new R();
        try {
            $table = new TableGateway(['f' => 'formulario_admision'], $this->dbAdapter);
            $select = $table->getSql()->select();
            
            // JOIN para contar respuestas
            $select->join(['r' => 'respuesta_aspirante'], 'f.id_formulario = r.id_formulario', 
                         ['total_respuestas' => new Expression('COUNT(r.id_respuesta)')], Select::JOIN_LEFT);
            
            $select->where(['f.activo' => 0]);
            $select->group('f.id_formulario');
            $select->order('f.fecha_creacion DESC');
            
            $result = $table->selectWith($select)->toArray();
            
            $formularios = [];
            foreach ($result as $row) {
                $formularios[] = new FormularioAdmision($row);
            }
            
            $res->success();
            $res->setObj($formularios);
            
        } catch (\Exception $ex) {
            $res->failure('No se pudieron obtener los formularios archivados: ' . $ex->getMessage());
        }
        
        return $res;
    }
    
    // MÉTODOS PARA VISTA 2: Lista de respuestas
    
    /**
     * Obtiene todas las respuestas de un formulario específico
     */
    public function getRespuestasFormulario($idFormulario) {
        $res = new R();
        try {
            $table = new TableGateway(['r' => 'respuesta_aspirante'], $this->dbAdapter);
            $select = $table->getSql()->select();
            
            // JOIN con aspirante para obtener datos básicos
            $select->join(['a' => 'aspirante'], 'r.aspirante_id = a.id', 
                         ['aspirante_cui' => 'cui', 'aspirante_nombres' => 'nombres', 
                          'aspirante_apellidos' => 'apellidos', 'aspirante_correo_electronico' => 'correo_electronico',
                          'aspirante_telefono' => 'telefono', 'aspirante_photo_dpi' => 'photo_dpi']);
            
            $select->where(['r.id_formulario' => $idFormulario]);
            $select->order('r.fecha_envio DESC');
            
            $result = $table->selectWith($select)->toArray();
            
            $respuestas = [];
            foreach ($result as $row) {
                $respuestas[] = new RespuestaAspirante($row);
            }
            
            $res->success();
            $res->setObj($respuestas);
            
        } catch (\Exception $ex) {
            $res->failure('No se pudieron obtener las respuestas del formulario: ' . $ex->getMessage());
        }
        
        return $res;
    }
    
    /**
     * Obtiene información completa del formulario
     */
    public function getFormulario($idFormulario) {
        $res = new R();
        try {
            $table = new TableGateway('formulario_admision', $this->dbAdapter);
            $result = $table->select(['id_formulario' => $idFormulario]);
            
            if ($result->count() > 0) {
                $formulario = new FormularioAdmision($result->current());
                $res->success();
                $res->setObj($formulario);
            } else {
                $res->failure('Formulario no encontrado');
            }
            
        } catch (\Exception $ex) {
            $res->failure('Error al obtener el formulario: ' . $ex->getMessage());
        }
        
        return $res;
    }
    
    // MÉTODOS PARA VISTA 3: Respuesta detallada
    
    /**
     * Obtiene respuesta detallada con todos los campos
     */
    public function getRespuestaDetallada($idRespuesta) {
        $res = new R();
        try {
            // Obtener respuesta básica
            $respuestaTable = new TableGateway(['r' => 'respuesta_aspirante'], $this->dbAdapter);
            $select = $respuestaTable->getSql()->select();
            
            // JOIN con aspirante
            $select->join(['a' => 'aspirante'], 'r.aspirante_id = a.id', 
                         ['aspirante_cui' => 'cui', 'aspirante_nombres' => 'nombres', 
                          'aspirante_apellidos' => 'apellidos', 'aspirante_correo_electronico' => 'correo_electronico',
                          'aspirante_telefono' => 'telefono', 'aspirante_photo_dpi' => 'photo_dpi']);
            
            $select->where(['r.id_respuesta' => $idRespuesta]);
            
            $respuestaData = $respuestaTable->selectWith($select)->current();
            
            if (!$respuestaData) {
                $res->failure('Respuesta no encontrada');
                return $res;
            }
            
            $respuesta = new RespuestaAspirante($respuestaData);
            
            // Obtener respuestas por campo
            $camposTable = new TableGateway(['rc' => 'respuesta_campo'], $this->dbAdapter);
            $select = $camposTable->getSql()->select();
            
            // JOIN con campo_formulario para obtener metadatos
            $select->join(['cf' => 'campo_formulario'], 'rc.id_campo = cf.id_campo', 
                         ['nombre_campo', 'etiqueta', 'tipo_campo', 'opciones', 'requerido', 'orden_campo']);
            
            $select->where(['rc.id_respuesta' => $idRespuesta]);
            $select->order('cf.orden_campo ASC');
            
            $respuestasCampos = $camposTable->selectWith($select)->toArray();
            $respuesta->setRespuestasCampos($respuestasCampos);
            
            $res->success();
            $res->setObj($respuesta);
            
        } catch (\Exception $ex) {
            $res->failure('Error al obtener la respuesta detallada: ' . $ex->getMessage());
        }
        
        return $res;
    }
    
    // MÉTODOS AUXILIARES
    
    /**
     * Elimina una respuesta completa
     */
    public function eliminarRespuesta($idRespuesta) {
        $res = new R();
        try {
            $table = new TableGateway('respuesta_aspirante', $this->dbAdapter);
            $deleted = $table->delete(['id_respuesta' => $idRespuesta]);
            
            if ($deleted > 0) {
                $res->success('Respuesta eliminada correctamente');
            } else {
                $res->failure('No se pudo eliminar la respuesta');
            }
            
        } catch (\Exception $ex) {
            $res->failure('Error al eliminar la respuesta: ' . $ex->getMessage());
        }
        
        return $res;
    }
    
    /**
     * Obtiene los campos de un formulario
     */
    public function getCamposFormulario($idFormulario) {
        $res = new R();
        try {
            $table = new TableGateway('campo_formulario', $this->dbAdapter);
            $result = $table->select([
                'id_formulario' => $idFormulario,
                'activo' => 1
            ]);
            
            $campos = [];
            foreach ($result as $row) {
                $campos[] = new CampoFormulario($row);
            }
            
            // Ordenar por orden_campo
            usort($campos, function($a, $b) {
                return $a->getOrdenCampo() <=> $b->getOrdenCampo();
            });
            
            $res->success();
            $res->setObj($campos);
            
        } catch (\Exception $ex) {
            $res->failure('Error al obtener los campos del formulario: ' . $ex->getMessage());
        }
        
        return $res;
    }
    
    /**
     * Crear un nuevo formulario de admisión
     */
    public function crearFormulario($datos) {
        $res = new R();
        
        try {
            $table = new TableGateway('formulario_admision', $this->dbAdapter);
            // Validar que no exista otro formulario activo
            $activeCount = $table->select(['activo' => 1])->count();
            if ($activeCount > 0) {
                $res->failure('Ya existe un formulario activo. Por favor archive primero el formulario actual antes de crear uno nuevo.');
                return $res;
            }
            
            $formularioId = $table->insert([
                'nombre' => $datos['nombre'],
                'fecha_inicio_admision' => $datos['fecha_inicio_admision'],
                'fecha_fin_admision' => $datos['fecha_fin_admision'],
                'creado_por' => $datos['creado_por'],
                'activo' => 1
            ]);
            
            // Obtener el ID del formulario recién creado
            $nuevoId = $this->dbAdapter->getDriver()->getLastGeneratedValue();
            
            // Ejecutar procedimiento que crea los campos predefinidos para este formulario
            try {
                $this->dbAdapter->createStatement('CALL CrearCamposPredefinidos(?)', [$nuevoId])->execute();
            } catch (\Exception $e) {
                // Ignorar error de procedimiento, ya se creó el formulario
            }
            $res->success('Formulario creado correctamente. ID: ' . $nuevoId);
            $res->setObj($nuevoId);
            
        } catch (\Exception $ex) {
            $res->failure('Error al crear el formulario: ' . $ex->getMessage());
        }
        
        return $res;
    }
    /**
     * Registra la respuesta pública: crea aspirante, respuesta_aspirante y respuesta_campo
     */
    public function registrarRespuestaPublica($idFormulario, array $campos, array $data, array $files)
    {
        $res = new R();
        $connection = $this->dbAdapter->getDriver()->getConnection();
        $connection->beginTransaction();
        try {
            // Verificar si aspirante ya existe por CUI
            $aspTable = new TableGateway('aspirante', $this->dbAdapter);
            $existingAspirante = $aspTable->select(['cui' => $data['cui'] ?? null])->current();
            if ($existingAspirante) {
                // Verificar si ya respondió a este formulario
                $respCheck = new TableGateway('respuesta_aspirante', $this->dbAdapter);
                $count = $respCheck->select([
                    'id_formulario' => $idFormulario,
                    'aspirante_id'  => $existingAspirante['id'],
                ])->count();
                if ($count > 0) {
                    $connection->rollback();
                    $res->failure('Ya registró una respuesta. Si desea volver a enviar, comuníquese con el administrador.');
                    return $res;
                }
                $aspiranteId = $existingAspirante['id'];
            } else {
                // Insertar nuevo aspirante
                $aspData = [
                    'cui' => $data['cui'] ?? null,
                    'photo_dpi' => $files['photo_dpi']['name'] ?? null,
                    'nombres' => $data['nombres'] ?? null,
                    'apellidos' => $data['apellidos'] ?? null,
                    'correo_electronico' => $data['correo_electronico'] ?? null,
                    'telefono' => $data['telefono'] ?? null,
                ];
                $aspTable->insert($aspData);
                $aspiranteId = $this->dbAdapter->getDriver()->getLastGeneratedValue();
            }
            // Insertar registro de respuesta
            $respTable = new TableGateway('respuesta_aspirante', $this->dbAdapter);
            $respTable->insert([
                'id_formulario' => $idFormulario,
                'aspirante_id' => $aspiranteId,
            ]);
            $respuestaId = $this->dbAdapter->getDriver()->getLastGeneratedValue();
            // Insertar cada campo de respuesta
            $campoRespTable = new TableGateway('respuesta_campo', $this->dbAdapter);
            foreach ($campos as $campo) {
                $nombre = $campo->getNombreCampo();
                if ($campo->getTipoCampo() === 'archivo') {
                    $valorArchivo = $files[$nombre]['name'] ?? null;
                    $campoRespTable->insert([
                        'id_respuesta' => $respuestaId,
                        'id_campo' => $campo->getIdCampo(),
                        'archivo_adjunto' => $valorArchivo,
                    ]);
                } else {
                    $valor = $data[$nombre] ?? null;
                    $campoRespTable->insert([
                        'id_respuesta' => $respuestaId,
                        'id_campo' => $campo->getIdCampo(),
                        'valor_respuesta' => $valor,
                    ]);
                }
            }
            $connection->commit();
            $res->success();
            $res->setObj($respuestaId);
        } catch (\Exception $ex) {
            $connection->rollback();
            // Error genérico al registrar respuesta
            $res->failure('Error al registrar respuesta: ' . $ex->getMessage());
        }
        return $res;
    }
}
