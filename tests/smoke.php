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
