# Bird API - 実装進捗レポート

## ✅ Phase 1-C: データベーススキーマとマイグレーション（完了）

### 実装完了項目
1. ✅ `muni_amount` テーブル作成
2. ✅ `muni_txn_staging` テーブル作成（UNLOGGED）
3. ✅ `snapshot_meta` テーブル作成
4. ✅ `muni_amount_snapshot` マテリアライズドビュー作成
5. ✅ テストデータシーダー実装（126件のレコード）

### データ確認
```bash
cd apps/api
php artisan tinker

# データ確認
DB::table('muni_amount')->count();
# => 126

DB::table('muni_amount')->first();
# 千代田区のデータなどが返される

DB::table('snapshot_meta')->first();
# period: "2026-Q2", snapshot_at: "2026-07-19 ..."
```

---

## 🔄 Phase 2-A: `/api/muni/amounts` エンドポイント（次の実装）

### 実装予定の構成

#### 1. Domain層（ドメイン層）
```
src/Domain/MunicipalityAmount/
├── Entity/
│   └── MunicipalityAmount.php
├── ValueObject/
│   ├── MunicipalityCode.php
│   ├── DataType.php
│   ├── PriceCategory.php
│   └── Period.php
└── Repository/
    └── MunicipalityAmountRepositoryInterface.php
```

#### 2. Infrastructure層（インフラ層）
```
src/Infrastructure/Persistence/
├── Eloquent/
│   └── MunicipalityAmountModel.php
└── Repository/
    └── EloquentMunicipalityAmountRepository.php
```

#### 3. Application層（アプリケーション層）
```
src/Application/UseCase/GetMunicipalityAmounts/
├── GetMunicipalityAmountsUseCase.php
├── GetMunicipalityAmountsRequest.php
└── GetMunicipalityAmountsResponse.php
```

#### 4. Presentation層（プレゼンテーション層）
```
src/Presentation/Http/
├── Controllers/
│   └── MunicipalityAmountController.php
├── Requests/
│   └── GetMunicipalityAmountsRequest.php
└── Resources/
    └── MunicipalityAmountResource.php
```

### APIエンドポイント仕様

```
GET /api/muni/amounts?type={type}&priceCategory={priceCategory}

クエリパラメータ:
- type: データ種類（宅地(土地) / 宅地(土地と建物) / 中古マンション等）
- priceCategory: 価格区分（取引価格 / 成約価格）

レスポンス例:
{
  "data": [
    {
      "muniCode": "13101",
      "avgTradePrice": 50000000,
      "medianTradePrice": 45000000,
      "latestCount": 120
    },
    ...
  ],
  "meta": {
    "period": "2026-Q2",
    "snapshotAt": "2026-07-19T12:00:00+09:00"
  }
}
```

---

## 📋 次のステップ

### Step 1: DDD構造のディレクトリ作成
```bash
cd apps/api
mkdir -p src/Domain/MunicipalityAmount/{Entity,ValueObject,Repository}
mkdir -p src/Infrastructure/Persistence/{Eloquent,Repository}
mkdir -p src/Application/UseCase/GetMunicipalityAmounts
mkdir -p src/Presentation/Http/{Controllers,Requests,Resources}
```

### Step 2: composer.json の更新
DDDレイヤーのオートロード設定を追加（既に設定済み）

### Step 3: Domain層の実装
1. Value Object の実装（MunicipalityCode, DataType, PriceCategory）
2. Entity の実装（MunicipalityAmount）
3. Repository Interface の定義

### Step 4: Infrastructure層の実装
1. Eloquent Model の実装
2. Repository の実装

### Step 5: Application層の実装
1. UseCase の実装
2. Request/Response DTO の実装

### Step 6: Presentation層の実装
1. Controller の実装
2. Form Request の実装
3. API Resource の実装

### Step 7: ルーティング設定
`routes/api.php` にエンドポイントを追加

### Step 8: 動作確認
```bash
php artisan serve
curl "http://localhost:8000/api/muni/amounts?type=宅地(土地と建物)&priceCategory=取引価格"
```

---

## 🎯 目標

Phase 2-A完了後、以下が実現されます：
- ✅ DDDアーキテクチャに基づいた実装
- ✅ `/api/muni/amounts` エンドポイントの動作
- ✅ テストデータを使った動作確認
- ✅ JSON形式のレスポンス取得

実装時間の目安: 30-45分
