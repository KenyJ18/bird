<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Application\MuniAmountBatch\Dto\PollDecision;
use Application\MuniAmountBatch\UseCase\PollMuniAmountsUpdateUseCase;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Log;

/**
 * 更新検知ポーリング（設計書 §5.0）
 *
 * 週1回 Cron から起動される想定。1都3県の新四半期データ出現を検知したときだけ
 * データ取込バッチ（app:update-muni-amounts）を起動する。日次実行は行わない。
 */
class PollMuniAmountsUpdateCommand extends Command
{
    protected $signature = 'app:poll-muni-amounts-update';

    protected $description = '新四半期データの公開を週1回ポーリングし、検知時のみ app:update-muni-amounts を起動する';

    public function __construct(
        private readonly PollMuniAmountsUpdateUseCase $pollUseCase,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $this->info('更新検知ポーリング開始');
        Log::info('app:poll-muni-amounts-update 開始');

        $decision = $this->pollUseCase->execute();

        return match ($decision->action) {
            PollDecision::ACTION_NOT_INITIALIZED => $this->handleNotInitialized(),
            PollDecision::ACTION_REIMPORT_GAPS => $this->handleImportTrigger($decision, '穴埋め再取込対象（1都3県が揃っていません）'),
            PollDecision::ACTION_NEW_PERIOD_DETECTED => $this->handleImportTrigger($decision, '新四半期を検知'),
            PollDecision::ACTION_NONE => $this->handleNone($decision),
            default => self::FAILURE,
        };
    }

    private function handleNotInitialized(): int
    {
        $this->warn('snapshot_meta が未初期化です。先に `php artisan app:update-muni-amounts` を手動実行してください。');
        Log::warning('app:poll-muni-amounts-update: snapshot_meta 未初期化のためスキップ');

        // ポーリング自体は正常終了とする（対象データがまだ存在しないだけであり、異常ではない）
        return self::SUCCESS;
    }

    private function handleImportTrigger(PollDecision $decision, string $reason): int
    {
        $this->info("{$reason}: period={$decision->period}");
        $this->info('データ取込バッチ（app:update-muni-amounts）を起動します');

        Log::info('app:poll-muni-amounts-update: 取込ジョブを起動', [
            'action' => $decision->action,
            'period' => $decision->period,
            'missingPrefectures' => $decision->missingPrefectureCodes,
            'probedPrefectures' => $decision->probedPrefectures,
        ]);

        $exitCode = Artisan::call('app:update-muni-amounts', ['--period' => $decision->period]);
        $this->line(Artisan::output());

        if ($exitCode !== self::SUCCESS) {
            $this->error('データ取込バッチが失敗しました。次回ポーリングで再試行されます。');
            Log::error('app:poll-muni-amounts-update: 取込ジョブが失敗', ['period' => $decision->period]);
        }

        return $exitCode;
    }

    private function handleNone(PollDecision $decision): int
    {
        $this->info("次四半期（{$decision->period}）は未公開です。次回ポーリングまで待機します。");
        Log::info('app:poll-muni-amounts-update: 新四半期未公開', ['period' => $decision->period]);

        return self::SUCCESS;
    }
}
