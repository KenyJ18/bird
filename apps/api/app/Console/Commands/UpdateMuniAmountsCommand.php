<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Application\MuniAmountBatch\UseCase\ImportMuniAmountsUseCase;
use Domain\MunicipalityAmount\ValueObject\Period;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Infrastructure\Models\SnapshotMetaModel;
use Throwable;

/**
 * データ取込バッチ（設計書 §5.1）
 *
 * 四半期に1回、更新検知ポーリング（フェーズ2項目9）から起動される想定。
 * 単独実行も可能（--period 省略時は snapshot_meta の次四半期を対象とする）。
 */
class UpdateMuniAmountsCommand extends Command
{
    protected $signature = 'app:update-muni-amounts
                            {--period= : 取込対象の四半期 (例: 2026-Q2)。省略時は snapshot_meta の次四半期}';

    protected $description = 'Reinfolibから1都3県の取引価格を取込み、市区町村ごとに集計してmuni_amountを更新する';

    public function __construct(
        private readonly ImportMuniAmountsUseCase $importUseCase,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $period = $this->resolveTargetPeriod();

        $this->info("データ取込バッチ開始: {$period->value()}");
        Log::info('app:update-muni-amounts 開始', ['period' => $period->value()]);

        DB::beginTransaction();

        try {
            $result = $this->importUseCase->execute($period);
            DB::commit();
        } catch (Throwable $e) {
            DB::rollBack();

            Log::error('app:update-muni-amounts 失敗（ロールバック）', [
                'period' => $period->value(),
                'error' => $e->getMessage(),
            ]);
            $this->error("取込に失敗しました。前回のデータのまま配信を継続します: {$e->getMessage()}");

            return self::FAILURE;
        }

        $this->info("取込完了: {$result->savedRowCount}件保存");
        if ($result->deletedPeriods !== []) {
            $this->info('履歴クリーンアップ: ' . implode(', ', $result->deletedPeriods) . ' を削除');
        }

        Log::info('app:update-muni-amounts 完了', [
            'period' => $result->period,
            'savedRowCount' => $result->savedRowCount,
            'deletedPeriods' => $result->deletedPeriods,
        ]);

        return self::SUCCESS;
    }

    private function resolveTargetPeriod(): Period
    {
        $option = $this->option('period');
        if ($option !== null) {
            return new Period($option);
        }

        $meta = SnapshotMetaModel::find(1);
        if ($meta === null) {
            // 初回実行：snapshot_meta が存在しないため現在の四半期を対象にする
            return Period::current();
        }

        return (new Period($meta->period))->next();
    }
}
