param(
    [string]$LaragonRoot = 'C:\laragon',
    [string]$SiteName = 'yemen-travel',
    [int]$MySqlPort = 3306
)

$ErrorActionPreference = 'Stop'
$repoRoot = Split-Path -Parent $PSScriptRoot
$envPath = Join-Path $repoRoot '.env'
$envExamplePath = Join-Path $repoRoot '.env.example'
$siteRoot = Join-Path (Join-Path $LaragonRoot 'www') $SiteName
$siteUrl = "http://localhost/$SiteName"

if (-not (Test-Path -LiteralPath $LaragonRoot)) { throw "Laragon folder not found: $LaragonRoot" }
if (-not (Get-Command npm -ErrorAction SilentlyContinue)) { throw 'Node.js and npm are required. Install the LTS version, then reopen PowerShell.' }
if (-not (Test-Path -LiteralPath $envPath)) { Copy-Item -LiteralPath $envExamplePath -Destination $envPath }

$settings = @{}
foreach ($line in Get-Content -LiteralPath $envPath) {
    if ($line -match '^\s*([A-Za-z_][A-Za-z0-9_]*)=(.*)$') { $settings[$Matches[1]] = $Matches[2] }
}

$mysqlRoot = Join-Path $LaragonRoot 'bin\mysql'
$mysqlExe = Get-ChildItem -LiteralPath $mysqlRoot -Directory -ErrorAction Stop |
    Sort-Object Name -Descending |
    ForEach-Object { Join-Path $_.FullName 'bin\mysql.exe' } |
    Where-Object { Test-Path -LiteralPath $_ } |
    Select-Object -First 1
if (-not $mysqlExe) { throw "mysql.exe was not found under $mysqlRoot" }
if (-not (Test-NetConnection -ComputerName 127.0.0.1 -Port $MySqlPort -InformationLevel Quiet)) {
    throw 'Start Laragon, click Start All, and make sure MySQL is running before running this setup again.'
}

$securePassword = Read-Host 'Laragon MySQL root password (press Enter if you have not set one)' -AsSecureString
$ptr = [Runtime.InteropServices.Marshal]::SecureStringToBSTR($securePassword)
try { $mysqlPassword = [Runtime.InteropServices.Marshal]::PtrToStringBSTR($ptr) }
finally { [Runtime.InteropServices.Marshal]::ZeroFreeBSTR($ptr) }

$previousMysqlPassword = $env:MYSQL_PWD
try {
    if ($mysqlPassword) { $env:MYSQL_PWD = $mysqlPassword } else { Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue }
    $sql = "CREATE DATABASE IF NOT EXISTS yemen_wordpress CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci; CREATE DATABASE IF NOT EXISTS yemen_strapi CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;"
    & $mysqlExe --host=127.0.0.1 "--port=$MySqlPort" --user=root "--execute=$sql"
    if ($LASTEXITCODE -ne 0) { throw 'Could not create the project databases. Check the MySQL root password you entered.' }
}
finally {
    if ($null -eq $previousMysqlPassword) { Remove-Item Env:MYSQL_PWD -ErrorAction SilentlyContinue } else { $env:MYSQL_PWD = $previousMysqlPassword }
}

if (-not (Test-Path -LiteralPath $siteRoot)) { New-Item -ItemType Directory -Path $siteRoot -Force | Out-Null }
$wpSettingsPath = Join-Path $siteRoot 'wp-settings.php'
if (-not (Test-Path -LiteralPath $wpSettingsPath)) {
    $existing = Get-ChildItem -LiteralPath $siteRoot -Force
    if ($existing.Count -gt 0) { throw "The target folder is not empty and is not a WordPress installation: $siteRoot" }
    $tempRoot = Join-Path $env:TEMP ('yemen-wordpress-' + [guid]::NewGuid().ToString('N'))
    New-Item -ItemType Directory -Path $tempRoot -Force | Out-Null
    try {
        $archive = Join-Path $tempRoot 'wordpress.zip'
        Invoke-WebRequest -Uri 'https://wordpress.org/latest.zip' -OutFile $archive -TimeoutSec 240
        Expand-Archive -LiteralPath $archive -DestinationPath $tempRoot -Force
        Copy-Item -Path (Join-Path $tempRoot 'wordpress\*') -Destination $siteRoot -Recurse -Force
    }
    finally {
        Remove-Item -LiteralPath $tempRoot -Recurse -Force -ErrorAction SilentlyContinue
    }
}

$configPath = Join-Path $siteRoot 'wp-config.php'
$samplePath = Join-Path $siteRoot 'wp-config-sample.php'
if (-not (Test-Path -LiteralPath $configPath)) {
    if (-not (Test-Path -LiteralPath $samplePath)) { throw 'WordPress config sample is missing from the downloaded files.' }
    $wpConfig = Get-Content -LiteralPath $samplePath -Raw
    $dbValues = @{
        DB_NAME = 'yemen_wordpress'
        DB_USER = 'root'
        DB_PASSWORD = $mysqlPassword
        DB_HOST = "127.0.0.1:$MySqlPort"
    }
    foreach ($key in $dbValues.Keys) {
        $value = ([string]$dbValues[$key]).Replace('\', '\\').Replace("'", "\'")
        $pattern = "define\(\s*'$key',\s*'[^']*'\s*\);"
        $replacement = "define( '$key', '$value' );"
        $evaluator = [System.Text.RegularExpressions.MatchEvaluator]{ param($match) $replacement }
        $wpConfig = [regex]::Replace($wpConfig, $pattern, $evaluator)
    }
    foreach ($key in @('AUTH_KEY','SECURE_AUTH_KEY','LOGGED_IN_KEY','NONCE_KEY','AUTH_SALT','SECURE_AUTH_SALT','LOGGED_IN_SALT','NONCE_SALT')) {
        $randomBytes = New-Object byte[] 48
        $randomGenerator = [Security.Cryptography.RandomNumberGenerator]::Create()
        try { $randomGenerator.GetBytes($randomBytes) } finally { $randomGenerator.Dispose() }
        $salt = [Convert]::ToBase64String($randomBytes)



        $pattern = "define\(\s*'$key',\s*'[^']*'\s*\);"
        $wpConfig = [regex]::Replace($wpConfig, $pattern, "define( '$key', '$salt' );")
    }
    $configFlags = "define( 'WP_ENVIRONMENT_TYPE', 'local' );`r`ndefine( 'YTS_ALLOW_LARAGON_STRAPI', true );"
    $marker = "/* That's all, stop editing! Happy publishing. */"
    if (-not $wpConfig.Contains($marker)) { throw 'Could not find the insertion point in wp-config-sample.php.' }
    $wpConfig = $wpConfig.Replace($marker, "$configFlags`r`n`r`n$marker")
    [System.IO.File]::WriteAllText($configPath, $wpConfig, [System.Text.UTF8Encoding]::new($false))
}
else {
    $wpConfig = Get-Content -LiteralPath $configPath -Raw
    if (-not $wpConfig.Contains('YTS_ALLOW_LARAGON_STRAPI')) {
        $marker = "/* That's all, stop editing! Happy publishing. */"
        if (-not $wpConfig.Contains($marker)) { throw 'Add YTS_ALLOW_LARAGON_STRAPI to wp-config.php before the stop-editing comment.' }
        $wpConfig = $wpConfig.Replace($marker, "define( 'YTS_ALLOW_LARAGON_STRAPI', true );`r`n$marker")
        [System.IO.File]::WriteAllText($configPath, $wpConfig, [System.Text.UTF8Encoding]::new($false))
    }
}

$pluginSource = Join-Path $repoRoot 'wordpress\wp-content\plugins\yemen-travel-site'
$pluginTarget = Join-Path $siteRoot 'wp-content\plugins\yemen-travel-site'
New-Item -ItemType Directory -Path $pluginTarget -Force | Out-Null
Copy-Item -Path (Join-Path $pluginSource '*') -Destination $pluginTarget -Recurse -Force

$dotenv = @{
    NODE_ENV = 'development'
    HOST = '127.0.0.1'
    PORT = '1337'
    PUBLIC_STRAPI_URL = 'http://127.0.0.1:1337'
    APP_KEYS = $settings['STRAPI_APP_KEYS']
    API_TOKEN_SALT = $settings['STRAPI_API_TOKEN_SALT']
    ADMIN_JWT_SECRET = $settings['STRAPI_ADMIN_JWT_SECRET']
    TRANSFER_TOKEN_SALT = $settings['STRAPI_TRANSFER_TOKEN_SALT']
    JWT_SECRET = $settings['STRAPI_JWT_SECRET']
    ENCRYPTION_KEY = $settings['STRAPI_ENCRYPTION_KEY']
    DATABASE_CLIENT = 'mysql'
    DATABASE_HOST = '127.0.0.1'
    DATABASE_PORT = [string]$MySqlPort
    DATABASE_NAME = 'yemen_strapi'
    DATABASE_USERNAME = 'root'
    DATABASE_PASSWORD = $mysqlPassword
    DATABASE_SSL = 'false'
}
$dotenvLines = foreach ($entry in $dotenv.GetEnumerator()) {
    $value = [string]$entry.Value
    $escaped = $value.Replace('\', '\\').Replace('"', '\"').Replace("`r", '\r').Replace("`n", '\n')
    "$($entry.Key)=`"$escaped`""
}
[System.IO.File]::WriteAllLines((Join-Path $repoRoot 'strapi\.env'), [string[]]$dotenvLines, [System.Text.UTF8Encoding]::new($false))

$strapiRoot = Join-Path $repoRoot 'strapi'
$requiredPackages = @(
    (Join-Path $strapiRoot 'node_modules\@strapi\strapi\package.json'),
    (Join-Path $strapiRoot 'node_modules\mysql2\package.json')
)
if ($requiredPackages | Where-Object { -not (Test-Path -LiteralPath $_) }) {
    Push-Location $strapiRoot
    try {
        npm install --no-audit --no-fund
        if ($LASTEXITCODE -ne 0) { throw 'Strapi dependency installation failed. Check the connection, then run npm install again from the strapi folder.' }
    }
    finally { Pop-Location }
}

Write-Host ''
Write-Host 'Laragon setup is ready.' -ForegroundColor Green
Write-Host "WordPress: $siteUrl"
Write-Host 'Strapi admin: http://127.0.0.1:1337/admin (start it with scripts/start-laragon-strapi.ps1)'
Write-Host 'WordPress database: yemen_wordpress; Strapi database: yemen_strapi'
Write-Host 'WordPress DB user: root; Strapi DB user: root'
Write-Host 'The WordPress admin installer still needs you to set the site title and create its admin account.'
