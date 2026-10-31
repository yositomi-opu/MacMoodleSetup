<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace local_virtuallearner;

/** Prepare textual input fields without executing or rewriting their contents. */
final class response {
    public static function prepare(array $inputs, array $answers): array {
        if (array_diff(array_keys($answers), array_keys($inputs))) {
            throw new \InvalidArgumentException('Unknown answer input / 未知の解答変数があります');
        }
        $result = [];
        foreach ($inputs as $name => $definition) {
            $expected = $definition->get_expected_data();
            if (!array_key_exists($name, $expected)) {
                throw new \InvalidArgumentException('Compound input not supported in this version / この版では複数セルの入力は未対応です: ' . $name);
            }
            $value = $answers[$name] ?? '';
            if (!is_string($value)) {
                throw new \InvalidArgumentException('Expected a literal answer string');
            }
            $result[$name] = $value;
            if (array_key_exists($name . '_val', $expected)) {
                // Explicit interpretation confirmation; content validation is still performed by STACK.
                $result[$name . '_val'] = $value;
            }
        }
        return $result;
    }
}
