<?php
$username = 'admin';
$password = "Vis@#2025";

// Generate a bcrypt hash (more modern)
$hash = password_hash($password, PASSWORD_BCRYPT);

echo "Add this to your .htpasswd file:\n";
echo $username . ':' . $hash . "\n";
?>