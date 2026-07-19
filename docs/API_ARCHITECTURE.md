# Bird API - アーキテクチャドキュメント

## プロジェクト構造

```
apps/api/
├── src/                           # DDD レイヤー
│   ├── Domain/                    # ドメイン層
│   │   ├── MunicipalityAmount/   # 市区町村金額集約
│   │   │   ├── Entity/
│   │   │   │   └── MunicipalityAmount.php
│   │   │   ├── ValueObject/
│   │   │   │   ├── MunicipalityCode.php
│   │   │   │   ├── DataType.php
│   │   │   │   ├── PriceCategory.php
│   │   │   │   └── Period.php
│   │   │   ├── Repository/
│   │   │   │   └── MunicipalityAmountRepositoryInterface.php
│   │   │   └── Service/
│   │   │       └── PriceClassificationService.php
│   │   └── Snapshot/              # スナップショット集約
│   │       ├── Entity/
│   │       │   └── SnapshotMeta.php
│   │       └── Repository/
│   │           └── SnapshotMetaRepositoryInterface.php
│   │
│   ├── Application/               # アプリケーション層
│   │   ├── UseCase/
│   │   │   ├── GetMunicipalityAmounts/
│   │   │   │   ├── GetMunicipalityAmountsUseCase.php
│   │   │   │   ├── GetMunicipalityAmountsRequest.php
│   │   │   │   └── GetMunicipalityAmountsResponse.php
│   │   │   ├── GetSnapshotMeta/
│   │   │   │   └── GetSnapshotMetaUseCase.php
│   │   │   └── ImportReinfolibData/
│   │   │       └── ImportReinfolibDataUseCase.php
│   │   ├── Service/
│   │   │   └── MunicipalityAmountApplicationService.php
│   │   └── DTO/
│   │       └── MunicipalityAmountDTO.php
│   │
│   ├── Infrastructure/            # インフラ層
│   │   ├── Persistence/          # DB実装
│   │   │   ├── Eloquent/
│   │   │   │   ├── MunicipalityAmountModel.php
│   │   │   │   └── SnapshotMetaModel.php
│   │   │   └── Repository/
│   │   │       ├── EloquentMunicipalityAmountRepository.php
│   │   │       └── EloquentSnapshotMetaRepository.php
│   │   ├── ExternalApi/          # 外部API
│   │   │   └── Reinfolib/
│   │   │       ├── ReinfolibApiClient.php
│   │   │       ├── ReinfolibApiClientInterface.php
│   │   │       └── DTO/
│   │   │           ├── TransactionPriceRequest.php
│   │   │           └── TransactionPriceResponse.php
│   │   └── GeoData/              # 地図データ
│   │       ├── GeoDataService.php
│   │       └── MunicipalityBoundaryLoader.php
│   │
│   └── Presentation/              # プレゼンテーション層
│       └── Http/
│           ├── Controllers/
│           │   ├── MunicipalityAmountController.php
│           │   └── SnapshotMetaController.php
│           ├── Requests/
│           │   └── GetMunicipalityAmountsRequest.php
│           └── Resources/
│               ├── MunicipalityAmountResource.php
│               └── SnapshotMetaResource.php
│
├── app/                          # Laravel標準
│   ├── Console/
│   │   ├── Commands/
│   │   │   ├── ImportReinfolibDataCommand.php
│   │   │   └── DetectNewQuarterCommand.php
│   │   └── Kernel.php
│   ├── Exceptions/
│   │   └── Handler.php
│   ├── Jobs/
│   │   ├── ImportMunicipalityDataJob.php
│   │   └── RefreshSnapshotJob.php
│   └── Providers/
│       ├── AppServiceProvider.php
│       └── RepositoryServiceProvider.php
│
├── database/
│   ├── migrations/
│   │   ├── 2026_07_19_000001_create_muni_amount_table.php
│   │   ├── 2026_07_19_000002_create_muni_txn_staging_table.php
│   │   ├── 2026_07_19_000003_create_snapshot_meta_table.php
│   │   └── 2026_07_19_000004_create_muni_amount_snapshot_view.php
│   ├── seeders/
│   │   └── DatabaseSeeder.php
│   └── init/
│       └── 01_create_extensions.sql
│
├── routes/
│   ├── api.php                   # API ルート
│   └── web.php
│
├── tests/
│   ├── Unit/
│   │   ├── Domain/
│   │   └── Application/
│   └── Feature/
│       ├── Api/
│       └── Commands/
│
├── config/
├── storage/
├── public/
├── composer.json
├── phpunit.xml
└── .env.example
```

## レイヤー責務

### Domain層（ドメイン層）
- **責務**: ビジネスロジック、ドメインルール
- **依存**: 他のレイヤーに依存しない
- **構成要素**:
  - Entity: 識別子を持つオブジェクト
  - Value Object: 値そのものを表現するオブジェクト
  - Repository Interface: データアクセスの抽象化
  - Domain Service: 複数のエンティティにまたがるビジネスロジック

### Application層（アプリケーション層）
- **責務**: ユースケースの実装、トランザクション制御
- **依存**: Domain層のみに依存
- **構成要素**:
  - UseCase: 具体的なユースケースの実装
  - Application Service: 複数のユースケースで共通の処理
  - DTO: レイヤー間のデータ転送

### Infrastructure層（インフラ層）
- **責務**: 技術的な実装詳細
- **依存**: Domain層とApplication層に依存
- **構成要素**:
  - Repository実装: Eloquentを使ったDB操作
  - External API Client: 外部API連携
  - File System: ファイル操作

### Presentation層（プレゼンテーション層）
- **責務**: HTTPリクエスト/レスポンスの処理
- **依存**: Application層に依存
- **構成要素**:
  - Controller: リクエストハンドリング
  - Request: バリデーション
  - Resource: レスポンス整形

## データフロー

```
HTTP Request
    ↓
Controller (Presentation)
    ↓
UseCase (Application)
    ↓
Domain Service / Repository (Domain)
    ↓
Repository Implementation (Infrastructure)
    ↓
Database / External API
```

## 実装優先順位

### Phase 1: C - データベーススキーマとマイグレーション ✅ 現在実装中
1. マイグレーションファイル作成
2. Eloquentモデル作成
3. 初期データ投入

### Phase 2: A - `/api/muni/amounts` エンドポイント
1. Domain層実装（Entity, ValueObject, Repository Interface）
2. Infrastructure層実装（Repository実装）
3. Application層実装（UseCase）
4. Presentation層実装（Controller, Resource）
5. 動作確認

### Phase 3: B - Reinfolib API クライアント
1. APIクライアント実装
2. DTO実装
3. エラーハンドリング
4. 単体テスト

### Phase 4: D - バッチ処理
1. 検知ポーリングコマンド
2. データ取込ジョブ
3. スケジューラー設定
4. 統合テスト
