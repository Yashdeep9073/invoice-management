<?php
function otpGenerate($adminId, $db, $localizationSettings)
{
    // Generate 6-digit OTP
    $otp = rand(100000, 999999);

    // Extract timezone from settings
    $timezone = $localizationSettings['timezone'] ?? 'UTC';

    // Create DateTime object with specified timezone
    $dateTime = new DateTime('now', new DateTimeZone($timezone));
    $dateTime->modify('+2 minutes');
    $expiresAt = $dateTime->format('Y-m-d H:i:s');

    // Insert OTP into table
    $stmt = $db->prepare("INSERT INTO admin_otp (admin_id, otp_code, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $adminId, $otp, $expiresAt);

    if ($stmt->execute()) {
        // Get last inserted ID
        $lastInsertedId = $db->insert_id;
        return [
            "otp" => $otp,
            "otpId" => $lastInsertedId,
            "expiresAt" => $expiresAt
        ];
    } else {
        error_log("OTP Generation Error: " . $stmt->error);
        return false;
    }
}
?>