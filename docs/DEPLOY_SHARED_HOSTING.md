# Bird Project - 共用レンタルサーバー デプロイガイド

## 📋 目次
1. [サーバー要件](#サーバー要件)
2. [推奨サービス](#推奨サービス)
3. [デプロイ手順](#デプロイ手順)
4. [Cronジョブ設定](#cronジョブ設定)
5. [トラブルシューティング](#トラブルシューティング)

---

## サーバー要件

### 必須要件
- **PHP**: 8.3以上
- **データベース**: MySQL 5.7+ または MariaDB 10.3+（SQLiteも可）
- **Composer**: 2.x
- **SSH アクセス**: 推奨（デプロイ作業に必要）
- **ディスク容量**: 最低500MB（推奨1GB以上）
- **メモリ**: 最低256MB（推奨512MB以上）

### 必須PHP拡張機能
```
✓ OpenSSL
✓ PDO (MySQL/SQLite)
✓ Mbstring
✓ Tokenizer
✓ XML
✓ Ctype
✓ JSON
✓ BCMath
✓ Fileinfo
✓ cURL
```

---

## 推奨サービス

### 1. ConoHa WING（最推奨）⭐
- **プラン**: ベーシック 月額882円
- **特徴**: 
  - SSD 300GB
  - 転送量無制限
  - SSH可能
  - 自動バックアップ
  - 独自SSL無料
  - PHP 8.3対応
- **URL**: https://www.conoha.jp/wing/

### 2. さくらレンタルサーバー
- **プラン**: スタンダード 月額524円
- **特徴**:
  - SSD 300GB
  - SSH可能
  - MySQL 50個
  - PHP 8.3対応
- **URL**: https://www.sakura.ne.jp/

### 3. ロリポップ!
- **プラン**: ハイスピード 月額550円
- **特徴**:
  - SSD 400GB
  - 転送量無制限
  - SSH可能（ハイスピード以上）
  - PHP 8.3対応
- **URL**: https://lolipop.jp/

---

## デプロイ手順

### ステップ 1: サーバー準備

#### 1.1 データベース作成
レンタルサーバーのコントロールパネルから以下を作成：
- データベース名: `bird_db`
- ユーザー名: `bird_user`
- パスワード: 任意の強固なパスワード

#### 1.2 SSH接続設定
```bash
# SSH鍵の生成（ローカル環境）
ssh-keygen -t ed25519 -C "your_email@example.com"

# 公開鍵をサーバーに登録
# レンタルサーバーのコントロールパネルからSSH鍵を登録
```

### ステップ 2: ファイルのアップロード

#### 2.1 Gitでクローン（推奨）
```bash
# サーバーにSSH接続
ssh user@your-server.com

# ホームディレクトリにクローン
cd ~
git clone https://github.com/KenyJ18/bird.git
cd bird
```

#### 2.2 FTP/SFTPでアップロード（代替案）
以下のディレクトリをアップロード：
```
apps/api/          → /home/your_user/bird/apps/api/
frontend/out/      → /home/your_user/public_html/
```

### ステップ 3: バックエンド設定

#### 3.1 環境変数設定
```bash
cd ~/bird/apps/api

# .envファイルをコピー
cp ../../.env.shared-hosting .env

# .envファイルを編集
nano .env
```

**.env設定内容**:
```bash
APP_ENV=production
APP_DEBUG=false
APP_URL=https://your-domain.com

DB_CONNECTION=mysql
DB_HOST=localhost
DB_DATABASE=bird_db
DB_USERNAME=bird_user
DB_PASSWORD=your_database_password

REINFOLIB_API_KEY=b7ea735e49bb4de8b72ed04efe0a4de1

CACHE_STORE=file
QUEUE_CONNECTION=database
```

#### 3.2 Composerインストール
```bash
# Composerがない場合はインストール
curl -sS https://getcomposer.org/installer | php
mv composer.phar /usr/local/bin/composer

# 依存関係をインストール
composer install --no-dev --optimize-autoloader
```

#### 3.3 アプリケーション初期化
```bash
# アプリケーションキー生成
php artisan key:generate

# データベースマイグレーション
php artisan migrate --force

# ストレージリンク作成
php artisan storage:link

# キャッシュ生成
php artisan config:cache
php artisan route:cache
php artisan view:cache

# 権限設定
chmod -R 755 storage bootstrap/cache
```

### ステップ 4: 公開ディレクトリ設定

#### 4.1 シンボリックリンク作成（推奨）
```bash
# 既存のpublic_htmlをバックアップ
mv ~/public_html ~/public_html.backup

# APIのpublicディレクトリへリンク
ln -s ~/bird/apps/api/public ~/public_html

# または、サブディレクトリとして
ln -s ~/bird/apps/api/public ~/public_html/api
```

#### 4.2 .htaccess確認
`public_html/.htaccess` が正しく配置されていることを確認

### ステップ 5: フロントエンド設定

#### 5.1 ビルド（ローカル環境）
```bash
# ローカル環境で実行
cd frontend
npm install
NODE_ENV=production npm run build
```

#### 5.2 アップロード
```bash
# out/ ディレクトリの内容をサーバーにアップロード
rsync -avz --delete out/ user@server:~/public_html/
```

または FTP/SFTP でアップロード

### ステップ 6: 動作確認

#### 6.1 APIエンドポイント確認
```bash
curl https://your-domain.com/api/muni/amounts
```

期待するレスポンス:
```json
{
  "data": [...],
  "period": "2026-Q2"
}
```

#### 6.2 フロントエンド確認
ブラウザで `https://your-domain.com` にアクセス

---

## Cronジョブ設定

### Laravelスケジューラー（必須）

レンタルサーバーのcrontab設定:
```bash
crontab -e
```

以下を追加:
```cron
# Laravel Scheduler（毎分実行）
* * * * * cd /home/your_user/bird/apps/api && php artisan schedule:run >> /dev/null 2>&1

# 四半期データ更新（1月,4月,7月,10月の1日 午前2時）
0 2 1 1,4,7,10 * cd /home/your_user/bird/apps/api && php artisan reinfolib:import >> /dev/null 2>&1
```

### キューワーカー（オプション）

バックグラウンドで実行:
```bash
cd ~/bird/apps/api

# nohup で永続実行
nohup php artisan queue:work --tries=3 --timeout=300 >> storage/logs/queue.log 2>&1 &

# プロセス確認
ps aux | grep queue:work

# 停止する場合
pkill -f "queue:work"
```

---

## トラブルシューティング

### 問題 1: 500 Internal Server Error

**原因**: 権限エラーまたは.envファイル未設定

**解決策**:
```bash
cd ~/bird/apps/api

# 権限修正
chmod -R 755 storage bootstrap/cache
chmod -R 775 storage bootstrap/cache

# .env確認
cat .env | grep APP_KEY

# キャッシュクリア
php artisan cache:clear
php artisan config:clear
```

### 問題 2: データベース接続エラー

**原因**: DB認証情報が正しくない

**解決策**:
```bash
# .envのDB設定を確認
nano .env

# 接続テスト
php artisan migrate:status
```

### 問題 3: Composer実行時にメモリ不足

**解決策**:
```bash
# メモリ制限を一時的に解除
php -d memory_limit=-1 $(which composer) install
```

### 問題 4: APIが404エラー

**原因**: .htaccessが機能していない

**解決策**:
```bash
# mod_rewriteが有効か確認
# レンタルサーバーのコントロールパネルで確認

# .htaccessを確認
cat public/.htaccess
```

### 問題 5: Cronが実行されない

**解決策**:
```bash
# 手動実行でテスト
cd ~/bird/apps/api
php artisan schedule:run

# crontabを確認
crontab -l

# ログを確認
tail -f storage/logs/laravel.log
```

---

## 本番環境セキュリティチェックリスト

- [ ] `APP_DEBUG=false` に設定
- [ ] `APP_ENV=production` に設定
- [ ] 強固な`APP_KEY`を生成
- [ ] データベースパスワードを強固に設定
- [ ] SSH鍵認証を使用（パスワード認証無効化）
- [ ] 不要なファイルを削除（.git, .env.example等）
- [ ] SSL証明書の設定（Let's Encrypt推奨）
- [ ] ファイアウォール設定
- [ ] 定期的なバックアップ設定

---

## 更新手順

### アプリケーション更新
```bash
cd ~/bird

# 最新版を取得
git pull origin main

# バックエンド更新
cd apps/api
composer install --no-dev --optimize-autoloader
php artisan migrate --force
php artisan cache:clear
php artisan config:cache

# フロントエンド更新（ローカルでビルド後アップロード）
```

---

## サポート情報

- **GitHub**: https://github.com/KenyJ18/bird
- **ドキュメント**: /docs/
- **Issue報告**: https://github.com/KenyJ18/bird/issues

---

**デプロイ完了後は、必ず全機能のテストを実施してください！** 🎉
