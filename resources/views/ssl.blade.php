@extends('base')
@section('title','Perpanjangan SSL')
@section('content')
<form class="bar"><label>Bulan pengecekan<input type="month" name="month" value="{{ $month }}" required></label><input type="hidden" name="filter" value="{{ $filter }}"><button>Tampilkan</button></form>
<p>Jadwal pengecekan tanggal 1–3. Periode kedaluwarsa {{ $start }} sampai {{ $end }}, termasuk kedua tanggal.</p>
<nav class="bar alerts">@foreach(['due'=>'Perlu diperpanjang','overdue'=>'Sebelum periode','missing'=>'Tanggal kosong','all'=>'Semua website'] as $key=>$label)
<a class="pill {{ $filter===$key?'selected':'' }}" href="?month={{ $month }}&amp;filter={{ $key }}">{{ $label }} @if(isset($counts[$key]))<b>{{ $counts[$key] }}</b>@endif</a>
@endforeach</nav>
<div class="panel scroll"><table><tr><th>Website</th><th>Kedaluwarsa SSL</th><th>Terakhir diinput</th><th>Tanggal kedaluwarsa baru</th></tr>
@forelse($rows as $site)<tr><td>{{ $site->domain }}</td><td>{{ $site->expiry ?: 'Belum diisi' }}</td><td>{{ $site->recorded_at ?: '—' }}</td>
<td><form class="inline" method="post" action="/ssl">@csrf<input type="hidden" name="domain" value="{{ $site->domain }}"><input type="hidden" name="version" value="{{ $site->id ?? 0 }}"><input type="date" name="expiry" aria-label="Kedaluwarsa SSL {{ $site->domain }}" required><button>Simpan</button></form></td></tr>
@empty<tr><td colspan="4">Tidak ada website dalam pilihan ini.</td></tr>@endforelse</table>@include('pages')</div>
@endsection
