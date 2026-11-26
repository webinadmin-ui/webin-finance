<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>@yield('title') · Web-In Finance</title><link rel="stylesheet" href="/style.css?v={{ filemtime(public_path('style.css')) }}"></head>
<body><header><a class="brand" href="/"><b>W</b> Web-In <small>Finance</small></a>
@if(session('admin'))<nav>@foreach(['/' => 'Ringkasan','/records/journal'=>'Transaksi','/records/orders'=>'Pelanggan','/records/ads'=>'Iklan','/simulation'=>'Simulasi','/subscriptions'=>'Langganan','/ssl'=>'SSL','/import'=>'Impor'] as $url=>$label)<a class="{{ request()->is(ltrim($url,'/') ?: '/') ? 'selected':'' }}" href="{{ $url }}">{{ $label }}</a>@endforeach</nav>
<details class="user-menu" data-user-menu>
<summary class="user-trigger {{ request()->is('password') ? 'selected' : '' }}" aria-label="Menu pengguna"><span class="user-avatar" aria-hidden="true">A</span><span>Admin</span><svg width="14" height="14" viewBox="0 0 16 16" fill="none" aria-hidden="true"><path d="M4 6l4 4 4-4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/></svg></summary>
<div class="user-dropdown" role="menu">
<div class="user-menu-head">Masuk sebagai <strong>Admin</strong></div>
<a class="user-menu-item {{ request()->is('password') ? 'selected' : '' }}" href="/password" role="menuitem">Kelola password</a>
<form method="post" action="/logout">@csrf<button type="submit" class="user-menu-item danger" role="menuitem">Keluar</button></form>
</div>
</details>@endif</header>
<main><h1>@yield('title')</h1>@if(session('message'))<p role="status" class="notice">{{ session('message') }}</p>@endif
@if($errors->any())<aside role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</aside>@endif
@yield('content')</main>
@if(session('admin'))<script>
(() => {
    const menu = document.querySelector('[data-user-menu]');
    if (!menu) return;
    document.addEventListener('click', (e) => { if (menu.open && !menu.contains(e.target)) menu.open = false; });
    document.addEventListener('keydown', (e) => { if (e.key === 'Escape' && menu.open) { menu.open = false; menu.querySelector('summary')?.focus(); } });
})();
</script>@endif
</body></html>
