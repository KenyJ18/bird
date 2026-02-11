# bird
現在の家賃相場を集計、地図に表示するアプリ

## 必要な環境
- Docker
- Docker Compose

## プロジェクト構成
```
bird/
├── src/
│   ├── components/    # Reactコンポーネント
│   ├── pages/         # Next.jsページ
│   └── view/          # ビューファイル
├── Dockerfile         # Dockerイメージ定義
├── docker-compose.yml # Docker Compose設定
├── next.config.js     # Next.js設定
└── package.json       # 依存関係管理
```

## Dockerを使用した開発

### 初回セットアップ
1. リポジトリをクローン
```bash
git clone https://github.com/KenyJ18/bird.git
cd bird
```

2. Dockerコンテナーを起動
```bash
npm run docker:up
```

初回起動時は依存関係のインストールとビルドに時間がかかります。

### サーバー起動コマンド

#### Dockerコンテナーを起動（バックグラウンド）
```bash
npm run docker:up
```
または
```bash
docker-compose up -d
```

起動後、ブラウザで http://localhost:3000 にアクセスしてください。

#### ログを確認
```bash
npm run docker:logs
```
または
```bash
docker-compose logs -f
```

### リセットコマンド

#### コンテナーとボリュームを削除して再起動
```bash
npm run docker:reset
```
または
```bash
docker-compose down -v && docker-compose up -d
```

このコマンドは以下を実行します:
- 既存のコンテナーを停止・削除
- ボリューム（node_modulesなど）を削除
- コンテナーを再ビルド・起動

### 削除コマンド

#### コンテナーを停止
```bash
npm run docker:down
```
または
```bash
docker-compose down
```

#### 完全削除（コンテナー、ボリューム、イメージをすべて削除）
```bash
npm run docker:clean
```
または
```bash
docker-compose down -v --rmi all
```

## ローカル開発（Dockerを使用しない場合）

### セットアップ
```bash
npm install
```

### 開発サーバー起動
```bash
npm run dev
```

### 本番ビルド
```bash
npm run build
npm start
```

## 技術スタック
- **フレームワーク**: Next.js 16
- **言語**: TypeScript, React 19
- **UIライブラリ**: Material-UI (MUI)
- **コンテナ**: Docker, Docker Compose

## トラブルシューティング

### ポート3000が既に使用されている
別のアプリケーションがポート3000を使用している場合、`docker-compose.yml`のポート設定を変更してください:
```yaml
ports:
  - "3001:3000"  # 3001など別のポートに変更
```

### ホットリロードが動作しない
Dockerコンテナー内でファイル変更が検知されない場合、`docker-compose.yml`の環境変数が正しく設定されているか確認してください:
```yaml
environment:
  - WATCHPACK_POLLING=true
```

### コンテナーが起動しない
以下のコマンドでログを確認してください:
```bash
docker-compose logs
```

依存関係のエラーが表示される場合は、リセットコマンドを実行してください:
```bash
npm run docker:reset
```

## ライセンス
ISC
