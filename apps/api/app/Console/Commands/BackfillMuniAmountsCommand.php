<?php

declare(strict_types=1);

namespace App\Console\Commands;

use Application\MuniAmountBatch\UseCase\ImportMuniAmountsUseCase;
use Domain\MunicipalityAmount\ValueObject\Period;
use Domain\Reinfolib\Repository\ReinfolibApiClientInterface;
use Domain\Reinfolib\ValueObject\PrefectureCode;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * 過去データの一括バックフィル（設計書 §5.3、2026-09-22追加）
 *
 * 履歴保持を直近4四半期（1年）から直近40四半期（10年）に拡張したことに伴い、
 * 初回のみ過去分をまとめて取り込むための一回限りのコマンド。
 * 通常運用（週次ポーリング）では使用しない。
 *
 * 四半期は古い方から新しい方の順に取り込む（新しい方から遡ると、最後に実行した
 * 四半期が最も古いものになり ImportMuniAmountsUseCase::execute() 内の
 * snapshot_meta 更新が誤った期間で固定されてしまうため）。
 *
 * 1四半期の取込失敗が他の四半期の取込結果を巻き込んでロールバックしないよう、
 * 四半期ごとに個別のトランザクションで実行し、失敗した四半期はスキップして続行する。
 */
class BackfillMuniAmountsCommand extends Command
{
    protected $signature = 'app:backfill-muni-amounts
                            {--periods=40 : 取り込む四半期数（デフォルト40＝10年）}
                            {--end= : 終端四半期 (例: 2026-Q2)。省略時は直近の公開済み四半期を自動検出}
                            {--probe-prefecture=13 : 自動検出時にプローブ対象とする都道府県コード（既定: 13=東京都）}';

    protected $description = '過去複数四半期分のデータをまとめて取り込む一回限りのバックフィル処理';

    public function __construct(
        private readonly ImportMuniAmountsUseCase $importUseCase,
        private readonly ReinfolibApiClientInterface $apiClient,
    ) {
        parent::__construct();
    }

    public function handle(): int
    {
        $periodCount = (int) $this->option('periods');
        if ($periodCount < 1) {
            $this->error('--periods は1以上を指定してください');

            return self::FAILURE;
        }

        $endPeriod = $this->resolveEndPeriod();
        if ($endPeriod === null) {
            $this->error('直近の公開済み四半期を自動検出できませんでした。--end で明示的に指定してください');

            return self::FAILURE;
        }

        $startPeriod = $endPeriod;
        for ($i = 1; $i < $periodCount; $i++) {
            $startPeriod = $startPeriod->previous();
        }

        $periods = [];
        $cursor = $startPeriod;
        while (true) {
            $periods[] = $cursor;
            if ($cursor->equals($endPeriod)) {
                break;
            }
            $cursor = $cursor->next();
        }

        $this->info(sprintf(
            'バックフィル対象: %s 〜 %s（%d四半期）',
            $startPeriod->value(),
            $endPeriod->value(),
            count($periods)
        ));

        $succeeded = 0;
        $failed = [];

        foreach ($periods as $index => $period) {
            $this->info(sprintf('[%d/%d] %s 取込中...', $index + 1, count($periods), $period->value()));

            DB::beginTransaction();
            try {
                $result = $this->importUseCase->execute($period);
                DB::commit();
                $this->info(sprintf('  → 完了: %d件保存', $result->savedRowCount));
                $succeeded++;
            } catch (Throwable $e) {
                DB::rollBack();
                $this->error(sprintf('  → 失敗: %s', $e->getMessage()));
                Log::error('app:backfill-muni-amounts 四半期取込失敗（スキップして続行）', [
                    'period' => $period->value(),
                    'error' => $e->getMessage(),
                ]);
                $failed[] = $period->value();
            }
        }

        $this->newLine();
        $this->info(sprintf('バックフィル完了: 成功 %d件 / 失敗 %d件', $succeeded, count($failed)));
        if ($failed !== []) {
            $this->warn('失敗した四半期: ' . implode(', ', $failed));
        }

        return $failed === [] ? self::SUCCESS : self::FAILURE;
    }

    /**
     * 直近の公開済み四半期を自動検出する。
     * 現在の暦年四半期から遡り、最初に200（データあり）を返した四半期を終端とする。
     */
    private function resolveEndPeriod(): ?Period
    {
        $option = $this->option('end');
        if ($option !== null) {
            return new Period($option);
        }

        $prefecture = new PrefectureCode((string) $this->option('probe-prefecture'));
        $candidate = Period::current();

        // 公開ラグを考慮し、最大8四半期（2年）遡って探索する
        for ($i = 0; $i < 8; $i++) {
            if ($this->apiClient->probeAreaHasData($prefecture, $candidate)) {
                return $candidate;
            }
            $candidate = $candidate->previous();
        }

        return null;
    }
}
