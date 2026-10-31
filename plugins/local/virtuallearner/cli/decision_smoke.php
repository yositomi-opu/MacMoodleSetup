<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace {
    if (PHP_SAPI !== 'cli') { http_response_code(403); exit; }
}
namespace local_virtuallearner {
    // Disposable-site integration double. Never loaded by production web/cron code.
    function curl_init($url) {
        if ($url !== 'https://api.typesafe.ai/v1/systemone') { throw new \RuntimeException('Unexpected origin'); }
        return new \stdClass();
    }
    function curl_setopt_array($ch, $options) { $ch->options = $options; }
    function curl_exec($ch) {
        $request = json_decode($ch->options[CURLOPT_POSTFIELDS], true, 32, JSON_THROW_ON_ERROR);
        if (!isset($request['state']['question'], $request['state']['skill_descriptions']) ||
                str_contains($ch->options[CURLOPT_POSTFIELDS], '"kind"') ||
                str_contains($ch->options[CURLOPT_POSTFIELDS], '"prts"')) {
            throw new \RuntimeException('Invalid model context');
        }
        $key = $request['state']['current_mastery']['LA000001'] ? 'c0' : 'c1';
        $probabilities = array_fill_keys(array_keys($request['questions']['response']['criteria']), 0);
        $probabilities[$key] = 1;
        $body = ['model' => decision::MODEL, 'answers' => ['response' => ['type' => 'choice',
            'choice' => $key, 'confidence' => 1, 'probabilities' => $probabilities]],
            'usage' => ['input_tokens' => 100, 'output_tokens' => 10]];
        if (!empty($GLOBALS['vl_bad_provider'])) { $body['answers']['response']['choice'] = 'not_a_candidate'; }
        $raw = json_encode($body);
        return ($ch->options[CURLOPT_WRITEFUNCTION])($ch, $raw) === strlen($raw);
    }
    function curl_getinfo($ch, $key) { return 200; }
    function curl_close($ch) {}
}
namespace {
    define('CLI_SCRIPT', true);
    require_once(__DIR__ . '/../../../config.php');
    require_once($CFG->libdir . '/clilib.php');
    [$options, $unknown] = cli_get_params(['allow-test-data' => false]);
    if ($unknown || !$options['allow-test-data']) { cli_error('Disposable sites only: --allow-test-data'); }
    \core\session\manager::set_user(get_admin());
    $CFG->local_virtuallearner_jev_enabled = true;
    $CFG->local_virtuallearner_jev_key = 'synthetic-disposable-fixture';
    $labid = \local_virtuallearner\service::demo('Virtual Learner decision transport fixture');
    $lab = $DB->get_record('local_vlearner_lab', ['id' => $labid], '*', MUST_EXIST);
    $scenario = \local_virtuallearner\policy::decode($lab->scenariojson);
    $pid = \local_virtuallearner\service::profile('Decision fixture', \local_virtuallearner\policy::defaults());
    $ids = \local_virtuallearner\service::create_runs($labid, $pid, $scenario, 79, 2, 1, false,
        ['model' => 'compare_jev', 'initial' => ['LA000001' => false],
            'skilllabels' => ['LA000001' => 'Cross term in binomial expansion']]);
    $admin = $USER;
    foreach ($ids as $id) {
        $run = $DB->get_record('local_vlearner_run', ['id' => $id], '*', MUST_EXIST);
        for ($step = 1; $step <= 2; $step++) {
            \core\session\manager::set_user(\core_user::get_user($run->userid));
            \local_virtuallearner\runner::step($id, $step);
            \core\session\manager::set_user($admin);
            $run = $DB->get_record('local_vlearner_run', ['id' => $id], '*', MUST_EXIST);
            if ($step === 1) {
                if ($run->status !== 'paused') { cli_error('Fixture first step failed: ' . $run->error); }
                \local_virtuallearner\service::control($id, 'resume');
            }
        }
        if ($run->status !== 'completed') { cli_error('Fixture did not complete: ' . $run->error); }
        foreach ($DB->get_records('local_vlearner_event', ['runid' => $id], 'stepno') as $event) {
            $data = \local_virtuallearner\policy::decode($event->payloadjson);
            if ((float)$data['results'][1]['fraction'] !== (float)($event->stepno - 1)) {
                cli_error('Decision fixture real grading differs');
            }
            if (str_contains($event->payloadjson, 'synthetic-disposable-fixture')) { cli_error('Credential reached event'); }
        }
    }
    $GLOBALS['vl_bad_provider'] = true;
    $failed = \local_virtuallearner\service::create_runs($labid, $pid, $scenario, 80, 1, 1, false, ['model' => 'jev']);
    $run = $DB->get_record('local_vlearner_run', ['id' => reset($failed)], '*', MUST_EXIST);
    \core\session\manager::set_user(\core_user::get_user($run->userid));
    \local_virtuallearner\runner::step($run->id, 1);
    \core\session\manager::set_user($admin);
    if ($DB->get_field('local_vlearner_run', 'status', ['id' => $run->id]) !== 'failed' ||
            $DB->count_records('quiz_attempts', ['userid' => $run->userid]) ||
            $DB->count_records('local_vlearner_event', ['runid' => $run->id])) {
        cli_error('Provider failure did not roll back atomically');
    }
    unset($CFG->local_virtuallearner_jev_enabled, $CFG->local_virtuallearner_jev_key);
    echo "PASS: actual Moodle/STACK rule-Jev paired runner, initial state, feedback learning and failure rollback with a simulated transport. No live Jev call.\n";
}
