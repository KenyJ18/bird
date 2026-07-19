<?php

namespace Database\Seeders;

use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\DB;

class MunicipalityAmountSeeder extends Seeder
{
    /**
     * Run the database seeds.
     */
    public function run(): void
    {
        // 1都3県の代表的な市区町村のテストデータ
        $municipalities = [
            // 東京都
            ['13101', '千代田区'],
            ['13102', '中央区'],
            ['13103', '港区'],
            ['13104', '新宿区'],
            ['13105', '文京区'],
            ['13106', '台東区'],
            ['13107', '墨田区'],
            ['13108', '江東区'],
            // 神奈川県
            ['14101', '横浜市鶴見区'],
            ['14102', '横浜市神奈川区'],
            ['14103', '横浜市西区'],
            ['14131', '川崎市川崎区'],
            ['14132', '川崎市幸区'],
            // 埼玉県
            ['11100', 'さいたま市'],
            ['11101', 'さいたま市西区'],
            ['11102', 'さいたま市北区'],
            ['11201', '川越市'],
            // 千葉県
            ['12100', '千葉市'],
            ['12101', '千葉市中央区'],
            ['12102', '千葉市花見川区'],
            ['12217', '市川市'],
        ];
        
        $dataTypes = ['宅地(土地)', '宅地(土地と建物)', '中古マンション等'];
        $priceCategories = ['取引価格', '成約価格'];
        $period = '2026-Q2';
        
        $data = [];
        
        foreach ($municipalities as [$code, $name]) {
            foreach ($dataTypes as $type) {
                foreach ($priceCategories as $priceCategory) {
                    // 地域と種類によって価格を変動させる
                    $basePrice = $this->getBasePrice($code, $type);
                    $variance = rand(80, 120) / 100; // 80%〜120%の変動
                    
                    $avgPrice = (int)($basePrice * $variance);
                    $medianPrice = (int)($avgPrice * 0.9); // 中央値は平均の90%程度
                    $txnCount = rand(10, 200); // 取引件数
                    
                    $data[] = [
                        'muni_code' => $code,
                        'type' => $type,
                        'price_category' => $priceCategory,
                        'avg_trade_price' => $avgPrice,
                        'median_trade_price' => $medianPrice,
                        'txn_count' => $txnCount,
                        'period' => $period,
                        'updated_at' => now(),
                    ];
                }
            }
        }
        
        // チャンク単位で挿入
        foreach (array_chunk($data, 100) as $chunk) {
            DB::table('muni_amount')->insert($chunk);
        }
        
        $this->command->info('Inserted ' . count($data) . ' municipality amount records.');
    }
    
    /**
     * 市区町村コードとデータ種類から基準価格を取得
     */
    private function getBasePrice(string $code, string $type): int
    {
        // 都道府県コード（最初の2桁）
        $prefCode = substr($code, 0, 2);
        
        // 都道府県による価格差
        $prefMultiplier = match($prefCode) {
            '13' => 1.5,  // 東京都（高め）
            '14' => 1.2,  // 神奈川県
            '11' => 1.0,  // 埼玉県
            '12' => 1.0,  // 千葉県
            default => 1.0,
        };
        
        // データ種類による基準価格
        $basePrice = match($type) {
            '宅地(土地)' => 30000000,
            '宅地(土地と建物)' => 45000000,
            '中古マンション等' => 35000000,
            default => 40000000,
        };
        
        return (int)($basePrice * $prefMultiplier);
    }
}
