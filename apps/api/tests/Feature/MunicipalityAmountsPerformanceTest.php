<?php

declare(strict_types=1);

namespace Tests\Feature;

use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Tests\TestCase;

final class MunicipalityAmountsPerformanceTest extends TestCase
{
    use RefreshDatabase;

    private const TOTAL_ROWS = 33446;

    private const RESPONSE_LIMIT_SECONDS = 1.0;

    public function test_municipality_amounts_response_stays_fast_with_full_history(): void
    {
        $this->seedFullHistory();

        $this->assertSame(self::TOTAL_ROWS, DB::table('muni_amount')->count());
        $endpoint = '/api/muni/amounts?'.http_build_query([
            'type' => '宅地(土地)',
            'priceCategory' => '取引価格',
        ]);

        // Warm application and database caches without including them in the measurement.
        $this->getJson($endpoint)
            ->assertOk();

        $startedAt = hrtime(true);
        $response = $this->getJson($endpoint);
        $elapsedSeconds = (hrtime(true) - $startedAt) / 1_000_000_000;

        $response
            ->assertOk()
            ->assertJsonCount(239)
            ->assertJsonStructure([
                '*' => ['muniCode', 'avgTradePrice', 'medianTradePrice', 'latestCount'],
            ]);

        if (getenv('MUNI_AMOUNTS_PERFORMANCE_REPORT') === '1') {
            fwrite(
                STDERR,
                sprintf(
                    "GET /api/muni/amounts: %.3f ms (%d rows)\n",
                    $elapsedSeconds * 1_000,
                    self::TOTAL_ROWS
                )
            );
        }

        self::assertLessThan(
            self::RESPONSE_LIMIT_SECONDS,
            $elapsedSeconds,
            sprintf(
                'GET /api/muni/amounts took %.3f seconds with %d rows.',
                $elapsedSeconds,
                self::TOTAL_ROWS
            )
        );
    }

    private function seedFullHistory(): void
    {
        $municipalityCodes = array_map(
            static fn (int $number): string => sprintf('%05d', 10000 + $number),
            range(0, 238)
        );
        $types = ['宅地(土地)', '宅地(土地と建物)', '中古マンション等'];
        $categories = ['取引価格', '成約価格'];
        $periods = [
            '2016-Q2', '2016-Q3', '2016-Q4',
            '2017-Q1', '2017-Q2', '2017-Q3', '2017-Q4',
            '2018-Q1', '2018-Q2', '2018-Q3', '2018-Q4',
            '2019-Q1', '2019-Q2', '2019-Q3', '2019-Q4',
            '2020-Q1', '2020-Q2', '2020-Q3', '2020-Q4',
            '2021-Q1', '2021-Q2', '2021-Q3',
            '2026-Q1',
        ];

        $rows = [];
        foreach ($periods as $period) {
            foreach ($types as $type) {
                foreach ($categories as $category) {
                    foreach ($municipalityCodes as $index => $municipalityCode) {
                        $rows[] = $this->row($municipalityCode, $type, $category, $period, $index);
                    }
                }
            }
        }

        foreach (array_slice($rows, 0, self::TOTAL_ROWS - count($rows)) as $row) {
            $row['period'] = '2021-Q4';
            $rows[] = $row;
        }

        foreach (array_chunk($rows, 500) as $chunk) {
            DB::table('muni_amount')->insert($chunk);
        }
    }

    private function row(
        string $municipalityCode,
        string $type,
        string $category,
        string $period,
        int $index
    ): array {
        return [
            'muni_code' => $municipalityCode,
            'type' => $type,
            'price_category' => $category,
            'avg_trade_price' => 20_000_000 + $index,
            'median_trade_price' => 18_000_000 + $index,
            'txn_count' => 10 + ($index % 100),
            'period' => $period,
            'updated_at' => '2026-09-22 00:00:00',
        ];
    }
}
