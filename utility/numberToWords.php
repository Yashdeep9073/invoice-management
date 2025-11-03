<?php

function numberToWords($number)
{
    $ones = array(
        0 => 'Zero',
        1 => 'One',
        2 => 'Two',
        3 => 'Three',
        4 => 'Four',
        5 => 'Five',
        6 => 'Six',
        7 => 'Seven',
        8 => 'Eight',
        9 => 'Nine',
        10 => 'Ten',
        11 => 'Eleven',
        12 => 'Twelve',
        13 => 'Thirteen',
        14 => 'Fourteen',
        15 => 'Fifteen',
        16 => 'Sixteen',
        17 => 'Seventeen',
        18 => 'Eighteen',
        19 => 'Nineteen'
    );
    $tens = array(
        2 => 'Twenty',
        3 => 'Thirty',
        4 => 'Forty',
        5 => 'Fifty',
        6 => 'Sixty',
        7 => 'Seventy',
        8 => 'Eighty',
        9 => 'Ninety'
    );
    $units = array('Hundred', 'Thousand', 'Lakh', 'Crore');

    // Format number to two decimal places
    $number = number_format($number, 2, '.', '');
    list($integerPart, $decimalPart) = explode('.', $number);

    // Convert integer part (rupees)
    $integerWords = convertIntegerPart((int) $integerPart, $ones, $tens);

    // Convert decimal part (paise)
    $decimalWords = '';
    if ($decimalPart > 0) {
        $decimalPart = (int) $decimalPart;
        if ($decimalPart < 20) {
            $decimalWords = $ones[$decimalPart];
        } else {
            $tensVal = floor($decimalPart / 10);
            $onesVal = $decimalPart % 10;
            $decimalWords = $tens[$tensVal] . ($onesVal > 0 ? ' ' . $ones[$onesVal] : '');
        }
        $decimalWords .= ' Paise';
    }

    // Combine rupees and paise
    $result = $integerWords;
    if ($decimalWords) {
        $result .= ($result && $result !== 'Zero' ? ' and ' : '') . $decimalWords;
    }
    $result = trim($result) . ' Only';
    return $result;
}

function convertIntegerPart($number, $ones, $tens)
{
    if ($number == 0) {
        return 'Zero';
    }

    $parts = array();

    // Crores
    if ($number >= 10000000) {
        $crores = floor($number / 10000000);
        $parts[] = convertIntegerPart($crores, $ones, $tens) . ' Crore';
        $number %= 10000000;
    }
    // Lakhs
    if ($number >= 100000) {
        $lakhs = floor($number / 100000);
        $parts[] = convertIntegerPart($lakhs, $ones, $tens) . ' Lakh';
        $number %= 100000;
    }
    // Thousands
    if ($number >= 1000) {
        $thousands = floor($number / 1000);
        $parts[] = convertIntegerPart($thousands, $ones, $tens) . ' Thousand';
        $number %= 1000;
    }
    // Hundreds
    if ($number >= 100) {
        $hundreds = floor($number / 100);
        $parts[] = convertIntegerPart($hundreds, $ones, $tens) . ' Hundred';
        $number %= 100;
    }
    // Tens and Ones
    if ($number > 0) {
        if ($number < 20) {
            $parts[] = $ones[$number];
        } else {
            $tensVal = floor($number / 10);
            $onesVal = $number % 10;
            $parts[] = $tens[$tensVal] . ($onesVal > 0 ? ' ' . $ones[$onesVal] : '');
        }
    }

    return implode(' ', array_filter($parts));
}


?>