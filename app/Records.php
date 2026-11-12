<?php
namespace App;

use Carbon\CarbonImmutable;
use Illuminate\Support\Facades\{DB,Validator};

class Records
{
    public static function layouts(): array {
        return json_decode(file_get_contents(database_path('layout.json')),true);
    }
    public static function validate(string $table,array $data): array {
        $rules = match($table) {
            'accounts'=>['code'=>'required|string','name'=>'required|string','group_name'=>'required|string',
                'opening_debit'=>'nullable|numeric|min:0','opening_credit'=>'nullable|numeric|min:0'],
            'journal'=>['date'=>'required|date_format:Y-m-d','debit'=>'required|exists:accounts,code',
                'credit'=>'required|exists:accounts,code|different:debit','amount'=>'required|numeric|gt:0','due'=>'nullable|date_format:Y-m-d'],
            'orders'=>['order_id'=>'required|string|max:255','customer'=>'required|string|max:255','domain'=>'required|string|max:255',
                'start'=>'nullable|date_format:Y-m-d','paid'=>'nullable|date_format:Y-m-d','chat'=>'nullable|date_format:Y-m-d',
                'duration'=>['nullable','regex:/^([1-9][0-9]{0,2}|one time)$/i'],
                'value'=>'nullable|numeric|min:0','cash'=>'nullable|numeric|min:0','cost'=>'nullable|numeric|min:0'],
            'ads'=>['month'=>['required','date_format:Y-m-d','regex:/-01$/'],'spend'=>'required|numeric|min:0',
                'bank'=>'nullable|numeric|min:0','promo'=>'nullable|numeric|min:0'],
        };
        foreach(self::layouts()[$table]['fields'] as $f) if($f!=='_') $rules[$f] ??= 'nullable|string|max:8000';
        $data=Validator::make($data,$rules)->validate();
        if($table==='orders') $data['expires']=!empty($data['start']) && is_numeric($data['duration']??null)
            ? CarbonImmutable::parse($data['start'])->addMonthsNoOverflow((int)$data['duration'])->toDateString() : null;
        return $data;
    }
}
