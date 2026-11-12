<?php
use App\{Records,Reports,Workbook};
use Carbon\CarbonImmutable;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\{DB,Hash,Route};

Route::view('/login','login')->name('login');
Route::post('/login',function(Request $r){
    $hash=DB::table('admins')->value('password');
    if(!$hash || !Hash::check((string)$r->input('password'),$hash)) return back()->withErrors(['password'=>'Password tidak cocok.']);
    $r->session()->regenerate(); $r->session()->put('admin',hash('sha256',$hash));
    return redirect('/');
})->middleware('throttle:6,1');

Route::middleware(\App\AdminAccess::class)->group(function(){
    Route::post('/logout',function(Request $r){$r->session()->invalidate(); $r->session()->regenerateToken(); return redirect('/login');});
    Route::get('/',function(Request $r,Reports $reports){
        $r->validate(['from'=>'nullable|date_format:Y-m-d','to'=>'nullable|date_format:Y-m-d|after_or_equal:from']);
        $from=$r->input('from') ?: now()->startOfMonth()->toDateString(); $to=$r->input('to') ?: now()->endOfMonth()->toDateString();
        return view('dashboard',['from'=>$from,'to'=>$to,'acquisition'=>$reports->acquisition($from,$to)]+$reports->finance($from,$to));
    });
    Route::view('/import','import');
    Route::post('/import',function(Request $r,Workbook $reader){
        $r->validate(['file'=>'required|file|extensions:xlsx|max:16384']);
        try{$counts=$reader->import($r->file('file')->getRealPath());}
        catch(\DomainException $e){return back()->withErrors(['file'=>$e->getMessage()]);}
        catch(\PhpOffice\PhpSpreadsheet\Reader\Exception $e){return back()->withErrors(['file'=>'File Excel tidak dapat dibaca.']);}
        return back()->with('message',"Disimpan: {$counts['saved']}. Sudah ada: {$counts['skipped']}.");
    });
    Route::get('/subscriptions',function(Request $r){
        $today=now('Asia/Jakarta')->toDateString();
        $q=DB::table('orders as o')->where('status','Bayar')->whereRaw("lower(coalesce(duration,'')) <> 'one time'")
            ->whereNotExists(fn($q)=>$q->selectRaw('1')->from('orders as n')->whereColumn('n.renews','o.order_id'));
        match($r->input('filter')){
            'missing'=>$q->whereNull('expires'),'overdue'=>$q->where('expires','<',$today),
            'soon'=>$q->whereBetween('expires',[$today,CarbonImmutable::parse($today)->addDays(30)->toDateString()]),default=>null};
        return view('subscriptions',['rows'=>$q->orderBy('expires')->paginate(50)->withQueryString(),'today'=>$today]);
    });
    Route::get('/records/{table}',function(Request $r,string $table){
        $layout=Records::layouts()[$table]; $key=match($table){'journal'=>'id','orders'=>'order_id','ads'=>'month'};
        $q=DB::table($table);
        if($term=$r->input('q')) $q->where(function($q)use($layout,$term){
            foreach(array_diff($layout['fields'],['_']) as $field) $q->orWhere($field,'like','%'.$term.'%');
        });
        $editing=$r->filled('edit')?$q->newQuery()->from($table)->where($key,$r->input('edit'))->first():null;
        $parent=$table==='orders' && $r->filled('renew')?DB::table('orders')->where('order_id',$r->input('renew'))->first():null;
        return view('records',compact('layout','table','key','editing','parent')+['rows'=>$q->orderByDesc(match($table){'orders'=>'chat','journal'=>'date','ads'=>'month'})->paginate(30)->withQueryString(),
            'accounts'=>DB::table('accounts')->orderBy('code')->get()]);
    })->whereIn('table',['journal','orders','ads']);
    Route::post('/records/{table}',function(Request $r,string $table){
        $key=match($table){'journal'=>'id','orders'=>'order_id','ads'=>'month'};
        $data=Records::validate($table,$r->all());
        if($r->filled('edit')){
            $id=$r->input('edit'); abort_unless(DB::table($table)->where($key,$id)->exists(),404);
            unset($data[$key]); DB::table($table)->where($key,$id)->update($data);
        }else{
            if($table==='orders' && $r->filled('renews')){
                $p=DB::table('orders')->where('order_id',$r->input('renews'))->first();
                if(!$p || $p->customer!==$data['customer'] || $data['kind']!=='Renewal' || $data['status']!=='Bayar' || empty($data['start']) || empty($data['expires']))
                    return back()->withInput()->withErrors('Periksa pelanggan, tanggal mulai, durasi dan status perpanjangan.');
                $data['renews']=$p->order_id;
            }
            try{DB::table($table)->insert($data);}catch(\Illuminate\Database\UniqueConstraintViolationException $e){
                return back()->withInput()->withErrors('ID, bulan, atau perpanjangan sudah tercatat.');
            }
        }
        return redirect('/records/'.$table)->with('message','Data tersimpan.');
    })->whereIn('table',['journal','orders','ads']);
});
Route::get('/ledger/{code}',function(Request $r,string $code,Reports $reports){
    $period=$r->validate(['from'=>'required|date_format:Y-m-d','to'=>'required|date_format:Y-m-d|after_or_equal:from']);
    $account=DB::table('accounts')->where('code',$code)->first(); abort_unless($account,404);
    $trial=$reports->finance($period['from'],$period['to'])['trial'];
    $rows=DB::table('journal')->whereBetween('date',array_values($period))->where(fn($q)=>$q->where('debit',$code)->orWhere('credit',$code))->orderBy('date')->orderBy('id')->get();
    return view('ledger',$period+compact('rows','account')+['running'=>$trial[$code]['opening']]);
})->middleware(\App\AdminAccess::class);
Route::get('/simulation',function(Request $r){
    $labels=['budget'=>'Budget iklan bruto','cac'=>'CAC','price'=>'Nilai rata-rata order','cost'=>'HPP per order',
        'opex'=>'Operasional lainnya','promo'=>'Kredit promo terpakai','target'=>'Target laba','fund'=>'Dana iklan tersedia'];
    $result=[];
    if($r->has('budget')){
        $rules=array_fill_keys(array_keys($labels),'required|numeric|min:0'); $rules['cac']='required|numeric|gt:0';
        $v=$r->validate($rules); $n=floor($v['budget']/$v['cac']);
        $gross=$n*($v['price']-$v['cost']); $net=max(0,$v['budget']-$v['promo']);
        $margin=$v['price']-$v['cost']-$v['cac']; $needed=$margin>0?ceil(($v['target']+$v['opex'])/$margin):null;
        $result=['Perkiraan pelanggan baru'=>$n,'Omzet order (Rp)'=>$n*$v['price'],'HPP total (Rp)'=>$n*$v['cost'],
            'Laba tanpa bonus (Rp)'=>$gross-$v['budget']-$v['opex'],'Laba dengan bonus (Rp)'=>$gross-$net-$v['opex'],
            'Pelanggan untuk target'=>$needed,'Budget untuk target (Rp)'=>$needed===null?null:$needed*$v['cac'],
            'Kekurangan dana iklan (Rp)'=>max(0,$net-$v['fund'])];
    }
    return view('simulation',compact('labels','result'));
})->middleware(\App\AdminAccess::class);
