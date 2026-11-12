<?php
use App\{Records,Reports,Workbook};
use Illuminate\Contracts\Console\Kernel;
use Illuminate\Support\Facades\DB;
use PhpOffice\PhpSpreadsheet\{Spreadsheet,Writer\Xlsx};
require __DIR__.'/../vendor/autoload.php';
$app=require __DIR__.'/../bootstrap/app.php';
$app->make(Kernel::class)->bootstrap();
config(['database.connections.sqlite.database'=>':memory:']);
Illuminate\Support\Facades\Artisan::call('migrate',['--force'=>true]);
$book=new Spreadsheet;
foreach(Records::layouts() as $table=>$layout){
    $s=$book->createSheet()->setTitle($layout['sheet']);
    $s->fromArray($layout['headers'],null,'A'.$layout['row']);
    $sample=match($table){
        'journal'=>['2026-09-01',null,'DP',null,null,'1101 - Kas','4101 - Pendapatan jasa website',100,null],
        'accounts'=>['1101 - Kas','1101','Kas','Kas','D',0,0],
        'orders'=>['A1','C1','example.test','2026-09-01','Google Ads','Baru','Bayar','2026-10-01','Demo',1,100,50,10,'2026-01-31'],
        'ads'=>['2026-09-01',80,0,80],
    };
    $s->fromArray($sample,null,'A'.($layout['row']+1));
    if($table==='journal') $s->fromArray($sample,null,'A'.($layout['row']+2));
}
$file=tempnam(sys_get_temp_dir(),'xlsx');
try{
    (new Xlsx($book))->save($file);
    $reader=new Workbook;
    $reader->import($file); $reader->import($file);
    $reports=new Reports; $finance=$reports->finance('2026-09-01','2026-09-30');
    $metrics=$reports->acquisition('2026-09-01','2026-09-30');
    $checks=[DB::table('journal')->count()===2,DB::table('orders')->value('expires')==='2026-02-28',
        array_sum($finance['income'])==200,$finance['flows']['Belum diklasifikasi']==200,
        $metrics['CAC kelompok chat (Rp)']==80,$metrics['Order baru bayar Google Ads']===0];
    if(in_array(false,$checks,true)) throw new RuntimeException('Reconciliation failed: '.json_encode($checks));
    echo "Workbook and reporting checks passed.\n";
}finally{unlink($file);}

function postEntry(array $fields,string $date,int $amount): void {
    $data=Records::validate('journal',['date'=>$date,'debit'=>'1101','credit'=>'4101','amount'=>$amount]);
    App\JournalEntry::save(Illuminate\Http\Request::create('/records/journal','POST',$fields+['date'=>$date]),$data);
}
function verify($condition,string $name): void {
    if(!$condition) throw new RuntimeException($name);
}
$new=['purpose'=>'new','submission'=>(string)Illuminate\Support\Str::uuid(),'order'=>['customer'=>'C2','domain'=>'demo.test',
    'source'=>'Google Ads','kind'=>'Baru','chat'=>'2026-11-01','package'=>'Web','duration'=>'3','start'=>'2026-11-01','value'=>500]];
postEntry($new,'2026-11-02',100);
postEntry($new,'2026-11-02',100);
$order=DB::table('orders')->where('customer','C2')->first();
verify($order->cash==100 && $order->expires==='2027-02-01','Initial payment and service');
postEntry(['purpose'=>'payment','linked_order'=>$order->order_id,'submission'=>(string)Illuminate\Support\Str::uuid()],'2026-11-04',400);
postEntry(['edit'=>DB::table('journal')->where('submission',$new['submission'])->value('id')],'2026-11-05',50);
$order=DB::table('orders')->where('order_id',$order->order_id)->first();
verify($order->paid==='2026-11-04' && $order->cash==450,'Recalculate edited installment');
$metrics=$reports->acquisition('2026-11-01','2026-11-30');
verify($metrics['Order baru bayar Google Ads']===1 && $metrics['Nilai order bayar (Rp)']==500,'One acquisition per order');
$today=Carbon\CarbonImmutable::parse('2027-01-18');
verify(App\SubscriptionAlerts::counts($today)['soon']===1,'14 days including boundary');
foreach(['2027-01-03','2027-02-03','2027-04-30'] as $i=>$expiry){
    App\SubscriptionAlerts::save('demo.test',$expiry,(int)DB::table('ssl_updates')->where('domain','demo.test')->max('id'));
    verify(App\SubscriptionAlerts::counts($today)['ssl']===($i<2?1:0),'SSL inclusive boundaries and reschedule');
}
$renew=$new;
$renew['purpose']='renew'; $renew['submission']=(string)Illuminate\Support\Str::uuid(); $renew['linked_order']=$order->order_id;
$renew['order']['start']='2027-02-01';
postEntry($renew,'2027-01-18',500);
verify(App\SubscriptionAlerts::counts($today)['soon']===0 && App\SubscriptionAlerts::websites()->where('sites.domain','demo.test')->count()===1,'Renewal retains one SSL site');
echo "Journal, acquisition, subscription and SSL checks passed.\n";
$adToken=(string)Illuminate\Support\Str::uuid();
postEntry(['purpose'=>'ad_paid','submission'=>$adToken],'2026-11-06',80);
postEntry(['edit'=>DB::table('journal')->where('submission',$adToken)->value('id')],'2026-12-01',60);
verify(DB::table('ads')->where('month','2026-11-01')->value('spend')==0 && DB::table('ads')->where('month','2026-12-01')->value('bank')==60,'Move ad spend and cash between months');
postEntry(['purpose'=>'cost','linked_order'=>$order->order_id,'submission'=>(string)Illuminate\Support\Str::uuid()],'2026-11-10',60);
verify(DB::table('orders')->where('order_id',$order->order_id)->value('cost')==60,'Linked order cost');
