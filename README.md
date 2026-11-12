# Web-In Finance

Ruang kerja Laravel berbahasa Indonesia untuk jurnal keuangan, pelacakan akuisisi, dan perpanjangan langganan website. Tampilan menggunakan Blade dan CSS, tanpa build Node.

## Menjalankan di laptop

Gunakan PHP 8.2–8.5, Composer 2, dan ekstensi SQLite/PDO, mbstring, GD, ZIP, XML serta ekstensi yang diperiksa oleh Composer. Dari PowerShell Windows:

```powershell
git clone https://github.com/webinadmin-ui/webin-finance.git
cd webin-finance
Copy-Item .env.example .env
New-Item database/database.sqlite -ItemType File
composer install
php artisan key:generate
php artisan migrate
php artisan webin:admin
php artisan serve
```

Buka http://127.0.0.1:8000 dan masuk dengan password yang dibuat. Untuk macOS/Linux, ganti dua perintah PowerShell dengan `cp .env.example .env` dan `touch database/database.sqlite`. Pada pengaturan berikutnya jalankan `git pull`, `composer install`, dan `php artisan migrate`, lalu jalankan server. Refresh browser untuk melihat perubahan. Seluruh data tersimpan di database lokal.

## Memulai dengan dua workbook

1. Dari **Impor**, unggah file laporan keuangan asli. Lembar **Akun & Saldo** memuat bagan akun dan saldo pembukaan, lalu **Transaksi** memuat kolom A–K. Jurnal dengan nomor bukti atau uraian kosong tetap dapat dimuat. Mutasi kas tanpa aktivitas tampil sebagai *Belum diklasifikasi*.
2. Unggah tracker pelanggan. **Pelanggan** menyimpan satu baris per order dan mempertahankan ID pelanggan lintas order. **Iklan** menyimpan spend, kredit yang terpakai, pembayaran bank, dan catatan per bulan. Nilai order dan pembayaran kumulatif tidak otomatis dijurnal.
3. Di **Ringkasan** pilih periode yang mencakup transaksi. Buka akun di neraca saldo untuk melihat buku besarnya. Untuk CAC, pilih bulan penuh dan pastikan spend semua bulan terisi. CAC kelompok chat mengikuti status pembayaran terbaru dari lead Google Ads yang memulai chat dalam periode.
4. Di **Langganan**, pilih **Belum terjadwal**, lalu **Edit** untuk mengisi tanggal mulai yang benar. Durasi dalam bulan mengikuti penambahan bulan kalender (31 Januari + 1 bulan berakhir pada akhir Februari). Durasi `one time` dikecualikan. **Perpanjang** membuat order Renewal dengan ID order baru dan menyimpan hubungan ke periode lama. Periksa tanggal mulai dan pembayaran sebelum menyimpan.
5. **Simulasi** menghitung dampak perubahan CAC, budget iklan, harga, HPP, dan kredit promo. Hasil simulasi tidak mengubah jurnal.

Impor memvalidasi header dan membatalkan seluruh file jika menemukan baris tidak valid. Impor ulang identik melewati transaksi yang sama, termasuk jika ada beberapa transaksi identik yang sah dalam satu file. ID order dan bulan iklan yang sudah ada dilewati, sedangkan saldo awal akun diperbarui. Koreksi transaksi lama langsung melalui formulir edit agar perubahan baris pada file tidak menghasilkan jurnal tambahan. Baris lama yang dikeluarkan dari workbook tidak menghapus data aplikasi.

Aplikasi ini memakai satu akun admin internal. Workbook, password, saldo awal aktual dan database pelanggan tidak disertakan dalam repositori. Cadangkan database lokal sebelum mengganti saldo pembukaan.
