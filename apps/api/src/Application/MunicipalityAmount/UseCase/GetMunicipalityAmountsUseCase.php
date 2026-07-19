<?php

declare(strict_types=1);

namespace Application\MunicipalityAmount\UseCase;

use Application\MunicipalityAmount\Dto\GetMunicipalityAmountsRequest;
use Application\MunicipalityAmount\Dto\GetMunicipalityAmountsResponse;
use Domain\MunicipalityAmount\Repository\MunicipalityAmountRepositoryInterface;

/**
 * 市区町村金額一覧取得ユースケース
 */
final readonly class GetMunicipalityAmountsUseCase
{
    public function __construct(
        private MunicipalityAmountRepositoryInterface $repository
    ) {
    }

    /**
     * 市区町村金額一覧を取得
     */
    public function execute(GetMunicipalityAmountsRequest $request): GetMunicipalityAmountsResponse
    {
        // リクエストから期間を取得、指定がない場合は最新を取得
        $period = $request->period ?? $this->repository->getLatestPeriod();
        
        if ($period === null) {
            // データが存在しない場合は空配列を返す
            return new GetMunicipalityAmountsResponse([], 'N/A');
        }

        // リポジトリから市区町村金額を取得
        $municipalityAmounts = $this->repository->findByTypeAndCategory(
            $request->dataType,
            $request->priceCategory,
            $period
        );

        // レスポンスDTOに変換
        return GetMunicipalityAmountsResponse::fromEntities(
            $municipalityAmounts,
            $period->value()
        );
    }
}
