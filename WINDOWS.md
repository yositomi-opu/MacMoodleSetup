# WindowsでMoodle＋STACKを試す

対象はWindows x64＋Docker DesktopのLinuxコンテナー環境です。Windows Armは未確認です。管理コマンドは用意していますが、実Windows上のDockerビルド・インストール・採点の通し確認はまだ完了していません。

## 準備

1. [Docker Desktop for Windows](https://docs.docker.com/desktop/setup/install/windows-install/)の要件を確認し、インストールします。WSL 2バックエンドを推奨します。WSL導入や仮想化の有効化で管理者操作・再起動が必要になる場合があります。大学管理PCでは担当者へ相談してください。
2. [Git for Windows](https://git-scm.com/downloads/win)と[Python](https://www.python.org/downloads/windows/)（3.10以降、`py`ランチャー付き）を導入します。導入後はPowerShellを開き直します。Makeは不要です。
3. Docker Desktopを手動起動し、初回の利用条件を確認したうえで、エンジンの起動を待ちます。WindowsコンテナーではなくLinuxコンテナーを使用します。
4. OneDrive・共有フォルダー・ネットワークドライブを避け、個人のローカル作業フォルダーを使います。`.env`とバックアップには認証情報を含むため、他ユーザーが読めないWindowsアクセス権のフォルダーで管理してください。

PowerShellで確認します。

```powershell
git --version
py -3 --version
docker version
docker compose version
wsl --version
```

Dockerの要件・対応するWindowsとWSLの版は、上記Docker公式資料を基準にしてください。

## 初回起動

```powershell
git clone https://github.com/yositomi-opu/MoodleSTACKSetup.git
cd MoodleSTACKSetup
.\moodle.cmd doctor
.\moodle.cmd setup start
.\moodle.cmd credentials
```

初回は公式ソース・イメージの取得とビルドで時間がかかります。setupはMoodleのGPLライセンスへ同意する公式CLIオプションを使います。失敗した場合は後続のstartを実行しません。

http://localhost:8088 を開き、表示された初期パスワードでadminとしてログインします。認証情報の出力を共有チャットやスクリーンショットへ含めないでください。ログイン後のパスワード変更後は初期パスワードは使えません。

既存cloneは作業内容を保存してから `git pull --ff-only` で更新します。既存サイトに対するsetupはDB更新を伴う場合があります。先に `backup` を実行して確認してください。`start`だけではソースを更新しません。

## 日常操作

```powershell
.\moodle.cmd start
.\moodle.cmd check
.\moodle.cmd status
.\moodle.cmd logs
.\moodle.cmd open
.\moodle.cmd stop
.\moodle.cmd restart
.\moodle.cmd backup
```

`stop`はDBと教材を保持します。backupは書込み処理を停止し、DB・ファイル・設定を `backups/日時/` に保存します。保存後は停止したままなので、確認後に `start` で再開します。失敗したバックアップには `FAILED` を付け、復元用として扱いません。PowerShellの `>` を介さずバイナリーで書くため、DBダンプとtarの文字コード変換を避けます。自動復元・データ削除コマンドはありません。

Makeを導入済みなら同じ作業フォルダーで `make setup start` も使えます。ネイティブPowerShellからの上記手順が基本です。WSL内でMac用の `scripts/manage.sh` を実行する方法は対象外です。ランチャーが使えない場合は `py -3 scripts/manage.py setup start` のようにPythonから直接呼び出せます。

## 打ち合わせでの確認項目

1. `doctor`でCompose・LinuxエンジンがOKになる。
2. `setup start`が完了し、ブラウザーでadminログインできる。
3. `check`でSTACK公式ヘルスチェック（CAS接続・数式・プロット等）が成功する。
4. コースでSTACK問題のXMLを1件取り込み、プレビュー表示・正答と誤答の採点を確認する。
5. 問題・コースを作成後に `stop` → `start` し、保持されることを確認する。
6. `setup start`を再実行し、同じ教材とログイン情報が保持されることを確認する。
7. `backup`でDBダンプ・ファイルアーカイブ・設定が保存され、再度 `start` できることを確認する。バックアップ復元試験は別途必要。

失敗報告には、Windowsの版・CPU（x64/Arm）・Docker/Python/Composeの版・実行したコマンド・エラーを添えてください。`.env`・初期パスワード・コースデータは添付しないでください。`credentials`はログ収集へ含めません。

## よくある停止箇所

| 表示・症状 | 確認 |
|---|---|
| `py`が見つからない | Pythonとランチャーを導入後、PowerShellを開き直す。`python --version`だけ通る場合は `python scripts/manage.py ...` も使用可能 |
| `docker`が見つからない | Docker Desktop導入後、PowerShellを開き直す |
| Dockerが準備できない | Docker Desktopの起動、WSL 2、仮想化、大学PCの制限を確認 |
| Linux containersの案内 | Docker DesktopでLinuxコンテナーへ切替 |
| 8088が使用中 | 他アプリを確認し、`.env`のMOODLE_PORTだけ空きポートへ変更。既存サイトでは変更後にsetupで整合性を確認 |
| 初回取得・ビルド失敗 | Docker Hub/GitHubへの接続、プロキシ、ディスク空き容量を確認。失敗したままstartを続けない |
| STACKチェック失敗 | `logs`と`check`の出力を確認。Dockerのメモリー割当てとMaximaの状態も確認 |

`.env`はKEY=valueの設定ファイルです。PowerShellスクリプトとして実行しません。自動生成値を使用してください。DBと管理者パスワードの変更は単なる`.env`書換えだけでは既存DBへ反映されません。

## English

Use Windows x64, Docker Desktop with Linux containers (WSL 2 recommended), Git and Python 3.10+ with the `py` launcher. Windows Arm and full Windows Docker/Moodle operation remain unverified. Start Docker Desktop first; the launcher does not change OS settings or install dependencies.

In PowerShell, clone into a private local folder outside OneDrive, then run `.\moodle.cmd doctor`, `.\moodle.cmd setup start`, and `.\moodle.cmd credentials`. Open http://localhost:8088. Make and Bash are optional/unneeded; existing GNU Make installations can use `make setup start`. `py -3 scripts/manage.py setup start` is an alternative entry point. No PowerShell execution-policy changes are needed.

Versions remain pinned to Moodle 4.5.15 and STACK 4.13.1. Existing Docker project/volume names are retained. Repeat setup preserves credentials and uses Moodle's normal upgrade path; back up before upgrades. Backups stop writers, write binary bytes directly and leave services stopped. Keep `.env` and backups private using Windows folder permissions. No automatic restore or data reset is provided.

For acceptance, verify doctor, initial setup/login, official STACK healthcheck, XML import and correct/incorrect grading, stop/start persistence, repeated setup, and backup followed by restart. Unit tests cover orchestration only and do not establish successful Docker installation or grading. Report OS/CPU/Python/Docker/Compose versions and failing commands, excluding credentials and private course data.


## Moodle 5.3の追加

上記の導入準備後、別サイトを追加できます。

```powershell
.\moodle.cmd setup53 start53
.\moodle.cmd credentials53
.\moodle.cmd check53
```

URLは http://localhost:8090、設定は`.env53`です。4.5のデータや認証情報を置き換えません。打ち合わせ用確認項目の各操作は、`setup53`／`start53`／`stop53`／`check53`／`backup53`へ読み替えて確認してください。Windows実機のDocker・ブラウザー・採点確認は未実施です。

English: To add the isolated 5.3 site, run `.\moodle.cmd setup53 start53`, then credentials53 and check53. Use http://localhost:8090. The earlier acceptance checklist applies with 53-suffixed actions; Windows Docker/browser/grading validation is still required.
