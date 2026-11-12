@extends('base')
@section('title','Ringkasan usaha')
@section('content')
@include('alert-links')
@php($rp=fn($v)=>'Rp '.number_format($v,0,',','.'))
<form class="bar"><label>Dari<input type="date" name="from" value="{{ $from }}" required></label><label>Sampai<input type="date" name="to" value="{{ $to }}" required></label><button>Tampilkan</button></form>
<div class="cards">@foreach(['Laba bersih'=>array_sum($income),'Pendapatan bersih'=>$income['Pendapatan']??0,'Arus kas neto'=>array_sum($flows),'Nilai order bayar'=>$acquisition['Nilai order bayar (Rp)']] as $label=>$v)<article><small>{{ $label }}</small><strong>{{ $rp($v) }}</strong></article>@endforeach</div>
<div class="grid"><section class="panel"><h2>Laba rugi</h2>@foreach($income as $label=>$v)<div class="line"><span>{{ $label }}</span><b>{{ $rp($v) }}</b></div>@endforeach</section>
<section class="panel"><h2>Posisi keuangan</h2>@foreach([1=>'Aset',2=>'Kewajiban',3=>'Ekuitas'] as $i=>$label)<div class="line">{{ $label }}<b>{{ $rp($balance[$i]??0) }}</b></div>@endforeach
<h2>Perubahan modal</h2>@foreach(['Ekuitas awal'=>$openingEquity,'Mutasi modal dan distribusi'=>$equityMovement,'Laba periode'=>array_sum($income),'Ekuitas akhir'=>$balance[3]??0] as $label=>$v)<div class="line">{{ $label }}<b>{{ $rp($v) }}</b></div>@endforeach
<h2>Arus kas per aktivitas</h2>@forelse($flows as $label=>$v)<div class="line">{{ $label }}<b>{{ $rp($v) }}</b></div>@empty<p>Belum ada mutasi kas pada periode ini.</p>@endforelse</section></div>
<section class="panel"><h2>Akuisisi pelanggan</h2><p>Kelompok chat mengikuti tanggal chat pertama. Untuk membandingkan CAC dengan spend bulanan, pilih periode bulan penuh.</p><div class="metrics">@foreach($acquisition as $label=>$v)<div class="line">{{ $label }}<b>{{ $v===null?'—':number_format($v,str_contains($label,'%')?1:0,',','.') }}</b></div>@endforeach</div></section>
<section class="panel"><h2>Neraca saldo</h2><p>Saldo bertanda positif menunjukkan debit. Klik akun untuk melihat transaksi terkait.</p><div class="scroll"><table><tr><th>Akun</th><th>Awal</th><th>Debit</th><th>Kredit</th><th>Akhir</th></tr>@foreach($trial as $code=>$r)<tr><td><a href="/ledger/{{ $code }}?from={{ $from }}&amp;to={{ $to }}">{{ $code }} · {{ $r['name'] }}</a></td>@foreach(['opening','debit','credit','closing'] as $f)<td>{{ $rp($r[$f]) }}</td>@endforeach</tr>@endforeach</table></div></section>
@endsection
