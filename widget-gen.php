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
$pageType = $input['pageType'] ?? 'product detail page';
$sectionType = $input['sectionType'] ?? 'banner';
$language = $input['language'] ?? 'English';
$layoutDir = $input['layoutDir'] ?? 'Left-To-Right';
$tone = $input['tone'] ?? 'clear, persuasive, and clean';
$userRequest = trim($input['prompt']);

$masterPrompt = "You are an AI assistant embedded inside a KartmaX DIY Page Builder (a visual website builder tool for KartmaX eCommerce platform) used by marketers and designers to create high-performance eCommerce websites. 

Your job is to help generate a single responsive page section that fits seamlessly into an existing layout. You are not creating a full page — only a self-contained block to be inserted where the user has dropped this widget. 

Critial Details about the div that you have to provide the code for:
 1. This is a(n) \"{$sectionType}\" section on a \"{$pageType}\"
 2. Write content copy in \"{$language}\", with \"{$layoutDir}\" layout with a \"{$tone}\" tone.
 3. Here is the user's request: \"{$userRequest}\" 
 4. Attached is a screenshot of the required design on desktop 

Instructions:
 1. Build for modern browsers and optimized for mobile-first layout
 2. Style using semantic, performant HTML and utility-based CSS (no JavaScript unless absolutely necessary)
 3. Use placeholder text where dynamic content (e.g., product names or prices) is expected — DO NOT inject dynamic logic 
 4. Generate valid, accessible HTML with responsive layout
 5. Do NOT include <html>, <head>, <section> or global layout containers — only the div block
 6. Avoid interactive elements that require JavaScript unless explicitly requested
 7. Optimize markup for Core Web Vitals: minimal nesting, small DOM footprint, mobile-first layout
 8. Use https://placehold.co/ for placeholder images wherever needed.
 9. Use https://avatar.iran.liara.run/public/boy or https://avatar.iran.liara.run/public/girl to get placeholder avatar images wherever needed as per your best judgetment.
 10. Make the mobile responsive version based on best practices.
 11. Try to follow the desing queues of the shared screenshot as closely as possible.
 12. Write your own content copy, do not replicate from  the attached screenshot.
 13. IMPORTANT: Assume no pre-exiting UI framework or styling (bootstrap tailwind, etc.).
 14. IMPORTANT: Write your on styling css code in it's own <style> tag covering both desktop & mobile responsiveness.
 15. IMPORTANT: Add a 4 digit random number to any css class that you make so that it does not clash with any existing styles on the page.

Once you are done with the code, check carefully if you have followed all the Critial Details & Instructions, only then share the code, else re-work and share.

VERY IMPORTANT: Return only the markup — no explanation, preamble or any other details
";


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
    // Only redact the API key from the text, keeping the rest of the request
    $text = $sanitizedPayload['contents'][0]['parts'][0]['text'];
    $text = preg_replace('/apiKey=([^&\s]+)/', 'apiKey=[REDACTED]', $text);
    $sanitizedPayload['contents'][0]['parts'][0]['text'] = $text;
}

$logData = [
    date('Y-m-d H:i:s'),
    $_SERVER['REMOTE_ADDR'] ?? 'CLI',
    $_SERVER['REQUEST_METHOD'] ?? 'UNKNOWN',
    $_SERVER['HTTP_USER_AGENT'] ?? 'UNKNOWN',
    $text, // Do not log the actual prompt
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