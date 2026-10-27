#Requires -Version 5.1
<#
.SYNOPSIS
    Script pencadangan (backup) database PostgreSQL Sistem Perpustakaan.
.DESCRIPTION
    Menghasilkan dump SQL terkompresi/lengkap dari database PostgreSQL yang aman
    digunakan sebelum pembaruan aplikasi, migrasi, atau pemulihan data.
.PARAMETER OutputDir
    Direktori tujuan penyimpanan file backup (default: ..\backups).
.PARAMETER Quiet
    Mode senyap untuk otomatisasi tanpa konfirmasi interaktif.
#>
[CmdletBinding()]
param(
    [string]$OutputDir,
    [switch]$Quiet
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$ProjectRoot = (Resolve-Path "$PSScriptRoot\..").Path

if (-not $OutputDir) {
    $OutputDir = Join-Path $ProjectRoot "backups"
}

if (-not (Test-Path $OutputDir)) {
    New-Item -ItemType Directory -Path $OutputDir -Force | Out-Null
}

function Write-Info([string]$msg) {
    Write-Host "[INFO] $msg" -ForegroundColor Cyan
}

function Write-Success([string]$msg) {
    Write-Host "[SUKSES] $msg" -ForegroundColor Green
}

function Write-Warn([string]$msg) {
    Write-Host "[PERINGATAN] $msg" -ForegroundColor Yellow
}

function Write-Err([string]$msg) {
    Write-Host "[ERROR] $msg" -ForegroundColor Red
}

Write-Host "==========================================================" -ForegroundColor Cyan
Write-Host "   BACKUP DATABASE SISTEM PERPUSTAKAAN SEKOLAH            " -ForegroundColor White
Write-Host "==========================================================" -ForegroundColor Cyan

# 1. Cari container database yang sedang berjalan
$activeContainer = $null
$runningContainers = docker ps --filter "name=library-lan-database" --format "{{.Names}}"
if ($LASTEXITCODE -ne 0) {
    throw "Tidak dapat memeriksa container Docker."
}
$activeContainer = $runningContainers | Select-Object -First 1

$tempStarted = $false
if (-not $activeContainer) {
    Write-Warn "Container database tidak sedang berjalan."
    if (-not (Test-Path -LiteralPath (Join-Path $ProjectRoot ".env.lan"))) {
        throw "File .env.lan tidak ditemukan; container database tidak dijalankan dengan konfigurasi default."
    }
    Write-Info "Menjalankan container database sementara melalui compose.lan.yaml..."
    
    Push-Location $ProjectRoot
    try {
        docker compose --env-file .env.lan -f compose.lan.yaml up -d database | Out-Null
        if ($LASTEXITCODE -ne 0) {
            throw "Gagal menjalankan container database LAN."
        }
        $tempStarted = $true
        $activeContainer = docker ps --filter "name=library-lan-database" --format "{{.Names}}" | Select-Object -First 1
        if ($LASTEXITCODE -ne 0 -or -not $activeContainer) {
            throw "Container database LAN tidak muncul setelah compose up."
        }
        
        # Tunggu ready
        $ready = $false
        for ($i = 0; $i -lt 30; $i++) {
            $isReady = docker exec $activeContainer pg_isready -q -U sail -d laravel 2>$null
            if ($LASTEXITCODE -eq 0) {
                $ready = $true
                break
            }
            Start-Sleep -Seconds 1
        }
        
        if (-not $ready) {
            throw "Database tidak siap dalam waktu 30 detik."
        }
    }
    finally {
        Pop-Location
    }
}

$composeLabels = docker inspect --format '{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' $activeContainer
if ($LASTEXITCODE -ne 0 -or $composeLabels.Trim() -ne "library-lan|database") {
    throw "Container yang dipilih bukan layanan database library-lan."
}
$targetDatabase = docker exec $activeContainer psql -U sail -d laravel -tAc "SELECT current_database()"
if ($LASTEXITCODE -ne 0 -or $targetDatabase.Trim() -ne "laravel") {
    throw "Target PostgreSQL tidak terverifikasi sebagai database 'laravel'."
}

Write-Info "Menggunakan container database: $activeContainer"

# 2. Persiapkan nama file backup
$timestamp = Get-Date -Format "yyyyMMdd_HHmmss"
$fileName = "library_backup_$timestamp.sql"
$filePath = Join-Path $OutputDir $fileName
$createdFile = $false

Write-Info "Membuat salinan database ke: $fileName..."

# 3. Jalankan pg_dump dan salin stdout sebagai byte agar kegagalan proses
#    maupun perubahan encoding PowerShell tidak menghasilkan backup palsu.
try {
    $dockerPath = (Get-Command docker -ErrorAction Stop).Source
    $startInfo = New-Object System.Diagnostics.ProcessStartInfo
    $startInfo.FileName = $dockerPath
    $startInfo.Arguments = "exec $activeContainer pg_dump -U sail -d laravel --clean --if-exists --no-owner --no-privileges"
    $startInfo.UseShellExecute = $false
    $startInfo.CreateNoWindow = $true
    $startInfo.RedirectStandardOutput = $true
    $startInfo.RedirectStandardError = $true

    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $startInfo
    if (-not $process.Start()) {
        throw "Tidak dapat menjalankan pg_dump."
    }

    $stderrTask = $process.StandardError.ReadToEndAsync()
    $outputStream = [System.IO.File]::Open($filePath, [System.IO.FileMode]::CreateNew, [System.IO.FileAccess]::Write, [System.IO.FileShare]::None)
    $createdFile = $true
    try {
        $process.StandardOutput.BaseStream.CopyTo($outputStream)
    }
    finally {
        $outputStream.Dispose()
    }
    $process.WaitForExit()
    $stderr = $stderrTask.GetAwaiter().GetResult()
    if ($process.ExitCode -ne 0) {
        throw "pg_dump gagal dengan exit code $($process.ExitCode). $stderr"
    }

    $fileItem = Get-Item $filePath
    if ($fileItem.Length -lt 500) {
        throw "Hasil dump database kosong atau terlalu kecil."
    }

    $stream = [System.IO.File]::OpenRead($filePath)
    try {
        $prefixLength = [Math]::Min(512, [int]$stream.Length)
        $prefixBytes = New-Object byte[] $prefixLength
        [void]$stream.Read($prefixBytes, 0, $prefixLength)
        $tailLength = [Math]::Min(4096, [int]$stream.Length)
        [void]$stream.Seek(-$tailLength, [System.IO.SeekOrigin]::End)
        $tailBytes = New-Object byte[] $tailLength
        [void]$stream.Read($tailBytes, 0, $tailLength)
        $header = [System.Text.Encoding]::UTF8.GetString($prefixBytes)
        $tail = [System.Text.Encoding]::UTF8.GetString($tailBytes)
    }
    finally {
        $stream.Dispose()
    }

    if (-not $header.StartsWith("-- PostgreSQL database dump") -or $tail -notmatch "PostgreSQL database dump complete") {
        throw "File hasil pg_dump tidak memiliki header/footer SQL yang lengkap."
    }

    $sizeKb = [math]::Round($fileItem.Length / 1KB, 2)
    $hash = (Get-FileHash -Path $filePath -Algorithm SHA256).Hash
    
    Write-Success "Backup berhasil diselesaikan!"
    Write-Host ""
    Write-Host "Detail File Backup:" -ForegroundColor White
    Write-Host "  - Lokasi   : $filePath" -ForegroundColor Gray
    Write-Host "  - Ukuran   : $sizeKb KB" -ForegroundColor Gray
    Write-Host "  - SHA256   : $hash" -ForegroundColor Gray
    Write-Host "  - Waktu    : $(Get-Date -Format 'dd/MM/yyyy HH:mm:ss')" -ForegroundColor Gray
    Write-Host ""
    
    # Simpan info metadata
    $metaFile = Join-Path $OutputDir "latest_backup.txt"
    $metaContent = @"
FileName=$fileName
Timestamp=$timestamp
SizeKB=$sizeKb
SHA256=$hash
Container=$activeContainer
"@
    $metaContent | Out-File -FilePath $metaFile -Encoding UTF8 -Force
    
    return $filePath
}
catch {
    Write-Err "Gagal membuat backup database: $_"
    if ($createdFile -and (Test-Path $filePath)) {
        Remove-Item -Path $filePath -Force -ErrorAction SilentlyContinue
    }
    exit 1
}
finally {
    if ($process) {
        $process.Dispose()
    }
    if ($tempStarted) {
        Write-Info "Menghentikan container database sementara..."
        Push-Location $ProjectRoot
        docker compose -f compose.lan.yaml stop database | Out-Null
        Pop-Location
    }
}
