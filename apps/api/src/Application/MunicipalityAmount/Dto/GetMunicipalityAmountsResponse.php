<?php

declare(strict_types=1);

namespace Application\MunicipalityAmount\Dto;

use Domain\MunicipalityAmount\Entity\MunicipalityAmount;

/**
 * 市区町村金額取得レスポンスDTO
 */
final readonly class GetMunicipalityAmountsResponse
{
    /**
     * @param MunicipalityAmountData[] $data
     */
    public function __construct(
        public array $data,
        public string $period
    ) {
    }

    /**
     * Domainエンティティ配列から生成
     * 
     * @param MunicipalityAmount[] $municipalityAmounts
     */
    public static function fromEntities(array $municipalityAmounts, string $period): self
    {
        $data = array_map(
            fn(MunicipalityAmount $entity) => MunicipalityAmountData::fromEntity($entity),
            $municipalityAmounts
        );

        return new self($data, $period);
    }

    /**
     * JSON配列に変換
     */
    public function toArray(): array
    {
        return [
            'data' => array_map(fn($item) => $item->toArray(), $this->data),
            'period' => $this->period,
        ];
    }
}

/**
 * 市区町村金額データ
 */
final readonly class MunicipalityAmountData
{
    public function __construct(
        public string $municipalityCode,
        public string $dataType,
        public string $priceCategory,
        public ?int $averageTradePrice,
        public ?int $medianTradePrice,
        public int $transactionCount,
        public string $period,
        public string $updatedAt
    ) {
    }

    public static function fromEntity(MunicipalityAmount $entity): self
    {
        return new self(
            municipalityCode: $entity->municipalityCode()->value(),
            dataType: $entity->dataType()->value(),
            priceCategory: $entity->priceCategory()->value(),
            averageTradePrice: $entity->averageTradePrice(),
            medianTradePrice: $entity->medianTradePrice(),
            transactionCount: $entity->transactionCount(),
            period: $entity->period()->value(),
            updatedAt: $entity->updatedAt()->format('Y-m-d H:i:s')
        );
    }

    public function toArray(): array
    {
        return [
            'municipality_code' => $this->municipalityCode,
            'data_type' => $this->dataType,
            'price_category' => $this->priceCategory,
            'average_trade_price' => $this->averageTradePrice,
            'median_trade_price' => $this->medianTradePrice,
            'transaction_count' => $this->transactionCount,
            'period' => $this->period,
            'updated_at' => $this->updatedAt,
        ];
    }
}
