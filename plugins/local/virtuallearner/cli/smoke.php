<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// Synthetic data only. Run exclusively on a disposable CI installation.
define('CLI_SCRIPT', true);
require_once(__DIR__ . '/../../../config.php');
require_once($CFG->libdir . '/clilib.php');
[$options, $unknown] = cli_get_params(['create' => false, 'verify' => false, 'allow-test-data' => false]);
if ($unknown || !$options['allow-test-data'] || ($options['create'] === $options['verify'])) {
    cli_error('Use --create or --verify with --allow-test-data on a disposable site.');
}
\core\session\manager::set_user(get_admin());
$fixturepath = $CFG->dataroot . '/virtuallearner-ci.json';
if ($options['create']) {
    if (file_exists($fixturepath)) { cli_error('Existing smoke fixture; refusing to replace it'); }
    $labid = \local_virtuallearner\service::demo('Virtual Learner CI');
    $lab = $DB->get_record('local_vlearner_lab', ['id' => $labid], '*', MUST_EXIST);
    $scenario = \local_virtuallearner\policy::decode($lab->scenariojson);
    $profile = \local_virtuallearner\policy::defaults();
    $runs = [];
    foreach (['learning', 'mastered', 'invalid', 'dropout'] as $kind) {
        $config = $profile;
        if ($kind === 'mastered') { $config['skills']['LA000001'] = 1; }
        if ($kind === 'invalid') { $config['inputerror'] = 1; }
        if ($kind === 'dropout') { $config['persistence'] = 0; }
        $pid = \local_virtuallearner\service::profile('CI ' . $kind, $config);
        $ids = \local_virtuallearner\service::create_runs($labid, $pid, $scenario, 12345, 2);
        $runs[$kind] = reset($ids);
    }
    // A second identical seeded learner is expected to make the same decisions.
    $p = \local_virtuallearner\service::profile('CI replay', $profile);
    $ids = \local_virtuallearner\service::create_runs($labid, $p, $scenario, 12345, 2);
    $runs['replay'] = reset($ids);
    file_put_contents($fixturepath, json_encode($runs));
    echo "Created isolated synthetic lab and five experiments. Run core cron, then --verify.\n";
} else {
    $runs = json_decode(file_get_contents($fixturepath), true, 32, JSON_THROW_ON_ERROR);
    $observations = [];
    foreach ($runs as $kind => $id) {
        $run = $DB->get_record('local_vlearner_run', ['id' => $id], '*', MUST_EXIST);
        if ($run->status !== ($kind === 'dropout' ? 'stopped' : 'completed')) {
            cli_error($kind . ': ' . $run->status . ' ' . $run->error);
        }
        $events = array_values($DB->get_records('local_vlearner_event', ['runid' => $id], 'stepno'));
        if (count($events) !== ($kind === 'dropout' ? 1 : 2)) { cli_error('Wrong event count'); }
        foreach ($events as $index => $event) {
            $data = json_decode($event->payloadjson, true, 32, JSON_THROW_ON_ERROR);
            $result = $data['results'][1];
            if ($kind === 'invalid') {
                if ($result['valid'] || $data['after']['LA000001']) { cli_error('Invalid answer incorrectly graded or learned'); }
            } else {
                $expected = ($kind === 'mastered' || $index > 0) ? 1 : 0;
                if (!$result['valid'] || abs($result['fraction'] - $expected) > 0.000001 || !$result['feedbackvisible']) {
                    cli_error('Actual STACK grading/feedback differs: ' . json_encode($data));
                }
                if (!$data['after']['LA000001']) { cli_error('Feedback learning missing'); }
            }
            $actual = $DB->get_record('quiz_attempts', ['id' => $event->attemptid], '*', MUST_EXIST);
            if ((int)$actual->userid !== (int)$run->userid || $actual->preview || $actual->state !== 'finished') {
                cli_error('Not a finished real synthetic-student attempt');
            }
            $observations[$kind][] = $data;
        }
        // A stale duplicate queued task must do nothing.
        $saveduser = $USER;
        \core\session\manager::set_user(\core_user::get_user($run->userid));
        \local_virtuallearner\runner::step($id, 1);
        \core\session\manager::set_user($saveduser);
        if ($DB->count_records('local_vlearner_event', ['runid' => $id]) !== count($events)) { cli_error('Duplicate submission'); }
    }
    if ($observations['learning'] !== $observations['replay']) { cli_error('Seed replay differs'); }
    echo "PASS: real STACK wrong→feedback→correct, mastered, invalid/no learning, dropout, deterministic replay, duplicate task rejection.\n";
}
