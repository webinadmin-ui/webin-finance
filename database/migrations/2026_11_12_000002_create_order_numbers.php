<?php
use App\OrderNumbers;
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};

return new class extends Migration {
    public function up(): void {
        Schema::create('order_numbers', function(Blueprint $table){
            $table->unsignedInteger('id')->primary();
            $table->unsignedBigInteger('last_number');
        });
        DB::table('order_numbers')->insert(['id'=>1, 'last_number'=>0]);
        DB::table('orders')->select('order_id')->chunkById(500, function($rows){
            foreach($rows as $order) OrderNumbers::observe($order->order_id);
        }, 'order_id');
    }
    public function down(): void {
        Schema::dropIfExists('order_numbers');
    }
};
