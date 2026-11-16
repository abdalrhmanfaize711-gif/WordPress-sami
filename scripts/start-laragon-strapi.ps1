$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path -Parent $PSScriptRoot
$strapiRoot = Join-Path $repoRoot 'strapi'
$dotenvPath = Join-Path $strapiRoot '.env'
if (-not (Test-Path -LiteralPath $dotenvPath)) { throw 'Run scripts/install-laragon.ps1 first to prepare the Laragon databases and Strapi settings.' }
if (-not (Test-NetConnection -ComputerName 127.0.0.1 -Port 3306 -InformationLevel Quiet)) { throw 'Start Laragon and click Start All so MySQL is running.' }
if (-not (Test-Path -LiteralPath (Join-Path $strapiRoot 'node_modules\@strapi\strapi\package.json')) -or -not (Test-Path -LiteralPath (Join-Path $strapiRoot 'node_modules\mysql2\package.json'))) { throw 'Strapi packages are missing. Run npm install from the strapi folder.' }
Push-Location $strapiRoot
try { npm run develop; if ($LASTEXITCODE -ne 0) { throw 'Strapi stopped with an error. Read the messages above.' } }
finally { Pop-Location }
