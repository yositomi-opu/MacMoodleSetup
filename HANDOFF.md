# Development handoff

Updated: 2026-10-05

## Scope

Independent macOS-local Moodle + STACK setup. No imports, mounts, config or runtime
services from any MCQ editor repository. Dedicated Compose project macmoodlesetup,
loopback port 8088; database and Maxima are internal only.

## Implementation

- Make targets: setup, start, stop, restart, check, status, logs, open, credentials, backup.
- Moodle 4.5.15 (Build 20261005), STACK 4.13.1 and four required plugins; upstream
  revisions pinned in docker/sources.txt. PHP 8.3, PostgreSQL 16, GoeMaxima
  2026080600 images pinned by digest. PHP/Maxima use linux/amd64 on Apple Silicon.
- Setup generates private .env only if absent, initializes a new DB or runs the
  normal Moodle upgrade path. It stops cron before DB upgrades; no data reset.
- First configuration enables STACK server connection, MathJax/multilang filters,
  disables activity auto-linking and outbound email. Subsequent setup preserves settings.
- Persistent dedicated DB/moodledata volumes. Backup stops writers, uses pg_dump
  and tar with overridden entrypoint to avoid startup-log contamination of archive.
- README documents prerequisites, startup, data storage, credentials, limits and backup.
- Publication target: https://github.com/yositomi-opu/MacMoodleSetup .
  User created the empty public repository and authorized publication. Added clone
  instructions; credentials and backup data remain ignored. Check Git remote/history
  for current push status.

## Verification actually performed

On an Apple Silicon Mac with Docker Desktop:

- bash -n scripts/manage.sh docker/bootstrap.sh: passed.
- make setup start: built image, installed all Moodle/STACK DB schemas, started cron.
- make setup start repeated: Moodle reported no upgrade needed; credentials retained.
- make backup, make start, make check: passed. Verified database.dump PGDMP header
  and opened/read every tar archive entry using Python tarfile (329 entries).
- STACK official stack_cas_healthcheck passed: CAS connection, Unicode, plots,
  JSXGraph, Parsons, free text, GeoGebra, optional libraries and expected STACK
  Maxima library version 2026080600. Actual Maxima version reported 5.44.0.
- Real browser: home page, login page and successful admin dashboard login.
- Existing unrelated STACK API containers remained running on their existing port.
- git diff --check passed before initial commit.

Initial development found and fixed use of unavailable PDO PostgreSQL driver (use
pg_connect), unsupported install_database --non-interactive flag (removed), and
entrypoint text contaminating backup tar output (override entrypoint).
An earlier invalid test backup was marked FAILED; the subsequent backup is verified.

## Remaining checks / future work

- Intel Mac and a second clean Mac have not been tested.
- No course/question XML import or live quiz grading performed in this new Moodle;
  official STACK healthcheck and browser admin login are the actual checks above.
- No automatic backup restore; never overwrite existing volumes without approval.
- Japanese language pack can be installed via Moodle admin UI; not preinstalled.
- Backup restoration, OS upgrade and production/public hosting have not been tested.
- Source/image updates must be reviewed together, backed up and retested. .env and
  backups are ignored and must never be committed.

## MoodleSTACKSetupへの名称変更（2026-10-27）

表示名をMoodleSTACKSetupへ変更。MacとWindowsの両対応を目指すが、現在の管理スクリプトはmacOS専用であり、Windows対応・実機検証は未実施。

GitHub側の改名は完了。URLは `https://github.com/yositomi-opu/MoodleSTACKSetup`。
既存の作業フォルダー、Composeプロジェクト名・イメージ名・ボリューム名、NASとMacの同期識別子は保持する。
DB・moodledata・稼働サービスは変更しない。既存の5.2対応等の未コミット作業は今回の名称変更commitに含めない。

NASの開発コードはMacBookなしで編集できる。現在のMoodle実行環境はMacBook上にあるため、実機確認にはMacBookの起動・接続とDocker Desktopが必要。
NAS上でのMoodle起動とWindowsでの実行は未検証。

検証: 文書変更のみ。git diff --checkを実施。setup・再起動・DBアップグレード・ブラウザ操作は実施しない。

## GitHubリポジトリ改名完了（2026-10-27）

- GitHub APIでリポジトリIDが同一のまま `yositomi-opu/MoodleSTACKSetup` へ改名されたことを確認。
- READMEのclone URLとNAS側originを新URLへ更新。既存の作業フォルダー、同期識別子、Dockerリソース名は保持。
- 名称変更に関する文書2ファイルだけをcommit・公開対象とし、既存の未コミット作業は保持。
- git diff --check成功。文書とGit接続先のみの変更で、サービス再起動・DB更新・ブラウザ検証は実施していない。
- Mac側cloneのGit remoteは今回未更新。必要時に新URLへ変更する。Mac側へのコード同期・配備は今回行わない。

## Windows導入候補（2026-10-31）

- 公開mainを基準に、Windows PowerShell/cmd用moodle.cmdと標準ライブラリーだけのscripts/manage.pyを追加。MakefileはOS=Windows_NTでPythonへ分岐し、Macの既存Bash処理は維持。WindowsはMake/Bash/OpenSSL不要。doctorでDocker ComposeとLinuxエンジンを確認し、OSやDocker Desktopの設定変更・自動インストールはしない。
- setup/start/stop/restart/check/status/logs/open/credentials/backupに対応。複数操作は失敗時に中断。既存Composeプロジェクト・ポート・ボリューム・固定ソース/イメージを維持。設定は排他的に初回生成し、再setupで認証情報を置き換えない。設定をシェル実行せず、他プロジェクトの環境変数によるCompose認証情報上書きを防ぐ。起動確認のPHPエラーを表示し、失敗時はcronを起動しない。
- WindowsのバックアップはPythonでstdoutをバイナリー保存し、PowerShellのリダイレクトによる破損を回避。書込み停止後にDB・ファイル・設定を保存、失敗はFAILEDを付け、完了後も停止を保持。復元・初期化・削除は追加しない。Windowsのファイル機密性は個人フォルダーのACLで管理する旨を文書化。
- .gitattributesでコンテナー内のsh/PHPとMakefile等のLFを固定し、cmdはCRLF。PythonキャッシュをGit除外。README日英とWINDOWS.mdへ準備・初回操作・障害時の確認・打ち合わせ用の実機受入項目を追加。対象はMoodle 4.5.15/STACK4.13.1。既存作業ツリーの5.2/PDF2submission等の未公開変更は含めない。
- 検証: Linux上でPython単体テスト10件成功。空白/日本語を含む作業パス、再setup時の設定保持、起動失敗時のcron抑止、複数操作の中断、再起動時のデータ保持、バックアップのバイナリー保存・失敗印、CRLF設定、無効設定、Windowsコンテナー拒否、Docker不在/起動前、引数配列によるshell不使用・環境変数分離を検証。make -nのMac/Windows分岐、Bash/Python構文、git diff --check成功。
- GitHub ActionsにUbuntu/Windows/macOS × Python3.10/3.12の管理処理単体テストとWindowsランチャーhelpを追加。CIもDocker・Moodleの通し検証ではない。手元にはDockerがなく、今回コードで実setup・再setup・start/stop・STACK healthcheck・ブラウザー・XML取込/採点・バックアップ復元を実行していない。旧Macでの実績と今回の未検証範囲を区別し、Windows実機ではWINDOWS.mdの受入項目を実施する。Windows Armは未検証。

### Windows候補のCI実測

- GitHub Actions run 41129167767で、Windows/macOS/UbuntuそれぞれPython3.10・3.12の6ジョブすべて成功。各OSで管理処理単体テスト10件、Windows2ジョブではmoodle.cmd --helpの実行も成功。実行記録: https://github.com/yositomi-opu/MoodleSTACKSetup/actions/runs/41129167767 。Docker/Moodleのビルド・実起動・採点を検証したものではない。

## Moodle 5.3追加候補（2026-10-31）

- 公式5.3.0タグをcommit 42622298fe06f9626d988d60b2bf589bd8f850e8へ解決して固定。PHP8.3、Composer2.8.11（SHA256確認）、PostgreSQL17-bookworm（公式レジストリのmulti-platform digest固定）、既存STACK4.13.1/GoeMaxima2026080600を採用。公式要件のPostgreSQL17以上を確認し、4.5/5.2のDBを流用しない。
- compose53.yaml、docker/moodle53/*を追加。専用プロジェクトmacmoodlesetup53、localhost8090、.env53、DB/moodledata/Maxima/Web/cron/ネットワーク/Cookieを分離。既存4.5/5.2を起動・停止・移行しない。5.xのpublic公開、CLI互換入口、Composer lockを使用。未公開PDF2submission等は同梱しない。5.3 cronはroot用entrypointを迂回しwww-dataで動かす。
- Python管理処理を45/53へ対応し、setup53/start53等のサフィックスまたは--moodle 53で指定。Macの53ターゲットはPython3を使用、Windowsはmoodle.cmd。4.5は従来のMacBashとWindows既定動作を維持。5.3は4.5/5.2とのポート重複を検査し、バックアップもbackups/moodle53へ分離。
- ローカルのPython単体14件、PHP3ファイルの構文、bootstrap Bash構文、diff --check成功。5.3の設定・認証情報・プロジェクト・ポート・ソース・バックアップ分離、再setup保持、版別操作のルーティングを確認。Dockerなしの環境なので、これは実インストールの確認ではない。
- CIへUbuntuの実Dockerビルド、新規setup/start、HTTP管理者ログイン、再setup/STACK公式検査、stop/start/backup/checkを追加。実測結果は後続記録へ。Windows/Macの実5.3 Docker導入、ブラウザー、問題XML取込/採点、バックアップ復元は未確認。既存環境や利用者データは変更しない。

### 5.3候補の実Docker・CI結果

- GitHub Actions run 41132952108（候補commit eae00eba4b6e7f84f80748ceff703cc35e1bf3e8）で全7ジョブ成功。Windows/macOS/Ubuntu × Python3.10/3.12の各14件とWindowsランチャーhelpが成功。
- Ubuntuの実Dockerで5.3.0/PHP8.3/PostgreSQL17/STACK4.13.1/GoeMaximaをビルド・新規導入・起動。実HTTP管理者ログイン成功。再setup、STACK公式検査、停止・再開、バックアップ保存後の再開と再検査がすべて成功。
- 公式検査はCAS接続、Unicode、plots、JSXGraph、Parsons、free text、GeoGebra、追加ライブラリ、DBキャッシュとSTACK-Maximaライブラリ2026080600を確認。実Maximaは5.44.0。検証は専用の使い捨てCI環境で行い、MacBookの既存環境には触れていない。
- HTTPログインは実ブラウザーの描画確認ではない。Windows/Mac上でのDocker導入、問題XMLの実取込・採点、バックアップ復元は今回未確認。記録: https://github.com/yositomi-opu/MoodleSTACKSetup/actions/runs/41132952108 。この候補と記録をmainへfast-forward公開する。

## Virtual Learner Lab basic plugin (2026-10-31, candidate)

- Added GPL-3.0-or-later `plugins/local/virtuallearner`: immutable profiles, JSON import/export, dedicated additive sample lab, generated nologin students, seeded explicit skill/error policy, real quiz attempts and STACK feedback/grade observations, pause/resume, replay, CSV/JSON and Privacy API. Only plugin-created labs/users can run; no direct grade writes or adoption of existing accounts/courses.
- Bound one task/transaction to one complete deferred-feedback attempt. Variant and question ID pinned, all slots/candidates validated, run lock/step guards reject duplicate tasks. Initial sampled mastery and assumed post-feedback transitions are logged separately from actual scores. Invalid inputs cannot cause learning. Candidate strings are literal; no AI/teacher-answer retrieval or arbitrary code. No BKT fitting, forgetting, semantic feedback understanding, auto PRT skill mapping, same-attempt interactive retry or Tree/Chain comparison yet.
- Bundled in both 4.5 and 5.3 Docker images; default installation creates no sample data until an authorized manager explicitly requests it. Docker context includes only the new plugin tree in addition to existing build files. No resets or changes to existing services/volumes. Native local Docker absent; CI uses disposable services for actual install/cron/STACK/browser checks.
- Local verification: PHP syntax on all plugin files; 27 deterministic policy assertions; all 14 Python manager tests; diff whitespace check. Runtime/CI results will be recorded after running, not inferred from test code. Mac/Windows native Docker and the user's existing Moodle sites have not been changed or tested in this task.
