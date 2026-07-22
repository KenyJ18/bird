# プランA実装完了チェックリスト

## ✅ 完了項目

### Phase 1: バックエンド（Laravel）最適化
- [x] キャッシュをFileベースに変更 (`config/cache.php`)
- [x] キューをDatabaseベースに変更 (`config/queue.php`)
- [x] マイグレーションのMySQL互換性確認（既に対応済み）
- [x] .htaccessセキュリティヘッダー追加
- [x] 共用サーバー用.env設定ファイル作成 (`.env.shared-hosting`)
- [x] デプロイスクリプト作成 (`deploy-shared-hosting.sh`)

### Phase 2: フロントエンド（Next.js）最適化
- [x] 静的エクスポート設定ファイル作成 (`next.config.shared-hosting.js`)
- [x] ビルドスクリプト作成 (`build-frontend.sh`)

### Phase 3: ドキュメント整備
- [x] デプロイガイド作成 (`docs/DEPLOY_SHARED_HOSTING.md`)
- [x] サーバー構成比較ドキュメント作成 (`docs/SERVER_COMPARISON.md`)

### Phase 4: 削除すべき設定（本番環境）
- [x] ドキュメント化: Docker関連ファイル（開発環境のみで使用）
- [x] ドキュメント化: Laravel Octane設定（不要）
- [x] ドキュメント化: Redis設定（不要）

---

## 📁 作成ファイル一覧

### 設定ファイル
1. `.env.shared-hosting` - 共用サーバー用環境変数
2. `frontend/next.config.shared-hosting.js` - 静的エクスポート設定

### スクリプト
3. `deploy-shared-hosting.sh` - バックエンドデプロイ
4. `build-frontend.sh` - フロントエンドビルド

### ドキュメント
5. `docs/DEPLOY_SHARED_HOSTING.md` - デプロイ手順書
6. `docs/SERVER_COMPARISON.md` - 構成比較

### 変更ファイル
7. `apps/api/config/cache.php` - デフォルトをfileに変更
8. `apps/api/public/.htaccess` - セキュリティヘッダー追加

---

## 🎯 コスト削減効果

| 項目 | 従来（AWS） | 最適化後（共用） | 削減率 |
|------|-------------|------------------|--------|
| 月額 | ¥7,800〜12,000 | ¥500〜1,000 | **92〜94%** |
| 年額 | ¥93,600〜144,000 | ¥6,000〜12,000 | **92〜93%** |
| 3年間 | ¥280,800〜432,000 | ¥18,000〜36,000 | **92〜93%** |

**3年間で約30〜40万円の削減！** 💰

---

## 🚀 次のアクション

### 即座に実行
1. **レンタルサーバー契約**
   - 推奨: ConoHa WING ベーシック (¥882/月)
   - または: さくらレンタルサーバー スタンダード (¥524/月)

2. **データベース作成**
   - コントロールパネルからMySQL作成
   - DB名: `bird_db`
   - ユーザー: `bird_user`

3. **デプロイ実行**
   ```bash
   # ローカルから
   cd /Users/kenyj/bird
   
   # バックエンドデプロイ
   ./deploy-shared-hosting.sh
   
   # フロントエンドビルド
   ./build-frontend.sh
   ```

4. **FTP/SFTPでアップロード**
   - バックエンド: `apps/api/` → サーバーの `~/bird/apps/api/`
   - フロントエンド: `frontend/out/` → サーバーの `~/public_html/`

### Cronジョブ設定
```cron
# Laravel Scheduler
* * * * * cd ~/bird/apps/api && php artisan schedule:run >> /dev/null 2>&1

# 四半期データ更新
0 2 1 1,4,7,10 * cd ~/bird/apps/api && php artisan reinfolib:import >> /dev/null 2>&1
```

### SSL設定
- Let's Encrypt（無料SSL）を有効化
- レンタルサーバーのコントロールパネルから設定

---

## 📚 参考ドキュメント

- [デプロイガイド](docs/DEPLOY_SHARED_HOSTING.md) - 詳細な手順
- [サーバー構成比較](docs/SERVER_COMPARISON.md) - コスト・性能比較

---

## ⚠️ 注意事項

### 開発環境との違い
- Docker Composeは使用しない
- Laravel Octaneは使用しない（標準PHP-FPM）
- Redisは使用しない（File Cache + Database Queue）
- PostgreSQLではなくMySQL/SQLite

### セキュリティ
- `.env`ファイルは絶対に公開しない
- `APP_DEBUG=false` を確認
- SSL証明書を必ず設定
- SSH鍵認証を使用

---

**プランA実装完了！デプロイ準備が整いました！** 🎉
