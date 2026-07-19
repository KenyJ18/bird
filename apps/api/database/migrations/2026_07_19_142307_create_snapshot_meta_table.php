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
        Schema::create('snapshot_meta', function (Blueprint $table) {
            $table->smallInteger('id')->primary()->default(1);
            $table->string('period', 20);
            $table->timestampTz('snapshot_at');
        });
        
        // 初期レコードを挿入
        DB::table('snapshot_meta')->insert([
            'id' => 1,
            'period' => '2026-Q2',
            'snapshot_at' => now(),
        ]);
    }

    /**
     * Reverse the migrations.
     */
    public function down(): void
    {
        Schema::dropIfExists('snapshot_meta');
    }
};
