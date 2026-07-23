<?php

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

    'max_repo_size' => 250 * 1024 * 1024,

    'ai' => [
        'default_provider' => $_ENV['AI_PROVIDER'] ?? getenv('AI_PROVIDER') ?: 'cerebras',
        'max_source_files' => 4,
        'max_source_chars' => 4000,
        'providers' => [
            'groq' => [
                'api_key' => $_ENV['GROQ_API_KEY'] ?? getenv('GROQ_API_KEY') ?: '',
                'model' => 'llama-3.3-70b-versatile',
                'max_tokens' => 8192,
                'temperature' => 0.7,
                'endpoint' => 'https://api.groq.com/openai/v1/chat/completions',
            ],
            'cerebras' => [
                'api_key' => $_ENV['CEREBRAS_API_KEY'] ?? getenv('CEREBRAS_API_KEY') ?: '',
                'model' => 'gpt-oss-120b',
                'max_tokens' => 32768,
                'temperature' => 0.7,
                'endpoint' => 'https://api.cerebras.ai/v1/chat/completions',
            ],
        ],
    ],

    'captcha' => [
        'enabled' => filter_var($_ENV['CAPTCHA_ENABLED'] ?? getenv('CAPTCHA_ENABLED') ?: 'true', FILTER_VALIDATE_BOOLEAN),
        'site_key' => $_ENV['RECAPTCHA_SITE_KEY'] ?? getenv('RECAPTCHA_SITE_KEY') ?: '',
        'secret_key' => $_ENV['RECAPTCHA_SECRET_KEY'] ?? getenv('RECAPTCHA_SECRET_KEY') ?: '',
    ],
];
