# MacMoodleSetup

Macで作問・検証するための、Moodle＋STACKの独立したDocker環境です。
MCQ編集アプリやSTACK API、ホストのPHP・Maximaは必要ありません。

## 初回導入

1. [Docker Desktop for Mac](https://www.docker.com/products/docker-desktop/) をインストールします。
   GitとMakeがなければ `xcode-select --install` でCommand Line Toolsを導入します。
2. このリポジトリを同期対象外のフォルダにcloneし、そのフォルダで実行します。

```sh
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
