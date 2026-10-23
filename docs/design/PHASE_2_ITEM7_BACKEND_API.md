# フェーズ2 項目7 — バックエンド API エンドポイント実装
## 詳細要件・実装設計ドキュメント

**作成日**: 2026-08-15
**対象**: `GET /api/muni/amounts` 修正 + `GET /api/muni/snapshot-meta` 新規

---

## 1. 背景・問題の整理

### 1.1 既存実装の課題

フェーズ2 着手時点では `GET /api/muni/amounts` の Routes / Controller / UseCase / Repository の
骨格は存在していたが、設計仕様（`bird_design.md` §6.2・§7.2.1）と以下2点で不整合があった。

#### 課題A: レスポンス形式の不整合

| 項目 | 既存実装 | 設計仕様（§7.2.1）|
|---|---|---|
| 外形 | `{"data":[...], "period":"..."}` オブジェクト | フラットな配列 `[...]` |
| キー名 | `municipality_code`, `data_type` など snake_case | `muniCode`, `avgTradePrice` など camelCase |
| フィールド数 | 8フィールド（不要な型情報・期間などを含む） | 4フィールドのみ |

フロントエンド（§7.2.1）は以下のコードでレスポンスを直接消費するため、
フラット配列 + camelCase 4フィールドが必須：

```ts
const byMuni = new Map(data?.map((r) => [r.muniCode, r]));
```

#### 課題B: 2段階グレーアウト未実装

既存の `findByTypeAndCategory()` は **最新四半期に存在するレコードのみ**を返す。
設計仕様では「過去1年（直近4四半期）に1件でも取引があるが、最新四半期は0件」の
市区町村についても API が返す必要がある（フロント側で薄いグレー表示に使う）。

| 市区町村の状態 | 既存実装 | 要件 |
|---|---|---|
| 最新四半期に取引あり | 返す ✅ | 返す |
| 最新四半期は0件・過去1年に取引あり | **返さない ❌** | 返す（`latestCount=0` で） |
| 過去1年に取引なし | 返さない | 返さない |

#### 課題C: `GET /api/muni/snapshot-meta` が未実装

Route / Controller / UseCase / DTO が全て欠落。
`snapshot_meta` テーブル（§6.1）の `period` と `snapshot_at` を返すだけのシンプルな
エンドポイントだが、フロントエンドがフッターの基準時刻表示に必要とする。

---

## 2. 詳細要件

### 2.1 `GET /api/muni/amounts`

#### リクエスト仕様

| パラメータ | 必須 | 型 | 説明 |
|---|---|---|---|
| `type` | 必須 | string | reinfolib の Type 値（例: `宅地(土地と建物)`） |
| `priceCategory` | 必須 | string | `取引価格` または `成約価格` |

URL エンコードが必要（日本語クエリ）:

```
GET /api/muni/amounts?type=%E5%AE%85%E5%9C%B0%28%E5%9C%9F%E5%9C%B0%E3%81%A8%E5%BB%BA%E7%89%A9%29&priceCategory=%E5%8F%96%E5%BC%95%E4%BE%A1%E6%A0%BC
```

#### レスポンス仕様（修正後）

```json
[
  {
    "muniCode":         "13101",
    "avgTradePrice":    45000000,
    "medianTradePrice": 42000000,
    "latestCount":      38
  },
  {
    "muniCode":         "13361",
    "avgTradePrice":    null,
    "medianTradePrice": null,
    "latestCount":      0
  }
]
```

| フィールド | 型 | 説明 |
|---|---|---|
| `muniCode` | string | 全国地方公共団体コード（5桁） |
| `avgTradePrice` | integer \| null | 平均取引価格（円）。最新四半期0件の場合 null |
| `medianTradePrice` | integer \| null | 中央値（円）。同上 |
| `latestCount` | integer | 最新四半期の取引件数。0の場合は薄いグレー表示対象 |

- `latestCount = 0` の市区町村がレスポンスに含まれる（2段階グレーアウト対応）
- 過去1年に取引が一件もない市区町村はレスポンスに含まれない（フロント側でグレーアウト）
- データ0件時は空配列 `[]` を返す（エラーにならない）
- HTTP ステータスは常に 200

#### フロントでの利用（§7.2.1 再掲）

```ts
const byMuni = new Map(data?.map((r) => [r.muniCode, r]));

function classify(muniCode) {
  const r = byMuni.get(muniCode);
  if (!r)                  return "greyout";    // 全期間0件 → グレーアウト
  if (r.latestCount === 0) return "lightGrey";  // 直近四半期0件 → 薄いグレー
  const value = stat === "平均" ? r.avgTradePrice : r.medianTradePrice;
  if (value == null)           return "lightGrey";
  if (value <= budget)         return "within";   // 予算以下 → 緑
  if (value <= budget + 1e7)   return "orange";   // 予算+1000万以内 → オレンジ
  return "red";                                   // 予算+1000万超 → 赤
}
```

### 2.2 `GET /api/muni/snapshot-meta`

#### リクエスト仕様

パラメータなし。

#### レスポンス仕様

```json
{
  "period":     "2026-Q2",
  "snapshotAt": "2026-07-20T21:35:00+09:00"
}
```

| フィールド | 型 | 説明 |
|---|---|---|
| `period` | string | 最新スナップショットの対象四半期（例: `2026-Q2`） |
| `snapshotAt` | string | 取込完了日時（JST・ISO 8601 ATOM 形式） |

- `snapshot_meta` テーブルにレコードがない場合は HTTP 404 を返す
- `snapshot_at` はタイムゾーン付きで UTC → JST に変換して返す（`+09:00` 表記）

---

## 3. 実装設計

### 3.1 アーキテクチャ概要

本プロジェクトは以下の DDD レイヤー構造を採用：

```
Presentation（HTTP I/O）
    └── Application（UseCase / DTO）
          └── Domain（Entity / ValueObject / Repository Interface）
                └── Infrastructure（Eloquent / DB / External API）
```

### 3.2 変更ファイル一覧

| ファイルパス | 種別 | 変更内容 |
|---|---|---|
| `src/Application/MunicipalityAmount/Dto/GetMunicipalityAmountsResponse.php` | 修正 | DTO 刷新 |
| `src/Domain/MunicipalityAmount/Repository/MunicipalityAmountRepositoryInterface.php` | 修正 | メソッド追加 |
| `src/Infrastructure/Repository/EloquentMunicipalityAmountRepository.php` | 修正 | SQL 実装追加 |
| `src/Application/MunicipalityAmount/UseCase/GetMunicipalityAmountsUseCase.php` | 修正 | 新メソッドに切替 |
| `routes/api.php` | 修正 | ルート追加 |
| `src/Infrastructure/Models/SnapshotMetaModel.php` | 新規 | Eloquent モデル |
| `src/Application/SnapshotMeta/Dto/GetSnapshotMetaResponse.php` | 新規 | レスポンス DTO |
| `src/Application/SnapshotMeta/UseCase/GetSnapshotMetaUseCase.php` | 新規 | UseCase |
| `src/Presentation/Api/Controllers/SnapshotMetaController.php` | 新規 | コントローラー |

---

## 4. 各ファイルの実装詳細

### 4.1 GetMunicipalityAmountsResponse.php（修正）

**変更前**:
- `GetMunicipalityAmountsResponse` が `data` + `period` の2フィールドを持つ
- `fromEntities(array $entities, string $period)` に `$period` 引数
- `toArray()` が `{"data":[...], "period":"..."}` のオブジェクトを返す
- `MunicipalityAmountData` が snake_case 8フィールドを持つ

**変更後**:
- `GetMunicipalityAmountsResponse` は `items` フィールドのみ
- `fromEntities(array $entities)` — `$period` 引数を削除
- `toArray()` がフラット配列 `[...]` を返す
- `MunicipalityAmountData` を camelCase 4フィールドに簡素化:
  - `muniCode` / `avgTradePrice` / `medianTradePrice` / `latestCount`

```php
// GetMunicipalityAmountsResponse
public function toArray(): array
{
    return array_map(fn($item) => $item->toArray(), $this->items);
}

// MunicipalityAmountData
public function toArray(): array
{
    return [
        'muniCode'         => $this->muniCode,
        'avgTradePrice'    => $this->avgTradePrice,
        'medianTradePrice' => $this->medianTradePrice,
        'latestCount'      => $this->latestCount,
    ];
}
```

### 4.2 MunicipalityAmountRepositoryInterface.php（修正）

既存の `findByTypeAndCategory()` はそのまま残し、新メソッドを追加：

```php
/**
 * 2段階グレーアウト対応取得。過去1年に1件でも取引がある全市区町村を返す
 *
 * @return MunicipalityAmount[]
 */
public function findWithGreyoutByTypeAndCategory(
    DataType $dataType,
    PriceCategory $priceCategory
): array;
```

`findByTypeAndCategory()` を削除しない理由: 他の用途（バッチ処理など）で
将来使われる可能性があるため、インターフェースの後方互換を保つ。

### 4.3 EloquentMunicipalityAmountRepository.php（修正）

**SQL 設計（§6.2 準拠・MySQL 8.0+ CTE 使用）**:

```sql
WITH latest AS (SELECT MAX(period) AS p FROM muni_amount)
SELECT
    h.muni_code,
    la.avg_trade_price,
    la.median_trade_price,
    COALESCE(la.txn_count, 0)                       AS latest_count,
    COALESCE(la.period, (SELECT p FROM latest))     AS period,
    COALESCE(la.updated_at, NOW())                  AS updated_at
FROM (
    -- 保持期間（1年）内に一度でも取引がある市区町村を列挙
    SELECT DISTINCT muni_code
    FROM muni_amount
    WHERE type = ? AND price_category = ?
) h
LEFT JOIN muni_amount la
    ON  la.muni_code      = h.muni_code
    AND la.type           = ?
    AND la.price_category = ?
    AND la.period         = (SELECT p FROM latest)
```

**SQL のポイント**:

1. サブクエリ `h` で「過去1年にこの type & price_category で1件でも取引があった
   全市区町村」を重複なく列挙する。
2. `LEFT JOIN` で最新四半期のレコードを結合。最新四半期に存在しない市区町村は
   `la.*` が全て NULL となる。
3. `COALESCE(la.txn_count, 0)` により、最新四半期0件市区町村は `latest_count=0`
   として返る（NULL ではない）。
4. `period` と `updated_at` も `COALESCE` でフォールバック値を補完し、
   `MunicipalityAmount` エンティティの非 null 制約を満たす。
5. MySQL 8.4.4（本番環境）は `WITH` CTE をサポート済み。

**PHP 側のマッピング**:

```php
return new MunicipalityAmount(
    municipalityCode: new MunicipalityCode($row->muni_code),
    dataType:         $dataType,
    priceCategory:    $priceCategory,
    averageTradePrice:  $row->avg_trade_price !== null ? (int) $row->avg_trade_price : null,
    medianTradePrice:   $row->median_trade_price !== null ? (int) $row->median_trade_price : null,
    transactionCount: (int) $row->latest_count,
    period:           new Period($row->period),
    updatedAt:        new \DateTimeImmutable($row->updated_at)
);
```

`avg_trade_price` / `median_trade_price` は LEFT JOIN の結果 NULL になりうるため
`null` を維持する（フロントで `latestCount=0` と一致する）。

### 4.4 GetMunicipalityAmountsUseCase.php（修正）

**変更前**:
- `getLatestPeriod()` でデータ有無チェック → `findByTypeAndCategory()` 呼び出し
- `period` を UseCase が管理してレスポンス DTO に渡す

**変更後**:
- `findWithGreyoutByTypeAndCategory()` 1回のみ呼び出す（period 管理不要）
- データ0件の場合でも `fromEntities([])` で空配列を返す

```php
public function execute(GetMunicipalityAmountsRequest $request): GetMunicipalityAmountsResponse
{
    $municipalityAmounts = $this->repository->findWithGreyoutByTypeAndCategory(
        $request->dataType,
        $request->priceCategory
    );

    return GetMunicipalityAmountsResponse::fromEntities($municipalityAmounts);
}
```

`GetMunicipalityAmountsRequest` の `period` フィールドは既存コードに残存するが、
この UseCase では使用しない（削除は次のリファクタリングフェーズで対応）。

### 4.5 SnapshotMetaModel.php（新規）

```php
class SnapshotMetaModel extends Model
{
    protected $table = 'snapshot_meta';
    public $timestamps = false;       // updated_at / created_at カラムなし
    protected $primaryKey = 'id';
    public $incrementing = false;     // id は常に 1 固定（自動採番しない）
    protected $casts = ['snapshot_at' => 'immutable_datetime'];
}
```

`snapshot_meta` テーブルは常に1行（`id=1`）運用。
`immutable_datetime` キャストにより `snapshot_at` が `DateTimeImmutable` として
PHP に渡される。

### 4.6 GetSnapshotMetaResponse.php（新規）

```php
public function toArray(): array
{
    return [
        'period'     => $this->period,
        'snapshotAt' => $this->snapshotAt
                            ->setTimezone(new DateTimeZone('Asia/Tokyo'))
                            ->format(DateTimeInterface::ATOM),
    ];
}
```

`DateTimeInterface::ATOM` = `Y-m-d\TH:i:sP` 形式（例: `2026-07-20T21:35:00+09:00`）。
DB には UTC で保存し、レスポンス時に JST に変換して返す。

### 4.7 GetSnapshotMetaUseCase.php（新規）

```php
public function execute(): ?GetSnapshotMetaResponse
{
    $model = SnapshotMetaModel::find(1);
    if ($model === null) return null;
    return new GetSnapshotMetaResponse($model->period, $model->snapshot_at);
}
```

`find(1)` で id=1 の1行を取得。テーブルが空（バッチ未実行）の場合は
`null` を返し、Controller が 404 を返す。

### 4.8 SnapshotMetaController.php（新規）

```php
public function show(): JsonResponse
{
    $response = $this->useCase->execute();
    if ($response === null) {
        return response()->json(['error' => 'Not found'], 404);
    }
    return response()->json($response->toArray());
}
```

シンプルに UseCase の結果を JSON として返す。
DI は Laravel のサービスコンテナが自動解決する（SnapshotMetaUseCase は
Infrastructure への直接依存を持つため、サービスプロバイダへの明示的な登録は不要）。

### 4.9 routes/api.php（修正）

```php
use Presentation\Api\Controllers\MunicipalityAmountController;
use Presentation\Api\Controllers\SnapshotMetaController;

Route::get('/muni/amounts', [MunicipalityAmountController::class, 'index'])
    ->name('api.muni.amounts.index');

Route::get('/muni/snapshot-meta', [SnapshotMetaController::class, 'show'])
    ->name('api.muni.snapshot-meta.show');
```

---

## 5. データフロー

### 5.1 `GET /api/muni/amounts` のデータフロー

```
HTTP GET /api/muni/amounts?type=...&priceCategory=...
    │
    ▼
GetMunicipalityAmountsHttpRequest（バリデーション）
    │ validated()
    ▼
GetMunicipalityAmountsRequest::fromArray()
    │ DataType / PriceCategory ValueObject に変換
    ▼
GetMunicipalityAmountsUseCase::execute()
    │
    ▼
EloquentMunicipalityAmountRepository::findWithGreyoutByTypeAndCategory()
    │ WITH CTE + LEFT JOIN SQL を MySQL に発行
    ▼
MunicipalityAmount[] エンティティ配列
    │
    ▼
GetMunicipalityAmountsResponse::fromEntities()
    │ MunicipalityAmountData（camelCase 4フィールド）に変換
    ▼
response()->json($response->toArray())
    │ フラット配列 JSON
    ▼
HTTP 200 [{"muniCode":"...","avgTradePrice":...,...}, ...]
```

### 5.2 `GET /api/muni/snapshot-meta` のデータフロー

```
HTTP GET /api/muni/snapshot-meta
    │
    ▼
SnapshotMetaController::show()
    │
    ▼
GetSnapshotMetaUseCase::execute()
    │ SnapshotMetaModel::find(1)
    ▼
snapshot_meta テーブルから id=1 を取得
    │
    ├─ null の場合 → HTTP 404 {"error":"Not found"}
    │
    ▼
GetSnapshotMetaResponse（period, snapshotAt）
    │ snapshot_at を UTC → JST に変換 + ATOM 形式
    ▼
HTTP 200 {"period":"2026-Q2","snapshotAt":"2026-07-20T21:35:00+09:00"}
```

---

## 6. DB テーブル参照

### muni_amount（読み取り元）

```sql
CREATE TABLE muni_amount (
  muni_code          CHAR(5)      NOT NULL,
  type               VARCHAR(50)  NOT NULL,
  price_category     VARCHAR(50)  NOT NULL,
  avg_trade_price    BIGINT,
  median_trade_price BIGINT,
  txn_count          INTEGER      NOT NULL,
  period             VARCHAR(20)  NOT NULL,
  updated_at         TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
  PRIMARY KEY (muni_code, type, price_category, period)
);
CREATE INDEX idx_muni_amount_lookup ON muni_amount (type, price_category, period);
```

### snapshot_meta（読み取り元）

```sql
CREATE TABLE snapshot_meta (
  id          SMALLINT    PRIMARY KEY DEFAULT 1,
  period      TEXT        NOT NULL,
  snapshot_at TIMESTAMPTZ NOT NULL
);
```

常に id=1 の1行のみ運用。取込バッチ完了時に UPSERT される。

---

## 7. CORS・認証

### CORS

`config/cors.php` は未作成。Laravel 11 はデフォルトで全オリジンを許可するため、
ConoHa WING（同一ドメイン）ではそのまま動作する。

ローカル開発（localhost:3000 ↔ localhost:8000）で問題が出る場合のみ
`config/cors.php` を追加して `allowed_origins` に `http://localhost:3000` を追記する。

### 認証

これらのエンドポイントは認証不要（公開 API）。
reinfolib API キーはバックエンド内にのみ存在し、これらのレスポンスには含まれない。

---

## 8. 動作確認コマンド

```bash
# オートロード再生成（ファイル追加後に必要）
cd apps/api && composer dump-autoload

# ルート一覧確認
php artisan route:list --path=api/muni

# ローカルサーバー起動
php artisan serve

# amounts エンドポイント確認（宅地(土地と建物) × 取引価格）
curl -s "http://localhost:8000/api/muni/amounts?type=%E5%AE%85%E5%9C%B0%28%E5%9C%9F%E5%9C%B0%E3%81%A8%E5%BB%BA%E7%89%A9%29&priceCategory=%E5%8F%96%E5%BC%95%E4%BE%A1%E6%A0%BC" | jq '.[0]'
# 期待: {"muniCode":"XXXXX","avgTradePrice":...,"medianTradePrice":...,"latestCount":...}

# snapshot-meta エンドポイント確認
curl -s "http://localhost:8000/api/muni/snapshot-meta" | jq
# 期待: {"period":"2026-Q2","snapshotAt":"2026-07-20T...+09:00"}
# データなし時: HTTP 404 {"error":"Not found"}
```

---

## 9. フェーズ2 残タスクとの関連

本ドキュメント（項目7）は **API エンドポイント層のみ**を対象とする。
以下のタスクは別途対応予定：

| 項目 | 内容 | 依存 |
|---|---|---|
| 項目8 | データ取込バッチ実装（§5.1） | 本実装の `muni_amount` テーブルへの書き込み |
| 項目9 | 更新検知ポーリング（§5.0） | バッチ実装完了後 |
| 項目10 | Cron ジョブ更新（月次 → 週次） | ポーリング実装完了後 |
| フェーズ3 | フロント統合・E2E テスト | 本 API の本番稼働後 |
