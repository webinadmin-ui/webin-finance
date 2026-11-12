@extends('base')
@section('title','Impor Excel')
@section('content')
<form class="panel narrow" method="post" enctype="multipart/form-data">@csrf
<h2>Pilih buku kerja</h2><p>Unggah laporan keuangan untuk memuat akun, saldo awal, dan transaksi. Unggah tracker pelanggan untuk memuat order dan biaya iklan.</p>
<label>File Excel (.xlsx)<input type="file" name="file" accept=".xlsx" required></label><button>Impor data</button></form>
<section class="panel narrow"><h2>Jika file diimpor kembali</h2><p>Saldo awal diperbarui. Order dengan ID yang sama, iklan dengan bulan yang sama, dan transaksi identik dilewati. Untuk mengoreksi transaksi yang pernah diimpor, edit melalui halaman Transaksi. Perubahan isi baris di Excel akan dianggap transaksi baru.</p></section>
@endsection
