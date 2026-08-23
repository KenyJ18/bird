<?php

declare(strict_types=1);

namespace Infrastructure\Repository;

use Domain\MunicipalityAmount\Entity\MunicipalityAmount;
use Domain\MunicipalityAmount\Repository\MunicipalityAmountRepositoryInterface;
use Domain\MunicipalityAmount\ValueObject\DataType;
use Domain\MunicipalityAmount\ValueObject\MunicipalityCode;
use Domain\MunicipalityAmount\ValueObject\Period;
use Domain\MunicipalityAmount\ValueObject\PriceCategory;
use Infrastructure\Models\MunicipalityAmountModel;

class EloquentMunicipalityAmountRepository implements MunicipalityAmountRepositoryInterface
{
    public function findByTypeAndCategory(
        DataType $dataType,
        PriceCategory $priceCategory,
        ?Period $period = null
    ): array {
        $query = MunicipalityAmountModel::query()
            ->where('type', $dataType->value())
            ->where('price_category', $priceCategory->value());

        if ($period !== null) {
            $query->where('period', $period->value());
        } else {
            // 最新四半期を取得
            $latestPeriod = $this->getLatestPeriod();
            if ($latestPeriod === null) {
                return [];
            }
            $query->where('period', $latestPeriod->value());
        }

        $models = $query->get();

        return $models->map(function ($model) {
            return $this->toDomainEntity($model);
        })->all();
    }

    public function getLatestPeriod(): ?Period
    {
        $latest = MunicipalityAmountModel::query()
            ->selectRaw('MAX(period) as latest_period')
            ->value('latest_period');

        return $latest ? new Period($latest) : null;
    }

    public function findOne(
        string $municipalityCode,
        DataType $dataType,
        PriceCategory $priceCategory,
        Period $period
    ): ?MunicipalityAmount {
        $model = MunicipalityAmountModel::query()
            ->where('muni_code', $municipalityCode)
            ->where('type', $dataType->value())
            ->where('price_category', $priceCategory->value())
            ->where('period', $period->value())
            ->first();

        return $model ? $this->toDomainEntity($model) : null;
    }

    public function save(MunicipalityAmount $municipalityAmount): void
    {
        MunicipalityAmountModel::updateOrCreate(
            [
                'muni_code' => $municipalityAmount->municipalityCode()->value(),
                'type' => $municipalityAmount->dataType()->value(),
                'price_category' => $municipalityAmount->priceCategory()->value(),
                'period' => $municipalityAmount->period()->value(),
            ],
            [
                'avg_trade_price' => $municipalityAmount->averageTradePrice(),
                'median_trade_price' => $municipalityAmount->medianTradePrice(),
                'txn_count' => $municipalityAmount->transactionCount(),
                'updated_at' => $municipalityAmount->updatedAt(),
            ]
        );
    }

    public function saveMany(array $municipalityAmounts): void
    {
        foreach ($municipalityAmounts as $amount) {
            $this->save($amount);
        }
    }

    public function deleteByPeriod(Period $period): void
    {
        MunicipalityAmountModel::query()
            ->where('period', $period->value())
            ->delete();
    }

    public function pruneHistory(int $keepLatestPeriods): array
    {
        // period は 'YYYY-QN'（N は1桁）形式のため、文字列の降順ソートが時系列の降順と一致する
        $periodsToKeep = MunicipalityAmountModel::query()
            ->select('period')
            ->distinct()
            ->orderByDesc('period')
            ->limit($keepLatestPeriods)
            ->pluck('period')
            ->all();

        $query = MunicipalityAmountModel::query();
        if ($periodsToKeep !== []) {
            $query->whereNotIn('period', $periodsToKeep);
        }

        $deletedPeriods = (clone $query)
            ->select('period')
            ->distinct()
            ->pluck('period')
            ->all();

        if ($deletedPeriods !== []) {
            $query->delete();
        }

        return $deletedPeriods;
    }

    public function findWithGreyoutByTypeAndCategory(
        DataType $dataType,
        PriceCategory $priceCategory
    ): array {
        $type     = $dataType->value();
        $category = $priceCategory->value();

        $rows = \Illuminate\Support\Facades\DB::select("
            WITH latest AS (SELECT MAX(period) AS p FROM muni_amount)
            SELECT
                h.muni_code,
                la.avg_trade_price,
                la.median_trade_price,
                COALESCE(la.txn_count, 0)                          AS latest_count,
                COALESCE(la.period, (SELECT p FROM latest))        AS period,
                COALESCE(la.updated_at, CURRENT_TIMESTAMP)         AS updated_at
            FROM (
                SELECT DISTINCT muni_code
                FROM muni_amount
                WHERE type = ? AND price_category = ?
            ) h
            LEFT JOIN muni_amount la
                ON  la.muni_code      = h.muni_code
                AND la.type           = ?
                AND la.price_category = ?
                AND la.period         = (SELECT p FROM latest)
        ", [$type, $category, $type, $category]);

        return array_map(function (object $row) use ($dataType, $priceCategory): MunicipalityAmount {
            return new MunicipalityAmount(
                municipalityCode: new MunicipalityCode($row->muni_code),
                dataType:         $dataType,
                priceCategory:    $priceCategory,
                averageTradePrice:  $row->avg_trade_price !== null ? (int) $row->avg_trade_price : null,
                medianTradePrice:   $row->median_trade_price !== null ? (int) $row->median_trade_price : null,
                transactionCount: (int) $row->latest_count,
                period:           new Period($row->period),
                updatedAt:        new \DateTimeImmutable($row->updated_at)
            );
        }, $rows);
    }

    /**
     * EloquentモデルをDomainエンティティに変換
     */
    private function toDomainEntity(MunicipalityAmountModel $model): MunicipalityAmount
    {
        return new MunicipalityAmount(
            municipalityCode: new MunicipalityCode($model->muni_code),
            dataType: new DataType($model->type),
            priceCategory: new PriceCategory($model->price_category),
            averageTradePrice: $model->avg_trade_price,
            medianTradePrice: $model->median_trade_price,
            transactionCount: $model->txn_count,
            period: new Period($model->period),
            updatedAt: $model->updated_at
        );
    }
}
