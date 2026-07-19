<?php

namespace App\Console\Commands;

use GuzzleHttp\Client;
use Illuminate\Console\Command;

class CheckReinfolibApiCommand extends Command
{
    protected $signature = 'reinfolib:check';
    protected $description = 'Reinfolib API接続チェック（レスポンス構造確認）';

    public function handle(): int
    {
        try {
            $apiKey = config('services.reinfolib.api_key');
            
            if (!$apiKey) {
                $this->error('REINFOLIB_API_KEYが設定されていません');
                return self::FAILURE;
            }

            $this->info("Reinfolib API接続チェック開始");
            $this->info("APIキー: " . substr($apiKey, 0, 10) . "...");
            $this->newLine();

            $client = new Client();
            
            // 小規模なテストとして2024-Q1, 埼玉県(11)のデータを取得
            $this->info("テストパラメータ: 2024年Q1, 埼玉県(11)");
            
            $response = $client->get('https://www.reinfolib.mlit.go.jp/ex-api/external/XIT001', [
                'query' => [
                    'year' => '2024',
                    'period' => '1',
                    'area' => '11',
                ],
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $apiKey,
                    'Accept' => 'application/json',
                ],
                'timeout' => 30,
            ]);

            $statusCode = $response->getStatusCode();
            $this->info("HTTPステータス: {$statusCode}");

            if ($statusCode !== 200) {
                $this->error("API呼び出し失敗");
                return self::FAILURE;
            }

            // レスポンスヘッダーを確認
            $contentType = $response->getHeader('Content-Type')[0] ?? 'unknown';
            $contentLength = $response->getHeader('Content-Length')[0] ?? 'unknown';
            
            $this->info("Content-Type: {$contentType}");
            $this->info("Content-Length: {$contentLength}");
            $this->newLine();

            // レスポンスの最初の1000文字だけ確認
            $body = $response->getBody()->getContents();
            $bodyPreview = substr($body, 0, 1000);
            
            $this->info("レスポンス冒頭（1000文字）:");
            $this->line($bodyPreview);
            $this->newLine();

            // JSON解析
            $data = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                $this->error('JSON解析エラー: ' . json_last_error_msg());
                return self::FAILURE;
            }

            $this->info("JSON解析成功");
            $this->info("トップレベルキー: " . implode(', ', array_keys($data)));
            
            if (isset($data['data']) && is_array($data['data'])) {
                $this->info("データ件数: " . count($data['data']) . "件");
                
                if (count($data['data']) > 0) {
                    $this->newLine();
                    $this->info("最初のデータサンプル:");
                    $this->line(json_encode($data['data'][0], JSON_UNESCAPED_UNICODE | JSON_PRETTY_PRINT));
                }
            }

            $this->newLine();
            $this->info("✅ API接続テスト成功");
            
            return self::SUCCESS;

        } catch (\Exception $e) {
            $this->error("❌ エラー: " . $e->getMessage());
            return self::FAILURE;
        }
    }
}
