<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        Schema::create('muni_amount', function (Blueprint $table) {
            $table->char('muni_code', 5);
            $table->string('type', 50);
            $table->string('price_category', 50);
            $table->bigInteger('avg_trade_price')->nullable();
            $table->bigInteger('median_trade_price')->nullable();
            $table->integer('txn_count');
            $table->string('period', 20);
            $table->timestampTz('updated_at')->useCurrent();
            
            // 複合主キー
            $table->primary(['muni_code', 'type', 'price_category', 'period']);
            
            // インデックス
            $table->index(['type', 'price_category', 'period'], 'idx_muni_amount_lookup');
        });
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('muni_amount');
    }
};
