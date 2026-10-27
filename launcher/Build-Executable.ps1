# Script untuk mengompilasi ulang Perpustakaan.exe
$ErrorActionPreference = 'Stop'

$ProjectDir = Split-Path -Parent $PSScriptRoot
Set-Location $ProjectDir

$CscPath = "C:\Windows\Microsoft.NET\Framework64\v4.0.30319\csc.exe"
if (-not (Test-Path $CscPath)) {
    throw "Kompiler C# tidak ditemukan di: $CscPath"
}

Write-Host "Mengompilasi launcher Perpustakaan.exe..." -ForegroundColor Cyan

& $CscPath /target:winexe /out:Perpustakaan.exe /r:System.dll,System.Drawing.dll,System.Windows.Forms.dll,System.Core.dll launcher\Program.cs

if ($LASTEXITCODE -eq 0 -and (Test-Path "Perpustakaan.exe")) {
    Write-Host "Kompilasi BERHASIL: $(Resolve-Path Perpustakaan.exe)" -ForegroundColor Green
} else {
    throw "Kompilasi GAGAL."
}
