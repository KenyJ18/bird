<?php

declare(strict_types=1);

namespace Domain\MunicipalityAmount\ValueObject;

use InvalidArgumentException;

/**
 * 市区町村コード（全国地方公共団体コード）
 * 5桁の数字で構成される
 */
final readonly class MunicipalityCode
{
    private string $value;

    public function __construct(string $value)
    {
        if (!preg_match('/^\d{5}$/', $value)) {
            throw new InvalidArgumentException(
                "Invalid municipality code format: {$value}. Must be 5 digits."
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
     * 1都3県の市区町村コードかどうかを判定
     */
    public function isInTargetArea(): bool
    {
        $prefectureCode = substr($this->value, 0, 2);
        
        // 11: 埼玉, 12: 千葉, 13: 東京, 14: 神奈川
        return in_array($prefectureCode, ['11', '12', '13', '14'], true);
    }

    /**
     * 東京都の島嶼部かどうかを判定
     */
    public function isTokyoIsland(): bool
    {
        $islandCodes = [
            '13361', // 大島町
            '13362', // 利島村
            '13363', // 新島村
            '13364', // 神津島村
            '13381', // 三宅村
            '13382', // 御蔵島村
            '13401', // 八丈町
            '13402', // 青ヶ島村
            '13421', // 小笠原村
        ];

        return in_array($this->value, $islandCodes, true);
    }
}
