<?php
require 'vendor/autoload.php';

use Dompdf\Dompdf;
use Dompdf\Options;

// Set paths
$fontFile = 'futuramdbt_bold.otf'; // Now in dompdf's font dir
$fontCache = __DIR__ . '/storage/fonts/';

// Ensure cache directory exists
if (!file_exists($fontCache)) {
    mkdir($fontCache, 0755, true);
}

// Configure dompdf
$options = new Options();
$options->set('fontDir', __DIR__ . '/vendor/dompdf/dompdf/lib/fonts/');
$options->set('fontCache', $fontCache);
$dompdf = new Dompdf($options);

// Register the font
$fontMetrics = $dompdf->getFontMetrics();
$fontMetrics->registerFont(
    ['family' => 'futura', 'weight' => 'bold', 'style' => 'normal'],
    $fontFile
);

// Force cache update
$fontMetrics->saveFontFamilies();

echo "Futura font successfully registered and cached!";