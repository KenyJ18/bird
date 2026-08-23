<?php

declare(strict_types=1);

namespace Application\MuniAmountBatch\UseCase;

use Application\MuniAmountBatch\Dto\ImportMuniAmountsResult;
use Application\Reinfolib\UseCase\FetchReinfolibTransactionsUseCase;
use DateTimeImmutable;
use Domain\MunicipalityAmount\Entity\MunicipalityAmount;
use Domain\MunicipalityAmount\Repository\MunicipalityAmountRepositoryInterface;
use Domain\MunicipalityAmount\Service\TradePriceAggregator;
use Domain\MunicipalityAmount\ValueObject\DataType;
use Domain\MunicipalityAmount\ValueObject\MunicipalityCode;
use Domain\MunicipalityAmount\ValueObject\Period;
use Domain\MunicipalityAmount\ValueObject\PriceCategory;
use Domain\Reinfolib\Entity\ReinfolibTransaction;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Infrastructure\Models\SnapshotMetaModel;

/**
 * データ取込バッチ ユースケース（設計書 §5.1）
 *
 * 指定四半期について、対象3データ種類 × 2価格区分の組み合わせごとに
 * Reinfolib から1都3県の取引データを取得し、市区町村単位で平均・中央値・件数を
 * 集計して muni_amount に保存する。あわせて履歴保持（直近4四半期）と
 * スナップショット基準時刻（snapshot_meta）の更新を行う。
 *
 * 保存件数に関わらず途中で例外が発生した場合は呼び出し元（コマンド）で
 * トランザクションごとロールバックし、前回の配信データを維持する（可用性優先）。
 */
final readonly class ImportMuniAmountsUseCase
{
    // 対象データ種類（案X＝3種のみ。設計書 §5.1 ステップ4・§7.2補足）
    private const TARGET_DATA_TYPES = [
        DataType::RESIDENTIAL_LAND,
        DataType::RESIDENTIAL_LAND_AND_BUILDING,
        DataType::USED_CONDOMINIUM,
    ];

    private const TARGET_PRICE_CATEGORIES = [
        PriceCategory::TRANSACTION_PRICE,
        PriceCategory::CONTRACT_PRICE,
    ];

    // 保持する四半期数（設計書 §5.3：1年 = 直近4四半期）
    private const KEEP_LATEST_PERIODS = 4;

    // レート配慮：type × priceCategory の組ごとに間隔を空ける（設計書 §5.1）
    private const INTER_COMBINATION_WAIT_MICROSECONDS = 500_000;

    public function __construct(
        private FetchReinfolibTransactionsUseCase $fetchUseCase,
        private MunicipalityAmountRepositoryInterface $repository,
    ) {
    }

    public function execute(Period $period): ImportMuniAmountsResult
    {
        Log::info('データ取込バッチ開始', ['period' => $period->value()]);

        $savedRowCount = 0;
        $combinations = $this->targetCombinations();

        foreach ($combinations as $index => [$dataType, $priceCategory]) {
            $transactions = $this->fetchUseCase->execute($period, $dataType, $priceCategory);

            $amounts = $this->aggregateByMunicipality($transactions, $dataType, $priceCategory, $period);
            $this->repository->saveMany($amounts);
            $savedRowCount += count($amounts);

            Log::info('取込・集計完了', [
                'period' => $period->value(),
                'type' => $dataType->value(),
                'priceCategory' => $priceCategory->value(),
                'municipalityCount' => count($amounts),
                'transactionCount' => count($transactions),
            ]);

            $isLast = $index === count($combinations) - 1;
            if (!$isLast) {
                usleep(self::INTER_COMBINATION_WAIT_MICROSECONDS);
            }
        }

        $deletedPeriods = $this->repository->pruneHistory(self::KEEP_LATEST_PERIODS);
        if ($deletedPeriods !== []) {
            Log::info('履歴クリーンアップ完了', ['deletedPeriods' => $deletedPeriods]);
        }

        $this->fixSnapshot($period);

        Log::info('データ取込バッチ完了', [
            'period' => $period->value(),
            'savedRowCount' => $savedRowCount,
        ]);

        return new ImportMuniAmountsResult($period->value(), $savedRowCount, $deletedPeriods);
    }

    /**
     * @return array<int, array{0: DataType, 1: PriceCategory}>
     */
    private function targetCombinations(): array
    {
        $combinations = [];
        foreach (self::TARGET_DATA_TYPES as $typeValue) {
            foreach (self::TARGET_PRICE_CATEGORIES as $categoryValue) {
                $combinations[] = [new DataType($typeValue), new PriceCategory($categoryValue)];
            }
        }

        return $combinations;
    }

    /**
     * @param ReinfolibTransaction[] $transactions
     * @return MunicipalityAmount[]
     */
    private function aggregateByMunicipality(
        array $transactions,
        DataType $dataType,
        PriceCategory $priceCategory,
        Period $period
    ): array {
        /** @var array<string, int[]> $pricesByMunicipality */
        $pricesByMunicipality = [];

        foreach ($transactions as $transaction) {
            $municipalityCode = new MunicipalityCode($transaction->municipalityCode());

            // 島嶼部除外（設計書 §2.4）：境界データ・集計の全てに適用
            if ($municipalityCode->isTokyoIsland()) {
                continue;
            }

            // 対象都県（1都3県）以外が紛れ込んでいないか念のため確認
            if (!$municipalityCode->isInTargetArea()) {
                continue;
            }

            $pricesByMunicipality[$municipalityCode->value()][] = $transaction->tradePrice();
        }

        $now = new DateTimeImmutable();
        $amounts = [];

        foreach ($pricesByMunicipality as $muniCode => $prices) {
            $stats = TradePriceAggregator::aggregate($prices);

            $amounts[] = new MunicipalityAmount(
                // PHPは数字のみの文字列（"13101"等）を配列キーにするとintへ暗黙変換するため、
                // MunicipalityCode（5桁文字列必須）に渡す前に明示的にstrへ戻す
                municipalityCode: new MunicipalityCode(str_pad((string) $muniCode, 5, '0', STR_PAD_LEFT)),
                dataType: $dataType,
                priceCategory: $priceCategory,
                averageTradePrice: $stats['avg'],
                medianTradePrice: $stats['median'],
                transactionCount: $stats['count'],
                period: $period,
                updatedAt: $now,
            );
        }

        return $amounts;
    }

    /**
     * スナップショット基準時刻の固定（設計書 §6.1）
     *
     * muni_amount_snapshot は MySQL/SQLite では通常のVIEWのため常に最新状態を参照する
     * （REFRESH不要）。PostgreSQL利用時のみマテリアライズドビューのREFRESHが必要。
     */
    private function fixSnapshot(Period $period): void
    {
        if (DB::getDriverName() === 'pgsql') {
            DB::statement('REFRESH MATERIALIZED VIEW CONCURRENTLY muni_amount_snapshot');
        }

        SnapshotMetaModel::updateOrCreate(
            ['id' => 1],
            ['period' => $period->value(), 'snapshot_at' => new DateTimeImmutable()],
        );
    }
}
