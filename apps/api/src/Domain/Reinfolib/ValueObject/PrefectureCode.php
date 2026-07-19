<?php

declare(strict_types=1);

namespace Domain\Reinfolib\ValueObject;

use InvalidArgumentException;

/**
 * 都道府県コードValue Object
 * 
 * 2桁の都道府県コードを表現
 */
final readonly class PrefectureCode
{
    // 対象都道府県（1都3県）
    public const SAITAMA = '11';
    public const CHIBA = '12';
    public const TOKYO = '13';
    public const KANAGAWA = '14';

    public function __construct(
        private string $value
    ) {
        if (!preg_match('/^\d{2}$/', $value)) {
            throw new InvalidArgumentException("都道府県コードは2桁の数字である必要があります: {$value}");
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
     * 対象エリア（1都3県）かどうか
     */
    public function isTargetArea(): bool
    {
        return in_array($this->value, [
            self::SAITAMA,
            self::CHIBA,
            self::TOKYO,
            self::KANAGAWA,
        ], true);
    }

    /**
     * 対象エリアの都道府県コード一覧を取得
     * 
     * @return string[]
     */
    public static function targetAreaCodes(): array
    {
        return [
            self::SAITAMA,
            self::CHIBA,
            self::TOKYO,
            self::KANAGAWA,
        ];
    }
}
