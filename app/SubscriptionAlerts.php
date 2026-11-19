<?php
namespace App;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

class SubscriptionAlerts
{
    private static function tracked(){
        return DB::table('orders as o')->where('o.status','Bayar')
            ->whereRaw("lower(coalesce(o.duration,'')) <> 'one time'")
            ->whereNotExists(fn($q)=>$q->selectRaw('1')->from('orders as n')->whereColumn('n.renews','o.order_id'));
    }

    public static function current(){
        return self::tracked()->whereNull('o.stopped_at');
    }

    public static function stopped(){
        return self::tracked()->whereNotNull('o.stopped_at');
    }

    public static function stop(string $scope,string $target,string $stoppedAt,?string $reason=null): int {
        $target=trim($target);
        $reason=$reason!==null && trim($reason)!=='' ? trim($reason) : null;
        return DB::transaction(function() use($scope,$target,$stoppedAt,$reason){
            $q=self::current();
            match($scope){
                'order'=>$q->where('o.order_id',$target),
                'domain'=>$q->whereRaw('lower(trim(o.domain)) = ?',[strtolower($target)]),
                'customer'=>$q->where('o.customer',$target),
            };
            $ids=$q->pluck('o.order_id');
            if($ids->isEmpty()) return 0;
            return DB::table('orders')->whereIn('order_id',$ids)->update(['stopped_at'=>$stoppedAt,'stopped_reason'=>$reason]);
        },3);
    }

    public static function reactivate(string $orderId): int {
        return DB::transaction(function() use($orderId){
            if(!self::stopped()->where('o.order_id',$orderId)->exists()) return 0;
            return DB::table('orders')->where('order_id',$orderId)->update(['stopped_at'=>null,'stopped_reason'=>null]);
        },3);
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
