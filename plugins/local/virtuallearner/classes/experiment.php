<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace local_virtuallearner;

/** Frozen comparison design: no database or provider dependencies. */
final class experiment {
    public const VERSION = 1;
    public static function options(array $options, array $profile, array $scenario): array {
        $defaults = ['model' => 'rule', 'initial' => [], 'qmatrix' => [], 'skilllabels' => [], 'comparison' => ''];
        if (array_diff(array_keys($options), array_keys($defaults))) {
            throw new \InvalidArgumentException('Unknown experiment option');
        }
        $options += $defaults;
        if (!in_array($options['model'], ['rule', 'dina', 'jev', 'compare_dina', 'compare_jev'], true)) {
            throw new \InvalidArgumentException('Unknown decision model');
        }
        if (!is_array($options['initial']) || !is_array($options['qmatrix']) || !is_array($options['skilllabels']) ||
                !is_string($options['comparison']) || strlen($options['comparison']) > 64) {
            throw new \InvalidArgumentException('Invalid experiment settings');
        }
        if (array_diff(array_keys($options['skilllabels']), array_keys($profile['skills']))) {
            throw new \InvalidArgumentException('Unknown skill description');
        }
        foreach ($options['skilllabels'] as $label) {
            if (!is_string($label) || strlen($label) > 1500) {
                throw new \InvalidArgumentException('Skill descriptions must be strings up to 1500 bytes');
            }
        }
        if ($options['initial']) {
            if (array_diff(array_keys($profile['skills']), array_keys($options['initial'])) ||
                    array_diff(array_keys($options['initial']), array_keys($profile['skills']))) {
                throw new \InvalidArgumentException('Initial state must specify every profile skill exactly once');
            }
            foreach ($options['initial'] as $value) {
                if (!is_bool($value)) { throw new \InvalidArgumentException('Initial states must be true or false'); }
            }
        }
        if (!$options['qmatrix']) {
            foreach ($scenario as $slot => $spec) {
                $options['qmatrix'][$slot] = array_fill_keys($spec['skills'], 1);
            }
        }
        if (array_diff(array_keys($scenario), array_keys($options['qmatrix'])) ||
                array_diff(array_keys($options['qmatrix']), array_keys($scenario))) {
            throw new \InvalidArgumentException('Q-matrix must cover every slot exactly once');
        }
        foreach ($options['qmatrix'] as $slot => $row) {
            if (!is_array($row) || !$row || array_diff(array_keys($row), array_keys($profile['skills']))) {
                throw new \InvalidArgumentException('Q-matrix uses an unknown skill');
            }
            $required = [];
            foreach ($row as $id => $value) {
                if ($value !== 0 && $value !== 1) { throw new \InvalidArgumentException('Q-matrix cells must be integer 0 or 1'); }
                if ($value === 1) { $required[] = $id; }
            }
            if (!$required) { throw new \InvalidArgumentException('Each question needs at least one required skill'); }
        }
        return $options;
    }
    public static function scenario(array $scenario, array $options, array $profile): array {
        foreach ($scenario as $slot => &$spec) {
            $spec['skills'] = array_keys(array_filter($options['qmatrix'][$slot], fn($v) => $v === 1));
        }
        unset($spec);
        $coverage = $profile;
        foreach ($options['initial'] as $id => $value) { $coverage['skills'][$id] = $value ? 1 : 0; }
        return policy::scenario($scenario, $coverage);
    }
    public static function initial(array $profile, int $seed, array $options): array {
        return $options['initial'] ?: policy::initial($profile, $seed);
    }
    /** DINA response probability: conjunctive mastery, with slip and guessing. Not fitted. */
    public static function dina(array $profile, array $state, array $spec, int $seed, int $round, int $slot): array {
        $key = "$round:$slot:";
        $missing = array_values(array_filter($spec['skills'], fn($id) => !$state[$id]));
        if (policy::draw($seed, $key . 'inputerror') < $profile['inputerror']) {
            $kind = 'invalid';
        } else {
            $p = $missing ? $profile['guess'] : 1 - $profile['slip'];
            $kind = policy::draw($seed, $key . 'dina:correct') < $p ? 'correct' : ($missing ? 'misconception' : 'slip');
        }
        $pool = array_values(array_filter($spec['responses'], fn($r) => $r['kind'] === $kind &&
            ($kind !== 'misconception' || array_intersect($r['skills'], $missing))));
        if (!$pool) { throw new \RuntimeException('No matching DINA response candidate'); }
        return $pool[(int)floor(policy::draw($seed, $key . 'candidate') * count($pool))];
    }
}
