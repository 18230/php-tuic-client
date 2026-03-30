param(
    [string]$Version = "0.26.1",
    [string]$WorkDir = "",
    [string]$OutputDir = ""
)

Set-StrictMode -Version Latest
$ErrorActionPreference = "Stop"

if ($WorkDir -eq "") {
    $WorkDir = Join-Path $PSScriptRoot "..\\runtime\\quiche-src"
}

if ($OutputDir -eq "") {
    $architecture = [System.Runtime.InteropServices.RuntimeInformation]::OSArchitecture.ToString().ToLowerInvariant()
    $architecture = switch ($architecture) {
        "x64" { "x64" }
        "arm64" { "arm64" }
        default { $architecture }
    }

    $OutputDir = Join-Path $PSScriptRoot ("..\\resources\\native\\windows-{0}" -f $architecture)
}

$WorkDir = [System.IO.Path]::GetFullPath($WorkDir)
$OutputDir = [System.IO.Path]::GetFullPath($OutputDir)
$ArchivePath = Join-Path ([System.IO.Path]::GetTempPath()) "quiche-$Version.zip"
$ExtractDir = Join-Path ([System.IO.Path]::GetTempPath()) "quiche-$Version"

New-Item -ItemType Directory -Force -Path $OutputDir | Out-Null

if (-not (Get-Command cargo -ErrorAction SilentlyContinue)) {
    throw "cargo was not found in PATH."
}

if (-not (Get-Command cmake -ErrorAction SilentlyContinue)) {
    throw "cmake was not found in PATH."
}

if (-not (Get-Command nasm -ErrorAction SilentlyContinue)) {
    throw "nasm was not found in PATH. It is required by quiche on Windows."
}

if (Test-Path $WorkDir) {
    Remove-Item -Recurse -Force $WorkDir
}

if (Test-Path $ExtractDir) {
    Remove-Item -Recurse -Force $ExtractDir
}

Invoke-WebRequest -Uri "https://github.com/cloudflare/quiche/archive/refs/tags/$Version.zip" -OutFile $ArchivePath
Expand-Archive -Path $ArchivePath -DestinationPath ([System.IO.Path]::GetTempPath()) -Force
Move-Item -LiteralPath $ExtractDir -Destination $WorkDir

Push-Location $WorkDir
try {
    cargo build -p quiche --release --features ffi
    $dll = Join-Path $WorkDir "target\\release\\quiche.dll"
    if (-not (Test-Path $dll)) {
        throw "quiche.dll was not produced at $dll"
    }

    Copy-Item -LiteralPath $dll -Destination (Join-Path $OutputDir "quiche.dll") -Force
    Write-Host "Built quiche.dll to $OutputDir"
} finally {
    Pop-Location
}
