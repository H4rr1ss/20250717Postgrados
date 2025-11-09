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
    
    /**
     * Obtiene información del formulario por ID de respuesta
     */
    public function getFormularioPorRespuesta($idRespuesta) {
        $res = new R();
        try {
            $respuestaTable = new TableGateway('respuesta_aspirante', $this->dbAdapter);
            $respuestaData = $respuestaTable->select(['id_respuesta' => $idRespuesta])->current();
            
            if ($respuestaData) {
                return $this->getFormulario($respuestaData['id_formulario']);
            } else {
                $res->failure('Respuesta no encontrada');
            }
        } catch (\Exception $ex) {
            $res->failure('Error al obtener el formulario: ' . $ex->getMessage());
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
     * Obtiene todas las respuestas de un formulario específico desde respuesta_campo
     */
    public function getRespuestasFormulario($idFormulario) {
        $res = new R();
        try {
            // Obtener todas las respuestas del formulario
            $respTable = new TableGateway('respuesta_aspirante', $this->dbAdapter);
            $respuestas = $respTable->select(['id_formulario' => $idFormulario]);
            
            $resultado = [];
            
            foreach ($respuestas as $respuesta) {
                $idRespuesta = $respuesta['id_respuesta'];
                
                // Obtener todos los campos de esta respuesta
                $camposTable = new TableGateway(['rc' => 'respuesta_campo'], $this->dbAdapter);
                $select = $camposTable->getSql()->select();
                
                $select->join(['cf' => 'campo_formulario'], 'rc.id_campo = cf.id_campo', 
                             ['nombre_campo', 'etiqueta', 'tipo_campo']);
                
                $select->where(['rc.id_respuesta' => $idRespuesta]);
                
                $campos = $camposTable->selectWith($select)->toArray();
                
                // Construir objeto con datos principales extraídos de campos
                $datosRespuesta = [
                    'id_respuesta' => $idRespuesta,
                    'id_formulario' => $respuesta['id_formulario'],
                    'fecha_envio' => $respuesta['fecha_envio'],
                    'aspirante_cui' => '',
                    'aspirante_nombres' => '',
                    'aspirante_apellidos' => '',
                    'aspirante_correo_electronico' => '',
                    'aspirante_telefono' => '',
                    'aspirante_photo_dpi' => '',
                ];
                
                // Extraer datos principales de los campos
                foreach ($campos as $campo) {
                    switch ($campo['nombre_campo']) {
                        case 'cui':
                            $datosRespuesta['aspirante_cui'] = $campo['valor_respuesta'];
                            break;
                        case 'nombres':
                            $datosRespuesta['aspirante_nombres'] = $campo['valor_respuesta'];
                            break;
                        case 'apellidos':
                            $datosRespuesta['aspirante_apellidos'] = $campo['valor_respuesta'];
                            break;
                        case 'correo_electronico':
                            $datosRespuesta['aspirante_correo_electronico'] = $campo['valor_respuesta'];
                            break;
                        case 'telefono':
                            $datosRespuesta['aspirante_telefono'] = $campo['valor_respuesta'];
                            break;
                        case 'photo_dpi':
                            $datosRespuesta['aspirante_photo_dpi'] = $campo['archivo_adjunto'];
                            break;
                    }
                }
                
                $resultado[] = new RespuestaAspirante($datosRespuesta);
            }
            
            $res->success();
            $res->setObj($resultado);
            
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
     * Obtiene respuesta detallada con todos los campos - solo desde respuesta_campo
     */
    public function getRespuestaDetallada($idRespuesta) {
        $res = new R();
        try {
            // Obtener solo respuestas por campo sin JOIN con aspirante
            $camposTable = new TableGateway(['rc' => 'respuesta_campo'], $this->dbAdapter);
            $select = $camposTable->getSql()->select();
            
            // JOIN con campo_formulario para obtener metadatos
            $select->join(['cf' => 'campo_formulario'], 'rc.id_campo = cf.id_campo', 
                         ['nombre_campo', 'etiqueta', 'tipo_campo', 'opciones', 'requerido', 'orden_campo']);
            
            $select->where(['rc.id_respuesta' => $idRespuesta]);
            $select->order('cf.orden_campo ASC');
            
            $respuestasCampos = $camposTable->selectWith($select)->toArray();
            
            if (empty($respuestasCampos)) {
                $res->failure('Respuesta no encontrada');
                return $res;
            }
            
            $res->success();
            $res->setObj($respuestasCampos);
            
        } catch (\Exception $ex) {
            $res->failure('Error al obtener la respuesta detallada: ' . $ex->getMessage());
        }
        
        return $res;
    }
    
    // MÉTODOS AUXILIARES
    
    /**
     * Actualiza las respuestas de un formulario
     */
    public function actualizarRespuesta($idRespuesta, array $data, array $files = []) {
        $res = new R();
        $connection = $this->dbAdapter->getDriver()->getConnection();
        $connection->beginTransaction();
        
        try {
            $campoRespTable = new TableGateway('respuesta_campo', $this->dbAdapter);
            
            // Iterar sobre los datos enviados
            foreach ($data as $key => $value) {
                // Los campos vienen como "campo_ID"
                if (strpos($key, 'campo_') === 0) {
                    $idCampo = (int) str_replace('campo_', '', $key);
                    
                    // Actualizar el valor en respuesta_campo
                    $campoRespTable->update(
                        ['valor_respuesta' => $value],
                        [
                            'id_respuesta' => $idRespuesta,
                            'id_campo' => $idCampo
                        ]
                    );
                }
            }
            
            // Manejar archivos si hay
            if (!empty($files)) {
                foreach ($files as $key => $fileInfo) {
                    if (strpos($key, 'campo_') === 0 && $fileInfo['error'] === UPLOAD_ERR_OK) {
                        $idCampo = (int) str_replace('campo_', '', $key);
                        
                        // Aquí podrías mover el archivo y guardar el nombre
                        $fileName = $fileInfo['name'];
                        
                        $campoRespTable->update(
                            ['archivo_adjunto' => $fileName],
                            [
                                'id_respuesta' => $idRespuesta,
                                'id_campo' => $idCampo
                            ]
                        );
                    }
                }
            }
            
            $connection->commit();
            $res->success('Respuesta actualizada correctamente');
            
        } catch (\Exception $ex) {
            $connection->rollback();
            $res->failure('Error al actualizar la respuesta: ' . $ex->getMessage());
        }
        
        return $res;
    }
    
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
