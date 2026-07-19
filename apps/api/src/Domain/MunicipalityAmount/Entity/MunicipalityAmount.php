<?php

declare(strict_types=1);

namespace Domain\MunicipalityAmount\Entity;

use Domain\MunicipalityAmount\ValueObject\MunicipalityCode;
use Domain\MunicipalityAmount\ValueObject\DataType;
use Domain\MunicipalityAmount\ValueObject\PriceCategory;
use Domain\MunicipalityAmount\ValueObject\Period;
use DateTimeImmutable;

/**
 * 市区町村金額エンティティ
 * 
 * 市区町村ごとの不動産取引価格の統計情報を表現する
 */
final class MunicipalityAmount
{
    private MunicipalityCode $municipalityCode;
    private DataType $dataType;
    private PriceCategory $priceCategory;
    private ?int $averageTradePrice;
    private ?int $medianTradePrice;
    private int $transactionCount;
    private Period $period;
    private DateTimeImmutable $updatedAt;

    public function __construct(
        MunicipalityCode $municipalityCode,
        DataType $dataType,
        PriceCategory $priceCategory,
        ?int $averageTradePrice,
        ?int $medianTradePrice,
        int $transactionCount,
        Period $period,
        DateTimeImmutable $updatedAt
    ) {
        $this->municipalityCode = $municipalityCode;
        $this->dataType = $dataType;
        $this->priceCategory = $priceCategory;
        $this->averageTradePrice = $averageTradePrice;
        $this->medianTradePrice = $medianTradePrice;
        $this->transactionCount = $transactionCount;
        $this->period = $period;
        $this->updatedAt = $updatedAt;
    }

    // Getters

    public function municipalityCode(): MunicipalityCode
    {
        return $this->municipalityCode;
    }

    public function dataType(): DataType
    {
        return $this->dataType;
    }

    public function priceCategory(): PriceCategory
    {
        return $this->priceCategory;
    }

    public function averageTradePrice(): ?int
    {
        return $this->averageTradePrice;
    }

    public function medianTradePrice(): ?int
    {
        return $this->medianTradePrice;
    }

    public function transactionCount(): int
    {
        return $this->transactionCount;
    }

    public function period(): Period
    {
        return $this->period;
    }

    public function updatedAt(): DateTimeImmutable
    {
        return $this->updatedAt;
    }

    /**
     * 取引データが存在するかどうか
     */
    public function hasTransactions(): bool
    {
        return $this->transactionCount > 0;
    }

    /**
     * 統計値が有効かどうか
     */
    public function hasValidStatistics(): bool
    {
        return $this->hasTransactions() 
            && ($this->averageTradePrice !== null || $this->medianTradePrice !== null);
    }

    /**
     * 指定された予算以下かどうかを判定
     */
    public function isWithinBudget(int $budget, string $statisticType = 'average'): bool
    {
        if (!$this->hasValidStatistics()) {
            return false;
        }

        $price = $statisticType === 'median' 
            ? $this->medianTradePrice 
            : $this->averageTradePrice;

        return $price !== null && $price <= $budget;
    }

    /**
     * 予算との差額を計算
     */
    public function budgetDifference(int $budget, string $statisticType = 'average'): ?int
    {
        if (!$this->hasValidStatistics()) {
            return null;
        }

        $price = $statisticType === 'median' 
            ? $this->medianTradePrice 
            : $this->averageTradePrice;

        return $price !== null ? $price - $budget : null;
    }
}
