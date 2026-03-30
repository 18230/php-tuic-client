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

$allowIp = $env:TUIC_ALLOW_IP
$maxConnections = $env:TUIC_MAX_CONNECTIONS
$connectTimeout = $env:TUIC_CONNECT_TIMEOUT
$idleTimeout = $env:TUIC_IDLE_TIMEOUT
$handshakeTimeout = $env:TUIC_HANDSHAKE_TIMEOUT
$statusFile = $env:TUIC_STATUS_FILE
$statusInterval = $env:TUIC_STATUS_INTERVAL
$logFile = $env:TUIC_LOG_FILE
$pidFile = $env:TUIC_PID_FILE

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

if (-not [string]::IsNullOrWhiteSpace($allowIp)) {
    $arguments += "--allow-ip=$allowIp"
}

if (-not [string]::IsNullOrWhiteSpace($maxConnections)) {
    $arguments += "--max-connections=$maxConnections"
}

if (-not [string]::IsNullOrWhiteSpace($connectTimeout)) {
    $arguments += "--connect-timeout=$connectTimeout"
}

if (-not [string]::IsNullOrWhiteSpace($idleTimeout)) {
    $arguments += "--idle-timeout=$idleTimeout"
}

if (-not [string]::IsNullOrWhiteSpace($handshakeTimeout)) {
    $arguments += "--handshake-timeout=$handshakeTimeout"
}

if (-not [string]::IsNullOrWhiteSpace($statusFile)) {
    $arguments += "--status-file=$statusFile"
}

if (-not [string]::IsNullOrWhiteSpace($statusInterval)) {
    $arguments += "--status-interval=$statusInterval"
}

if (-not [string]::IsNullOrWhiteSpace($logFile)) {
    $arguments += "--log-file=$logFile"
}

if (-not [string]::IsNullOrWhiteSpace($pidFile)) {
    $arguments += "--pid-file=$pidFile"
}

if (-not [string]::IsNullOrWhiteSpace($QuicheLib)) {
    $arguments += "--quiche-lib=$QuicheLib"
}

& $phpBin @arguments
exit $LASTEXITCODE
