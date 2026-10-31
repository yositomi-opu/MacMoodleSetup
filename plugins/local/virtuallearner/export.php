<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once(__DIR__ . '/../../config.php');
require_login();
\local_virtuallearner\service::require_manager();
$profileid = optional_param('profileid', 0, PARAM_INT);
$runid = optional_param('runid', 0, PARAM_INT);
$format = optional_param('format', 'json', PARAM_ALPHA);
if (!in_array($format, ['json', 'csv'], true)) { throw new invalid_parameter_exception('Invalid format'); }
if ($profileid) {
    $record = $DB->get_record('local_vlearner_profile', ['id' => $profileid], '*', MUST_EXIST);
    $payload = ['schema' => 1, 'name' => $record->name, 'profile' => json_decode($record->configjson, true)];
} else {
    $run = $DB->get_record('local_vlearner_run', ['id' => $runid], '*', MUST_EXIST);
    $events = $DB->get_records('local_vlearner_event', ['runid' => $runid], 'stepno');
    $payload = ['schema' => 1, 'runid' => $runid, 'status' => $run->status,
        'config' => json_decode($run->configjson, true), 'state' => json_decode($run->statejson, true),
        'error' => $run->error, 'events' => array_values(array_map(fn($e) => ['step' => $e->stepno,
            'attemptid' => $e->attemptid, 'timecreated' => $e->timecreated, 'observation' => json_decode($e->payloadjson, true)], $events))];
}
header('Cache-Control: private, no-store');
header('X-Content-Type-Options: nosniff');
header('Content-Disposition: attachment; filename="virtuallearner-' . ($profileid ? 'profile-' . $profileid : 'run-' . $runid) . '.' . $format . '"');
if ($format === 'json') {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($payload, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR);
} else {
    if ($profileid) { throw new invalid_parameter_exception('Profile export uses JSON'); }
    header('Content-Type: text/csv; charset=utf-8');
    $out = fopen('php://output', 'w'); fwrite($out, "\xEF\xBB\xBF");
    fputcsv($out, ['run', 'step', 'attempt', 'slot', 'questionid', 'variant', 'candidate', 'kind', 'answers',
        'state', 'fraction', 'valid', 'feedbackvisible', 'feedback', 'prts', 'before', 'after'], ',', '"', '');
    foreach ($payload['events'] as $event) {
        $observation = $event['observation'];
        foreach ($observation['results'] as $slot => $result) {
            $row = [$runid, $event['step'], $event['attemptid'], $slot];
            foreach (['questionid', 'variant', 'candidate', 'kind', 'answers', 'state', 'fraction', 'valid', 'feedbackvisible', 'feedback', 'prts'] as $key) {
                $row[] = is_array($result[$key]) ? json_encode($result[$key], JSON_UNESCAPED_UNICODE) : $result[$key];
            }
            $row[] = json_encode($observation['before']); $row[] = json_encode($observation['after']);
            $row = array_map(fn($value) => preg_match('/^[=+\-@\t\r]/', (string)$value) ? "'" . $value : $value, $row);
            fputcsv($out, $row, ',', '"', '');
        }
    }
    fclose($out);
}
exit;
