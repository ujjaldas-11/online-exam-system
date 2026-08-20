#!/usr/bin/env bash
# Script to run editorconfig-checker across the project.

set -e

# Navigate to project root (one level up from tools directory)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
cd "${ROOT_DIR}"

# Check if npx is available
if ! command -v npx &> /dev/null; then
    echo "Error: 'npx' command is not found on your system."
    echo ""
    echo "To run editorconfig-checker, please install Node.js (which includes npx):"
    echo "  - Official Download: https://nodejs.org"
    echo "  - macOS (Homebrew):   brew install node"
    echo "  - Debian / Ubuntu:    sudo apt update && sudo apt install nodejs npm"
    echo "  - Fedora / RHEL:      sudo dnf install nodejs npm"
    echo "  - Arch Linux:         sudo pacman -S nodejs npm"
    echo ""
    echo "Alternatively, you can install the standalone editorconfig-checker binary:"
    echo "  - https://github.com/editorconfig-checker/editorconfig-checker/releases"
    exit 1
fi

echo "Running editorconfig-checker..."
npx editorconfig-checker "$@"
