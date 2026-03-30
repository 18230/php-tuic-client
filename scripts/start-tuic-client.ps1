param(
    [string] $Config = '',
    [string] $Node = '',
    [string] $NodeName = '',
    [string] $SocksListen = '',
    [string] $QuicheLib = ''
)

$ErrorActionPreference = 'Stop'

$phpBin = if ($env:PHP_BIN) { $env:PHP_BIN } else { 'php' }
$appRoot = Split-Path -Parent $PSScriptRoot

if (-not $Config) {
    $Config = $env:TUIC_CONFIG
}

if (-not $Node) {
    $Node = $env:TUIC_NODE
}

if (-not $NodeName) {
    $NodeName = $env:TUIC_NODE_NAME
}

if (-not $SocksListen) {
    $SocksListen = if ($env:TUIC_SOCKS_LISTEN) { $env:TUIC_SOCKS_LISTEN } else { '127.0.0.1:1080' }
}

if (-not $QuicheLib) {
    $QuicheLib = $env:QUICHE_LIB
}

if ([string]::IsNullOrWhiteSpace($Config) -and [string]::IsNullOrWhiteSpace($Node)) {
    throw 'TUIC_CONFIG or TUIC_NODE is required.'
}

$arguments = @(
    (Join-Path $appRoot 'bin\tuic-client')
    "--listen=$SocksListen"
)

if (-not [string]::IsNullOrWhiteSpace($Config)) {
    $arguments += "--config=$Config"
}

if (-not [string]::IsNullOrWhiteSpace($Node)) {
    $arguments += "--node=$Node"
}

if (-not [string]::IsNullOrWhiteSpace($NodeName)) {
    $arguments += "--node-name=$NodeName"
}

if (-not [string]::IsNullOrWhiteSpace($QuicheLib)) {
    $arguments += "--quiche-lib=$QuicheLib"
}

& $phpBin @arguments
exit $LASTEXITCODE
