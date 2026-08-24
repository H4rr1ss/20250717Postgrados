<?php

namespace Eep\Service;

class EvaluacionDocenteGraficaService
{
    private $tempDir;
    private $fontPath;

    public function __construct(string $tempDir = '/var/www/data/graficas')
    {
        $this->tempDir = rtrim($tempDir, '/');
        $this->fontPath = '/var/www/data/fonts/DejaVuSans.ttf';
        $this->ensureDir();
    }

    public function generarGraficaEscala10(array $distribucion, $promedio): string
    {
        $width = 640;
        $height = 360;

        $img = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $white);

        $marginLeft = 60;
        $marginRight = 20;
        $marginTop = 30;
        $marginBottom = 65;

        $plotX = $marginLeft;
        $plotY = $marginTop;
        $plotW = $width - $marginLeft - $marginRight;
        $plotH = $height - $marginTop - $marginBottom;

        $maxVal = 0;
        for ($i = 1; $i <= 10; $i++) {
            $v = (int) ($distribucion[$i] ?? 0);
            if ($v > $maxVal) {
                $maxVal = $v;
            }
        }
        if ($maxVal === 0) {
            $maxVal = 1;
        }

        $scaleY = $plotH / $maxVal;

        $gray = imagecolorallocate($img, 220, 220, 220);
        $black = imagecolorallocate($img, 51, 51, 51);
        $barColor = imagecolorallocate($img, 91, 192, 222);
        $borderColor = imagecolorallocate($img, 70, 184, 218);

        // Grid horizontales
        $gridLines = 5;
        for ($g = 0; $g <= $gridLines; $g++) {
            $val = ($maxVal / $gridLines) * $g;
            $y = $plotY + $plotH - ($val * $scaleY);
            imageline($img, $plotX, (int) $y, $plotX + $plotW, (int) $y, $gray);
            $label = (string) round($val, 1);
            $this->drawTtfText($img, 8, $plotX - 25, (int) $y - 4, $label, $black);
        }

        // Ejes
        imageline($img, $plotX, $plotY + $plotH, $plotX + $plotW, $plotY + $plotH, $black);
        imageline($img, $plotX, $plotY, $plotX, $plotY + $plotH, $black);

        // Barras
        $barCount = 10;
        $slotW = $plotW / $barCount;
        $barW = $slotW * 0.6;

        for ($i = 1; $i <= 10; $i++) {
            $val = (int) ($distribucion[$i] ?? 0);
            $barH = $val * $scaleY;
            $x1 = $plotX + ($slotW * ($i - 1)) + (($slotW - $barW) / 2);
            $y1 = $plotY + $plotH - $barH;
            $x2 = $x1 + $barW;
            $y2 = $plotY + $plotH;

            if ($val > 0) {
                imagefilledrectangle($img, (int) $x1, (int) $y1, (int) $x2, (int) $y2, $barColor);
                imagerectangle($img, (int) $x1, (int) $y1, (int) $x2, (int) $y2, $borderColor);

                $txt = (string) $val;
                $txtW = $this->ttfTextWidth(9, $txt);
                $tx = (int) ($x1 + ($barW - $txtW) / 2);
                $ty = (int) ($y1 - 14);
                $this->drawTtfText($img, 9, $tx, $ty, $txt, $black);
            }

            $txt = (string) $i;
            $txtW = $this->ttfTextWidth(9, $txt);
            $tx = (int) ($x1 + ($barW - $txtW) / 2);
            $ty = $plotY + $plotH + 8;
            $this->drawTtfText($img, 9, $tx, $ty, $txt, $black);
        }

        // Etiqueta eje X
        $xLabel = 'Calificación (1 = Deficiente, 10 = Excelente)';
        $xLabelW = $this->ttfTextWidth(10, $xLabel);
        $xLabelX = (int) ($plotX + ($plotW - $xLabelW) / 2);
        $xLabelY = $plotY + $plotH + 40;
        $this->drawTtfText($img, 10, $xLabelX, $xLabelY, $xLabel, $black);

        // Etiqueta eje Y (rotada 90°)
        $yLabel = 'Cantidad de respuestas';
        $yLabelX = 16;
        $yLabelY = (int) ($plotY + $plotH / 2 + $this->ttfTextWidth(10, $yLabel) / 2);
        if (file_exists($this->fontPath)) {
            imagettftext($img, 10, 90, $yLabelX, $yLabelY, $black, $this->fontPath, $yLabel);
        } else {
            $this->drawTtfText($img, 9, $yLabelX, $yLabelY - 40, $yLabel, $black);
        }

        $filename = 'grafica_escala10_' . uniqid() . '.png';
        $path = $this->tempDir . '/' . $filename;
        imagepng($img, $path);
        imagedestroy($img);

        return $path;
    }

    public function generarGraficaBoolean(int $si, int $no, int $total): string
    {
        $width = 640;
        $height = 280;

        $img = imagecreatetruecolor($width, $height);
        $white = imagecolorallocate($img, 255, 255, 255);
        imagefill($img, 0, 0, $white);

        $cx = 200;
        $cy = 140;
        $radius = 110;
        $d = $radius * 2;

        $siColor = imagecolorallocate($img, 92, 184, 92);
        $noColor = imagecolorallocate($img, 217, 83, 79);
        $black = imagecolorallocate($img, 51, 51, 51);
        $gray = imagecolorallocate($img, 200, 200, 200);

        $startAngle = 270;

        if ($total > 0) {
            $angleSi = ($si / $total) * 360;
            $angleNo = ($no / $total) * 360;

            if ($si > 0) {
                $endAngle = $startAngle + $angleSi;
                imagefilledarc($img, $cx, $cy, $d, $d, (int) $startAngle, (int) $endAngle, $siColor, IMG_ARC_PIE);
                $startAngle = $endAngle;
            }
            if ($no > 0) {
                $endAngle = $startAngle + $angleNo;
                imagefilledarc($img, $cx, $cy, $d, $d, (int) $startAngle, (int) $endAngle, $noColor, IMG_ARC_PIE);
            }

            // Borde del pastel
            imagearc($img, $cx, $cy, $d, $d, 0, 360, $black);
        } else {
            // Sin datos: círculo gris vacío
            imagefilledarc($img, $cx, $cy, $d, $d, 0, 360, $gray, IMG_ARC_PIE);
            imagearc($img, $cx, $cy, $d, $d, 0, 360, $black);
            $this->drawTtfText($img, 11, $cx - 35, $cy - 4, 'Sin datos', $black);
        }

        // Leyenda a la derecha
        $legendX = 360;
        $legendY = 90;
        $boxSize = 18;
        $lineHeight = 36;

        // Sí
        imagefilledrectangle($img, $legendX, $legendY, $legendX + $boxSize, $legendY + $boxSize, $siColor);
        imagerectangle($img, $legendX, $legendY, $legendX + $boxSize, $legendY + $boxSize, $black);

        $pctSi = $total > 0 ? round(($si / $total) * 100, 1) : 0;
        $textSi = "Sí: {$si} ({$pctSi}%)";
        $this->drawTtfText($img, 11, $legendX + $boxSize + 10, $legendY + 2, $textSi, $black);

        // No
        $legendY += $lineHeight;
        imagefilledrectangle($img, $legendX, $legendY, $legendX + $boxSize, $legendY + $boxSize, $noColor);
        imagerectangle($img, $legendX, $legendY, $legendX + $boxSize, $legendY + $boxSize, $black);

        $pctNo = $total > 0 ? round(($no / $total) * 100, 1) : 0;
        $textNo = "No: {$no} ({$pctNo}%)";
        $this->drawTtfText($img, 11, $legendX + $boxSize + 10, $legendY + 2, $textNo, $black);

        $filename = 'grafica_boolean_' . uniqid() . '.png';
        $path = $this->tempDir . '/' . $filename;
        imagepng($img, $path);
        imagedestroy($img);

        return $path;
    }

    public function limpiarGraficas(array $paths): void
    {
        foreach ($paths as $path) {
            if (file_exists($path) && is_writable($path)) {
                unlink($path);
            }
        }
    }

    private function drawTtfText($img, int $size, int $x, int $y, string $text, int $color): void
    {
        if (!file_exists($this->fontPath)) {
            imagestring($img, 2, $x, $y, $text, $color);
            return;
        }
        imagettftext($img, $size, 0, $x, $y + $size, $color, $this->fontPath, $text);
    }

    private function ttfTextWidth(int $size, string $text): int
    {
        if (!file_exists($this->fontPath)) {
            return imagefontwidth(2) * strlen($text);
        }
        $box = imagettfbbox($size, 0, $this->fontPath, $text);
        return abs($box[4] - $box[0]);
    }

    private function ensureDir(): void
    {
        if (!is_dir($this->tempDir)) {
            mkdir($this->tempDir, 0755, true);
        }
    }
}
