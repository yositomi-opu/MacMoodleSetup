# Virtual Learner Lab / 仮想学習者ラボ 0.2.0-alpha

設定した知識・誤概念のプロフィールでSTACK小テストを実際に受験するMoodle追加プラグインです。規則・DINAモデルはAI不要で、Jev判断モデルは任意に追加できます。採点・成績はMoodle/STACKの通常APIで保存し、直接書き換えません。実験用コースとパスワードを公開しない仮想学生を新規作成します。既存コースを実験対象に登録する機能はありません。

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
- 候補のcorrect/misconception等は作成者が意図した分類です。実際の正誤はSTACKが判定し、分類と得点を別々に記録します。
- 初期版は1入力変数を1文字列として提出できる入力形式を対象にします。固定行列のように複数セルのPOST値を必要とする入力は理由付きで停止します（無視して採点しません）。
- 解答候補は文字列です。CAS生成式や任意コードを実行する機能はありません。問題ID・variantを固定し、実行時に一致を検査します。STACK自身は通常どおり数学入力を評価します。
- 各ステップは遅延フィードバックの小テストを1回完了し、次回は新しい受験を開始します。同じ受験内の対話的な再解答、ランダム問題の選択、Parsons専用候補生成、Tree/Chain自動比較は将来の拡張です。
- 有効な解答と、学生に公開されるspecific feedbackがあるときだけ、設定した学習率に従って対象スキルを習得状態へ移します。0なら学習しない比較条件、1なら必ず習得します。フィードバックの意味を理解するAIは使っていません。入力無効、または入力エラー候補（invalid）を選んだ場合は学習しません。後者はPRTの独自Validationで通常の誤答として採点される場合も含みます。忘却はありません。
- PRTのanswer notesは検証者向け観測です。訪問しなかったノードを正答・習得済みとは解釈しません。PRTスキルIDの自動読込・ノード別習得推定は未対応です。
- 同じseedによるポリシーの選択は決定的です。実際の日時・受験IDは毎回異なります。設定スナップショットとイベントを保管してください。モデル内での改善を実際の学生への教育効果と混同しないでください。
- 実験データはMoodle DBに残り、ユーザーやコースを自動削除しません。通常のMoodleバックアップの対象です。CSVは数式注入対策を行います。UIは入力内容をエスケープして表示します。

## English

A virtual learner simulation lab with deterministic local models and optional Jev judgments. Install the plugin at `local/virtuallearner`, run the normal Moodle upgrade, and open Site administration → Plugins → Local plugins → Virtual Learner Lab. Create a sample course, save a profile, and start an experiment. Cron executes one actual deferred-feedback quiz attempt per step using a newly generated, random-password student enrolled only in that lab. No existing learners or courses are adopted.

Profiles specify initial per-skill mastery probabilities, slip, guessing, input errors, learning after visible feedback, and persistence. They are explicit assumptions, not empirically fitted models. The response policy samples fixed, literal candidate answers; it does not generate CAS code or retrieve teacher answers. Every required error has to be represented by a candidate. Inputs must accept a single textual value per variable; compound cell-based widgets are explicitly rejected in this version. Question ID and variant are pinned and checked. Only graded input with student-visible specific feedback can trigger the configured simulated learning transition. Learning does not infer semantic comprehension. Invalid input does not imply a knowledge deficit or cause learning.

Automatic or stepwise runs support pause/resume, frozen snapshots, immutable observations, CSV/JSON export, and seeded replay with a new student. All recorded scores come from Moodle/STACK, without grade overrides. Each round is a new attempt. Interactive same-attempt retries, BKT fitting, forgetting, automatic PRT/MeLQS skill mapping, Parsons generation, automatic Tree/Chain comparisons are not implemented. The optional Jev adapter and Q-matrix comparison controls are described below.

## Verification

`php plugins/local/virtuallearner/tests/policy_test.php` tests the pure policy without Moodle. `cli/smoke.php` is exclusively for disposable test sites with `--allow-test-data`: create fixtures, run actual Moodle cron, then verify actual STACK grades, feedback learning, invalid inputs, dropout, seeded replay and duplicate-task rejection. CI covers Moodle 4.5 and 5.3. Browser tests cover configuration, pause/resume, CSV and access guards. See the repository HANDOFF for actual results and limitations; CI configuration alone is not evidence of success.

GPL-3.0-or-later. Moodle/STACK are separately licensed upstream components.

## 0.2 判断モデル・Q-matrix・比較実験

名称は **Virtual Learner / 仮想学習者**、CSV等の配布名は `virtual_learner` とします。既存のインストール・権限・タスク・実験記録との互換性のため、内部コンポーネント `local_virtuallearner` と配置先 `local/virtuallearner` は維持します。フォルダーだけを改名しないでください。

実験フォームで、規則モデル、DINA型応答モデル、Jev、または規則モデルとの比較を選べます。「比較」は同じプロフィール・問題ID・variant・seed・Q-matrix・初期習熟状態を使って、モデル別に学生と実験を作成します。実験詳細の「同じ条件の実験を比較」で得点と状態の推移を参照できます。学生数はモデルごとなので、比較の20学生は合計40学生です。JSON/CSVにモデル・比較グループ・seedを保存します。再実行は独立した実験として作成し、元の比較グループに混ぜません。

初期状態欄に `{"LA000001":false,"LA000002":true}` のように全プロフィールスキルを指定できます。`{}`なら従来の確率抽選です。Q-matrix欄は `{"1":{"LA000001":1,"LA000002":0}}` の形式で全問題スロットを指定します。1が必要なスキル、0が不要です。`{}`なら解答候補設定のskillsから生成します。誤概念候補のスキル対応も必要スキルと一致させてください。スキルの説明欄には `{"LA000001":"二項式の展開で交差項を求める"}` のように知識項目の意味を指定できます。JevにはIDだけでなくこの説明も渡します。

習熟状態は実験中に変化します。両モデルとも、有効な解答と公開フィードバックがある場合だけ、問題に必要なスキルを設定した学習確率で未習得→習得へ移します。不要スキル・非公開フィードバック・無効入力からは学習しません。この版は問題単位の更新で、PRTノード別の意味解釈・知識推定・忘却はありません。

DINA型は必要スキルが全て習得済みなら正答確率 `1-slip`、一つでも未習得なら `guess` とする応答生成です。項目別パラメーターの推定はしません。元の規則モデルも同様の結合条件を持つため、両者は同じパラメーターなら正答確率が一致します。乱数の抽選方法の違いをモデル優劣と解釈しないでください。

### Jevの任意設定

Jevは公式TypeSafeのChoice APIを使用します。版は `jev-1.14.0` に固定します。問題文・解答候補・現在の仮想習熟状態・プロフィールの傾向・直近3回までの公開フィードバックを外部送信します。正解ラベル・誤概念ラベル・PRT内部情報・アカウントID・教師の正解定義は送りません。ただし問題文やフィードバックに作成者が書いた情報はそのまま送信対象です。実験データのみで利用してください。

管理者がサーバーの `config.php` で次を設定します。キーを画面・プロフィール・Gitへ貼り付けないでください。

```php
$CFG->local_virtuallearner_jev_enabled = true;
$CFG->local_virtuallearner_jev_key = getenv('VIRTUAL_LEARNER_JEV_KEY');
```

またはWebとcronの両プロセスに `VIRTUAL_LEARNER_JEV_ENABLED=1` と `VIRTUAL_LEARNER_JEV_KEY` を秘密の環境設定で渡します。MoodleSTACKSetupでも同様で、ホストだけへのexportではコンテナーに渡りません。必要ならGit管理外のCompose overrideでweb/cron双方に渡し、以後の全管理操作で同じoverrideを使用してください。キーをDockerfileに埋め込まないでください。規則・DINAでは設定不要です。

Jevに数学の正解を求めるのではなく、指定した仮想学習者がどの入力候補を出すかを判断させます。候補IDと正誤分類は外部送信時に除き、返された選択だけを元の候補に戻してSTACKへ提出します。確率分布・confidence・実モデル版・使用トークン・通信時間・リクエストハッシュを記録します。confidenceは習熟確率として使いません。

Jevの学習をファインチューニングする機能ではありません。習熟状態と履歴を次の判断へ渡すことで行動を変化させ、状態更新自体は比較相手と共通の規則にします。外部APIはseed指定を受けないため完全な再現性を保証できません。モデルの最上位選択を使い、確率分布から追加抽選はしません。APIエラー・不正な分布・モデル版の不一致は実験を失敗として表示し、規則モデルへの切替や自動API再試行はしません。明示的な再試行では再課金され得ます。失敗時はMoodle受験をロールバックしますが、外部APIの請求はロールバックできません。

### English: comparison design

Version 0.2 adds model selection (rule, local DINA response generation, optional Jev), explicit per-skill Boolean initial states and a binary per-slot Q-matrix. Paired experiments freeze the same profile, scenario, seed, required skills and initial state, creating separate synthetic students for each arm. Their subsequent trajectories evolve independently. The comparison page shows actual STACK fractions and simulated skill states; CSV/JSON retain model and group identifiers. Both arms share the same eligible-visible-feedback learning rule, isolating response selection rather than changing two mechanisms at once. DINA here is response generation, not diagnostic parameter fitting; its response probabilities overlap the existing conjunctive rule policy.

Jev is opt-in using server-only config or `VIRTUAL_LEARNER_JEV_ENABLED=1` / `VIRTUAL_LEARNER_JEV_KEY` in both web and cron processes. Only the fixed official HTTPS endpoint is used, with TLS verification, response limits, timeouts and no redirects. A Choice request sends student-facing question text, opaque literal candidates, simulated mastery, behavioral parameters and up to three previous attempts of visible feedback. Grading labels, internal PRT traces and account IDs are excluded. Credentials never enter snapshots or exports. The pinned model is jev-1.14.0. Provider top choice, probabilities, confidence, usage and request hash are audited; invalid responses fail explicitly without fallback or automatic retry. Provider responses are not seed-reproducible. Retry may incur another charge even if the Moodle transaction rolls back. The internal Moodle component/path remain unchanged to preserve installed data; human-facing naming and exported filenames use separated words.

### Sources and verification scope

- [TypeSafe official Choice contract](https://docs.typesafe.ai/primitives/choice)
- [TypeSafe HTTP API](https://docs.typesafe.ai/api/system-one)
- [TypeSafe model versions](https://docs.typesafe.ai/models)
- [Junker & Sijtsma (2001), conjunctive cognitive assessment](https://doi.org/10.1177/01466210122032064)

`tests/transport_test.php` uses transport doubles: these are **not live Jev tests** and do not validate account access, charges, service availability or educational realism. Actual integration results are recorded in HANDOFF.md. Native Mac/Windows Docker and a live Jev comparison still need separate verification.
