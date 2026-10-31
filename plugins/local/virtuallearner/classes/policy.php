<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace local_virtuallearner;

/** Pure deterministic policy. These are explicit simulation assumptions, not fitted student models. */
final class policy {
    public const VERSION = 1;

    public static function defaults(): array {
        return ['skills' => ['LA000001' => 0.0], 'slip' => 0.0, 'guess' => 0.0,
            'inputerror' => 0.0, 'feedbacklearn' => 1.0, 'persistence' => 1.0];
    }

    public static function decode(string $raw): array {
        if (strlen($raw) > 65536) {
            throw new \InvalidArgumentException('JSON exceeds 64 KiB / JSONは64 KiB以内です');
        }
        $value = json_decode($raw, true, 32, JSON_THROW_ON_ERROR);
        if (!is_array($value)) {
            throw new \InvalidArgumentException('Expected a JSON object / JSONオブジェクトが必要です');
        }
        return $value;
    }

    public static function profile(array $config): array {
        $expected = array_keys(self::defaults());
        if (array_diff(array_keys($config), $expected) || array_diff($expected, array_keys($config))) {
            throw new \InvalidArgumentException('Unknown or missing profile fields / プロフィールの項目を確認してください');
        }
        if (!is_array($config['skills']) || !$config['skills'] || count($config['skills']) > 100) {
            throw new \InvalidArgumentException('Specify 1–100 skills / スキルは1〜100件です');
        }
        foreach ($config['skills'] as $id => $probability) {
            if (!preg_match('/^[A-Z]{2}[0-9]{6}$/D', (string)$id)) {
                throw new \InvalidArgumentException('Skill IDs: LA000001 etc. / スキルIDはLA000001形式です');
            }
            self::probability($probability);
        }
        foreach (array_diff($expected, ['skills']) as $key) {
            self::probability($config[$key]);
        }
        return $config;
    }

    private static function probability($value): void {
        if ((!is_int($value) && !is_float($value)) || !is_finite((float)$value) || $value < 0 || $value > 1) {
            throw new \InvalidArgumentException('Probabilities must be numbers in [0,1] / 確率は0〜1の数値です');
        }
    }

    public static function scenario(array $slots, array $profile): array {
        if (!$slots || count($slots) > 10) {
            throw new \InvalidArgumentException('Specify 1–10 question slots / 問題スロットは1〜10件です');
        }
        foreach ($slots as $slot => $spec) {
            if (!ctype_digit((string)$slot) || (int)$slot < 1 || !is_array($spec)
                    || !isset($spec['questionid'], $spec['variant'], $spec['skills'], $spec['responses'])
                    || !is_int($spec['questionid']) || $spec['questionid'] < 1
                    || !is_int($spec['variant']) || $spec['variant'] < 1
                    || !is_array($spec['skills']) || !$spec['skills']
                    || count($spec['skills']) !== count(array_unique($spec['skills']))
                    || array_diff($spec['skills'], array_keys($profile['skills']))
                    || !is_array($spec['responses']) || !$spec['responses'] || count($spec['responses']) > 50) {
                throw new \InvalidArgumentException('Invalid slot, question ID, variant or skills / スロット・問題ID・variant・スキルを確認してください');
            }
            $kinds = []; $ids = [];
            foreach ($spec['responses'] as $response) {
                if (!is_array($response) || !isset($response['id'], $response['kind'], $response['answers'])
                        || !is_string($response['id']) || !preg_match('/^[A-Za-z0-9_-]{1,60}$/D', $response['id'])
                        || isset($ids[$response['id']])
                        || !in_array($response['kind'], ['correct', 'misconception', 'slip', 'invalid'], true)
                        || !is_array($response['answers']) || !$response['answers'] || count($response['answers']) > 20) {
                    throw new \InvalidArgumentException('Invalid or duplicate response candidate / 解答候補が不正または重複しています');
                }
                foreach ($response['answers'] as $input => $value) {
                    if (!preg_match('/^[a-zA-Z][a-zA-Z0-9_]*$/D', (string)$input) || !is_string($value)
                            || strlen($value) > 4096) {
                        throw new \InvalidArgumentException('Answers must be named input strings / 解答は入力変数名と文字列で指定します');
                    }
                }
                if ($response['kind'] === 'misconception') {
                    if (empty($response['skills']) || !is_array($response['skills'])
                            || array_diff($response['skills'], $spec['skills'])) {
                        throw new \InvalidArgumentException('Map each misconception to required skills / 誤概念には対象スキルが必要です');
                    }
                }
                $ids[$response['id']] = true;
                $kinds[$response['kind']] = true;
            }
            $required = ['correct'];
            if ($profile['slip'] > 0) { $required[] = 'slip'; }
            if ($profile['inputerror'] > 0) { $required[] = 'invalid'; }
            if (array_filter($spec['skills'], fn($id) => $profile['skills'][$id] < 1)) {
                $required[] = 'misconception';
                // Each potentially missing skill must have a candidate. Do not silently simulate a correct answer.
                foreach ($spec['skills'] as $id) {
                    if ($profile['skills'][$id] < 1 && !array_filter($spec['responses'],
                            fn($r) => $r['kind'] === 'misconception' && in_array($id, $r['skills'], true))) {
                        throw new \InvalidArgumentException('Missing misconception candidate for ' . $id);
                    }
                }
            }
            if (array_diff($required, array_keys($kinds))) {
                throw new \InvalidArgumentException('Missing candidates for configured errors / 設定した誤りに対応する解答候補がありません');
            }
        }
        return $slots;
    }

    /** Hash-based independent draws: no global random state or dependency on task scheduling. */
    public static function draw(int $seed, string $key): float {
        return hexdec(substr(hash('sha256', self::VERSION . ':' . $seed . ':' . $key), 0, 8)) / 4294967296;
    }

    public static function initial(array $profile, int $seed): array {
        $state = [];
        foreach ($profile['skills'] as $id => $probability) {
            $state[$id] = self::draw($seed, 'initial:' . $id) < $probability;
        }
        return $state;
    }

    public static function choose(array $profile, array $state, array $spec, int $seed, int $round, int $slot): array {
        $key = "$round:$slot:";
        $missing = array_values(array_filter($spec['skills'], fn($id) => !$state[$id]));
        if (self::draw($seed, $key . 'inputerror') < $profile['inputerror']) {
            $kind = 'invalid';
        } else if (!$missing) {
            $kind = self::draw($seed, $key . 'slip') < $profile['slip'] ? 'slip' : 'correct';
        } else {
            $kind = self::draw($seed, $key . 'guess') < $profile['guess'] ? 'correct' : 'misconception';
        }
        $pool = array_values(array_filter($spec['responses'], fn($r) => $r['kind'] === $kind
            && ($kind !== 'misconception' || array_intersect($r['skills'], $missing))));
        if (!$pool) {
            throw new \RuntimeException('No matching response candidate / 選択可能な解答候補がありません');
        }
        return $pool[(int)floor(self::draw($seed, $key . 'candidate') * count($pool))];
    }

    public static function learn(array $profile, array $state, array $skills, bool $feedbackvisible,
            bool $valid, int $seed, int $round, int $slot): array {
        if (!$feedbackvisible || !$valid) { return $state; }
        foreach ($skills as $id) {
            if (!$state[$id] && self::draw($seed, "$round:$slot:learn:$id") < $profile['feedbacklearn']) {
                $state[$id] = true;
            }
        }
        return $state;
    }
}
