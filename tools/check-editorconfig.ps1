# Script to run editorconfig-checker across the project.
[CmdletBinding()]
param(
    [Parameter(ValueFromRemainingArguments = $true)]
    [string[]]$CheckerArgs
)

# Navigate to project root (one level up from tools directory)
$ScriptDir = Split-Path -Parent $MyInvocation.MyCommand.Definition
$RootDir = Split-Path -Parent $ScriptDir
Set-Location $RootDir

# Check if npx is available
if (-not (Get-Command "npx" -ErrorAction SilentlyContinue)) {
    Write-Host "Error: 'npx' command is not found on your system." -ForegroundColor Red
    Write-Host ""
    Write-Host "To run editorconfig-checker, please install Node.js (which includes npx):" -ForegroundColor Yellow
    Write-Host "  - Official Download: https://nodejs.org"
    Write-Host "  - Windows (winget):  winget install OpenJS.NodeJS"
    Write-Host "  - Windows (choco):   choco install nodejs"
    Write-Host "  - Windows (scoop):   scoop install nodejs"
    Write-Host ""
    Write-Host "Alternatively, you can install the standalone editorconfig-checker binary:" -ForegroundColor Yellow
    Write-Host "  - https://github.com/editorconfig-checker/editorconfig-checker/releases"
    exit 1
}

Write-Host "Running editorconfig-checker..." -ForegroundColor Cyan
& npx editorconfig-checker @CheckerArgs
exit $LASTEXITCODE
