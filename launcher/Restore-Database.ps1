#Requires -Version 5.1
<#
.SYNOPSIS
    Script pemulihan (restore) database PostgreSQL Sistem Perpustakaan.
.DESCRIPTION
    Memulihkan database dari file dump SQL cadangan.
    Menyediakan verifikasi keselamatan:
    1. Membuat salinan keselamatan otomatis (safety backup) sebelum restore.
    2. Mengharuskan konfirmasi tertulis eksplisit dari operator.
    3. Menghentikan layanan backend sementara selama proses restore berlangsung.
.PARAMETER BackupFile
    Path ke file .sql yang ingin dipulihkan. Jika dikosongkan, menampilkan daftar file tersedia.
#>
[CmdletBinding()]
param(
    [string]$BackupFile
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$ProjectRoot = (Resolve-Path "$PSScriptRoot\..").Path
$BackupsDir = Join-Path $ProjectRoot "backups"

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

function Test-PostgresDump([string]$Path) {
    if (-not (Test-Path -LiteralPath $Path -PathType Leaf)) {
        throw "File backup tidak ditemukan: $Path"
    }

    $item = Get-Item -LiteralPath $Path
    if ($item.Length -lt 500) {
        throw "File backup terlalu kecil atau kosong."
    }

    $stream = [System.IO.File]::OpenRead($Path)
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
        throw "File backup tidak memiliki header/footer SQL yang lengkap."
    }
}

Write-Host "==========================================================" -ForegroundColor Red
Write-Host "   PEMULIHAN (RESTORE) DATABASE SISTEM PERPUSTAKAAN       " -ForegroundColor White
Write-Host "==========================================================" -ForegroundColor Red

# 1. Pilih file backup
if (-not $BackupFile) {
    if (-not (Test-Path $BackupsDir)) {
        Write-Err "Direktori backup tidak ditemukan: $BackupsDir"
        exit 1
    }
    
    $backupFiles = Get-ChildItem -Path $BackupsDir -Filter "*.sql" | Sort-Object LastWriteTime -Descending
    if (-not $backupFiles -or $backupFiles.Count -eq 0) {
        Write-Err "Tidak ada file backup (.sql) yang ditemukan di: $BackupsDir"
        exit 1
    }
    
    Write-Host "Daftar File Cadangan Tersedia:" -ForegroundColor White
    for ($i = 0; $i -lt $backupFiles.Count; $i++) {
        $f = $backupFiles[$i]
        $sizeKb = [math]::Round($f.Length / 1KB, 2)
        Write-Host "  [$($i + 1)] $($f.Name) ($sizeKb KB) - $($f.LastWriteTime.ToString('dd/MM/yyyy HH:mm:ss'))" -ForegroundColor Gray
    }
    Write-Host ""
    
    $choice = Read-Host "Pilih nomor file cadangan yang ingin dipulihkan (1-$($backupFiles.Count)) atau tekan Enter untuk batal"
    if (-not $choice -or -not ($choice -match '^\d+$') -or [int]$choice -lt 1 -or [int]$choice -gt $backupFiles.Count) {
        Write-Warn "Pemulihan dibatalkan. Tidak ada file yang dipilih."
        exit 0
    }
    
    $selectedItem = $backupFiles[[int]$choice - 1]
    $BackupFile = $selectedItem.FullName
}

if (-not (Test-Path -LiteralPath $BackupFile)) {
    Write-Err "File backup tidak ditemukan: $BackupFile"
    exit 1
}

$BackupFile = (Resolve-Path -LiteralPath $BackupFile).Path
try {
    Test-PostgresDump $BackupFile
}
catch {
    Write-Err "File backup tidak valid: $_"
    exit 1
}

$fileItem = Get-Item $BackupFile
$sizeKb = [math]::Round($fileItem.Length / 1KB, 2)

# 2. Tampilkan Peringatan Kritis & Konfirmasi Tertulis
Write-Host ""
Write-Host "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!" -ForegroundColor Yellow
Write-Host "                      PERINGATAN KRITIS                   " -ForegroundColor Red
Write-Host "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!" -ForegroundColor Yellow
Write-Host "Prosedur ini akan MENIMPA database 'laravel' pada layanan database LAN." -ForegroundColor White
Write-Host "Target database akan diverifikasi sebelum pemulihan dimulai." -ForegroundColor White
Write-Host "Sumber data:" -ForegroundColor White
Write-Host "  File   : $($fileItem.Name)" -ForegroundColor Cyan
Write-Host "  Ukuran : $sizeKb KB" -ForegroundColor Cyan
Write-Host "  Waktu  : $($fileItem.LastWriteTime.ToString('dd/MM/yyyy HH:mm:ss'))" -ForegroundColor Cyan
Write-Host "!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!!" -ForegroundColor Yellow
Write-Host ""
Write-Host "Ketik kata 'PULIHKAN' (huruf besar) untuk melanjutkan," -ForegroundColor Yellow
$confirm = Read-Host "atau tekan sembarang tombol lain untuk membatalkan"

if ($confirm -ne "PULIHKAN") {
    Write-Warn "Konfirmasi tidak sesuai ('$confirm'). Pemulihan DIBATALKAN."
    Write-Info "Database aktif tetap aman dan tidak mengalami perubahan."
    exit 0
}

# 3. Pastikan target adalah database dari stack LAN yang dimaksud
$activeContainer = $null
$runningContainers = docker ps --filter "name=library-lan-database" --format "{{.Names}}"
if ($LASTEXITCODE -ne 0) {
    Write-Err "Tidak dapat memeriksa container Docker."
    exit 1
}
$activeContainer = $runningContainers | Select-Object -First 1

if (-not $activeContainer) {
    Write-Err "Container database LAN tidak berjalan. Tidak ada database yang diubah."
    exit 1
}

$composeLabels = docker inspect --format '{{index .Config.Labels "com.docker.compose.project"}}|{{index .Config.Labels "com.docker.compose.service"}}' $activeContainer
if ($LASTEXITCODE -ne 0 -or $composeLabels.Trim() -ne "library-lan|database") {
    Write-Err "Container yang ditemukan bukan layanan database library-lan. Restore dibatalkan."
    exit 1
}

$targetDatabase = docker exec $activeContainer psql -U sail -d laravel -tAc "SELECT current_database()"
if ($LASTEXITCODE -ne 0 -or $targetDatabase.Trim() -ne "laravel") {
    Write-Err "Target PostgreSQL tidak terverifikasi sebagai database 'laravel'. Restore dibatalkan."
    exit 1
}

Write-Info "Menggunakan container database: $activeContainer"

$composeFile = Join-Path $ProjectRoot "compose.lan.yaml"
$servicesToRestart = @()
foreach ($service in @("backend", "scheduler")) {
    $serviceContainer = docker ps `
        --filter "label=com.docker.compose.project=library-lan" `
        --filter "label=com.docker.compose.service=$service" `
        --filter "status=running" `
        --format "{{.Names}}"
    if ($LASTEXITCODE -ne 0) {
        throw "Tidak dapat memeriksa status layanan $service."
    }
    if ($serviceContainer) {
        $servicesToRestart += $service
    }
}

# 4. Ambil backup keselamatan otomatis sebelum menimpa data
Write-Host ""
Write-Info "Membuat backup keselamatan otomatis (pre-restore safety snapshot)..."
$safetyBackupScript = Join-Path $PSScriptRoot "Backup-Database.ps1"
if (-not (Test-Path -LiteralPath $safetyBackupScript)) {
    Write-Err "Skrip safety backup tidak ditemukan. Restore dibatalkan."
    exit 1
}

$backupNamesBefore = @()
if (Test-Path -LiteralPath $BackupsDir) {
    $backupNamesBefore = @(Get-ChildItem -LiteralPath $BackupsDir -Filter "library_backup_*.sql" -File | Select-Object -ExpandProperty Name)
}
$safetyStartedAt = Get-Date
& powershell.exe -NoProfile -ExecutionPolicy Bypass -File $safetyBackupScript -OutputDir $BackupsDir | Out-Null
$safetyExitCode = $LASTEXITCODE
if ($safetyExitCode -ne 0) {
    throw "Safety backup gagal dengan exit code $safetyExitCode. Restore dibatalkan."
}

$safetyFile = Get-ChildItem -LiteralPath $BackupsDir -Filter "library_backup_*.sql" -File |
    Where-Object { $_.Name -notin $backupNamesBefore -and $_.LastWriteTime -ge $safetyStartedAt } |
    Sort-Object LastWriteTime -Descending |
    Select-Object -First 1
if (-not $safetyFile) {
    throw "Safety backup tidak menghasilkan file baru. Restore dibatalkan."
}
Test-PostgresDump $safetyFile.FullName

$metadataPath = Join-Path $BackupsDir "latest_backup.txt"
$metadataHash = $null
if (Test-Path -LiteralPath $metadataPath) {
    $hashLine = Get-Content -LiteralPath $metadataPath | Where-Object { $_ -match '^SHA256=' } | Select-Object -First 1
    if ($hashLine) {
        $metadataHash = ($hashLine -split '=', 2)[1].Trim()
    }
}
$safetyHash = (Get-FileHash -LiteralPath $safetyFile.FullName -Algorithm SHA256).Hash
if (-not $metadataHash -or $metadataHash -ne $safetyHash) {
    throw "Checksum safety backup tidak valid. Restore dibatalkan."
}
Write-Success "Safety snapshot terverifikasi: $($safetyFile.Name) (SHA-256 cocok)."

# 5. Hentikan proses yang dapat menulis database selama restore
$servicesRestartFailed = $false
$restoreCompleted = $false
try {
    $servicesMayNeedRestart = $true
    docker compose -f $composeFile stop backend scheduler
    if ($LASTEXITCODE -ne 0) {
        throw "Gagal menghentikan backend/scheduler. Restore dibatalkan."
    }

    # 6. Eksekusi restore via psql, dengan berhenti pada kesalahan SQL pertama
    Write-Info "Memulai pemulihan data ke database 'laravel'..."
    $dockerPath = (Get-Command docker -ErrorAction Stop).Source
    $startInfo = New-Object System.Diagnostics.ProcessStartInfo
    $startInfo.FileName = $dockerPath
    $startInfo.Arguments = "exec -i $activeContainer psql -U sail -d laravel -q --set=ON_ERROR_STOP=1"
    $startInfo.UseShellExecute = $false
    $startInfo.CreateNoWindow = $true
    $startInfo.RedirectStandardInput = $true
    $startInfo.RedirectStandardOutput = $true
    $startInfo.RedirectStandardError = $true

    $process = New-Object System.Diagnostics.Process
    $process.StartInfo = $startInfo
    if (-not $process.Start()) {
        throw "Tidak dapat menjalankan psql."
    }

    $stdoutTask = $process.StandardOutput.ReadToEndAsync()
    $stderrTask = $process.StandardError.ReadToEndAsync()
    $backupStream = [System.IO.File]::OpenRead($BackupFile)
    try {
        $backupStream.CopyTo($process.StandardInput.BaseStream)
    }
    finally {
        $backupStream.Dispose()
        $process.StandardInput.Close()
    }
    $process.WaitForExit()
    $null = $stdoutTask.GetAwaiter().GetResult()
    $stderr = $stderrTask.GetAwaiter().GetResult()
    if ($process.ExitCode -ne 0) {
        throw "Perintah psql keluar dengan kode error $($process.ExitCode). $stderr"
    }

    Write-Host ""
    Write-Host "Verifikasi data aktif:" -ForegroundColor White
    $verifiedDatabase = docker exec $activeContainer psql -U sail -d laravel --set=ON_ERROR_STOP=1 -tAc "SELECT current_database()"
    if ($LASTEXITCODE -ne 0 -or $verifiedDatabase.Trim() -ne "laravel") {
        throw "Verifikasi database setelah restore gagal."
    }
    $tableCount = docker exec $activeContainer psql -U sail -d laravel --set=ON_ERROR_STOP=1 -tAc "SELECT count(*) FROM information_schema.tables WHERE table_schema='public';"
    if ($LASTEXITCODE -ne 0) { throw "Verifikasi jumlah tabel gagal." }
    $userCount = docker exec $activeContainer psql -U sail -d laravel --set=ON_ERROR_STOP=1 -tAc "SELECT count(*) FROM users;"
    if ($LASTEXITCODE -ne 0) { throw "Verifikasi jumlah pengguna gagal." }
    $bookCount = docker exec $activeContainer psql -U sail -d laravel --set=ON_ERROR_STOP=1 -tAc "SELECT count(*) FROM books;"
    if ($LASTEXITCODE -ne 0) { throw "Verifikasi jumlah buku gagal." }
    
    Write-Host "  - Jumlah tabel publik : $($tableCount.Trim())" -ForegroundColor Gray
    Write-Host "  - Jumlah pengguna     : $($userCount.Trim())" -ForegroundColor Gray
    Write-Host "  - Jumlah judul buku   : $($bookCount.Trim())" -ForegroundColor Gray
    Write-Host ""
    Write-Success "Database berhasil dipulihkan dan diverifikasi: $($fileItem.Name)!"
    $restoreCompleted = $true
}
catch {
    Write-Err "Gagal memulihkan database: $_"
    Write-Warn "Jika terjadi kesalahan, Anda dapat memulihkan salinan snapshot keselamatan terbaru di folder backups/."
    exit 1
}
finally {
    if ($process) {
        $process.Dispose()
    }
    if ($servicesMayNeedRestart) {
        foreach ($service in $servicesToRestart) {
            docker compose -f $composeFile start $service
            if ($LASTEXITCODE -ne 0) {
                Write-Err "Gagal menyalakan kembali layanan $service setelah restore."
                $servicesRestartFailed = $true
            }
        }
    }
}

if ($servicesRestartFailed) {
    exit 1
}
if ($restoreCompleted) {
    Write-Success "Sistem siap digunakan kembali."
}
