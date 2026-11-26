@if(!empty($customerPattern))
<?php
$rp=fn($v)=>'Rp '.number_format($v,0,',','.');
$rangeLabels=['all'=>'Semua bulan','6m'=>'6 bulan terakhir','3m'=>'3 bulan terakhir','month'=>'Bulan terpilih'];
$patternCards=[
    ['title'=>'Chat pertama kali','color'=>'chat','data'=>$customerPattern['chat']],
    ['title'=>'Pembayaran pertama kali','color'=>'paid','data'=>$customerPattern['paid']],
];
?>
<section class="panel" aria-label="Analisis pola awal, tengah, dan akhir bulan">
<div class="chart-head">
<div><h2>Pola mingguan dalam bulan (Awal &amp; Akhir vs Tengah)</h2>
<p class="chart-sub">Laju order per hari yang dinormalisasi terhadap jumlah hari teramati ({{ $customerPattern['months'] }} bulan · {{ $customerPattern['total_days'] }} hari @if($customerPattern['start'] && $customerPattern['end']): {{ $customerPattern['start'] }} s/d {{ $customerPattern['end'] }}@endif)</p></div>
<div class="chart-controls">
<div class="legend">
<span><i class="swatch chat"></i>Awal / Akhir (Tgl 1–7 &amp; 24+)</span>
<span><i class="swatch mid"></i>Tengah (Tgl 8–23)</span>
<span><i class="swatch line-ma"></i>Kurva pola</span>
</div>
<form class="inline" method="get" action="/records/orders">
@foreach(['q','chart_month','cum_scope'] as $param)@if(request()->filled($param))<input type="hidden" name="{{ $param }}" value="{{ request($param) }}">@endif @endforeach
<select name="chart_range" aria-label="Rentang analisis mingguan" onchange="this.form.submit()">
@foreach($rangeLabels as $rk=>$rl)<option value="{{ $rk }}" @selected($customerPattern['range']===$rk)>{{ $rl }}</option>@endforeach
</select>
</form>
</div></div>
<div class="grid">
@foreach($patternCards as $card)
<?php
$m=$card['data'];
$maxRate=max(0.0,...array_column($m['buckets'],'rate'));
$scale=$maxRate>0?$maxRate*1.15:1.0;
$pts=[];
foreach($m['buckets'] as $bi=>$b){
    $x=round((($bi+0.5)/4)*1000,2);
    $y=round(max(0,min(100,100-(($b['rate']/$scale)*100))),2);
    $pts[]="$x,$y";
}
$endsRate=$m['ends']['rate'];
$midRate=$m['mid']['rate'];
$cmpMax=max($endsRate,$midRate,0.001);
?>
<div class="pattern-card">
<div class="pattern-head">
<h3>{{ $card['title'] }}</h3>
<span class="verdict {{ $m['verdict']['tone'] }}">{{ $m['verdict']['label'] }}</span>
</div>
<p class="chart-sub">{{ $m['verdict']['detail'] }}</p>
<div class="bar-chart">
<div class="y-axis compact" aria-hidden="true">@foreach([1,0.75,0.5,0.25,0] as $t)<span>{{ $maxRate>0?number_format($scale*$t,2,',','.'):'0' }}</span>@endforeach</div>
<div class="plot weekly" role="img" aria-label="Grafik pola 4 minggu {{ $card['title'] }}">
@if($maxRate>0)
<svg class="trend-svg" viewBox="0 0 1000 100" preserveAspectRatio="none" aria-hidden="true">
<polyline class="trend-ma" points="{{ implode(' ',$pts) }}" fill="none" vector-effect="non-scaling-stroke"/>
</svg>
@endif
@foreach($m['buckets'] as $b)
@php($pct=$b['rate']>0?round(($b['rate']/$scale)*100,2):0)
<div class="month-col" tabindex="0">
<div class="chart-tip"><strong>{{ $b['label'] }} ({{ $b['range'] }})</strong><br>Laju: {{ number_format($b['rate'],2,',','.') }} order/hari<br>Total: {{ $b['count'] }} order ({{ $b['share'] }}%)<br>Hari teramati: {{ $b['days'] }} hari @if($card['color']==='paid' && $b['value']>0)<br>Nilai order: {{ $rp($b['value']) }}@endif</div>
<div class="bars"><div class="bar-col {{ $b['phase']==='Tengah'?'mid':$card['color'] }}" style="height:{{ $pct }}%"></div></div>
@if($maxRate>0)<span class="trend-dot" style="bottom:{{ $pct }}%" aria-hidden="true"></span>@endif
<span class="m-label">{{ $b['label'] }}<small>{{ $b['range'] }}</small></span>
</div>
@endforeach
</div></div>
<div class="phase-compare">
<div class="phase-row"><span>Awal &amp; Akhir <small>({{ $m['ends']['count'] }} order / {{ $m['ends']['days'] }} hari)</small></span><div class="phase-track"><i class="{{ $card['color'] }}" style="width:{{ $endsRate>0?round(($endsRate/$cmpMax)*100,1):0 }}%"></i></div><b>{{ number_format($endsRate,2,',','.') }}/hari</b></div>
<div class="phase-row"><span>Tengah Bulan <small>({{ $m['mid']['count'] }} order / {{ $m['mid']['days'] }} hari)</small></span><div class="phase-track"><i class="mid" style="width:{{ $midRate>0?round(($midRate/$cmpMax)*100,1):0 }}%"></i></div><b>{{ number_format($midRate,2,',','.') }}/hari</b></div>
</div>
</div>
@endforeach
</div>
</section>
@endif

@php($rp=fn($v)=>'Rp '.number_format($v,0,',','.'))
<?php
$buildDailyLines=function(array $days,string $field,?array $trend,int $scale,int $dayCount,int $activeDays,bool $cumulative=false): array {
    $points=[];
    $total=0.0;
    for($i=0;$i<$activeDays;$i++){
        $val=$days[$i][$field]??null;
        if($val===null) continue;
        $total=(float)$val;
        $x=round((($i+0.5)/$dayCount)*1000,2);
        $y=round(max(0,min(100,100-(($total/$scale)*100))),2);
        $points[]="$x,$y";
    }
    $reg=null;
    if($cumulative && $activeDays>=2 && $total>0){
        $startVal=$total/$activeDays;
        $reg=[
            'x1'=>round((0.5/$dayCount)*1000,2),
            'y1'=>round(max(0,min(100,100-(($startVal/$scale)*100))),2),
            'x2'=>round((($activeDays-0.5)/$dayCount)*1000,2),
            'y2'=>round(max(0,min(100,100-(($total/$scale)*100))),2),
        ];
    }elseif(!$cumulative && $trend && $activeDays>=2){
        $x0=0; $x1=$activeDays-1;
        $y0=$trend['intercept']; $y1=$trend['intercept']+$trend['slope']*$x1;
        if($y0<0 && $trend['slope']>0){ $x0=-$trend['intercept']/$trend['slope']; $y0=0; }
        elseif($y1<0 && $trend['slope']<0){ $x1=-$trend['intercept']/$trend['slope']; $y1=0; }
        if($y0>$scale && $trend['slope']<0){ $x0=($scale-$trend['intercept'])/$trend['slope']; $y0=$scale; }
        elseif($y1>$scale && $trend['slope']>0){ $x1=($scale-$trend['intercept'])/$trend['slope']; $y1=$scale; }
        if($y0>=0 && $y1>=0 && $x1>$x0){
            $reg=[
                'x1'=>round((($x0+0.5)/$dayCount)*1000,2),
                'y1'=>round(max(0,min(100,100-(($y0/$scale)*100))),2),
                'x2'=>round((($x1+0.5)/$dayCount)*1000,2),
                'y2'=>round(max(0,min(100,100-(($y1/$scale)*100))),2),
            ];
        }
    }
    return ['daily'=>implode(' ',$points),'reg'=>$reg];
};

$panels=[];
if(!empty($customerCharts)){
    $days=$customerCharts['days'];
    $dayCount=count($days);
    $activeDays=$customerCharts['active_days'] ?? $dayCount;
    $chatMax=max(0,...array_column($days,'chat_count'));
    $paidMax=max(0,...array_column($days,'paid_count'));
    $chatScale=$chatMax>0?(int)(ceil($chatMax/4)*4):4;
    $paidScale=$paidMax>0?(int)(ceil($paidMax/4)*4):4;
    $panels[]=[
        'mode'=>'daily',
        'heading'=>'Grafik pelanggan harian ('.$customerCharts['month_label'].')',
        'aria'=>'Grafik pelanggan harian',
        'summary'=>'Chat pertama: '.$customerCharts['totals']['chat_count'].' order · Pembayaran pertama: '.$customerCharts['totals']['paid_count'].' order ('.$rp($customerCharts['totals']['paid_value']).')',
        'line_label'=>'Garis harian',
        'ref_label'=>'Garis tren',
        'details_label'=>'Lihat rincian harian',
        'days'=>$days,
        'day_count'=>$dayCount,
        'active_days'=>$activeDays,
        'month'=>$customerCharts['month'],
        'scope'=>null,
        'charts'=>[
            [
                'title'=>'Berdasarkan chat pertama kali',
                'subtitle'=>'Total '.$customerCharts['totals']['chat_count'].' order ('.$customerCharts['totals']['chat_new_count'].' baru)',
                'empty'=>'Belum ada data chat pertama kali pada '.$customerCharts['month_label'].'.',
                'field'=>'chat_count','delta_field'=>'chat_count','ma_field'=>'chat_ma7',
                'raw_max'=>$chatMax,'scale'=>$chatScale,'color'=>'chat',
                'trend'=>$customerCharts['trends']['chat'],
                'lines'=>$chatMax>0?$buildDailyLines($days,'chat_count',$customerCharts['trends']['chat'],$chatScale,$dayCount,$activeDays,false):['daily'=>'','reg'=>null],
                'aria'=>'Grafik batang dan tren chat pertama kali harian bulan '.$customerCharts['month_label'],
            ],
            [
                'title'=>'Berdasarkan pembayaran pertama kali',
                'subtitle'=>'Total '.$customerCharts['totals']['paid_count'].' order bayar · '.$rp($customerCharts['totals']['paid_value']),
                'empty'=>'Belum ada data pembayaran pertama kali pada '.$customerCharts['month_label'].'.',
                'field'=>'paid_count','delta_field'=>'paid_count','ma_field'=>'paid_ma7',
                'raw_max'=>$paidMax,'scale'=>$paidScale,'color'=>'paid',
                'trend'=>$customerCharts['trends']['paid'],
                'lines'=>$paidMax>0?$buildDailyLines($days,'paid_count',$customerCharts['trends']['paid'],$paidScale,$dayCount,$activeDays,false):['daily'=>'','reg'=>null],
                'aria'=>'Grafik batang dan tren pembayaran pertama kali harian bulan '.$customerCharts['month_label'],
            ],
        ],
    ];
}
if(!empty($customerCumulative)){
    $cDays=$customerCumulative['days'];
    $cDayCount=count($cDays);
    $cActive=$customerCumulative['active_days'];
    $cChatMax=$customerCumulative['totals']['chat_count'];
    $cPaidMax=$customerCumulative['totals']['paid_count'];
    $cChatScale=$cChatMax>0?(int)(ceil($cChatMax/4)*4):4;
    $cPaidScale=$cPaidMax>0?(int)(ceil($cPaidMax/4)*4):4;
    $panels[]=[
        'mode'=>'cumulative',
        'heading'=>'Grafik pelanggan kumulatif ('.$customerCumulative['label'].')',
        'aria'=>'Grafik pelanggan kumulatif',
        'summary'=>'Total kumulatif · Chat pertama: '.$cChatMax.' order · Pembayaran pertama: '.$cPaidMax.' order ('.$rp($customerCumulative['totals']['paid_value']).')',
        'line_label'=>'Garis kumulatif',
        'ref_label'=>'Laju merata',
        'details_label'=>'Lihat rincian kumulatif harian',
        'days'=>$cDays,
        'day_count'=>$cDayCount,
        'active_days'=>$cActive,
        'month'=>$customerCumulative['month'],
        'scope'=>$customerCumulative['scope'],
        'charts'=>[
            [
                'title'=>'Berdasarkan chat pertama kali',
                'subtitle'=>'Kumulatif '.$cChatMax.' order ('.$customerCumulative['totals']['chat_new_count'].' baru)',
                'empty'=>'Belum ada data chat pertama kali pada '.$customerCumulative['label'].'.',
                'field'=>'cum_chat','delta_field'=>'chat_count','ma_field'=>null,
                'raw_max'=>$cChatMax,'scale'=>$cChatScale,'color'=>'chat',
                'trend'=>null,
                'lines'=>$cChatMax>0?$buildDailyLines($cDays,'cum_chat',null,$cChatScale,$cDayCount,$cActive,true):['daily'=>'','reg'=>null],
                'aria'=>'Grafik batang kumulatif chat pertama kali '.$customerCumulative['label'],
            ],
            [
                'title'=>'Berdasarkan pembayaran pertama kali',
                'subtitle'=>'Kumulatif '.$cPaidMax.' order bayar · '.$rp($customerCumulative['totals']['paid_value']),
                'empty'=>'Belum ada data pembayaran pertama kali pada '.$customerCumulative['label'].'.',
                'field'=>'cum_paid','delta_field'=>'paid_count','ma_field'=>null,
                'raw_max'=>$cPaidMax,'scale'=>$cPaidScale,'color'=>'paid',
                'trend'=>null,
                'lines'=>$cPaidMax>0?$buildDailyLines($cDays,'cum_paid',null,$cPaidScale,$cDayCount,$cActive,true):['daily'=>'','reg'=>null],
                'aria'=>'Grafik batang kumulatif pembayaran pertama kali '.$customerCumulative['label'],
            ],
        ],
    ];
}
?>
@foreach($panels as $panel)
<section class="panel" aria-label="{{ $panel['aria'] }}">
<div class="chart-head">
<div><h2>{{ $panel['heading'] }}</h2>
<p class="chart-sub">{{ $panel['summary'] }}</p></div>
<div class="chart-controls">
<div class="legend">
<span><i class="swatch chat"></i>Chat pertama kali</span>
<span><i class="swatch paid"></i>Pembayaran pertama kali</span>
<span><i class="swatch line-ma"></i>{{ $panel['line_label'] }}</span>
<span><i class="swatch line-trend"></i>{{ $panel['ref_label'] }}</span>
</div>
<form class="inline" method="get" action="/records/orders">
@foreach(['q','chart_range'] as $param)@if(request()->filled($param))<input type="hidden" name="{{ $param }}" value="{{ request($param) }}">@endif @endforeach
@if($panel['mode']==='cumulative')
<input type="hidden" name="chart_month" value="{{ $panel['month'] }}">
<select name="cum_scope" aria-label="Cakupan grafik kumulatif" onchange="this.form.submit()">
<option value="month" @selected($panel['scope']==='month')>Bulan terpilih</option>
<option value="all" @selected($panel['scope']==='all')>Semua bulan (Tgl 1–31)</option>
</select>
@else
@if(request()->filled('cum_scope'))<input type="hidden" name="cum_scope" value="{{ request('cum_scope') }}">@endif
<input type="month" name="chart_month" value="{{ $panel['month'] }}" aria-label="Bulan grafik pelanggan" onchange="this.form.submit()">
@endif
</form>
</div></div>
<div class="chart-stack">
@foreach($panel['charts'] as $chart)
@php($tr=$chart['trend'])
<div class="chart-block">
<h3><span>{{ $chart['title'] }}</span><small>{{ $chart['subtitle'] }}@if($tr && $chart['raw_max']>0) · Rata-rata {{ number_format($tr['avg'],1,',','.') }}/hari · Puncak {{ $tr['peak'] }} (tgl {{ $tr['peak_day'] }}) · Tren: <b class="trend-badge {{ strtolower($tr['direction']) }}">{{ $tr['direction'] }}</b>@endif</small></h3>
@if($chart['raw_max']<=0)<p class="chart-sub">{{ $chart['empty'] }}</p>@endif
<div class="bar-chart">
<div class="y-axis compact" aria-hidden="true">@foreach([1,0.75,0.5,0.25,0] as $t)<span>{{ $chart['raw_max']>0?(int)round($chart['scale']*$t):0 }}</span>@endforeach</div>
<div class="plot-scroll"><div class="plot daily" style="grid-template-columns:repeat({{ $panel['day_count'] }},minmax(24px,1fr))" role="img" aria-label="{{ $chart['aria'] }}">
@if($chart['raw_max']>0 && ($chart['lines']['daily'] || $chart['lines']['reg']))
<svg class="trend-svg" viewBox="0 0 1000 100" preserveAspectRatio="none" aria-hidden="true">
@if($chart['lines']['reg'])<line class="trend-linear" x1="{{ $chart['lines']['reg']['x1'] }}" y1="{{ $chart['lines']['reg']['y1'] }}" x2="{{ $chart['lines']['reg']['x2'] }}" y2="{{ $chart['lines']['reg']['y2'] }}" vector-effect="non-scaling-stroke"/>@endif
@if($chart['lines']['daily'])<polyline class="trend-ma" points="{{ $chart['lines']['daily'] }}" fill="none" vector-effect="non-scaling-stroke"/>@endif
</svg>
@endif
@foreach($panel['days'] as $idx=>$d)
@php($val=$d[$chart['field']]??null)
@php($delta=$d[$chart['delta_field']]??0)
@php($ma=$chart['ma_field']?($d[$chart['ma_field']]??null):null)
@php($pct=$val!==null && $val>0?round(($val/$chart['scale'])*100,2):0)
<div class="month-col" tabindex="0">
<div class="chart-tip"><strong>@if($d['weekday']){{ $d['weekday'] }}, @endif{{ $d['date'] }}</strong><br>
@if($panel['mode']==='cumulative')
@if($val===null)
Belum memasuki tanggal ini
@elseif($chart['field']==='cum_chat')
Kumulatif chat: {{ $val }} order @if($delta>0)<br>Tambahan hari ini: +{{ $delta }}@endif
@else
Kumulatif bayar: {{ $val }} order @if($delta>0)<br>Tambahan hari ini: +{{ $delta }}@endif @if(($d['cum_paid_value']??0)>0)<br>Nilai kumulatif: {{ $rp($d['cum_paid_value']) }}@endif
@endif
@else
@if($chart['field']==='chat_count')
Chat pertama: {{ $d['chat_count'] }} order
@if($d['chat_count']>0)<br>Order baru: {{ $d['chat_new_count'] }}@endif
@else
Pembayaran pertama: {{ $d['paid_count'] }} order
@if($d['paid_count']>0)<br>Nilai order: {{ $rp($d['paid_value']) }}@endif
@endif
@if($ma!==null && $chart['raw_max']>0)<br>Rata-rata 7 hari: {{ number_format($ma,1,',','.') }}@endif
@endif
</div>
<div class="bars"><div class="bar-col {{ $chart['color'] }}" style="height:{{ $pct }}%" title="@if($d['weekday']){{ $d['weekday'] }}, @endif{{ $d['date'] }}: {{ $val ?? 0 }} order"></div></div>
@if($idx<$panel['active_days'] && $val!==null && $chart['raw_max']>0)<span class="trend-dot" style="bottom:{{ $pct }}%" aria-hidden="true"></span>@endif
<span class="m-label">{{ $d['day'] }}@if($d['weekday'])<small>{{ $d['weekday'] }}</small>@endif</span>
</div>
@endforeach
</div></div></div>
</div>
@endforeach
</div>
<details class="chart-details"><summary>{{ $panel['details_label'] }}</summary><div class="scroll"><table>
@if($panel['mode']==='cumulative')
<tr><th>Tanggal</th><th>Chat (+hari)</th><th>Kumulatif chat</th><th>Bayar (+hari)</th><th>Kumulatif bayar</th><th>Nilai kumulatif</th></tr>
@foreach($panel['days'] as $d)<tr><td>@if($d['weekday']){{ $d['weekday'] }}, @endif{{ $d['date'] }}</td><td>{{ $d['chat_count'] }}</td><td>{{ $d['cum_chat'] ?? '—' }}</td><td>{{ $d['paid_count'] }}</td><td>{{ $d['cum_paid'] ?? '—' }}</td><td>{{ $d['cum_paid_value']!==null?$rp($d['cum_paid_value']):'—' }}</td></tr>@endforeach
@else
<tr><th>Tanggal</th><th>Chat pertama kali</th><th>Order baru</th><th>Pembayaran pertama kali</th><th>Nilai order bayar</th></tr>
@foreach($panel['days'] as $d)<tr><td>{{ $d['weekday'] }}, {{ $d['date'] }}</td><td>{{ $d['chat_count'] }}</td><td>{{ $d['chat_new_count'] }}</td><td>{{ $d['paid_count'] }}</td><td>{{ $rp($d['paid_value']) }}</td></tr>@endforeach
@endif
</table></div></details>
</section>
@endforeach
