#!/bin/bash

# ==============================================
# Bird Project - 共用レンタルサーバー デプロイスクリプト
# ==============================================
# 使い方: ./deploy-shared-hosting.sh
#
# 前提条件:
# 1. レンタルサーバーにSSHアクセス可能
# 2. Composerがインストール済み
# 3. PHP 8.3以上が利用可能

set -e  # エラーが発生したら停止

echo "=========================================="
echo "Bird Project - デプロイ開始"
echo "=========================================="

# カラー設定
RED='\033[0;31m'
GREEN='\033[0;32m'
YELLOW='\033[1;33m'
NC='\033[0m' # No Color

# 設定
APP_DIR="/home/your_user/bird"  # 本番環境のパスに変更
PUBLIC_DIR="/home/your_user/public_html"  # 公開ディレクトリ

echo -e "${YELLOW}ステップ 1: 依存関係のインストール${NC}"
cd apps/api
composer install --no-dev --optimize-autoloader

echo -e "${YELLOW}ステップ 2: 設定ファイルのチェック${NC}"
if [ ! -f .env ]; then
    echo -e "${RED}エラー: .envファイルが見つかりません${NC}"
    echo "apps/api/.env.shared-hosting をコピーして設定してください"
    exit 1
fi

echo -e "${YELLOW}ステップ 3: アプリケーションキーの生成${NC}"
php artisan key:generate --force

echo -e "${YELLOW}ステップ 4: キャッシュのクリア${NC}"
php artisan cache:clear
php artisan config:clear
php artisan route:clear
php artisan view:clear

echo -e "${YELLOW}ステップ 5: 最適化${NC}"
php artisan config:cache
php artisan route:cache
php artisan view:cache

echo -e "${YELLOW}ステップ 6: データベースマイグレーション${NC}"
read -p "マイグレーションを実行しますか？ (y/n): " -n 1 -r
echo
if [[ $REPLY =~ ^[Yy]$ ]]; then
    php artisan migrate --force
fi

echo -e "${YELLOW}ステップ 7: ストレージリンクの作成${NC}"
php artisan storage:link

echo -e "${YELLOW}ステップ 8: 権限の設定${NC}"
chmod -R 755 storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

echo -e "${GREEN}=========================================="
echo "デプロイ完了！"
echo "==========================================${NC}"
echo ""
echo "次のステップ:"
echo "1. 公開ディレクトリのシンボリックリンクを確認"
echo "   ln -s $APP_DIR/apps/api/public $PUBLIC_DIR/api"
echo ""
echo "2. Cronジョブの設定"
echo "   * * * * * cd $APP_DIR/apps/api && php artisan schedule:run >> /dev/null 2>&1"
echo ""
echo "3. キューワーカーの設定（オプション）"
echo "   nohup php artisan queue:work --tries=3 --timeout=300 &"
