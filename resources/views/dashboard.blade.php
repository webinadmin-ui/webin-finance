@extends('base')
@section('title','Ringkasan usaha')
@section('content')
@include('alert-links')
<?php
$rp=fn($v)=>'Rp '.number_format($v,0,',','.');
$rawMax=max(0,...array_map(fn($m)=>max(0,$m['revenue'],$m['order_value']),$monthlySales['months']));
$scaleMax=$rawMax>0?$rawMax:1;
$shortRp=function(float $v):string{
    if($v<=0) return 'Rp 0';
    if($v>=1_000_000_000) return 'Rp '.number_format($v/1_000_000_000,1,',','.').' M';
    if($v>=1_000_000) return 'Rp '.number_format($v/1_000_000,1,',','.').' jt';
    if($v>=1_000) return 'Rp '.number_format($v/1_000,0,',','.').' rb';
    return 'Rp '.number_format($v,0,',','.');
};
?>
<form class="bar"><label>Dari<input type="date" name="from" value="{{ $from }}" required></label><label>Sampai<input type="date" name="to" value="{{ $to }}" required></label><button>Tampilkan</button></form>
<div class="cards">@foreach(['Laba bersih'=>array_sum($income),'Pendapatan bersih'=>$income['Pendapatan']??0,'Arus kas neto'=>array_sum($flows),'Nilai order bayar'=>$acquisition['Nilai order bayar (Rp)']] as $label=>$v)<article><small>{{ $label }}</small><strong>{{ $rp($v) }}</strong></article>@endforeach</div>
<section class="panel" aria-label="Laporan penjualan bulanan">
<div class="chart-head">
<div><h2>Laporan penjualan bulanan ({{ $monthlySales['year'] }})</h2>
<p class="chart-sub">Total {{ $monthlySales['year'] }} · Pendapatan bersih {{ $rp($monthlySales['totals']['revenue']) }} · Nilai order bayar {{ $rp($monthlySales['totals']['order_value']) }} ({{ $monthlySales['totals']['order_count'] }} order)</p></div>
<div class="chart-controls">
<div class="legend"><span><i class="swatch rev"></i>Pendapatan bersih</span><span><i class="swatch ord"></i>Nilai order bayar</span></div>
<form class="inline" method="get"><input type="hidden" name="from" value="{{ $from }}"><input type="hidden" name="to" value="{{ $to }}"><select name="chart_year" aria-label="Tahun laporan penjualan" onchange="this.form.submit()">@foreach($monthlySales['available_years'] as $yr)<option value="{{ $yr }}" @selected($yr===$monthlySales['year'])>{{ $yr }}</option>@endforeach</select></form>
</div></div>
@if($rawMax<=0)<p>Belum ada data penjualan tercatat pada tahun {{ $monthlySales['year'] }}.</p>@endif
<div class="bar-chart">
<div class="y-axis" aria-hidden="true">@foreach([1,0.75,0.5,0.25,0] as $t)<span>{{ $rawMax>0?$shortRp($rawMax*$t):'Rp 0' }}</span>@endforeach</div>
<div class="plot-scroll"><div class="plot" role="img" aria-label="Grafik batang penjualan bulanan tahun {{ $monthlySales['year'] }}">
@foreach($monthlySales['months'] as $m)
<?php
$revPct=$m['revenue']>0?max(2,round(($m['revenue']/$scaleMax)*100,2)):0;
$ordPct=$m['order_value']>0?max(2,round(($m['order_value']/$scaleMax)*100,2)):0;
?>
<div class="month-col" tabindex="0">
<div class="chart-tip"><strong>{{ $m['label'] }} {{ $monthlySales['year'] }}</strong><br>Pendapatan: {{ $rp($m['revenue']) }}<br>Order bayar: {{ $rp($m['order_value']) }} ({{ $m['order_count'] }})</div>
<div class="bars"><div class="bar-col rev" style="height:{{ $revPct }}%" title="Pendapatan {{ $m['label'] }}: {{ $rp($m['revenue']) }}"></div><div class="bar-col ord" style="height:{{ $ordPct }}%" title="Order bayar {{ $m['label'] }}: {{ $rp($m['order_value']) }}"></div></div>
<span class="m-label">{{ $m['label'] }}</span>
</div>
@endforeach
</div></div></div>
<details class="chart-details"><summary>Lihat rincian bulanan</summary><div class="scroll"><table><tr><th>Bulan</th><th>Pendapatan bersih (Jurnal)</th><th>Nilai order bayar</th><th>Jumlah order</th></tr>
@foreach($monthlySales['months'] as $m)<tr><td>{{ $m['label'] }} {{ $monthlySales['year'] }}</td><td>{{ $rp($m['revenue']) }}</td><td>{{ $rp($m['order_value']) }}</td><td>{{ $m['order_count'] }}</td></tr>@endforeach
</table></div></details>
</section>
<div class="grid"><section class="panel"><h2>Laba rugi</h2>@foreach($income as $label=>$v)<div class="line"><span>{{ $label }}</span><b>{{ $rp($v) }}</b></div>@endforeach</section>
<section class="panel"><h2>Posisi keuangan</h2>@foreach([1=>'Aset',2=>'Kewajiban',3=>'Ekuitas'] as $i=>$label)<div class="line">{{ $label }}<b>{{ $rp($balance[$i]??0) }}</b></div>@endforeach
<h2>Perubahan modal</h2>@foreach(['Ekuitas awal'=>$openingEquity,'Mutasi modal dan distribusi'=>$equityMovement,'Laba periode'=>array_sum($income),'Ekuitas akhir'=>$balance[3]??0] as $label=>$v)<div class="line">{{ $label }}<b>{{ $rp($v) }}</b></div>@endforeach
<h2>Arus kas per aktivitas</h2>@forelse($flows as $label=>$v)<div class="line">{{ $label }}<b>{{ $rp($v) }}</b></div>@empty<p>Belum ada mutasi kas pada periode ini.</p>@endforelse</section></div>
<section class="panel"><h2>Akuisisi pelanggan</h2><p>Kelompok chat mengikuti tanggal chat pertama. Untuk membandingkan CAC dengan spend bulanan, pilih periode bulan penuh.</p><div class="metrics">@foreach($acquisition as $label=>$v)<div class="line">{{ $label }}<b>{{ $v===null?'—':number_format($v,str_contains($label,'%')?1:0,',','.') }}</b></div>@endforeach</div></section>
<section class="panel"><h2>Neraca saldo</h2><p>Saldo bertanda positif menunjukkan debit. Klik akun untuk melihat transaksi terkait.</p><div class="scroll"><table><tr><th>Akun</th><th>Awal</th><th>Debit</th><th>Kredit</th><th>Akhir</th></tr>@foreach($trial as $code=>$r)<tr><td><a href="/ledger/{{ $code }}?from={{ $from }}&amp;to={{ $to }}">{{ $code }} · {{ $r['name'] }}</a></td>@foreach(['opening','debit','credit','closing'] as $f)<td>{{ $rp($r[$f]) }}</td>@endforeach</tr>@endforeach</table></div></section>
@endsection
