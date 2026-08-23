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
     * 2段階グレーアウト対応取得。過去1年に1件でも取引がある全市区町村を返す
     *
     * @param DataType $dataType
     * @param PriceCategory $priceCategory
     * @return MunicipalityAmount[]
     */
    public function findWithGreyoutByTypeAndCategory(
        DataType $dataType,
        PriceCategory $priceCategory
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

    /**
     * 履歴保持（設計書 §5.3）：直近N四半期のみ残し、それより古い四半期を削除する
     *
     * @return string[] 削除した period の一覧（削除がなければ空配列）
     */
    public function pruneHistory(int $keepLatestPeriods): array;

    /**
     * 指定した period で muni_amount に1件以上データが存在する都道府県コード（2桁）を返す
     *
     * 更新検知ポーリング（設計書 §5.0 部分公開への保険）が、当該四半期の取込が
     * 1都3県すべて揃っているかを判定するために使う。
     *
     * @return string[] 都道府県コード（'11'/'12'/'13'/'14'）の一覧。重複なし
     */
    public function coveredPrefectureCodes(Period $period): array;
}
