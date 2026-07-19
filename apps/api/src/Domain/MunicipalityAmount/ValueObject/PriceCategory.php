<?php

declare(strict_types=1);

namespace Domain\MunicipalityAmount\ValueObject;

use InvalidArgumentException;

/**
 * 価格区分（取引価格 / 成約価格）
 */
final readonly class PriceCategory
{
    public const TRANSACTION_PRICE = '取引価格';
    public const CONTRACT_PRICE = '成約価格';

    private string $value;

    public function __construct(string $value)
    {
        if (!self::isValid($value)) {
            throw new InvalidArgumentException(
                "Invalid price category: {$value}. Must be one of: " . 
                implode(', ', self::validCategories())
            );
        }

        $this->value = $value;
    }

    public function value(): string
    {
        return $this->value;
    }

    public function equals(self $other): bool
    {
        return $this->value === $other->value;
    }

    public function __toString(): string
    {
        return $this->value;
    }

    /**
     * 有効な価格区分のリスト
     */
    public static function validCategories(): array
    {
        return [
            self::TRANSACTION_PRICE,
            self::CONTRACT_PRICE,
        ];
    }

    /**
     * 有効な価格区分かどうかを判定
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::validCategories(), true);
    }

    /**
     * デフォルト値（取引価格）を取得
     */
    public static function default(): self
    {
        return new self(self::TRANSACTION_PRICE);
    }
}
