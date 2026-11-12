@extends('base')
@section('title','Langganan pelanggan')
@section('content')
<div class="bar">@foreach([''=>'Semua','soon'=>'Jatuh tempo 30 hari','overdue'=>'Lewat jatuh tempo','missing'=>'Belum terjadwal'] as $k=>$v)<a class="pill" href="?filter={{ $k }}">{{ $v }}</a>@endforeach</div>
<div class="panel scroll"><table><tr><th>Nama / domain</th><th>Paket</th><th>Mulai</th><th>Jatuh tempo</th><th>Status</th><th></th></tr>@forelse($rows as $r)<tr><td>{{ $r->domain }}</td><td>{{ $r->package }}</td><td>{{ $r->start ?: '—' }}</td><td>{{ $r->expires ?: '—' }}</td><td>{{ !$r->expires?'Belum terjadwal':($r->expires<$today?'Lewat tempo':($r->start>$today?'Terjadwal':'Aktif')) }}</td><td><a href="{{ url('/records/orders').'?'.http_build_query(['edit'=>$r->order_id]) }}">Edit</a> · <a href="{{ url('/records/orders').'?'.http_build_query(['renew'=>$r->order_id]) }}">Perpanjang</a></td></tr>@empty<tr><td colspan="6">Belum ada langganan dalam pilihan ini.</td></tr>@endforelse</table>@include('pages')</div>
@endsection
