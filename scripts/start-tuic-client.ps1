$ErrorActionPreference = 'Stop'

if (-not $env:PHP_BIN -or [string]::IsNullOrWhiteSpace($env:PHP_BIN)) {
    $env:PHP_BIN = 'php'
}

$root = Split-Path -Parent $PSScriptRoot

if ([string]::IsNullOrWhiteSpace($env:TUIC_SERVER)) { throw 'TUIC_SERVER is required.' }
if ([string]::IsNullOrWhiteSpace($env:TUIC_PORT)) { throw 'TUIC_PORT is required.' }
if ([string]::IsNullOrWhiteSpace($env:TUIC_UUID)) { throw 'TUIC_UUID is required.' }
if ([string]::IsNullOrWhiteSpace($env:TUIC_PASSWORD)) { throw 'TUIC_PASSWORD is required.' }
if ([string]::IsNullOrWhiteSpace($env:TUIC_ALPN)) { $env:TUIC_ALPN = 'h3' }
if ([string]::IsNullOrWhiteSpace($env:TUIC_UDP_RELAY_MODE)) { $env:TUIC_UDP_RELAY_MODE = 'native' }
if ([string]::IsNullOrWhiteSpace($env:TUIC_CONGESTION_CONTROLLER)) { $env:TUIC_CONGESTION_CONTROLLER = 'bbr' }
if ([string]::IsNullOrWhiteSpace($env:TUIC_ALLOW_INSECURE)) { $env:TUIC_ALLOW_INSECURE = '0' }
if ([string]::IsNullOrWhiteSpace($env:TUIC_LOCAL)) { $env:TUIC_LOCAL = '127.0.0.1:1080' }
if ([string]::IsNullOrWhiteSpace($env:TUIC_LOG_LEVEL)) { $env:TUIC_LOG_LEVEL = 'info' }
if ([string]::IsNullOrWhiteSpace($env:TUIC_DRY_RUN)) { $env:TUIC_DRY_RUN = '1' }

$arguments = @(
    (Join-Path $root 'bin\tuic-client'),
    'run',
    "--server=$($env:TUIC_SERVER)",
    "--port=$($env:TUIC_PORT)",
    "--uuid=$($env:TUIC_UUID)",
    "--password=$($env:TUIC_PASSWORD)",
    "--alpn=$($env:TUIC_ALPN)",
    "--udp-relay-mode=$($env:TUIC_UDP_RELAY_MODE)",
    "--congestion-controller=$($env:TUIC_CONGESTION_CONTROLLER)",
    "--allow-insecure=$($env:TUIC_ALLOW_INSECURE)",
    "--local=$($env:TUIC_LOCAL)",
    "--log-level=$($env:TUIC_LOG_LEVEL)"
)

if (-not [string]::IsNullOrWhiteSpace($env:TUIC_SNI)) {
    $arguments += "--sni=$($env:TUIC_SNI)"
}

if ($env:TUIC_DRY_RUN -eq '1') {
    $arguments += '--dry-run'
}

& $env:PHP_BIN @arguments
