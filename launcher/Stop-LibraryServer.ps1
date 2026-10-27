#Requires -Version 5.1
<#
.SYNOPSIS
    Script penghentian server perpustakaan LAN secara bersih dan aman.
.DESCRIPTION
    Menghentikan semua container layanan (Caddy, Backend, Nuxt, Database, Scheduler)
    secara graceful tanpa menghapus volume atau file data.
#>
[CmdletBinding()]
param()

$ErrorActionPreference = 'Continue'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$ProjectRoot = (Resolve-Path "$PSScriptRoot\..").Path

function Write-Info([string]$msg) {
    Write-Host "[INFO] $msg" -ForegroundColor Cyan
}

function Write-Success([string]$msg) {
    Write-Host "[SUKSES] $msg" -ForegroundColor Green
}

function Write-Warn([string]$msg) {
    Write-Host "[PERINGATAN] $msg" -ForegroundColor Yellow
}

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "   MENGHENTIKAN SERVER PERPUSTAKAAN SEKOLAH               " -ForegroundColor White
Write-Host "==========================================================" -ForegroundColor Cyan

Push-Location $ProjectRoot
try {
    # 1. Hentikan container dari compose.lan.yaml jika ada
    if (Test-Path "compose.lan.yaml") {
        Write-Info "Menghentikan container jaringan LAN (compose.lan.yaml)..."
        docker compose -f compose.lan.yaml stop
    }

    # 2. Periksa apakah masih ada container library-lan yang berjalan
    $runningLan = docker ps --filter "name=library-lan" --format "{{.Names}}"
    if ($runningLan) {
        Write-Info "Menurunkan jaringan container library-lan (tanpa menghapus data volume)..."
        docker compose -f compose.lan.yaml down
    }

    Write-Success "Seluruh layanan server perpustakaan berhasil dihentikan dengan aman."
    Write-Host ""
    Write-Host "Status Data:" -ForegroundColor White
    Write-Host "  - Database PostgreSQL : Tetap aman di volume 'library-system_sail-pgsql'" -ForegroundColor Gray
    Write-Host "  - Identitas & Logo    : Tetap aman di folder 'storage/app/'" -ForegroundColor Gray
    Write-Host ""
    Write-Host "Untuk menyalakan kembali, jalankan: .\launcher\Start-LibraryServer.ps1" -ForegroundColor Cyan
}
finally {
    Pop-Location
}
