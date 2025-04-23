<?php
// gemini_proxy.php

require 'vendor/autoload.php';

use Dotenv\Dotenv;

// Load environment variables
$dotenv = Dotenv::createImmutable(__DIR__);
$dotenv->load();

$apiKey = $_ENV['GEMINI_API_KEY'];
$apiUrl = "https://generativelanguage.googleapis.com/v1/models/gemini-2.0-flash:generateContent?key=" . $apiKey;

// Set headers
header("Access-Control-Allow-Origin: *");
header("Content-Type: application/json");

// Validate API key
if (empty($apiKey)) {
    http_response_code(500);
    echo json_encode(["error" => "API key is missing or invalid."]);
    exit;
}

// Only allow POST requests
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(["error" => "Only POST method is allowed."]);
    exit;
}

// Get JSON input
$rawInput = file_get_contents("php://input");
$input = json_decode($rawInput, true);

// Validate JSON input
if (json_last_error() !== JSON_ERROR_NONE) {
    http_response_code(400);
    echo json_encode(["error" => "Invalid JSON input."]);
    exit;
}

// Validate input
if (!isset($input['prompt']) || !is_string($input['prompt']) || trim($input['prompt']) === '') {
    http_response_code(400);
    echo json_encode(["error" => "Prompt is required and must be a non-empty string."]);
    exit;
}

$prompt = trim($input['prompt']);
$images = $input['images'] ?? [];

// Validate images
if (!is_array($images)) {
    http_response_code(400);
    echo json_encode(["error" => "Images must be an array."]);
    exit;
}

// Prepare parts
$parts = [
    ["text" => $prompt]
];

// Add images if provided
foreach ($images as $image) {
    if (isset($image['mimeType'], $image['data']) && is_string($image['mimeType']) && is_string($image['data'])) {
        $parts[] = [
            "inlineData" => [
                "mimeType" => $image['mimeType'],
                "data" => $image['data']
            ]
        ];
    } else {
        http_response_code(400);
        echo json_encode(["error" => "Each image must have valid 'mimeType' and 'data'."]);
        exit;
    }
}

$payload = [
    "contents" => [
        [
            "parts" => $parts
        ]
    ]
];

// Initialize cURL
$ch = curl_init($apiUrl);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, json_encode($payload));
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json'
]);
curl_setopt($ch, CURLOPT_TIMEOUT, 30); // Set timeout to 30 seconds

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Log request and response
$sanitizedPayload = $payload;
if (isset($sanitizedPayload['contents'][0]['parts'][0]['text'])) {
    $sanitizedPayload['contents'][0]['parts'][0]['text'] = '[REDACTED]';
}

$logData = [
    date('Y-m-d H:i:s'),
    $_SERVER['REMOTE_ADDR'] ?? 'CLI',
    $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
    $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN',
    '[REDACTED]', // Do not log the actual prompt
    json_encode($images),
    $httpCode,
    $response
];

$logFile = fopen('gemini_log.csv', 'a');
fputcsv($logFile, $logData);
fclose($logFile);

// Handle response
if ($error) {
    http_response_code(500);
    echo json_encode(["error" => "cURL Error: " . $error]);
    exit;
}

if ($httpCode !== 200) {
    http_response_code($httpCode);
    echo $response;
    exit;
}

echo $response;