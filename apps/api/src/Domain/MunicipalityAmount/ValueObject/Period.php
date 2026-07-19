<?php

declare(strict_types=1);

namespace Domain\MunicipalityAmount\ValueObject;

use InvalidArgumentException;

/**
 * 対象期間（四半期）
 * 形式: "YYYY-QN" (例: "2026-Q2")
 */
final readonly class Period
{
    private string $value;

    public function __construct(string $value)
    {
        if (!preg_match('/^\d{4}-Q[1-4]$/', $value)) {
            throw new InvalidArgumentException(
                "Invalid period format: {$value}. Must be in format YYYY-QN (e.g., 2026-Q2)"
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
     * 年を取得
     */
    public function year(): int
    {
        return (int) substr($this->value, 0, 4);
    }

    /**
     * 四半期番号を取得（1-4）
     */
    public function quarter(): int
    {
        return (int) substr($this->value, 6, 1);
    }

    /**
     * 次の四半期を取得
     */
    public function next(): self
    {
        $year = $this->year();
        $quarter = $this->quarter();

        if ($quarter === 4) {
            return new self(($year + 1) . '-Q1');
        }

        return new self($year . '-Q' . ($quarter + 1));
    }

    /**
     * 前の四半期を取得
     */
    public function previous(): self
    {
        $year = $this->year();
        $quarter = $this->quarter();

        if ($quarter === 1) {
            return new self(($year - 1) . '-Q4');
        }

        return new self($year . '-Q' . ($quarter - 1));
    }

    /**
     * 現在の四半期を取得
     */
    public static function current(): self
    {
        $month = (int) date('n');
        $year = (int) date('Y');
        $quarter = (int) ceil($month / 3);

        return new self("{$year}-Q{$quarter}");
    }
}
