<?php

// Made by alex1dev - https://alex1dev.xyz
// File config.php - Main configuration file

$envFile = __DIR__ . '/.env';
if (file_exists($envFile)) {
    $lines = file($envFile, FILE_IGNORE_NEW_LINES | FILE_SKIP_EMPTY_LINES);
    foreach ($lines as $line) {
        $line = trim($line);
        if ($line === '' || strpos($line, '#') === 0) continue;
        if (strpos($line, '=') !== false) {
            [$key, $value] = explode('=', $line, 2);
            $key = trim($key);
            $value = trim($value);
            $_ENV[$key] = $value;
            putenv("$key=$value");
        }
    }
}

return [
    'debug' => true,

    'temp_dir' => __DIR__ . '/temp_repos',

    'providers' => [
        'https://github.com/%s.git',
    ],

    'ignore_extensions' => ['lock', 'log', 'tmp', 'bak', 'swp', 'git', 'png', 'jpg', 'jpeg', 'gif', 'svg', 'ico', 'woff', 'woff2', 'ttf', 'eot'],

    'ignore_dirs' => ['.git', 'node_modules', 'vendor', 'dist', 'build', 'coverage', '.idea', '.vscode'],

    'groq' => [
        'api_key' => $_ENV['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY') ?: 'YOUR_GROQ_API_KEY_HERE',
        'model' => 'llama-3.3-70b-versatile',
        'max_tokens' => 8192,
        'temperature' => 0.7,
        'max_source_files' => 2,
        'max_source_chars' => 2000,
    ],
];
