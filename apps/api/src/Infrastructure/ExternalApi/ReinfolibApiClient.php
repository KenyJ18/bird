<?php

declare(strict_types=1);

namespace Infrastructure\ExternalApi;

use Domain\Reinfolib\Entity\ReinfolibTransaction;
use Domain\Reinfolib\Repository\ReinfolibApiClientInterface;
use Domain\Reinfolib\ValueObject\PrefectureCode;
use Domain\Reinfolib\ValueObject\ReinfolibDataType;
use Domain\Reinfolib\ValueObject\ReinfolibPriceCategory;
use Domain\MunicipalityAmount\ValueObject\Period;
use GuzzleHttp\Client;
use GuzzleHttp\Exception\GuzzleException;
use Illuminate\Support\Facades\Log;
use RuntimeException;

/**
 * Reinfolib APIクライアント実装
 */
class ReinfolibApiClient implements ReinfolibApiClientInterface
{
    private const BASE_URL = 'https://www.reinfolib.mlit.go.jp/ex-api/external/XIT001';
    private const TIMEOUT = 30;

    public function __construct(
        private readonly Client $httpClient,
        private readonly string $apiKey
    ) {
    }

    public function fetchTransactions(
        PrefectureCode $prefectureCode,
        Period $period,
        ReinfolibDataType $dataType,
        ReinfolibPriceCategory $priceCategory
    ): array {
        try {
            Log::info('Reinfolib API呼び出し開始', [
                'prefecture' => $prefectureCode->value(),
                'period' => $period->value(),
                'dataType' => $dataType->value(),
                'priceCategory' => $priceCategory->value(),
            ]);

            $response = $this->httpClient->get(self::BASE_URL, [
                'query' => [
                    'year' => $period->year(),
                    'period' => $period->quarter(),
                    'area' => $prefectureCode->value(),
                ],
                'headers' => [
                    'Ocp-Apim-Subscription-Key' => $this->apiKey,
                    'Accept' => 'application/json',
                ],
                'timeout' => self::TIMEOUT,
                'stream' => true, // ストリーミング有効化
            ]);

            $statusCode = $response->getStatusCode();
            if ($statusCode !== 200) {
                throw new RuntimeException("Reinfolib API呼び出しエラー: HTTP {$statusCode}");
            }

            // ストリームから少しずつ読み込む
            $body = '';
            $stream = $response->getBody();
            while (!$stream->eof()) {
                $body .= $stream->read(8192); // 8KBずつ読み込む
            }
            
            $data = json_decode($body, true);
            
            if (json_last_error() !== JSON_ERROR_NONE) {
                throw new RuntimeException('Reinfolib APIレスポンスのJSON解析エラー: ' . json_last_error_msg());
            }

            return $this->parseResponse($data, $dataType, $priceCategory, $period);

        } catch (GuzzleException $e) {
            Log::error('Reinfolib API通信エラー', [
                'error' => $e->getMessage(),
                'prefecture' => $prefectureCode->value(),
            ]);
            throw new RuntimeException("Reinfolib API通信エラー: {$e->getMessage()}", 0, $e);
        }
    }

    public function fetchAllTargetAreaTransactions(
        Period $period,
        ReinfolibDataType $dataType,
        ReinfolibPriceCategory $priceCategory
    ): array {
        $allTransactions = [];

        foreach (PrefectureCode::targetAreaCodes() as $code) {
            $prefectureCode = new PrefectureCode($code);
            
            try {
                $transactions = $this->fetchTransactions(
                    $prefectureCode,
                    $period,
                    $dataType,
                    $priceCategory
                );
                
                $allTransactions = array_merge($allTransactions, $transactions);
                
                Log::info('都道府県データ取得完了', [
                    'prefecture' => $code,
                    'count' => count($transactions),
                ]);

                // API負荷軽減のため、都道府県間で1秒待機
                sleep(1);

            } catch (RuntimeException $e) {
                Log::warning('都道府県データ取得失敗（スキップ）', [
                    'prefecture' => $code,
                    'error' => $e->getMessage(),
                ]);
                // エラーが発生しても他の都道府県のデータ取得は継続
                continue;
            }
        }

        return $allTransactions;
    }

    /**
     * APIレスポンスをパースしてエンティティ配列に変換
     * 
     * @param array $data APIレスポンスデータ
     * @param ReinfolibDataType $dataType
     * @param ReinfolibPriceCategory $priceCategory
     * @param Period $period
     * @return ReinfolibTransaction[]
     */
    private function parseResponse(
        array $data,
        ReinfolibDataType $dataType,
        ReinfolibPriceCategory $priceCategory,
        Period $period
    ): array {
        if (!isset($data['data']) || !is_array($data['data'])) {
            Log::warning('Reinfolib APIレスポンスにdataフィールドがありません');
            return [];
        }

        $transactions = [];

        foreach ($data['data'] as $item) {
            // 必須フィールドのチェック
            if (!isset($item['MunicipalityCode'], $item['TradePrice'], $item['Type'])) {
                Log::debug('必須フィールドが不足しているデータをスキップ', ['item' => $item]);
                continue;
            }

            // データタイプのフィルタリング
            if ($item['Type'] !== $dataType->value()) {
                continue;
            }

            // 価格が数値でない場合はスキップ
            $tradePrice = filter_var($item['TradePrice'], FILTER_VALIDATE_INT);
            if ($tradePrice === false || $tradePrice <= 0) {
                continue;
            }

            try {
                $transactions[] = new ReinfolibTransaction(
                    municipalityCode: $item['MunicipalityCode'],
                    dataType: $dataType,
                    priceCategory: $priceCategory,
                    tradePrice: $tradePrice,
                    period: $period
                );
            } catch (\Exception $e) {
                Log::debug('取引データの生成に失敗', [
                    'item' => $item,
                    'error' => $e->getMessage(),
                ]);
                continue;
            }
        }

        Log::info('取引データ解析完了', [
            'total' => count($data['data']),
            'parsed' => count($transactions),
        ]);

        return $transactions;
    }
}
