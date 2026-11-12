<?php
use Illuminate\Support\Facades\{Artisan,DB,Hash};
Artisan::command('webin:admin', function () {
    $password = $this->secret('Password admin (minimal 12 karakter)');
    if (strlen($password ?? '') < 12) { $this->error('Password terlalu pendek.'); return 1; }
    DB::table('admins')->updateOrInsert(['id'=>1], ['password'=>Hash::make($password)]);
    $this->info('Password admin tersimpan.');
});
