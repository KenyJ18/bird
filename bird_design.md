# bird 地図ハイライト画面 設計ドキュメント

## 0. 概要

`frontend/src/components/EnterCheck/index.tsx` の `handleNext` 処理から、地図を表示する画面へ遷移する。遷移前に確定した予算（`budget`）以下の地域を、不動産取引価格の平均額／中央値をもとにハイライトする。**対象範囲は1都3県（東京都・神奈川県・埼玉県・千葉県）で、島嶼部を除く。**

本ドキュメントは、これまでの検討で確定した仕様・参照リソース・実装方針をまとめたものである。

## 1. 確定した要件

| 項目 | 決定内容 |
| --- | --- |
| 対象範囲（データ・地図） | **1都3県（東京都・神奈川県・埼玉県・千葉県）／島嶼部を除く** |
| 地図表示範囲 | **1都3県にズーム表示**（全国表示はしない） |
| ハイライト粒度 | **市区町村単位**（1都3県・島嶼部除きで約200面） |
| 比較に使う金額 | 不動産情報ライブラリ（reinfolib）の**市区町村ごとの取引価格の統計値** |
| 金額の一次ソース | 不動産情報ライブラリ API（https://www.reinfolib.mlit.go.jp） |
| データ種類の扱い | **ユーザーがデータ種類（Type）を選択**（「全種類」合算も選択可） |
| 価格区分 | **取引価格 / 成約価格をユーザーが選択** |
| 統計指標 | **平均 / 中央値をユーザーが選択**（両方をDBに保持し併記） |
| ハイライト判定 | 選択中の「データ種類 × 価格区分 × 統計指標」の値が `budget` 以下 |
| 集計対象期間 | **直近四半期分**（ハイライト判定に使う値） |
| データ更新頻度 | **reinfolibの四半期更新を検知して**自DBを四半期更新（日次実行はしない） |
| バッチトリガ | **更新検知ポーリング（週1回）** で新四半期を検知 → フル取込を1回実行 |
| 検知プローブ | **1都3県の各都道府県（東京13・神奈川14・埼玉11・千葉12）** を area で照会、1つでもデータありで公開判定 |
| 取込ジョブ実行時間帯 | **21:00〜翌08:00 に完了する想定**（オフピーク・リトライ余裕を確保） |
| 履歴保持期間 | **1年間（直近4四半期）** |
| 配信時の基準 | 四半期取込完了時に**スナップショットを1回固定**。基準時刻＝取込完了日時 |
| 基準時刻の表示 | 画面に「◯年◯月◯日時点（◯◯四半期データ）」を表示（JST） |
| budget の受け渡し | ルーターの state / クエリ |
| 地図描画 | 地図ライブラリ利用（MapLibre GL JS を推奨） |

### 補足：更新頻度（A案）
reinfolib の取引価格情報は四半期ごと（年4回）の更新で、公開日は固定カレンダー化されておらず、更新の都度トップの「お知らせ」／報道発表で告知される。各四半期データは当該四半期終了後おおむね数か月遅れで追加される。

したがって**日次バッチは行わない**。週1回程度の軽量ポーリングで新四半期データの出現を検知し、**検知した時のみ**フル取込・集計・スナップショット固定を実行する。スナップショットは四半期取込ごとに1回固定し、その完了日時を配信の基準時刻とする。

## 2. 参照が必要な外部リソース

### 2.1 金額データ（一次ソース）：不動産情報ライブラリ API

| API | 用途 | 備考 |
| --- | --- | --- |
| **XIT001** 不動産価格（取引価格・成約価格）情報取得API | 市区町村×直近四半期の**個別取引**を取得し、統計値を自前集計 | エンドポイント `https://www.reinfolib.mlit.go.jp/ex-api/external/XIT001` |
| **XIT002** 都道府県内市区町村一覧取得API | 全市区町村コードの列挙（バッチのループキー） | — |

**XIT001 主なパラメータ**

- `year`（必須）：取引年（YYYY）
- `quarter`：四半期（1〜4）
- `area`：都道府県コード（2桁）
- `city`：市区町村コード（5桁）
- `priceClassification`：価格情報区分（01=取引価格 / 02=成約価格 / 未指定=両方）
- ※ `area` / `city` / `station` のいずれか1つ以上が必須

**XIT001 主な出力フィールド**

- `Type`：取引の種類（宅地(土地) / 宅地(土地と建物) / 中古マンション等 / 農地 / 林地）
- `TradePrice`：取引価格（総額）※文字列
- `MunicipalityCode`：市区町村コード（5桁）
- `Prefecture` / `Municipality`：都道府県名 / 市区町村名
- `Period`：取引時点（例 2015年第2四半期）
- `PriceCategory`：価格情報区分（不動産取引価格情報 / 成約価格情報）

**利用上の制約（重要）**

- APIキーが必要（`Ocp-Apim-Subscription-Key` ヘッダ）。**利用申請・承認制**。
- 公式が「**ブラウザから直接呼ばないこと（CORSエラー）**」と明記 → **必ずバックエンド経由で呼び出す**。APIキー秘匿の観点でも必須。
- レスポンスは **gzip** エンコード。
- 「市区町村ごとの統計値」はAPIが直接返さない → 個別取引の `TradePrice` を市区町村×`Type`×価格区分で**自前集計**する必要がある。
- 連続実行は非推奨（アクセス制限あり）。バッチは間隔を空けて実行する。

### 2.2 地図データ（描画用）

- **市区町村境界データ**：国土数値情報「行政区域データ（N03）」の市区町村ポリゴン、または geolonia 系の市区町村GeoJSON。**1都3県（島嶼部除く）に絞り込んで使用**。
- 対象は約200面と小規模なため、**単純なGeoJSON（TopoJSON簡素化）でも描画負荷は問題になりにくい**。ベクタータイル化は必須ではない。
- 初期表示は1都3県の範囲に `fitBounds` でズームする。

### 2.3 地図ライブラリ／タイル（採用パッケージ）

- **react-map-gl（v8, `react-map-gl/maplibre`）＋ maplibre-gl（v4/5）** を採用。React（**v19 想定**）から MapLibre を扱う。TypeScript 型同梱・Mapbox トークン不要・無料。
  ```bash
  npm i react-map-gl maplibre-gl
  npm i -D topojson-server topojson-client   # 境界データ軽量化用
  ```
- **背景地図（実地図タイルを下敷き）**：**地理院タイル**（標準地図/淡色地図）を basemap に使用。街路・地名の上に市区町村ポリゴンを重ねる。地理院タイルの帰属表示（クレジット）を地図内に表示。
- **市区町村ポリゴン**：GeoJSON/TopoJSON を fill レイヤーとして重ね、`fill-color` 式で5段階に塗り分け。半透明にして下敷きの地図を透かす。
- 主な機能対応：
  - 塗り分け：`fill-color` の `match`/`feature-state` 式で `classify()` の tier → 色をマッピング（§7.3）。
  - 件数併記（§7.4）：`<Popup>` で市区町村クリック時に統計値＋件数を表示。
  - 1都3県ズーム：初期表示で `fitBounds`（§2.2）。

塗り分け式の例：
```ts
'fill-color': [
  'match', ['feature-state', 'tier'],
  'within',    '#4FA97E',  // 予算以下
  'orange',    '#E08A3C',  // 予算+1000万円以内
  'red',       '#D9534F',  // 予算+1000万円超
  'lightGrey', '#D3D1C7',  // 直近四半期0件
  /* default */ '#B4B2A9'  // データなし
]
```

### 2.4 結合キー・対象範囲

- **全国地方公共団体コード（5桁）**：XIT001 の `MunicipalityCode` と、地図境界データの市区町村コードを一致させる。
- **対象都道府県コード**：埼玉(11)・千葉(12)・東京(13)・神奈川(14)。
- **島嶼部除外（東京都の9町村）**：大島町(13361)・利島村(13362)・新島村(13363)・神津島村(13364)・三宅村(13381)・御蔵島村(13382)・八丈町(13401)・青ヶ島村(13402)・小笠原村(13421)。神奈川・埼玉・千葉に島嶼市区町村はない。この除外リストは境界データ・データ取得・集計の全てに適用する。

## 3. アーキテクチャ / データフロー

```
[更新検知ポーリング：週1回]
  1都3県の各都道府県(area=11,12,13,14)に「次の四半期(year/quarter)」の
  新データが出現したかを軽量チェック
     → いずれか1つでもデータありなら取込ジョブを起動（全て404なら何もしない）

[取込ジョブ：四半期に1回・検知時のみ]
  XIT002 で1都3県(11,12,13,14)の市区町村コードを列挙 → 島嶼部9町村を除外
     → 各市区町村を XIT001（直近四半期・取引価格/成約価格）で走査
        ※対象約200件を間隔を空けて順次取得（レート制御・リトライ）
     → gzip解凍・パース → ステージングテーブルへ投入
     → 市区町村 × データ種類(Type) × 価格区分 ごとに
        平均・中央値・件数を算出 → 集計テーブル(muni_amount) を upsert
     → 履歴は直近4四半期のみ保持（それ以前を削除）
     → マテビューを1回リフレッシュしスナップショット固定（基準時刻＝完了日時）

[実行時]
  [EnterCheck.handleNext] --budget(router state)--> [Map画面]
     → セレクタ（データ種類 / 価格区分 / 統計指標）を選択
     → 自前API GET /api/muni/amounts?type=&priceCategory= を取得
     → 選択統計値 <= budget の市区町村をハイライト
     → 直近四半期0件は薄いグレー、全期間0件はグレーアウト
```

## 4. データベース設計（PostgreSQL・集計結果のみ永続化）

平均計算はアプリ内メモリよりPostgreSQL側で行うほうが正確・堅牢なため、**一時ステージングテーブルで集計 → 結果だけを永続テーブルに upsert → ステージングを破棄**する。永続スキーマは集計結果のみ。

### 4.1 永続テーブル（集計結果）

```sql
CREATE TABLE muni_amount (
  muni_code          char(5)     NOT NULL,   -- 全国地方公共団体コード（結合キー）
  type               text        NOT NULL,   -- データ種類（3種のみ：宅地(土地)/宅地(土地と建物)/中古マンション等）
  price_category     text        NOT NULL,   -- '取引価格' / '成約価格'
  avg_trade_price    bigint,                 -- 平均（0件時 NULL）
  median_trade_price bigint,                 -- 中央値（0件時 NULL）
  txn_count          integer     NOT NULL,   -- その四半期のヒット件数
  period             text        NOT NULL,   -- 集計対象四半期 例 '2026-Q1'
  updated_at         timestamptz NOT NULL DEFAULT now(),
  PRIMARY KEY (muni_code, type, price_category, period)
);
CREATE INDEX idx_muni_amount_lookup ON muni_amount (type, price_category, period);
```

### 4.2 ステージングテーブル（実行ごとにTRUNCATE、永続しない）

```sql
CREATE UNLOGGED TABLE muni_txn_staging (
  muni_code      char(5),
  type           text,
  price_category text,
  trade_price    bigint,   -- API は文字列なので取込時に数値化
  period         text
);
```

`UNLOGGED` はWALを書かず高速。四半期バッチの間だけ使い、都度TRUNCATEする。

## 5. バッチ処理仕様

### 5.0 更新検知ポーリング（トリガ）

reinfolib の取引価格情報は四半期ごと・不定日更新（固定カレンダーなし）のため、日次実行はしない。週1回程度の軽量ポーリングで新四半期データの出現を検知し、検知時のみ取込ジョブ（§5.1）を起動する。

**検知の前提（XIT001 の 404 の意味）**

XIT001（タイル指定なし）はデータが無いと HTTP 404 を返す。ただし 404 は「四半期が未公開」か「その市区町村がその四半期に取引0件」かを区別しない。取引の少ない市区町村をプローブにすると、公開済みでも0件404で「未公開」と誤検知（false negative）する。これを避けるため、**都道府県単位（`area` パラメータ）でプローブする**。都道府県全体で取引0件はまず起きないため、404=未公開 が信頼できる。

**検知ロジック（確定）**

- **ポーリング頻度**：週1回。
- **プローブ粒度**：都道府県単位（`area`）。
- **プローブ対象**：対象範囲である**1都3県の各都道府県＝埼玉(11)・千葉(12)・東京(13)・神奈川(14)の4件**を照会する。
- **対象四半期**：`snapshot_meta.period` の次四半期（`year`/`quarter`）。
- **判定条件**：4件のうち**いずれか1つでも200（データあり）**なら「公開済み」とみなし取込ジョブを起動。全て404なら次回ポーリングまで待機。
- **二重取込防止**：取込済み四半期は `snapshot_meta.period` で記録し、同一四半期の再取込をスキップ。

**部分公開への保険（段階公開対策）**

「1つでもあれば」判定は最速だが、reinfolib が地域を分けて段階公開した場合、未公開の県は取込時に404（0件）となり、その市区町村が次四半期までグレーアウトのまま残る恐れがある（実際は全国一括公開が通例のためリスクは低い）。保険として、取込時に404だった県を記録し、**次回ポーリングで穴埋め再取込（冪等upsert）**する。1都3県が揃った時点で当該四半期を「完了」とする。

### 5.1 取込ジョブ 処理フロー（検知時のみ・四半期に1回）

1. `TRUNCATE muni_txn_staging;`
2. XIT002 で1都3県（11,12,13,14）の市区町村コードを列挙し、**島嶼部9町村（§2.4）を除外**（対象約200件）。
3. 各市区町村を XIT001（直近四半期）で、取引価格・成約価格の両方について呼び出し → gzip解凍・パース。
4. `TradePrice` が数値として妥当なレコードのみ `muni_txn_staging` へ一括投入（`COPY` 推奨）。無効・空値は除外。`price_category` は `PriceCategory` から設定。**対象3種（宅地(土地) / 宅地(土地と建物) / 中古マンション等）以外の `Type`（農地・林地等）はこの時点で除外**（案X）。
5. 集計＋upsert（§5.2）を実行。
6. 履歴クリーンアップ（§5.3）を実行。
7. スナップショット固定（§6.1）：マテビューを1回リフレッシュし、`snapshot_meta` に基準時刻・対象四半期を記録。
8. `TRUNCATE muni_txn_staging;`

レート配慮：reinfolib は連続実行非推奨のため、対象約200件の取得はリクエスト間に間隔を設け、リトライ／指数バックオフ・429時の待機を入れる。取込ジョブが失敗した場合は `muni_amount`・スナップショットを更新せず、前回値を継続配信する（可用性優先）。

**実行時間帯・所要時間の見込み**

- **実行時間帯**：週1回のポーリングで新四半期を検知したら、**次の 21:00〜翌08:00 の窓**で取込ジョブを実行し、この窓内で完了させる。
- **所要時間**：XIT001 は市区町村×四半期の全取引を1レスポンスで返す（ページングなし）ため、リクエスト数は概ね200〜400件（価格区分の取得方法次第）。1件あたり数秒間隔＋リトライでも合計は十数分〜1時間以内の見込みで、約11時間の窓に対し余裕は大きい（正確な所要時間は実測で確認）。
- スナップショット固定（§6.1）も窓内で実施し、基準時刻＝取込完了日時を記録する。

### 5.2 集計クエリ（平均＋中央値・価格区分別）※案X：3種のみ

ステージングには対象3種（宅地(土地) / 宅地(土地と建物) / 中古マンション等）のみが入っている前提（§5.1 ステップ4で除外）。データ種類×価格区分ごとに平均・中央値・件数を集計する。「全種類（合算）」の集計・保存は行わない（案X）。

```sql
INSERT INTO muni_amount
  (muni_code, type, price_category, avg_trade_price, median_trade_price, txn_count, period, updated_at)
SELECT
  muni_code,
  type,
  price_category,
  round(avg(trade_price))::bigint                                          AS avg_trade_price,
  round(percentile_cont(0.5) WITHIN GROUP (ORDER BY trade_price))::bigint  AS median_trade_price,
  count(*)                                                                 AS txn_count,
  period,
  now()
FROM muni_txn_staging
WHERE trade_price > 0
GROUP BY muni_code, type, price_category, period
ON CONFLICT (muni_code, type, price_category, period) DO UPDATE
  SET avg_trade_price    = EXCLUDED.avg_trade_price,
      median_trade_price = EXCLUDED.median_trade_price,
      txn_count          = EXCLUDED.txn_count,
      updated_at         = EXCLUDED.updated_at;
```

### 5.3 履歴保持（1年 = 直近4四半期）

```sql
DELETE FROM muni_amount
WHERE period NOT IN (
  SELECT period FROM (
    SELECT DISTINCT period FROM muni_amount ORDER BY period DESC LIMIT 4
  ) t
);
```

## 6. 配信（四半期スナップショット）と API

### 6.1 マテリアライズドビュー（四半期取込ごとに1回リフレッシュ）

`muni_amount` の更新は四半期（不定日）なので、スナップショットも**取込ジョブ完了時に1回だけ**固定する。日次リフレッシュは行わない。基準時刻・対象四半期は `snapshot_meta` に記録して画面表示に使う。

```sql
-- 配信用スナップショット（最新四半期）
CREATE MATERIALIZED VIEW muni_amount_snapshot AS
SELECT muni_code, type, price_category, avg_trade_price, median_trade_price, txn_count, period
FROM muni_amount
WHERE period = (SELECT max(period) FROM muni_amount);

CREATE UNIQUE INDEX idx_muni_snapshot_pk
  ON muni_amount_snapshot (muni_code, type, price_category);

-- スナップショットのメタ情報（基準時刻・対象四半期）1行運用
CREATE TABLE snapshot_meta (
  id          smallint    PRIMARY KEY DEFAULT 1,  -- 常に1行
  period      text        NOT NULL,               -- 例 '2026-Q1'
  snapshot_at timestamptz NOT NULL                -- 取込完了日時（基準時刻・JST表示）
);

-- 取込ジョブ完了時（§5.1 ステップ7）に実行：
-- REFRESH MATERIALIZED VIEW CONCURRENTLY muni_amount_snapshot;
-- INSERT INTO snapshot_meta (id, period, snapshot_at) VALUES (1, :period, now())
--   ON CONFLICT (id) DO UPDATE SET period = EXCLUDED.period, snapshot_at = EXCLUDED.snapshot_at;
```

リフレッシュ失敗時は前回のマテビュー・`snapshot_meta` をそのまま継続配信する（可用性優先）。

### 6.2 配信API（グレーアウト2段階対応）

```
GET /api/muni/amounts?type={データ種類}&priceCategory={取引価格|成約価格}
```

```sql
WITH latest AS (SELECT max(period) AS p FROM muni_amount)
SELECT
  h.muni_code,
  la.avg_trade_price,
  la.median_trade_price,
  COALESCE(la.txn_count, 0) AS latest_count
FROM (
  -- 保持期間(1年)内に一度でも取引がある市区町村
  SELECT DISTINCT muni_code
  FROM muni_amount
  WHERE type = $1 AND price_category = $2
) h
LEFT JOIN muni_amount la
  ON la.muni_code = h.muni_code
 AND la.type = $1 AND la.price_category = $2
 AND la.period = (SELECT p FROM latest);
```

- APIキーはバックエンド内で保持し、フロントには一切露出しない。フロントはこの自前APIのみを叩く。
- 平均・中央値の両方を返し、フロントの統計指標セレクタで使う列を選ぶ。

基準時刻表示用に、`snapshot_meta` を返すエンドポイントも用意する（画面の「◯年◯月◯日時点（◯◯四半期データ）」表示に使用）。

```
GET /api/muni/snapshot-meta
→ { "period": "2026-Q1", "snapshotAt": "2026-06-xxT12:00:00+09:00" }
```

## 7. フロントエンド実装

### 7.1 遷移元：EnterCheck の handleNext

```tsx
const navigate = useNavigate();

const handleNext = () => {
  // 既存のバリデーション等
  navigate("/map", { state: { budget } });
};
```

### 7.2 セレクタ定義（選択肢・並び順・初期値）

3つのセレクタは以下で確定。各セレクタの並び順はコード昇順、初期値は太字。

**データ種類**（`type`）

| コード | ラベル | reinfolib Type 値 |
| --- | --- | --- |
| 01 | 宅地(土地) | 宅地(土地) |
| **02** | **宅地(土地と建物)**（初期値） | 宅地(土地と建物) |
| 03 | 中古マンション等 | 中古マンション等 |

**価格情報区分**（`priceCategory`）

| コード | ラベル | reinfolib priceClassification |
| --- | --- | --- |
| **01** | **取引価格**（初期値） | 01 |
| 02 | 成約価格 | 02 |

**統計指標**（`stat`）

| コード | ラベル | 参照カラム |
| --- | --- | --- |
| **01** | **平均価格**（初期値） | `avg_trade_price` |
| 02 | 中央値 | `median_trade_price` |

- セレクタのコードは画面内部値。API・DBへは対応する reinfolib Type 値／`price_category`／参照カラムにマッピングする。
- 農地・林地・「全種類（合算）」はセレクタに含めない（下記§7.2補足）。

### 7.2.1 遷移先：Map 画面（3セレクタ + ハイライト判定）

```tsx
const { state } = useLocation();
const budget = state?.budget;                        // 直リロード時のフォールバック要

const [type, setType] = useState("宅地(土地と建物)");   // 初期値 02
const [priceCategory, setPriceCategory] = useState("取引価格"); // 初期値 01
const [stat, setStat] = useState("平均");              // 初期値 01（平均価格）

const { data } = useSWR(
  `/api/muni/amounts?type=${encodeURIComponent(type)}&priceCategory=${encodeURIComponent(priceCategory)}`
);
const byMuni = new Map(data?.map((r) => [r.muniCode, r]));

const OVER = 10_000_000; // 予算からの許容上振れ = 1000万円

function classify(muniCode) {
  const r = byMuni.get(muniCode);
  if (!r) return "greyout";                    // 全期間(1年)0件 → グレーアウト
  if (r.latestCount === 0) return "lightGrey"; // 直近四半期0件 → 薄いグレー
  const value = stat === "平均" ? r.avgTradePrice : r.medianTradePrice;
  if (value == null) return "lightGrey";
  if (value <= budget) return "within";           // 予算以下 → 緑
  if (value <= budget + OVER) return "orange";    // 予算+1000万円以内 → オレンジ
  return "red";                                   // 予算+1000万円超 → 赤
}
// MapLibre: 市区町村ベクターレイヤーの fill-color を classify() の結果で制御
```

**§7.2 補足：セレクタから外した種類の扱い（案X確定）**

データ種類セレクタは3種（宅地(土地)／宅地(土地と建物)／中古マンション等）のみとし、農地・林地・「全種類（合算）」は表示しない。集計バッチも**案X＝3種のみ集計**で確定（§5.1 ステップ4で3種以外を除外、§5.2 は3種のみ集計、全種類合算クエリは廃止）。DB容量・処理を削減する。

### 7.3 色分け仕様

選択統計値（平均 or 中央値）を `V`、予算を `budget`、許容上振れ `OVER = 1000万円` とする。

| 状態 | 条件 | 表示 |
| --- | --- | --- |
| 予算以下 | `latest_count > 0` かつ `V ≤ budget` | **緑**（色は調整可） |
| 予算+1000万円以内 | `latest_count > 0` かつ `budget < V ≤ budget + 1000万` | **オレンジ** |
| 予算+1000万円超 | `latest_count > 0` かつ `V > budget + 1000万` | **赤** |
| 直近四半期0件 | レスポンスに存在するが `latest_count = 0`（値はNULL） | **薄いグレー** |
| 全期間0件 | レスポンスに市区町村が存在しない（1年間ヒットなし） | **グレーアウト** |

### 7.4 少数母数（統計信頼性）の扱い

件数が少ない市区町村を閾値で除外することはしない。代わりに**件数（母数）を画面に併記**し、統計値の信頼性は利用者の判断に委ねる。

- ハイライト判定は件数に関わらず選択統計値で行う（`classify()` は変更なし）。
- 各市区町村の**件数（`latest_count`）を画面に表示**する（例：市区町村クリック時のポップアップ／ツールチップに「中古マンション等 取引価格 中央値 ◯◯円（取引◯件）」のように併記）。
- 併記により、例えば「1件だけの中央値」であることを利用者が認識できる。

配信APIは既に `latest_count` を返しているため、追加のデータ取得は不要。

## 8. 実装上の注意点

1. **対象範囲の絞り込み**：地図境界・データ取得・集計のすべてを1都3県（島嶼部除く・約200面）に限定する。境界データも1都3県ぶんに事前フィルタしておく。約200面のため描画負荷は軽く、単純GeoJSONで足りる。
2. **budget の消失対策**：`router state` は直リロードで消えるため、`budget` 未設定時は入力画面へ戻す等のフォールバックを用意する。
3. **APIキーの秘匿**：reinfolib API は必ずバックエンド経由。フロントからの直接呼び出し・キー埋め込みは禁止。
4. **数値化**：`TradePrice` は文字列。ステージング投入時に数値へ変換し、空値・不正値は除外。
5. **利用規約**：reinfolib 利用規約・API利用規約（§8.5）、地図タイルの帰属表示（地理院タイル/OSM）を遵守する。

### 8.5 reinfolib 利用規約の遵守事項

利用規約（本体）・API利用規約を精読した結果、**設計自体に違反はない**（データの取得・保存・加工・自前API配信はPDL1.0／API利用規約第1条(3)で許容）。ただし以下の表示・運用義務を実装に含める必要がある。

| # | 義務 | 根拠 | 実装 |
| --- | --- | --- | --- |
| 1 | 出典表示＋編集・加工クレジット | PDL1.0 重要情報(1) | 画面に「出典：国土交通省 不動産情報ライブラリ」＋「『不動産取引価格情報／成約価格情報』（国土交通省）をもとに bird が作成」。平均・中央値は bird の算出値であり、**国が作成したかのような表示は禁止** |
| 2 | 指定クレジット（免責文）の表示 | API利用規約 第7条 | エンドユーザが参照できる場所に固定文言を表示：「このサービスは、国土交通省の不動産情報ライブラリのAPI機能を使用していますが、提供情報の最新性、正確性、完全性等が保証されたものではありません」 |
| 3 | 運営主体・責任範囲の明示 | API利用規約 第8条(1) | アプリは bird が開発・運営し責任を負う旨、bird とエンドユーザの責任範囲をエンドユーザに明示 |
| 4 | アクセスログの記録・保存 | API利用規約 第8条(7) | reinfolib API 呼び出し（取込ジョブ・検知ポーリング）のアクセスログを記録・保存 |
| 5 | サービス名称の制限 | 利用規約 第5条(7) | 製品・サービス・アプリ名に「不動産情報ライブラリ」を使用しない（bird は問題なし。将来命名時に注意） |
| 6 | APIキーの利用申請・秘匿・適切管理 | API利用規約 第3条・第5条・第8条(2) | 組織単位で利用申請。キーはバックエンドで保持し第三者非公開（§8項目3と整合） |
| 7 | 運用妨害の禁止（レート配慮） | 利用規約 第6条⑦／API第9条④ | 週1回ポーリング・間隔取得・約200件（§5で設計済み） |
| 8 | 参考情報としての位置づけ | 利用規約 第3条(4)・第5条 | 重要事項説明等ではなく参考情報である旨を明示（免責文と併せて表示） |

補足：クレジット（#1・#2）と件数併記（§7.4）は、地図画面のヘッダ／フッタ、または市区町村ポップアップ内にまとめて表示するとよい。

## 8.6 画面レイアウト（検索結果画面）

上から順に以下を縦積みで配置する。

```
┌─────────────────────────────────────────────┐
│ ヘッダー：  bird            検索結果画面        │
├─────────────────────────────────────────────┤
│ ボディ上部：                                   │
│  ┌─予算（今回入力・表示のみ）─┐               │
│  データ種類▼  価格情報区分▼  統計指標▼  [条件反映] │
├─────────────────────────────────────────────┤
│ 地図：1都3県ズーム（市区町村ハイライト）         │
│   凡例：緑=予算以下／橙=予算+1000万以内／赤=超過／  │
│        薄グレー=直近0件／グレーアウト=データなし   │
│   （市区町村クリックで件数併記ポップアップ §7.4） │
├─────────────────────────────────────────────┤
│ 最下部：出典・加工クレジット・免責文・基準時刻     │
└─────────────────────────────────────────────┘
```

**構成要素**

- **ヘッダー**：左に「bird」、右に「検索結果画面」。
- **ボディ上部（条件バー）**：
  - 予算：前画面（EnterCheck）で入力した `budget` を表示（このバーでは読み取り専用）。
  - 検索条件セレクタ：データ種類 / 価格情報区分 / 統計指標（§7.2 のコード・初期値に従う）。
  - 「条件反映」ボタン：押下で選択条件を地図へ反映（`/api/muni/amounts?type=&priceCategory=` 再取得 → 統計指標に応じた列で `budget` と比較しハイライト再計算）。
- **地図**：条件バー直下に1都3県ズーム地図（MapLibre、`fitBounds`）。凡例5状態（緑=予算以下／橙=予算+1000万以内／赤=予算+1000万超／薄いグレー=直近四半期0件／グレーアウト=全期間0件、§7.3）。市区町村クリックで件数併記ポップアップ（§7.4）。
- **最下部（フッター）**：規約遵守表示（§8.5）をまとめて配置。
  - 基準時刻：「◯年◯月◯日 12:00時点（◯◯四半期）」（§6.1 `snapshot_meta`）。
  - 出典：「出典：国土交通省 不動産情報ライブラリ（URL）」。
  - 加工クレジット：「『不動産取引価格情報／成約価格情報』（国土交通省）をもとに bird が作成」。
  - 免責文（API利用規約 第7条）：固定文言を表示。

**前提・補足**

- 予算はこの画面では表示のみ。金額の再入力・変更は行わない（変更は前画面に戻る想定）。
- 「条件反映」を押すまで地図のハイライトは変わらない（セレクタ変更即時反映ではなくボタン適用方式）。

## 9. 未確定・要確認事項

- 取込ジョブの正確な所要時間（実行時間帯は21:00〜翌08:00で確定。所要時間は実測で確認）。
- ハイライト色・薄いグレー・グレーアウトの具体的な配色値、および件数併記の表示方法（ポップアップ/ツールチップ等）。
- **直近四半期に取引0件となる（島嶼部を除く）1都3県の市区町村が実在するかの確認**。APIキーが未申請のため未確定。キー発行後に XIT001 で1都3県の全市区町村を直近四半期で照会し確認する。
  - 参考：少数母数の扱い自体は「閾値で除外せず件数を画面併記」（§7.4）で確定済み。0件地域の実在有無に関わらず設計は成立する。

## 10. 参照

- XIT001 不動産価格情報取得API：https://www.reinfolib.mlit.go.jp/help/apiManual/xit001/
- API操作説明（一覧・利用方法）：https://www.reinfolib.mlit.go.jp/help/apiManual/
- 不動産取引価格情報提供制度（国土交通省）：https://www.mlit.go.jp/totikensangyo/totikensangyo_tk5_000069.html
- 不動産価格情報の検索・ダウンロード（reinfolib）：https://www.reinfolib.mlit.go.jp/realEstatePrices/
- 不動産情報ライブラリ 利用規約・API利用規約：https://www.reinfolib.mlit.go.jp/help/termsOfUse/
- 公共データ利用規約（PDL1.0）：https://www.digital.go.jp/resources/open_data/public_data_license_v1.0
