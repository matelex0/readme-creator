<?php

if (session_status() === PHP_SESSION_NONE) {
    session_start();
}

function generateCaptcha() {
    return '';
}

function verifyCaptcha($config) {
    if (!$config['captcha']['enabled']) return true;

    $response = $_POST['g-recaptcha-response'] ?? '';
    if (!$response) return false;

    $ch = curl_init('https://www.google.com/recaptcha/api/siteverify');
    curl_setopt($ch, CURLOPT_POST, true);
    curl_setopt($ch, CURLOPT_POSTFIELDS, http_build_query([
        'secret' => $config['captcha']['secret_key'],
        'response' => $response,
        'remoteip' => $_SERVER['REMOTE_ADDR'] ?? '',
    ]));
    curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
    $result = json_decode(curl_exec($ch), true);
    curl_close($ch);

    return !empty($result['success']);
}