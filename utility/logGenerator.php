<?php


require_once __DIR__ . '/../vendor/autoload.php';
require_once __DIR__ . '/env.php';

use ipinfo\ipinfo\IPinfo;



function detectRequestType()
{
    $userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $xhr = $_SERVER['HTTP_X_REQUESTED_WITH'] ?? '';
    $contentType = $_SERVER['CONTENT_TYPE'] ?? '';

    $ipAddress = $_SERVER['REMOTE_ADDR'] ?? '0.0.0.0';

    // Get browser, version, platform, and mobile status
    $browserInfo = detectBrowserInfo($userAgent);

    // Get geolocation information
    $geoInfo = detectGeoInfo($ipAddress);

    // Check for AJAX/API requests
    if (!empty($xhr) && strtolower($xhr) === 'xmlhttprequest') {
        return [
            'type' => 'ajax',
            'browser' => $browserInfo,
            'geo' => $geoInfo
        ];
    }

    if (strpos($contentType, 'application/json') !== false) {
        return [
            'type' => 'api',
            'browser' => $browserInfo,
            'geo' => $geoInfo
        ];
    }

    // Check for browser characteristics
    if (strpos($accept, 'text/html') !== false && !empty($userAgent)) {
        $browserPatterns = [
            '/Chrome/i',
            '/Firefox/i',
            '/Safari/i',
            '/Edge/i',
            '/Opera/i',
            '/MSIE/i',
            '/Trident/i'
        ];

        foreach ($browserPatterns as $pattern) {
            if (preg_match($pattern, $userAgent)) {
                return [
                    'type' => 'browser',
                    'browser' => $browserInfo
                ];
            }
        }
    }

    return [
        'type' => 'unknown',
        'browser' => $browserInfo,
        'geo' => $geoInfo
    ];
}

function detectBrowserInfo($userAgent)
{
    if (empty($userAgent)) {
        return [
            'name' => 'Unknown',
            'version' => 'Unknown',
            'platform' => 'Unknown',
            'is_mobile' => 0
        ];
    }

    // Define browser patterns
    $browsers = [
        'Chrome' => '/Chrome\/([0-9.]+)/',
        'Firefox' => '/Firefox\/([0-9.]+)/',
        'Safari' => '/Version\/([0-9.]+).*Safari/',
        'Edge' => '/Edge\/([0-9.]+)/',
        'Opera' => '/Opera\/([0-9.]+)/',
        'Internet Explorer' => '/(?:MSIE|Trident).*?([0-9.]+)/'
    ];

    // Detect browser and version
    $browserName = 'Unknown';
    $browserVersion = 'Unknown';
    foreach ($browsers as $browser => $pattern) {
        if (preg_match($pattern, $userAgent, $matches)) {
            $browserName = $browser;
            $browserVersion = $matches[1] ?? 'Unknown';
            break;
        }
    }

    // Fallback detection
    if ($browserName === 'Unknown') {
        if (strpos($userAgent, 'Chrome') !== false) {
            $browserName = 'Chrome';
        } elseif (strpos($userAgent, 'Firefox') !== false) {
            $browserName = 'Firefox';
        } elseif (strpos($userAgent, 'Safari') !== false) {
            $browserName = 'Safari';
        } elseif (strpos($userAgent, 'Edge') !== false) {
            $browserName = 'Edge';
        } elseif (strpos($userAgent, 'Opera') !== false) {
            $browserName = 'Opera';
        } elseif (strpos($userAgent, 'MSIE') !== false || strpos($userAgent, 'Trident') !== false) {
            $browserName = 'Internet Explorer';
        }
    }

    // Detect platform
    $platform = 'Unknown';
    if (preg_match('/Windows/i', $userAgent)) {
        $platform = 'Windows';
    } elseif (preg_match('/Macintosh|Mac OS X/i', $userAgent)) {
        $platform = 'macOS';
    } elseif (preg_match('/Linux/i', $userAgent)) {
        $platform = 'Linux';
    } elseif (preg_match('/Android/i', $userAgent)) {
        $platform = 'Android';
    } elseif (preg_match('/iPhone|iPad|iPod/i', $userAgent)) {
        $platform = 'iOS';
    }

    // Detect mobile device
    $isMobile = preg_match('/Mobile|Android|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i', $userAgent) ? 1 : 0;

    return [
        'name' => $browserName,
        'version' => $browserVersion,
        'platform' => $platform,
        'is_mobile' => $isMobile
    ];
}


function detectGeoInfo($ipAddress)
{
    if ($ipAddress === '0.0.0.0' || $ipAddress === '127.0.0.1' || $ipAddress === '::1') {
        return [
            'country' => 'Unknown',
            'state' => 'Unknown',
            'city' => 'Unknown'
        ];
    }

    // If you have a free token from ipinfo.io (recommended)
    $accessToken = getenv("IP_TOKEN"); // optional
    $client = new IPinfo($accessToken);

    try {
        $details = $client->getDetails($ipAddress);

        return [
            'country' => $details->country ?? 'Unknown',
            'state' => $details->region ?? 'Unknown',
            'city' => $details->city ?? 'Unknown'
        ];
    } catch (Exception $e) {
        return [
            'country' => 'Unknown',
            'state' => 'Unknown',
            'city' => 'Unknown'
        ];
    }
}


// Usage
// $requestInfo = detectRequestType();
// $requestType = $requestInfo['type'];
// $browserName = $requestInfo['browser'];