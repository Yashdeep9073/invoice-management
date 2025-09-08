<?php
function formatDateTime($dateTimeValue, $localizationSettings)
{
    try {
        // Extract values from settings
        $timezone = $localizationSettings['timezone'] ?? 'UTC';
        $dateFormat = $localizationSettings['date_format'] ?? 'd M Y';
        $timeFormat = $localizationSettings['time_format'] ?? '12';

        // If dateFormat already has time, don’t append
        $format = $dateFormat;
        if (strpos($dateFormat, 'H') === false && strpos($dateFormat, 'h') === false) {
            $format .= ' ' . ($timeFormat == '12' ? 'h:i A' : 'H:i');
        }

        // Assume DB stores UTC datetime
        $date = new DateTime($dateTimeValue, new DateTimeZone('UTC'));
        $date->setTimezone(new DateTimeZone($timezone));

        return $date->format($format);
    } catch (Exception $e) {
        return $dateTimeValue;
    }
}
