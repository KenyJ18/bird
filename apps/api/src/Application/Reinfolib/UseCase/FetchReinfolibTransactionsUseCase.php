<?php

declare(strict_types=1);

namespace Application\Reinfolib\UseCase;

use Domain\Reinfolib\Repository\ReinfolibApiClientInterface;
use Domain\Reinfolib\ValueObject\ReinfolibDataType;
use Domain\Reinfolib\ValueObject\ReinfolibPriceCategory;
use Domain\MunicipalityAmount\ValueObject\DataType;
use Domain\MunicipalityAmount\ValueObject\Period;
use Domain\MunicipalityAmount\ValueObject\PriceCategory;
use Illuminate\Support\Facades\Log;

/**
 * Reinfolib取引データ取得ユースケース
 */
final readonly class FetchReinfolibTransactionsUseCase
{
    public function __construct(
        private ReinfolibApiClientInterface $apiClient
    ) {
    }

    /**
     * 指定された条件でReinfolibから取引データを取得
     * 
     * @param Period $period 対象期間
     * @param DataType $dataType データタイプ
     * @param PriceCategory $priceCategory 価格区分
     * @return array 取得した取引データ
     */
    public function execute(
        Period $period,
        DataType $dataType,
        PriceCategory $priceCategory
    ): array {
        Log::info('Reinfolib取引データ取得開始', [
            'period' => $period->value(),
            'dataType' => $dataType->value(),
            'priceCategory' => $priceCategory->value(),
        ]);

        // Domain Value ObjectをReinfolib用に変換
        $reinfolibDataType = ReinfolibDataType::fromDataType($dataType);
        $reinfolibPriceCategory = ReinfolibPriceCategory::fromPriceCategory($priceCategory);

        // すべての対象エリア（1都3県）からデータ取得
        $transactions = $this->apiClient->fetchAllTargetAreaTransactions(
            $period,
            $reinfolibDataType,
            $reinfolibPriceCategory
        );

        Log::info('Reinfolib取引データ取得完了', [
            'totalTransactions' => count($transactions),
        ]);

        return $transactions;
    }
}
