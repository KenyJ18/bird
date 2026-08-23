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
     * @param MunicipalityAmountData[] $items
     */
    public function __construct(
        public array $items
    ) {
    }

    /**
     * Domainエンティティ配列から生成
     *
     * @param MunicipalityAmount[] $municipalityAmounts
     */
    public static function fromEntities(array $municipalityAmounts): self
    {
        $items = array_map(
            fn(MunicipalityAmount $entity) => MunicipalityAmountData::fromEntity($entity),
            $municipalityAmounts
        );

        return new self($items);
    }

    /**
     * フラットな配列に変換
     */
    public function toArray(): array
    {
        return array_map(fn($item) => $item->toArray(), $this->items);
    }
}

/**
 * 市区町村金額データ（camelCase 4フィールド）
 */
final readonly class MunicipalityAmountData
{
    public function __construct(
        public string $muniCode,
        public ?int $avgTradePrice,
        public ?int $medianTradePrice,
        public int $latestCount
    ) {
    }

    public static function fromEntity(MunicipalityAmount $entity): self
    {
        return new self(
            muniCode: $entity->municipalityCode()->value(),
            avgTradePrice: $entity->averageTradePrice(),
            medianTradePrice: $entity->medianTradePrice(),
            latestCount: $entity->transactionCount()
        );
    }

    public function toArray(): array
    {
        return [
            'muniCode'         => $this->muniCode,
            'avgTradePrice'    => $this->avgTradePrice,
            'medianTradePrice' => $this->medianTradePrice,
            'latestCount'      => $this->latestCount,
        ];
    }
}
