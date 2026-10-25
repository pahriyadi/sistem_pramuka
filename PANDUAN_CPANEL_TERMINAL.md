# 🖥️ Panduan Lengkap Deployment & Git Terminal cPanel
### Proyek: Sistem Informasi & Pendaftaran Kontingen — Racana Universitas Samawa (UNSA)
- **Repository GitHub**: `https://github.com/pahriyadi/sistem_pramuka.git`
- **Domain Tujuan**: `https://racanaunsa.my.id`
- **Path Server Hosting**: `/home/n1579664/public_html/racanaunsa.my.id/`
- **Path Repository Git**: `/home/n1579664/repositories/sistem_pramuka/`

---

## 📌 Ringkasan Variabel Server Anda
| Parameter | Nilai Konfigurasi |
| :--- | :--- |
| **cPanel User** | `n1579664` |
| **Home Directory** | `/home/n1579664` |
| **Folder Web Live** | `/home/n1579664/public_html/racanaunsa.my.id/` |
| **Folder Git Repo** | `/home/n1579664/repositories/sistem_pramuka/` |
| **Database Name** | `n1579664_racana` *(atau nama database yang Anda buat di cPanel)* |
| **Database User** | `n1579664_racana` *(atau user MySQL cPanel Anda)* |

---

## 🚀 1. Setup Awal di Terminal cPanel (Inisialisasi Pertama)

Buka cPanel Anda, lalu cari menu **Terminal** di bagian *Advanced*.

### A. Clone Repository dari GitHub
Jalankan perintah berikut di Terminal cPanel:

```bash
# 1. Buat direktori repositories jika belum ada
mkdir -p ~/repositories

# 2. Masuk ke direktori repositories
cd ~/repositories

# 3. Kloning repository dari GitHub
git clone https://github.com/pahriyadi/sistem_pramuka.git

# 4. Masuk ke folder repository
cd sistem_pramuka
```

---

### B. Deploy Berkas ke Folder Web Live (Sesuai .cpanel.yml)
Jalankan perintah rsync berikut untuk menyalin file ke folder domain:

```bash
# Pastikan folder target di public_html sudah ada
mkdir -p /home/n1579664/public_html/racanaunsa.my.id/

# Jalankan rsync deployment
rsync -av --exclude='.git' --exclude='.cpanel.yml' /home/n1579664/repositories/sistem_pramuka/ /home/n1579664/public_html/racanaunsa.my.id/
```

---

### C. Konfigurasi File `.env` Database di Web Live
Masuk ke folder web dan buat file `.env` dari template:

```bash
# Pindah ke folder web live
cd /home/n1579664/public_html/racanaunsa.my.id/

# Salin file .env.example menjadi .env
cp .env.example .env

# Edit file .env menggunakan text editor nano
nano .env
```

Sesuaikan isi file `.env` dengan kredensial database cPanel Anda:
```ini
DB_HOST=localhost
DB_PORT=3306
DB_NAME=n1579664_racana
DB_USER=n1579664_racana
DB_PASS=PasswordDatabaseHostingAnda
```
*Tips nano: Tekan `Ctrl + O` lalu tekan `Enter` untuk menyimpan, lalu `Ctrl + X` untuk keluar.*

---

### D. Import Database MySQL via Terminal (Jauh Lebih Cepat)
Pastikan Anda sudah membuat database dan user MySQL melalui menu cPanel **MySQL Databases** terlebih dahulu.

Setelah database dibuat, import skema dan data awal langsung via Terminal cPanel:

```bash
# Masuk ke folder web
cd /home/n1579664/public_html/racanaunsa.my.id/

# Eksekusi import file database.sql ke MySQL
mysql -u n1579664_racana -p n1579664_racana < database/database.sql
```
> Terminal akan meminta input password database hosting Anda. Ketik password lalu tekan `Enter` (karakter password memang tersembunyi).

Verifikasi bahwa seluruh tabel berhasil terpasang:
```bash
mysql -u n1579664_racana -p n1579664_racana -e "SHOW TABLES;"
```

---

### E. Set Izin Akses Folder Uploads
Pastikan direktori upload berkas memiliki hak akses tulis yang aman:
```bash
chmod -R 755 /home/n1579664/public_html/racanaunsa.my.id/uploads/
```

Website Anda sekarang sudah aktif dan dapat diakses di:
👉 **https://racanaunsa.my.id**

---

## 🔄 2. Perintah Rutin Update (Pull & Deploy) via Terminal

Setiap kali Anda selesai melakukan `git push` dari komputer lokal dan ingin memperbarui website live di server:

Buka **Terminal cPanel**, lalu cukup jalankan 3 baris perintah ini:

```bash
# 1. Pindah ke folder repository git
cd /home/n1579664/repositories/sistem_pramuka

# 2. Tarik update terbaru dari GitHub
git pull origin main

# 3. Sinkronkan file terbaru ke folder website
rsync -av --exclude='.git' --exclude='.cpanel.yml' /home/n1579664/repositories/sistem_pramuka/ /home/n1579664/public_html/racanaunsa.my.id/
```

### Jika Ada Update Skema Database dari Git:
Jika ada perubahan pada file `database/database.sql`, jalankan kembali import di terminal:
```bash
cd /home/n1579664/public_html/racanaunsa.my.id/
mysql -u n1579664_racana -p n1579664_racana < database/database.sql
```

---

## ⚡ 3. Trik Praktis: Buat Command Shortcut (1 Kali Ketik)

Agar Anda tidak perlu mengetik panjang setiap kali ingin deploy, Anda bisa membuat shortcut perintah di file `~/.bashrc` cPanel:

Jalankan perintah ini sekali saja di Terminal cPanel:

```bash
cat << 'EOF' >> ~/.bashrc

# Shortcut Deploy Racana UNSA
alias deploy-racana='cd /home/n1579664/repositories/sistem_pramuka && git pull origin main && rsync -av --exclude=".git" --exclude=".cpanel.yml" /home/n1579664/repositories/sistem_pramuka/ /home/n1579664/public_html/racanaunsa.my.id/ && echo "✅ Deployment Berhasil Selesai!"'
EOF

source ~/.bashrc
```

Setelah itu, setiap kali ingin deploy di Terminal cPanel, Anda **cukup ketik satu perintah ini**:
```bash
deploy-racana
```

---

## 💾 4. Cara Backup Database Hosting via Terminal

Jika Anda ingin membackup data pendaftaran atau data live dari server hosting sebelum melakukan update:

```bash
# Backup database ke file .sql dengan timestamp tanggal hari ini
mysqldump -u n1579664_racana -p n1579664_racana > ~/backup_racana_$(date +%Y%m%d_%H%M%S).sql
```
File backup akan tersimpan di direktori home (`/home/n1579664/`) dengan aman.
