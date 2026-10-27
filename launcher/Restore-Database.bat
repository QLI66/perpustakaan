@echo off
title Restore Database Perpustakaan Sekolah
cd /d "%~dp0"
powershell.exe -NoProfile -ExecutionPolicy Bypass -File "%~dp0Restore-Database.ps1"
pause
