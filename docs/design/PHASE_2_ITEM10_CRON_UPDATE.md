# フェーズ2 項目10 — Cronジョブ更新（月次 → 週次ポーリング）

**作成日**: 2026-08-23
**対象**: `bird_design.md` §5.0・§9.1
**前提**: 項目8（データ取込バッチ）・項目9（更新検知ポーリング）完了済み

---

## 1. 背景

本番環境（ConoHa WING）には旧設計に基づく月次cronが直接設定されていた（`README.md`
「現在の Cron ジョブ設定」より）：

```
0 0 1 * * cd ~/bird/apps/api && php artisan app:update-muni-amounts
```

reinfolib の実際の更新は四半期ごと・不定日（固定カレンダーなし）であり、月次の
直接実行では更新タイミングを捉えられない（`bird_design.md` §0補足「更新頻度（A案）」）。
項目9で実装した `app:poll-muni-amounts-update`（週1回の更新検知ポーリング）に置き換える。

## 2. 実装方針：crontabではなくLaravelスケジューラーで管理

このプロジェクトは Laravel 13（`bootstrap/app.php` 方式、`app/Console/Kernel.php` なし）。
スケジュールは `routes/console.php` に `Schedule::command()` で定義し、サーバー側の
crontabには **`schedule:run` を毎分呼ぶ1行だけ**を置く（Laravelの標準的な運用方式）。

理由：
- スケジュールの内容（実行頻度・時刻・多重実行防止など）が**コードとしてgit管理**される。
  crontabを直接編集する運用だと、変更履歴が残らずレビューもできない。
- `QUICKSTART.md` / `DEPLOY_STEP_BY_STEP.md` では元々この方式（`* * * * * schedule:run`）
  が案内されていた一方、`README.md` の「現在の Cron ジョブ設定」と
  `docs/DEPLOY_SHARED_HOSTING.md` は旧来の「crontabに個別ジョブを直接書く」方式が
  混在しており、後者には存在しないコマンド（`php artisan reinfolib:import`）への
  参照も残っていた。今回あわせて整理した。

## 3. 実装内容

### 3.1 `apps/api/routes/console.php`

```php
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Schedule;

Schedule::command('app:poll-muni-amounts-update')
    ->weeklyOn(1, '21:00') // 1 = 月曜
    ->timezone('Asia/Tokyo')
    ->withoutOverlapping(720) // 720分(12時間)。取込ウィンドウより長く確保しつつ多重実行を防止
    ->onFailure(function (): void {
        Log::error('app:poll-muni-amounts-update がスケジュール実行で失敗しました（前回データのまま配信継続）');
    });
```

**時刻の根拠**：`bird_design.md` §5.1「取込ジョブ実行時間帯」は
「21:00〜翌08:00に完了する想定」。ポーリングが新四半期を検知した場合、
同じプロセス内で即座に取込バッチ（`app:update-muni-amounts`）を起動する
（項目9の設計）。したがってポーリング自体も21:00開始とし、取込がそのまま
21:00始まりの想定ウィンドウに収まるようにした。

**タイムゾーンの扱い**：アプリ全体のタイムゾーン（`config('app.timezone')`）は `UTC` の
ままとし（他機能への影響を避けるため変更しない）、このスケジュールにのみ
`->timezone('Asia/Tokyo')` を明示した。

**`withoutOverlapping(720)` の根拠**：`bird_design.md` §5.1 の所要時間見込みは
「十数分〜1時間以内」。720分(12時間)は明らかに長い上限だが、万一の異常（API側の遅延・
ネットワーク問題等）でプロセスが長時間ハングした場合でも、次の週次実行までには
確実にロックが解放されるようにするための安全弁として設定した。

`php artisan schedule:list` で登録内容を確認済み：

```
0 12 * * 1  php artisan app:poll-muni-amounts-update  Next Due: 1 day from now
```

（`0 12 * * 1` はUTC表記。JST 21:00 = UTC 12:00 で一致していることを確認）

### 3.2 サーバー側crontabの切替（要手動対応）

このリポジトリのコード変更だけでは、ConoHa WING上の実際のcrontabは自動的には
切り替わらない。`README.md`「🔧 トラブルシューティング」→「Cron ジョブ確認」節に、
SSH経由での切替手順（旧ジョブ削除 → `* * * * * schedule:run` の1行を登録 → 確認）を
追記した。**この手順のサーバー側での実施はユーザー側の作業として残る**（本エージェントは
本番サーバーへのSSHアクセスを持たないため）。

### 3.3 既存ドキュメントの整合性修正

以下、旧来の「crontabに四半期ジョブを直接書く」記述および存在しないコマンド
（`php artisan reinfolib:import`）への参照を、「`schedule:run` の1行のみで足りる」旨に修正：

| ファイル | 修正内容 |
|---|---|
| `README.md` | 「現在の Cron ジョブ設定」を月次直接実行→週次ポーリングへの切替手順に更新 |
| `DEPLOY_STEP_BY_STEP.md` | 存在しない `reinfolib:import` の四半期cron行を削除し、`schedule:run`のみに整理 |
| `docs/DEPLOY_SHARED_HOSTING.md` | 同上 |
| `QUICKSTART.md` | 既に `schedule:run` のみの記述だったため変更なし |
| `bird_design.md` §9.1 | 旧cron設定の記載に「項目10で置き換え済み」の注記を追加 |

## 4. 動作確認方法

- `php -l routes/console.php` で構文確認。
- `php artisan schedule:list` で登録内容・次回実行時刻（JST 21:00と一致するUTC表記か）を確認。
- 実際に週次実行させての確認（および `app:poll-muni-amounts-update` が実APIキーで
  正しく動くか）は、本番相当の環境で `schedule:run` を手動実行するか、実際の月曜21:00の
  実行を待って `storage/logs/laravel.log` を確認すること。

## 5. フェーズ2 完了状況（最終）

| 項目 | 内容 | 状態 |
|---|---|---|
| 項目7 | バックエンドAPIエンドポイント実装 | 完了（`PHASE_2_ITEM7_BACKEND_API.md`） |
| 項目8 | データ取込バッチ実装（§5.1） | 完了（`PHASE_2_ITEM8_REINFOLIB_CLIENT_FIX.md`） |
| 項目9 | 更新検知ポーリング（§5.0） | 完了（`PHASE_2_ITEM9_UPDATE_DETECTION_POLLING.md`） |
| 項目10 | Cronジョブ更新（月次 → 週次） | 完了（本ドキュメント。**本番crontabの実切替は要手動対応**） |

フェーズ2の残タスク（`bird_design.md` §9.3）はすべてコード上は完了。次はフェーズ3
（フロント統合・E2Eテスト・実APIキーでの疎通確認・本番crontab切替の実施）。
