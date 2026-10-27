# Panduan Penggunaan Aplikasi Windows EXE - Sistem Perpustakaan Sekolah

Dokumen ini berisi panduan penggunaan aplikasi desktop Windows mandiri (Native Windows) untuk **Sistem Informasi Perpustakaan Sekolah MAS PERSIS 84 Ciganitri**.

---

## 1. Ringkasan Fitur & Arsitektur

Aplikasi ini dapat dijalankan langsung melalui shortcut desktop atau berkas executable tanpa memerlukan Docker, PowerShell, maupun perintah terminal manual:

* **File Executable Utama**: `C:\Users\AMU66\library-system\Perpustakaan.exe`
* **Shortcut Desktop**: `Sistem Perpustakaan.lnk` di Desktop Windows.
* **Shortcut Berhenti**: `Hentikan Perpustakaan.lnk` di Desktop Windows.
* **Database Persisten**: PostgreSQL 18.6 Native pada port `5434` (data tersimpan aman di `D:\LibrarySystemRuntime\postgresql-data`).
* **Backend API**: Laravel pada port `8000` (`.env.windows`).
* **Frontend Web**: Nuxt 3 Production pada port `3005`.
* **System Tray**: Aplikasi berjalan di latar belakang tanpa jendela console CMD hitam. Ikon perpustakaan tersedia di area notifikasi (Taskbar / System Tray) Windows.

---

## 2. Cara Menjalankan Aplikasi

1. **Buka Aplikasi**:
   * Cukup klik ganda (double-click) shortcut **Sistem Perpustakaan** di Desktop Windows, atau berkas `Perpustakaan.exe` di folder proyek.
2. **Otomatisasi Latar Belakang**:
   * Aplikasi akan memeriksa dan otomatis menyalakan PostgreSQL (port 5434), Laravel Backend (port 8000), dan Nuxt Frontend (port 3005).
   * Browser default Windows akan otomatis terbuka langsung ke halaman login: `http://127.0.0.1:3005/login`.
3. **Ikon System Tray**:
   * Di sudut kanan bawah taskbar (System Tray), terdapat ikon Sistem Perpustakaan.
   * Klik kanan ikon untuk membuka menu cepat:
     * **Buka Perpustakaan di Browser**: Membuka kembali halaman aplikasi.
     * **Status Layanan**: Memeriksa kondisi database, backend, dan frontend.
     * **Cadangkan Database (Backup)**: Membuat backup instan ke folder `D:\LibrarySystemRuntime\backups`.
     * **Buka Folder Backup**: Membuka Windows Explorer ke direktori backup.
     * **Hentikan & Tutup Aplikasi**: Mematikan seluruh proses dengan bersih dan aman.

---

## 3. Cara Menghentikan Aplikasi

Pilih salah satu cara berikut:
* **Cara 1 (Rekomendasi)**: Klik kanan ikon Perpustakaan di System Tray (dekat jam Windows), pilih **Hentikan & Tutup Aplikasi**.
* **Cara 2**: Klik ganda shortcut **Hentikan Perpustakaan** di Desktop.
* **Cara 3**: Jalankan `Stop-Perpustakaan.bat` di folder proyek.

---

## 4. Pencadangan Data (Backup)

* Seluruh data tersimpan secara persisten pada disk `D:\LibrarySystemRuntime\postgresql-data` dan tidak akan hilang saat aplikasi dimatikan atau komputer direstart.
* Lokasi berkas cadangan (dump): `D:\LibrarySystemRuntime\backups\`.
* Anda dapat membuat cadangan kapan saja melalui menu tray **Cadangkan Database (Backup)** atau menjalankan `Perpustakaan.exe --backup`.

---

## 5. Kompilasi Ulang (Jika Ada Pembaruan)

Jika di kemudian hari kode aplikasi diperbarui:
1. Jalankan `npm run build` di folder `frontend/`.
2. Jalankan skrip `launcher\Build-Executable.ps1` melalui PowerShell.
3. Berkas `Perpustakaan.exe` akan terkompilasi ulang secara instan menggunakan compiler native Microsoft C#.
