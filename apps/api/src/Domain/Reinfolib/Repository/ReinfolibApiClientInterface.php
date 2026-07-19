<?php

declare(strict_types=1);

namespace Domain\Reinfolib\Repository;

use Domain\Reinfolib\Entity\ReinfolibTransaction;
use Domain\Reinfolib\ValueObject\PrefectureCode;
use Domain\Reinfolib\ValueObject\ReinfolibDataType;
use Domain\Reinfolib\ValueObject\ReinfolibPriceCategory;
use Domain\MunicipalityAmount\ValueObject\Period;

/**
 * Reinfolib APIクライアントインターフェース
 */
interface ReinfolibApiClientInterface
{
    /**
     * 指定された条件で不動産取引データを取得
     * 
     * @param PrefectureCode $prefectureCode 都道府県コード
     * @param Period $period 取得対象期間
     * @param ReinfolibDataType $dataType データタイプ
     * @param ReinfolibPriceCategory $priceCategory 価格区分
     * @return ReinfolibTransaction[] 取引データ配列
     * @throws \RuntimeException API呼び出しに失敗した場合
     */
    public function fetchTransactions(
        PrefectureCode $prefectureCode,
        Period $period,
        ReinfolibDataType $dataType,
        ReinfolibPriceCategory $priceCategory
    ): array;

    /**
     * すべての対象エリア（1都3県）のデータを取得
     * 
     * @param Period $period 取得対象期間
     * @param ReinfolibDataType $dataType データタイプ
     * @param ReinfolibPriceCategory $priceCategory 価格区分
     * @return ReinfolibTransaction[] 取引データ配列
     */
    public function fetchAllTargetAreaTransactions(
        Period $period,
        ReinfolibDataType $dataType,
        ReinfolibPriceCategory $priceCategory
    ): array;
}
