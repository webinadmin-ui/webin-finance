<?php
namespace App;
use Illuminate\Support\Facades\DB;

class Reports
{
    public function finance(string $from,string $to): array {
        $accounts=DB::table('accounts')->orderBy('code')->get()->keyBy('code');
        $trial=[]; $flows=[]; $income=[]; $balance=[];
        foreach($accounts as $a) $trial[$a->code]=['name'=>$a->name,'opening'=>$a->opening_debit-$a->opening_credit,'debit'=>0,'credit'=>0];
        foreach(DB::table('journal')->where('date','<=',$to)->orderBy('id')->cursor() as $entry){
            foreach(['debit'=>1,'credit'=>-1] as $side=>$sign){
                $code=$entry->$side;
                if($entry->date<$from) $trial[$code]['opening']+=$sign*$entry->amount;
                else $trial[$code][$side]+=$entry->amount;
            }
            $cash=((int)($accounts[$entry->debit]->group_name==='Kas')-(int)($accounts[$entry->credit]->group_name==='Kas'))*$entry->amount;
            if($entry->date >= $from && $cash!=0){
                $label=$entry->activity ?: 'Belum diklasifikasi';
                $flows[$label]=($flows[$label]??0)+$cash;
            }
        }
        $openingEquity=0; $closingProfit=0; $equityMovement=0;
        foreach($trial as $code=>&$r){
            $change=$r['debit']-$r['credit']; $r['closing']=$r['opening']+$change;
            $group=$accounts[$code]->group_name; $class=substr((string)$code,0,1);
            if((int)$class>=4){
                $income[$group]=($income[$group]??0)-$change;
                $closingProfit-=$r['closing']; $openingEquity-=$r['opening'];
            }else{
                $sign=$class==='1'?1:-1;
                $balance[$class]=($balance[$class]??0)+$sign*$r['closing'];
                if($class==='3'){ $openingEquity-=$r['opening']; $equityMovement-=$change; }
            }
        }
        unset($r);
        $balance[3]=($balance[3]??0)+$closingProfit;
        return compact('trial','flows','income','balance','openingEquity','equityMovement');
    }
    public function acquisition(string $from,string $to): array {
        $new=DB::table('orders')->where('source','Google Ads')->where('kind','Baru');
        $cohort=(clone $new)->whereBetween('chat',[$from,$to]);
        $leads=(clone $cohort)->count(); $converted=(clone $cohort)->where('status','Bayar')->whereNotNull('paid')->count();
        $paid=DB::table('orders')->where('status','Bayar')->whereBetween('paid',[$from,$to]);
        $newPaid=(clone $new)->where('status','Bayar')->whereBetween('paid',[$from,$to]);
        $acquired=(clone $newPaid)->count();
        $adSpend=DB::table('ads')->whereBetween('month',[$from,$to])->selectRaw('sum(spend) as total')->first()->total;
        return ['Lead baru Google Ads'=>$leads,'Closing kelompok chat'=>$converted,'Closing kelompok chat (%)'=>$leads?100*$converted/$leads:null,
            'Order baru bayar Google Ads'=>$acquired,'Spend Google Ads (Rp)'=>$adSpend,
            'CAC kelompok chat (Rp)'=>$converted && $adSpend!==null?$adSpend/$converted:null,
            'CAC bulan bayar (Rp)'=>$acquired && $adSpend!==null?$adSpend/$acquired:null,
            'Rata-rata order baru Google Ads (Rp)'=>(clone $newPaid)->avg('value'),
            'Nilai order bayar (Rp)'=>(clone $paid)->sum('value'),'Kas kumulatif order (Rp)'=>(clone $paid)->sum('cash'),
            'HPP order bayar (Rp)'=>(clone $paid)->whereNull('cost')->exists()?null:(clone $paid)->sum('cost'),
            'Order renewal bayar'=>(clone $paid)->where('kind','Renewal')->count(),
            'Renewal jatuh tempo'=>DB::table('orders')->whereBetween('expires',[$from,$to])->count()];
    }

    public function monthlySales(int $year): array {
        $start=sprintf('%04d-01-01',$year); $end=sprintf('%04d-12-31',$year);
        $labels=['Jan','Feb','Mar','Apr','Mei','Jun','Jul','Agu','Sep','Okt','Nov','Des'];
        $months=[];
        for($m=1;$m<=12;$m++){
            $months[$m]=['month'=>$m,'key'=>sprintf('%04d-%02d',$year,$m),'label'=>$labels[$m-1],
                'revenue'=>0.0,'order_value'=>0.0,'order_count'=>0];
        }
        $revenueSet=array_flip(DB::table('accounts')->where('group_name','Pendapatan')->pluck('code')->all());
        if($revenueSet){
            foreach(DB::table('journal')->whereBetween('date',[$start,$end])->select('date','debit','credit','amount')->cursor() as $entry){
                $m=(int)substr((string)$entry->date,5,2);
                if(!isset($months[$m])) continue;
                if(isset($revenueSet[$entry->credit])) $months[$m]['revenue']+=(float)$entry->amount;
                if(isset($revenueSet[$entry->debit])) $months[$m]['revenue']-=(float)$entry->amount;
            }
        }
        foreach(DB::table('orders')->where('status','Bayar')->whereBetween('paid',[$start,$end])->select('paid','value')->cursor() as $order){
            $m=(int)substr((string)$order->paid,5,2);
            if(!isset($months[$m])) continue;
            $months[$m]['order_value']+=(float)($order->value??0);
            $months[$m]['order_count']++;
        }
        $totals=['revenue'=>array_sum(array_column($months,'revenue')),
            'order_value'=>array_sum(array_column($months,'order_value')),
            'order_count'=>array_sum(array_column($months,'order_count'))];
        $jRange=DB::table('journal')->selectRaw('min(date) as min_d, max(date) as max_d')->first();
        $oRange=DB::table('orders')->where('status','Bayar')->whereNotNull('paid')->selectRaw('min(paid) as min_d, max(paid) as max_d')->first();
        $years=[$year,(int)date('Y')];
        foreach([$jRange?->min_d,$jRange?->max_d,$oRange?->min_d,$oRange?->max_d] as $d){
            if($d && strlen((string)$d)>=4) $years[]=(int)substr((string)$d,0,4);
        }
        $availableYears=range(min($years),max($years));
        rsort($availableYears);
        return ['year'=>$year,'months'=>array_values($months),'totals'=>$totals,'available_years'=>$availableYears];
    }

    public function customerDaily(string $month): array {
        $anchor=\Carbon\CarbonImmutable::parse($month.'-01');
        $start=$anchor->startOfMonth()->toDateString();
        $end=$anchor->endOfMonth()->toDateString();
        $monthNames=['Januari','Februari','Maret','April','Mei','Juni','Juli','Agustus','September','Oktober','November','Desember'];
        $dayNames=['Min','Sen','Sel','Rab','Kam','Jum','Sab'];
        $monthLabel=$monthNames[$anchor->month-1].' '.$anchor->year;
        $days=[];
        for($d=1;$d<=$anchor->daysInMonth;$d++){
            $current=$anchor->day($d);
            $date=$current->toDateString();
            $days[$date]=['day'=>$d,'weekday'=>$dayNames[$current->dayOfWeek],'date'=>$date,
                'chat_count'=>0,'chat_new_count'=>0,'chat_ma7'=>null,
                'paid_count'=>0,'paid_value'=>0.0,'paid_ma7'=>null];
        }
        foreach(DB::table('orders')->whereBetween('chat',[$start,$end])->select('chat','kind')->cursor() as $order){
            $date=substr((string)$order->chat,0,10);
            if(!isset($days[$date])) continue;
            $days[$date]['chat_count']++;
            if($order->kind==='Baru') $days[$date]['chat_new_count']++;
        }
        foreach(DB::table('orders')->where('status','Bayar')->whereBetween('paid',[$start,$end])->select('paid','value')->cursor() as $order){
            $date=substr((string)$order->paid,0,10);
            if(!isset($days[$date])) continue;
            $days[$date]['paid_count']++;
            $days[$date]['paid_value']+=(float)($order->value??0);
        }
        $daysList=array_values($days);
        $maxDataDay=0;
        foreach($daysList as $i=>$row){
            if($row['chat_count']>0 || $row['paid_count']>0) $maxDataDay=$i+1;
        }
        $today=\Carbon\CarbonImmutable::today('Asia/Jakarta');
        $currentMonth=$today->format('Y-m');
        $activeDays=match(true){
            $month<$currentMonth=>count($daysList),
            $month===$currentMonth=>max($today->day,$maxDataDay),
            default=>$maxDataDay,
        };
        $activeDays=min(count($daysList),$activeDays);
        $chatTrend=$this->dailyTrend($daysList,'chat_count','chat_ma7',$activeDays);
        $paidTrend=$this->dailyTrend($daysList,'paid_count','paid_ma7',$activeDays);
        $totals=[
            'chat_count'=>array_sum(array_column($daysList,'chat_count')),
            'chat_new_count'=>array_sum(array_column($daysList,'chat_new_count')),
            'paid_count'=>array_sum(array_column($daysList,'paid_count')),
            'paid_value'=>array_sum(array_column($daysList,'paid_value')),
        ];
        return ['month'=>$month,'month_label'=>$monthLabel,'active_days'=>$activeDays,'days'=>$daysList,
            'totals'=>$totals,'trends'=>['chat'=>$chatTrend,'paid'=>$paidTrend]];
    }

    public function customerCumulative(): array {
        $days=[];
        for($d=1;$d<=31;$d++){
            $days[$d]=['day'=>$d,'weekday'=>null,'date'=>'Tgl '.$d,
                'chat_count'=>0,'chat_new_count'=>0,'chat_ma7'=>null,
                'paid_count'=>0,'paid_value'=>0.0,'paid_ma7'=>null];
        }
        $months=[];
        foreach(DB::table('orders')->whereNotNull('chat')->select('chat','kind')->cursor() as $o){
            $d=(int)substr((string)$o->chat,8,2);
            if(!isset($days[$d])) continue;
            $months[substr((string)$o->chat,0,7)]=true;
            $days[$d]['chat_count']++;
            if($o->kind==='Baru') $days[$d]['chat_new_count']++;
        }
        foreach(DB::table('orders')->where('status','Bayar')->whereNotNull('paid')->select('paid','value')->cursor() as $o){
            $d=(int)substr((string)$o->paid,8,2);
            if(!isset($days[$d])) continue;
            $months[substr((string)$o->paid,0,7)]=true;
            $days[$d]['paid_count']++;
            $days[$d]['paid_value']+=(float)($o->value??0);
        }
        $daysList=array_values($days);
        $chatTrend=$this->dailyTrend($daysList,'chat_count','chat_ma7',31);
        $paidTrend=$this->dailyTrend($daysList,'paid_count','paid_ma7',31);
        $totals=[
            'chat_count'=>array_sum(array_column($daysList,'chat_count')),
            'chat_new_count'=>array_sum(array_column($daysList,'chat_new_count')),
            'paid_count'=>array_sum(array_column($daysList,'paid_count')),
            'paid_value'=>array_sum(array_column($daysList,'paid_value')),
        ];
        return [
            'label'=>'Semua bulan · Tgl 1–31',
            'months'=>count($months),
            'active_days'=>31,
            'days'=>$daysList,
            'totals'=>$totals,
            'trends'=>['chat'=>$chatTrend,'paid'=>$paidTrend],
        ];
    }

    private function dailyTrend(array &$days,string $field,string $maField,int $activeDays): array {
        $total=0; $peakVal=0; $peakDay=null; $peakDate=null;
        $sumX=0.0; $sumY=0.0; $sumXX=0.0; $sumXY=0.0;
        for($i=0;$i<$activeDays;$i++){
            $v=(float)$days[$i][$field];
            $total+=$v;
            if($v>$peakVal){ $peakVal=(int)$v; $peakDay=$days[$i]['day']; $peakDate=$days[$i]['date']; }
            $wStart=max(0,$i-6); $wSum=0.0;
            for($j=$wStart;$j<=$i;$j++) $wSum+=$days[$j][$field];
            $days[$i][$maField]=round($wSum/($i-$wStart+1),2);
            $sumX+=$i; $sumY+=$v; $sumXX+=$i*$i; $sumXY+=$i*$v;
        }
        $slope=0.0; $intercept=0.0;
        if($activeDays>=2){
            $den=$activeDays*$sumXX-$sumX*$sumX;
            if($den>0){
                $slope=($activeDays*$sumXY-$sumX*$sumY)/$den;
                $intercept=($sumY-$slope*$sumX)/$activeDays;
            }
        }
        $direction=$peakVal===0 || $activeDays<2 || abs($slope*($activeDays-1))<0.25 ? 'Stabil' : ($slope>0 ? 'Naik' : 'Turun');
        return [
            'avg'=>$activeDays>0?round($total/$activeDays,1):0.0,
            'peak'=>$peakVal,
            'peak_day'=>$peakDay,
            'peak_date'=>$peakDate,
            'slope'=>round($slope,4),
            'intercept'=>round($intercept,4),
            'direction'=>$direction,
        ];
    }

    public function customerWeeklyPattern(string $range='all',string $month=''): array {
        $today=\Carbon\CarbonImmutable::today('Asia/Jakarta');
        $month=$month ?: $today->format('Y-m');
        $chatBounds=DB::table('orders')->whereNotNull('chat')->selectRaw('min(chat) as min_d, max(chat) as max_d')->first();
        $paidBounds=DB::table('orders')->where('status','Bayar')->whereNotNull('paid')->selectRaw('min(paid) as min_d, max(paid) as max_d')->first();
        $minDates=array_filter([$chatBounds?->min_d,$paidBounds?->min_d]);
        $maxDates=array_filter([$chatBounds?->max_d,$paidBounds?->max_d]);

        if($range==='month'){
            $anchor=\Carbon\CarbonImmutable::parse($month.'-01');
            $start=$anchor->startOfMonth()->toDateString();
            $endMonth=$anchor->endOfMonth();
            $monthMax=null;
            foreach(DB::table('orders')->whereBetween('chat',[$start,$endMonth->toDateString()])->selectRaw('max(chat) as m')->pluck('m') as $d) if($d) $monthMax=$d;
            foreach(DB::table('orders')->where('status','Bayar')->whereBetween('paid',[$start,$endMonth->toDateString()])->selectRaw('max(paid) as m')->pluck('m') as $d) if($d && (!$monthMax || $d>$monthMax)) $monthMax=$d;
            $end=match(true){
                $month<$today->format('Y-m')=>$endMonth->toDateString(),
                $month===$today->format('Y-m')=>min($endMonth,max($today,$monthMax?\Carbon\CarbonImmutable::parse($monthMax):$today))->toDateString(),
                default=>$monthMax ?: null,
            };
        }elseif(in_array($range,['3m','6m'],true)){
            $monthsBack=$range==='3m'?2:5;
            $start=$today->startOfMonth()->subMonthsNoOverflow($monthsBack)->toDateString();
            $maxAll=$maxDates?max($maxDates):null;
            $end=($maxAll && $maxAll>$today->toDateString()) ? $maxAll : $today->toDateString();
        }else{
            $range='all';
            $start=$minDates ? \Carbon\CarbonImmutable::parse(min($minDates))->startOfMonth()->toDateString() : null;
            $maxAll=$maxDates?max($maxDates):null;
            $end=$start ? (($maxAll && $maxAll>$today->toDateString()) ? $maxAll : $today->toDateString()) : null;
        }

        $bucketMeta=[
            0=>['key'=>'w1','label'=>'Minggu 1','range'=>'Tgl 1–7','phase'=>'Awal'],
            1=>['key'=>'w2','label'=>'Minggu 2','range'=>'Tgl 8–15','phase'=>'Tengah'],
            2=>['key'=>'w3','label'=>'Minggu 3','range'=>'Tgl 16–23','phase'=>'Tengah'],
            3=>['key'=>'w4','label'=>'Minggu 4','range'=>'Tgl 24–akhir','phase'=>'Akhir'],
        ];
        $bucketDays=[0=>0,1=>0,2=>0,3=>0];
        $monthCount=0;
        if($start && $end && $start<=$end){
            $s=\Carbon\CarbonImmutable::parse($start);
            $e=\Carbon\CarbonImmutable::parse($end);
            $monthCursor=$s->startOfMonth();
            while($monthCursor->lte($e)){
                $monthCount++;
                $fromDay=$monthCursor->isSameMonth($s)?$s->day:1;
                $toDay=$monthCursor->isSameMonth($e)?$e->day:$monthCursor->daysInMonth;
                for($d=$fromDay;$d<=$toDay;$d++){
                    $b=$d<=7?0:($d<=15?1:($d<=23?2:3));
                    $bucketDays[$b]++;
                }
                $monthCursor=$monthCursor->addMonthNoOverflow();
            }
        }

        $counts=['chat'=>[0=>0,1=>0,2=>0,3=>0],'paid'=>[0=>0,1=>0,2=>0,3=>0]];
        $paidValue=[0=>0.0,1=>0.0,2=>0.0,3=>0.0];
        if($start && $end && $start<=$end){
            foreach(DB::table('orders')->whereBetween('chat',[$start,$end])->select('chat')->cursor() as $o){
                $d=(int)substr((string)$o->chat,8,2);
                $b=$d<=7?0:($d<=15?1:($d<=23?2:3));
                $counts['chat'][$b]++;
            }
            foreach(DB::table('orders')->where('status','Bayar')->whereBetween('paid',[$start,$end])->select('paid','value')->cursor() as $o){
                $d=(int)substr((string)$o->paid,8,2);
                $b=$d<=7?0:($d<=15?1:($d<=23?2:3));
                $counts['paid'][$b]++;
                $paidValue[$b]+=(float)($o->value??0);
            }
        }

        $buildMetric=function(string $metric) use($bucketMeta,$bucketDays,$counts,$paidValue){
            $total=array_sum($counts[$metric]);
            $buckets=[];
            for($b=0;$b<4;$b++){
                $days=$bucketDays[$b];
                $c=$counts[$metric][$b];
                $buckets[]=$bucketMeta[$b]+[
                    'count'=>$c,
                    'days'=>$days,
                    'rate'=>$days>0?round($c/$days,3):0.0,
                    'share'=>$total>0?round(100*$c/$total,1):0.0,
                    'value'=>$metric==='paid'?$paidValue[$b]:0.0,
                ];
            }
            $endsCount=$buckets[0]['count']+$buckets[3]['count'];
            $endsDays=$buckets[0]['days']+$buckets[3]['days'];
            $midCount=$buckets[1]['count']+$buckets[2]['count'];
            $midDays=$buckets[1]['days']+$buckets[2]['days'];
            $endsRate=$endsDays>0?$endsCount/$endsDays:0.0;
            $midRate=$midDays>0?$midCount/$midDays:0.0;
            $diffPct=$endsRate>0?round((($midRate-$endsRate)/$endsRate)*100,1):($midRate>0?100.0:null);
            $totalDays=$endsDays+$midDays;
            $expEnds=$totalDays>0?$total*($endsDays/$totalDays):0.0;
            $expMid=$totalDays>0?$total*($midDays/$totalDays):0.0;
            $pValue=($expEnds>0 && $expMid>0)?$this->chiSquareP1((($endsCount-$expEnds)**2)/$expEnds+(($midCount-$expMid)**2)/$expMid):1.0;
            $reliable=$total>=15 && $expEnds>=5 && $expMid>=5;

            if($total===0){
                $verdict=['tone'=>'neutral','label'=>'Belum ada data','detail'=>'Belum ada order pada rentang waktu ini.'];
            }elseif(!$reliable){
                $dir=$diffPct===null?'seimbang':($diffPct<0?abs($diffPct).'% lebih rendah':'+'.abs($diffPct).'% lebih tinggi');
                $verdict=['tone'=>'caution','label'=>"Sampel masih kecil (N = $total)",
                    'detail'=>"Awal-akhir rata-rata ".number_format($endsRate,2,',','.')."/hari vs tengah $dir. Butuh minimal ~15–30 order agar uji statistik lebih stabil."];
            }elseif($pValue<0.05 && $midRate<$endsRate){
                $verdict=['tone'=>'confirmed','label'=>'Mendukung hipotesis (p = '.number_format($pValue,3,',','.').')',
                    'detail'=>'Minggu tengah (Tgl 8–23) '.abs($diffPct).'% lebih rendah per hari dibanding awal & akhir bulan.'];
            }elseif($pValue<0.05 && $midRate>$endsRate){
                $verdict=['tone'=>'opposite','label'=>'Berlawanan dengan hipotesis (p = '.number_format($pValue,3,',','.').')',
                    'detail'=>'Minggu tengah justru '.abs($diffPct).'% lebih tinggi per hari dibanding awal & akhir bulan.'];
            }else{
                $verdict=['tone'=>'neutral','label'=>'Belum signifikan (p = '.number_format($pValue,3,',','.').')',
                    'detail'=>'Selisih '.($diffPct!==null?($diffPct>=0?'+':'').$diffPct.'%':'0%').' masih bisa disebabkan variasi acak.'];
            }

            return [
                'total'=>$total,
                'buckets'=>$buckets,
                'ends'=>['count'=>$endsCount,'days'=>$endsDays,'rate'=>round($endsRate,3),'share'=>$total>0?round(100*$endsCount/$total,1):0.0],
                'mid'=>['count'=>$midCount,'days'=>$midDays,'rate'=>round($midRate,3),'share'=>$total>0?round(100*$midCount/$total,1):0.0],
                'diff_pct'=>$diffPct,
                'p_value'=>round($pValue,4),
                'reliable'=>$reliable,
                'verdict'=>$verdict,
            ];
        };

        return [
            'range'=>$range,
            'start'=>$start,
            'end'=>$end,
            'months'=>$monthCount,
            'total_days'=>array_sum($bucketDays),
            'chat'=>$buildMetric('chat'),
            'paid'=>$buildMetric('paid'),
        ];
    }

    private function chiSquareP1(float $chi2): float {
        if($chi2<=0) return 1.0;
        $x=sqrt($chi2/2.0);
        $t=1.0/(1.0+0.3275911*$x);
        $poly=((((1.061405429*$t-1.453152027)*$t)+1.421413741)*$t-0.284496736)*$t+0.254829592;
        return max(0.0,min(1.0,$poly*$t*exp(-$x*$x)));
    }
}
