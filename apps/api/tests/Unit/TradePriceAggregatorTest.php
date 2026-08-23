<?php

declare(strict_types=1);

namespace Tests\Unit;

use Domain\MunicipalityAmount\Service\TradePriceAggregator;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

class TradePriceAggregatorTest extends TestCase
{
    public function test_奇数件の中央値は中央の値そのもの(): void
    {
        $result = TradePriceAggregator::aggregate([10, 30, 20]);

        $this->assertSame(20, $result['avg']);
        $this->assertSame(20, $result['median']);
        $this->assertSame(3, $result['count']);
    }

    public function test_偶数件の中央値は中央2件の平均(): void
    {
        $result = TradePriceAggregator::aggregate([10, 20, 30, 40]);

        $this->assertSame(25, $result['avg']);
        $this->assertSame(25, $result['median']);
        $this->assertSame(4, $result['count']);
    }

    public function test_1件のみの場合は平均も中央値もその値になる(): void
    {
        $result = TradePriceAggregator::aggregate([5_000_000]);

        $this->assertSame(5_000_000, $result['avg']);
        $this->assertSame(5_000_000, $result['median']);
        $this->assertSame(1, $result['count']);
    }

    public function test_入力順序に関わらず結果は同じになる(): void
    {
        $sorted = TradePriceAggregator::aggregate([10, 20, 30, 40, 50]);
        $shuffled = TradePriceAggregator::aggregate([40, 10, 50, 30, 20]);

        $this->assertSame($sorted, $shuffled);
    }

    public function test_平均は四捨五入した整数円で返る(): void
    {
        // 合計100、3件 → 33.33... → 33に丸められる
        $result = TradePriceAggregator::aggregate([0, 50, 50]);

        $this->assertSame(33, $result['avg']);
    }

    public function test_空配列は例外になる(): void
    {
        $this->expectException(InvalidArgumentException::class);

        TradePriceAggregator::aggregate([]);
    }
}
