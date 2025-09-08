<?php

function otpGenerate($adminId, $db)
{
    // Generate 6-digit OTP
    $otp = rand(100000, 999999);

    // Expiry time (5 minutes from now)
    $expiresAt = date("Y-m-d H:i:s", strtotime("+2 minutes"));

    // Insert OTP into table
    $stmt = $db->prepare("INSERT INTO admin_otp (admin_id, otp_code, expires_at) VALUES (?, ?, ?)");
    $stmt->bind_param("iss", $adminId, $otp, $expiresAt);

    $stmt->execute();

    // Get last inserted ID
    $lastInsertedId = $db->insert_id;
    return [
        "otp" => $otp,
        "otpId" => $lastInsertedId,
    ];
}
?>