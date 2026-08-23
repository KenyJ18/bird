<?php

declare(strict_types=1);

namespace Application\MuniAmountBatch\Dto;

/**
 * 取込バッチの実行結果
 */
final readonly class ImportMuniAmountsResult
{
    /**
     * @param string $period 取込対象の四半期
     * @param int $savedRowCount muni_amount に保存した行数
     * @param string[] $deletedPeriods 履歴保持（直近4四半期）により削除した period 一覧
     */
    public function __construct(
        public string $period,
        public int $savedRowCount,
        public array $deletedPeriods,
    ) {
    }
}
