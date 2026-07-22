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

**Readme Creator** is a powerful PHP-based tool designed to automatically generate comprehensive and professional `README.md` files for your GitHub repositories. By analyzing your codebase, it detects languages, frameworks, and project structure to create a ready-to-use documentation template, saving you time and ensuring your projects always look their best.

## Languages

![PHP](https://img.shields.io/badge/PHP-777BB4?style=for-the-badge&logo=php&logoColor=white) ![CSS](https://img.shields.io/badge/CSS-1572B6?style=for-the-badge&logo=css3&logoColor=white) ![HTML](https://img.shields.io/badge/HTML-E34F26?style=for-the-badge&logo=html5&logoColor=white)

## Features

- 🔍 **Automatic Analysis**: Scans your repository to detect programming languages, frameworks, and tools.
- 🤖 **AI-Powered Generation**: Optional AI generation via Groq for richer, more accurate READMEs.
- 📊 **Language Statistics**: Generates language distribution badges automatically.
- 🌳 **Project Structure**: Visualizes your project's file hierarchy in a clean tree format.
- 🎨 **Customizable**: Supports custom header images via Socialify and manual license selection.
- ⚡ **Instant Generation**: Just enter a GitHub repository URL or path to generate the README.
- 📋 **One-Click Copy**: Easily copy the generated Markdown or download it as a file.
- 🔗 **Smart Links**: Automatically generates links for contributors, issues, and license.

## Getting Started

### Prerequisites

To run this project, you need:
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

2. Configure your web server (Apache/Nginx) to point to the project directory.

3. Ensure the script has write permissions to create the temporary directory:
```bash
chmod 755 .
```

4. (Optional) Set up AI generation by creating a `.env` file:
```bash
echo 'GROQ_API_KEY=your_groq_api_key_here' > .env
```
Get an API key at [console.groq.com](https://console.groq.com).

## Usage

1. Open the application in your web browser (e.g., `http://localhost/readme-creator`).
2. Enter a GitHub repository URL (e.g., `https://github.com/username/repo`) or use the short format `username/repo` in the URL path.
3. Check **Generate with AI** for AI-enhanced README generation (requires Groq API key).
4. Click **Generate**.
5. Review the generated README, then click **Copy Markdown** or **Download**.

> **Tip:** Replace `github.com` with `readme.matelex.it` in any repo URL to auto-generate instantly with AI (e.g., `readme.matelex.it/username/repo`).

## Project Structure

```text
├── config.php           # Configuration settings (temp dir, ignored files, Groq)
├── index.php            # Main application entry point and UI
├── ReadmeGenerator.php  # Core logic for repo analysis, AI, and markdown generation
├── style.css            # Styling for the web interface
├── .env.example         # Environment template (copy to .env and add your Groq API key)
├── .gitignore           # Git ignore rules
├── .htaccess            # Apache rewrite rules
└── README.md            # Project documentation
```

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
Created by **Alex1Dev**

