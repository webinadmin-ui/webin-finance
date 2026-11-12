<?php
namespace App;

use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

class JournalEntry
{
    private const ADS=['ad_spend'=>['spend'],'ad_cash'=>['bank'],'ad_promo'=>['promo'],'ad_paid'=>['spend','bank']];

    public static function save(Request $r,array $data): void {
        DB::transaction(function() use($r,$data){
            $old=null;
            if($r->filled('edit')){
                $old=DB::table('journal')->where('id',$r->input('edit'))->lockForUpdate()->first();
                abort_unless($old,404);
                $purpose=$old->purpose;
                $orderId=$old->order_id;
            }else{
                $r->validate(['submission'=>'required|uuid','purpose'=>'required|in:general,new,renew,payment,cost,ad_spend,ad_cash,ad_promo,ad_paid']);
                if(DB::table('journal')->where('submission',$r->input('submission'))->exists()) return;
                $data['submission']=$r->input('submission');
                $purpose=$r->input('purpose');
                $orderId=null;
                if(in_array($purpose,['new','renew'],true)){
                    $orderId=self::createOrder($r,$purpose);
                    $purpose='payment';
                }elseif(in_array($purpose,['payment','cost'],true)){
                    $r->validate(['linked_order'=>'required|exists:orders,order_id']);
                    $orderId=$r->input('linked_order');
                }
            }
            $data['purpose']=$purpose;
            $data['order_id']=$orderId;
            $order=$orderId===null?null:DB::table('orders')->where('order_id',$orderId)->lockForUpdate()->first();
            if($purpose==='payment' && !$old && $r->input('purpose')==='payment'){
                $service=$r->validate(['service.start'=>'nullable|date_format:Y-m-d','service.duration'=>['nullable','regex:/^([1-9][0-9]{0,2}|one time)$/i'],
                    'service.ssl'=>'nullable|date_format:Y-m-d']);
                $extra=array_filter($service['service']??[],fn($v)=>$v!==null && $v!=='');
                if(isset($extra['start']) || isset($extra['duration'])){
                    $revised=Records::validate('orders',array_merge((array)$order,array_intersect_key($extra,array_flip(['start','duration']))));
                    DB::table('orders')->where('order_id',$orderId)->update(array_intersect_key($revised,array_flip(['start','duration','expires'])));
                }
                if(isset($extra['ssl']) && !DB::table('ssl_updates')->where('domain',strtolower(trim($order->domain)))->exists())
                    SubscriptionAlerts::save($order->domain,$extra['ssl'],0);
            }
            if($purpose==='payment' && !$old && !DB::table('journal')->where('order_id',$orderId)->where('purpose','payment')->exists()){
                DB::table('orders')->where('order_id',$orderId)->update(['prior_paid'=>$order->paid]);
                $order->prior_paid=$order->paid;
            }
            if($old) DB::table('journal')->where('id',$old->id)->update($data);
            else DB::table('journal')->insert($data);

            if($order){
                $delta=$data['amount']-($old->amount??0);
                if($purpose==='cost') $changes=['cost'=>($order->cost??0)+$delta];
                else{
                    $first=DB::table('journal')->where('order_id',$orderId)->where('purpose','payment')->min('date');
                    $changes=['cash'=>($order->cash??0)+$delta,'status'=>'Bayar','paid'=>$order->prior_paid?min($order->prior_paid,$first):$first];
                }
                DB::table('orders')->where('order_id',$orderId)->update($changes);
            }
            if(isset(self::ADS[$purpose])){
                if($old) self::adjustAds($purpose,$old->date,-$old->amount);
                self::adjustAds($purpose,$data['date'],$data['amount']);
            }
        },3);
    }

    private static function createOrder(Request $r,string $purpose): string {
        $r->validate(['order'=>'required|array','order.package'=>'required|string','order.duration'=>'required',
            'order.value'=>'required|numeric|min:0','order.ssl'=>'nullable|date_format:Y-m-d','order.source'=>'required|string',
            'order.chat'=>'required|date_format:Y-m-d|before_or_equal:date','order.kind'=>'nullable|in:Baru,Repeat order']);
        $input=$r->input('order'); $parent=null;
        if($purpose==='renew'){
            $r->validate(['linked_order'=>'required|exists:orders,order_id','order.start'=>'required|date_format:Y-m-d']);
            $parent=SubscriptionAlerts::current()->where('o.order_id',$r->input('linked_order'))->lockForUpdate()->first();
            if(!$parent || !is_numeric($input['duration'])) throw ValidationException::withMessages(['order'=>'Pilih langganan berjalan dan durasi bulanan untuk diperpanjang.']);
            $input['customer']=$parent->customer; $input['domain']=$parent->domain;
        }
        $id=(string)Str::ulid();
        $order=Records::validate('orders',array_merge($input,['order_id'=>$id,'kind'=>$parent?'Renewal':($input['kind']??'Baru'),
            'status'=>'Bayar','cash'=>0,'paid'=>null]));
        $order['renews']=$parent?->order_id;
        DB::table('orders')->insert($order);
        if(!empty($input['ssl'])){
            $domain=strtolower(trim($order['domain']));
            if(!DB::table('ssl_updates')->where('domain',$domain)->exists()) SubscriptionAlerts::save($domain,$input['ssl'],0);
        }
        return $id;
    }

    private static function adjustAds(string $purpose,string $date,float $amount): void {
        $month=substr($date,0,7).'-01';
        DB::table('ads')->insertOrIgnore(['month'=>$month]);
        $q=DB::table('ads')->where('month',$month);
        $row=$q->lockForUpdate()->first(); $changes=[];
        foreach(self::ADS[$purpose] as $field) $changes[$field]=($row->$field??0)+$amount;
        $q->update($changes);
    }
}
