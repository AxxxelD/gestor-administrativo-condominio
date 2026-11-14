@echo off
chcp 65001 >nul
title Condominio - Desarrollo local
pushd "%~dp0"
node iniciar-desarrollo.mjs
if errorlevel 1 pause
popd
