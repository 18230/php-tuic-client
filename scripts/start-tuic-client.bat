@echo off
setlocal

if "%PHP_BIN%"=="" set "PHP_BIN=php"
set "APP_ROOT=%~dp0.."

if "%TUIC_CONFIG%"=="" if "%TUIC_NODE%"=="" (
    echo TUIC_CONFIG or TUIC_NODE is required.
    exit /b 1
)

set "ARGS=%APP_ROOT%\bin\tuic-client --http-listen=%TUIC_HTTP_LISTEN%"
if "%TUIC_HTTP_LISTEN%"=="" set "ARGS=%APP_ROOT%\bin\tuic-client --http-listen=127.0.0.1:8080"

if "%TUIC_SOCKS_LISTEN%"=="" (
    set "ARGS=%ARGS% --socks-listen=127.0.0.1:1080"
) else (
    set "ARGS=%ARGS% --socks-listen=%TUIC_SOCKS_LISTEN%"
)

if not "%TUIC_CONFIG%"=="" set "ARGS=%ARGS% --config=%TUIC_CONFIG%"
if not "%TUIC_NODE%"=="" set "ARGS=%ARGS% --node=%TUIC_NODE%"
if not "%TUIC_NODE_NAME%"=="" set "ARGS=%ARGS% --node-name=%TUIC_NODE_NAME%"
if "%TUIC_NO_HTTP%"=="1" set "ARGS=%ARGS% --no-http"
if "%TUIC_NO_SOCKS%"=="1" set "ARGS=%ARGS% --no-socks"

"%PHP_BIN%" %ARGS%
exit /b %ERRORLEVEL%
