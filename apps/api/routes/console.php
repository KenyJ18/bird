<?php

use Illuminate\Foundation\Inspiring;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Artisan::command('inspire', function () {
    $this->comment(Inspiring::quote());
})->purpose('Display an inspiring quote');

/*
|--------------------------------------------------------------------------
| 市区町村金額データ 更新検知ポーリング（設計書 bird_design.md §5.0・§9.1）
|--------------------------------------------------------------------------
|
| 週1回、月曜21:00(JST)に app:poll-muni-amounts-update を実行する。
| reinfolib は四半期ごと・不定日更新で固定カレンダーがないため、日次実行はしない。
|
| 新四半期の公開を検知した場合のみ、このコマンド内部で取込バッチ
| （app:update-muni-amounts）が起動する（設計書 §5.1 の実行時間帯
| 「21:00〜翌08:00に完了する想定」に収まるよう、ポーリング自体も21:00開始とする）。
|
| 実行にはサーバー側 crontab に以下1行が必要（Laravelスケジューラー起動用）：
|   * * * * * cd /path/to/apps/api && php artisan schedule:run >> /dev/null 2>&1
*/
Schedule::command('app:poll-muni-amounts-update')
    ->weeklyOn(1, '21:00') // 1 = 月曜
    ->timezone('Asia/Tokyo')
    ->withoutOverlapping(720) // 720分(12時間)。取込ウィンドウ(最大約11時間)より長く確保しつつ多重実行を防止
    ->onFailure(function (): void {
        Log::error('app:poll-muni-amounts-update がスケジュール実行で失敗しました（前回データのまま配信継続）');
    });
