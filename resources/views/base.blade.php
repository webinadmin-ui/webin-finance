<!doctype html><html lang="id"><head><meta charset="utf-8"><meta name="viewport" content="width=device-width,initial-scale=1"><title>@yield('title') · Web-In Finance</title><link rel="stylesheet" href="/style.css"></head>
<body><header><a class="brand" href="/"><b>W</b> Web-In <small>Finance</small></a>
@if(session('admin'))<nav>@foreach(['/' => 'Ringkasan','/records/journal'=>'Transaksi','/records/orders'=>'Pelanggan','/records/ads'=>'Iklan','/simulation'=>'Simulasi','/subscriptions'=>'Langganan','/ssl'=>'SSL','/import'=>'Impor'] as $url=>$label)<a class="{{ request()->is(ltrim($url,'/') ?: '/') ? 'selected':'' }}" href="{{ $url }}">{{ $label }}</a>@endforeach</nav><form method="post" action="/logout">@csrf<button class="quiet">Keluar</button></form>@endif</header>
<main><h1>@yield('title')</h1>@if(session('message'))<p role="status" class="notice">{{ session('message') }}</p>@endif
@if($errors->any())<aside role="alert">@foreach($errors->all() as $error)<div>{{ $error }}</div>@endforeach</aside>@endif
@yield('content')</main></body></html>
