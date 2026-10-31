<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace local_virtuallearner;
defined('MOODLE_INTERNAL') || die();

/** Opt-in server-only transport; fixed HTTPS origin, no redirects and no automatic retry. */
final class jev {
    public static function available(): bool {
        global $CFG;
        $enabled = $CFG->local_virtuallearner_jev_enabled ?? (getenv('VIRTUAL_LEARNER_JEV_ENABLED') === '1');
        return $enabled === true && trim(self::key()) !== '';
    }
    private static function key(): string {
        global $CFG;
        $key = $CFG->local_virtuallearner_jev_key ?? getenv('VIRTUAL_LEARNER_JEV_KEY');
        return is_string($key) ? $key : '';
    }
    public static function choose(array $request, array $spec): array {
        global $CFG;
        if (!self::available()) { throw new \RuntimeException('Jev is not enabled/configured by the site administrator'); }
        $key = self::key();
        if (preg_match('/[\r\n]/', $key)) { throw new \RuntimeException('Invalid Jev configuration'); }
        $started = microtime(true);
        $ch = curl_init('https://api.typesafe.ai/v1/systemone');
        $raw = '';
        curl_setopt_array($ch, [CURLOPT_POST => true, CURLOPT_FOLLOWLOCATION => false,
            CURLOPT_CONNECTTIMEOUT => 5, CURLOPT_TIMEOUT => 20, CURLOPT_PROTOCOLS => CURLPROTO_HTTPS,
            CURLOPT_SSL_VERIFYPEER => true, CURLOPT_SSL_VERIFYHOST => 2,
            CURLOPT_HTTPHEADER => ['Authorization: Bearer ' . $key, 'Content-Type: application/json'],
            CURLOPT_POSTFIELDS => json_encode($request, JSON_THROW_ON_ERROR),
            CURLOPT_WRITEFUNCTION => static function($unused, string $data) use (&$raw): int {
                if (strlen($raw) + strlen($data) > 65536) { return 0; }
                $raw .= $data; return strlen($data);
            }]);
        $ok = curl_exec($ch); $status = curl_getinfo($ch, CURLINFO_HTTP_CODE); curl_close($ch);
        // Never persist provider error bodies, request headers or curl errors containing configuration.
        if ($ok === false || $status !== 200) { throw new \RuntimeException('Jev request failed (HTTP ' . (int)$status . '); explicit retry required'); }
        try { $body = json_decode($raw, true, 32, JSON_THROW_ON_ERROR); }
        catch (\Throwable $e) { throw new \RuntimeException('Jev returned invalid JSON'); }
        if (!is_array($body)) { throw new \RuntimeException('Jev returned an invalid response'); }
        $result = decision::parse($body, $spec);
        $result['audit']['latency_ms'] = (int)round((microtime(true) - $started) * 1000);
        $result['audit']['request_sha256'] = hash('sha256', json_encode($request, JSON_THROW_ON_ERROR));
        return $result;
    }
}
