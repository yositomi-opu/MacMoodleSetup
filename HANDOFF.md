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

GitHub側の改名は管理画面での操作が残る。完了後のURLは `https://github.com/yositomi-opu/MoodleSTACKSetup`。
既存の作業フォルダー、Composeプロジェクト名・イメージ名・ボリューム名、NASとMacの同期識別子は保持する。
DB・moodledata・稼働サービスは変更しない。既存の5.2対応等の未コミット作業は今回の名称変更commitに含めない。

NASの開発コードはMacBookなしで編集できる。現在のMoodle実行環境はMacBook上にあるため、実機確認にはMacBookの起動・接続とDocker Desktopが必要。
NAS上でのMoodle起動とWindowsでの実行は未検証。

検証: 文書変更のみ。git diff --checkを実施。setup・再起動・DBアップグレード・ブラウザ操作は実施しない。
