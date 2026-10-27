@echo off
title Hentikan Server Perpustakaan Sekolah
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0Stop-LibraryServer.ps1"
pause
