<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\{DB,Schema};
return new class extends Migration {
    public function up(): void {
        Schema::create('admins',function(Blueprint $t){$t->id(); $t->string('password');});
        Schema::create('accounts',function(Blueprint $t){
            $t->string('code')->primary(); $t->string('name'); $t->string('group_name');
            $t->decimal('opening_debit',20,4)->default(0); $t->decimal('opening_credit',20,4)->default(0);
        });
        DB::table('accounts')->insert(json_decode(file_get_contents(database_path('chart.json')),true));
        Schema::create('journal',function(Blueprint $t){
            $t->id(); $t->date('date')->index(); $t->string('debit'); $t->string('credit');
            foreach(['debit','credit'] as $col) $t->foreign($col)->references('code')->on('accounts');
            $t->decimal('amount',20,4); $t->date('due')->nullable(); $t->string('import_key')->nullable()->unique();
            foreach(['proof','description','party','invoice','activity','notes'] as $col) $t->text($col)->nullable();
        });
        Schema::create('orders',function(Blueprint $t){
            $t->string('order_id')->primary(); $t->string('customer')->index(); $t->string('domain');
            foreach(['source','kind','status','package','duration','notes','hours'] as $col) $t->text($col)->nullable();
            foreach(['chat','paid','start','expires'] as $col) $t->date($col)->nullable()->index();
            foreach(['value','cash','cost'] as $col) $t->decimal($col,20,4)->nullable();
            $t->string('renews')->nullable()->unique(); $t->foreign('renews')->references('order_id')->on('orders');
        });
        Schema::create('ads',function(Blueprint $t){
            $t->date('month')->primary();
            foreach(['spend','promo','bank'] as $col) $t->decimal($col,20,4)->nullable();
            $t->text('notes')->nullable();
        });
    }
    public function down(): void {foreach(['ads','orders','journal','accounts','admins'] as $table) Schema::dropIfExists($table);}
};
