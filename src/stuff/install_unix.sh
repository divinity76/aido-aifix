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

# Prompt for target installation directory
echo "Where do you want to install the symbolic links for the commands?"
echo "1) /usr/local/bin (system-wide, requires sudo)"
echo "2) \$HOME/.local/bin (user-specific)"
read -p "Enter 1 or 2: " target_option </dev/tty

if [ "$target_option" -eq 1 ]; then
    target_bin="/usr/local/bin"
elif [ "$target_option" -eq 2 ]; then
    target_bin="$HOME/.local/bin"
    mkdir -p "$target_bin"
else
    echo "Invalid option, defaulting to /usr/local/bin"
    target_bin="/usr/local/bin"
fi

# Clone the repository
echo "Cloning aido-aifix repository..."
git clone --recurse-submodules --depth=1 https://github.com/divinity76/aido-aifix.git

cd aido-aifix

# Create symbolic links in the chosen directory
echo "Creating symbolic links in $target_bin ..."
if [ "$target_bin" = "/usr/local/bin" ]; then
    sudo ln -sv "$(pwd)/src/aido.php" "$target_bin/aido"
    sudo chmod a+x "$target_bin/aido"

    sudo ln -sv "$(pwd)/src/aifix.php" "$target_bin/aifix"
    sudo chmod a+x "$target_bin/aifix"
else
    ln -sv "$(pwd)/src/aido.php" "$target_bin/aido"
    chmod a+x "$target_bin/aido"

    ln -sv "$(pwd)/src/aifix.php" "$target_bin/aifix"
    chmod a+x "$target_bin/aifix"
fi

# Create configuration directory and file
echo "Setting up configuration..."
mkdir -p ~/.config

# Write the configuration JSON to file
echo "{\"api_key\":\"$API_KEY\", \"default_model\":\"$DEFAULT_MODEL\"}" > ~/.config/aido.json

echo "Installation complete!"
