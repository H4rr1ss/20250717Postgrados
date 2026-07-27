<?php

namespace Eep\Service;

use Zend\Db\Adapter\AdapterInterface;

/**
 * Gestiona la disponibilidad de la carta de examinadores.
 *
 * Ya no se generan cartas dinámicamente ni se registran en BD.
 * La plantilla .docx se descarga directamente desde:
 *   data/graduacion/plantillas/carta-examinadores/general.docx
 *
 * Esta clase solo verifica la existencia del archivo en disco.
 */
class CartaGenerator
{
    /** @var string */
    private $rutaProyecto;

    /** Ruta relativa a la plantilla por defecto. */
    private const RUTA_PLANTILLA = 'data/graduacion/plantillas/carta-examinadores/general.docx';

    public function __construct(AdapterInterface $adapter, string $rutaProyecto)
    {
        $this->rutaProyecto = rtrim($rutaProyecto, '/');
    }

    /**
     * Verifica que la plantilla exista y devuelve la ruta relativa.
     * No interactúa con base de datos.
     */
    public function obtenerRutaPlantilla(): ?string
    {
        $ruta = $this->rutaProyecto . '/' . self::RUTA_PLANTILLA;
        return is_file($ruta) ? self::RUTA_PLANTILLA : null;
    }

    /**
     * Compatibilidad: devuelve la ruta de la carta para un proceso.
     * (anteriormente insertaba en examen_carta_examinadores).
     */
    public function generar(int $codProceso, int $codCicloAprobacion, int $codUsuarioGenera): string
    {
        $ruta = $this->obtenerRutaPlantilla();
        if ($ruta === null) {
            throw new \RuntimeException(
                'No se puede generar la carta de examinadores porque la plantilla .docx no está configurada en el sistema. ' .
                'Falta el archivo: ' . self::RUTA_PLANTILLA . '. ' .
                'Por favor contacte al departamento de IT para que configure la plantilla antes de continuar.'
            );
        }
        return $ruta;
    }
}
