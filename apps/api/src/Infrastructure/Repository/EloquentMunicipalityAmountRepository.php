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
