# 機密情報漏洩対策ルール（bird プロジェクト）

対象読者: bird プロジェクトに初めて触れる人、Claude Code などのAIセッションでこのリポジトリを操作する人。
最終更新: 2026-09-21

---

## 0. まず結論だけ知りたい人へ

- birdでは `.env` に **reinfolib APIキー・DBパスワード・SSH秘密鍵** など本物の機密情報が入っている
- これをAIセッション（Claude Code）や Git に漏らさないために、**4層の防御**を組み合わせている
- 2026-09-21、そのうち1層（Bashフック）が設定ミスで機能せず、**実際にAPIキーが会話に漏洩する事故が発生した**（詳細は§5）
- 「絶対に破られない対策」は存在しない。だからこそ**複数の層を重ねる**（多層防御）ことと、**漏れたら即キーを失効・再発行する**運用をセットで行っている

---

## 1. 何を守っているか

| 機密情報 | 保存場所 | 使いみち |
|---|---|---|
| `REINFOLIB_API_KEY` | `apps/api/.env`（ローカル）/ GitHub Secrets → 本番 `.env`（デプロイ時） | 不動産情報ライブラリAPIへの認証ヘッダ |
| `DB_PASSWORD` / `DB_USERNAME` | `apps/api/.env` | 本番MySQLへの接続 |
| `APP_KEY` | `apps/api/.env` | Laravelのデータ暗号化キー |
| `CONOHA_SSH_*`（ホスト・ユーザー・秘密鍵） | GitHub Secrets | 本番サーバーへのデプロイ用SSH接続 |

過去には `frontend/.next/dev/` 配下に自動生成された暗号化キーがGit履歴に混入していたことがあり、2026-08-11に [BFG Repo-Cleaner](https://rtyley.github.io/bfg-repo-cleaner/) で履歴ごと削除している（`SECURITY_AUDIT_REPORT.md` 参照）。**「AIセッション中の漏洩」と「Gitへの漏洩」は別問題であり、両方に対策が必要**というのがこのプロジェクトの前提。

---

## 2. 想定している漏洩ルート（脅威モデル）

| 経路 | 内容 | 例 |
|---|---|---|
| **経路A** | Claude Codeが `Read` ツールで `.env` を直接読み込み、内容が会話に乗る | `.env` ファイルをそのままReadしようとする |
| **経路B** | 人間がAI CLI起動時に `.env` の中身を手でペーストしてしまう | 「このエラー直して」と一緒に`.env`をコピペ |
| **経路C** | `Bash` ツール経由で間接的に中身が表示される | `cat .env`, `grep API_KEY .env`, `printenv` |
| **経路D** | `git commit` 時に `.env` の内容が誤って記録される | `.gitignore` 漏れ、`git add -A` の事故 |
| **経路E**（2026-09-21判明） | Bashフックの検知ルールを迂回する間接呼び出し | `php artisan tinker` から `env()`/`config()` で値を読んで `echo` する |

---

## 3. 防御レイヤー全体像

```
┌──────────────────────────────────────────────────────────────┐
│ Layer C: シェルの preexec フック（~/.zshrc、グローバル設定）  │
│ claude / gh copilot 起動前に「.envに値が入ってるよ」と警告    │
│ → 経路B（人間のコピペ事故）対策                               │
├──────────────────────────────────────────────────────────────┤
│ Layer A: permissions.deny（.claude/settings.json）            │
│ Claude Codeの Read ツールで .env 系ファイルを開くこと自体を禁止│
│ → 経路A対策                                                    │
├──────────────────────────────────────────────────────────────┤
│ Layer B: PreToolUse フック（Claude Code / Copilot CLI共通）    │
│ Bash ツールで実行しようとしたコマンド文字列を検査し、          │
│ .env の中身や環境変数を表示しそうなものをブロック              │
│ → 経路C・経路E対策                                             │
├──────────────────────────────────────────────────────────────┤
│ Layer 0: .gitignore + gitleaks                                 │
│ .env系ファイルをGit管理外にし、コミット前後でスキャンする      │
│ → 経路D対策                                                    │
└──────────────────────────────────────────────────────────────┘
＋ 本番運用: GitHub Secrets + デプロイワークフロー（§4.5）
  → 「そもそも本物のキーがAIの手元に来ないようにする」設計
```

**ポイント（初心者向け）**: 経路ごとに担当するレイヤーが違う。1つのレイヤーで全部を防ごうとしていない。だから「このレイヤーは何の経路を防ぐためのものか」を意識すると理解しやすい。

---

## 4. 各レイヤーの詳細

### 4.1 Layer 0: Gitに載せない（コミット時点の防止・経路D）

- **`.gitignore`**（リポジトリルート）
  ```
  .env*.local
  .env
  .envrc
  .env.shared-hosting
  .credentials.local        # 実際の認証情報。.credentials.local.example がテンプレート
  SECURITY_AUDIT_REPORT.md
  ```
- **`apps/api/.gitignore`** にも個別に `.env` が記載されており、二重に除外されている
- **gitleaks**: コミット前後に機密情報らしき文字列（APIキー・暗号化キー等）を検出するスキャナ。2026-08-11時点で「Git履歴: no leaks found」を確認済み
- テンプレート運用: 実際の値が入るファイルには必ず `.example` サフィックスの空テンプレートを用意する（`apps/api/.env.example`, `.credentials.local.example`）。**AIはテンプレートファイルを読んでよい**（値が入っていないため）

> 初心者向け補足: `.gitignore` に書いてあるファイルは「これから `git add` してもGitに追跡されない」だけ。**すでに一度コミットしてしまった機密情報は `.gitignore` に追記しても消えない**（履歴に残り続ける）。もし誤ってコミットした場合は、単に取り消すだけでなく BFG や `git filter-repo` で履歴ごと消し、キーも再発行する必要がある。

### 4.2 Layer A: AIの「Read」を直接禁止する（経路A）

#### Claude Codeのルール（従来からの設定）

`.claude/settings.json`:
```json
{
  "permissions": {
    "deny": [
      "Read(**/.env)",
      "Read(**/.env.local)",
      "Read(**/.env.production)",
      "Read(**/.env.staging)",
      "Read(**/.credentials.local)"
    ]
  }
}
```

これはClaude Code専用の「宣言的なブロック」で、Claude Code自体が実行前に拒否する。実際に試すと次のようにエラーになる：

```
Read(/Users/.../apps/api/.env)
→ File is in a directory that is denied by your permission settings.
```

#### Copilotの同等ルール（2026-09-22追加）

Copilot CLIは `.claude/settings.json` の `permissions.deny` をClaude Codeと同じ意味では使わない。
そのため、Copilot向けに `.github/hooks/secret-guard.json` を追加した。この設定はCopilotが
ファイルを読む直前に `.claude/hooks/copilot-read-secret-guard.sh` を実行し、Claude Codeと同じ
`.env`／`.env.local`／`.env.production`／`.env.staging`／`.credentials.local` を拒否する。

| AI | Layer Aで使う設定 | 拒否するタイミング |
| --- | --- | --- |
| Claude Code | `.claude/settings.json` の `permissions.deny` | `Read` ツールを呼ぶ前 |
| Copilot CLI / cloud agent | `.github/hooks/secret-guard.json` の `PreToolUse`（`Read`） | ファイル読取ツールを実行する前 |

初心者向けに言い換えると、**設定ファイルの形式はAIごとに違っても、「本物の `.env` は読ませず、
値のない `.env.example` だけは読める」というルールは同じ**である。Copilot CLIでは設定を変更した後に
再起動し、リポジトリフックを信頼する確認を承認してから有効になる。

**このレイヤーの強み**: 機密ファイルを開く操作そのものを実行前に止める。
**このレイヤーの弱み**: `Read` ツールだけではBash経由の `cat` を止められない → だからLayer Bが必要。

### 4.3 Layer B: Bashコマンド経由の間接アクセスを検査する（経路C・経路E）

`.claude/hooks/secret-guard.sh` が、`Bash` ツールが実行しようとしているコマンド文字列を毎回検査する。
当初この仕組みはClaude Code専用で、`.claude/settings.json` から直接このスクリプトを呼んでいた。
Copilot CLI／Copilot cloud agentには2026-09-22に `.github/hooks/secret-guard.json` を追加し、
`.claude/hooks/copilot-secret-guard.sh` を経由して同じ判定スクリプトを呼ぶようにした。

ラッパーは検知ルールを持たず、Claude Codeの `{"decision":"block"}` をCopilotが理解する
`{"permissionDecision":"deny"}` に言い換えるだけである。したがって、**Bashコマンドの検知ルールは
`secret-guard.sh` だけで一元管理する**。ルールを追加・修正するときはこのファイルを変更すれば、
Claude CodeとCopilotの両方に反映される。

Copilot CLIはフック設定を読み込んだ後に再起動し、リポジトリ設定の信頼確認を承認する必要がある。
Copilotのコマンドフックは異常終了時には拒否するが、タイムアウト時は通常の権限フローへ戻るため、
本フックはネットワークアクセスをせず5秒以内に完了する構成とする。**2026-09-22時点のルール一覧：**

| # | 検知対象 | 具体例 | 目的 |
|---|---|---|---|
| 1 | `.env` を直接表示・ダンプするコマンド | `cat .env`, `od -c .env`, `xxd .env`, `strings .env`, `base64 .env` など | 生の中身をそのまま画面に出す手段を広く塞ぐ |
| 2 | `.env` に対する `grep` | `grep API_KEY .env` | 値だけを抜き出す手口を防ぐ |
| 3 | 全環境変数の一括表示 | `printenv`, `env`, `set`, `export -p`, `declare -x` | 単体実行すると無関係な変数まで全部出てしまうものを防ぐ |
| 4 | `tinker` 経由の機密アクセス（直接・間接どちらも） | `tinker --execute="echo env('KEY')"`, `tinker --execute="echo config('services.reinfolib.api_key')"` | Laravelの `env()`/`config()` はどちらも `.env` の値をそのまま返すため、両方をキーワードで検知 |
| 5 | インタプリタのワンライナー経由 | `php -r "..."`, `python3 -c "..."`, `node -e "..."` の中に `.env` やキーワード（`api_key`, `password`, `secret`, `token` 等）が含まれる場合 | 汎用言語での読み出しを一般的に塞ぐ |

`.env.example` は値が入っていないテンプレートなので、ルール1・2からは除外している（`cat .env.example` は通す）。

**「フェイルクローズ」方針（2026-09-21改訂）**: 入力のJSONが壊れている、`python3` が使えない、などスクリプト自身が正常に判定できない場合は「わからないから通す」ではなく「わからないから止める」を徹底している。

### 4.4 Layer C: 人間への警告（~/.zshrc、経路B）

Claude Codeのセッションが直接見るファイルではなく、**ターミナルを使う人間向け**の注意喚起。`claude` や `gh copilot` を起動する直前に、カレントディレクトリに値の入った `.env` 系ファイルがあれば警告を出す：

```
[secret-guard] WARNING: 機密ファイルを検出しました: .env
[secret-guard] これらのファイルの内容をプロンプトに含めないでください。
```

コメント行・空行・`<...>`のようなプレースホルダは無視するので、テンプレートファイルでは警告が出ない。**これはブロックではなく注意喚起のみ**（人間の操作を止める権限はシェルフックにはないため）。

### 4.5 本番運用: そもそも本物のキーをAIの手元に置かない（GitHub Secrets + デプロイワークフロー）

上記4層はすべて「AIセッションの近くに本物のキーがある前提」での防御。もう一つの根本的なアプローチが、**本番用の本物のキーをAIセッションの目に触れる場所に一切置かない**という設計：

1. `REINFOLIB_API_KEY` の正ソースは **GitHub Actions Secrets** にのみ存在する
2. `apps/api/.env.example` にはキー名だけを記載し、値は空
3. `.github/workflows/deploy.yml` が手動実行（`workflow_dispatch`）されると、SSH経由で本番サーバーの `apps/api/.env` に直接書き込む（Claude Codeもリポジトリの他のファイルもこの値を経由しない）
4. 書き込み後は `chmod 600` でオーナーのみ読み取り可能にし、`config:cache` を再生成する

**ローカル開発用のキー**（今回漏洩したもの）は、この経路とは別に開発者が手元の `apps/api/.env` に個別設定するものであり、ローカルでのAI操作の対象になり得る。だからこそLayer A〜Cのローカル防御が必要になる。

---

## 5. 2026-09-21に実際に起きた事故

### 何が起きたか

疎通確認作業中、以下のコマンドがLayer Bの検知ルール（当時: `tinker.*env\(` の正規表現1本のみ）を**すり抜け**、実際のAPIキーの値がそのままAIとの会話に出力された。

```bash
php artisan tinker --execute="echo env('REINFOLIB_API_KEY');"
```

### なぜすり抜けたか（根本原因）

1. `.claude/settings.json` のフック登録が**相対パス**だった：
   ```json
   "command": "bash .claude/hooks/secret-guard.sh"
   ```
2. 直前の操作でBashツールのカレントディレクトリが `apps/api/` にドリフトしていた
3. 相対パスのため、実際には存在しない `apps/api/.claude/hooks/secret-guard.sh` を探しに行き、**スクリプトの起動自体に失敗**した
4. Claude Codeの仕様上、**PreToolUseフックが起動すら出来なかった場合はフェイルオープン（そのまま処理続行）がデフォルト動作**（[公式ドキュメント](https://code.claude.com/docs/en/hooks)）
5. 結果、本来ブロックされるはずのコマンドがそのまま実行され、キーが漏洩した

副次的な要因として、`.claude/settings.local.json` に `Bash(php artisan *)` / `Bash(php *)` が無条件allowlist登録されており、**Layer Bが唯一の砦になっていた**（人間の承認やAI自動判定という二段目のチェックが最初から働かない構成だった）ことも挙げられる。

### 対応したこと

1. **キーの再発行を依頼**（漏洩したキーはコードを直しても無効化されないため、必ず失効・再発行が必要）
2. フックの登録パスを `${CLAUDE_PROJECT_DIR}` による絶対パスに修正 → cwdドリフトで無効化されなくなった
3. スクリプト内部でも「判定できない時はブロック」を徹底（python3不在・JSON解析失敗時など）
4. 検知ルールを拡充（`config()` 経由の間接アクセス、`od`/`xxd`/`strings`等の生表示コマンド、インタプリタのワンライナーを追加）
5. 12パターンのテストケースで正しくブロック/通過することを確認（誤検知なし）

**参考**: これは記録に残っている中で2件目の漏洩事故（1件目は2026-08-11に対応したGit履歴への暗号化キー混入）。事故の種類が毎回異なる（1件目=Git履歴、2件目=AIセッション内の間接アクセス）ことからも、**単一のレイヤーだけに頼らず多層防御を維持し続けることが重要**だと分かる。

---

## 6. 早見表：やっていいこと・だめなこと

| 状況 | やっていいこと | だめなこと |
|---|---|---|
| キー名を知りたい | `apps/api/.env.example` を見る（値は空） | `apps/api/.env` を `cat`/`Read` する |
| APIの動作確認をしたい | `php artisan reinfolib:check` のような**専用の確認コマンド**を使う | `tinker` で `env()`/`config()` を直接 `echo` する |
| 環境変数の存在確認をしたい | `config('...') ? 'SET' : 'NOT SET'` のように**真偽値・長さだけ**を確認する | 値そのものや、先頭/末尾の文字列を出力する（部分的でも漏洩とみなす） |
| 全体の環境を確認したい | 個別の変数名を指定して存在有無を聞く | `printenv`/`env`/`set` を単独実行する |
| デバッグ中にキーを貼る必要がありそうな時 | チャットで「専用コマンドで確認しますか？」と提案する | チャットに直接ペーストする、コード中にハードコードする |

---

## 7. 漏洩に気づいたときの対応手順

1. **即座に発行元でキーを失効・再発行する**（コードやフックの修正では過去に漏れた値そのものは無効化されない）
2. 漏洩した具体的な経路（どのコマンド・どのルールの抜け穴か）を特定する
3. `.claude/hooks/secret-guard.sh` に検知ルールを追記し、テストケースを追加して回帰確認する
4. 本ファイルと `bird_design.md` に事故内容・対応内容を追記する（次に同じ穴を踏まないため）
5. 本番環境にも影響する場合は、本番 `.env` の更新と `config:cache` の再生成を忘れずに行う

---

## 8. このルールの限界（正直に書いておく）

- Layer Bは**正規表現によるブロックリスト方式**であり、原理的に「完全に安全」ではない。未知のコマンド・言語・エンコード手段での読み出しは検知できない可能性が残る
- `Bash(php *)` のように広いallowlistが設定されていると、Layer Bが唯一の防波堤になってしまう。恒久的にはallowlistを絞るか、後述のサンドボックスを併用するのが望ましい
- **恒久対策（2026-09-22に設定準備）**: Layer A/BはAIが危険な操作をしないようにする
  ルールだが、サンドボックスはOSが「そもそも本物の値をコマンドに渡さない」ため、さらに強い。
  birdでは実キーを `.env` ではなくmacOS Keychainに保存し、zshが必要なときだけ
  `REINFOLIB_API_KEY` として読み込む方式へ移行する。
  `scripts/configure-local-secret-sandbox.sh` は、次の安全な設定をユーザーの
  `~/.claude/settings.json` にマージする（`mask` はリポジトリ設定では有効にならないため、
  ユーザー設定が必要）。
  - `sandbox.credentials.envVars` の `REINFOLIB_API_KEY` を `mask` にする。
    sandbox内のコマンドは本物ではなく置換値だけを見る
  - 通信先が `www.reinfolib.mlit.go.jp` のときだけ、サンドボックスプロキシが本物の値を
    認証ヘッダへ差し替える（`injectHosts`）
  - TLS終端、strict sandbox、未対応環境での失敗を有効にする。安全にサンドボックスを
    起動できない場合は、保護なしで実行しない

  **開発者が一度だけ行う手順**:
  1. macOSの「キーチェーンアクセス」で、アカウントを自分のユーザー名、サービス名を
     `bird.reinfolib-api-key`、パスワードを実APIキーとして「汎用パスワード」を作成する。
     キーをAIチャットやリポジトリのファイルに貼り付けない。
  2. リポジトリルートで `bash scripts/configure-local-secret-sandbox.sh` を実行する。
     スクリプトはキーの値を表示・保存せず、Keychainの項目が存在することだけを確認する。
  3. ローカルの `.env` から `REINFOLIB_API_KEY` の行をエディタで削除する。削除時も
     値をコピーしたりターミナルへ表示したりしない。
  4. ターミナルとClaude Codeを再起動して `/sandbox` を開き、sandboxが有効で
     `REINFOLIB_API_KEY` がmask設定になっていることを確認する。

  Docker ComposeはAPI・queue・schedulerへOS環境変数を引き渡すため、ローカルDocker開発でも
  `.env` にキーを残す必要はない。Copilot CLIは独自のローカルサンドボックスを使うため、
  `.github/hooks/secret-guard.json` のLayer A/Bも引き続き有効にする。

---

## 9. 変更履歴

| 日付 | 内容 |
|---|---|
| 2026-08-11 | Git履歴から機密ファイルを削除（BFG）、3層防御（Layer A/B/C）の初期実装、gitleaksスキャン導入 |
| 2026-08-15 | reinfolib APIキー管理体制整備（GitHub Secrets運用開始、デプロイワークフロー作成） |
| 2026-09-21 | `tinker` 経由の間接アクセスがLayer Bをすり抜け実キーが漏洩する事故発生。フックを絶対パス化・フェイルクローズ化・ルール拡充。本ドキュメント作成 |
| 2026-09-22 | Copilot CLI／cloud agent用の `.github/hooks/secret-guard.json` を追加。Layer A（直接読取）ではClaude Codeと同じ機密ファイルを拒否し、Layer B（Bash検査）ではClaude Codeと同一の検知ルールを共用してCopilotの拒否形式へ変換するラッパーを追加 |
| 2026-09-22 | KeychainからOS環境変数へ安全に移行し、Claude Code sandboxの`REINFOLIB_API_KEY`をmaskする `scripts/configure-local-secret-sandbox.sh` を追加。実キーのKeychain登録と`.env`からの削除は開発者が手動で行う |

---

## 関連ドキュメント

- `bird_design.md` §9.4（APIキー管理・AIプロンプト漏洩防止の設計背景）
- `SECURITY_AUDIT_REPORT.md`（2026-08-11のGit履歴クリーンアップの詳細）
- `docs/design/PHASE_2_ITEM8_REINFOLIB_CLIENT_FIX.md` §7.4（実キーでの動作確認方法）
