<?php

require_once 'functions.php';
require_once 'ReadmeGenerator.php';

header('Content-Type: application/json');

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Method not allowed']);
    exit;
}

$owner = $_POST['owner'] ?? '';
$repo = $_POST['repo'] ?? '';

if (!$owner || !$repo) {
    http_response_code(400);
    echo json_encode(['error' => 'Missing owner or repo']);
    exit;
}

$config = require 'config.php';

if (!verifyCaptcha($config)) {
    http_response_code(403);
    echo json_encode(['error' => 'Please complete the reCAPTCHA verification.']);
    exit;
}

$generator = new ReadmeGenerator($config);

register_shutdown_function(function() use ($generator) {
    $generator->cleanup();
});

try {
    $owner = $generator->sanitize($owner);
    $repo = $generator->sanitize($repo);

    $repoUrl = $generator->findRepoUrl($owner, $repo);

    if (!$repoUrl) {
        throw new Exception("Repository not found on GitHub ($owner/$repo).");
    }

    $generator->checkRepoSize($owner, $repo);
    set_time_limit(300);
    $tempPath = $generator->cloneRepo($repoUrl);
    $analysis = $generator->analyze($tempPath);
    $sourceContent = $generator->collectSourceContent($tempPath);

    $language = $_POST['language'] ?? 'en';

    $readmeContent = $generator->generateMarkdownAI($owner, $repo, $analysis, $repoUrl, null, $sourceContent, $language);
    $previewHtml = $generator->simpleMarkdownToHtml($readmeContent);

    $generator->cleanup();

    ob_start();
    ?>
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
    <?php
    $html = ob_get_clean();

    echo json_encode([
        'success' => true,
        'html' => $html,
        'markdown' => $readmeContent,
    ]);

} catch (Exception $e) {
    $generator->cleanup();
    $msg = $e->getMessage();
    if (strpos($msg, 'not found') !== false) {
        http_response_code(404);
    } else {
        http_response_code(500);
    }
    echo json_encode(['error' => $msg]);
}
