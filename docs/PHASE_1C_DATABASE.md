# Phase 1-C: データベーススキーマ実装ガイド

## 実装するテーブル

### 1. muni_amount（市区町村金額集計テーブル）
**用途**: 市区町村ごとの不動産取引価格の統計値を保存

```sql
CREATE TABLE muni_amount (
    muni_code          CHAR(5)         NOT NULL,  -- 市区町村コード
    type               VARCHAR(50)     NOT NULL,  -- データ種類
    price_category     VARCHAR(50)     NOT NULL,  -- 価格区分
    avg_trade_price    BIGINT,                    -- 平均価格
    median_trade_price BIGINT,                    -- 中央値
    txn_count          INTEGER         NOT NULL,  -- 取引件数
    period             VARCHAR(20)     NOT NULL,  -- 対象期間 (例: 2026-Q1)
    updated_at         TIMESTAMP WITH TIME ZONE NOT NULL DEFAULT NOW(),
    PRIMARY KEY (muni_code, type, price_category, period)
);

CREATE INDEX idx_muni_amount_lookup ON muni_amount (type, price_category, period);
```

### 2. muni_txn_staging（ステージングテーブル）
**用途**: バッチ処理中の一時データ保存（UNLOGGED）

```sql
CREATE UNLOGGED TABLE muni_txn_staging (
    muni_code      CHAR(5),
    type           VARCHAR(50),
    price_category VARCHAR(50),
    trade_price    BIGINT,
    period         VARCHAR(20)
);
```

### 3. snapshot_meta（スナップショットメタ情報）
**用途**: 配信基準時刻とスナップショット情報

```sql
CREATE TABLE snapshot_meta (
    id          SMALLINT    PRIMARY KEY DEFAULT 1,
    period      VARCHAR(20) NOT NULL,
    snapshot_at TIMESTAMP WITH TIME ZONE NOT NULL
);
```

### 4. muni_amount_snapshot（マテリアライズドビュー）
**用途**: 最新四半期データの高速配信

```sql
CREATE MATERIALIZED VIEW muni_amount_snapshot AS
SELECT muni_code, type, price_category, avg_trade_price, 
       median_trade_price, txn_count, period
FROM muni_amount
WHERE period = (SELECT MAX(period) FROM muni_amount);

CREATE UNIQUE INDEX idx_muni_snapshot_pk
ON muni_amount_snapshot (muni_code, type, price_category);
```

## Eloquentモデル

### MunicipalityAmountModel

```php
<?php

namespace Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class MunicipalityAmountModel extends Model
{
    protected $table = 'muni_amount';
    
    public $incrementing = false;
    protected $primaryKey = ['muni_code', 'type', 'price_category', 'period'];
    
    protected $fillable = [
        'muni_code',
        'type',
        'price_category',
        'avg_trade_price',
        'median_trade_price',
        'txn_count',
        'period',
    ];
    
    protected $casts = [
        'avg_trade_price' => 'integer',
        'median_trade_price' => 'integer',
        'txn_count' => 'integer',
        'updated_at' => 'datetime',
    ];
    
    public $timestamps = false;
}
```

### SnapshotMetaModel

```php
<?php

namespace Infrastructure\Persistence\Eloquent;

use Illuminate\Database\Eloquent\Model;

class SnapshotMetaModel extends Model
{
    protected $table = 'snapshot_meta';
    
    public $incrementing = false;
    protected $primaryKey = 'id';
    
    protected $fillable = [
        'id',
        'period',
        'snapshot_at',
    ];
    
    protected $casts = [
        'id' => 'integer',
        'snapshot_at' => 'datetime',
    ];
    
    public $timestamps = false;
}
```

## マイグレーション実装順序

1. ✅ `2026_07_19_000001_create_muni_amount_table.php`
2. ✅ `2026_07_19_000002_create_muni_txn_staging_table.php`
3. ✅ `2026_07_19_000003_create_snapshot_meta_table.php`
4. ✅ `2026_07_19_000004_create_muni_amount_snapshot_view.php`

## 初期データ

### snapshot_meta初期化

```sql
INSERT INTO snapshot_meta (id, period, snapshot_at) 
VALUES (1, '2026-Q2', NOW())
ON CONFLICT (id) DO NOTHING;
```

## テストデータ

開発用のダミーデータを作成し、API動作確認を行います。

```php
// database/seeders/MunicipalityAmountSeeder.php
DB::table('muni_amount')->insert([
    [
        'muni_code' => '13101',  // 東京都千代田区
        'type' => '宅地(土地と建物)',
        'price_category' => '取引価格',
        'avg_trade_price' => 50000000,
        'median_trade_price' => 45000000,
        'txn_count' => 120,
        'period' => '2026-Q2',
        'updated_at' => now(),
    ],
    // ... 他の市区町村
]);
```

## 動作確認SQL

```sql
-- データ確認
SELECT * FROM muni_amount LIMIT 10;

-- 統計確認
SELECT 
    type, 
    price_category, 
    COUNT(*) as municipality_count,
    SUM(txn_count) as total_transactions
FROM muni_amount 
WHERE period = '2026-Q2'
GROUP BY type, price_category;

-- スナップショット確認
SELECT * FROM muni_amount_snapshot LIMIT 10;

-- メタ情報確認
SELECT * FROM snapshot_meta;
```

## 次のステップ

Phase 1-C完了後、Phase 2-Aの実装に進みます：
1. Domain層のEntity/ValueObject作成
2. Repository Interface定義
3. Infrastructure層のRepository実装
4. Application層のUseCase実装
5. Presentation層のController/Resource実装
