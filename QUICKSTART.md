# 🚀 Bird Project - ConoHa WING クイックスタート

## 📋 デプロイまでの流れ（概要）

```
1. 情報収集 (10分)
   ↓
2. ローカルでビルド (5分)
   ↓
3. サーバーにアップロード (10分)
   ↓
4. サーバーでセットアップ (20分)
   ↓
5. 動作確認 (5分)
   ↓
完了！ 🎉
```

**所要時間: 約50分〜1時間**

---

## ステップ1: 情報を集める（10分）

### やること
1. **ConoHa WINGコントロールパネル**にログイン
   - URL: https://www.conoha.jp/wing/

2. **データベースを作成**
   - サイト管理 → データベース → ＋データベース
   - データベース名: `bird_db`
   - ユーザー名: `bird_user`  
   - パスワード: **強固なパスワードを設定**

3. **SSH情報を確認**
   - サイト管理 → SSH設定
   - ユーザー名とホスト名をメモ

4. **`CONOHA_WING_INFO.md`に記入**
   ```bash
   open /Users/kenyj/bird/CONOHA_WING_INFO.md
   ```
   すべての項目を埋める

### 確認
- [ ] データベース作成完了
- [ ] SSH接続情報メモ完了
- [ ] ドメイン名確認完了

---

## ステップ2: ローカルでビルド（5分）

### やること

```bash
# ターミナルを開く
cd /Users/kenyj/bird

# フロントエンドをビルド
./build-frontend.sh
```

### 確認
```bash
# 出力ディレクトリができているか確認
ls -la frontend/out/
# index.html などがあればOK ✅
```

---

## ステップ3: SSH接続テスト（3分）

### やること

```bash
# ConoHa WINGサーバーに接続
ssh [ユーザー名]@[サーバーホスト]

# 例:
# ssh c1234567@123.456.789.012
```

### 成功したら
```bash
# ホームディレクトリを確認
pwd
# /home/c1234567/ のように表示されればOK

# プロジェクトディレクトリ作成
mkdir -p ~/bird/apps/api

# ログアウト
exit
```

---

## ステップ4: ファイルをアップロード（10分）

### 方法A: rsync（推奨）

**ターミナルから実行**:
```bash
# バックエンドをアップロード
rsync -avz --progress \
  --exclude='vendor' \
  --exclude='node_modules' \
  --exclude='.git' \
  --exclude='storage/logs/*' \
  /Users/kenyj/bird/apps/api/ \
  [ユーザー名]@[サーバー]:~/bird/apps/api/

# フロントエンドをアップロード
rsync -avz --progress --delete \
  /Users/kenyj/bird/frontend/out/ \
  [ユーザー名]@[サーバー]:~/public_html/
```

### 方法B: FileZilla（GUI）

1. FileZillaを開く
2. 接続情報:
   - ホスト: `sftp://[サーバーホスト]`
   - ユーザー名: `[ユーザー名]`
   - パスワード: `[パスワード]`
   - ポート: `22`

3. ドラッグ&ドロップでアップロード:
   - `apps/api/` → `/home/[ユーザー名]/bird/apps/api/`
   - `frontend/out/*` → `/home/[ユーザー名]/public_html/`

---

## ステップ5: サーバーでセットアップ（20分）

### SSH接続

```bash
ssh [ユーザー名]@[サーバー]
```

### 5-1. Composerインストール（初回のみ）

```bash
# Composerがあるか確認
composer --version

# なければインストール
curl -sS https://getcomposer.org/installer | php
mkdir -p ~/bin
mv composer.phar ~/bin/composer
chmod +x ~/bin/composer
echo 'export PATH="$HOME/bin:$PATH"' >> ~/.bashrc
source ~/.bashrc
```

### 5-2. 環境変数設定

```bash
cd ~/bird/apps/api

# .envファイルを作成
nano .env
```

**以下をコピペして、[ ]部分を実際の値に変更**:
```bash
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
```

**Ctrl+O で保存、Ctrl+X で終了**

### 5-3. セットアップコマンド実行

```bash
cd ~/bird/apps/api

# 依存関係インストール（5分程度かかります）
composer install --no-dev --optimize-autoloader

# アプリケーションキー生成
php artisan key:generate

# ディレクトリ作成と権限設定
mkdir -p storage/framework/{cache,sessions,views}
mkdir -p storage/logs
chmod -R 775 storage bootstrap/cache

# ストレージリンク
php artisan storage:link

# データベースマイグレーション
php artisan migrate --force

# テストデータ投入（オプション）
php artisan db:seed --class=MunicipalityAmountSeeder

# キャッシュ生成
php artisan config:cache
php artisan route:cache
php artisan view:cache
```

### 5-4. 公開ディレクトリ設定

```bash
# 既存のpublic_htmlをバックアップ
mv ~/public_html ~/public_html.backup

# APIのpublicにリンク
ln -s ~/bird/apps/api/public ~/public_html
```

---

## ステップ6: 動作確認（5分）

### API確認（ローカルから）

```bash
# ターミナルで実行
curl https://[あなたのドメイン]/api/muni/amounts
```

**成功すると**:
```json
{"data":[...],"period":"2026-Q2"}
```

### フロントエンド確認

ブラウザで開く:
```
https://[あなたのドメイン]/
```

**正常に表示されればOK！** ✅

---

## ステップ7: Cronジョブ設定（5分）

### SSH接続中に実行

```bash
# crontab編集
crontab -e

# 以下を追加（[ユーザー名]を実際の値に変更）
* * * * * cd /home/[ユーザー名]/bird/apps/api && php artisan schedule:run >> /dev/null 2>&1
```

**保存して終了**: `Esc` → `:wq` → `Enter`

### 確認

```bash
# crontabを確認
crontab -l

# 1分待ってログ確認
tail -f ~/bird/apps/api/storage/logs/laravel.log
```

---

## ステップ8: SSL設定（3分）

### ConoHaコントロールパネルで設定

1. サイト管理 → サイトセキュリティ → 無料独自SSL
2. 「利用設定」を **ON**

### HTTPS強制

```bash
# SSH接続中に実行
cd ~/public_html
nano .htaccess
```

**ファイルの末尾に追加**:
```apache
# Force HTTPS
<IfModule mod_rewrite.c>
    RewriteCond %{HTTPS} off
    RewriteRule ^(.*)$ https://%{HTTP_HOST}%{REQUEST_URI} [L,R=301]
</IfModule>
```

---

## ✅ 完了チェックリスト

デプロイが完了したら確認:

- [ ] `https://[ドメイン]/` でフロントエンドが表示される
- [ ] `https://[ドメイン]/api/muni/amounts` がJSON返す
- [ ] HTTPアクセスがHTTPSにリダイレクトされる
- [ ] エラーログにエラーがない
- [ ] Cronジョブが設定されている

**すべてOKなら** 🎉 **デプロイ完了！**

---

## 🐛 よくあるエラーと対処法

### エラー: 500 Internal Server Error

```bash
# エラーログ確認
ssh [ユーザー名]@[サーバー]
tail -50 ~/bird/apps/api/storage/logs/laravel.log
```

**よくある原因**:
1. `.env`のDB設定が間違っている → `nano .env` で修正
2. 権限エラー → `chmod -R 775 storage bootstrap/cache`
3. APP_KEYが未設定 → `php artisan key:generate`

### エラー: データベース接続できない

```bash
# .envファイル確認
cd ~/bird/apps/api
cat .env | grep DB_

# ConoHaのデータベース情報と一致しているか確認
```

### フロントエンドが表示されない

```bash
# public_htmlの中身確認
ls -la ~/public_html/

# index.htmlがあるか確認
```

---

## 📚 詳細ドキュメント

より詳しい手順は以下を参照:
- `DEPLOY_STEP_BY_STEP.md` - 詳細なデプロイ手順
- `CONOHA_WING_INFO.md` - 必要情報チェックリスト
- `docs/DEPLOY_SHARED_HOSTING.md` - 完全ガイド

---

## 📞 サポート

問題が解決しない場合:
- **ConoHa WINGサポート**: 03-6702-0428
- **サポートサイト**: https://support.conoha.jp/wing/
- **GitHub Issues**: https://github.com/KenyJ18/bird/issues

---

## 🎯 次のステップ

デプロイ完了後:
1. 定期的なバックアップ確認（ConoHaの自動バックアップ）
2. アクセスログの監視
3. データ更新の確認（四半期ごと）

**おめでとうございます！** 🎊
