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

    // リトライ設定（設計書 §5.1「リトライ／指数バックオフ・429時の待機」対応）
    private const MAX_ATTEMPTS = 3;
    private const BACKOFF_BASE_SECONDS = 2;
    private const RATE_LIMIT_WAIT_SECONDS = 60; // 429時、Retry-Afterヘッダがない場合の固定待機秒数

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
        $lastError = null;

        for ($attempt = 1; $attempt <= self::MAX_ATTEMPTS; $attempt++) {
            try {
                Log::info('Reinfolib API呼び出し開始', [
                    'prefecture' => $prefectureCode->value(),
                    'period' => $period->value(),
                    'dataType' => $dataType->value(),
                    'priceCategory' => $priceCategory->value(),
                    'attempt' => $attempt,
                ]);

                $response = $this->httpClient->get(self::BASE_URL, [
                    'query' => [
                        'year' => $period->year(),
                        'quarter' => $period->quarter(),
                        'area' => $prefectureCode->value(),
                        'priceClassification' => $priceCategory->value(),
                    ],
                    'headers' => [
                        'Ocp-Apim-Subscription-Key' => $this->apiKey,
                        'Accept' => 'application/json',
                    ],
                    'timeout' => self::TIMEOUT,
                    'stream' => true, // ストリーミング有効化
                    'http_errors' => false, // 4xx/5xxで例外にせず自前でハンドリングする
                ]);

                $statusCode = $response->getStatusCode();

                // 404 = 四半期未公開 or その都道府県は該当四半期0件（設計書 §5.0）。
                // エラーではなく「データなし」として扱い、リトライせず空配列を返す。
                if ($statusCode === 404) {
                    Log::info('Reinfolib API 404（データなし）', [
                        'prefecture' => $prefectureCode->value(),
                        'period' => $period->value(),
                    ]);
                    return [];
                }

                if ($statusCode === 429) {
                    // Retry-After ヘッダがあればその指示に従い、なければ固定60秒待機する
                    $retryAfter = (int) ($response->getHeaderLine('Retry-After') ?: self::RATE_LIMIT_WAIT_SECONDS);
                    Log::warning('Reinfolib API レート制限(429)。待機してリトライします', [
                        'prefecture' => $prefectureCode->value(),
                        'attempt' => $attempt,
                        'waitSeconds' => $retryAfter,
                    ]);
                    $lastError = "HTTP 429 (レート制限)";
                    if ($attempt < self::MAX_ATTEMPTS) {
                        sleep($retryAfter);
                    }
                    continue;
                }

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
                $lastError = $e->getMessage();
                Log::warning('Reinfolib API通信エラー', [
                    'error' => $e->getMessage(),
                    'prefecture' => $prefectureCode->value(),
                    'attempt' => $attempt,
                ]);
                if ($attempt < self::MAX_ATTEMPTS) {
                    sleep(self::BACKOFF_BASE_SECONDS * $attempt); // 簡易指数バックオフ
                }
            } catch (RuntimeException $e) {
                // HTTPエラー(404/429以外)・JSON解析エラーはリトライ対象
                $lastError = $e->getMessage();
                Log::warning('Reinfolib API呼び出し失敗', [
                    'error' => $e->getMessage(),
                    'prefecture' => $prefectureCode->value(),
                    'attempt' => $attempt,
                ]);
                if ($attempt < self::MAX_ATTEMPTS) {
                    sleep(self::BACKOFF_BASE_SECONDS * $attempt);
                }
            }
        }

        Log::error('Reinfolib API通信エラー（リトライ上限到達）', [
            'prefecture' => $prefectureCode->value(),
            'period' => $period->value(),
            'lastError' => $lastError,
        ]);
        throw new RuntimeException(
            "Reinfolib API通信エラー（" . self::MAX_ATTEMPTS . "回リトライ後も失敗）: {$lastError}"
        );
    }

    public function fetchAllTargetAreaTransactions(
        Period $period,
        ReinfolibDataType $dataType,
        ReinfolibPriceCategory $priceCategory
    ): array {
        $allTransactions = [];

        // 404（データなし）は fetchTransactions 内で空配列として吸収されるため、
        // ここに例外が伝播するのは「404/429以外の失敗がリトライ上限まで続いた」場合のみ。
        // その場合は途中経過を握りつぶさず呼び出し元（バッチ）に伝播させ、
        // 不完全な四半期データで muni_amount を更新しないようにする（可用性優先・設計書 §5.1）。
        foreach (PrefectureCode::targetAreaCodes() as $code) {
            $prefectureCode = new PrefectureCode($code);

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

            // データタイプのフィルタリング（クエリでは絞り込めないためレスポンス側で判定）
            if ($item['Type'] !== $dataType->value()) {
                continue;
            }

            // 価格区分のフィルタリング（priceClassification 指定時は基本一致するはずだが念のため検証）
            if (isset($item['PriceCategory']) && $item['PriceCategory'] !== $priceCategory->responseLabel()) {
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
