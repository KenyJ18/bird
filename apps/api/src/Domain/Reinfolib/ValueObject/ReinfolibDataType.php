<?php

declare(strict_types=1);

namespace Domain\Reinfolib\ValueObject;

use InvalidArgumentException;

/**
 * ReinfolibデータタイプValue Object
 *
 * XIT001 にはデータ種類を絞り込むクエリパラメータが存在しない。
 * レスポンスの `Type` フィールドがそのまま日本語ラベルで返るため、
 * 値はそのラベル自体を保持し、レスポンスの後段フィルタリングに使う（設計書 §2.1・§7.2参照）
 */
final readonly class ReinfolibDataType
{
    // データタイプ（レスポンス Type フィールドの値そのもの。設計書 §7.2参照）
    public const RESIDENTIAL_LAND = '宅地(土地)';
    public const RESIDENTIAL_LAND_BUILDING = '宅地(土地と建物)';
    public const USED_CONDOMINIUM = '中古マンション等';

    private const VALID_CODES = [
        self::RESIDENTIAL_LAND,
        self::RESIDENTIAL_LAND_BUILDING,
        self::USED_CONDOMINIUM,
    ];

    public function __construct(
        private string $value
    ) {
        if (!in_array($value, self::VALID_CODES, true)) {
            throw new InvalidArgumentException(
                "無効なデータタイプコードです: {$value}。有効な値: " . implode(', ', self::VALID_CODES)
            );
        }
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
     * 有効なデータタイプコード一覧
     * 
     * @return string[]
     */
    public static function validCodes(): array
    {
        return self::VALID_CODES;
    }

    /**
     * DataTypeからReinfolibDataTypeに変換
     */
    public static function fromDataType(\Domain\MunicipalityAmount\ValueObject\DataType $dataType): self
    {
        return new self($dataType->value());
    }

    /**
     * ReinfolibDataTypeからDataTypeに変換
     */
    public function toDataType(): \Domain\MunicipalityAmount\ValueObject\DataType
    {
        return new \Domain\MunicipalityAmount\ValueObject\DataType($this->value);
    }
}
