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

// Extract configuration parameters with defaults
$sectionType = $input['sectionType'] ?? 'banner';
$pageType = $input['pageType'] ?? 'product detail page';
$language = $input['language'] ?? 'English';
$tone = $input['tone'] ?? 'clear, persuasive, and clean';
$userRequest = trim($input['prompt']);

$masterPrompt = "You are an AI assistant embedded inside a KartmaX DIY Page Builder (a visual website builder tool for KartmaX eCommerce platform) used by marketers and designers to create high-performance eCommerce websites. 

Your job is to help generate a single responsive page section that fits seamlessly into an existing layout. You are not creating a full page — only a self-contained block to be inserted where the user has dropped this widget. 

Critial Details about the div that you have to provide the code for:
 - This is a(n) \"{$sectionType}\" section on a \"{$pageType}\"
 - Write content copy in \"{$language}\", with a \"{$tone}\" tone.
 
 Instructions:
 - Build for modern browsers and optimized for mobile-first layout
 - Style using semantic, performant HTML and utility-based CSS (no JavaScript unless absolutely necessary)
 - Use placeholder text where dynamic content (e.g., product names or prices) is expected — DO NOT inject dynamic logic 
 - Generate valid, accessible HTML with responsive layout
 - Do NOT include <html>, <head>, <section> or global layout containers — only the div block
 - Avoid interactive elements that require JavaScript unless explicitly requested
 - Optimize markup for Core Web Vitals: minimal nesting, small DOM footprint, mobile-first layout
 - IMPORTANT: Assume no pre-exiting UI framework or styling (bootstrap tailwind, etc.).
 - IMPORTANT: Write your on styling css code in it's own <style> tag covering both desktop & mobile responsiveness.
 - IMPORTANT: Add a 4 digit random number to any css class that you make so that it does not clash with any existing styles on the page.

 Here is the user's request: \"{$userRequest}\" 
 
Return only the markup — no explanation or preamble. If you understand these instructions, return a fully-formed section now.

Attached is a screenshot of the required design on desktop (make the mobile responsive version based on best practices)";


$images = $input['images'] ?? [];

// Validate images
if (!is_array($images)) {
    http_response_code(400);
    echo json_encode(["error" => "Images must be an array."]);
    exit;
}

// Prepare parts
$parts = [
    ["text" => $masterPrompt]
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