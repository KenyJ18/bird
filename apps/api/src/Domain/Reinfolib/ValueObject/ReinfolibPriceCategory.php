<?php

declare(strict_types=1);

namespace Domain\Reinfolib\ValueObject;

use InvalidArgumentException;

/**
 * Reinfolib価格区分Value Object
 *
 * XIT001 の `priceClassification` クエリパラメータに渡すコード。
 * "01"=取引価格のみ, "02"=成約価格のみ（未指定は両方、本アプリでは使用しない）
 */
final readonly class ReinfolibPriceCategory
{
    // 価格区分コード（priceClassification パラメータ値）
    public const TRANSACTION_PRICE = '01';  // 取引価格
    public const CONTRACT_PRICE = '02';     // 成約価格

    // レスポンスの PriceCategory フィールドが取りうる値
    private const RESPONSE_LABEL_TRANSACTION = '不動産取引価格情報';
    private const RESPONSE_LABEL_CONTRACT = '成約価格情報';

    private const VALID_CODES = [
        self::TRANSACTION_PRICE,
        self::CONTRACT_PRICE,
    ];

    public function __construct(
        private string $value
    ) {
        if (!in_array($value, self::VALID_CODES, true)) {
            throw new InvalidArgumentException(
                "無効な価格区分コードです: {$value}。有効な値: " . implode(', ', self::VALID_CODES)
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
     * 有効な価格区分コード一覧
     * 
     * @return string[]
     */
    public static function validCodes(): array
    {
        return self::VALID_CODES;
    }

    /**
     * PriceCategoryからReinfolibPriceCategoryに変換
     */
    public static function fromPriceCategory(\Domain\MunicipalityAmount\ValueObject\PriceCategory $priceCategory): self
    {
        return match ($priceCategory->value()) {
            '取引価格' => new self(self::TRANSACTION_PRICE),
            '成約価格' => new self(self::CONTRACT_PRICE),
            default => throw new InvalidArgumentException("未対応の価格区分: {$priceCategory->value()}"),
        };
    }

    /**
     * ReinfolibPriceCategoryからPriceCategoryに変換
     */
    public function toPriceCategory(): \Domain\MunicipalityAmount\ValueObject\PriceCategory
    {
        $value = match ($this->value) {
            self::TRANSACTION_PRICE => '取引価格',
            self::CONTRACT_PRICE => '成約価格',
        };

        return new \Domain\MunicipalityAmount\ValueObject\PriceCategory($value);
    }

    /**
     * この価格区分に対応する、APIレスポンス PriceCategory フィールドの期待値
     */
    public function responseLabel(): string
    {
        return match ($this->value) {
            self::TRANSACTION_PRICE => self::RESPONSE_LABEL_TRANSACTION,
            self::CONTRACT_PRICE => self::RESPONSE_LABEL_CONTRACT,
        };
    }
}
