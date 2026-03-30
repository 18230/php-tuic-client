@echo off
setlocal

if "%PHP_BIN%"=="" set "PHP_BIN=php"
if "%TUIC_SERVER%"=="" echo TUIC_SERVER is required.& exit /b 1
if "%TUIC_PORT%"=="" echo TUIC_PORT is required.& exit /b 1
if "%TUIC_UUID%"=="" echo TUIC_UUID is required.& exit /b 1
if "%TUIC_PASSWORD%"=="" echo TUIC_PASSWORD is required.& exit /b 1
if "%TUIC_ALPN%"=="" set "TUIC_ALPN=h3"
if "%TUIC_UDP_RELAY_MODE%"=="" set "TUIC_UDP_RELAY_MODE=native"
if "%TUIC_CONGESTION_CONTROLLER%"=="" set "TUIC_CONGESTION_CONTROLLER=bbr"
if "%TUIC_ALLOW_INSECURE%"=="" set "TUIC_ALLOW_INSECURE=0"
if "%TUIC_LOCAL%"=="" set "TUIC_LOCAL=127.0.0.1:1080"
if "%TUIC_LOG_LEVEL%"=="" set "TUIC_LOG_LEVEL=info"
if "%TUIC_DRY_RUN%"=="" set "TUIC_DRY_RUN=1"

set "ARGS=%~dp0..\bin\tuic-client run --server=%TUIC_SERVER% --port=%TUIC_PORT% --uuid=%TUIC_UUID% --password=%TUIC_PASSWORD% --alpn=%TUIC_ALPN% --udp-relay-mode=%TUIC_UDP_RELAY_MODE% --congestion-controller=%TUIC_CONGESTION_CONTROLLER% --allow-insecure=%TUIC_ALLOW_INSECURE% --local=%TUIC_LOCAL% --log-level=%TUIC_LOG_LEVEL%"
if not "%TUIC_SNI%"=="" set "ARGS=%ARGS% --sni=%TUIC_SNI%"
if "%TUIC_DRY_RUN%"=="1" set "ARGS=%ARGS% --dry-run"

%PHP_BIN% %ARGS%
