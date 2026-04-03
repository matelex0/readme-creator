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
            mkdir($this->config['temp_dir'], 0777, true);
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
            if (strpos($filename, 'test') !== false) $data['has_tests'] = true;
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

    public function generateMarkdown($owner, $repo, $data, $url, $customImage = null) {
        $description = isset($data['description']) ? $data['description'] : "";
        $author = $data['author'] ? $data['author'] : $owner;

        $md = "<div align=\"center\">\n";
        if ($customImage) {
            $md .= "  <img src=\"" . $customImage . "\" alt=\"" . $repo . "\" width=\"500\" />\n";
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

        $html = htmlspecialchars($markdown);

        $html = str_replace(
            ['###DIVSTART###', '###DIVEND###', '###PSTART###', '###PEND###'],
            ['<div align="center">', '</div>', '<p align="center">', '</p>'],
            $html
        );
        foreach ($imgTags as $id => $tag) {
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
