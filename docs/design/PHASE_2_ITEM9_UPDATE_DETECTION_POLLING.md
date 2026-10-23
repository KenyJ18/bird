# フェーズ2 項目9 — 更新検知ポーリングの実装

**作成日**: 2026-08-23
**対象**: `bird_design.md` §5.0（更新検知ポーリング）
**前提**: 項目8（データ取込バッチ、`PHASE_2_ITEM8_REINFOLIB_CLIENT_FIX.md`）完了済み

---

## 1. 背景・要件

`bird_design.md` §5.0 の要件：

- reinfolib の取引価格情報は四半期ごと・不定日更新のため、日次実行はしない。
- **週1回**、1都3県の各都道府県（`area`＝11/12/13/14）を軽量プローブし、次四半期の
  データ出現を検知する。
- 判定条件：4件のうち**いずれか1つでも200**なら「公開済み」とみなし取込ジョブを起動。
  全て404なら次回ポーリングまで待機。
- 二重取込防止：取込済み四半期は `snapshot_meta.period` で記録し、同一四半期の再取込をスキップ。
- **部分公開への保険（段階公開対策）**：reinfolib が地域を分けて段階公開した場合、
  未公開だった県は取込時に404（0件）となり、その市区町村が次四半期まで
  グレーアウトのまま残る恐れがある。保険として、取込時に404だった県を記録し、
  **次回ポーリングで穴埋め再取込（冪等upsert）**する。1都3県が揃った時点で当該四半期を
  「完了」とする。

## 2. 設計上の課題：項目8だけでは「部分公開への保険」を満たせない

項目8で実装した `ImportMuniAmountsUseCase::fixSnapshot()` は、取込が成功すると
（一部都道府県が404で欠けていても）`snapshot_meta.period` を対象四半期に**無条件で**
更新する。そのため、素朴に「`snapshot_meta.period` の次四半期をプローブする」だけの
ポーリングだと、一部都道府県が欠けたまま次の四半期の検知に進んでしまい、
「1都3県が揃うまで同一四半期を穴埋め再取込する」という設計要件を満たせない。

### 採用した解決策：新規テーブルを持たず `muni_amount` から充足状況を導出する

`muni_amount.muni_code` の先頭2桁は都道府県コードと一致する。取込ジョブが成功していれば、
その都道府県のいずれかの市区町村・データ種類・価格区分の組み合わせで最低1行は
`muni_amount` に保存されているはずである（「都道府県全体で取引0件はまず起きない」という
§5.0 の前提と同じ理由で、都道府県全体で0行なら「その都道府県は未取得」とみなせる）。

これにより、取込の進捗を記録する専用テーブルを新設せずに、既存の `muni_amount` へ
`SELECT DISTINCT SUBSTR(muni_code, 1, 2)` するだけで「現在のスナップショット期間に
1都3県すべて含まれているか」を判定できる。

```php
// EloquentMunicipalityAmountRepository::coveredPrefectureCodes()
return MunicipalityAmountModel::query()
    ->where('period', $period->value())
    ->selectRaw('DISTINCT SUBSTR(muni_code, 1, 2) AS prefecture_code')
    ->pluck('prefecture_code')
    ->all();
```

`SUBSTR` はMySQL・SQLiteどちらでも同じ構文で動くため、DB非依存で書ける。

## 3. 判定ロジック（2段階）

`PollMuniAmountsUpdateUseCase::execute()` が毎回この2段階で判定する。

```
1. snapshot_meta が空か？
   → 空なら ACTION_NOT_INITIALIZED（先に app:update-muni-amounts の手動初回実行が必要）

2. snapshot_meta.period（現在のスナップショット期間）は1都3県すべて
   muni_amount に存在するか？（coveredPrefectureCodes で判定）
   → 揃っていない場合 ACTION_REIMPORT_GAPS
      同じ period のまま app:update-muni-amounts を再実行する（冪等upsertなので安全）
      ※ 新たなプローブは行わない。「既に公開されているはずの四半期」の欠けを埋めるだけなので、
        改めて404/200を確認する必要がない

3. 揃っている場合、次の四半期（period.next()）を1都3県それぞれプローブする
   → いずれか1つでも200（データあり） → ACTION_NEW_PERIOD_DETECTED
      （probeAreaHasData で軽量プローブ。本文は取得・パースしない）
   → 全て404 → ACTION_NONE（次回ポーリングまで待機）
```

「二重取込防止」は、上記の通り「揃った期間は二度とプローブ・再取込されない」構造そのもの
によって自然に満たされる（`snapshot_meta.period` を専用フラグとして別途チェックする実装は
不要）。

## 4. 実装ファイル一覧

| ファイルパス | 種別 | 役割 |
|---|---|---|
| `app/Console/Commands/PollMuniAmountsUpdateCommand.php` | 新規 | Artisanコマンド `app:poll-muni-amounts-update` 本体。判定結果に応じて取込コマンドを起動 |
| `src/Application/MuniAmountBatch/UseCase/PollMuniAmountsUpdateUseCase.php` | 新規 | 判定ロジック本体（上記2段階） |
| `src/Application/MuniAmountBatch/Dto/PollDecision.php` | 新規 | 判定結果DTO（`not_initialized`/`reimport_gaps`/`new_period_detected`/`none`） |
| `src/Domain/Reinfolib/Repository/ReinfolibApiClientInterface.php` | 修正 | `probeAreaHasData()` を追加 |
| `src/Infrastructure/ExternalApi/ReinfolibApiClient.php` | 修正 | `probeAreaHasData()` の実装（本文を読まず200/404のみ判定。軽量リトライ2回、429は60秒待機） |
| `src/Domain/MunicipalityAmount/Repository/MunicipalityAmountRepositoryInterface.php` | 修正 | `coveredPrefectureCodes()` を追加 |
| `src/Infrastructure/Repository/EloquentMunicipalityAmountRepository.php` | 修正 | `coveredPrefectureCodes()` の実装 |

## 5. コマンドの処理フロー

```
php artisan app:poll-muni-amounts-update
    │
    ▼
PollMuniAmountsUpdateUseCase::execute() → PollDecision
    │
    ├─ not_initialized     → 警告ログ・何もしない（正常終了）
    ├─ reimport_gaps       → Artisan::call('app:update-muni-amounts', ['--period' => 現行period])
    ├─ new_period_detected → Artisan::call('app:update-muni-amounts', ['--period' => 次period])
    └─ none                → 何もしない（正常終了）
```

取込コマンド本体（`app:update-muni-amounts`）はプロセス内で `Artisan::call()` により
直接起動する（別プロセスを立てない）。取込ジョブが失敗した場合、項目8の実装により
DBトランザクションがロールバックされ、`snapshot_meta` は更新前のまま維持される
（可用性優先）ため、次回ポーリングで同じ判定（`reimport_gaps` または
`new_period_detected`）が再度下り、自然にリトライされる。

## 6. 動作確認方法（実データ未使用）

実際のAPIキーを使わず、フェイクの `ReinfolibApiClientInterface` 実装に差し替えて
`php artisan tinker` 上で以下を確認済み：

1. `PollMuniAmountsUpdateUseCase::execute()` の4分岐すべて：
   - `snapshot_meta` が空 → `not_initialized`
   - 現行期間が1都3県のうち1県欠けている → `reimport_gaps`（欠けている県のコードも正しく取得）
   - 現行期間が揃っており、次四半期を1県だけ200が返す → `new_period_detected`（`period.next()` も正しい）
   - 現行期間が揃っており、次四半期が全て404 → `none`
2. 一連の流れ：新四半期検知 → 取込実行（1都道府県分のみデータが返るフェイク）→
   `snapshot_meta.period` が更新される → 同じ期間で再度ポーリング →
   欠けている3都道府県が正しく検出され `reimport_gaps` になる（部分公開への保険が
   実際に機能することを確認）
3. `coveredPrefectureCodes()` がSQLite上で `SUBSTR` を使った意図通りの結果を返すこと

実際のReinfolib APIキーを使った疎通確認（`probeAreaHasData` の実際の200/404応答、
本番相当の週次ポーリング動作）は、本番環境またはAPIキーが利用可能な環境で別途行うこと。

## 7. 既知の未対応事項（次フェーズ以降）

- Cronジョブへの登録（週1回 `app:poll-muni-amounts-update` を実行するよう設定）は
  項目10の範囲。本項目ではコマンドの実装のみ。
- 「部分公開のまま何四半期分も揃わない」という極端なケースへのアラート通知は未実装
  （設計書でも想定されていない。実運用ログで様子を見て必要なら別途検討）。

## 8. フェーズ2 残タスクとの関連（更新）

| 項目 | 内容 | 状態 |
|---|---|---|
| 項目7 | バックエンドAPIエンドポイント実装 | 完了（`PHASE_2_ITEM7_BACKEND_API.md`） |
| 項目8 | データ取込バッチ実装（§5.1） | 完了（`PHASE_2_ITEM8_REINFOLIB_CLIENT_FIX.md`） |
| 項目9 | 更新検知ポーリング（§5.0） | 完了（本ドキュメント） |
| 項目10 | Cronジョブ更新（月次 → 週次） | 未着手（次の作業） |
