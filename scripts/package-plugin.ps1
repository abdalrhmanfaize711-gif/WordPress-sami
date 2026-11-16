param(
    [string]$OutputPath = (Join-Path $PSScriptRoot '..\dist\yemen-travel-site.zip')
)

$ErrorActionPreference = 'Stop'
$pluginPath = (Resolve-Path (Join-Path $PSScriptRoot '..\wordpress\wp-content\plugins\yemen-travel-site')).Path
$outputFullPath = [System.IO.Path]::GetFullPath($OutputPath)
$outputDirectory = Split-Path -Parent $outputFullPath

if (-not (Test-Path -LiteralPath (Join-Path $pluginPath 'yemen-travel-site.php') -PathType Leaf)) {
    throw 'The main plugin file is missing.'
}

New-Item -ItemType Directory -Path $outputDirectory -Force | Out-Null
if (Test-Path -LiteralPath $outputFullPath) {
    Remove-Item -LiteralPath $outputFullPath -Force
}

Add-Type -AssemblyName System.IO.Compression.FileSystem
[System.IO.Compression.ZipFile]::CreateFromDirectory(
    $pluginPath,
    $outputFullPath,
    [System.IO.Compression.CompressionLevel]::Optimal,
    $true
)

$archive = [System.IO.Compression.ZipFile]::OpenRead($outputFullPath)
try {
    $requiredEntries = @(
        'yemen-travel-site/yemen-travel-site.php',
        'yemen-travel-site/assets/site.css',
        'yemen-travel-site/assets/site.js'
    )
    foreach ($entry in $requiredEntries) {
        if (-not $archive.GetEntry($entry)) {
            throw "The ZIP is missing required file: $entry"
        }
    }
    Write-Output "Validated $($archive.Entries.Count) files in $outputFullPath"
}
finally {
    $archive.Dispose()
}

