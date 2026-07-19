<?php

declare(strict_types=1);

namespace Application\MunicipalityAmount\Dto;

use Domain\MunicipalityAmount\ValueObject\DataType;
use Domain\MunicipalityAmount\ValueObject\PriceCategory;
use Domain\MunicipalityAmount\ValueObject\Period;

/**
 * 市区町村金額取得リクエストDTO
 */
final readonly class GetMunicipalityAmountsRequest
{
    public function __construct(
        public DataType $dataType,
        public PriceCategory $priceCategory,
        public ?Period $period = null
    ) {
    }

    /**
     * HTTPリクエストパラメータから生成
     */
    public static function fromArray(array $params): self
    {
        return new self(
            dataType: new DataType($params['type'] ?? DataType::default()->value()),
            priceCategory: new PriceCategory($params['priceCategory'] ?? PriceCategory::default()->value()),
            period: isset($params['period']) ? new Period($params['period']) : null
        );
    }
}
