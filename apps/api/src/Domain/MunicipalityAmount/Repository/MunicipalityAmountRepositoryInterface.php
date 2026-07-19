<?php

declare(strict_types=1);

namespace Domain\MunicipalityAmount\Repository;

use Domain\MunicipalityAmount\Entity\MunicipalityAmount;
use Domain\MunicipalityAmount\ValueObject\DataType;
use Domain\MunicipalityAmount\ValueObject\PriceCategory;
use Domain\MunicipalityAmount\ValueObject\Period;

/**
 * 市区町村金額リポジトリインターフェース
 * 
 * データアクセスの抽象化を提供
 */
interface MunicipalityAmountRepositoryInterface
{
    /**
     * データ種類と価格区分で市区町村金額を取得
     * 
     * @param DataType $dataType
     * @param PriceCategory $priceCategory
     * @param Period|null $period 指定しない場合は最新四半期
     * @return MunicipalityAmount[]
     */
    public function findByTypeAndCategory(
        DataType $dataType,
        PriceCategory $priceCategory,
        ?Period $period = null
    ): array;

    /**
     * 最新四半期を取得
     */
    public function getLatestPeriod(): ?Period;

    /**
     * 市区町村コード、データ種類、価格区分、期間で1件取得
     */
    public function findOne(
        string $municipalityCode,
        DataType $dataType,
        PriceCategory $priceCategory,
        Period $period
    ): ?MunicipalityAmount;

    /**
     * 保存
     */
    public function save(MunicipalityAmount $municipalityAmount): void;

    /**
     * 複数保存
     * 
     * @param MunicipalityAmount[] $municipalityAmounts
     */
    public function saveMany(array $municipalityAmounts): void;

    /**
     * 期間で削除
     */
    public function deleteByPeriod(Period $period): void;
}
