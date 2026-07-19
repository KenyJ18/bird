<?php

namespace App\Console\Commands;

use Application\Reinfolib\UseCase\FetchReinfolibTransactionsUseCase;
use Domain\MunicipalityAmount\ValueObject\DataType;
use Domain\MunicipalityAmount\ValueObject\Period;
use Domain\MunicipalityAmount\ValueObject\PriceCategory;
use Illuminate\Console\Command;

class TestReinfolibApiCommand extends Command
{
    /**
     * コマンド名と説明
     */
    protected $signature = 'reinfolib:test 
                            {--period= : 取得期間 (例: 2026-Q2)}
                            {--type= : データタイプ}
                            {--category= : 価格区分}';

    protected $description = 'Reinfolib APIの動作確認';

    public function __construct(
        private readonly FetchReinfolibTransactionsUseCase $useCase
    ) {
        parent::__construct();
    }

    /**
     * コマンド実行
     */
    public function handle(): int
    {
        try {
            // パラメータ取得（デフォルト値あり）
            $periodStr = $this->option('period') ?? Period::current()->value();
            $typeStr = $this->option('type') ?? '宅地(土地と建物)';
            $categoryStr = $this->option('category') ?? '取引価格';

            $period = new Period($periodStr);
            $dataType = new DataType($typeStr);
            $priceCategory = new PriceCategory($categoryStr);

            $this->info("Reinfolib API取得開始");
            $this->info("期間: {$period->value()}");
            $this->info("データタイプ: {$dataType->value()}");
            $this->info("価格区分: {$priceCategory->value()}");
            $this->newLine();

            // データ取得実行
            $transactions = $this->useCase->execute($period, $dataType, $priceCategory);

            // 結果表示
            $this->info("取得件数: " . count($transactions) . "件");

            if (count($transactions) > 0) {
                $this->newLine();
                $this->info("サンプルデータ（最初の3件）:");
                
                foreach (array_slice($transactions, 0, 3) as $index => $transaction) {
                    $this->line(sprintf(
                        "[%d] 市区町村: %s, 価格: %s円, 期間: %s",
                        $index + 1,
                        $transaction->municipalityCode(),
                        number_format($transaction->tradePrice()),
                        $transaction->period()->value()
                    ));
                }
            }

            $this->newLine();
            $this->info("✅ テスト完了");

            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ エラー: " . $e->getMessage());
            $this->error($e->getTraceAsString());
            return self::FAILURE;
        }
    }
}
