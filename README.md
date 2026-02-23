# bird

Web アプリケーション開発プロジェクト

## 技術スタック

| レイヤー | 技術 |
|---------|------|
| バックエンド（メイン） | CakePHP / PHP 8.3 / Apache |
| フロントエンド（一部機能） | Next.js / React / TypeScript / Material UI / Jotai |
| データベース | PostgreSQL 16 |
| 開発環境 | Docker Compose |

## 前提条件

- [Docker Desktop](https://www.docker.com/products/docker-desktop/) がインストール・起動済みであること
- ポート `8080`, `3000`, `5432` が空いていること

## 初回セットアップ

```bash
# 1. プロジェクトルートに移動
cd /Users/kenyj/bird

# 2. 環境変数ファイルを作成（必要に応じて編集）
cp .env.example .env

# 3. CakePHP プロジェクトを初期化
docker compose build app
docker compose run --rm app composer create-project --prefer-dist cakephp/app .

# 4. Next.js プロジェクトを初期化
docker compose build frontend
docker compose run --rm frontend npx create-next-app@latest . --typescript --use-npm

# 5. フロントエンド追加パッケージをインストール
docker compose run --rm frontend npm install \
  @mui/material @emotion/react @emotion/styled \
  jotai

# 6. 全コンテナを起動
docker compose up -d
```

## 開発環境サーバーの操作コマンド

### 起動

```bash
docker compose up -d
```

全サービス（CakePHP / Next.js / PostgreSQL）をバックグラウンドで起動します。

### 再起動

```bash
docker compose restart
```

全サービスを再起動します。特定のサービスのみ再起動する場合：

```bash
docker compose restart app        # CakePHP のみ再起動
docker compose restart frontend   # Next.js のみ再起動
docker compose restart db         # PostgreSQL のみ再起動
```

### 停止

```bash
docker compose down
```

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
