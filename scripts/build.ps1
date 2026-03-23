param(
    [switch]$Clean
)

$ErrorActionPreference = "Stop"

$repoRoot = (Resolve-Path (Join-Path $PSScriptRoot "..")).Path
$venvPath = Join-Path $repoRoot ".venv"

if (-not (Test-Path $venvPath)) {
    python -m venv $venvPath
}

$pythonExe = Join-Path $venvPath "Scripts\\python.exe"

& $pythonExe -m pip install --upgrade pip pyinstaller

$env:PYTHONPATH = Join-Path $repoRoot "src"

if ($Clean) {
    Remove-Item -Recurse -Force (Join-Path $repoRoot "build") -ErrorAction SilentlyContinue
    Remove-Item -Recurse -Force (Join-Path $repoRoot "dist") -ErrorAction SilentlyContinue
    Remove-Item -Force (Join-Path $repoRoot "SkroutzFeedBuilder.spec") -ErrorAction SilentlyContinue
}

Push-Location $repoRoot
try {
    & $pythonExe -m unittest discover -s tests -v
    & $pythonExe -m PyInstaller `
        --noconfirm `
        --clean `
        --windowed `
        --name SkroutzFeedBuilder `
        --paths src `
        src/skroutz_feed_builder/__main__.py
}
finally {
    Pop-Location
}
