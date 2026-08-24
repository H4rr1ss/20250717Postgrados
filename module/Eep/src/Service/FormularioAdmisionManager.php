<?php

namespace Eep\Service;

use Zend\Db\TableGateway\TableGateway;
use Zend\Db\Sql\Select;
use Zend\Db\Sql\Expression;
use Eep\Entity\Result as R;
use Eep\Entity\FormularioAdmision;
use Eep\Entity\RespuestaAspirante;
use Eep\Entity\CampoFormulario;

class FormularioAdmisionManager extends Manager {

    const RUTA_ARCHIVOS = './data/admisiones';
    const TAMANO_MAXIMO = 5 * 1024 * 1024; // 5MB
    const TIPOS_PERMITIDOS = ['image/jpeg', 'image/png', 'image/gif', 'application/pdf'];

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
     * Elimina un formulario y sus datos relacionados (incluyendo archivos físicos)
     */
    public function eliminarFormulario($idFormulario) {
        $res = new R();
        try {
            // Eliminar archivos físicos de todas las respuestas antes de borrar registros
            $respTable = new TableGateway('respuesta_aspirante', $this->dbAdapter);
            $respuestas = $respTable->select(['id_formulario' => $idFormulario]);
            foreach ($respuestas as $respuesta) {
                $this->eliminarCarpetaRespuesta($idFormulario, $respuesta['id_respuesta']);
            }

            // Eliminar respuestas asociadas (cascada en BD también)
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
     * Cuenta el total de respuestas de un formulario
     */
    public function countRespuestasFormulario($idFormulario) {
        $res = new R();
        try {
            $table = new TableGateway('respuesta_aspirante', $this->dbAdapter);
            $count = $table->select(['id_formulario' => $idFormulario])->count();
            $res->success();
            $res->setObj((int)$count);
        } catch (\Exception $ex) {
            $res->failure('Error al contar respuestas: ' . $ex->getMessage());
        }
        return $res;
    }

    /**
     * Obtiene respuestas paginadas de un formulario específico
     */
    public function getRespuestasFormulario($idFormulario, $page = 1, $perPage = 20) {
        $res = new R();
        try {
            $offset = ($page - 1) * $perPage;

            // Obtener respuestas paginadas del formulario
            $respTable = new TableGateway('respuesta_aspirante', $this->dbAdapter);
            $respuestas = $respTable->select(function (Select $select) use ($idFormulario, $offset, $perPage) {
                $select->where(['id_formulario' => $idFormulario]);
                $select->order('fecha_envio DESC');
                $select->limit($perPage);
                $select->offset($offset);
            });

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
            // Obtener respuestas por campo con JOIN para metadatos
            $camposTable = new TableGateway(['rc' => 'respuesta_campo'], $this->dbAdapter);
            $select = $camposTable->getSql()->select();

            // Seleccionar columnas de respuesta_campo y metadatos de campo_formulario
            $select->columns(['id_respuesta', 'id_campo', 'valor_respuesta', 'archivo_adjunto']);
            $select->join(['cf' => 'campo_formulario'], 'rc.id_campo = cf.id_campo',
                         ['nombre_campo', 'etiqueta', 'tipo_campo', 'opciones', 'requerido', 'orden_campo', 'seccion']);

            $select->where(['rc.id_respuesta' => $idRespuesta]);
            $select->order('cf.orden_campo ASC');

            $resultSet = $camposTable->selectWith($select);
            // Forzar arrays planos: algunos drivers devuelven ArrayObject en lugar de array
            $respuestasCampos = [];
            foreach ($resultSet as $row) {
                if (is_array($row)) {
                    $respuestasCampos[] = $row;
                } elseif ($row instanceof \ArrayObject) {
                    $respuestasCampos[] = $row->getArrayCopy();
                } elseif (is_object($row)) {
                    $respuestasCampos[] = get_object_vars($row);
                } else {
                    $respuestasCampos[] = (array) $row;
                }
            }

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
     * Actualiza las respuestas de un formulario (solo campos de texto, no archivos)
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

            // NOTA: Los archivos NO son editables. Se ignoran en actualizaciones.
            // El admin no puede reemplazar documentos ya adjuntos.

            $connection->commit();
            $res->success('Respuesta actualizada correctamente');

        } catch (\Exception $ex) {
            $connection->rollback();
            $res->failure('Error al actualizar la respuesta: ' . $ex->getMessage());
        }

        return $res;
    }

    /**
     * Elimina una respuesta completa y sus archivos físicos
     */
    public function eliminarRespuesta($idRespuesta) {
        $res = new R();
        try {
            // Obtener id_formulario antes de eliminar para borrar archivos
            $respTable = new TableGateway('respuesta_aspirante', $this->dbAdapter);
            $respData = $respTable->select(['id_respuesta' => $idRespuesta])->current();
            if ($respData) {
                $this->eliminarCarpetaRespuesta($respData['id_formulario'], $idRespuesta);
            }

            $deleted = $respTable->delete(['id_respuesta' => $idRespuesta]);

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
     * Obtiene los campos globales predefinidos del formulario de admisión
     */
    public function getCamposFormulario($idFormulario = null) {
        $res = new R();
        try {
            $table = new TableGateway('campo_formulario', $this->dbAdapter);
            $result = $table->select();

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

            $res->success('Formulario creado correctamente. ID: ' . $nuevoId);
            $res->setObj($nuevoId);

        } catch (\Exception $ex) {
            $res->failure('Error al crear el formulario: ' . $ex->getMessage());
        }

        return $res;
    }

    /**
     * Registra la respuesta pública: verifica CUI, crea respuesta_aspirante y respuesta_campo
     */
    public function registrarRespuestaPublica($idFormulario, array $campos, array $data, array $files)
    {
        $res = new R();
        $connection = $this->dbAdapter->getDriver()->getConnection();
        $connection->beginTransaction();
        try {
            // Verificar si el CUI ya existe en este formulario
            $duplicado = $this->verificarCuiDuplicado($data['cui'] ?? null, $idFormulario);
            if ($duplicado) {
                $connection->rollback();
                $res->failure('Ya registró una respuesta. Si desea volver a enviar, comuníquese con el administrador.');
                return $res;
            }

            // Insertar registro de respuesta
            $respTable = new TableGateway('respuesta_aspirante', $this->dbAdapter);
            $respTable->insert([
                'id_formulario' => $idFormulario,
            ]);
            $respuestaId = $this->dbAdapter->getDriver()->getLastGeneratedValue();

            // Insertar cada campo de respuesta
            $campoRespTable = new TableGateway('respuesta_campo', $this->dbAdapter);
            foreach ($campos as $campo) {
                $nombre = $campo->getNombreCampo();
                if ($campo->getTipoCampo() === 'archivo') {
                    $valorArchivo = null;
                    if (isset($files[$nombre]) && $files[$nombre]['error'] === UPLOAD_ERR_OK) {
                        // Campo específico: adjunto_titulos solo permite PDF y hasta 10MB
                        if ($nombre === 'adjunto_titulos') {
                            $valorArchivo = $this->guardarArchivoSeguro($files[$nombre], $idFormulario, $respuestaId, ['application/pdf'], 10 * 1024 * 1024);
                        } else {
                            $valorArchivo = $this->guardarArchivoSeguro($files[$nombre], $idFormulario, $respuestaId);
                        }
                    }
                    $campoRespTable->insert([
                        'id_respuesta' => $respuestaId,
                        'id_campo' => $campo->getIdCampo(),
                        'archivo_adjunto' => $valorArchivo,
                    ]);
                } else {
                    $valor = $data[$nombre] ?? null;
                    // Si es multicheckbox, el valor viene como array; convertir a string separado por comas
                    if ($campo->getTipoCampo() === 'multicheckbox' && is_array($valor)) {
                        $valor = implode(',', $valor);
                    }
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
            $res->failure('Error al registrar respuesta: ' . $ex->getMessage());
        }
        return $res;
    }

    /**
     * Verifica si un CUI ya tiene respuesta en un formulario
     */
    public function verificarCuiDuplicado($cui, $idFormulario) {
        if (empty($cui)) {
            return false;
        }
        try {
            $camposTable = new TableGateway(['rc' => 'respuesta_campo'], $this->dbAdapter);
            $select = $camposTable->getSql()->select();
            $select->join(['cf' => 'campo_formulario'], 'rc.id_campo = cf.id_campo', []);
            $select->join(['ra' => 'respuesta_aspirante'], 'rc.id_respuesta = ra.id_respuesta', []);
            $select->where([
                'cf.nombre_campo' => 'cui',
                'rc.valor_respuesta' => $cui,
                'ra.id_formulario' => $idFormulario,
            ]);
            $result = $camposTable->selectWith($select);
            return $result->count() > 0;
        } catch (\Exception $ex) {
            return false;
        }
    }

    /**
     * Guarda un archivo de forma segura con nombre MD5
     * @param array $fileInfo Array de $_FILES
     * @param int $idFormulario
     * @param int $idRespuesta
     * @param array|null $tiposPermitidos Lista de MIME types permitidos (null = usar constante por defecto)
     * @param int|null $tamanoMaximo Tamaño máximo en bytes (null = usar constante por defecto)
     */
    private function guardarArchivoSeguro($fileInfo, $idFormulario, $idRespuesta, $tiposPermitidos = null, $tamanoMaximo = null) {
        $tmpName = $fileInfo['tmp_name'];
        $originalName = $fileInfo['name'];
        $mimeType = mime_content_type($tmpName);

        $tipos = $tiposPermitidos ?? self::TIPOS_PERMITIDOS;
        $maxSize = $tamanoMaximo ?? self::TAMANO_MAXIMO;

        if (!in_array($mimeType, $tipos)) {
            throw new \Exception('Tipo de archivo no permitido: ' . $mimeType);
        }
        if ($fileInfo['size'] > $maxSize) {
            $maxMb = $maxSize / 1024 / 1024;
            throw new \Exception('El archivo excede el tamaño máximo permitido de ' . $maxMb . 'MB');
        }

        $ext = pathinfo($originalName, PATHINFO_EXTENSION);
        if (empty($ext)) {
            $ext = 'bin';
        }
        $fileName = md5(uniqid('', true)) . '.' . strtolower($ext);

        $dir = self::RUTA_ARCHIVOS . '/formularios/' . (int)$idFormulario . '/' . (int)$idRespuesta;
        if (!is_dir($dir)) {
            mkdir($dir, 0755, true);
        }

        $destPath = $dir . '/' . $fileName;
        if (!move_uploaded_file($tmpName, $destPath)) {
            throw new \Exception('No se pudo guardar el archivo adjunto');
        }

        return 'formularios/' . (int)$idFormulario . '/' . (int)$idRespuesta . '/' . $fileName;
    }

    /**
     * Elimina la carpeta de archivos de una respuesta
     */
    private function eliminarCarpetaRespuesta($idFormulario, $idRespuesta) {
        $dir = self::RUTA_ARCHIVOS . '/formularios/' . (int)$idFormulario . '/' . (int)$idRespuesta;
        if (!is_dir($dir)) {
            return;
        }
        // Eliminar archivos dentro de la carpeta
        $files = glob($dir . '/*');
        foreach ($files as $file) {
            if (is_file($file)) {
                unlink($file);
            }
        }
        // Eliminar carpeta vacía
        rmdir($dir);
    }

    /**
     * Obtiene la ruta física completa de un archivo relativo.
     * Compatible con registros antiguos que guardaban 'admisiones/formularios/...'
     * y registros nuevos que guardan 'formularios/...'.
     */
    public function getRutaArchivoFisico($rutaRelativa) {
        $rutaRelativa = ltrim($rutaRelativa, '/');
        // Normalizar: quitar prefijo 'admisiones/' si existe (registros antiguos)
        if (strpos($rutaRelativa, 'admisiones/') === 0) {
            $rutaRelativa = substr($rutaRelativa, strlen('admisiones/'));
        }
        $ruta = self::RUTA_ARCHIVOS . '/' . $rutaRelativa;
        if (file_exists($ruta) && is_file($ruta)) {
            return $ruta;
        }
        return null;
    }

    /**
     * Obtiene información del archivo adjunto de un campo específico de una respuesta
     */
    public function obtenerArchivoAdjunto($idRespuesta, $nombreCampo) {
        try {
            $camposTable = new TableGateway(['rc' => 'respuesta_campo'], $this->dbAdapter);
            $select = $camposTable->getSql()->select();
            $select->join(['cf' => 'campo_formulario'], 'rc.id_campo = cf.id_campo', []);
            $select->where([
                'rc.id_respuesta' => $idRespuesta,
                'cf.nombre_campo' => $nombreCampo,
            ]);
            $result = $camposTable->selectWith($select)->current();

            if (!$result || empty($result['archivo_adjunto'])) {
                return null;
            }

            $rutaFisica = $this->getRutaArchivoFisico($result['archivo_adjunto']);
            if (!$rutaFisica || !file_exists($rutaFisica)) {
                return null;
            }

            return [
                'ruta_relativa' => $result['archivo_adjunto'],
                'ruta_fisica' => $rutaFisica,
                'mime_type' => mime_content_type($rutaFisica),
                'nombre' => basename($rutaFisica),
                'tamano' => filesize($rutaFisica),
            ];
        } catch (\Exception $ex) {
            return null;
        }
    }

    /**
     * Verifica si ya existe un usuario registrado con el CUI o correo proporcionado.
     * Usado en la lista de respuestas para indicar visualmente que el aspirante ya está en el sistema.
     */
    public function verificarUsuarioRegistrado($cui, $correo) {
        $res = new R();
        try {
            $table = new TableGateway('usuario', $this->dbAdapter);
            $select = $table->getSql()->select();

            $predicates = [];
            if (!empty($cui)) {
                $predicates[] = new \Zend\Db\Sql\Predicate\Operator('cui', '=', $cui);
            }
            if (!empty($correo)) {
                $predicates[] = new \Zend\Db\Sql\Predicate\Operator('correo', '=', $correo);
            }
            if (empty($predicates)) {
                $res->failure('Sin identificadores');
                return $res;
            }

            $select->where(new \Zend\Db\Sql\Predicate\PredicateSet($predicates, \Zend\Db\Sql\Predicate\PredicateSet::COMBINED_BY_OR));
            $result = $table->selectWith($select);
            if ($result->count() > 0) {
                $res->success();
            } else {
                $res->failure('No encontrado');
            }
        } catch (\Exception $ex) {
            $res->failure('Error: ' . $ex->getMessage());
        }
        return $res;
    }
}
