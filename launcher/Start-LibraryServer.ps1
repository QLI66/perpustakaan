#Requires -Version 5.1
<#
.SYNOPSIS
    Launcher Portable Server Sistem Perpustakaan Sekolah (Windows 10/11 LAN).
.DESCRIPTION
    Menjalankan sistem perpustakaan berbasis Docker Desktop untuk jaringan LAN sekolah.
    - Caddy Reverse Proxy menjadi satu-satunya gerbang HTTP port 80.
    - PostgreSQL aman terisolasi di dalam jaringan Docker (tanpa publish port ke LAN).
    - Data database dan identitas sekolah persisten di Docker volume & host.
    - Menghindari migrasi otomatis tanpa persetujuan operator.
    - Membuka browser otomatis dan menampilkan instruksi akses komputer klien.
#>
[CmdletBinding()]
param(
    [int]$HttpPort = 80,
    [switch]$NoBrowser,
    [switch]$NonInteractive
)

$ErrorActionPreference = 'Stop'
[Console]::OutputEncoding = [System.Text.Encoding]::UTF8

$ProjectRoot = (Resolve-Path "$PSScriptRoot\..").Path
$BackupsDir = Join-Path $ProjectRoot "backups"
$EnvLanFile = Join-Path $ProjectRoot ".env.lan"
$EnvLanExample = Join-Path $ProjectRoot ".env.lan.example"
$EnvDevFile = Join-Path $ProjectRoot ".env"
$EnvWindowsFile = Join-Path $ProjectRoot ".env.windows"

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

function Clear-Banner {
    Write-Host ""
    Write-Host "=================================================================" -ForegroundColor Cyan
    Write-Host "   SISTEM INFORMASI PERPUSTAKAAN SEKOLAH - PORTABLE SERVER LAN   " -ForegroundColor White
    Write-Host "=================================================================" -ForegroundColor Cyan
    Write-Host ""
}

$dbPasswordLine = $null
if (Test-Path -LiteralPath $EnvLanFile) {
    $dbPasswordLine = Get-Content -LiteralPath $EnvLanFile |
        Where-Object { $_ -match '^DB_PASSWORD=' } |
        Select-Object -First 1
}

$dbPassword = $null
if ($dbPasswordLine -match '^DB_PASSWORD=(.*)$') {
    $dbPassword = $matches[1].Trim().Trim('"')
}
if (-not $dbPassword) {
    Write-Err "DB_PASSWORD harus diisi di .env.lan. Launcher menolak menggunakan password bawaan."
    exit 1
}

$nativeDbPasswordLine = $null
if (Test-Path -LiteralPath $EnvWindowsFile) {
    $nativeDbPasswordLine = Get-Content -LiteralPath $EnvWindowsFile |
        Where-Object { $_ -match '^DB_PASSWORD=' } |
        Select-Object -First 1
}
if ($nativeDbPasswordLine -notmatch '^DB_PASSWORD=(.+)$') {
    Write-Err "DB_PASSWORD database Windows harus diisi di .env.windows."
    exit 1
}
$nativeDbPasswordLine = $nativeDbPasswordLine -replace '^DB_PASSWORD=', 'NATIVE_DB_PASSWORD='

$appKey = $null
if (Test-Path -LiteralPath $EnvLanFile) {
    $keyLine = Get-Content -LiteralPath $EnvLanFile |
        Where-Object { $_ -match '^APP_KEY=(.+)$' } |
        Select-Object -First 1
    if ($keyLine -match '^APP_KEY=(.+)$') {
        $appKey = $matches[1].Trim()
    }
}
if (-not $appKey -and (Test-Path -LiteralPath $EnvDevFile)) {
    $keyLine = Get-Content -LiteralPath $EnvDevFile |
        Where-Object { $_ -match '^APP_KEY=(.+)$' } |
        Select-Object -First 1
    if ($keyLine -match '^APP_KEY=(.+)$') {
        $appKey = $matches[1].Trim()
    }
}
if (-not $appKey) {
    Write-Err "APP_KEY harus diisi di .env.lan atau .env. Launcher menolak menggunakan key bawaan."
    exit 1
}

Clear-Banner

# =====================================================================
# 1. PERIKSA DOCKER DESKTOP
# =====================================================================
Write-Info "Memeriksa status Docker Desktop..."

$dockerRunning = $false
try {
    $version = docker version --format '{{.Server.Version}}' 2>$null
    if ($LASTEXITCODE -eq 0 -and $version) {
        $dockerRunning = $true
        Write-Success "Docker Desktop terdeteksi berjalan (v$version)."
    }
} catch {
    $dockerRunning = $false
}

if (-not $dockerRunning) {
    Write-Warn "Docker Desktop belum berjalan."
    
    $dockerPath = "C:\Program Files\Docker\Docker\Docker Desktop.exe"
    if (Test-Path $dockerPath) {
        Write-Info "Mencoba menyalakan Docker Desktop secara otomatis..."
        Start-Process -FilePath $dockerPath
        
        Write-Info "Menunggu mesin Docker siap digunakan (maksimal 60 detik)..."
        $maxWait = 60
        for ($i = 1; $i -le $maxWait; $i++) {
            Start-Sleep -Seconds 1
            try {
                $ver = docker version --format '{{.Server.Version}}' 2>$null
                if ($LASTEXITCODE -eq 0 -and $ver) {
                    $dockerRunning = $true
                    Write-Success "Docker Desktop berhasil dinyalakan (v$ver)."
                    break
                }
            } catch {}
            Write-Progress -Activity "Menunggu Docker Desktop siap" -Status "$i / $maxWait detik" -PercentComplete (($i / $maxWait) * 100)
        }
        Write-Progress -Activity "Menunggu Docker Desktop siap" -Completed
    }
    
    if (-not $dockerRunning) {
        Write-Err "Tidak dapat menghubungkan ke Docker Desktop."
        Write-Host "Silakan buka aplikasi 'Docker Desktop' dari Start Menu terlebih dahulu," -ForegroundColor Yellow
        Write-Host "tunggu hingga ikon di taskbar berwarna hijau/putih stabil, lalu jalankan launcher ini kembali." -ForegroundColor Yellow
        exit 1
    }
}

# =====================================================================
# 2. DETEKSI IP LAN SERVER
# =====================================================================
Write-Info "Mendeteksi alamat IP LAN komputer server..."

$detectedIp = $null
$detectedInterface = "Localhost"

# Coba cari adapter dengan default gateway IPv4 aktif
try {
    $route = Get-NetRoute -DestinationPrefix '0.0.0.0/0' -AddressFamily IPv4 -ErrorAction SilentlyContinue |
             Sort-Object RouteMetric | Select-Object -First 1
    if ($route) {
        $ipObj = Get-NetIPAddress -InterfaceIndex $route.InterfaceIndex -AddressFamily IPv4 -ErrorAction SilentlyContinue |
                 Where-Object { $_.IPAddress -notmatch "^127\.|^169\.254\." } | Select-Object -First 1
        if ($ipObj) {
            $detectedIp = $ipObj.IPAddress
            $aliasObj = Get-NetIPInterface -InterfaceIndex $route.InterfaceIndex -ErrorAction SilentlyContinue | Select-Object -First 1
            $detectedInterface = if ($aliasObj) { $aliasObj.InterfaceAlias } else { "LAN" }
        }
    }
} catch {}

# Fallback ke pencarian adapter fisik/Wi-Fi
if (-not $detectedIp) {
    try {
        $adapters = Get-NetIPAddress -AddressFamily IPv4 -ErrorAction SilentlyContinue |
                    Where-Object {
                        $_.InterfaceAlias -notmatch "Loopback|vEthernet|WSL|Virtual|Default Switch" -and
                        $_.IPAddress -notmatch "^127\.|^169\.254\."
                    } | Sort-Object -Property @{Expression={$_.InterfaceAlias -like "*Ethernet*"}; Descending=$true},
                                              @{Expression={$_.InterfaceAlias -like "*Wi-Fi*"}; Descending=$true}
        $firstAdapter = $adapters | Select-Object -First 1
        if ($firstAdapter) {
            $detectedIp = $firstAdapter.IPAddress
            $detectedInterface = $firstAdapter.InterfaceAlias
        }
    } catch {}
}

if (-not $detectedIp) {
    $detectedIp = "127.0.0.1"
    $detectedInterface = "Loopback (Offline Standalone)"
    Write-Warn "Tidak terdeteksi koneksi jaringan aktif. Menggunakan localhost (127.0.0.1)."
} else {
    Write-Success "IP LAN terdeteksi: $detectedIp (Adapter: $detectedInterface)"
}

# =====================================================================
# 3. PERIKSA KONFLIK PORT & KESIAPAN ENVIRONMENT
# =====================================================================
Write-Info "Memeriksa port $HttpPort..."

Push-Location $ProjectRoot
try {
    # Periksa apakah container development (compose.yaml) sedang aktif
    $devContainers = docker ps --filter "name=library-system" --format "{{.Names}}"
    $hasDevRunning = $false
    if ($devContainers) {
        foreach ($dName in $devContainers) {
            if ($dName -match "laravel\.test|nuxt|pgsql") {
                $hasDevRunning = $true
                break
            }
        }
    }

    if ($hasDevRunning) {
        Write-Warn "Layanan development (compose.yaml) sedang aktif dan menggunakan port host."
        Write-Info "Mengalihkan ke mode LAN: Menonaktifkan container development sementara..."
        Write-Info "(Data PostgreSQL di volume tetap aman dan tidak disentuh)"
        docker compose -f compose.yaml down | Out-Null
        Start-Sleep -Seconds 2
        Write-Success "Container development dihentikan dengan aman."
    }

    # Periksa ketersediaan port 80 di host
    $portListener = Get-NetTCPConnection -LocalPort $HttpPort -State Listen -ErrorAction SilentlyContinue | Select-Object -First 1
    if ($portListener) {
        $pId = $portListener.OwningProcess
        $pName = "Unknown"
        try { $pName = (Get-Process -Id $pId -ErrorAction SilentlyContinue).ProcessName } catch {}
        
        Write-Err "Port $HttpPort saat ini sedang digunakan oleh proses lain: $pName (PID: $pId)."
        Write-Host "Kemungkinan penyebab: Web Server lokal (IIS/Apache/Nginx) atau aplikasi lain." -ForegroundColor Yellow
        Write-Host "Silakan hentikan proses pemakai port $HttpPort atau jalankan launcher dengan parameter -HttpPort <nomor_port> (misal: -HttpPort 8080)." -ForegroundColor Yellow
        exit 1
    }

    # =====================================================================
    # 4. KELOLA FILE .env.lan
    # =====================================================================
    Write-Info "Menyiapkan file konfigurasi LAN (.env.lan)..."

    $urlSuffix = if ($HttpPort -eq 80) { "" } else { ":$HttpPort" }
    $appUrl = "http://${detectedIp}${urlSuffix}"
    $apiBaseUrl = "http://${detectedIp}${urlSuffix}/api"

    $envLanContent = @"
# =====================================================================
# SISTEM PERPUSTAKAAN SEKOLAH - KONFIGURASI SERVER LAN (OTOMATIS)
# Dibuat/Diperbarui otomatis oleh Start-LibraryServer.ps1
# Waktu: $(Get-Date -Format 'yyyy-MM-dd HH:mm:ss')
# =====================================================================

APP_NAME="Sistem Informasi Perpustakaan"
APP_ENV=production
APP_DEBUG=false
APP_KEY=$appKey
APP_URL=$appUrl

HTTP_PORT=$HttpPort
HTTP_BIND_IP=0.0.0.0

DB_CONNECTION=pgsql
DB_HOST=database
DB_PORT=5432
DB_DATABASE=laravel
DB_USERNAME=sail
$dbPasswordLine

NATIVE_DB_HOST=host.docker.internal
NATIVE_DB_PORT=5434
NATIVE_DB_DATABASE=laravel
NATIVE_DB_USERNAME=sail
$nativeDbPasswordLine

SESSION_DRIVER=database
SESSION_LIFETIME=120
SESSION_ENCRYPT=true
SESSION_SECURE_COOKIE=false
SESSION_SAME_SITE=lax

CACHE_STORE=database
QUEUE_CONNECTION=database
FILESYSTEM_DISK=local
LOG_CHANNEL=daily
LOG_LEVEL=warning

NUXT_PUBLIC_API_BASE_URL=$apiBaseUrl
WWWUSER=1000
"@
    $envLanContent | Out-File -FilePath $EnvLanFile -Encoding UTF8 -Force
    Write-Success "File .env.lan berhasil disinkronkan ke IP LAN: $detectedIp"

    # =====================================================================
    # 5. JALANKAN CONTAINER COMPOSE LAN
    # =====================================================================
    Write-Info "Memulai layanan server (Caddy, Backend, Nuxt SSR, Scheduler, PostgreSQL)..."
    docker compose -f compose.lan.yaml --env-file .env.lan up -d

    if ($LASTEXITCODE -ne 0) {
        throw "Gagal menjalankan docker compose up pada compose.lan.yaml."
    }

    # =====================================================================
    # 6. POLLING KESIAPAN LAYANAN (HEALTHCHECKS)
    # =====================================================================
    Write-Info "Menunggu seluruh layanan siap melayani permintaan..."
    
    $maxWaitSec = 45
    $allHealthy = $false

    for ($sec = 1; $sec -le $maxWaitSec; $sec++) {
        Start-Sleep -Seconds 1
        
        # Periksa Caddy reverse proxy via localhost
        $caddyReady = $false
        try {
            $resp = Invoke-WebRequest -Uri "http://127.0.0.1${urlSuffix}/api/settings/school-identity" -TimeoutSec 2 -UseBasicParsing -ErrorAction SilentlyContinue
            if ($resp -and $resp.StatusCode -eq 200) {
                $caddyReady = $true
            }
        } catch {}

        # Periksa frontend
        $frontendReady = $false
        try {
            $respFe = Invoke-WebRequest -Uri "http://127.0.0.1${urlSuffix}/" -TimeoutSec 2 -UseBasicParsing -ErrorAction SilentlyContinue
            if ($respFe -and $respFe.StatusCode -eq 200) {
                $frontendReady = $true
            }
        } catch {}

        if ($caddyReady -and $frontendReady) {
            $allHealthy = $true
            break
        }
        
        Write-Progress -Activity "Inisialisasi sistem perpustakaan" -Status "Memeriksa kesiapan layanan ($sec/$maxWaitSec detik)..." -PercentComplete (($sec / $maxWaitSec) * 100)
    }
    Write-Progress -Activity "Inisialisasi sistem perpustakaan" -Completed

    if (-not $allHealthy) {
        Write-Warn "Layanan memerlukan waktu inisialisasi lebih lama. Memeriksa status container:"
        docker compose -f compose.lan.yaml ps
    } else {
        Write-Success "Seluruh layanan perpustakaan telah siap dan terverifikasi sehat!"
    }

    # =====================================================================
    # 7. TAMPILKAN BANNER AKSES DAN BUKA BROWSER
    # =====================================================================
    # Ambil nama sekolah dari API jika tersedia
    $schoolName = "Perpustakaan Sekolah"
    try {
        $identityResp = Invoke-RestMethod -Uri "http://127.0.0.1${urlSuffix}/api/settings/school-identity" -TimeoutSec 2 -ErrorAction SilentlyContinue
        if ($identityResp -and $identityResp.data -and $identityResp.data.school_name) {
            $schoolName = $identityResp.data.school_name
        }
    } catch {}

    Write-Host ""
    Write-Host "=================================================================" -ForegroundColor Green
    Write-Host "     SISTEM PERPUSTAKAAN SEKOLAH TELAH BERJALAN AKTIF!           " -ForegroundColor White
    Write-Host "=================================================================" -ForegroundColor Green
    Write-Host ""
    Write-Host "  Identitas Sekolah : $schoolName" -ForegroundColor White
    Write-Host "  Status Layanan    : Caddy Proxy, Laravel API, Nuxt SSR, PostgreSQL" -ForegroundColor Gray
    Write-Host ""
    Write-Host "  [AKSES KOMPUTER SERVER (PC INI)]" -ForegroundColor Yellow
    Write-Host "  >> http://localhost${urlSuffix} (atau http://localhost:3000)" -ForegroundColor Cyan
    Write-Host ""
    Write-Host "  [AKSES KOMPUTER KLIEN / ANGGOTA (JARINGAN LAN)]" -ForegroundColor Yellow
    Write-Host "  >> http://${detectedIp}${urlSuffix} (atau http://${detectedIp}:3000)" -ForegroundColor Green
    Write-Host ""
    Write-Host "  Petunjuk Operator:" -ForegroundColor White
    Write-Host "  1. Bagikan alamat LAN (http://${detectedIp} atau http://${detectedIp}:3000) ke komputer staf & anggota." -ForegroundColor Gray
    Write-Host "  2. Pastikan komputer klien terhubung ke jaringan Wi-Fi/LAN yang sama ($detectedInterface)." -ForegroundColor Gray
    Write-Host "  3. Jangan menutup jendela terminal ini selama layanan perpustakaan digunakan." -ForegroundColor Gray
    Write-Host "  4. Database terisolasi aman (port 5432 tidak dapat diakses langsung dari LAN)." -ForegroundColor Gray
    Write-Host "=================================================================" -ForegroundColor Green
    Write-Host ""

    # Buka browser otomatis jika tidak dinonaktifkan
    if (-not $NoBrowser) {
        Write-Info "Membuka halaman utama perpustakaan di browser default..."
        Start-Process "http://localhost${urlSuffix}"
    }

    # =====================================================================
    # 8. MENUNGGU INPUT OPERATOR / PENANGANAN SHUTDOWN
    # =====================================================================
    if ($NonInteractive) {
        Write-Success "Mode NonInteractive aktif: Seluruh layanan server LAN berjalan di latar belakang."
        return
    }

    Write-Host "Server sedang melayani pengguna. Tekan [Q] lalu Enter atau [Ctrl+C] untuk menghentikan server..." -ForegroundColor Magenta
    
    while ($true) {
        $inputKey = Read-Host
        if ($inputKey -match "^[qQ]$") {
            break
        }
    }

    Write-Info "Permintaan penghentian diterima..."
    $stopScript = Join-Path $PSScriptRoot "Stop-LibraryServer.ps1"
    if (Test-Path $stopScript) {
        & powershell.exe -ExecutionPolicy Bypass -File $stopScript
    } else {
        docker compose -f compose.lan.yaml stop
    }

} catch {
    Write-Err "Terjadi kesalahan saat menjalankan server: $_"
    exit 1
} finally {
    Pop-Location
}
