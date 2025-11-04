<?php

// Function to encode/decode file IDs for security
function encodeFileId($id)
{
    return base64_encode($id . '_' . time());
}

function decodeFileId($encoded)
{
    $decoded = base64_decode($encoded);
    $parts = explode('_', $decoded);
    return $parts[0] ?? 0;
}

?>