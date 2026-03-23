param(
    [switch]$Clean
)

$ErrorActionPreference = "Stop"

$repoRoot = Split-Path -Parent $PSScriptRoot
$pluginDir = Join-Path $repoRoot "plugin\skroutz-xml-feed-for-woocommerce"
$pluginMainFile = Join-Path $pluginDir "skroutz-xml-feed-for-woocommerce.php"
$pluginReadme = Join-Path $pluginDir "readme.txt"
$rootMirrorFile = Join-Path $repoRoot "skroutz-xml-feed-for-woocommerce.php"
$rootReadme = Join-Path $repoRoot "readme.txt"
$buildRoot = Join-Path $repoRoot "build\plugin-package"
$stageDir = Join-Path $buildRoot "skroutz-xml-feed-for-woocommerce"
$distDir = Join-Path $repoRoot "dist"

function Get-HeaderValue {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$HeaderName
    )

    $pattern = "^\s*\*\s*" + [regex]::Escape($HeaderName) + ":\s*(.+?)\s*$"
    $match = Select-String -Path $Path -Pattern $pattern | Select-Object -First 1

    if (-not $match) {
        throw "Could not find header '$HeaderName' in $Path."
    }

    return $match.Matches[0].Groups[1].Value.Trim()
}

function Get-ReadmeValue {
    param(
        [Parameter(Mandatory = $true)][string]$Path,
        [Parameter(Mandatory = $true)][string]$HeaderName
    )

    $pattern = "^" + [regex]::Escape($HeaderName) + ":\s*(.+?)\s*$"
    $match = Select-String -Path $Path -Pattern $pattern | Select-Object -First 1

    if (-not $match) {
        throw "Could not find readme header '$HeaderName' in $Path."
    }

    return $match.Matches[0].Groups[1].Value.Trim()
}

if (-not (Test-Path $pluginMainFile)) {
    throw "Plugin main file not found at $pluginMainFile"
}

$pluginVersion = Get-HeaderValue -Path $pluginMainFile -HeaderName "Version"
$rootVersion = Get-HeaderValue -Path $rootMirrorFile -HeaderName "Version"
$pluginStableTag = Get-ReadmeValue -Path $pluginReadme -HeaderName "Stable tag"
$rootStableTag = Get-ReadmeValue -Path $rootReadme -HeaderName "Stable tag"

if ($pluginVersion -ne $rootVersion) {
    throw "Plugin version mismatch between $pluginMainFile and $rootMirrorFile"
}

if ($pluginVersion -ne $pluginStableTag -or $pluginVersion -ne $rootStableTag) {
    throw "Plugin version and readme stable tags must match."
}

if ($Clean) {
    if (Test-Path $buildRoot) {
        Remove-Item $buildRoot -Recurse -Force
    }
}

if (Test-Path $stageDir) {
    Remove-Item $stageDir -Recurse -Force
}

if (-not (Test-Path $distDir)) {
    New-Item -ItemType Directory -Path $distDir | Out-Null
}

$php = Get-Command php -ErrorAction SilentlyContinue
if ($php) {
    $phpFiles = Get-ChildItem -Path $pluginDir -Recurse -Filter *.php | Sort-Object FullName
    foreach ($file in $phpFiles) {
        & $php.Source -l $file.FullName
        if ($LASTEXITCODE -ne 0) {
            throw "PHP lint failed for $($file.FullName)"
        }
    }
} else {
    Write-Warning "PHP was not found on PATH. Skipping php -l validation."
}

New-Item -ItemType Directory -Path $buildRoot -Force | Out-Null
Copy-Item -Path $pluginDir -Destination $stageDir -Recurse -Force

$zipPath = Join-Path $distDir ("skroutz-xml-feed-for-woocommerce-{0}.zip" -f $pluginVersion)
if (Test-Path $zipPath) {
    Remove-Item $zipPath -Force
}

Compress-Archive -Path $stageDir -DestinationPath $zipPath -Force

$hash = (Get-FileHash -Path $zipPath -Algorithm SHA256).Hash

Write-Host "Created plugin package: $zipPath"
Write-Host "SHA256: $hash"
