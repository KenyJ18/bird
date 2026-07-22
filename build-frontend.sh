#!/bin/bash

# ==============================================
# Bird Project - フロントエンドビルドスクリプト
# ==============================================
# Next.jsを静的HTMLとしてエクスポート

set -e

echo "=========================================="
echo "Bird Frontend - 静的サイトビルド"
echo "=========================================="

# カラー設定
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m'

cd frontend

echo -e "${YELLOW}ステップ 1: 依存関係のインストール${NC}"
npm install --production=false

echo -e "${YELLOW}ステップ 2: 本番環境設定のコピー${NC}"
cp next.config.shared-hosting.js next.config.js

echo -e "${YELLOW}ステップ 3: 静的サイトビルド${NC}"
NODE_ENV=production npm run build

echo -e "${YELLOW}ステップ 4: 出力ディレクトリの確認${NC}"
if [ -d "out" ]; then
    echo "✓ ビルド成功: out/ ディレクトリが生成されました"
    echo ""
    echo "ファイル一覧:"
    ls -lh out/ | head -10
    echo ""
    echo "ディレクトリサイズ:"
    du -sh out/
else
    echo "✗ エラー: out/ ディレクトリが見つかりません"
    exit 1
fi

echo -e "${GREEN}=========================================="
echo "ビルド完了！"
echo "==========================================${NC}"
echo ""
echo "デプロイ方法:"
echo "1. FTP/SFTPで out/ ディレクトリ内のファイルをアップロード"
echo "   アップロード先: /home/your_user/public_html/"
echo ""
echo "2. または rsync でアップロード:"
echo "   rsync -avz --delete out/ user@server:/home/your_user/public_html/"
