<div align="center">
  <img src="https://socialify.git.ci/alex1dev0/readme-creator/image?description=1&font=Inter&language=1&name=1&owner=1&pattern=Transparent&theme=Auto" alt="readme-creator" width="500" />
</div>

<div align="center">

![License](https://img.shields.io/badge/license-MIT%20License-blue.svg?style=flat-square) ![Top Language](https://img.shields.io/github/languages/top/alex1dev0/readme-creator?style=flat-square) ![Repo Size](https://img.shields.io/github/repo-size/alex1dev0/readme-creator?style=flat-square) ![Issues](https://img.shields.io/github/issues/alex1dev0/readme-creator?style=flat-square) ![Stars](https://img.shields.io/github/stars/alex1dev0/readme-creator?style=flat-square)

<p align="center">
  <em>Instantly generate beautiful README files for your GitHub projects.</em>
</p>
<p align="center">
  <a href="https://github.com/alex1dev0/readme-creator/graphs/contributors">
    <img src="https://img.shields.io/github/contributors/alex1dev0/readme-creator?style=flat-square" alt="contributors" />
  </a>
  <a href="https://github.com/alex1dev0/readme-creator/network/members">
    <img src="https://img.shields.io/github/forks/alex1dev0/readme-creator?style=flat-square" alt="forks" />
  </a>
  <a href="https://github.com/alex1dev0/readme-creator/stargazers">
    <img src="https://img.shields.io/github/stars/alex1dev0/readme-creator?style=flat-square" alt="stars" />
  </a>
</p>
</div>

---

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

## Overview

**Readme Creator** is a powerful PHP-based web tool that automatically generates comprehensive, professional `README.md` files for any public GitHub repository. It clones repositories on-the-fly, performs deep static analysis to detect languages, frameworks, dependencies, and project structure, then produces a polished ready-to-use documentation template. An optional AI mode (powered by Groq or Cerebras) enriches the output with contextual descriptions, feature highlights, and natural language tailored to your project's actual source code.

## Languages

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white) ![CSS](https://img.shields.io/badge/CSS-1572B6?style=for-the-badge&logo=css3&logoColor=white) ![HTML](https://img.shields.io/badge/HTML-E34F26?style=for-the-badge&logo=html5&logoColor=white) ![JavaScript](https://img.shields.io/badge/JavaScript-F7DF1E?style=for-the-badge&logo=javascript&logoColor=black)

## Features

- 🔍 **Automatic Analysis**: Scans any GitHub repository to detect programming languages, frameworks, tools, and project structure.
- 🤖 **AI-Powered Generation**: Optional AI generation via Groq (Llama 3.3 70B) or Cerebras for richer, more accurate READMEs.
- 🌐 **Multi-Language Support**: Generate READMEs in 13 languages — English, Italian, Spanish, French, German, Portuguese, Dutch, Russian, Chinese, Japanese, Korean, Arabic, and Turkish.
- 📊 **Language Statistics**: Detects language distribution and generates percentage badges automatically.
- 🌳 **Project Structure**: Visualizes your project's file hierarchy in a clean ASCII tree format.
- 🎨 **Customizable**: Supports custom header images, manual license selection, and framework-aware feature descriptions.
- 🔗 **Smart URL Routing**: Just append `/owner/repo` to the base URL for instant generation — no form required.
- ⚡ **Instant Generation**: Enter a GitHub URL or use the short path format to generate a README in seconds.
- 📋 **One-Click Copy & Download**: Copy the generated Markdown to clipboard or download it as a file.
- 🔒 **reCAPTCHA Protection**: Optional Google reCAPTCHA v2 to prevent abuse.
- 📡 **REST API**: Dedicated `api.php` endpoint for programmatic README generation from external tools.
- 🛡️ **Repository Safety**: Checks repository size before cloning (max 250 MB) and auto-cleans temp data after generation.

## Getting Started

### Prerequisites

- **PHP 8.0** or higher
- **Git** installed and accessible from the command line
- PHP **cURL** extension (`php-curl`)
- PHP `exec()` function enabled (for running Git commands)

### Installation

1. Clone the repository:
```bash
git clone https://github.com/alex1dev0/readme-creator.git
cd readme-creator
```

2. Configure your web server (Apache/Nginx) to point to the project directory — `.htaccess` (Apache) and `confignginx.txt` (Nginx) are included.

3. Ensure the script has write permissions for the temp directory:
```bash
chmod 755 .
```

4. (Optional) Set up AI generation by creating a `.env` file:
```bash
echo 'GROQ_API_KEY=your_key_here
CEREBRAS_API_KEY=your_key_here' > .env
```
Get API keys at [console.groq.com](https://console.groq.com) and [cloud.cerebras.ai](https://cloud.cerebras.ai).

5. (Optional) Set up reCAPTCHA v2 in your `.env` file — get free keys at [google.com/recaptcha/admin](https://www.google.com/recaptcha/admin).

6. Install PHP cURL extension (required for AI generation):
```bash
sudo apt install php-curl
sudo systemctl restart apache2
```

## Usage

### Web Interface

1. Open the application in your web browser (e.g., `http://localhost/readme-creator`).
2. Enter a GitHub repository URL (e.g., `https://github.com/username/repo`).
3. (Optional) Check **Generate with AI** for AI-enhanced README generation (requires an API key).
4. Select the output **language** from the Advanced Options (English is default).
5. Click **Generate**.
6. Review the generated README — switch between **Preview** and **Markdown Code** tabs, then **Copy** or **Download**.

### Quick URL Mode

Replace `github.com` with `readme.matelex.it` in any repo URL to auto-generate instantly:
```
readme.matelex.it/username/repo
```

### REST API

Send a POST request to `api.php` for programmatic generation:

```bash
curl -X POST https://your-domain.com/api.php \
  -d "owner=username" \
  -d "repo=repository" \
  -d "language=en" \
  -d "g-recaptcha-response=...your_token..."
```

Returns JSON with `markdown`, `html` preview, or an `error` message.

### AI Providers

The default AI provider is **Cerebras** (faster, larger context). To switch to **Groq**, set `AI_PROVIDER=groq` in your `.env` file. Both providers are configured in `config.php` with separate API keys.

## Project Structure

```text
├── .env.example         # Environment template (API keys, reCAPTCHA config)
├── .htaccess            # Apache rewrite rules (pretty URLs)
├── api.php              # REST API endpoint for programmatic generation
├── config.php           # Configuration (providers, AI, CAPTCHA, ignore lists)
├── confignginx.txt      # Nginx configuration example
├── functions.php        # Helper functions (session, CAPTCHA verify)
├── index.php            # Main application entry point and UI
├── ReadmeGenerator.php  # Core logic: repo analysis, AI calls, markdown rendering
├── style.css            # Dark-theme UI styling
└── temp_repos/          # Temporary cloned repositories (auto-cleaned)
```

- `index.php` — Main entry point with macOS-style UI, form handling, and auto-mode URL routing.
- `ReadmeGenerator.php` — Repository analysis (language/framework detection), AI integration (Groq/Cerebras), template-based and AI-powered markdown generation, and markdown-to-HTML preview rendering.
- `config.php` — Central configuration for Git providers, AI settings, reCAPTCHA keys, ignored extensions/directories, and repository size limits.
- `api.php` — JSON REST API for remote README generation with CAPTCHA validation.
- `functions.php` — Session management and Google reCAPTCHA v2 verification.
- `style.css` — Full dark theme inspired by macOS window aesthetics, responsive split-view layout for desktop and mobile.

## Contributing

Contributions are welcome! Please feel free to submit a Pull Request.

1. Fork the project
2. Create your feature branch (`git checkout -b feature/AmazingFeature`)
3. Commit your changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

## License

Distributed under the MIT License. See `LICENSE` for more information.

---

Created by **Matelex**