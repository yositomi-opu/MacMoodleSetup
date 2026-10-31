# MoodleSTACKSetup

Moodle＋STACKを使って作問・検証するための、独立したDocker環境です。
MCQ編集アプリやSTACK API、ホストのPHP・Maximaは必要ありません。

## 名称変更と対応OS

旧名はMacMoodleSetupです。MacとWindowsの両対応を目指して、表示名を **MoodleSTACKSetup** に変更しました。
Mac用の既存スクリプトに加え、WindowsのPowerShell／コマンドプロンプトから使える管理コマンドを用意しています。Windows実機でのDocker・Moodle通し確認は未実施です。[Windows導入・検証手順](WINDOWS.md)を参照してください。

GitHubリポジトリは [MoodleSTACKSetup](https://github.com/yositomi-opu/MoodleSTACKSetup) へ改名済みです。
既存環境の作業フォルダーは改名不要です。Dockerのプロジェクト名・ボリューム名は既存データの継続利用のため維持します。
NASとMacの同期設定も従来のフォルダー名を使い続けます。

English: MoodleSTACKSetup is the new project name (formerly MacMoodleSetup).
A native Windows launcher is available; end-to-end Windows Docker/Moodle validation remains outstanding. See [Windows setup and verification](WINDOWS.md).
Existing checkout paths and Docker resource names remain unchanged to preserve data and synchronization.

## 初回導入（Mac）

1. [Docker Desktop for Mac](https://www.docker.com/products/docker-desktop/) をインストールします。
   GitとMakeがなければ `xcode-select --install` でCommand Line Toolsを導入します。
2. 同期対象外のフォルダで、次を実行します。

```sh
git clone https://github.com/yositomi-opu/MoodleSTACKSetup.git
cd MoodleSTACKSetup
make setup start
```

初回はイメージと公式ソースの取得・DB作成に時間がかかります。
Docker Desktopが停止している場合は起動を試みます。
MoodleのGPLライセンスへの同意が必要です。setupは公式CLIの `--agree-license` を使用します。

[http://localhost:8088](http://localhost:8088) を開いてください。
ログイン情報は `make credentials` で表示できます。
ユーザー名は `admin`、初期パスワードは自動生成し、Git対象外の `.env` に保存します。
ブラウザでパスワードを変更した後は、表示される初期パスワードは使用できません。
`.env` はDB接続情報も含むため、大切に保管してください。

## 初回導入（Windows）

Docker Desktop（Linuxコンテナー／WSL 2バックエンド）、Git、Python 3.10以降（`py`ランチャー付き）を導入し、Docker Desktopを起動してください。Make・Bash・ホストのPHP・Maxima・OpenSSLは不要です。Windows x64を導入候補とし、Windows Armは未検証です。

PowerShellで、OneDriveなどの同期対象外の個人用フォルダーにcloneします。

```powershell
git clone https://github.com/yositomi-opu/MoodleSTACKSetup.git
cd MoodleSTACKSetup
.\moodle.cmd doctor
.\moodle.cmd setup start
.\moodle.cmd credentials
```

http://localhost:8088 を開きます。Makeを導入済みなら `make setup start` でも同じWindows用処理を呼び出します。`moodle.cmd`の代わりに `py -3 scripts/manage.py setup start` も使えます。PowerShellの実行ポリシー変更は不要です。Dockerの起動やWSL導入は利用者が行い、このコマンドではOS設定を変更しません。

Windows用の管理処理は、Macと同じComposeプロジェクト・ポート・DB・ファイル保存先を使用します。既存の環境を初期化せず、setupは新規インストールまたは通常のDB更新を行います。ソース・イメージは固定版（Moodle 4.5.15／STACK 4.13.1）で、最新版を自動取得するものではありません。5.2用の未公開開発環境はこの導入には含まれません。

Windowsでは以下の表の `make start` を `.\moodle.cmd start`、`make backup` を `.\moodle.cmd backup` のように読み替えられます。`doctor` は設定ファイルやDBを作らず、Docker ComposeとLinuxエンジンの準備状態を確認します。[詳しい導入・実機確認・トラブル対応](WINDOWS.md)を参照してください。

English: On Windows, install Docker Desktop with Linux containers/WSL 2, Git and Python 3.10+ with the `py` launcher. Start Docker Desktop, clone the repository into a private non-synced folder, and run `.\moodle.cmd doctor`, `.\moodle.cmd setup start`, then `.\moodle.cmd credentials` in PowerShell. GNU Make is optional; `make setup start` routes to the native Windows manager when installed. No Bash, host PHP/Maxima/OpenSSL or PowerShell execution-policy change is required. Versions stay pinned; Windows end-to-end installation and browser grading remain unverified. See [Windows guide](WINDOWS.md).

## 日常の操作

| コマンド | 内容 |
|---|---|
| `make start` | 保存済みデータを使って起動、定期処理も開始 |
| `make stop` | 停止。教材・ユーザーは保持 |
| `make restart` | 停止して起動 |
| `make open` | ブラウザで開く |
| `make check` | STACK公式ヘルスチェック（Maxima・数式・描画など） |
| `make status` | 起動状況 |
| `make logs` | 最近のログ |
| `make backup` | Webと定期処理を停止しDB・ファイル・設定をバックアップ |

Mac再起動後は `make start` を使います。常時自動起動は前提にしていません。
setupを繰り返しても初期化やパスワード再設定はしません。ただしソース更新後のsetupは
MoodleのDBアップグレードを行うため、更新前にバックアップを取ってください。

## Moodle 5.3を追加する

4.5を残したまま、別サイトとしてMoodle 5.3.0を追加できます。5.3はPHP 8.3／PostgreSQL 17／STACK 4.13.1／GoeMaxima 2026080600を固定して使用します。公式ソースは `docker/moodle53/sources.txt`、専用構成は `compose53.yaml` です。4.5のDB・教材・アカウントを移行する操作ではありません。

Mac（Python 3.10以降も必要）：

```sh
make setup53 start53
make credentials53
```

Windows（PowerShell）：

```powershell
.\moodle.cmd setup53 start53
.\moodle.cmd credentials53
```

http://localhost:8090 を開きます。日常操作は `start53`／`stop53`／`restart53`／`check53`／`logs53`／`status53`／`open53`／`backup53` です。Macでは `make check53`、Windowsでは `.\moodle.cmd check53` のように使います。

| 対象 | Moodle 4.5 | Moodle 5.3 |
|---|---|---|
| URL | http://localhost:8088 | http://localhost:8090 |
| 設定（Git対象外） | `.env` | `.env53` |
| Composeプロジェクト | `macmoodlesetup` | `macmoodlesetup53` |
| DB／ファイルボリューム | `macmoodlesetup_database`／`macmoodlesetup_moodledata` | `macmoodlesetup53_database`／`macmoodlesetup53_moodledata` |
| DB | PostgreSQL 16 | PostgreSQL 17 |
| バックアップ | `backups/日時/` | `backups/moodle53/日時/` |

5.3はDB・Maxima・Web・cron・ネットワーク・ログインCookieを分けています。5.3用操作は4.5のサービスを起動・停止・更新しません。既存の5.2開発環境の既定ポート8089も避けます。8090が使用中なら`.env53`のMOODLE_PORTを変更してください。4.5／5.2と同じポートは拒否します。setup53の再実行は5.3だけを通常のMoodle手順で更新するため、既存サイトでは先にbackup53を取得してください。

Moodle 5.3のPostgreSQL最小要件は17です。[公式要件](https://moodledev.io/general/releases/5.3)に従い、既存のPostgreSQL 16ボリュームを流用しません。[Linuxの実Docker検証](https://github.com/yositomi-opu/MoodleSTACKSetup/actions/runs/41132952108)で、新規導入・管理者HTTPログイン・再setup・STACK公式検査・停止再開・バックアップ後再開が成功しています。詳細はHANDOFF.mdを参照してください。Windows/Mac実機での新規5.3導入・ブラウザー表示・問題XML取込・採点確認は別途必要です。

English: Add a separate Moodle 5.3.0 site using `make setup53 start53` on Mac (Python 3.10+ required) or `.\moodle.cmd setup53 start53` on Windows. Use credentials53 for login details and open http://localhost:8090. The 53-suffixed lifecycle actions operate only on the dedicated .env53, Compose project, PostgreSQL 17 database, Maxima, web, cron, network and cookie. Existing 4.5/5.2 data is neither upgraded nor migrated. Sources and image digests are pinned. Back up before repeating setup on an existing 5.3 site. Windows/Mac Docker and browser/grading verification remains separate from orchestration tests; see HANDOFF.md for actual results.

## Moodleでの利用

コースを作成し、問題バンクにMoodle XMLをインポートできます。
STACK以外の通常のMoodle機能も利用できます。MathJaxと多言語フィルタを有効にします。
外向きメール送信は無効です。`stack_include` はXML内のURLを取得するため、外部URLを使う
問題にはネット接続が必要です。日本語表示は「Site administration → Language → Language packs」で `ja` を導入できます。

## データの保存

専用Dockerボリューム `macmoodlesetup_database` と `macmoodlesetup_moodledata` に保存します。
Gitには教材・ユーザー・パスワードを保存しません。cloneだけでは教材データは移行されません。
Docker Desktopのデータ消去や `docker compose down -v` はデータを失う操作です。

`make backup` は `backups/日時/` にDBダンプ、moodledata、設定、ソース版を保存します。
実行後は停止したままです。内容を確認して `make start` で再開してください。
バックアップには個人情報・秘密情報が含まれます。別媒体にも保管してください。
復元にはDBとmoodledataの両方、および対応する `.env` を使用します。
既存データの誤上書きを防ぐため、自動復元コマンドはまだ提供しません。

## 構成

- Moodle 4.5系列（コミット固定）、STACK 4.13.1、必須4プラグイン。
- PHP 8.3、PostgreSQL 16、GoeMaxima 2026080600。
- `docker/sources.txt` とイメージdigestで版を固定。
- 専用Composeプロジェクト `macmoodlesetup`。他アプリのコンテナ・データは使いません。
- WebはMac自身の `127.0.0.1:8088` のみに公開。DB・Maximaのポートは非公開。
- PHP・MaximaはIntel用Linuxイメージ。Apple SiliconではDockerのエミュレーションを使用。
- macOS更新の影響を軽減しますが、Docker Desktop自体の互換性は必要です。
- ローカル作問・検証用です。インターネット公開用の構成ではありません。

8088が使用中なら `.env` の `MOODLE_PORT` を変更し `make start` してください。
同じMacに複数cloneしても、同じ専用プロジェクトを操作します。

## トラブル時

- Dockerに接続できない: Docker Desktopの起動完了後に再実行します。
- setup失敗: `make logs` を確認し、原因解消後 `make setup start` を再実行します。
  DB導入が途中で中断した場合は部分的なDBが残ることがあります。自動削除はしません。
- STACK計算失敗: `make check` と管理画面のSTACK healthcheckを確認します。
  STACKとGoeMaximaはセットで版を合わせます。
- 数式が描画されない: MathJaxを取得するネット接続とブラウザのエラーを確認します。

## 公式情報

- [Moodle Docker](https://github.com/moodlehq/moodle-docker)
- [Moodle PHPイメージ](https://github.com/moodlehq/moodle-php-apache)
- [STACK導入手順](https://docs.stack-assessment.org/en/Installation/)

セットアップコードはMITライセンス。取得されるMoodle・STACK等は各プロジェクトのライセンス
（主にGPL）に従います。検証状況はHANDOFF.mdを参照してください。

## 仮想学習者ラボ（AIなし・開発版）

追加プラグイン `local_virtuallearner` を同梱します。サイト管理 → プラグイン → ローカルプラグイン → 仮想学習者ラボから、専用実験コース・仮想学生・調整可能なプロフィールを作成し、STACKの実採点とフィードバックを記録できます。AIキーは不要です。既存コースや実学生は自動操作しません。利用は任意です。

ソース更新後はバックアップを取り、`make setup start`（5.3は `make setup53 start53`）で再ビルドと通常のプラグイン更新を行います。Windowsは `moodle.cmd` を使います。画面の操作、モデルの仮定、初期版の制限は [プラグインの説明](plugins/local/virtuallearner/README.md) を参照してください。開発版であり、シミュレーション内の学習を実際の教育効果とはみなしません。

English: The bundled alpha Virtual Learner Lab runs deterministic, AI-free STACK experiments with adjustable learner profiles, dedicated random-password students, real quiz grading, pause/resume and CSV/JSON export. Back up and repeat setup to rebuild/install. See the bilingual [plugin guide](plugins/local/virtuallearner/README.md). Existing courses and students are not automatically operated on.
