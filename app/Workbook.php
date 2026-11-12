<?php
namespace App;

use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use PhpOffice\PhpSpreadsheet\Reader\Xlsx;
use PhpOffice\PhpSpreadsheet\Shared\Date;

class Workbook
{
    public function import(string $path): array {
        $reader=new Xlsx;
        $layouts=Records::layouts();
        $names=$reader->listWorksheetNames($path);
        $wanted=array_intersect(['Akun & Saldo','Transaksi','Pelanggan','Iklan'],$names);
        if(!$wanted) throw new \DomainException('Lembar Transaksi atau Pelanggan tidak ditemukan.');
        $reader->setLoadSheetsOnly(array_values($wanted));
        $book=$reader->load($path);
        return DB::transaction(function() use($book,$layouts) {
            $counts=['saved'=>0,'skipped'=>0]; $seen=[];
            foreach(['accounts','journal','orders','ads'] as $table){
                $layout=$layouts[$table]; $sheet=$book->getSheetByName($layout['sheet']);
                if(!$sheet) continue;
                for($i=$layout['row'];$i<=$sheet->getHighestDataRow();$i++){
                    $data=[];
                    foreach($layout['fields'] as $j=>$key){
                        $cell=$sheet->getCell([$j+1,$i]); $v=$cell->getValue();
                        if($i===$layout['row']){
                            if(trim((string)$v)!==$layout['headers'][$j]) throw new \DomainException("Header {$sheet->getTitle()} kolom ".($j+1)." berbeda.");
                            continue;
                        }
                        if($key==='_') continue;
                        if($cell->isFormula()) $v=$cell->getOldCalculatedValue();
                        if($v==='' || $v===null) { $data[$key]=null; continue; }
                        if(in_array($key,['date','due','chat','paid','start','month'],true) && is_numeric($v))
                            $v=Date::excelToDateTimeObject($v)->format('Y-m-d');
                        if($key==='hours' && Date::isDateTime($cell) && is_numeric($v))
                            $v=Date::excelToDateTimeObject($v)->format('Y-m-d');
                        if(in_array($key,['debit','credit'],true)) $v=explode(' - ',(string)$v,2)[0];
                        $data[$key]=trim((string)$v);
                    }
                    if(!array_filter($data,fn($v)=>$v!==null)) continue;
                    // Account schedule ends with a totals line outside the chart.
                    if($table==='accounts' && !$data['code'] && !$data['name']) continue;
                    try { $data=Records::validate($table,$data); }
                    catch(ValidationException $e){ throw new \DomainException($sheet->getTitle()." baris $i: ".implode(' ',array_merge(...array_values($e->errors())))); }
                    if($table==='accounts'){
                        $data['opening_debit']??=0; $data['opening_credit']??=0;
                        DB::table($table)->updateOrInsert(['code'=>$data['code']],$data);
                    } else {
                        if($table==='journal'){
                            $hash=hash('sha256',json_encode($data,JSON_THROW_ON_ERROR));
                            $seen[$hash]=($seen[$hash]??0)+1; $data['import_key']=$hash.'-'.$seen[$hash];
                        }
                        $key=match($table){'journal'=>'import_key','orders'=>'order_id','ads'=>'month'};
                        if(DB::table($table)->where($key,$data[$key])->exists()){ $counts['skipped']++; continue; }
                        DB::table($table)->insert($data);
                        if($table==='orders') OrderNumbers::observe($data['order_id']);
                    }
                    $counts['saved']++;
                }
            }
            return $counts;
        });
    }
}
