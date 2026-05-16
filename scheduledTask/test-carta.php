<?php
/**
 * Prueba REAL de generación de carta de examinadores.
 *
 * - Usa CartaGenerator (la misma clase del flujo del paso 5).
 * - Lee la plantilla registrada en examen_carta_plantilla.
 * - Toma datos reales del proceso indicado (estudiante, terna, fechas).
 * - Inserta una fila en examen_carta_examinadores y guarda el .docx
 *   en public/archivos/cartas-examinadores/proceso-{N}.docx
 *
 * Variables (env):
 *   COD_PROCESO    cod_proceso a usar (default: 3)
 *   COD_CICLO      cod_ciclo asociado a la aprobación (default: el último del proceso)
 *   COD_USUARIO    cod_usuario que figura como generador (default: 1)
 *
 * Uso:
 *   docker exec 20250717postgrados-web-1 php /var/www/scheduledTask/test-carta.php
 */

chdir(dirname(__DIR__));
require __DIR__ . '/../vendor/autoload.php';

// Bootstrap mínimo de ZF para reusar el adapter y la factory del CartaGenerator
$appConfig = require __DIR__ . '/../config/application.config.php';
$container = \Zend\Mvc\Application::init($appConfig)->getServiceManager();

$cartaGenerator = $container->get(\Eep\Service\CartaGenerator::class);
$adapter        = $container->get(\Zend\Db\Adapter\AdapterInterface::class);

$codProceso = (int) (getenv('COD_PROCESO') ?: 3);
$codUsuario = (int) (getenv('COD_USUARIO') ?: 1);

// Detectar ciclo si no se especifica
$envCiclo = getenv('COD_CICLO');
if ($envCiclo !== false && $envCiclo !== '') {
    $codCiclo = (int) $envCiclo;
} else {
    $stmt = $adapter->createStatement(
        'SELECT cod_ciclo FROM examen_correccion_ciclo WHERE cod_proceso = :p ORDER BY numero_ciclo DESC LIMIT 1',
        ['p' => $codProceso]
    );
    $row = $stmt->execute()->current();
    $codCiclo = (int) ($row['cod_ciclo'] ?? 0);
}

if (!$codCiclo) {
    fwrite(STDERR, "ERROR: no se encontró ciclo para el proceso $codProceso.\n");
    exit(1);
}

echo "Generando carta para proceso=$codProceso, ciclo=$codCiclo, usuario_generador=$codUsuario\n";

try {
    $codCarta = $cartaGenerator->generar($codProceso, $codCiclo, $codUsuario);
} catch (\Throwable $e) {
    fwrite(STDERR, "FALLO: " . $e->getMessage() . "\n");
    fwrite(STDERR, $e->getTraceAsString() . "\n");
    exit(2);
}

echo "OK. cod_carta = $codCarta\n";

$stmt = $adapter->createStatement(
    'SELECT cod_carta, archivo_generado, estado, fecha_generacion FROM examen_carta_examinadores WHERE cod_carta = :c',
    ['c' => $codCarta]
);
$row = $stmt->execute()->current();
print_r($row);

echo "\nAbre el archivo:\n";
echo "  " . realpath(__DIR__ . '/..') . '/' . $row['archivo_generado'] . "\n";
