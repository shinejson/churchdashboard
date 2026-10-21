# generate-env.ps1
# PowerShell script to generate frontend/js/env.js from env.dev.js or env.prod.js
# Usage: run from project root or via full path. Reads ENV_MODE file in project root.

param(
    [string]$ProjectRoot = $(Split-Path -Parent $PSScriptRoot)
)

$envFile = Join-Path $ProjectRoot 'ENV_MODE'
$mode = 'dev'
if (Test-Path $envFile) {
    $m = (Get-Content $envFile -Raw).Trim()
    if ($m -eq 'prod') { $mode = 'prod' }
}

$srcDev = Join-Path $ProjectRoot 'frontend\js\env.dev.js'
$srcProd = Join-Path $ProjectRoot 'frontend\js\env.prod.js'
$dest = Join-Path $ProjectRoot 'frontend\js\env.js'

if ($mode -eq 'prod' -and (Test-Path $srcProd)) {
    Copy-Item -Path $srcProd -Destination $dest -Force
    Write-Host "Generated env.js from env.prod.js (mode=prod) -> $dest"
} elseif ($mode -eq 'dev' -and (Test-Path $srcDev)) {
    Copy-Item -Path $srcDev -Destination $dest -Force
    Write-Host "Generated env.js from env.dev.js (mode=dev) -> $dest"
} else {
    # fallback: write a minimal file
    $content = "// auto-generated fallback env.js`nwindow.DEV = false;`nwindow.API_BASE = window.API_BASE || 'http://localhost/church/backend';`n"
    $content | Out-File -FilePath $dest -Encoding UTF8 -Force
    Write-Host "Generated fallback env.js -> $dest"
}

# Exit with success
exit 0
