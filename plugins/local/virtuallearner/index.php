<?php
// SPDX-License-Identifier: GPL-3.0-or-later
require_once(__DIR__ . '/../../config.php');
require_login();
\local_virtuallearner\service::require_manager();
$context = context_system::instance();
$runid = optional_param('runid', 0, PARAM_INT);
$labid = optional_param('labid', 0, PARAM_INT);
$PAGE->set_url(new moodle_url('/local/virtuallearner/index.php', array_filter(['runid' => $runid, 'labid' => $labid])));
$PAGE->set_context($context);
$PAGE->set_pagelayout('admin');
$PAGE->set_title(get_string('pluginname', 'local_virtuallearner'));
$PAGE->set_heading(get_string('pluginname', 'local_virtuallearner'));
$base = new moodle_url('/local/virtuallearner/index.php');
function vl_post_button(string $operation, string $label, array $fields = []): string {
    $html = html_writer::start_tag('form', ['method' => 'post', 'action' => new moodle_url('/local/virtuallearner/index.php')]);
    foreach (['sesskey' => sesskey(), 'operation' => $operation] + $fields as $key => $value) {
        $html .= html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $key, 'value' => $value]);
    }
    $html .= html_writer::tag('button', s($label), ['class' => 'btn btn-secondary my-1', 'type' => 'submit']);
    return $html . html_writer::end_tag('form');
}
$error = null;
if ($_SERVER['REQUEST_METHOD'] === 'POST' && ($operation = optional_param('operation', '', PARAM_ALPHA))) {
    require_sesskey();
    try {
        if ($operation === 'demo') {
            $labid = \local_virtuallearner\service::demo(optional_param('labname', '', PARAM_TEXT));
            redirect(new moodle_url($base, ['labid' => $labid]), get_string('created', 'local_virtuallearner'));
        } else if ($operation === 'importprofile') {
            $data = \local_virtuallearner\policy::decode(required_param('profilejson', PARAM_RAW));
            if (($data['schema'] ?? null) !== 1) { throw new InvalidArgumentException('Unsupported profile schema'); }
            \local_virtuallearner\service::profile($data['name'], $data['profile']);
            redirect($base, get_string('created', 'local_virtuallearner'));
        } else if ($operation === 'replay') {
            $old = $DB->get_record('local_vlearner_run', ['id' => required_param('runid', PARAM_INT)], '*', MUST_EXIST);
            $config = \local_virtuallearner\policy::decode($old->configjson);
            $profile = $DB->get_record('local_vlearner_profile', ['id' => $old->profileid], '*', MUST_EXIST);
            if ($config['policyversion'] !== \local_virtuallearner\policy::VERSION ||
                    \local_virtuallearner\policy::decode($profile->configjson) != $config['profile']) {
                throw new RuntimeException('Frozen profile/policy differs; create a new experiment');
            }
            $ids = \local_virtuallearner\service::create_runs($old->labid, $old->profileid, $config['scenario'],
                $config['seed'], $config['rounds'], 1, $config['automatic']);
            redirect(new moodle_url($base, ['runid' => reset($ids)]));
        } else if (in_array($operation, ['pause', 'resume'], true)) {
            \local_virtuallearner\service::control(required_param('runid', PARAM_INT), $operation);
            redirect(new moodle_url($base, ['runid' => required_param('runid', PARAM_INT)]));
        } else { throw new InvalidArgumentException('Unknown operation'); }
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
$profiles = $DB->get_records('local_vlearner_profile', [], 'id DESC');
$profileform = new \local_virtuallearner\form\profile($base);
if ($data = $profileform->get_data()) {
    try {
        \local_virtuallearner\service::profile($data->name, \local_virtuallearner\form\profile::config((array)$data));
        redirect($base, get_string('created', 'local_virtuallearner'));
    } catch (Throwable $e) { $error = $e->getMessage(); }
}
$labs = $DB->get_records('local_vlearner_lab', [], 'id DESC');
$lab = $labs[$labid] ?? ($labs ? reset($labs) : null);
$runform = null;
if ($lab && $profiles && !$runid) {
    $names = array_map(fn($p) => $p->name . ' (#' . $p->id . ')', $profiles);
    $runform = new \local_virtuallearner\form\experiment(new moodle_url($base, ['labid' => $lab->id]), ['lab' => $lab, 'profiles' => $names]);
    if ($data = $runform->get_data()) {
        try {
            if ((int)$data->labid !== (int)$lab->id) { throw new InvalidArgumentException('Lab mismatch'); }
            $ids = \local_virtuallearner\service::create_runs($lab->id, $data->profileid,
                \local_virtuallearner\policy::decode($data->scenario), $data->seed, $data->rounds,
                $data->count, (bool)$data->automatic);
            redirect(new moodle_url($base, ['runid' => reset($ids)]));
        } catch (Throwable $e) { $error = $e->getMessage(); }
    }
}
echo $OUTPUT->header();
echo $OUTPUT->notification(get_string('intro', 'local_virtuallearner'), 'info');
if ($error) { echo $OUTPUT->notification(s($error), 'error'); }
if ($runid) {
    $run = $DB->get_record('local_vlearner_run', ['id' => $runid], '*', MUST_EXIST);
    echo html_writer::link($base, get_string('back', 'local_virtuallearner')) . ' · ';
    echo html_writer::link(new moodle_url($base, ['runid' => $runid]), get_string('refresh', 'local_virtuallearner'));
    echo $OUTPUT->heading('#' . $run->id . ' — ' . get_string($run->status, 'local_virtuallearner') . ' (' . $run->stepno . ')', 3);
    if ($run->error) { echo $OUTPUT->notification(s($run->error), 'error'); }
    if ($run->status === 'queued') { echo vl_post_button('pause', get_string('pause', 'local_virtuallearner'), ['runid' => $runid]); }
    if (in_array($run->status, ['paused', 'failed'], true)) { echo vl_post_button('resume', get_string('resume', 'local_virtuallearner'), ['runid' => $runid]); }
    echo vl_post_button('replay', get_string('replay', 'local_virtuallearner'), ['runid' => $runid]);
    foreach (['csv', 'json'] as $format) {
        echo html_writer::link(new moodle_url('/local/virtuallearner/export.php', ['runid' => $runid, 'format' => $format]),
            get_string($format, 'local_virtuallearner'), ['class' => 'btn btn-secondary m-1']);
    }
    echo html_writer::start_tag('details') . html_writer::tag('summary', get_string('snapshot', 'local_virtuallearner'));
    echo html_writer::tag('pre', s(json_encode(json_decode($run->configjson), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)));
    echo html_writer::end_tag('details');
    echo $OUTPUT->heading(get_string('details', 'local_virtuallearner'), 3);
    $events = $DB->get_records('local_vlearner_event', ['runid' => $runid], 'stepno');
    if (!$events) { echo html_writer::tag('p', get_string('noresults', 'local_virtuallearner')); }
    foreach ($events as $event) {
        echo html_writer::tag('h4', get_string('step', 'local_virtuallearner') . ' ' . $event->stepno);
        echo html_writer::link(new moodle_url('/mod/quiz/review.php', ['attempt' => $event->attemptid]), 'Moodle #' . $event->attemptid);
        $observation = json_decode($event->payloadjson, true, 32, JSON_THROW_ON_ERROR);
        $table = new html_table();
        $table->head = array_map(fn($key) => get_string($key, 'local_virtuallearner'),
            ['slot', 'candidate', 'fraction', 'inputstatus', 'feedback']);
        foreach ($observation['results'] as $slot => $result) {
            $table->data[] = [$slot,
                get_string('kind_' . $result['kind'], 'local_virtuallearner') . ' / ' . s($result['candidate']),
                $result['fraction'] === null ? '—' : s((string)$result['fraction']),
                get_string($result['valid'] ? 'valid' : 'invalid', 'local_virtuallearner'),
                html_writer::tag('span', s(implode("\n", $result['feedback'])), ['style' => 'white-space:pre-wrap'])];
        }
        echo html_writer::div(html_writer::table($table), 'table-responsive');
        $changes = [];
        foreach ($observation['before'] as $skill => $value) {
            $changes[] = s($skill) . ': ' . get_string($value ? 'mastered' : 'unmastered', 'local_virtuallearner') .
                ' → ' . get_string($observation['after'][$skill] ? 'mastered' : 'unmastered', 'local_virtuallearner');
        }
        echo html_writer::tag('p', get_string('knowledge', 'local_virtuallearner') . ': ' . implode(' / ', $changes));
        // Imported maths, feedback and profiles are shown as escaped text, never executed in this UI.
        echo html_writer::start_tag('details') . html_writer::tag('summary', get_string('rawdata', 'local_virtuallearner'));
        echo html_writer::tag('pre', s(json_encode($observation, JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE)));
        echo html_writer::end_tag('details');
    }
} else {
    echo $OUTPUT->heading(get_string('labs', 'local_virtuallearner'), 3);
    foreach ($labs as $entry) {
        $course = get_course($entry->courseid);
        echo html_writer::tag('p', html_writer::link(new moodle_url($base, ['labid' => $entry->id]), s($course->fullname)) . ' · ' .
            html_writer::link(new moodle_url('/course/view.php', ['id' => $course->id]), 'Moodle'));
    }
    echo vl_post_button('demo', get_string('createlab', 'local_virtuallearner'));
    echo $OUTPUT->heading(get_string('profiles', 'local_virtuallearner'), 3);
    foreach ($profiles as $profile) {
        echo html_writer::tag('p', s($profile->name) . ' · ' . html_writer::link(
            new moodle_url('/local/virtuallearner/export.php', ['profileid' => $profile->id]), get_string('exportprofile', 'local_virtuallearner')));
    }
    $profileform->display();
    echo html_writer::start_tag('details');
    echo html_writer::tag('summary', get_string('importprofile', 'local_virtuallearner'));
    echo html_writer::start_tag('form', ['method' => 'post', 'action' => $base]);
    foreach (['sesskey' => sesskey(), 'operation' => 'importprofile'] as $key => $value) {
        echo html_writer::empty_tag('input', ['type' => 'hidden', 'name' => $key, 'value' => $value]);
    }
    echo html_writer::tag('textarea', '', ['name' => 'profilejson', 'rows' => 8, 'cols' => 70,
        'aria-label' => get_string('importprofile', 'local_virtuallearner'), 'required' => 'required']);
    echo html_writer::tag('button', get_string('importprofile', 'local_virtuallearner'), ['type' => 'submit', 'class' => 'btn btn-secondary']);
    echo html_writer::end_tag('form') . html_writer::end_tag('details');
    if ($runform) { $runform->display(); }
    echo $OUTPUT->heading(get_string('runs', 'local_virtuallearner'), 3);
    $table = new html_table();
    $table->head = ['ID', get_string('profile', 'local_virtuallearner'), get_string('status', 'local_virtuallearner'), get_string('step', 'local_virtuallearner'), ''];
    foreach ($DB->get_records('local_vlearner_run', [], 'id DESC', '*', 0, 100) as $run) {
        $config = \local_virtuallearner\policy::decode($run->configjson);
        $table->data[] = [$run->id, s($config['profilename']), get_string($run->status, 'local_virtuallearner'), $run->stepno,
            html_writer::link(new moodle_url($base, ['runid' => $run->id]), get_string('view', 'local_virtuallearner'))];
    }
    echo html_writer::table($table);
}
echo $OUTPUT->footer();
