#!/bin/bash
set -euxo pipefail

# Check for required commands
command -v git >/dev/null 2>&1 || { echo "Error: git is not installed." >&2; exit 1; }
command -v php >/dev/null 2>&1 || { echo "Error: php is not installed." >&2; exit 1; }

# Prompt for the OpenAI API key from the terminal
read -p "Enter your OpenAI API key: " API_KEY </dev/tty
if [ -z "$API_KEY" ]; then
    echo "Error: API key cannot be empty." >&2
    exit 1
fi

# Prompt for the default model (with a fallback to 'o3-mini')
read -p "Default model (leave blank for the recommended model 'o3-mini'): " DEFAULT_MODEL </dev/tty
if [ -z "$DEFAULT_MODEL" ]; then
    DEFAULT_MODEL="o3-mini"
fi

# Clone the repository
echo "Cloning aido-aifix repository..."
git clone --recurse-submodules --depth=1 https://github.com/divinity76/aido-aifix.git

cd aido-aifix

# Create symbolic links in /usr/local/bin
echo "Creating symbolic links..."
sudo ln -sv "$(pwd)/src/aido.php" /usr/local/bin/aido
sudo chmod a+x /usr/local/bin/aido

sudo ln -sv "$(pwd)/src/aifix.php" /usr/local/bin/aifix
sudo chmod a+x /usr/local/bin/aifix

# Create configuration directory and file
echo "Setting up configuration..."
mkdir -p ~/.config

# Write the configuration JSON to file
echo "{\"api_key\":\"$API_KEY\", \"default_model\":\"$DEFAULT_MODEL\"}" > ~/.config/aido.json

echo "Installation complete!"
