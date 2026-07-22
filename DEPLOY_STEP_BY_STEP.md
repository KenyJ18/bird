# Bird Project - ConoHa WING デプロイ実行ガイド

## 📌 前提条件
- ✅ `CONOHA_WING_INFO.md` の情報収集が完了している
- ✅ SSH接続テストが成功している
- ✅ データベースが作成済み

---

## 🚀 デプロイ実行手順

### Phase 1: ローカル環境での準備（10分）

#### 1-1. フロントエンドのビルド

```bash
# birdプロジェクトディレクトリに移動
cd /Users/kenyj/bird

# フロントエンドビルドスクリプトを実行
./build-frontend.sh
```

**期待される出力**:
```
✓ ビルド成功: out/ ディレクトリが生成されました
```

**確認**:
```bash
ls -la frontend/out/
# index.html や _next/ などが生成されているはず
```

#### 1-2. バックエンドのパッケージング

```bash
# 開発用ファイルを除外してアーカイブ作成
cd apps/api
tar --exclude='vendor' \
    --exclude='node_modules' \
    --exclude='.git' \
    --exclude='storage/logs/*' \
    --exclude='storage/framework/cache/*' \
    -czf ~/bird-api.tar.gz .
cd ../..

echo "✅ バックエンドパッケージング完了: ~/bird-api.tar.gz"
```

---

### Phase 2: サーバー接続とセットアップ（15分）

#### 2-1. SSH接続

```bash
# ConoHa WINGサーバーに接続
ssh [あなたのユーザー名]@[サーバーホスト名]

# 接続例:
# ssh conoha_user@123.456.789.012
```

#### 2-2. ディレクトリ構造作成

```bash
# ホームディレクトリを確認
pwd
# /home/[ユーザー名]/ と表示されるはず

# プロジェクトディレクトリ作成
mkdir -p ~/bird/apps/api
mkdir -p ~/bird/frontend

# 確認
ls -la ~/bird/
```

#### 2-3. Composerインストール確認

```bash
# Composerがあるか確認
composer --version

# ない場合はインストール
curl -sS https://getcomposer.org/installer | php
mkdir -p ~/bin
mv composer.phar ~/bin/composer
chmod +x ~/bin/composer

# PATHに追加（.bashrcまたは.bash_profileに追記）
echo 'export PATH="$HOME/bin:$PATH"' >> ~/.bashrc
source ~/.bashrc

# 再確認
composer --version
```

#### 2-4. PHP設定確認

```bash
# PHPバージョン確認
php -v
# PHP 8.3.x 以上が必要

# もし古い場合、ConoHaコントロールパネルで変更
# サイト管理 → 応用設定 → PHP設定 → バージョン変更

# 必要な拡張機能確認
php -m | grep -E 'pdo_mysql|mbstring|xml|curl|json|bcmath|fileinfo'

# すべて表示されればOK
```

---

### Phase 3: ファイルアップロード（10分）

2つの方法から選択:

#### 方法A: rsync（推奨・高速）

**ローカル環境から実行**:
```bash
# バックエンドをアップロード
rsync -avz --progress \
  --exclude='vendor' \
  --exclude='node_modules' \
  --exclude='.git' \
  --exclude='storage/logs/*' \
  /Users/kenyj/bird/apps/api/ \
  [ユーザー名]@[サーバー]:~/bird/apps/api/

# フロントエンドをアップロード（静的ファイル）
rsync -avz --progress --delete \
  /Users/kenyj/bird/frontend/out/ \
  [ユーザー名]@[サーバー]:~/public_html/

echo "✅ ファイルアップロード完了"
```

#### 方法B: SFTP（GUI使用）

**FileZillaなどのSFTPクライアントを使用**:
1. 接続情報:
   - ホスト: `sftp://[サーバーホスト名]`
   - ユーザー名: `[ユーザー名]`
   - パスワード: `[パスワード]`
   - ポート: 22

2. アップロード:
   - `apps/api/` → `/home/[ユーザー名]/bird/apps/api/`
   - `frontend/out/*` → `/home/[ユーザー名]/public_html/`

---

### Phase 4: バックエンドセットアップ（15分）

**サーバー上で実行** (SSH接続中):

#### 4-1. 環境変数設定

```bash
cd ~/bird/apps/api

# .envファイルを作成
cat > .env << 'EOF'
APP_NAME=bird
APP_ENV=production
APP_DEBUG=false
APP_URL=https://[あなたのドメイン]

DB_CONNECTION=mysql
DB_HOST=localhost
DB_PORT=3306
DB_DATABASE=[データベース名]
DB_USERNAME=[DBユーザー名]
DB_PASSWORD=[DBパスワード]

CACHE_STORE=file
QUEUE_CONNECTION=database
SESSION_DRIVER=file

REINFOLIB_API_KEY=b7ea735e49bb4de8b72ed04efe0a4de1

LOG_CHANNEL=daily
LOG_LEVEL=warning
EOF

# 必ず実際の値に置き換えてください！
nano .env
```

**編集のポイント**:
- `[あなたのドメイン]` → 実際のドメイン名
- `[データベース名]` → ConoHaで作成したDB名
- `[DBユーザー名]` → ConoHaで作成したDBユーザー
- `[DBパスワード]` → ConoHaで設定したDBパスワード

#### 4-2. Composer依存関係インストール

```bash
cd ~/bird/apps/api

# 本番用パッケージのインストール（時間がかかります）
composer install --no-dev --optimize-autoloader

# 完了を確認
ls vendor/
```

#### 4-3. アプリケーション初期化

```bash
# アプリケーションキー生成
php artisan key:generate

# .envを確認（APP_KEYが設定されているはず）
grep APP_KEY .env

# ストレージディレクトリ作成
mkdir -p storage/framework/{cache,sessions,views}
mkdir -p storage/logs
mkdir -p bootstrap/cache

# 権限設定
chmod -R 755 storage
chmod -R 755 bootstrap/cache
chmod -R 775 storage/framework
chmod -R 775 storage/logs

# ストレージリンク作成
php artisan storage:link
```

#### 4-4. データベースマイグレーション

```bash
# データベース接続テスト
php artisan migrate:status

# マイグレーション実行
php artisan migrate --force

# 確認
php artisan migrate:status
# すべてのマイグレーションが "Ran" になっているはず
```

#### 4-5. テストデータ投入（開発用・オプション）

```bash
# テストデータをシード
php artisan db:seed --class=MunicipalityAmountSeeder

# 確認
php artisan tinker
>>> \App\Models\MunicipalityAmountModel::count();
# 126 が返ればOK
>>> exit
```

#### 4-6. キャッシュ生成

```bash
# 設定キャッシュ
php artisan config:cache

# ルートキャッシュ
php artisan route:cache

# ビューキャッシュ
php artisan view:cache

# 確認
ls -la bootstrap/cache/
# config.php, routes-v7.php などが生成されているはず
```

---

### Phase 5: 公開ディレクトリ設定（5分）

#### 5-1. シンボリックリンク設定

**オプションA: ルートドメインで公開（推奨）**
```bash
# 既存のpublic_htmlをバックアップ
mv ~/public_html ~/public_html.backup

# APIのpublicディレクトリにリンク
ln -s ~/bird/apps/api/public ~/public_html

# 確認
ls -la ~/public_html
# ~/bird/apps/api/public へのリンクが表示されるはず
```

**オプションB: サブディレクトリで公開**
```bash
# フロントエンドはルートに
# APIは /api サブディレクトリに

# APIへのリンク作成
ln -s ~/bird/apps/api/public ~/public_html/api

# .htaccessでルーティング調整が必要
```

#### 5-2. .htaccess確認

```bash
# .htaccessが存在するか確認
cat ~/public_html/.htaccess

# 正しく表示されればOK
```

---

### Phase 6: 動作確認（5分）

#### 6-1. APIエンドポイント確認

**ローカルから確認**:
```bash
# APIエンドポイントテスト
curl -s https://[あなたのドメイン]/api/muni/amounts | jq .

# または
curl -s https://[あなたのドメイン]/api/muni/amounts | python -m json.tool
```

**期待されるレスポンス**:
```json
{
  "data": [
    {
      "municipality_code": "13101",
      "data_type": "宅地(土地と建物)",
      ...
    }
  ],
  "period": "2026-Q2"
}
```

#### 6-2. フロントエンド確認

ブラウザで以下にアクセス:
```
https://[あなたのドメイン]/
```

正常に表示されればOK！

#### 6-3. エラーログ確認

```bash
# サーバー上で確認
cd ~/bird/apps/api
tail -f storage/logs/laravel.log

# エラーがなければ成功！
```

---

### Phase 7: Cronジョブ設定（5分）

#### 7-1. crontab編集

```bash
# crontabを編集
crontab -e

# 以下を追加
* * * * * cd /home/[ユーザー名]/bird/apps/api && php artisan schedule:run >> /dev/null 2>&1

# 四半期ごとのデータ更新（1月,4月,7月,10月の1日 午前2時）
0 2 1 1,4,7,10 * cd /home/[ユーザー名]/bird/apps/api && php artisan reinfolib:import >> storage/logs/import.log 2>&1
```

**必ず** `[ユーザー名]` を実際の値に置き換えてください！

#### 7-2. Cron動作確認

```bash
# 保存して終了後、crontabを確認
crontab -l

# 1分待ってログ確認
tail -f ~/bird/apps/api/storage/logs/laravel.log
# スケジューラーのログが出力されればOK
```

---

### Phase 8: SSL設定（3分）

#### 8-1. ConoHaコントロールパネルでSSL設定

1. ConoHa WINGコントロールパネルにログイン
2. サイト管理 → サイトセキュリティ → 無料独自SSL
3. 「利用設定」をON

#### 8-2. HTTPS強制リダイレクト

```bash
# .htaccessにHTTPS強制を追加
cd ~/public_html

# バックアップ
cp .htaccess .htaccess.backup

# HTTPS強制を追記
cat >> .htaccess << 'EOF'

# Force HTTPS
<IfModule mod_rewrite.c>
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>
EOF
```

#### 8-3. 確認

```bash
# HTTPでアクセスしてHTTPSにリダイレクトされるか確認
curl -I http://[あなたのドメイン]/
# Location: https://... が表示されればOK
```

---

## ✅ デプロイ完了チェックリスト

- [ ] フロントエンドが正常に表示される
- [ ] APIエンドポイントが応答する (`/api/muni/amounts`)
- [ ] データベースにデータが存在する（126件）
- [ ] SSL証明書が有効
- [ ] HTTPSにリダイレクトされる
- [ ] Cronジョブが設定されている
- [ ] エラーログにエラーがない

**すべてチェックできたら** 🎉 **デプロイ完了！**

---

## 🐛 トラブルシューティング

### エラー: 500 Internal Server Error

```bash
# エラーログ確認
tail -100 ~/bird/apps/api/storage/logs/laravel.log

# よくある原因:
# 1. .envファイルの設定ミス → nano .env で確認
# 2. 権限エラー → chmod -R 775 storage bootstrap/cache
# 3. APP_KEYが未設定 → php artisan key:generate
```

### エラー: データベース接続エラー

```bash
# DB接続テスト
cd ~/bird/apps/api
php artisan tinker
>>> DB::connection()->getPdo();

# エラーが出たら.envのDB設定を確認
nano .env
```

### エラー: Composer実行でメモリ不足

```bash
# メモリ制限を解除してインストール
php -d memory_limit=-1 $(which composer) install --no-dev
```

### フロントエンドが表示されない

```bash
# public_htmlの内容確認
ls -la ~/public_html/

# index.htmlがあるか確認
cat ~/public_html/index.html | head
```

---

## 📞 サポート

問題が解決しない場合:
1. `storage/logs/laravel.log` を確認
2. ConoHa WINGサポートに問い合わせ: 03-6702-0428
3. GitHub Issuesに報告

---

## 🎉 次のステップ

デプロイ完了後:
1. 定期的なバックアップ設定（ConoHaの自動バックアップ機能）
2. 監視設定（Cronログの定期確認）
3. データ更新（四半期ごと自動実行）

**おめでとうございます！birdプロジェクトが本番稼働しました！** 🚀
