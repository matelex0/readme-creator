<?php

// Made by alex1dev - https://alex1dev.xyz
// File needed in the site to generate the readme

class ReadmeGenerator {
    private $config;
    private $tempPath;

    public function __construct($config) {
        $this->config = $config;
    }

    public function sanitize($input) {
        return preg_replace('/[^a-zA-Z0-9_\-\.]/', '', $input);
    }

    public function findRepoUrl($owner, $repo) {
        $apiRepoUrl = $this->findRepoWithGitHubApi($owner, $repo);
        if ($apiRepoUrl) {
            return $apiRepoUrl;
        }

        $path = $owner . '/' . $repo;

        foreach ($this->config['providers'] as $providerTemplate) {
            $url = sprintf($providerTemplate, $path);

            $cmd = sprintf('git ls-remote %s HEAD', escapeshellarg($url));
            exec($cmd, $output, $returnVar);

            if ($returnVar === 0) {
                return $url;
            }
        }

        return null;
    }

    private function findRepoWithGitHubApi($owner, $repo) {
        $apiUrl = sprintf(
            'https://api.github.com/repos/%s/%s',
            rawurlencode($owner),
            rawurlencode($repo)
        );

        $context = stream_context_create([
            'http' => [
                'method' => 'GET',
                'header' => "User-Agent: README-Creator\r\nAccept: application/vnd.github+json\r\n",
                'timeout' => 8,
                'ignore_errors' => true,
            ],
        ]);

        $response = @file_get_contents($apiUrl, false, $context);
        if ($response === false) {
            return null;
        }

        if (!isset($http_response_header) || !is_array($http_response_header)) {
            return null;
        }

        $statusLine = $http_response_header[0] ?? '';
        if (strpos($statusLine, '200') === false) {
            return null;
        }

        $json = json_decode($response, true);
        if (!is_array($json)) {
            return null;
        }

        if (!empty($json['clone_url'])) {
            return $json['clone_url'];
        }

        if (!empty($json['html_url'])) {
            return rtrim($json['html_url'], '/') . '.git';
        }

        return null;
    }

    public function cloneRepo($url) {
        if (!file_exists($this->config['temp_dir'])) {
            mkdir($this->config['temp_dir'], 0700, true);
        }

        $folderName = uniqid('repo_');
        $targetPath = $this->config['temp_dir'] . '/' . $folderName;

        $this->tempPath = $targetPath;

        $cmd = sprintf('git clone --depth=1 %s %s 2>&1', escapeshellarg($url), escapeshellarg($targetPath));
        exec($cmd, $output, $returnVar);

        if ($returnVar !== 0) {
            throw new Exception("Error cloning repository.");
        }

        return $targetPath;
    }

    public function analyze($path) {
        $data = [
            'languages' => [],
            'frameworks' => [],
            'structure' => [],
            'has_tests' => false,
            'has_docker' => false,
            'has_env' => false,
            'has_makefile' => false,
            'license' => 'Not specified',
            'author' => null,
            'description' => null,
            'version' => null,
        ];

        $files = new RecursiveIteratorIterator(
            new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS),
            RecursiveIteratorIterator::SELF_FIRST
        );

        $fileCount = 0;
        $maxFiles = 2000;

        $extMap = [
            'php' => 'PHP',
            'js' => 'JavaScript', 'jsx' => 'JavaScript', 'mjs' => 'JavaScript',
            'ts' => 'TypeScript', 'tsx' => 'TypeScript',
            'py' => 'Python',
            'go' => 'Go',
            'java' => 'Java',
            'rb' => 'Ruby',
            'rs' => 'Rust',
            'c' => 'C', 'h' => 'C',
            'cpp' => 'C++', 'cc' => 'C++', 'hpp' => 'C++',
            'cs' => 'C#',
            'html' => 'HTML',
            'css' => 'CSS', 'scss' => 'CSS', 'less' => 'CSS',
            'sql' => 'SQL',
            'sh' => 'Shell',
            'vue' => 'Vue.js',
            'swift' => 'Swift',
            'kt' => 'Kotlin',
            'dart' => 'Dart',
        ];

        foreach ($files as $file) {
            if ($fileCount++ > $maxFiles) break;

            $filename = $file->getFilename();
            $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
            $relativePath = str_replace($path . DIRECTORY_SEPARATOR, '', $file->getPathname());

            $firstDir = explode(DIRECTORY_SEPARATOR, $relativePath)[0];
            if (in_array($firstDir, $this->config['ignore_dirs'])) continue;
            if (strpos($relativePath, '.git') === 0) continue;

            if (isset($extMap[$ext])) {
                $lang = $extMap[$ext];
                if (!isset($data['languages'][$lang])) {
                    $data['languages'][$lang] = 0;
                }
                $data['languages'][$lang] += filesize($file->getPathname());
            }

            if ($filename === 'composer.json') {
                $data['frameworks']['Composer'] = true;
                $content = @file_get_contents($file->getPathname());
                if ($content) {
                    $json = json_decode($content, true);
                    if ($json) {
                        if (isset($json['description']) && empty($data['description'])) $data['description'] = $json['description'];
                        if (isset($json['authors'][0]['name']) && empty($data['author'])) $data['author'] = $json['authors'][0]['name'];
                        if (isset($json['version']) && empty($data['version'])) $data['version'] = $json['version'];
                        if (isset($json['license'])) $data['license'] = $json['license'];

                        if (isset($json['require']['laravel/framework'])) $data['frameworks']['Laravel'] = true;
                        if (isset($json['require']['symfony/framework-bundle'])) $data['frameworks']['Symfony'] = true;
                    }
                }
            }
            if ($filename === 'package.json') {
                $data['frameworks']['Node.js'] = true;
                $content = @file_get_contents($file->getPathname());
                if ($content) {
                    $json = json_decode($content, true);
                    if ($json) {
                        if (isset($json['description']) && empty($data['description'])) $data['description'] = $json['description'];
                        if (isset($json['author']) && empty($data['author'])) {
                            $data['author'] = is_array($json['author']) ? ($json['author']['name'] ?? '') : $json['author'];
                        }
                        if (isset($json['version']) && empty($data['version'])) $data['version'] = $json['version'];
                        if (isset($json['license'])) $data['license'] = $json['license'];

                        $deps = array_merge($json['dependencies'] ?? [], $json['devDependencies'] ?? []);
                        if (isset($deps['react'])) $data['frameworks']['React'] = true;
                        if (isset($deps['vue'])) $data['frameworks']['Vue.js'] = true;
                        if (isset($deps['@angular/core'])) $data['frameworks']['Angular'] = true;
                        if (isset($deps['next'])) $data['frameworks']['Next.js'] = true;
                        if (isset($deps['nuxt'])) $data['frameworks']['Nuxt'] = true;
                        if (isset($deps['svelte'])) $data['frameworks']['Svelte'] = true;
                        if (isset($deps['tailwindcss'])) $data['frameworks']['Tailwind CSS'] = true;
                        if (isset($deps['bootstrap'])) $data['frameworks']['Bootstrap'] = true;
                        if (isset($deps['typescript'])) $data['languages']['TypeScript'] = ($data['languages']['TypeScript'] ?? 0) + 1;
                        if (isset($deps['jest'])) $data['frameworks']['Jest'] = true;
                    }
                }
            }
            if ($filename === 'artisan') $data['frameworks']['Laravel'] = true;
            if ($filename === 'symfony.lock') $data['frameworks']['Symfony'] = true;
            if ($filename === 'wp-config.php' || ($filename === 'style.css' && strpos(@file_get_contents($file->getPathname()), 'Theme Name:') !== false)) {
                $data['frameworks']['WordPress'] = true;
            }
            if ($filename === 'requirements.txt') {
                $content = @file_get_contents($file->getPathname());
                if (strpos($content, 'Django') !== false) $data['frameworks']['Django'] = true;
                if (strpos($content, 'flask') !== false) $data['frameworks']['Flask'] = true;
                if (strpos($content, 'fastapi') !== false) $data['frameworks']['FastAPI'] = true;
            }
            if ($filename === 'manage.py') $data['frameworks']['Django'] = true;
            if ($filename === 'go.mod') $data['frameworks']['Go Modules'] = true;
            if ($filename === 'Cargo.toml') $data['frameworks']['Cargo'] = true;
            if ($filename === 'Gemfile') $data['frameworks']['Ruby on Rails'] = true;
            if ($filename === 'pom.xml') $data['frameworks']['Maven'] = true;
            if ($filename === 'build.gradle') $data['frameworks']['Gradle'] = true;
            if ($filename === 'Dockerfile' || $filename === 'docker-compose.yml') $data['has_docker'] = true;
            if (preg_match('/\.test\.|\.spec\.|\\_test\\.|-test\\./', $filename) || $firstDir === 'tests' || $firstDir === '__tests__' || $firstDir === 'spec') $data['has_tests'] = true;
            if (stripos($filename, 'license') !== false && $data['license'] === 'Not specified') $data['license'] = 'See LICENSE file';
            if ($filename === '.env.example' || $filename === '.env') $data['has_env'] = true;
            if ($filename === 'Makefile') $data['has_makefile'] = true;

            if ($filename === 'vite.config.js' || $filename === 'vite.config.ts') $data['frameworks']['Vite'] = true;
            if ($filename === 'webpack.config.js') $data['frameworks']['Webpack'] = true;
            if (strpos($filename, '.prettier') !== false) $data['frameworks']['Prettier'] = true;
            if (strpos($filename, '.eslint') !== false) $data['frameworks']['ESLint'] = true;
            if ($filename === '.babelrc' || $filename === 'babel.config.js') $data['frameworks']['Babel'] = true;
            if ($filename === 'pubspec.yaml') {
                 $data['frameworks']['Flutter'] = true;
                 $data['languages']['Dart'] = ($data['languages']['Dart'] ?? 0) + 1;
            }
            if ($filename === 'AndroidManifest.xml') $data['frameworks']['Android'] = true;
            if ($filename === 'Podfile') $data['frameworks']['iOS'] = true;
            if ($ext === 'tf') $data['frameworks']['Terraform'] = true;
            if (strpos($relativePath, '.github/workflows') !== false) $data['frameworks']['GitHub Actions'] = true;

            if (!in_array($ext, $this->config['ignore_extensions'])) {
                $data['structure'][] = $relativePath;
            }
        }

        sort($data['structure']);

        unset($data['languages']['Markdown']);
        unset($data['languages']['Pip']);
        unset($data['frameworks']['Pip']);
        unset($data['frameworks']['Markdown']);

        arsort($data['languages']);

        return $data;
    }

    private function generateTree($paths) {
        $tree = "";
        $maxLines = 25;
        $lines = 0;

        $structure = [];
        foreach ($paths as $path) {
            $parts = explode(DIRECTORY_SEPARATOR, $path);
            $current = &$structure;
            foreach ($parts as $part) {
                if (!isset($current[$part])) {
                    $current[$part] = [];
                }
                $current = &$current[$part];
            }
        }

        $render = function($node, $prefix = '') use (&$tree, &$lines, &$maxLines, &$render) {
            if ($lines >= $maxLines) return;

            $keys = array_keys($node);
            $lastIndex = count($keys) - 1;

            foreach ($keys as $index => $key) {
                if ($lines >= $maxLines) {
                    $tree .= $prefix . "...\n";
                    return;
                }

                $isLast = ($index === $lastIndex);
                $connector = $isLast ? "└── " : "├── ";
                $tree .= $prefix . $connector . $key . "\n";
                $lines++;

                if (in_array($key, ['node_modules', 'vendor', '.git', 'dist', 'build'])) continue;

                if (!empty($node[$key])) {
                    $newPrefix = $prefix . ($isLast ? "    " : "│   ");
                    $render($node[$key], $newPrefix);
                }
            }
        };

        $render($structure);
        return $tree;
    }

    private function getSocialifyUrl($owner, $repo) {
        return sprintf(
            "https://socialify.git.ci/%s/%s/image?description=1&font=Inter&language=1&name=1&owner=1&pattern=Transparent&theme=Auto",
            $owner,
            $repo
        );
    }

    private function getTechBadge($tech, $percentage = null) {
        $techLower = strtolower($tech);

        $map = [
            'php' => ['color' => '777BB4', 'logo' => 'php'],
            'laravel' => ['color' => 'FF2D20', 'logo' => 'laravel'],
            'symfony' => ['color' => '000000', 'logo' => 'symfony'],
            'javascript' => ['color' => 'F7DF1E', 'logo' => 'javascript'],
            'typescript' => ['color' => '3178C6', 'logo' => 'typescript'],
            'react' => ['color' => '61DAFB', 'logo' => 'react'],
            'vue.js' => ['color' => '4FC08D', 'logo' => 'vuedotjs'],
            'angular' => ['color' => 'DD0031', 'logo' => 'angular'],
            'node.js' => ['color' => '339933', 'logo' => 'nodedotjs'],
            'python' => ['color' => '3776AB', 'logo' => 'python'],
            'django' => ['color' => '092E20', 'logo' => 'django'],
            'flask' => ['color' => '000000', 'logo' => 'flask'],
            'go' => ['color' => '00ADD8', 'logo' => 'go'],
            'rust' => ['color' => '000000', 'logo' => 'rust'],
            'docker' => ['color' => '2496ED', 'logo' => 'docker'],
            'html' => ['color' => 'E34F26', 'logo' => 'html5'],
            'css' => ['color' => '1572B6', 'logo' => 'css3'],
            'tailwind css' => ['color' => '06B6D4', 'logo' => 'tailwindcss'],
            'bootstrap' => ['color' => '7952B3', 'logo' => 'bootstrap'],
            'wordpress' => ['color' => '21759B', 'logo' => 'wordpress'],
            'mysql' => ['color' => '4479A1', 'logo' => 'mysql'],
            'git' => ['color' => 'F05032', 'logo' => 'git'],
            'shell' => ['color' => '121011', 'logo' => 'gnu-bash'],
            'markdown' => ['color' => '000000', 'logo' => 'markdown'],
            'vite' => ['color' => '646CFF', 'logo' => 'vite'],
            'webpack' => ['color' => '8DD6F9', 'logo' => 'webpack'],
            'prettier' => ['color' => 'F7B93E', 'logo' => 'prettier'],
            'eslint' => ['color' => '4B32C3', 'logo' => 'eslint'],
            'babel' => ['color' => 'F9DC3E', 'logo' => 'babel'],
            'flutter' => ['color' => '02569B', 'logo' => 'flutter'],
            'android' => ['color' => '3DDC84', 'logo' => 'android'],
            'ios' => ['color' => '000000', 'logo' => 'apple'],
            'terraform' => ['color' => '7B42BC', 'logo' => 'terraform'],
            'github actions' => ['color' => '2088FF', 'logo' => 'github-actions'],
            'mongodb' => ['color' => '47A248', 'logo' => 'mongodb'],
            'postgresql' => ['color' => '4169E1', 'logo' => 'postgresql'],
            'graphql' => ['color' => 'E10098', 'logo' => 'graphql'],
        ];

        $color = '555555';
        $logo = str_replace(['.', ' '], '', $techLower);

        if (isset($map[$techLower])) {
            $color = $map[$techLower]['color'];
            $logo = $map[$techLower]['logo'];
        }

        $label = $tech;
        if ($percentage !== null) {
            $label .= " " . $percentage . "%";
        }

        $labelEncoded = rawurlencode($label);

        return sprintf(
            '![%s](https://img.shields.io/badge/%s-%s?style=for-the-badge&logo=%s&logoColor=white)',
            $tech,
            $labelEncoded,
            $color,
            $logo
        );
    }

    private function generateFeatures($data) {
        $features = [];

        // Infrastructure & Tools
        if (!empty($data['has_docker'])) $features[] = "Containerized deployment with Docker for consistent environments";
        if (!empty($data['has_tests'])) $features[] = "Comprehensive test suite setup for reliability";
        if (!empty($data['has_env'])) $features[] = "Environment-based configuration management";
        if (isset($data['frameworks']['Composer'])) $features[] = "Dependency management via Composer";
        if (isset($data['frameworks']['Node.js'])) $features[] = "Node.js runtime environment";

        if (isset($data['languages']['TypeScript'])) $features[] = "Type-safe codebase using TypeScript";
        if (isset($data['languages']['Go'])) $features[] = "High-performance concurrency with Go routines";
        if (isset($data['languages']['Rust'])) $features[] = "Memory safety and performance guaranteed by Rust";

        if (isset($data['frameworks']['Laravel'])) {
            $features[] = "Robust MVC architecture powered by Laravel";
            $features[] = "Eloquent ORM for elegant database interactions";
        }
        if (isset($data['frameworks']['Symfony'])) {
            $features[] = "Modular and reusable Symfony components";
        }
        if (isset($data['frameworks']['React'])) {
            $features[] = "Interactive and reactive UI components";
            $features[] = "Virtual DOM for optimal rendering performance";
        }
        if (isset($data['frameworks']['Vue.js'])) {
            $features[] = "Progressive JavaScript framework architecture";
            $features[] = "Reactive data binding";
        }
        if (isset($data['frameworks']['Tailwind CSS'])) {
            $features[] = "Modern, utility-first CSS styling";
            $features[] = "Responsive design out of the box";
        }
        if (isset($data['frameworks']['Bootstrap'])) {
            $features[] = "Responsive grid system and pre-built components";
        }
        if (isset($data['frameworks']['Django'])) {
            $features[] = "Secure and scalable Python backend";
            $features[] = "Built-in admin interface and ORM";
        }
        if (isset($data['frameworks']['Flask']) || isset($data['frameworks']['FastAPI'])) {
            $features[] = "Lightweight and high-performance API development";
        }
        if (isset($data['frameworks']['Spring Boot']) || isset($data['frameworks']['Maven']) || isset($data['frameworks']['Gradle'])) {
            $features[] = "Enterprise-grade Java application structure";
        }
        if (isset($data['frameworks']['Next.js']) || isset($data['frameworks']['Nuxt'])) {
            $features[] = "Server-Side Rendering (SSR) and Static Site Generation (SSG)";
            $features[] = "Optimized routing and performance";
        }
        if (isset($data['frameworks']['Vite'])) $features[] = "Lightning-fast build tool powered by Vite";
        if (isset($data['frameworks']['Flutter'])) $features[] = "Cross-platform mobile development with Flutter";
        if (isset($data['frameworks']['Android'])) $features[] = "Native Android application development";
        if (isset($data['frameworks']['iOS'])) $features[] = "Native iOS application development";
        if (isset($data['frameworks']['Terraform'])) $features[] = "Infrastructure as Code (IaC) managed via Terraform";
        if (isset($data['frameworks']['GitHub Actions'])) $features[] = "Automated CI/CD workflows with GitHub Actions";
        if (isset($data['frameworks']['GraphQL'])) $features[] = "Efficient data fetching with GraphQL";

        if (empty($features)) {
             $features[] = "Clean and modular code structure";
             $features[] = "Easy to customize and extend";
             $features[] = "Well-documented codebase";
        }

        if (count($features) < 3) {
            $generics = [
                "Cross-platform compatibility",
                "Lightweight and efficient",
                "Follows best practices",
                "Ready for production deployment"
            ];
            foreach ($generics as $g) {
                if (!in_array($g, $features)) {
                    $features[] = $g;
                    if (count($features) >= 3) break;
                }
            }
        }

        return array_unique($features);
    }

    public function collectSourceContent($path) {
        $maxFiles = $this->config['ai']['max_source_files'] ?? 4;
        $maxChars = $this->config['ai']['max_source_chars'] ?? 4000;

        $priorityNames = [
            'package.json', 'composer.json', 'Cargo.toml', 'requirements.txt',
            'Dockerfile', 'docker-compose.yml', 'Makefile', '.env.example',
            'webpack.config.js', 'vite.config.js', 'vite.config.ts', 'tsconfig.json',
            '.gitignore', 'Gemfile', 'Pipfile', 'pubspec.yaml', 'go.mod',
            'pom.xml', 'build.gradle', 'README.md', 'readme.md',
            'index.php', 'index.js', 'index.ts', 'main.py', 'main.go',
            'main.rs', 'app.js', 'app.py', 'server.js', 'server.py',
            'cli.php', 'artisan', 'manage.py',
            'LICENSE', 'LICENSE.md', 'LICENSE.txt', 'license', 'license.md', 'license.txt',
        ];

        $codeExts = ['php', 'js', 'ts', 'py', 'go', 'rs', 'java', 'rb', 'c',
                     'cpp', 'cs', 'vue', 'swift', 'kt', 'dart', 'sh', 'css',
                     'scss', 'html', 'tf', 'yaml', 'yml', 'toml', 'lua'];

        $result = [];
        $totalChars = 0;
        $filesList = [];

        $dir = new RecursiveDirectoryIterator($path, RecursiveDirectoryIterator::SKIP_DOTS);
        $iterator = new RecursiveIteratorIterator($dir, RecursiveIteratorIterator::SELF_FIRST);

        foreach ($iterator as $file) {
            if (!$file->isFile()) continue;
            $filesList[] = $file;
        }

        $priorityFiles = [];
        $otherFiles = [];

        foreach ($filesList as $file) {
            $filename = $file->getFilename();
            $relativePath = str_replace($path . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $firstDir = explode(DIRECTORY_SEPARATOR, $relativePath)[0];

            if (in_array($firstDir, $this->config['ignore_dirs'])) continue;

            if (in_array($filename, $priorityNames)) {
                $priorityFiles[] = $file;
            } else {
                $ext = strtolower(pathinfo($filename, PATHINFO_EXTENSION));
                if (in_array($ext, $codeExts)
                    && !in_array($ext, $this->config['ignore_extensions'])
                    && $file->getSize() <= 50000
                ) {
                    $otherFiles[] = $file;
                }
            }
        }

        foreach ($priorityFiles as $file) {
            $relativePath = str_replace($path . DIRECTORY_SEPARATOR, '', $file->getPathname());
            $content = @file_get_contents($file->getPathname());
            if ($content !== false) {
                $truncated = substr($content, 0, 500);
                $result[$relativePath] = $truncated;
                $totalChars += strlen($truncated);
            }
            if (count($result) >= $maxFiles || $totalChars >= $maxChars) break;
        }

        if ($totalChars < $maxChars && count($result) < $maxFiles) {
            foreach ($otherFiles as $file) {
                $relativePath = str_replace($path . DIRECTORY_SEPARATOR, '', $file->getPathname());
                if (isset($result[$relativePath])) continue;

                $content = @file_get_contents($file->getPathname());
                if ($content === false) continue;

                $truncated = substr($content, 0, 800);
                $result[$relativePath] = $truncated;
                $totalChars += strlen($truncated);

                if (count($result) >= $maxFiles || $totalChars >= $maxChars) break;
            }
        }

        return $result;
    }

    public function callAI($messages, $provider = null) {
        if (!function_exists('curl_init')) {
            throw new Exception('cURL extension is not installed. Enable it in PHP to use AI generation.');
        }

        $providers = $this->config['ai']['providers'] ?? [];
        $providerName = $provider ?: ($this->config['ai']['default_provider'] ?? 'cerebras');

        if (!isset($providers[$providerName])) {
            throw new Exception("AI provider '$providerName' not configured.");
        }

        $cfg = $providers[$providerName];
        $apiKey = $cfg['api_key'] ?? '';
        $label = ucfirst($providerName);

        if (empty($apiKey)) {
            throw new Exception(strtoupper($providerName) . " API key not configured. Set it in .env file ({$providerName}_api_key).");
        }

        $url = $cfg['endpoint'];
        $maxRetries = 3;

        $payload = [
            'model' => $cfg['model'],
            'messages' => $messages,
            'max_tokens' => $cfg['max_tokens'] ?? 8192,
            'temperature' => $cfg['temperature'] ?? 0.7,
        ];

        $lastError = '';

        for ($attempt = 0; $attempt <= $maxRetries; $attempt++) {
            $ch = curl_init();
            curl_setopt_array($ch, [
                CURLOPT_URL => $url,
                CURLOPT_POST => true,
                CURLOPT_HTTPHEADER => [
                    'Content-Type: application/json',
                    'Authorization: Bearer ' . $apiKey,
                ],
                CURLOPT_POSTFIELDS => json_encode($payload),
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_TIMEOUT => 180,
                CURLOPT_SSL_VERIFYPEER => true,
                CURLOPT_HEADER => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $headerSize = curl_getinfo($ch, CURLINFO_HEADER_SIZE);
            $curlError = curl_error($ch);

            if ($response === false) {
                curl_close($ch);
                if ($attempt >= $maxRetries) {
                    throw new Exception("{$label} API connection error after " . ($maxRetries + 1) . " attempts: {$curlError}");
                }
                $lastError = $curlError;
                sleep(pow(2, $attempt + 1));
                continue;
            }

            $headers = substr($response, 0, $headerSize);
            $body = substr($response, $headerSize);
            curl_close($ch);

            if ($httpCode === 200) {
                $data = json_decode($body, true);
                if (!isset($data['choices'][0]['message']['content'])) {
                    throw new Exception("{$label} API returned an empty response.");
                }
                return $data['choices'][0]['message']['content'];
            }

            $data = json_decode($body, true);
            $errorMsg = $data['error']['message'] ?? 'Unknown error';

            if ($httpCode === 429) {
                preg_match('/Retry-After:\s*(\d+)/i', $headers, $matches);
                $retryAfter = isset($matches[1]) ? (int)$matches[1] : pow(2, $attempt + 1);
                if ($attempt >= $maxRetries) {
                    throw new Exception("{$label} API rate limit exceeded: {$errorMsg}");
                }
                $lastError = "Rate limited, retrying in {$retryAfter}s...";
                sleep($retryAfter);
                continue;
            }

            if ($httpCode === 401) {
                throw new Exception("{$label} API authentication failed (HTTP 401). Check your API key.");
            }

            throw new Exception("{$label} API error (HTTP {$httpCode}): {$errorMsg}");
        }

        throw new Exception("{$label} API request failed after " . ($maxRetries + 1) . " attempts. {$lastError}");
    }

    public function generateMarkdownAI($owner, $repo, $data, $url, $customImage = null, $sourceContent = []) {
        set_time_limit(0);

        $totalBytes = array_sum($data['languages']);
        $langDetails = '';
        if ($totalBytes > 0) {
            foreach ($data['languages'] as $lang => $bytes) {
                $pct = round(($bytes / $totalBytes) * 100, 1);
                if ($pct >= 1) $langDetails .= "- {$lang}: {$pct}%\n";
            }
        }

        $frameworks = array_keys($data['frameworks']);
        $techStack = !empty($frameworks) ? implode(', ', $frameworks) : 'None';

        $features = $this->generateFeatures($data);

        $structure = $this->generateTree($data['structure']);

        $sourceBlock = '';
        $currentChars = 0;
        foreach ($sourceContent as $filePath => $content) {
            $block = "### {$filePath}\n```\n{$content}\n```\n\n";
            if ($currentChars + strlen($block) > $this->config['ai']['max_source_chars']) break;
            $sourceBlock .= $block;
            $currentChars += strlen($block);
        }

        $imageUrl = $customImage ?: $this->getSocialifyUrl($owner, $repo);

        $systemPrompt = <<<PROMPT
You are an expert technical writer. Generate a polished, professional README.md in English that follows this exact structure. The output must be comprehensive, well-organized, and developer-friendly.

## CORE RULES
1. Output ONLY raw markdown — no code fences around the output, no explanations, no comments.
2. Use REAL data from the analysis. NEVER invent features, commands, or dependencies.
3. Do NOT put markdown syntax inside HTML tags. Inside <div> use only <img>.
4. Every shields.io badge must use a realistic color. Prefer `style=for-the-badge` for tech badges.
5. Write with authority and clarity. Assume the reader is a developer evaluating the project.

## EXACT OUTPUT TEMPLATE

### 1. Header
\`\`\`markdown
<div align="center">
  <img src="HEADER_IMAGE_URL" alt="PROJECT" width="500" />
</div>
\`\`\`

### 2. Badge Row (inside div align="center")
Group all badges in one `<div align="center">` block. Include:
- License badge: `https://img.shields.io/badge/license-NAME-blue.svg?style=flat-square`
- Top Language: `https://img.shields.io/github/languages/top/OWNER/REPO?style=flat-square`
- Repo Size: `https://img.shields.io/github/repo-size/OWNER/REPO?style=flat-square`
- Last Commit: `https://img.shields.io/github/last-commit/OWNER/REPO?style=flat-square`
- Stars, Issues, Forks if relevant
Then add a `<p align="center"><em>Short tagline (one sentence)</em></p>` followed by shields.io badges for contributors, forks, and stars in another `<p align="center">`:
\`\`\`markdown
<p align="center">
  <a href="https://github.com/OWNER/REPO/graphs/contributors">
    <img src="https://img.shields.io/github/contributors/OWNER/REPO?style=flat-square" alt="contributors" />
  </a>
  <a href="https://github.com/OWNER/REPO/network/members">
    <img src="https://img.shields.io/github/forks/OWNER/REPO?style=flat-square" alt="forks" />
  </a>
  <a href="https://github.com/OWNER/REPO/stargazers">
    <img src="https://img.shields.io/github/stars/OWNER/REPO?style=flat-square" alt="stars" />
  </a>
</p>
\`\`\`
Close the div.

### 3. Separator
\`\`\`markdown
---
\`\`\`

### 4. Table of Contents
Must include all sections present in the README. Example:
\`\`\`markdown
## Table of Contents
- [Overview](#overview)
- [Languages](#languages)
- [Features](#features)
- [Getting Started](#getting-started)
  - [Prerequisites](#prerequisites)
  - [Installation](#installation)
- [Usage](#usage)
- [Project Structure](#project-structure)
- [Contributing](#contributing)
- [License](#license)
\`\`\`

### 5. Overview
**Project Name** — A detailed 3-4 sentence paragraph: what the project does, the problem it solves, who it's for, and its key value proposition. Be specific and compelling.

### 6. Languages
One badge per detected language using \`style=for-the-badge\`. Use the accurate logo and color.

### 7. Features
List features with an emoji, bold title, and a short description. Like this:
\`\`\`markdown
- 🔍 **Automatic Analysis**: Scans your repository to detect programming languages, frameworks, and tools.
- 🤖 **AI-Powered Generation**: Optional AI generation for richer, more accurate READMEs.
- 📊 **Language Statistics**: Generates language distribution badges automatically.
\`\`\`
Each feature must have a real description after the colon. Never use just a bold title without explanation.

### 8. Getting Started
#### Prerequisites
Bullet list of actual runtime requirements from the analysis. Include versions if detected.
#### Installation
Numbered steps. Start with clone, then add real install commands from the analysis.
\`\`\`markdown
1. Clone the repository:
\`\`\`bash
git clone URL
cd REPO
\`\`\`
2. (next step from analysis)
\`\`\`bash
command
\`\`\`
\`\`\`

### 9. Usage
Numbered steps explaining how to use the project. Be specific with commands and examples.
\`\`\`markdown
1. Step one — what to do.
2. Step two — what to do.
> **Tip:** A helpful tip if applicable.
\`\`\`

### 10. Scripts (if package.json or composer.json scripts exist)
If the source context shows a scripts section in package.json or composer.json, list available scripts:
\`\`\`bash
npm run dev      # Start development server
npm run build    # Build for production
npm run test     # Run tests
\`\`\`

### 11. API Reference (if source context shows functions, endpoints, or a CLI)
Document the main entry points with signatures, parameters, return values, and examples.

### 12. Configuration (if .env or config files exist)
If the project uses environment variables or config files, show how to configure:
\`\`\`bash
cp .env.example .env
# Edit .env with your settings
\`\`\`
Then list key variables in a table:
| Variable | Description | Default |
|----------|-------------|---------|
| PORT | Server port | 3000 |

### 13. Testing (if tests exist)
If the analysis shows test files or test frameworks, show how to run tests:
\`\`\`bash
npm test
# or
python -m pytest
\`\`\`

### 14. Docker (if Docker detected)
If Dockerfile or docker-compose.yml exists:
\`\`\`bash
docker build -t NAME .
docker run -p 3000:3000 NAME
\`\`\`

### 15. Screenshots / Demo (if assets/ or screenshots/ directory exists)
If the project has image assets, add a placeholder section for screenshots.
Only include if assets/images are detected.

### 16. Project Structure
\`\`\`text
tree from analysis
\`\`\`
After the tree, add a bullet list with brief descriptions of the key files:
\`\`\`markdown
- \`config.php\` — Configuration settings
- \`index.php\` — Main entry point and UI
- \`ReadmeGenerator.php\` — Core logic for analysis and generation
\`\`\`

### 17. Contributing
Numbered steps:
\`\`\`markdown
Contributions are welcome! Please feel free to submit a Pull Request.
1. Fork the project
2. Create your feature branch (\`git checkout -b feature/AmazingFeature\`)
3. Commit your changes (\`git commit -m 'Add some AmazingFeature'\`)
4. Push to the branch (\`git push origin feature/AmazingFeature\`)
5. Open a Pull Request
\`\`\`

### 18. License
If LICENSE file content is available, write a 2-sentence human summary with a link. Otherwise state the detected license name with a badge and link.
\`\`\`markdown
Distributed under the MIT License. See \`LICENSE\` for more information.
\`\`\`

### 19. Acknowledgements (if applicable)
Only include if the project has dependencies, credits, or third-party assets worth noting.
\`\`\`markdown
- [Library Name](https://...) — What it's used for
- [Tool Name](https://...) — What it's used for
\`\`\`

### 20. Footer
\`\`\`markdown
---
Created by **OWNER**
\`\`\`

PROMPT;


        $userPrompt = "Generate a README.md for **{$owner}/{$repo}**.\n\n"
            . "## Repository Info\n"
            . "- Owner: {$owner}\n- Repo: {$repo}\n- URL: {$url}\n"
            . "- Description: " . ($data['description'] ?? 'N/A') . "\n"
            . "- License: {$data['license']}\n"
            . "- Author: " . ($data['author'] ?? $owner) . "\n"
            . "- Version: " . ($data['version'] ?? 'N/A') . "\n\n"
            . "## Languages\n{$langDetails}\n"
            . "## Tech Stack\n{$techStack}\n\n"
            . "## Flags\n"
            . "- Tests: " . ($data['has_tests'] ? 'Yes' : 'No') . "\n"
            . "- Docker: " . ($data['has_docker'] ? 'Yes' : 'No') . "\n"
            . "- Env Config: " . ($data['has_env'] ? 'Yes' : 'No') . "\n\n"
            . "## Features\n";
        foreach ($features as $f) {
            $userPrompt .= "- {$f}\n";
        }
        $userPrompt .= "\n## Structure\n```\n{$structure}```\n\n"
            . "## Header Image URL\n{$imageUrl}\n\n"
            . ($sourceBlock ? "## Source Files Context\n{$sourceBlock}" : "")
            . "\n---\nGenerate the complete README.md in English. Output ONLY the markdown.";

        return $this->callAI([
            ['role' => 'system', 'content' => $systemPrompt],
            ['role' => 'user', 'content' => $userPrompt],
        ]);
    }

    public function generateMarkdown($owner, $repo, $data, $url, $customImage = null) {
        $description = isset($data['description']) ? $data['description'] : "";
        $author = $data['author'] ? $data['author'] : $owner;

        $md = "<div align=\"center\">\n";
        if ($customImage) {
            $md .= "  <img src=\"" . htmlspecialchars($customImage, ENT_QUOTES) . "\" alt=\"" . htmlspecialchars($repo, ENT_QUOTES) . "\" width=\"500\" />\n";
        } else {
            $md .= "  <img src=\"" . $this->getSocialifyUrl($owner, $repo) . "\" alt=\"" . $repo . "\" width=\"500\" />\n";
        }
        $md .= "</div>\n\n";

        $md .= "<div align=\"center\">\n\n";

        if ($data['license'] && $data['license'] !== 'Not specified' && $data['license'] !== 'See LICENSE file') {
             $licenseEncoded = rawurlencode($data['license']);
             $md .= "![License](https://img.shields.io/badge/license-" . $licenseEncoded . "-blue.svg?style=flat-square) ";
        } else {
             $md .= "![License](https://img.shields.io/github/license/" . $owner . "/" . $repo . "?style=flat-square) ";
        }

        $md .= "![Top Language](https://img.shields.io/github/languages/top/" . $owner . "/" . $repo . "?style=flat-square) ";
        $md .= "![Repo Size](https://img.shields.io/github/repo-size/" . $owner . "/" . $repo . "?style=flat-square) ";
        $md .= "![Issues](https://img.shields.io/github/issues/" . $owner . "/" . $repo . "?style=flat-square) ";
        $md .= "![Stars](https://img.shields.io/github/stars/" . $owner . "/" . $repo . "?style=flat-square) ";

        if ($description) {
            $md .= "\n\n" . $description . "\n\n";
        } else {
            $md .= "\n\n";
        }

        $md .= "<p align=\"center\">\n";
        $md .= "  *Developed with the software and tools below.*\n";
        $md .= "</p>\n";

        $md .= "<p align=\"center\">\n";
        $md .= "  [![contributors](https://img.shields.io/github/contributors/" . $owner . "/" . $repo . "?style=flat-square)](https://github.com/" . $owner . "/" . $repo . "/graphs/contributors)\n";
        $md .= "  [![forks](https://img.shields.io/github/forks/" . $owner . "/" . $repo . "?style=flat-square)](https://github.com/" . $owner . "/" . $repo . "/network/members)\n";
        $md .= "  [![stars](https://img.shields.io/github/stars/" . $owner . "/" . $repo . "?style=flat-square)](https://github.com/" . $owner . "/" . $repo . "/stargazers)\n";
        $md .= "</p>\n";

        $md .= "</div>\n\n";

        $md .= "---\n\n";

        $md .= "## Table of Contents\n\n";
        $md .= "- [Languages](#languages)\n";
        $md .= "- [Tech Stack](#tech-stack)\n";
        $md .= "- [Features](#features)\n";
        $md .= "- [Getting Started](#getting-started)\n";
        $md .= "- [Project Structure](#project-structure)\n";
        $md .= "- [Contributing](#contributing)\n";
        $md .= "- [License](#license)\n\n";

        $md .= "## Languages\n\n";

            $totalBytes = array_sum($data['languages']);
            if ($totalBytes > 0) {
                foreach ($data['languages'] as $lang => $bytes) {
                    $percentage = round(($bytes / $totalBytes) * 100, 1);
                    if ($percentage >= 1) {
                         $md .= $this->getTechBadge($lang, $percentage) . " ";
                    }
                }
                $md .= "\n\n";
            }

            $allTech = array_keys($data['frameworks']);
            $allTech = array_unique($allTech);

            if (!empty($allTech)) {
                $md .= "## Tech Stack\n\n";
                foreach ($allTech as $tech) {
                     $md .= $this->getTechBadge($tech) . " ";
                }
                $md .= "\n\n";
            }

        $md .= "## Features\n\n";
        $features = $this->generateFeatures($data);
        foreach ($features as $feature) {
            $md .= "- " . $feature . "\n";
        }
        $md .= "\n";

        $md .= "## Getting Started\n\n";
        $md .= "### Prerequisites\n\n";
        if (isset($data['languages']['PHP'])) $md .= "- PHP 8.0+\n";
        if (isset($data['languages']['JavaScript'])) $md .= "- Node.js\n";
        if (isset($data['languages']['Python'])) $md .= "- Python 3.8+\n";
        if (isset($data['has_docker']) && $data['has_docker']) $md .= "- Docker\n";
        $md .= "\n";

        $md .= "### Installation\n\n";
        $md .= "1. Clone the repository:\n";
        $md .= "```bash\n";
        $md .= "git clone " . $url . "\n";
        $md .= "cd " . $repo . "\n";
        $md .= "```\n\n";

        $step = 2;
        if (!empty($data['has_env'])) {
            $md .= $step++ . ". Configure environment variables:\n```bash\ncp .env.example .env\n```\n\n";
        }
        if (isset($data['frameworks']['Node.js'])) {
            $md .= $step++ . ". Install NPM dependencies:\n```bash\nnpm install\n```\n\n";
        }
        if (isset($data['frameworks']['Composer'])) {
            $md .= $step++ . ". Install PHP dependencies:\n```bash\ncomposer install\n```\n\n";
        }
        if (isset($data['frameworks']['Pip'])) {
            $md .= $step++ . ". Install Python requirements:\n```bash\npip install -r requirements.txt\n```\n\n";
        }
        if (isset($data['has_docker']) && $data['has_docker']) {
            $md .= $step++ . ". Start with Docker:\n```bash\ndocker-compose up -d\n```\n\n";
        }

        $md .= "### Running the App\n\n";
        if (isset($data['frameworks']['Node.js'])) {
             $md .= "```bash\nnpm start\n# or\nnpm run dev\n```\n\n";
        } elseif (isset($data['frameworks']['Laravel'])) {
             $md .= "```bash\nphp artisan serve\n```\n\n";
        } elseif (isset($data['frameworks']['Django'])) {
             $md .= "```bash\npython manage.py runserver\n```\n\n";
        } else {
             $md .= "Check the documentation for specific run commands.\n\n";
        }

        if (isset($data['has_tests']) && $data['has_tests']) {
            $md .= "## Running Tests\n\n";
            $md .= "To run the test suite:\n\n";
            $md .= "```bash\n";
            if (isset($data['frameworks']['Jest'])) {
                $md .= "npm test\n";
            } elseif (isset($data['frameworks']['Composer'])) {
                $md .= "composer test\n# or\n./vendor/bin/phpunit\n";
            } elseif (isset($data['frameworks']['Django'])) {
                $md .= "python manage.py test\n";
            } else {
                $md .= "# Run your test command here\n";
            }
            $md .= "```\n\n";
        }

        $md .= "## Project Structure\n\n";
        $md .= "```text\n";
        $md .= $this->generateTree($data['structure']);
        $md .= "```\n\n";

        $md .= "## Contributing\n\n";
        $md .= "Contributions are welcome! Please feel free to submit a Pull Request.\n\n";

        $md .= "## License\n\n";
        $md .= $data['license'] . "\n";

        return $md;
    }

    public function simpleMarkdownToHtml($markdown) {
        $markdown = str_replace(['<div align="center">', '</div>'], ['###DIVSTART###', '###DIVEND###'], $markdown);
        $markdown = str_replace(['<p align="center">', '</p>'], ['###PSTART###', '###PEND###'], $markdown);

        $imgTags = [];
        $markdown = preg_replace_callback('/<img[^>]+>/', function($matches) use (&$imgTags) {
             $id = '###IMGTAG' . count($imgTags) . '###';
             $imgTags[$id] = $matches[0];
             return $id;
        }, $markdown);

        $codeBlocks = [];
        $markdown = preg_replace_callback('/```(\w+)?\s*\n(.*?)```/s', function($matches) use (&$codeBlocks) {
            $id = '###CODEBLOCK' . count($codeBlocks) . '###';
            $codeBlocks[$id] = '<pre><code class="language-' . ($matches[1] ?? 'text') . '">' . htmlspecialchars($matches[2]) . '</code></pre>';
            return $id;
        }, $markdown);

        $markdown = preg_replace_callback('/`([^`]+)`/', function($matches) use (&$codeBlocks) {
            $id = '###INLINECODE' . count($codeBlocks) . '###';
            $codeBlocks[$id] = '<code>' . htmlspecialchars($matches[1]) . '</code>';
            return $id;
        }, $markdown);

        $otherHtmlTags = [];
        $markdown = preg_replace_callback('/<[^>]+>/', function($matches) use (&$otherHtmlTags) {
            $id = '###HTMLTAG' . count($otherHtmlTags) . '###';
            $otherHtmlTags[$id] = $matches[0];
            return $id;
        }, $markdown);

        $html = htmlspecialchars($markdown);

        $html = str_replace(
            ['###DIVSTART###', '###DIVEND###', '###PSTART###', '###PEND###'],
            ['<div align="center">', '</div>', '<p align="center">', '</p>'],
            $html
        );
        foreach ($imgTags as $id => $tag) {
            $html = str_replace($id, $tag, $html);
        }
        foreach ($otherHtmlTags as $id => $tag) {
            $html = str_replace($id, $tag, $html);
        }

        $html = preg_replace('/!\[([^\]]*)\]\(([^)]+)\)/', '<img src="$2" alt="$1" style="max-width:100%;">', $html);

        $html = preg_replace('/\[([^\]]+)\]\(([^)]+)\)/', '<a href="$2" target="_blank">$1</a>', $html);

        $html = preg_replace('/^# (.*?)$/m', '<h1>$1</h1>', $html);
        $html = preg_replace('/^## (.*?)$/m', '<h2>$1</h2>', $html);
        $html = preg_replace('/^### (.*?)$/m', '<h3>$1</h3>', $html);
        $html = preg_replace('/^#### (.*?)$/m', '<h4>$1</h4>', $html);

        $html = preg_replace('/\*\*([^*]+)\*\*/', '<strong>$1</strong>', $html);
        $html = preg_replace('/\*([^*]+)\*/', '<em>$1</em>', $html);

        $html = preg_replace('/^&gt; (.*?)$/m', '<blockquote>$1</blockquote>', $html);

        $html = preg_replace('/^---$/m', '<hr>', $html);

        $html = preg_replace('/^\s*-\s+(.*?)$/m', '<li>$1</li>', $html);

        $html = preg_replace_callback('/(<li>.*?<\/li>\s*)+/s', function($matches) {
            return "<ul>\n" . trim($matches[0]) . "\n</ul>";
        }, $html);

        $html = preg_replace('/^\s*\d+\.\s+(.*?)$/m', '<li data-ordered>$1</li>', $html);

        $html = preg_replace_callback('/(<li data-ordered>.*?<\/li>\s*)+/s', function($matches) {
             $content = str_replace(' data-ordered', '', $matches[0]);
             return "<ol>\n" . trim($content) . "\n</ol>";
        }, $html);

        $html = preg_replace('/\n\s*\n/', '<br><br>', $html);

        foreach ($codeBlocks as $id => $block) {
            $html = str_replace($id, $block, $html);
        }

        $html = preg_replace('/(<br><br>\s*)+<pre/', '<pre', $html);
        $html = preg_replace('/<\/pre>(\s*<br><br>)+/', '</pre>', $html);

        return $html;
    }

    public function cleanup() {
        if ($this->tempPath && is_dir($this->tempPath)) {
            $this->rrmdir($this->tempPath);
        }
    }

    private function rrmdir($dir) {
        if (is_dir($dir)) {
            $objects = scandir($dir);
            foreach ($objects as $object) {
                if ($object != "." && $object != "..") {
                    if (is_dir($dir . "/" . $object))
                        $this->rrmdir($dir . "/" . $object);
                    else {
                        @chmod($dir . "/" . $object, 0777);
                        @unlink($dir . "/" . $object);
                    }
                }
            }
            @rmdir($dir);
        }
    }
}
