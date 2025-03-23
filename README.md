## aido-aifix
Command-line utilities for AI-driven development and troubleshooting

- **aido** is an AI assistant you can talk to from the command line. Describe what you want to do in plain language — and it will figure it out *and actually do it* for you. That might mean generating code, installing packages, creating files, running commands, or all of the above.
  
- **aifix** takes a broken command (like a failed compile), analyzes the error, and automatically edits your files or environment and re-runs the command, until the issue is resolved.

## ✨ Features

- ⚡ Just tell `aido` what you want — it makes it happen, step by step
- 📦 Automatically installs missing packages, creates files, edits code
- 🛠️ `aifix` repairs broken CLI commands by editing the relevant code or setup
- 💬 Interactive when needed, but often fully hands-free

# Example
```bash
# Using aido to automate the setup of a small web project
hans@LAPTOP-O1AO16UE:/projects$ aido create a new web project with HTML, CSS, and JavaScript
(...execution output...)
> I have set up a new web project with the files `index.html`, `styles.css`, and `script.js`.

# Displaying the structure of the created project
hans@LAPTOP-O1AO16UE:/projects$ tree new-web-project
new-web-project
├── index.html
├── styles.css
└── script.js

# Intentionally adding a syntax error in JavaScript
hans@LAPTOP-O1AO16UE:/projects/new-web-project$ echo 'consol.log("Test")' >> script.js

# Simulating the error by running the script through Node.js
hans@LAPTOP-O1AO16UE:/projects/new-web-project$ node script.js
(...execution output...)
> ReferenceError: consol is not defined

# Using aifix to automatically resolve the syntax error
hans@LAPTOP-O1AO16UE:/projects/new-web-project$ aifix node script.js
(...execution output...)
> The issue has been resolved. The text `consol` was corrected to `console`.

# Running the corrected JavaScript file
hans@LAPTOP-O1AO16UE:/projects/new-web-project$ node script.js
Test
```

# Requirements
- php-cli >=8
- OpenAI API key
- unix system (Linux/MacOS)
# Installation
```bash
git clone --recurse-submodules --depth=1 https://github.com/divinity76/aido-aifix.git
cd aido-aifix
sudo ln -sv "$(pwd)/src/aido.php" /usr/local/bin/aido
sudo chmod a+x /usr/local/bin/aido
sudo ln -sv "$(pwd)/src/aifix.php" /usr/local/bin/aifix
sudo chmod a+x /usr/local/bin/aifix
```
## AI Models

- **Default Model - o4-mini**: Cost-effective and suitable for general tasks.
- **Advanced Model - o3-mini**: Choose this for complex tasks; it's 7 times the cost of o4-mini.
- **Premium Model - o1**: Use for the most challenging issues; it's 100 times more expensive than o4-mini and 13 times more than o3-mini (untested).

