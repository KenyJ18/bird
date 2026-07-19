<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Database\Schema\Blueprint;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // UNLOGGED テーブルは PostgreSQL 固有の機能のため、
        // 通常のテーブルとして作成し、後で手動で変更する
        Schema::create('muni_txn_staging', function (Blueprint $table) {
            $table->char('muni_code', 5)->nullable();
            $table->string('type', 50)->nullable();
            $table->string('price_category', 50)->nullable();
            $table->bigInteger('trade_price')->nullable();
            $table->string('period', 20)->nullable();
        });
        
        // PostgreSQL の場合、UNLOGGED に変更
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('ALTER TABLE muni_txn_staging SET UNLOGGED');
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('muni_txn_staging');
    }
};
