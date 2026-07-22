# 🏠 Bird - 不動産価格可視化プラットフォーム

> 1都3県の不動産取引価格を地図上で可視化し、予算内のエリアを簡単に見つけられるWebアプリケーション

[![License: MIT](https://img.shields.io/badge/License-MIT-yellow.svg)](https://opensource.org/licenses/MIT)
[![PHP](https://img.shields.io/badge/PHP-8.3+-blue.svg)](https://php.net)
[![Laravel](https://img.shields.io/badge/Laravel-13+-red.svg)](https://laravel.com)
[![Next.js](https://img.shields.io/badge/Next.js-15-black.svg)](https://nextjs.org)

## 📖 概要

Birdは、国土交通省の不動産取引価格データ（Reinfolib API）を活用し、東京都・神奈川県・千葉県・埼玉県の市区町村別の不動産価格情報を地図上に可視化するプラットフォームです。

### 主な機能
- 📊 市区町村別の平均・中央値価格表示
- 🗺️ インタラクティブな地図表示（MapLibre GL JS）
- 💰 予算に応じたエリアハイライト
- 📈 四半期ごとの自動データ更新
- 🔍 データタイプ・価格区分による絞り込み

---

## 🚀 クイックスタート

### 開発環境

```bash
# リポジトリをクローン
git clone https://github.com/KenyJ18/bird.git
cd bird

# 環境変数設定
cp .env.example .env

# Dockerで起動
docker-compose up -d

# ブラウザで確認
# フロントエンド: http://localhost:3000
# バックエンドAPI: http://localhost:8080/api
```

### 本番環境（共用レンタルサーバー）

**完全ガイド**: [QUICKSTART.md](QUICKSTART.md) 📝

```bash
# 1. フロントエンドビルド
./build-frontend.sh

# 2. サーバーにアップロード
rsync -avz apps/api/ user@server:~/bird/apps/api/
rsync -avz frontend/out/ user@server:~/public_html/

# 3. サーバーでセットアップ
ssh user@server
cd ~/bird/apps/api
composer install --no-dev
php artisan migrate --force
```

**詳細**: [DEPLOY_STEP_BY_STEP.md](DEPLOY_STEP_BY_STEP.md)

---

## 🏗️ 技術スタック

### バックエンド
| 項目 | 技術 |
|------|------|
| **フレームワーク** | Laravel 13 |
| **言語** | PHP 8.3+ |
| **アーキテクチャ** | DDD (Domain-Driven Design) |
| **データベース** | MySQL 8.0 / PostgreSQL 16 / SQLite |
| **キャッシュ** | File / Database |
| **キュー** | Database Queue |

### フロントエンド
| 項目 | 技術 |
|------|------|
| **フレームワーク** | Next.js 15 |
| **言語** | TypeScript 5.3 |
| **UIライブラリ** | React 19 |
| **状態管理** | Jotai |
| **地図** | MapLibre GL JS |
| **スタイリング** | Tailwind CSS |

### インフラ（2パターン対応）

#### パターンA: 共用レンタルサーバー（推奨・低コスト）
- **サーバー**: ConoHa WING / さくらレンタルサーバー
- **コスト**: 月額 ¥500〜1,000
- **推奨**: トラフィック 日100ビュー未満

#### パターンB: AWS（スケーラブル）
- **バックエンド**: AWS App Runner
- **データベース**: AWS RDS (PostgreSQL)
- **キャッシュ**: ElastiCache (Redis)
- **フロントエンド**: Vercel
- **コスト**: 月額 ¥7,800〜12,000
- **推奨**: トラフィック 日1,000ビュー以上

**比較表**: [docs/SERVER_COMPARISON.md](docs/SERVER_COMPARISON.md)

---

## 📁 プロジェクト構成

```
bird/
├── apps/
│   └── api/                    # Laravel バックエンド
│       ├── src/
│       │   ├── Domain/         # ドメイン層（ビジネスロジック）
│       │   ├── Application/    # アプリケーション層（ユースケース）
│       │   ├── Infrastructure/ # インフラ層（DB、外部API）
│       │   └── Presentation/   # プレゼンテーション層（Controller）
│       ├── database/
│       │   ├── migrations/     # DBマイグレーション
│       │   └── seeders/        # テストデータ
│       └── routes/
│           └── api.php         # APIルート定義
├── frontend/                   # Next.js フロントエンド
│   ├── src/
│   │   ├── components/         # Reactコンポーネント
│   │   ├── pages/              # ページ
│   │   └── view/               # ビューロジック
│   └── out/                    # ビルド出力（静的HTML）
├── docs/                       # ドキュメント
│   ├── DEPLOY_SHARED_HOSTING.md
│   └── SERVER_COMPARISON.md
├── docker-compose.yml          # 開発環境構成
├── .env.shared-hosting         # 本番環境設定テンプレート
├── deploy-shared-hosting.sh    # デプロイスクリプト
└── build-frontend.sh           # フロントエンドビルドスクリプト
```

---

## 🎯 API エンドポイント

### GET /api/muni/amounts
市区町村別の不動産価格データを取得

**パラメータ**:
- `type` (optional): データタイプ
  - `宅地(土地)`
  - `宅地(土地と建物)` (デフォルト)
  - `中古マンション等`
- `priceCategory` (optional): 価格区分
  - `取引価格` (デフォルト)
  - `成約価格`
- `period` (optional): 期間 (例: `2026-Q2`)

**レスポンス例**:
```json
{
  "data": [
    {
      "municipality_code": "13101",
      "data_type": "宅地(土地と建物)",
      "price_category": "取引価格",
      "average_trade_price": 81000000,
      "median_trade_price": 72900000,
      "transaction_count": 56,
      "period": "2026-Q2",
      "updated_at": "2026-07-19 14:28:30"
    }
  ],
  "period": "2026-Q2"
}
```

**使用例**:
```bash
# すべてのデータ取得
curl https://your-domain.com/api/muni/amounts

# 中古マンションのみ
curl https://your-domain.com/api/muni/amounts?type=中古マンション等
```

---

## 📊 データソース

- **不動産取引価格データ**: [国土交通省 不動産情報ライブラリ (Reinfolib)](https://www.reinfolib.mlit.go.jp/)
- **市区町村境界データ**: [geolonia/japanese-admins](https://github.com/geolonia/japanese-admins)（GeoJSON）
- **更新頻度**: 四半期ごと（1月・4月・7月・10月）

---

## 💰 コスト比較

| 項目 | 共用サーバー | AWS |
|------|-------------|-----|
| **月額費用** | ¥500〜1,000 | ¥7,800〜12,000 |
| **初期費用** | ¥0〜3,000 | ¥0 |
| **年間コスト** | **¥10,000〜12,000** | ¥120,000 |
| **3年間総コスト** | **¥32,000** | ¥360,000 |
| **削減額** | - | **¥328,000** |

**週50ビュー程度の小規模サイトでは共用サーバーが最適** ✅

詳細: [docs/SERVER_COMPARISON.md](docs/SERVER_COMPARISON.md)

---

## 📚 ドキュメント

### 🚀 デプロイ関連
- [QUICKSTART.md](QUICKSTART.md) - 最速デプロイガイド（50分）
- [DEPLOY_STEP_BY_STEP.md](DEPLOY_STEP_BY_STEP.md) - 詳細手順書
- [CONOHA_WING_INFO.md](CONOHA_WING_INFO.md) - 必要情報チェックリスト

### 📖 設計・仕様
- [bird_design.md](bird_design.md) - システム設計書
- [docs/API_ARCHITECTURE.md](docs/API_ARCHITECTURE.md) - DDD アーキテクチャ
- [docs/PHASE_1C_DATABASE.md](docs/PHASE_1C_DATABASE.md) - データベース設計

### 💡 その他
- [docs/SERVER_COMPARISON.md](docs/SERVER_COMPARISON.md) - サーバー構成比較
- [docs/PROGRESS_REPORT.md](docs/PROGRESS_REPORT.md) - 実装進捗

---

## 🛠️ 開発環境

### 前提条件
- Docker Desktop インストール済み
- ポート `8080`, `3000`, `5432` が使用可能

### セットアップ

```bash
# リポジトリクローン
git clone https://github.com/KenyJ18/bird.git
cd bird

# 環境変数設定
cp .env.example .env

# Docker起動
docker-compose up -d

# データベースマイグレーション
docker-compose exec api php artisan migrate

# テストデータ投入
docker-compose exec api php artisan db:seed
```

### アクセスURL
- **フロントエンド**: http://localhost:3000
- **バックエンドAPI**: http://localhost:8080/api
- **データベース**: localhost:5432

### よく使うコマンド

```bash
# サービス起動
docker-compose up -d

# ログ確認
docker-compose logs -f api
docker-compose logs -f web

# サービス停止
docker-compose down

# マイグレーション実行
docker-compose exec api php artisan migrate

# キャッシュクリア
docker-compose exec api php artisan cache:clear

# Composerパッケージ更新
docker-compose exec api composer update
```

---

## 🧪 テスト

```bash
# バックエンドテスト
docker-compose exec api php artisan test

# フロントエンドテスト
docker-compose exec web npm test

# API動作確認
curl http://localhost:8080/api/muni/amounts
```

---

## 🔧 トラブルシューティング

### ポートが使用中
```bash
# 使用中のポート確認
lsof -i :8080
lsof -i :3000

# プロセスを停止して再試行
```

### データベース接続エラー
```bash
# データベースコンテナ再起動
docker-compose restart db

# 接続確認
docker-compose exec db psql -U bird_user -d bird
```

### Composerエラー
```bash
# vendor削除して再インストール
docker-compose exec api rm -rf vendor
docker-compose exec api composer install
```

---

## 📄 ライセンス

MIT License - 詳細は [LICENSE](LICENSE) を参照

---

## 👥 貢献

プルリクエスト歓迎！以下の手順で貢献できます:

1. Fork the Project
2. Create your Feature Branch (`git checkout -b feature/AmazingFeature`)
3. Commit your Changes (`git commit -m 'Add some AmazingFeature'`)
4. Push to the Branch (`git push origin feature/AmazingFeature`)
5. Open a Pull Request

---

## 📞 サポート

- **GitHub Issues**: https://github.com/KenyJ18/bird/issues
- **ドキュメント**: [docs/](docs/)

---

**Made with ❤️ by KenyJ18**


全サービスを停止し、コンテナを削除します。データベースのデータは保持されます。

> **注意**: データベースのデータも含めて完全に初期化したい場合は以下を実行してください。
>
> ```bash
> docker compose down -v
> ```

## アクセスURL

| サービス | URL |
|---------|-----|
| CakePHP（バックエンド） | http://localhost:8080 |
| Next.js（フロントエンド） | http://localhost:3000 |
| PostgreSQL | `localhost:5432` |

## よく使うコマンド

### ログ確認

```bash
docker compose logs -f            # 全サービスのログ
docker compose logs -f app        # CakePHP のログのみ
docker compose logs -f frontend   # Next.js のログのみ
docker compose logs -f db         # PostgreSQL のログのみ
```

### コンテナに入る

```bash
docker compose exec app bash           # CakePHP コンテナ
docker compose exec frontend sh        # Next.js コンテナ
docker compose exec db psql -U bird_user -d bird   # PostgreSQL
```

### パッケージ管理

```bash
# PHP (Composer)
docker compose exec app composer install
docker compose exec app composer require <パッケージ名>

# Node.js (npm)
docker compose exec frontend npm install
docker compose exec frontend npm install <パッケージ名>
```

### CakePHP マイグレーション

```bash
docker compose exec app bin/cake migrations migrate        # マイグレーション実行
docker compose exec app bin/cake migrations rollback       # ロールバック
docker compose exec app bin/cake bake migration <名前>     # マイグレーション作成
```

## ディレクトリ構成

```
bird/
├── docker-compose.yml
├── .env / .env.example
├── .gitignore
├── README.md
├── docker/
│   ├── php/
│   │   ├── Dockerfile
│   │   ├── php.ini
│   │   └── 000-default.conf
│   └── node/
│       └── Dockerfile
├── backend/          ← CakePHP アプリケーション
└── frontend/         ← Next.js アプリケーション
```
