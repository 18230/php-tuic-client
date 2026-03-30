@echo off
setlocal

if "%PHP_BIN%"=="" set "PHP_BIN=php"
set "APP_ROOT=%~dp0.."

if "%TUIC_CONFIG%"=="" if "%TUIC_NODE%"=="" (
    echo TUIC_CONFIG or TUIC_NODE is required.
    exit /b 1
)

set "ARGS=%APP_ROOT%\bin\tuic-client"

if "%TUIC_SOCKS_LISTEN%"=="" (
    set "ARGS=%ARGS% --listen=127.0.0.1:1080"
) else (
    set "ARGS=%ARGS% --listen=%TUIC_SOCKS_LISTEN%"
)

if not "%TUIC_ALLOW_IP%"=="" set "ARGS=%ARGS% --allow-ip=%TUIC_ALLOW_IP%"
if not "%TUIC_MAX_CONNECTIONS%"=="" set "ARGS=%ARGS% --max-connections=%TUIC_MAX_CONNECTIONS%"
if not "%TUIC_CONNECT_TIMEOUT%"=="" set "ARGS=%ARGS% --connect-timeout=%TUIC_CONNECT_TIMEOUT%"
if not "%TUIC_IDLE_TIMEOUT%"=="" set "ARGS=%ARGS% --idle-timeout=%TUIC_IDLE_TIMEOUT%"
if not "%TUIC_HANDSHAKE_TIMEOUT%"=="" set "ARGS=%ARGS% --handshake-timeout=%TUIC_HANDSHAKE_TIMEOUT%"
if not "%TUIC_STATUS_FILE%"=="" set "ARGS=%ARGS% --status-file=%TUIC_STATUS_FILE%"
if not "%TUIC_STATUS_INTERVAL%"=="" set "ARGS=%ARGS% --status-interval=%TUIC_STATUS_INTERVAL%"
if not "%TUIC_LOG_FILE%"=="" set "ARGS=%ARGS% --log-file=%TUIC_LOG_FILE%"
if not "%TUIC_PID_FILE%"=="" set "ARGS=%ARGS% --pid-file=%TUIC_PID_FILE%"

if not "%TUIC_CONFIG%"=="" set "ARGS=%ARGS% --config=%TUIC_CONFIG%"
if not "%TUIC_NODE%"=="" set "ARGS=%ARGS% --node=%TUIC_NODE%"
if not "%TUIC_NODE_NAME%"=="" set "ARGS=%ARGS% --node-name=%TUIC_NODE_NAME%"
if not "%QUICHE_LIB%"=="" set "ARGS=%ARGS% --quiche-lib=%QUICHE_LIB%"

"%PHP_BIN%" %ARGS%
exit /b %ERRORLEVEL%
