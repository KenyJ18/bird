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
     * 市区町村金額一覧を取得（2段階グレーアウト対応）
     */
    public function execute(GetMunicipalityAmountsRequest $request): GetMunicipalityAmountsResponse
    {
        $municipalityAmounts = $this->repository->findWithGreyoutByTypeAndCategory(
            $request->dataType,
            $request->priceCategory
        );

        return GetMunicipalityAmountsResponse::fromEntities($municipalityAmounts);
    }
}
