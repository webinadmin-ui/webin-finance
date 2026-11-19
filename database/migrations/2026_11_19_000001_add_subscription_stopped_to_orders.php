<?php
use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration {
    public function up(): void {
        Schema::table('orders', function(Blueprint $t){
            $t->date('stopped_at')->nullable()->index();
            $t->text('stopped_reason')->nullable();
        });
    }
    public function down(): void {
        Schema::table('orders', function(Blueprint $t){
            $t->dropIndex(['stopped_at']);
            $t->dropColumn(['stopped_at', 'stopped_reason']);
        });
    }
};

