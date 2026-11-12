<?php
namespace App;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionAlerts
{
    public static function current(){
        return DB::table('orders as o')->where('o.status','Bayar')
            ->whereRaw("lower(coalesce(o.duration,'')) <> 'one time'")
            ->whereNotExists(fn($q)=>$q->selectRaw('1')->from('orders as n')->whereColumn('n.renews','o.order_id'));
    }

    public static function websites(){
        $domains=self::current()->selectRaw('distinct lower(trim(o.domain)) as domain');
        $latest=DB::table('ssl_updates')->selectRaw('domain,max(id) as id')->groupBy('domain');
        return DB::query()->fromSub($domains,'sites')->leftJoinSub($latest,'latest','sites.domain','=','latest.domain')
            ->leftJoin('ssl_updates as ssl','ssl.id','=','latest.id')
            ->select('sites.domain','ssl.id','ssl.expiry','ssl.recorded_at');
    }

    public static function window(CarbonImmutable $month): array {
        $start=$month->startOfMonth()->addDays(2);
        return [$start->toDateString(),$start->addMonth()->toDateString()];
    }

    public static function counts(CarbonImmutable $today): array {
        return [
            'soon'=>self::current()->whereBetween('o.expires',[$today->toDateString(),$today->addDays(14)->toDateString()])->count(),
            'ssl'=>self::websites()->whereBetween('ssl.expiry',self::window($today))->count(),
            'missing'=>self::websites()->whereNull('ssl.id')->count(),
        ];
    }

    public static function save(string $domain,string $expiry,int $version): void {
        $domain=strtolower(trim($domain));
        $latest=(int)DB::table('ssl_updates')->where('domain',$domain)->max('id');
        if($latest!==$version) throw ValidationException::withMessages(['ssl'=>'Tanggal SSL berubah. Muat ulang untuk melihat tanggal terbaru.']);
        DB::table('ssl_updates')->insert(['domain'=>$domain,'expiry'=>$expiry,'recorded_at'=>now()]);
    }
}
