@echo off
title Backup Database Perpustakaan Sekolah
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0Backup-Database.ps1"
pause
