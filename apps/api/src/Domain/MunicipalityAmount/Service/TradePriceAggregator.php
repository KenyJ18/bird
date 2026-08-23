<?php

declare(strict_types=1);

namespace Domain\MunicipalityAmount\Service;

use InvalidArgumentException;

/**
 * 取引価格の集計（平均・中央値・件数）を算出するドメインサービス
 *
 * 中央値は PostgreSQL の percentile_cont(0.5) と同じ「連続分布」方式で算出する。
 * すなわち件数が偶数のときは中央2件の平均、奇数のときは中央値そのもの。
 */
final class TradePriceAggregator
{
    /**
     * @param int[] $tradePrices 取引価格（正の整数）の配列。1件以上必要
     * @return array{avg: int, median: int, count: int}
     */
    public static function aggregate(array $tradePrices): array
    {
        $count = count($tradePrices);
        if ($count === 0) {
            throw new InvalidArgumentException('集計対象の取引価格が0件です');
        }

        sort($tradePrices);

        return [
            'avg' => (int) round(array_sum($tradePrices) / $count),
            'median' => self::median($tradePrices),
            'count' => $count,
        ];
    }

    /**
     * @param int[] $sortedPrices 昇順ソート済みの配列
     */
    private static function median(array $sortedPrices): int
    {
        $count = count($sortedPrices);
        $mid = intdiv($count, 2);

        if ($count % 2 === 0) {
            // 偶数件：中央2件の平均（percentile_cont(0.5) の線形補間と同義）
            return (int) round(($sortedPrices[$mid - 1] + $sortedPrices[$mid]) / 2);
        }

        return $sortedPrices[$mid];
    }
}
