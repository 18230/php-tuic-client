param(
    [string] $Config = '',
    [string] $Node = '',
    [string] $NodeName = '',
    [string] $HttpListen = '',
    [string] $SocksListen = ''
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

if (-not $HttpListen) {
    $HttpListen = if ($env:TUIC_HTTP_LISTEN) { $env:TUIC_HTTP_LISTEN } else { '127.0.0.1:8080' }
}

if (-not $SocksListen) {
    $SocksListen = if ($env:TUIC_SOCKS_LISTEN) { $env:TUIC_SOCKS_LISTEN } else { '127.0.0.1:1080' }
}

if ([string]::IsNullOrWhiteSpace($Config) -and [string]::IsNullOrWhiteSpace($Node)) {
    throw 'TUIC_CONFIG or TUIC_NODE is required.'
}

$arguments = @(
    (Join-Path $appRoot 'bin\tuic-client')
    "--http-listen=$HttpListen"
    "--socks-listen=$SocksListen"
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

if ($env:TUIC_NO_HTTP -eq '1') {
    $arguments += '--no-http'
}

if ($env:TUIC_NO_SOCKS -eq '1') {
    $arguments += '--no-socks'
}

& $phpBin @arguments
exit $LASTEXITCODE
