<?php

$url = 'http://127.0.0.1:8000/api/v1/auth/login';
$data = json_encode(['email' => 'owner@delight.com', 'password' => 'password123']);

$ch = curl_init($url);
curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
curl_setopt($ch, CURLOPT_HTTPHEADER, ['Content-Type: application/json', 'Accept: application/json']);
curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

$response = curl_exec($ch);
curl_close($ch);

echo "--- LOGIN API RESPONSE ---\n";
echo $response . "\n";
