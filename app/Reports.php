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
}
