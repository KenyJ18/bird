<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\DB;

return new class extends Migration
{
    /**
     * Run the migrations.
     */
    public function up(): void
    {
        // マテリアライズドビューを作成 (PostgreSQL のみ)
        if (DB::getDriverName() === 'pgsql') {
            DB::statement("
                CREATE MATERIALIZED VIEW muni_amount_snapshot AS
                SELECT muni_code, type, price_category, avg_trade_price, 
                       median_trade_price, txn_count, period
                FROM muni_amount
                WHERE period = (SELECT MAX(period) FROM muni_amount)
            ");
            
            DB::statement("
                CREATE UNIQUE INDEX idx_muni_snapshot_pk
                ON muni_amount_snapshot (muni_code, type, price_category)
            ");
        } else {
            // SQLite などの場合は通常のビューとして作成
            DB::statement("
                CREATE VIEW muni_amount_snapshot AS
                SELECT muni_code, type, price_category, avg_trade_price, 
                       median_trade_price, txn_count, period
                FROM muni_amount
                WHERE period = (SELECT MAX(period) FROM muni_amount)
            ");
        }
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('DROP MATERIALIZED VIEW IF EXISTS muni_amount_snapshot');
        } else {
            DB::statement('DROP VIEW IF EXISTS muni_amount_snapshot');
        }
    }
};
