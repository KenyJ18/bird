<?php

declare(strict_types=1);

namespace Domain\Reinfolib\Entity;

use Domain\Reinfolib\ValueObject\PrefectureCode;
use Domain\Reinfolib\ValueObject\ReinfolibDataType;
use Domain\Reinfolib\ValueObject\ReinfolibPriceCategory;
use Domain\MunicipalityAmount\ValueObject\Period;

/**
 * Reinfolib取引データエンティティ
 */
final class ReinfolibTransaction
{
    public function __construct(
        private string $municipalityCode,
        private ReinfolibDataType $dataType,
        private ReinfolibPriceCategory $priceCategory,
        private int $tradePrice,
        private Period $period
    ) {
    }

    // Getters

    public function municipalityCode(): string
    {
        return $this->municipalityCode;
    }

    public function dataType(): ReinfolibDataType
    {
        return $this->dataType;
    }

    public function priceCategory(): ReinfolibPriceCategory
    {
        return $this->priceCategory;
    }

    public function tradePrice(): int
    {
        return $this->tradePrice;
    }

    public function period(): Period
    {
        return $this->period;
    }

    /**
     * 市区町村コードの先頭2桁（都道府県コード）を取得
     */
    public function prefectureCode(): PrefectureCode
    {
        return new PrefectureCode(substr($this->municipalityCode, 0, 2));
    }
}
