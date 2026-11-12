@extends('base')
@section('title',$layout['sheet'])
@section('content')
@php($columns=array_diff($layout['fields'],['_']))
@php($defaults=$parent?['customer'=>$parent->customer,'domain'=>$parent->domain,'package'=>$parent->package,'kind'=>'Renewal','source'=>'Renewal','status'=>'Bayar','start'=>$parent->expires,'duration'=>$parent->duration]:[])
<details class="panel" @if($editing || $parent || $errors->any()) open @endif><summary>{{ $editing?'Edit data':($parent?'Perpanjang langganan':'Tambah data') }}</summary>
<form method="post" action="/records/{{ $table }}">@csrf
@if($editing)<input type="hidden" name="edit" value="{{ $editing->$key }}">@endif
@if($parent && $table==='orders')<input type="hidden" name="renews" value="{{ $parent->order_id }}">@endif
<div class="fields">@foreach($columns as $index=>$field)
@php($value=old($field,$editing->$field ?? $defaults[$field] ?? ''))
@if($table==='orders' && $field==='customer')
@include('customer-choice',['currentCustomer'=>$editing->customer ?? $parent->customer ?? null])
@elseif($table==='orders' && $field==='order_id')
<label>ID order<input value="{{ $editing->order_id ?? 'Otomatis saat disimpan' }}" readonly></label>
@else
<label>{{ $layout['headers'][$index] }}
@if(in_array($field,['debit','credit']))<select name="{{ $field }}" required><option value="">Pilih akun</option>@foreach($accounts as $a)<option value="{{ $a->code }}" @selected($value==$a->code)>{{ $a->code }} · {{ $a->name }}</option>@endforeach</select>
@else<input name="{{ $field }}" type="{{ in_array($field,['date','due','month','paid','chat','start'])?'date':(in_array($field,['amount','value','cash','cost','spend','bank','promo'])?'number':'text') }}" step="any" value="{{ $value }}" @readonly($editing && $field===$key) list="options-{{ $field }}">
@endif</label>
@endif
@endforeach</div>
@foreach(['kind'=>['Baru','Repeat order','Renewal'],'source'=>['Google Ads','Repeat Order','Referral','Organik'],'status'=>['Prospek','Bayar','Batal','Refund'],'duration'=>['1','3','6','12','one time'],'activity'=>['Operasi','Investasi','Pendanaan']] as $field=>$options)<datalist id="options-{{ $field }}">@foreach($options as $opt)<option value="{{ $opt }}">@endforeach</datalist>@endforeach
@if($table==='journal') @include('journal-details') @endif
<button>Simpan</button></form></details>
<form class="bar"><label>Cari data<input name="q" value="{{ request('q') }}" placeholder="Nama, akun, atau referensi"></label><button>Cari</button></form>
<div class="panel scroll"><table><tr><th></th>@foreach($columns as $i=>$f)<th>{{ $layout['headers'][$i] }}</th>@endforeach</tr>@forelse($rows as $row)<tr><td><a href="?{{ http_build_query(['edit'=>$row->$key]) }}">Edit</a></td>@foreach($columns as $f)<td>{{ $row->$f ?? '—' }}</td>@endforeach</tr>@empty<tr><td colspan="8">Belum ada data yang cocok.</td></tr>@endforelse</table>@include('pages')</div>
@endsection
