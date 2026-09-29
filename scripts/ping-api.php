<?php

$token = trim((string) file_get_contents(sys_get_temp_dir() . '/cms-grok-token.txt'));
$html = (string) file_get_contents('C:/Users/death/Downloads/how-to-care-for-monstera-deliciosa-indoors-beginner-guide.html');
$ch = curl_init('http://127.0.0.1:1155/api/v1/posts');
curl_setopt_array($ch, [
    CURLOPT_POST => true,
    CURLOPT_HTTPHEADER => ['X-Api-Token: ' . $token, 'Content-Type: application/json'],
    CURLOPT_POSTFIELDS => json_encode([
        'title' => 'How to Care for Monstera Deliciosa Indoors',
        'category' => 'Houseplants',
        'html' => $html,
    ]),
    CURLOPT_RETURNTRANSFER => true,
]);
echo curl_exec($ch), PHP_EOL, curl_getinfo($ch, CURLINFO_HTTP_CODE), PHP_EOL;
