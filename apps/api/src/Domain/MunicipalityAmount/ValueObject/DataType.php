<?php

declare(strict_types=1);

namespace Domain\MunicipalityAmount\ValueObject;

use InvalidArgumentException;

/**
 * データ種類（不動産の種類）
 */
final readonly class DataType
{
    // 対象とするデータ種類（設計書§7.2より）
    public const RESIDENTIAL_LAND = '宅地(土地)';
    public const RESIDENTIAL_LAND_AND_BUILDING = '宅地(土地と建物)';
    public const USED_CONDOMINIUM = '中古マンション等';

    private string $value;

    public function __construct(string $value)
    {
        if (!self::isValid($value)) {
            throw new InvalidArgumentException(
                "Invalid data type: {$value}. Must be one of: " . 
                implode(', ', self::validTypes())
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
     * 有効なデータ種類のリスト
     */
    public static function validTypes(): array
    {
        return [
            self::RESIDENTIAL_LAND,
            self::RESIDENTIAL_LAND_AND_BUILDING,
            self::USED_CONDOMINIUM,
        ];
    }

    /**
     * 有効なデータ種類かどうかを判定
     */
    public static function isValid(string $value): bool
    {
        return in_array($value, self::validTypes(), true);
    }

    /**
     * デフォルト値（宅地(土地と建物)）を取得
     */
    public static function default(): self
    {
        return new self(self::RESIDENTIAL_LAND_AND_BUILDING);
    }
}
