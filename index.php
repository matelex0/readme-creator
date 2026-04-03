<?php

// Made by alex1dev - https://alex1dev.xyz
// File index.php - Main file

require_once 'config.php';
require_once 'ReadmeGenerator.php';

$config = require 'config.php';
$generator = new ReadmeGenerator($config);

$error = null;
$readmeContent = null;
$previewHtml = null;
$repoName = '';
$ownerName = '';

if (isset($_POST['download']) && isset($_POST['markdown_content'])) {
    $filename = 'README.md';
    header('Content-Type: text/markdown');
    header('Content-Disposition: attachment; filename="' . $filename . '"');
    echo $_POST['markdown_content'];
    exit;
}

$requestPath = parse_url($_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH) ?? '/';
$requestPath = trim($requestPath, '/');
$parts = array_values(array_filter(explode('/', $requestPath), static function ($part) {
    return $part !== '';
}));

if (count($parts) >= 2 && $parts[0] !== 'index.php') {
    $ownerName = rawurldecode($parts[0]);
    $repoName = rawurldecode($parts[1]);
    $repoName = preg_replace('/\.git$/i', '', $repoName);
    $autoMode = true;
} elseif ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['repo_url'])) {
    if (strpos($_POST['repo_url'], 'github.com') === false) {
        $error = "Only GitHub repositories are supported.";
    } else {
        $urlParts = parse_url($_POST['repo_url']);
        $pathParts = explode('/', trim($urlParts['path'] ?? '', '/'));

        if (count($pathParts) >= 2) {
            $ownerName = $pathParts[count($pathParts)-2];
            $repoName = str_replace('.git', '', $pathParts[count($pathParts)-1]);
        } else {
            $error = "Invalid URL. Please enter a complete URL (e.g., https://github.com/user/repo)";
        }
    }
}

if (($ownerName && $repoName) && !$error) {
    try {
        $ownerName = $generator->sanitize($ownerName);
        $repoName = $generator->sanitize($repoName);

        $repoUrl = $generator->findRepoUrl($ownerName, $repoName);

        if (!$repoUrl) {
            $error = "Repository not found on GitHub ($ownerName/$repoName).";
        } else {
            $tempPath = $generator->cloneRepo($repoUrl);
            $analysis = $generator->analyze($tempPath);

            $customImage = isset($_POST['custom_image']) && !empty($_POST['custom_image']) ? $_POST['custom_image'] : null;

            $manualLicense = isset($_POST['license']) && !empty($_POST['license']) ? $_POST['license'] : null;
            if ($manualLicense) {
                $analysis['license'] = $manualLicense;
            }

            $readmeContent = $generator->generateMarkdown($ownerName, $repoName, $analysis, $repoUrl, $customImage);
            $previewHtml = $generator->simpleMarkdownToHtml($readmeContent);

            $generator->cleanup();
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        $generator->cleanup();
    }
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>README Creator <?php echo $repoName ? "- $repoName" : ""; ?></title>
    <link rel="stylesheet" href="/style.css">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;800&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <script>
        function copyMarkdown() {
            const textarea = document.querySelector('.code-editor');
            textarea.select();
            document.execCommand('copy');
            const btn = document.getElementById('copyBtn');
            const originalText = btn.innerText;
            btn.innerText = 'Copied!';
            setTimeout(() => btn.innerText = originalText, 2000);
        }

        function showLoading() {
            document.querySelector('.loading-overlay').classList.remove('hidden');
        }

        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(b => b.classList.remove('active'));
            document.querySelectorAll('.tab-content').forEach(c => c.classList.remove('active'));

            document.querySelector(`[data-tab="${tab}"]`).classList.add('active');
            document.getElementById(`${tab}-content`).classList.add('active');
        }
    </script>
</head>
<body>
    <div class="loading-overlay hidden">
        <div class="loading-spinner"></div>
        <div class="loading-text">Analyzing Repository...</div>
    </div>

    <div class="mac-window">
        <div class="window-titlebar">
            <div class="window-controls">
                <div class="control close"></div>
                <div class="control minimize"></div>
                <div class="control maximize"></div>
            </div>
            <div class="window-title">README Creator</div>
        </div>

        <div class="window-content">
            <header class="hero">
                <h1>Craft the Perfect<br>README in Seconds</h1>
                <p>Instantly generate professional, comprehensive documentation for your GitHub projects using advanced static analysis.</p>
            </header>

            <main>
                <section class="input-wrapper">
                    <form method="POST" action="/" onsubmit="showLoading()">
                        <div class="input-group-vertical">
                            <div class="input-wrapper">
                                <input type="text" name="repo_url" placeholder="https://github.com/username/repository" required value="<?php echo isset($_POST['repo_url']) ? htmlspecialchars($_POST['repo_url']) : ''; ?>">
                            </div>
                            <div class="input-wrapper">
                                <input type="text" name="custom_image" placeholder="Custom Header Image URL (Optional)" value="<?php echo isset($_POST['custom_image']) ? htmlspecialchars($_POST['custom_image']) : ''; ?>">
                            </div>
                            <div class="select-wrapper">
                                <select name="license">
                                    <option value="">-- Detect License Automatically --</option>
                                    <option value="MIT License">MIT License</option>
                                    <option value="Apache License 2.0">Apache License 2.0</option>
                                    <option value="GNU General Public License v3.0">GNU GPL v3.0</option>
                                    <option value="BSD 3-Clause License">BSD 3-Clause</option>
                                </select>
                            </div>
                            <button type="submit" class="btn primary btn-block">Generate README</button>
                        </div>
                    </form>
                </section>

                <?php if ($error): ?>
                    <div class="card" style="margin-top: 20px; border-color: var(--mac-red); color: var(--mac-red); text-align: center;">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($readmeContent): ?>
                    <div class="results-container">
                        <div class="split-view">
                            <div class="split-pane code-pane">
                                <div class="pane-header">
                                    <h3>Markdown Code</h3>
                                    <div class="pane-actions">
                                        <button id="copyBtn" class="btn secondary small" onclick="copyMarkdown()">Copy</button>
                                        <form method="POST" action="/" style="display: inline;">
                                            <input type="hidden" name="markdown_content" value="<?php echo htmlspecialchars($readmeContent); ?>">
                                            <button type="submit" name="download" class="btn primary small">Download</button>
                                        </form>
                                    </div>
                                </div>
                                <textarea class="code-editor" readonly><?php echo htmlspecialchars($readmeContent); ?></textarea>
                            </div>

                            <div class="split-pane preview-pane">
                                <div class="pane-header">
                                    <h3>Preview</h3>
                                </div>
                                <div class="preview-box markdown-body">
                                    <?php echo $previewHtml; ?>
                                </div>
                            </div>
                        </div>

                        <div class="mobile-tabs-view">
                             <div class="tabs">
                                <button class="tab-btn active" data-tab="preview" onclick="switchTab('preview')">Preview</button>
                                <button class="tab-btn" data-tab="code" onclick="switchTab('code')">Markdown Code</button>
                            </div>

                            <div id="preview-content" class="tab-content active">
                                <div class="preview-box markdown-body">
                                    <?php echo $previewHtml; ?>
                                </div>
                            </div>

                            <div id="code-content" class="tab-content">
                                <textarea class="code-editor" readonly><?php echo htmlspecialchars($readmeContent); ?></textarea>
                                <div style="margin-top: 16px; display: flex; gap: 10px;">
                                    <button id="copyBtnMobile" class="btn secondary" onclick="copyMarkdown()">Copy to Clipboard</button>
                                    <form method="POST" action="/" style="display: inline;">
                                        <input type="hidden" name="markdown_content" value="<?php echo htmlspecialchars($readmeContent); ?>">
                                        <button type="submit" name="download" class="btn primary">Download README.md</button>
                                    </form>
                                </div>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </main>
        </div>
    </div>
</body>
</html>
