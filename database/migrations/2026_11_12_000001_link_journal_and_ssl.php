<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('journal',function(Blueprint $t){
            $t->string('purpose')->default('general');
            $t->string('order_id')->nullable()->index();
            $t->foreign('order_id')->references('order_id')->on('orders');
            $t->uuid('submission')->nullable()->unique();
        });
        Schema::table('orders',fn(Blueprint $t)=>$t->date('prior_paid')->nullable());
        Schema::create('ssl_updates',function(Blueprint $t){
            $t->id(); $t->string('domain')->index(); $t->date('expiry'); $t->timestamp('recorded_at');
        });
    }
    public function down(): void {
        Schema::dropIfExists('ssl_updates');
        Schema::table('orders',fn(Blueprint $t)=>$t->dropColumn('prior_paid'));
        Schema::table('journal',function(Blueprint $t){
            $t->dropForeign(['order_id']); $t->dropUnique(['submission']); $t->dropIndex(['order_id']);
            $t->dropColumn(['purpose','order_id','submission']);
        });
    }
};
