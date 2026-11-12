@extends('base')
@section('title','Buku besar · '.$account->name)
@section('content')
<p>{{ $from }} – {{ $to }}</p><div class="panel scroll"><table><tr>@foreach(['Tanggal','Uraian','Debit','Kredit','Saldo'] as $c)<th>{{ $c }}</th>@endforeach</tr><tr><td colspan="4">Saldo awal</td><td>{{ number_format($running,0,',','.') }}</td></tr>@foreach($rows as $row)
@php($d=$row->debit===$account->code?$row->amount:0)
@php($c=$row->credit===$account->code?$row->amount:0)
@php($running+=$d-$c)
<tr><td>{{ $row->date }}</td><td>{{ $row->description }}</td>@foreach([$d,$c,$running] as $v)<td>{{ number_format($v,0,',','.') }}</td>@endforeach</tr>@endforeach</table></div>
@endsection
