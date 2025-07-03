<?php
require_once 'vendor/tecnickcom/tcpdf/tcpdf.php';
require_once 'vendor/tecnickcom/tcpdf/include/tcpdf_fonts.php'; // ✅ Add this line


// Convert Montserrat Regular
addTTFfont(
    'C:/laragon/www/smart-sheet/assets/fonts/Montserrat/Montserrat-VariableFont_wght.ttf',
    'TrueTypeUnicode',
    '',
    32
);

// Convert Montserrat Italic
addTTFfont(
    'C:/laragon/www/smart-sheet/assets/fonts/Montserrat/Montserrat-Italic-VariableFont_wght.ttf',
    'TrueTypeUnicode',
    '',
    32
);

echo "Fonts converted successfully!";
