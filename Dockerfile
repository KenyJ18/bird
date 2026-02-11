# ベースイメージ
FROM node:20-alpine

# 作業ディレクトリを設定
WORKDIR /app

# package.jsonとpackage-lock.jsonをコピー
COPY package*.json ./

# 依存関係をインストール
RUN npm install

# アプリケーションのソースコードをコピー
COPY . .

# Next.jsのビルド（本番環境用）
# 開発環境では不要なのでコメントアウト
# RUN npm run build

# ポート3000を公開
EXPOSE 3000

# 開発サーバーを起動
CMD ["npm", "run", "dev"]
