@extends('base')
@section('title','Langganan pelanggan')
@section('content')
@include('alert-links')
<?php
$domains=$activeChoices->map(fn($c)=>strtolower(trim($c->domain)))->unique()->sort()->values();
$customers=$activeChoices->pluck('customer')->unique()->sort()->values();
?>
<nav class="bar alerts">@foreach([''=>'Semua','soon'=>'Jatuh tempo 14 hari','overdue'=>'Lewat jatuh tempo','missing'=>'Belum terjadwal','stopped'=>'Berhenti'] as $k=>$v)
<a class="pill {{ $filter===$k?'selected':'' }}" href="?filter={{ $k }}">{{ $v }} <b>{{ $counts[$k] }}</b></a>
@endforeach</nav>
<details class="panel" @if($errors->any()) open @endif><summary>Catat klien atau domain berhenti langganan</summary>
@if($activeChoices->isEmpty())
<p>Tidak ada langganan aktif saat ini.</p>
@else
<form method="post" action="/subscriptions/stop">@csrf
<div class="fields">
<label>Berhenti berdasarkan<select name="scope" id="stop-scope">
<option value="order" @selected(old('scope','order')==='order')>Langganan tertentu</option>
<option value="domain" @selected(old('scope')==='domain')>Semua langganan domain</option>
<option value="customer" @selected(old('scope')==='customer')>Semua langganan klien</option>
</select></label>
<label data-stop-scope="order">Pilih langganan<select name="order_id" required><option value="">Pilih domain / order</option>@foreach($activeChoices as $c)<option value="{{ $c->order_id }}" @selected(old('order_id')===$c->order_id)>{{ $c->domain }} · {{ $c->customer }} · {{ $c->package }}</option>@endforeach</select></label>
<label data-stop-scope="domain" hidden>Pilih domain<select name="domain" required disabled><option value="">Pilih domain</option>@foreach($domains as $d)<option value="{{ $d }}" @selected(old('domain')===$d)>{{ $d }}</option>@endforeach</select></label>
<label data-stop-scope="customer" hidden>Pilih klien<select name="customer" required disabled><option value="">Pilih klien</option>@foreach($customers as $cust)<option value="{{ $cust }}" @selected(old('customer')===$cust)>{{ $cust }}</option>@endforeach</select></label>
<label>Tanggal berhenti<input type="date" name="stopped_at" value="{{ old('stopped_at',$today) }}" required></label>
<label>Alasan (opsional)<input type="text" name="stopped_reason" value="{{ old('stopped_reason') }}" placeholder="Contoh: Tidak perpanjang, pindah vendor"></label>
</div>
<button>Tandai berhenti</button>
</form>
<script>
(() => {
    const scope = document.getElementById('stop-scope');
    if (!scope) return;
    function syncScope() {
        document.querySelectorAll('[data-stop-scope]').forEach(el => {
            const active = el.dataset.stopScope === scope.value;
            el.hidden = !active;
            el.querySelectorAll('select,input').forEach(ctrl => { ctrl.disabled = !active; });
        });
    }
    scope.addEventListener('change', syncScope);
    syncScope();
})();
</script>
@endif
</details>
<div class="panel scroll"><table>
@if($filter==='stopped')
<tr><th>Nama / domain</th><th>Pelanggan</th><th>Paket</th><th>Jatuh tempo terakhir</th><th>Tanggal berhenti</th><th>Alasan</th><th></th></tr>
@forelse($rows as $r)
<tr><td>{{ $r->domain }}</td><td>{{ $r->customer }}</td><td>{{ $r->package }}</td><td>{{ $r->expires ?: '—' }}</td><td>{{ $r->stopped_at }}</td><td>{{ $r->stopped_reason ?: '—' }}</td>
<td><div class="inline"><a href="{{ url('/records/orders').'?'.http_build_query(['edit'=>$r->order_id]) }}">Edit</a> · <form class="inline" method="post" action="/subscriptions/reactivate">@csrf<input type="hidden" name="order_id" value="{{ $r->order_id }}"><button class="link-btn resume">Aktifkan lagi</button></form></div></td></tr>
@empty<tr><td colspan="7">Belum ada langganan yang berhenti.</td></tr>@endforelse
@else
<tr><th>Nama / domain</th><th>Pelanggan</th><th>Paket</th><th>Mulai</th><th>Jatuh tempo</th><th>Status</th><th></th></tr>
@forelse($rows as $r)
<tr><td>{{ $r->domain }}</td><td>{{ $r->customer }}</td><td>{{ $r->package }}</td><td>{{ $r->start ?: '—' }}</td><td>{{ $r->expires ?: '—' }}</td><td>{{ !$r->expires?'Belum terjadwal':($r->expires<$today?'Lewat tempo':($r->start>$today?'Terjadwal':'Aktif')) }}</td>
<td><div class="inline"><a href="{{ url('/records/orders').'?'.http_build_query(['edit'=>$r->order_id]) }}">Edit</a> · <a href="{{ url('/records/journal').'?'.http_build_query(['renew'=>$r->order_id]) }}">Perpanjang</a> · <form class="inline" method="post" action="/subscriptions/stop" data-domain="{{ $r->domain }}" onsubmit="return confirm('Tandai ' + this.dataset.domain + ' berhenti langganan?')">@csrf<input type="hidden" name="scope" value="order"><input type="hidden" name="order_id" value="{{ $r->order_id }}"><button class="link-btn">Berhenti</button></form></div></td></tr>
@empty<tr><td colspan="7">Belum ada langganan dalam pilihan ini.</td></tr>@endforelse
@endif
</table>@include('pages')</div>
@endsection
