<?php

require_once 'functions.php';
require_once 'ReadmeGenerator.php';

$config = require 'config.php';
$generator = new ReadmeGenerator($config);

register_shutdown_function(function() use ($generator) {
    $generator->cleanup();
});

$error = null;
$aiError = null;
$readmeContent = null;
$previewHtml = null;
$repoName = '';
$ownerName = '';

$captchaQuestion = generateCaptcha();

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

$autoMode = false;
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

if (($ownerName && $repoName) && !$error && !$autoMode) {
    if (!verifyCaptcha($config)) {
        $error = "Please complete the reCAPTCHA verification.";
        $captchaQuestion = generateCaptcha();
    } else {
    try {
        $ownerName = $generator->sanitize($ownerName);
        $repoName = $generator->sanitize($repoName);

        $repoUrl = $generator->findRepoUrl($ownerName, $repoName);

        if (!$repoUrl) {
            $error = "Repository not found on GitHub ($ownerName/$repoName).";
        } else {
            $generator->checkRepoSize($ownerName, $repoName);
            $tempPath = $generator->cloneRepo($repoUrl);
            $analysis = $generator->analyze($tempPath);

            $customImage = isset($_POST['custom_image']) && !empty($_POST['custom_image']) ? $_POST['custom_image'] : null;

            $manualLicense = isset($_POST['license']) && !empty($_POST['license']) ? $_POST['license'] : null;
            if ($manualLicense) {
                $analysis['license'] = $manualLicense;
            }

            $useAI = isset($_POST['use_ai']) && $_POST['use_ai'] === '1';
            $language = $_POST['language'] ?? 'en';

            if ($useAI) {
                try {
                    set_time_limit(0);
                    $sourceContent = $generator->collectSourceContent($tempPath);
                    $readmeContent = $generator->generateMarkdownAI($ownerName, $repoName, $analysis, $repoUrl, $customImage, $sourceContent, $language);
                } catch (Exception $e) {
                    $aiError = $e->getMessage();
                    $readmeContent = $generator->generateMarkdown($ownerName, $repoName, $analysis, $repoUrl, $customImage, $language);
                }
            } else {
                $readmeContent = $generator->generateMarkdown($ownerName, $repoName, $analysis, $repoUrl, $customImage, $language);
            }

            $previewHtml = $generator->simpleMarkdownToHtml($readmeContent);

            $generator->cleanup();
        }
    } catch (Exception $e) {
        $error = "Error: " . $e->getMessage();
        $generator->cleanup();
    }
    }
}

$defaultRepoUrl = '';
if ($autoMode && $ownerName && $repoName) {
    $defaultRepoUrl = "https://github.com/$ownerName/$repoName";
} elseif (isset($_POST['repo_url'])) {
    $defaultRepoUrl = $_POST['repo_url'];
}

?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>README Creator <?php echo $repoName ? "- $repoName" : ""; ?></title>
    <link rel="stylesheet" href="/style.css?v=<?php echo filemtime(__DIR__ . '/style.css'); ?>">
    <link rel="preconnect" href="https://fonts.googleapis.com">
    <link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>
    <link href="https://fonts.googleapis.com/css2?family=Inter:wght@400;500;600;800&family=JetBrains+Mono&display=swap" rel="stylesheet">
    <?php if ($config['captcha']['enabled']): ?>
    <script src="https://www.google.com/recaptcha/api.js" async defer></script>
    <?php endif; ?>
    <script>
        <?php if ($config['captcha']['enabled']): ?>
        function onCaptchaSolved() {
            var err = document.getElementById('captchaError');
            if (err) err.style.display = 'none';
            var box = document.querySelector('.g-recaptcha');
            if (box && box.style) box.style.border = 'none';
        }

        function onModalCaptchaSolved() {
            document.getElementById('modalGenerateBtn').disabled = false;
            document.getElementById('modalCaptchaError').style.display = 'none';
        }
        <?php endif; ?>

        function submitForm() {
            var form = document.querySelector('form');
            if (!form) return;
            var useAI = document.querySelector('[name="use_ai"]')?.checked;
            document.querySelector('.loading-text').textContent = useAI
                ? 'Generating with AI... This may take a moment.'
                : 'Analyzing Repository...';
            document.querySelector('.loading-overlay').classList.remove('hidden');

            <?php if ($autoMode && $config['captcha']['enabled']): ?>
            var inp = document.querySelector('input[name="g-recaptcha-response"]');
            if (!inp) {
                inp = document.createElement('input');
                inp.type = 'hidden';
                inp.name = 'g-recaptcha-response';
                form.appendChild(inp);
            }
            inp.value = grecaptcha.getResponse();
            <?php endif; ?>

            form.submit();
        }

        document.addEventListener('DOMContentLoaded', function() {
            <?php if ($autoMode): ?>
            <?php if ($config['captcha']['enabled']): ?>
            var modal = document.getElementById('captchaModal');
            if (modal) modal.classList.remove('hidden');
            document.getElementById('modalGenerateBtn').addEventListener('click', function() {
                if (!grecaptcha || !grecaptcha.getResponse()) {
                    document.getElementById('modalCaptchaError').style.display = 'block';
                    return;
                }
                modal.classList.add('hidden');
                submitForm();
            });
            <?php else: ?>
            submitForm();
            <?php endif; ?>
            <?php endif; ?>

            var form = document.querySelector('form');
            if (!form) return;

            form.addEventListener('submit', function(e) {
                <?php if ($config['captcha']['enabled'] && !$autoMode): ?>
                if (!grecaptcha || !grecaptcha.getResponse()) {
                    e.preventDefault();
                    var err = document.getElementById('captchaError');
                    if (!err) {
                        err = document.createElement('div');
                        err.id = 'captchaError';
                        err.style.cssText = 'color:var(--mac-red);font-size:0.8rem;margin-bottom:12px;';
                        var box = document.querySelector('.g-recaptcha');
                        if (box && box.parentNode) box.parentNode.insertBefore(err, box.nextSibling);
                    }
                    err.textContent = 'Please complete the reCAPTCHA.';
                    var box = document.querySelector('.g-recaptcha');
                    if (box) {
                        box.style.border = '1px solid var(--mac-red)';
                        box.style.borderRadius = '3px';
                        box.style.padding = '2px';
                        box.style.animation = 'none';
                        void box.offsetHeight;
                        box.style.animation = 'shake 0.4s ease';
                    }
                    return;
                }
                <?php endif; ?>

                submitForm();
            });
        });

        function copyMarkdown() {
            var textarea = document.querySelector('.code-editor');
            if (!textarea) return;
            textarea.select();
            document.execCommand('copy');
            var btn = document.getElementById('copyBtn') || document.getElementById('copyBtnMobile');
            if (btn) {
                var originalText = btn.innerText;
                btn.innerText = 'Copied!';
                setTimeout(function() { btn.innerText = originalText; }, 2000);
            }
        }

        function switchTab(tab) {
            document.querySelectorAll('.tab-btn').forEach(function(b) { b.classList.remove('active'); });
            document.querySelectorAll('.tab-content').forEach(function(c) { c.classList.remove('active'); });
            var btn = document.querySelector('[data-tab="' + tab + '"]');
            if (btn) btn.classList.add('active');
            var content = document.getElementById(tab + '-content');
            if (content) content.classList.add('active');
        }

        function escapeHtml(str) {
            var div = document.createElement('div');
            div.appendChild(document.createTextNode(str));
            return div.innerHTML;
        }
    </script>
</head>
<body>
    <div class="loading-overlay <?php echo $error ? '' : 'hidden'; ?>">
        <div class="loading-spinner"></div>
        <div class="loading-text"><?php echo $autoMode ? 'Generating with AI...' : ($error ? htmlspecialchars($error) : 'Analyzing Repository...'); ?></div>
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
                <section class="quick-info">
                    <div class="info-card">
                        <div class="info-icon">⚡</div>
                        <div class="info-body">
                            <strong>Quick mode</strong> — Just append <code>/owner/repo</code> to the URL:<br>
                            <code class="example-url">readme.matelex.it/matelex0/xenoai</code>
                        </div>
                    </div>
                </section>

                <section class="input-wrapper">
                    <form method="POST" action="/">
                        <div class="input-group-vertical">
                            <div class="field-group">
                                <label class="field-label">Repository URL</label>
                                <div class="input-wrapper">
                                    <input type="text" name="repo_url" placeholder="https://github.com/username/repository" required value="<?php echo htmlspecialchars($defaultRepoUrl); ?>">
                                </div>
                            </div>

                            <details class="advanced-toggle">
                                <summary>Advanced Options</summary>
                                <div class="advanced-body">

                                <div class="field-group">
                                    <label class="field-label">Custom Header Image</label>
                                    <div class="input-wrapper">
                                        <input type="text" name="custom_image" placeholder="https://example.com/your-image.png" value="<?php echo isset($_POST['custom_image']) ? htmlspecialchars($_POST['custom_image']) : ''; ?>">
                                    </div>
                                    <p class="field-hint">Overrides the auto-generated Socialify header image. Provide a direct URL to any image (PNG, JPG, GIF). The image will be displayed at the top of your README, centered and 500px wide.</p>
                                    <div class="image-example">
                                        <span class="example-label">Default auto-generated:</span>
                                        <img src="https://socialify.git.ci/alex1dev0/readme-creator/image?description=1&font=Inter&language=1&name=1&owner=1&pattern=Transparent&theme=Auto" alt="Socialify example" class="example-img">
                                        <span class="example-label">With custom image:</span>
                                        <div class="example-custom-preview">Your image here → <code class="example-url">https://example.com/your-image.png</code></div>
                                    </div>
                                </div>

                                <div class="field-group">
                                    <label class="field-label">License Override</label>
                                    <div class="select-wrapper">
                                        <select name="license">
                                            <option value="">-- Detect Automatically --</option>
                                            <option value="MIT License">MIT License</option>
                                            <option value="Apache License 2.0">Apache License 2.0</option>
                                            <option value="GNU General Public License v3.0">GNU GPL v3.0</option>
                                            <option value="BSD 3-Clause License">BSD 3-Clause</option>
                                        </select>
                                    </div>
                                </div>

                                <div class="field-group">
                                    <label class="field-label">Language</label>
                                    <div class="select-wrapper">
                                        <select name="language">
                                            <option value="en" <?php echo (!isset($_POST['language']) || $_POST['language'] === 'en') ? 'selected' : ''; ?>>English (auto)</option>
                                            <option value="it" <?php echo (isset($_POST['language']) && $_POST['language'] === 'it') ? 'selected' : ''; ?>>Italiano</option>
                                            <option value="es" <?php echo (isset($_POST['language']) && $_POST['language'] === 'es') ? 'selected' : ''; ?>>Español</option>
                                            <option value="fr" <?php echo (isset($_POST['language']) && $_POST['language'] === 'fr') ? 'selected' : ''; ?>>Français</option>
                                            <option value="de" <?php echo (isset($_POST['language']) && $_POST['language'] === 'de') ? 'selected' : ''; ?>>Deutsch</option>
                                            <option value="pt" <?php echo (isset($_POST['language']) && $_POST['language'] === 'pt') ? 'selected' : ''; ?>>Português</option>
                                            <option value="nl" <?php echo (isset($_POST['language']) && $_POST['language'] === 'nl') ? 'selected' : ''; ?>>Nederlands</option>
                                            <option value="ru" <?php echo (isset($_POST['language']) && $_POST['language'] === 'ru') ? 'selected' : ''; ?>>Русский</option>
                                            <option value="zh" <?php echo (isset($_POST['language']) && $_POST['language'] === 'zh') ? 'selected' : ''; ?>>中文</option>
                                            <option value="ja" <?php echo (isset($_POST['language']) && $_POST['language'] === 'ja') ? 'selected' : ''; ?>>日本語</option>
                                            <option value="ko" <?php echo (isset($_POST['language']) && $_POST['language'] === 'ko') ? 'selected' : ''; ?>>한국어</option>
                                            <option value="ar" <?php echo (isset($_POST['language']) && $_POST['language'] === 'ar') ? 'selected' : ''; ?>>العربية</option>
                                            <option value="tr" <?php echo (isset($_POST['language']) && $_POST['language'] === 'tr') ? 'selected' : ''; ?>>Türkçe</option>
                                        </select>
                                    </div>
                                    <p class="field-hint">Leave as English (auto) for AI to generate in English by default.</p>
                                </div>

                                <label class="checkbox-label">
                                    <input type="checkbox" name="use_ai" value="1" <?php echo (isset($_POST['use_ai']) || $autoMode) ? 'checked' : ''; ?>>
                                    <span class="checkbox-text">Generate with AI <span class="badge-ai">AI</span></span>
                                </label>
                                <p class="field-hint" style="margin-top: 6px;">AI generation produces richer, more accurate READMEs using repo source code analysis. Uncheck for a fast template-based version.</p>

                                </div>
                            </details>

                            <?php if ($config['captcha']['enabled'] && !$autoMode): ?>
                            <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($config['captcha']['site_key']); ?>" data-callback="onCaptchaSolved" data-expired-callback="onCaptchaSolved" style="margin-bottom: 16px;"></div>
                            <?php endif; ?>

                            <button type="submit" class="btn primary btn-block">Generate README</button>
                        </div>
                    </form>
                </section>

                <?php if ($autoMode && $config['captcha']['enabled']): ?>
                <div class="modal-overlay hidden" id="captchaModal">
                    <div class="modal-box">
                        <h3>Verify you're human</h3>
                        <p style="margin: 8px 0 16px; color: var(--text-secondary); font-size: 0.85rem;">Complete the captcha to generate the README for <strong><?php echo htmlspecialchars($ownerName . '/' . $repoName); ?></strong></p>
                        <div class="g-recaptcha" data-sitekey="<?php echo htmlspecialchars($config['captcha']['site_key']); ?>" data-callback="onModalCaptchaSolved"></div>
                        <div id="modalCaptchaError" style="color:var(--mac-red);font-size:0.8rem;margin-top:8px;display:none;">Please complete the reCAPTCHA.</div>
                        <button class="btn primary" id="modalGenerateBtn" disabled style="margin-top: 16px; width: 100%;">Generate README</button>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ($error && !$autoMode): ?>
                    <div class="card" style="margin-top: 20px; border-color: var(--mac-red); color: var(--mac-red); text-align: center;">
                        <?php echo htmlspecialchars($error); ?>
                    </div>
                <?php endif; ?>

                <?php if ($aiError): ?>
                    <div class="card" style="margin-top: 20px; border-color: var(--mac-yellow); color: var(--mac-yellow); text-align: center; font-size: 13px;">
                        AI generation unavailable: <?php echo htmlspecialchars($aiError); ?>. Falling back to standard generation.
                    </div>
                <?php endif; ?>

                <div id="async-results" class="<?php echo ($autoMode && !$readmeContent) ? 'hidden' : ''; ?>">
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
                </div>
            </main>
        </div>
    </div>
    <div style="position:fixed;bottom:16px;right:16px;z-index:500">
        <a href="https://github.com/matelex0/readme-creator" target="_blank" rel="noopener" style="display:flex;align-items:center;gap:6px;padding:8px 14px;border-radius:8px;background:var(--bg-window);border:1px solid var(--border);color:var(--text-secondary);text-decoration:none;font-size:0.8rem;transition:all 0.15s" onmouseover="this.style.color='var(--text-primary)';this.style.borderColor='var(--accent-blue)'" onmouseout="this.style.color='var(--text-secondary)';this.style.borderColor='var(--border)'">
            <svg width="16" height="16" viewBox="0 0 16 16" fill="currentColor"><path d="M8 0C3.58 0 0 3.58 0 8c0 3.54 2.29 6.53 5.47 7.59.4.07.55-.17.55-.38 0-.19-.01-.82-.01-1.49-2.01.37-2.53-.49-2.69-.94-.09-.23-.48-.94-.82-1.13-.28-.15-.68-.52-.01-.53.63-.01 1.08.58 1.23.82.72 1.21 1.87.87 2.33.66.07-.52.28-.87.51-1.07-1.78-.2-3.64-.89-3.64-3.95 0-.87.31-1.59.82-2.15-.08-.2-.36-1.02.08-2.12 0 0 .67-.21 2.2.82.64-.18 1.32-.27 2-.27.68 0 1.36.09 2 .27 1.53-1.04 2.2-.82 2.2-.82.44 1.1.16 1.92.08 2.12.51.56.82 1.27.82 2.15 0 3.07-1.87 3.75-3.65 3.95.29.25.54.73.54 1.48 0 1.07-.01 1.93-.01 2.2 0 .21.15.46.55.38A8.013 8.013 0 0016 8c0-4.42-3.58-8-8-8z"/></svg>
            <span>Open Source</span>
        </a>
    </div>
</body>
</html>
