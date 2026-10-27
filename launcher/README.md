# Panduan Operasional Portable Server LAN - Sistem Perpustakaan Sekolah

Dokumen ini memuat petunjuk lengkap pengoperasian **Sistem Informasi Perpustakaan Sekolah** dalam konfigurasi **Server LAN** untuk komputer Windows 10/11.

---

## 1. Ikhtisar Arsitektur

- **PC Petugas (Komputer Server)**: Menjalankan database PostgreSQL, backend Laravel API, frontend Nuxt SSR, dan Caddy Reverse Proxy.
- **Komputer Klien (Siswa, Guru, Staf)**: **TIDAK memerlukan instalasi apa pun**. Cukup membuka browser (Chrome, Edge, Firefox) dan mengakses alamat IP LAN server (contoh: `http://192.168.1.3`).
- **Keamanan Database**: Port PostgreSQL (`5432`) **tidak diekspos ke jaringan LAN**. Hanya Caddy Reverse Proxy pada port `80` yang melayani lalu lintas HTTP.
- **Persistensi Data**: Seluruh data buku, anggota, transaksi peminjaman, denda, dan pengaturan identitas sekolah disimpan pada **Docker Volume Persisten** (`library-system_sail-pgsql`) dan folder lokal host (`storage/app`). Data tetap utuh dan aman saat server dimatikan, direstart, atau diperbarui.

---

## 2. Persyaratan Sistem Komputer Server

| Komponen | Persyaratan Minimum | Rekomendasi |
|---|---|---|
| **Sistem Operasi** | Windows 10 (versi 2004+) atau Windows 11 (64-bit) | Windows 11 64-bit Pro / Home |
| **Aplikasi Pendukung** | Docker Desktop (dengan backend WSL2) | Docker Desktop versi terbaru |
| **Prosesor (CPU)** | Intel Core i3 / AMD Ryzen 3 (dengan virtualisasi VT-x/AMD-V aktif) | Intel Core i5 / AMD Ryzen 5 |
| **Memori (RAM)** | 8 GB RAM | 16 GB RAM |
| **Ruang Penyimpanan** | Minimal 15 GB ruang kosong | SSD dengan 30+ GB ruang kosong |
| **Jaringan** | Kartu Jaringan Ethernet (LAN) atau Wi-Fi yang stabil | Kabel LAN (Ethernet Gigabit) |

> **Catatan Komputer Klien:** Komputer klien hanya membutuhkan browser modern (Google Chrome, Microsoft Edge, Mozilla Firefox) dan terhubung ke jaringan Wi-Fi/LAN sekolah yang sama dengan PC server.

---

## 3. Cara Menggunakan (Quick Start)

### A. Menjalankan Server

Sebelum pertama kali menjalankan server, salin `.env.lan.example` menjadi `.env.lan`, lalu isi `APP_KEY` dan `DB_PASSWORD` dengan nilai non-kosong yang aman. Untuk database persisten yang sudah ada, `DB_PASSWORD` harus cocok dengan kredensial role PostgreSQL yang tersimpan dan `APP_KEY` tidak boleh diganti sembarangan. Simpan `.env.lan` secara lokal dan jangan membagikan atau memasukkannya ke source control. Launcher menolak nilai kosong dan tidak lagi mengisi kredensial bawaan.

1. Pastikan **Docker Desktop** sudah menyala di PC server (ikon ikan paus di taskbar Windows sudah stabil).
2. Buka folder `launcher\` di Windows Explorer.
3. Klik ganda (double-click) file:
   ```
   Start-Server.bat
   ```
   *(Atau jalankan `powershell -ExecutionPolicy Bypass -File .\launcher\Start-LibraryServer.ps1` melalui terminal).*
4. Launcher akan secara otomatis:
   - Memeriksa Docker Desktop.
   - Mendeteksi alamat IP LAN komputer server.
   - Memperbarui alamat LAN pada `.env.lan` sambil mempertahankan konfigurasi rahasia yang telah disiapkan.
   - Menjalankan seluruh container layanan.
   - Melakukan uji kesehatan (healthcheck).
   - Membuka halaman utama aplikasi di browser PC server (`http://localhost`).
   - Menampilkan alamat akses LAN untuk komputer lain.
5. **Biarkan jendela terminal tetap terbuka** selama sistem perpustakaan sedang digunakan.

---

### B. Mengakses Aplikasi dari Komputer Klien

1. Hubungkan komputer klien ke Wi-Fi atau switch LAN yang sama dengan PC server.
2. Buka browser (Chrome / Edge).
3. Ketik alamat IP LAN yang ditampilkan oleh launcher server pada bilah alamat URL, contoh:
   ```
   http://192.168.1.3
   ```
4. Halaman perpustakaan sekolah akan langsung terbuka. Pengguna dapat langsung mencari katalog buku, masuk sebagai anggota, atau login sebagai petugas perpustakaan.

---

### C. Menghentikan Server

Untuk mematikan server secara bersih dan aman:
- Tekan huruf `Q` lalu tekan **Enter** pada jendela terminal server yang sedang berjalan, **ATAU**
- Klik ganda file:
  ```
  Stop-Server.bat
  ```

> ⚠️ **PERINGATAN KESELAMATAN DATA:**  
> Jangan pernah menjalankan perintah `docker compose down -v` secara manual. Flag `-v` akan menghapus volume database. Gunakan selalu script `Stop-Server.bat` yang sudah dirancang aman.

---

## 4. Pencadangan Data (Backup) & Pemulihan (Restore)

### A. Membuat Backup Database

Lakukan backup berkala (misal: setiap akhir pekan atau sebelum pembaruan sistem):
1. Klik ganda file:
   ```
   Backup-Database.bat
   ```
2. File cadangan SQL lengkap akan otomatis dibuat di dalam folder `backups\` dengan format nama:
   ```
   library_backup_YYYYMMDD_HHMMSS.sql
   ```
3. Terminal akan menampilkan ukuran file dan checksum SHA256 sebagai bukti integritas data. Skrip hanya menyatakan berhasil bila `pg_dump` keluar dengan kode 0 dan file SQL memiliki header/footer lengkap.

### B. Memulihkan Database (Restore)

Prosedur pemulihan dilengkapi dengan 3 lapis perlindungan keselamatan:
1. Klik ganda file:
   ```
   Restore-Database.bat
   ```
2. Terminal akan menampilkan daftar file cadangan yang tersedia di folder `backups\`.
3. Masukkan nomor file yang ingin dipulihkan.
4. Muncul peringatan kritis bahwa data aktif akan ditimpa. Operator **wajib mengetik kata `PULIHKAN`** secara eksplisit.
5. Sebelum penimpaan dilakukan, script akan **secara otomatis membuat snapshot keselamatan (safety backup)** dari data saat ini ke folder `backups\`, lalu memverifikasi berkas serta checksum-nya.
6. Skrip memverifikasi container dan database tujuan, menghentikan backend serta scheduler, lalu menjalankan `psql` dengan `ON_ERROR_STOP=1`.
7. Jika proses restore gagal, skrip melaporkan kegagalan dan menyalakan kembali backend/scheduler. Jika berhasil, nama database serta jumlah tabel, pengguna, dan buku diverifikasi sebelum dinyatakan selesai.

---

## 5. Penanganan Alamat IP Berubah (DHCP vs Static IP)

Secara default, router sekolah dapat memberikan alamat IP dinamis (DHCP) yang dapat berubah saat PC server dimatikan atau direstart.

### Rekomendasi Terbaik (IP Statis / DHCP Reservation)
Agar alamat IP server tidak pernah berubah:
1. Buka pengaturan router/access point sekolah.
2. Buat **DHCP Static IP Reservation** atau **MAC Binding** untuk alamat fisik PC server (misal: tetapkan tetap `192.168.1.100`).
3. Komputer klien cukup menyimpan bookmark ke `http://192.168.1.100`.

### Deteksi Otomatis Launcher
Jika IP server berubah karena router sekolah:
1. Saat launcher `Start-Server.bat` dijalankan kembali, launcher akan otomatis mendeteksi IP LAN baru komputer.
2. File konfigurasi `.env.lan` dan `NUXT_PUBLIC_API_BASE_URL` akan otomatis disinkronkan ke IP baru.
3. Petugas cukup mengumumkan alamat IP baru tersebut kepada pengguna di jaringan.

---

## 6. Pengaturan Firewall Windows

Jika komputer klien tidak dapat membuka alamat server (halaman memuat tanpa henti / *timeout*), pastikan Windows Firewall di PC server mengizinkan koneksi port 80 pada profil Private Network:

Jalankan perintah berikut di PowerShell (sebagai Administrator) pada PC server jika diperlukan:
```powershell
New-NetFirewallRule -DisplayName "Sistem Perpustakaan Sekolah (Port 80)" `
    -Direction Inbound `
    -LocalPort 80 `
    -Protocol TCP `
    -Action Allow `
    -Profile Private
```

---

## 7. Troubleshooting / Tanya Jawab

**T: Muncul pesan error "Port 80 saat ini sedang digunakan oleh proses lain".**  
**J:** Periksa apakah di komputer server terpasang software web server lain (IIS, XAMPP/Apache, atau Skype). Anda dapat mematikan sementara IIS dengan perintah `iisreset /stop` atau menjalankan launcher dengan port alternatif:
```powershell
.\launcher\Start-LibraryServer.ps1 -HttpPort 8080
```
*(Jika menggunakan port 8080, klien mengakses via `http://<IP_LAN>:8080`)*.

**T: Gambar logo sekolah atau nama sekolah apakah hilang setelah server direstart?**  
**J:** **Tidak hilang.** Nama sekolah tersimpan di tabel `settings` database PostgreSQL, dan file logo tersimpan di folder `storage/app/settings/` yang terhubung secara persisten.

**T: Apakah komputer server bisa dipakai untuk keperluan lain saat perpustakaan aktif?**  
**J:** Ya, komputer server dapat digunakan secara normal (mengetik, browsing, dll.) karena container berjalan ringan di latar belakang melalui Docker Desktop.
