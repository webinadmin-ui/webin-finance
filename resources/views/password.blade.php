@extends('base')
@section('title','Kelola password')
@section('content')
<form class="panel narrow stack" method="post" action="/password">@csrf
<h2>Ubah password admin</h2>
<p>Gunakan minimal 12 karakter yang sulit ditebak dan berbeda dari password saat ini.</p>
<label>Password saat ini<input name="current_password" type="password" autocomplete="current-password" required autofocus></label>
<label>Password baru<input name="password" type="password" autocomplete="new-password" minlength="12" required></label>
<label>Konfirmasi password baru<input name="password_confirmation" type="password" autocomplete="new-password" minlength="12" required></label>
<button>Simpan password</button>
</form>
<section class="panel narrow"><h2>Keamanan sesi</h2><p>Setelah password diperbarui, sesi Anda saat ini tetap aktif dan semua sesi login di perangkat lain otomatis keluar.</p></section>
@endsection

