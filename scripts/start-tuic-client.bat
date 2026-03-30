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

if not "%TUIC_CONFIG%"=="" set "ARGS=%ARGS% --config=%TUIC_CONFIG%"
if not "%TUIC_NODE%"=="" set "ARGS=%ARGS% --node=%TUIC_NODE%"
if not "%TUIC_NODE_NAME%"=="" set "ARGS=%ARGS% --node-name=%TUIC_NODE_NAME%"
if not "%QUICHE_LIB%"=="" set "ARGS=%ARGS% --quiche-lib=%QUICHE_LIB%"

"%PHP_BIN%" %ARGS%
exit /b %ERRORLEVEL%
