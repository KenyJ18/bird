# フェーズ2 項目8（データ取込バッチ）着手前 — Reinfolib APIクライアント不整合の修正

**作成日**: 2026-08-23
**対象**: `ReinfolibApiClient` および関連する Value Object（`ReinfolibDataType` / `ReinfolibPriceCategory`）

---

## 1. 背景

フェーズ2 項目8（データ取込バッチの実装、`bird_design.md` §5.1）に着手する前に、
既存の Reinfolib API 連携コード（2026-07-19 コミット `f290a81`）を確認したところ、
実際の XIT001 API 仕様と3点の不整合が見つかった。これらはバッチ実装の土台となる部分のため、
先に修正した。

裏付けとして、reinfolib 公式APIマニュアル（`https://www.reinfolib.mlit.go.jp/help/apiManual/xit001/`）
を直接確認し、以下を確定した。

| 確認事項 | 実際の仕様 |
|---|---|
| 四半期を指定するパラメータ名 | `quarter`（1〜4の数字1桁） |
| 価格区分を絞り込むパラメータ | `priceClassification`（`"01"`=取引価格のみ / `"02"`=成約価格のみ / 未指定=両方） |
| レスポンスの `Type` フィールドの値 | `"宅地(土地と建物)"` のような**日本語ラベルそのもの** |
| レスポンスの `PriceCategory` フィールドの値 | `"不動産取引価格情報"` / `"成約価格情報"`（`priceClassification` の値とは別表記） |

---

## 2. 見つかった不整合（バグ）と修正内容

### ①クエリパラメータ名の誤り：`period` → `quarter`

APIに四半期を伝えるパラメータ名が `period` になっていたが、正しくは `quarter`。
API側はこの名前を認識しないため、四半期の絞り込みが効いていなかった。

```php
// 修正前
'query' => [
    'year'   => $period->year(),
    'period' => $period->quarter(),   // ← 誤ったパラメータ名。APIに無視される
    'area'   => $prefectureCode->value(),
],

// 修正後
'query' => [
    'year'                 => $period->year(),
    'quarter'              => $period->quarter(),
    'area'                 => $prefectureCode->value(),
    'priceClassification'  => $priceCategory->value(), // ②で追加
],
```

**例え**：レストランで「4番テーブルに水をください」と伝えるべきところを、
店員が知らない言葉（存在しないパラメータ名）で注文していたようなもの。
意図が伝わらず、意図しない範囲のデータが返っていた。

**修正ファイル**: `src/Infrastructure/ExternalApi/ReinfolibApiClient.php`

---

### ②価格区分の絞り込み漏れ：`priceClassification` を一切送っていなかった

Reinfolib のデータには「取引価格（実際の売買価格）」と「成約価格（アンケート回答による申告価格）」
の2種類があり、`priceClassification` パラメータで一方だけに絞り込める。しかし、
このパラメータが呼び出しに含まれておらず、**常に両方が混在した状態**でAPIから返っていた。

さらに、コード側は取得した全レコードに対して「これは呼び出し時に指定した区分（例：取引価格）だ」
と無条件にラベル付けしていたため、**実際は成約価格のレコードが「取引価格」として保存されうる**
データ汚染バグになっていた。

**修正内容**：
1. `priceClassification` パラメータを送信し、API側で絞り込む（①のコード参照）。
2. 念のため、レスポンスの `PriceCategory` フィールド（`"不動産取引価格情報"` 等）と
   期待値を照合し、一致しないレコードは破棄する二重チェックを追加。

```php
// 追加した検証（ReinfolibApiClient::parseResponse 内）
if (isset($item['PriceCategory']) && $item['PriceCategory'] !== $priceCategory->responseLabel()) {
    continue;
}
```

**例え**：「Aだけください」と頼まずに全部持ってこさせ、中身を確認しないまま
「これは全部Aです」という札を貼って保存していたようなもの。

**修正ファイル**:
- `src/Domain/Reinfolib/ValueObject/ReinfolibPriceCategory.php`
  - コード値を `'1'`/`'2'` → 実際のAPI仕様に合わせて `'01'`/`'02'` に変更
  - レスポンス側の期待ラベルを返す `responseLabel()` メソッドを追加
- `src/Infrastructure/ExternalApi/ReinfolibApiClient.php`

---

### ③データ種類の一致判定が成立しない比較になっていた（フィルタが常に不一致 → データが0件になる）

XIT001 にはデータ種類（宅地(土地)／宅地(土地と建物)／中古マンション等…）を絞り込む
クエリパラメータが存在しない。そのため、レスポンスに含まれる `Type` フィールド
（`"宅地(土地と建物)"` のような**日本語ラベル**）を見て、アプリ側でフィルタする必要がある。

ところが `ReinfolibDataType` という Value Object は、なぜか `'13'` `'14'` `'15'` という
**根拠不明の数字コード**を値として持っていた。そのため、

```php
// 修正前
if ($item['Type'] !== $dataType->value()) {  // '宅地(土地と建物)' !== '14' → 常に不一致
    continue; // ← 毎回ここでスキップされる
}
```

という比較になり、**日本語文字列と数字コードを比較しても絶対に一致しない**ため、
このフィルタを通過するレコードが1件も存在しない状態だった（＝欲しいデータが常に0件）。

**修正内容**：`ReinfolibDataType` が保持する値を、実際のAPIレスポンスと同じ日本語ラベル
（`'宅地(土地)'` 等）に変更。これにより `DataType`（アプリ内の値）とAPIレスポンスの
`Type` が同じ表記になり、単純な文字列比較で正しく判定できるようになった。
`fromDataType()` / `toDataType()` の変換ロジックも、コード変換表が不要になり簡素化された。

**例え**：「りんご」という答えを `1` という数字と比較して、「一致しない」と
機械的に判定していたようなもの。

**修正ファイル**: `src/Domain/Reinfolib/ValueObject/ReinfolibDataType.php`

---

## 3. 影響範囲・確認方法

- 上記3クラスの外部からの利用箇所は `FetchReinfolibTransactionsUseCase`（`fromDataType()` /
  `fromPriceCategory()` を呼ぶのみ）に限られており、シグネチャ変更は不要だった。
  値の中身（コード体系）のみを実仕様に合わせて変更している。
- `php -l` によるシンタックスチェックを実施。
- 実際のAPIには接続せず（APIキーの実使用・課金/レート制限を避けるため）、
  `php artisan tinker` 上で以下の変換ロジックのみを確認済み：
  - `DataType → ReinfolibDataType → DataType` の往復変換
  - `PriceCategory → ReinfolibPriceCategory → PriceCategory` の往復変換
  - `ReinfolibPriceCategory::value()`（`priceClassification` 送信値）と
    `responseLabel()`（レスポンス照合用ラベル）が期待通りの値を返すこと

```
dataType value: 宅地(土地と建物)
roundtrip: 宅地(土地と建物)
priceCategory value: 01
responseLabel: 不動産取引価格情報
roundtrip: 取引価格
```

- 実データでの最終確認は、項目8（データ取込バッチ）の実装後、
  APIキーを使った実接続テスト（`reinfolib:test` コマンド等）で行う。

---

## 4. フェーズ2 残タスクとの関連

→ 最新状況は §8「フェーズ2 残タスクとの関連（更新）」を参照。

---

## 5. 参照

- XIT001 不動産価格情報取得API：https://www.reinfolib.mlit.go.jp/help/apiManual/xit001/
- `bird_design.md` §2.1（Reinfolib API仕様）・§5.1（取込ジョブ処理フロー）

---

## 6. 追加修正：429（レート制限）時の待機時間

**追記日**: 2026-08-23

当初は `Retry-After` ヘッダがない場合の待機を「2秒 × 試行回数」の指数バックオフとしていたが、
ユーザー指示により固定60秒待機に変更した。

```php
private const RATE_LIMIT_WAIT_SECONDS = 60; // 429時、Retry-Afterヘッダがない場合の固定待機秒数

if ($statusCode === 429) {
    $retryAfter = (int) ($response->getHeaderLine('Retry-After') ?: self::RATE_LIMIT_WAIT_SECONDS);
    // ...
    sleep($retryAfter);
    continue;
}
```

- `Retry-After` ヘッダが返ってきた場合はその値を優先する。
- ヘッダがない場合は固定60秒待機してからリトライする（最大 `MAX_ATTEMPTS`＝3回まで）。

**修正ファイル**: `src/Infrastructure/ExternalApi/ReinfolibApiClient.php`

---

## 7. 項目8（データ取込バッチ）最終仕様

**完了日**: 2026-08-23

`bird_design.md` §5.1 に基づき、データ取込バッチ本体を実装した。実データでのAPI呼び出しは
行わず（APIキーの実使用・課金/レート制限を避けるため）、フェイクの `ReinfolibApiClientInterface`
実装に差し替えたローカルSQLite環境での結合テストで、集計・保存・履歴削除・スナップショット
更新・失敗時ロールバックの一連の動作を確認済み。

### 7.1 新規・変更ファイル一覧

| ファイルパス | 種別 | 役割 |
|---|---|---|
| `app/Console/Commands/UpdateMuniAmountsCommand.php` | 新規 | Artisanコマンド `app:update-muni-amounts` 本体。期間解決・トランザクション制御 |
| `src/Application/MuniAmountBatch/UseCase/ImportMuniAmountsUseCase.php` | 新規 | 取込バッチの中心ロジック（取得→集計→保存→履歴削除→スナップショット更新） |
| `src/Application/MuniAmountBatch/Dto/ImportMuniAmountsResult.php` | 新規 | ユースケースの実行結果DTO（保存件数・削除四半期一覧） |
| `src/Domain/MunicipalityAmount/Service/TradePriceAggregator.php` | 新規 | 平均・中央値（`percentile_cont(0.5)`と同じ連続分布方式）・件数の算出（純粋関数） |
| `src/Domain/MunicipalityAmount/Repository/MunicipalityAmountRepositoryInterface.php` | 修正 | `pruneHistory(int $keepLatestPeriods): array` を追加 |
| `src/Infrastructure/Repository/EloquentMunicipalityAmountRepository.php` | 修正 | `pruneHistory()` の実装追加（`period` の文字列降順ソートで直近N件を保持） |
| `src/Infrastructure/Models/SnapshotMetaModel.php` | 修正 | `$fillable` 未設定で `updateOrCreate` が `MassAssignmentException` になるバグを修正 |
| `src/Infrastructure/ExternalApi/ReinfolibApiClient.php` | 修正 | §2・§6 のクエリパラメータ修正＋リトライ・404/429ハンドリング追加 |
| `src/Domain/Reinfolib/ValueObject/ReinfolibDataType.php` | 修正 | §2 の日本語ラベル方式に変更 |
| `src/Domain/Reinfolib/ValueObject/ReinfolibPriceCategory.php` | 修正 | §2 のコード体系・`responseLabel()` 追加 |
| `app/Console/Commands/CheckReinfolibApiCommand.php` | 修正 | 診断用コマンドの同種パラメータ名バグ（`period`→`quarter`）を修正 |
| `tests/Unit/TradePriceAggregatorTest.php` | 新規 | 集計ロジックのユニットテスト（奇数/偶数件・1件・順序非依存・四捨五入・空配列例外） |

### 7.2 処理フロー（実装版）

```
php artisan app:update-muni-amounts [--period=YYYY-QN]
    │
    ▼
UpdateMuniAmountsCommand::resolveTargetPeriod()
    │ --period 指定時はそれを使用。省略時は snapshot_meta.period の次四半期
    │ （snapshot_meta が空＝初回実行時は現在の四半期）
    ▼
DB::beginTransaction()
    │
    ▼
ImportMuniAmountsUseCase::execute($period)
    │
    ├─ 3データ種類 × 2価格区分 = 6通りをループ
    │     │
    │     ├─ FetchReinfolibTransactionsUseCase::execute($period, $dataType, $priceCategory)
    │     │     └─ ReinfolibApiClient が1都3県(4都道府県)を area 単位で順次呼び出し
    │     │        （1都道府県の応答に全市区町村分が含まれるため、市区町村ごとの
    │     │        ループは行わない＝設計書§5.1の想定より少ないAPI呼び出し数で済む）
    │     │
    │     ├─ 市区町村コードごとにグルーピング、島嶼部9町村（§2.4）を除外
    │     ├─ TradePriceAggregator::aggregate() で平均・中央値・件数を算出
    │     ├─ MunicipalityAmountRepositoryInterface::saveMany() で upsert
    │     └─ 次の組み合わせまで0.5秒待機（レート配慮）
    │
    ├─ MunicipalityAmountRepositoryInterface::pruneHistory(4)
    │     └─ muni_amount から直近4四半期以外を削除（設計書§5.3）
    │
    └─ スナップショット固定（設計書§6.1）
          ├─ PostgreSQL利用時のみ REFRESH MATERIALIZED VIEW CONCURRENTLY
          │  （MySQL/SQLiteでは muni_amount_snapshot は通常のVIEWのためREFRESH不要）
          └─ SnapshotMetaModel::updateOrCreate(id=1, period, snapshot_at=now())
    │
    ▼
成功時: DB::commit()      失敗時: DB::rollBack()（前回データのまま配信を継続・可用性優先）
```

### 7.3 設計書からの実装上の判断・差分

| 論点 | 設計書（§5.1原案） | 実装 | 理由 |
|---|---|---|---|
| API呼び出し粒度 | 市区町村（`city`）単位でループ（約200〜400リクエスト） | 都道府県（`area`）単位でループ（4件 × 6通り = 最大24リクエスト） | `area` 指定だけで該当都道府県の全市区町村分がまとめて返るため、`city` ループは不要。リクエスト数を大幅削減でき、Reinfolibへの負荷・レート制限リスクも下がる（2026-07-19時点で既にこの方針で実装済みだった `ReinfolibApiClient` を踏襲） |
| ステージングテーブル | `muni_txn_staging` にCOPYしSQLで集計（PostgreSQL前提の `percentile_cont`） | ステージングテーブルは使わず、PHPメモリ上で市区町村ごとにグルーピングして集計 | 本番はMySQL、ローカル開発はSQLiteであり、いずれも `percentile_cont` を持たない。1回の取込対象は最大約200市区町村×数千件程度でメモリ集計が現実的な規模のため、DB非依存の実装とした（`muni_txn_staging` テーブル自体は将来デバッグ用に残置） |
| 404の扱い | 明記なし（§5.0のポーリングのみ404を「未公開」として扱う） | 取込時も404は「データなし」として空配列を返す（エラーにしない） | §5.0の「部分公開への保険」の考え方（一部都道府県が遅れて404になりうる）を取込時にも適用し、1都道府県だけ404でもバッチ全体を失敗させない。件数0はそのまま「その都道府県は対象四半期の取引が0件」として扱われる |
| リトライ | 「リトライ／指数バックオフ・429時の待機」と方針のみ記載 | 最大3回リトライ、通信エラー等は簡易指数バックオフ（2秒×試行回数）、429は固定60秒待機（`Retry-After`ヘッダがあれば優先） | 具体的な秒数を実装で確定（ユーザー指示により429は60秒固定） |

### 7.4 動作確認方法（実データ未使用）

実際のAPIキーを使わず、フェイクの `ReinfolibApiClientInterface` 実装に差し替えて
`php artisan tinker` 上で以下を確認済み：

1. 集計ロジック（`TradePriceAggregator`）：奇数/偶数件の中央値、1件のみ、入力順序非依存、
   四捨五入、空配列時の例外
2. 結合テスト：3データ種類×2価格区分＝6行が正しい値（avg/median/count）で保存されること
3. 島嶼部（例：大島町 13361）が結果に含まれず除外されること
4. `pruneHistory(4)`：5四半期分のダミーデータのうち最も古い1件だけが削除されること
5. `snapshot_meta` が `updateOrCreate` で正しく更新されること
   （`SnapshotMetaModel::$fillable` 未設定バグを発見・修正）
6. 集計途中で例外が発生した場合、`DB::rollBack()` によりその回の投入分が一切残らず、
   `snapshot_meta` も更新前の値のまま維持されること（可用性優先の要件を満たす）

実際のReinfolib APIキーを使った本番相当の疎通確認（HTTP応答・実データでの集計値検証）は、
本番環境またはAPIキーが利用可能な環境で `reinfolib:test` / `app:update-muni-amounts` を
実行して別途行うこと。

### 7.5 既知の未対応事項（次フェーズ以降）

- 部分公開（一部都道府県のみ404）が起きた場合の「次回ポーリングでの穴埋め再取込」は
  項目9（更新検知ポーリング）の実装範囲。項目8では404を「0件」として処理するのみで、
  自動的な再取込は行わない。
- Cronジョブの登録・更新（月次 → 週次ポーリング + 検知時取込）は項目10の範囲。
- 本番DB（MySQL）・実APIキーでの実行時間計測は未実施（`bird_design.md` §9.2 優先度: 低）。

## 8. フェーズ2 残タスクとの関連（更新）

| 項目 | 内容 | 状態 |
|---|---|---|
| 項目7 | バックエンドAPIエンドポイント実装 | 完了（`PHASE_2_ITEM7_BACKEND_API.md`） |
| クライアント修正 | Reinfolib APIクライアントの不整合修正（§1〜§6） | 完了 |
| 項目8 | データ取込バッチ実装（§5.1）：集計ロジック＋Artisanコマンド化 | 完了（§7） |
| 項目9 | 更新検知ポーリング（§5.0） | 未着手（次の作業） |
| 項目10 | Cronジョブ更新（月次 → 週次） | 未着手 |
