<?php

namespace Eep\Service;

use Zend\Db\Adapter\AdapterInterface;
use Zend\Db\ResultSet\ResultSet;
/**
 * Genera la carta de examinadores copiando la plantilla .docx tal cual.
 *
 * La plantilla se descarga sin modificaciones; el estudiante o el staff
 * completan los datos manualmente. Se mantiene el registro en BD para
 * controlar que el proceso ya fue aprobado y la carta está disponible.
 */
class CartaGenerator
{
    /** @var AdapterInterface */
    private $adapter;

    /** Ruta absoluta a la raíz del proyecto (sin slash final). */
    private $rutaProyecto;

    public function __construct(AdapterInterface $adapter, string $rutaProyecto)
    {
        $this->adapter      = $adapter;
        $this->rutaProyecto = rtrim($rutaProyecto, '/');
    }

    private function execute(string $sql, array $params = []): array
    {
        $statement = $this->adapter->createStatement($sql, $params);
        $result    = $statement->execute();
        $resultSet = new ResultSet(ResultSet::TYPE_ARRAY);
        $resultSet->initialize($result);
        return $resultSet->toArray();
    }

    private function exec(string $sql, array $params = []): void
    {
        $this->adapter->createStatement($sql, $params)->execute();
    }

    /**
     * Genera la carta para un proceso ya aprobado. Devuelve el cod_carta
     * insertado en examen_carta_examinadores.
     */
    public function generar(int $codProceso, int $codCicloAprobacion, int $codUsuarioGenera): int
    {
        // Si ya existe una carta para este proceso, devolverla (idempotente).
        $existente = $this->execute(
            'SELECT cod_carta FROM examen_carta_examinadores
              WHERE cod_proceso = :proceso LIMIT 1',
            ['proceso' => $codProceso]
        );
        if (!empty($existente)) {
            return (int) $existente[0]['cod_carta'];
        }

        $tipoExamen = $this->getTipoExamenDeProceso($codProceso);
        if ($tipoExamen === null) {
            throw new \RuntimeException('Proceso no encontrado al generar carta: ' . $codProceso);
        }

        $plantilla = $this->getPlantillaParaTipo((int) $tipoExamen);
        if ($plantilla === null) {
            throw new \RuntimeException('No hay plantilla registrada para el tipo de examen.');
        }

        $rutaPlantilla = $this->rutaProyecto . '/' . ltrim($plantilla['archivo_plantilla'], '/');
        if (!is_file($rutaPlantilla)) {
            throw new \RuntimeException('Plantilla .docx no encontrada en disco: ' . $rutaPlantilla);
        }

        // La plantilla se sirve directamente (sin copiar por proceso) ya que
        // no se personaliza automáticamente; el estudiante la completa manualmente.
        $rutaRelativa = ltrim($plantilla['archivo_plantilla'], '/');

        $this->exec(
            'INSERT INTO examen_carta_examinadores
                 (cod_proceso, cod_ciclo_aprobacion, cod_plantilla,
                  archivo_generado, estado, generada_por)
             VALUES (:proceso, :ciclo, :plantilla, :archivo, :estado, :usuario)',
            [
                'proceso'   => $codProceso,
                'ciclo'     => $codCicloAprobacion,
                'plantilla' => $plantilla['cod_plantilla'],
                'archivo'   => $rutaRelativa,
                'estado'    => 'generada',
                'usuario'   => $codUsuarioGenera,
            ]
        );

        return (int) $this->adapter->getDriver()->getLastGeneratedValue();
    }

    /**
     * Plantilla aplicable: específica del tipo si existe y está activa,
     * en caso contrario la genérica (cod_tipo_examen IS NULL).
     */
    private function getPlantillaParaTipo(int $codTipoExamen): ?array
    {
        $sql = 'SELECT cod_plantilla, cod_tipo_examen, nombre, archivo_plantilla
                FROM examen_carta_plantilla
                WHERE activo = 1
                  AND (cod_tipo_examen = :tipo OR cod_tipo_examen IS NULL)
                ORDER BY cod_tipo_examen IS NULL ASC
                LIMIT 1';
        $result = $this->execute($sql, ['tipo' => $codTipoExamen]);
        return $result[0] ?? null;
    }

    /**
     * Obtiene únicamente el cod_tipo_examen de un proceso.
     */
    private function getTipoExamenDeProceso(int $codProceso): ?int
    {
        $result = $this->execute(
            'SELECT cod_tipo_examen FROM examen_proceso WHERE cod_proceso = :proceso LIMIT 1',
            ['proceso' => $codProceso]
        );
        return empty($result) ? null : (int) $result[0]['cod_tipo_examen'];
    }
}
