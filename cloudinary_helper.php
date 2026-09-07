<?php
/**
 * Cloudinary Helper Function for PHP
 * Uploads a local file or temporary upload buffer to Cloudinary
 * Returns secure_url on success, or null on failure.
 */
function uploadToCloudinary($filePath) {
    $cloudName = getenv('CLOUDINARY_CLOUD_NAME') ?: '';
    $apiKey = getenv('CLOUDINARY_API_KEY') ?: '';
    $apiSecret = getenv('CLOUDINARY_API_SECRET') ?: '';

    if (empty($cloudName) || empty($apiKey) || empty($apiSecret)) {
        error_log("Cloudinary Environment Variables are not configured.");
        return null;
    }

    $timestamp = time();
    $signature = sha1("timestamp={$timestamp}" . $apiSecret);

    $ch = curl_init();
    curl_setopt_array($ch, [
        CURLOPT_URL => "https://api.cloudinary.com/v1_1/{$cloudName}/image/upload",
        CURLOPT_POST => true,
        CURLOPT_POSTFIELDS => [
            'file' => new CURLFile($filePath),
            'api_key' => $apiKey,
            'timestamp' => $timestamp,
            'signature' => $signature,
            'folder' => 'food_api_uploads'
        ],
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_SSL_VERIFYPEER => false
    ]);

    $result = curl_exec($ch);
    $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
    curl_close($ch);

    if ($httpCode === 200) {
        $json = json_decode($result, true);
        return $json['secure_url'] ?? null;
    }

    error_log("Cloudinary Upload Error: Code {$httpCode} - {$result}");
    return null;
}
?>
