#!/usr/bin/env bash
# Script to run editorconfig-checker across the project.

set -e

# Navigate to project root (one level up from tools directory)
SCRIPT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")" && pwd)"
ROOT_DIR="$(cd "${SCRIPT_DIR}/.." && pwd)"
cd "${ROOT_DIR}"

# Check if editorconfig-checker standalone binary or npx is available
if command -v editorconfig-checker &> /dev/null; then
    EC_CMD="editorconfig-checker"
elif command -v ec &> /dev/null; then
    EC_CMD="ec"
elif [ -x "$HOME/.local/bin/editorconfig-checker" ]; then
    EC_CMD="$HOME/.local/bin/editorconfig-checker"
elif command -v npx &> /dev/null; then
    EC_CMD="npx editorconfig-checker"
else
    echo "Error: Neither 'editorconfig-checker' binary nor 'npx' command was found on your system."
    echo ""
    echo "To run editorconfig-checker, please install Node.js (which includes npx):"
    echo "  - Official Download: https://nodejs.org"
    echo "  - Debian / Ubuntu:    sudo apt update && sudo apt install nodejs npm"
    echo ""
    echo "Alternatively, install the standalone binary:"
    echo "  - https://github.com/editorconfig-checker/editorconfig-checker/releases"
    exit 1
fi

echo "Running editorconfig-checker ($EC_CMD)..."
$EC_CMD "$@"
