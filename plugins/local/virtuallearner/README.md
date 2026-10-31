# Virtual Learner Lab / 仮想学習者ラボ 0.1.0-alpha

AIを使わず、設定した知識・誤概念のプロフィールでSTACK小テストを実際に受験するMoodle追加プラグインです。採点・成績はMoodle/STACKの通常APIで保存し、直接書き換えません。実験用コースとパスワードを公開しない仮想学生を新規作成します。既存コースを実験対象に登録する機能はありません。

## 使い方

1. Moodle管理者またはシステムで `local/virtuallearner:manage` を許可された管理者として、サイト管理 → プラグイン → ローカルプラグイン → 仮想学習者ラボを開きます。直接の入口は `/local/virtuallearner/index.php`。
2. 「サンプル実験コースを作成」を押します。二項式の展開のSTACK問題と専用の小テストが作成されます。LA000001はこのデモだけの仮のスキルIDで、外部の知識体系とはまだ対応しません。
3. スキル別の初期習得確率、ミス率、推測率、入力ミス率、フィードバック後の学習率、再挑戦率を指定し、プロフィールを新規保存します。保存済みプロフィールは不変で、JSONを書き出し・読み込んで複製できます。
4. 実験コース・プロフィールを選び、seed、学生数、受験回数上限、解答候補を設定します。最大20学生×20受験×10問。各学生のseedは基準seedに0,1,…を加えた値です。
5. Moodleのcronが実際の学生として1回ずつ受験します。自動進行を外すと各回で一時停止します。「次のステップを予約」で続けられます。自動進行も境界で停止・再開できます。
6. 実験詳細で、実際の得点、解答、入力有効性、PRT answer notes、学生に表示可能なフィードバック、シミュレーション知識状態の前後を確認します。CSV/JSONを保存できます。JSONにはプロフィール・候補・seed・モデル版・Moodle/STACK版と全観測を含めます。
7. 「別の仮想学生で再実行」は同じ設定を使って新しい学生・受験記録を追加します。過去の記録を上書きしません。実際のPRT/問題版が変われば再現結果は変わり得ます。

MoodleSTACKSetupではDockerイメージへ同梱します。ソース更新後は既存サイトをバックアップして `make setup start`、5.3は `make setup53 start53`。Windowsは `moodle.cmd setup start` / `moodle.cmd setup53 start53`。`start`だけではコードのビルド・プラグインDBの更新は行いません。別Moodleでは `local/virtuallearner` にこのフォルダーを置き、通常の管理者アップグレードで導入できます。Moodle 4.5以上とSTACKが必要です。

## モデルの意味と制約

- 各スキルの確率から最初の二値の習得状態を抽選します。これは推定結果ではなく、明示した実験上の内部状態です。BKTの推定器や実データで学習したモデルは未実装です。
- 入力ミスを先に抽選し、そうでなければ習得状態に応じて正答・ミス・推測・対応する誤概念候補を選びます。設定した誤りに候補がない場合は設定を拒否します。
- 誤概念はスキルに対応する候補です。複数の誤概念が同時に現れる場合には、その組合せの解答候補を作成者が用意してください。プラグインが行列計算や誤答発見を行うわけではありません。
- 解答候補は文字列です。CAS生成式や任意コードを実行する機能はありません。問題ID・variantを固定し、実行時に一致を検査します。STACK自身は通常どおり数学入力を評価します。
- 各ステップは遅延フィードバックの小テストを1回完了し、次回は新しい受験を開始します。同じ受験内の対話的な再解答、ランダム問題の選択、Parsons専用候補生成、Tree/Chain自動比較は将来の拡張です。
- 有効な解答と、学生に公開されるspecific feedbackがあるときだけ、設定した学習率に従って対象スキルを習得状態へ移します。0なら学習しない比較条件、1なら必ず習得します。フィードバックの意味を理解するAIは使っていません。入力無効では学習しません。忘却はありません。
- PRTのanswer notesは検証者向け観測です。訪問しなかったノードを正答・習得済みとは解釈しません。PRTスキルIDの自動読込・ノード別習得推定は未対応です。
- 同じseedによるポリシーの選択は決定的です。実際の日時・受験IDは毎回異なります。設定スナップショットとイベントを保管してください。モデル内での改善を実際の学生への教育効果と混同しないでください。
- 実験データはMoodle DBに残り、ユーザーやコースを自動削除しません。通常のMoodleバックアップの対象です。CSVは数式注入対策を行います。UIは入力内容をエスケープして表示します。

## English

A deterministic, AI-free simulation lab. Install the plugin at `local/virtuallearner`, run the normal Moodle upgrade, and open Site administration → Plugins → Local plugins → Virtual Learner Lab. Create a sample course, save a profile, and start an experiment. Cron executes one actual deferred-feedback quiz attempt per step using a newly generated, random-password student enrolled only in that lab. No existing learners or courses are adopted.

Profiles specify initial per-skill mastery probabilities, slip, guessing, input errors, learning after visible feedback, and persistence. They are explicit assumptions, not empirically fitted models. The response policy samples fixed, literal candidate answers; it does not generate CAS code or retrieve teacher answers. Every required error has to be represented by a candidate. Question ID and variant are pinned and checked. Only graded input with student-visible specific feedback can trigger the configured simulated learning transition. Learning does not infer semantic comprehension. Invalid input does not imply a knowledge deficit or cause learning.

Automatic or stepwise runs support pause/resume, frozen snapshots, immutable observations, CSV/JSON export, and seeded replay with a new student. All recorded scores come from Moodle/STACK, without grade overrides. Each round is a new attempt. Interactive same-attempt retries, BKT fitting, forgetting, automatic PRT/MeLQS skill mapping, Parsons generation, LLM/Jev adapters and automatic Tree/Chain comparisons are not implemented in this first version.

## Verification

`php plugins/local/virtuallearner/tests/policy_test.php` tests the pure policy without Moodle. `cli/smoke.php` is exclusively for disposable test sites with `--allow-test-data`: create fixtures, run actual Moodle cron, then verify actual STACK grades, feedback learning, invalid inputs, dropout, seeded replay and duplicate-task rejection. CI covers Moodle 4.5 and 5.3. Browser tests cover configuration, pause/resume, CSV and access guards. See the repository HANDOFF for actual results and limitations; CI configuration alone is not evidence of success.

GPL-3.0-or-later. Moodle/STACK are separately licensed upstream components.
