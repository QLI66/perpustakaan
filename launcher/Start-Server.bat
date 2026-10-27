@echo off
title Sistem Informasi Perpustakaan Sekolah - Server Launcher
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0Start-LibraryServer.ps1"
pause
