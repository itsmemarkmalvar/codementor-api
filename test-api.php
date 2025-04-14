<?php

// Set up the request data
$data = [
    'question' => 'Hello, how are you?',
    'preferences' => [
        'responseLength' => 'brief'
    ],
    'topic_id' => 1
];

// Convert data to JSON
$jsonData = json_encode($data);

// Set up cURL
$ch = curl_init('http://localhost:8000/api/tutor/chat');
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
curl_setopt($ch, CURLOPT_POST, true);
curl_setopt($ch, CURLOPT_POSTFIELDS, $jsonData);
curl_setopt($ch, CURLOPT_HTTPHEADER, [
    'Content-Type: application/json',
    'Content-Length: ' . strlen($jsonData)
]);

// Execute the request
$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$error = curl_error($ch);
curl_close($ch);

// Output the results
echo "HTTP Status Code: " . $httpCode . "\n";
if ($error) {
    echo "cURL Error: " . $error . "\n";
} else {
    echo "Response: " . $response . "\n";
    
    // Pretty print JSON if valid
    $decodedResponse = json_decode($response, true);
    if (json_last_error() === JSON_ERROR_NONE) {
        echo "Decoded Response:\n";
        print_r($decodedResponse);
    }
} 