<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace local_virtuallearner;
defined('MOODLE_INTERNAL') || die();

/** Executes one complete, real quiz attempt per step. Never writes grades directly. */
final class runner {
    public static function queue(\stdClass $run): void {
        if ($run->status !== 'queued') { return; }
        $task = new \local_virtuallearner\task\advance();
        $task->set_userid($run->userid);
        $task->set_custom_data(['runid' => $run->id, 'step' => $run->stepno + 1]);
        \core\task\manager::queue_adhoc_task($task, true);
    }

    public static function step(int $runid, int $expectedstep): void {
        global $DB, $USER, $CFG;
        $factory = \core\lock\lock_config::get_lock_factory('local_virtuallearner');
        $lock = $factory->get_lock('run:' . $runid, 0);
        if (!$lock) { return; }
        try {
            $run = $DB->get_record('local_vlearner_run', ['id' => $runid], '*', MUST_EXIST);
            if ($run->status !== 'queued' || $run->stepno + 1 !== $expectedstep) { return; }
            if ((int)$USER->id !== (int)$run->userid) {
                throw new \moodle_exception('nopermissions', 'error', '', 'Run as the synthetic student');
            }
            $lab = $DB->get_record('local_vlearner_lab', ['id' => $run->labid], '*', MUST_EXIST);
            $config = policy::decode($run->configjson);
            $profile = policy::profile($config['profile']);
            $design = experiment::options($config['design'] ?? [], $profile, $config['scenario']);
            $scenario = experiment::scenario($config['scenario'], $design, $profile);
            if (isset($config['designversion']) && $config['designversion'] !== experiment::VERSION) {
                throw new \RuntimeException('Experiment design version changed');
            }
            if (($config['policyversion'] ?? null) !== policy::VERSION) {
                throw new \RuntimeException('Policy version changed; clone the experiment');
            }
            $state = policy::decode($run->statejson);
            $transaction = $DB->start_delegated_transaction();
            try {
                require_once($CFG->dirroot . '/mod/quiz/locallib.php');
                $quiz = \mod_quiz\quiz_settings::create($lab->quizid, $run->userid);
                if ($quiz->get_courseid() != $lab->courseid || $quiz->is_preview_user()
                        || !is_enrolled($quiz->get_context(), $USER, 'mod/quiz:attempt', true)) {
                    throw new \RuntimeException('Synthetic student/course scope mismatch');
                }
                if (!in_array($quiz->get_quiz()->preferredbehaviour, ['deferredfeedback', 'dfexplicitvaildate'], true)
                        || $quiz->get_quiz()->attemptonlast) {
                    throw new \RuntimeException('Basic version requires deferred feedback, fixed question order and independent attempts');
                }
                // Core access rules are still authoritative, including attempt limits and availability.
                [$current, $number, $last, $messages] = quiz_validate_new_attempt($quiz,
                    $quiz->get_access_manager(time()), false, 0, false);
                if ($current || $messages) {
                    throw new \RuntimeException('Attempt unavailable: ' . implode('; ', $messages));
                }
                $variants = array_map(fn($spec) => $spec['variant'], $scenario);
                $attempt = quiz_prepare_and_start_new_attempt($quiz, $number, $last, false, [], $variants);
                $attemptobj = \mod_quiz\quiz_attempt::create($attempt->id);
                $slots = $attemptobj->get_slots();
                if (array_map('intval', array_keys($scenario)) !== array_map('intval', $slots)) {
                    throw new \RuntimeException('Scenario must cover every quiz slot, in order');
                }
                $responses = []; $choices = []; $decisions = [];
                $history = [];
                if ($design['model'] === 'jev') {
                    foreach ($DB->get_records('local_vlearner_event', ['runid' => $runid], 'stepno DESC', '*', 0, 3) as $e) {
                        $event = policy::decode($e->payloadjson);
                        foreach ($event['results'] as $previousslot => $r) {
                            $history[] = ['round' => $e->stepno, 'slot' => $previousslot, 'answers' => $r['answers'],
                                'feedback' => $r['feedbackvisible'] ? $r['feedback'] : []];
                        }
                    }
                }
                foreach ($slots as $slot) {
                    $qa = $attemptobj->get_question_attempt($slot);
                    $question = $qa->get_question();
                    $spec = $scenario[$slot];
                    if ($question->qtype->name() !== 'stack' || $question->id != $spec['questionid']
                            || $qa->get_variant() != $spec['variant']) {
                        throw new \RuntimeException('Question version/variant differs from frozen scenario (slot ' . $slot . ')');
                    }
                    if ($design['model'] === 'jev') {
                        $request = decision::request($profile, $state, $spec,
                            html_to_text($question->format_questiontext($qa), 0, false), $history, $design['skilllabels']);
                        $judgment = jev::choose($request, $spec);
                        $choice = $judgment['choice'];
                        $decisions[$slot] = $judgment['audit'];
                    } else {
                        $choice = $design['model'] === 'dina'
                            ? experiment::dina($profile, $state, $spec, $config['seed'], $expectedstep, $slot)
                            : policy::choose($profile, $state, $spec, $config['seed'], $expectedstep, $slot);
                        $decisions[$slot] = ['model' => $design['model'], 'adapterversion' => experiment::VERSION];
                    }
                    $responses[$slot] = response::prepare($question->inputs, $choice['answers']);
                    $choices[$slot] = $choice;
                }
                $attemptobj->process_submitted_actions(time(), false, $responses);
                $attemptobj->process_finish(time(), false);
                $options = $attemptobj->get_display_options(true);
                $before = $state; $results = [];
                foreach ($slots as $slot) {
                    $qa = $attemptobj->get_question_attempt($slot);
                    $question = $qa->get_question();
                    $valid = $qa->get_state()->is_graded() && $question->is_complete_response($qa->get_last_qt_data());
                    $feedback = []; $prts = [];
                    foreach ($question->prts as $name => $unused) {
                        if (!$valid) {
                            $prts[$name] = ['observed' => false, 'reason' => 'Input not graded'];
                            continue;
                        }
                        $result = $question->get_prt_result($name, $qa->get_last_qt_data(), true);
                        if (!$result->is_evaluated()) {
                            $prts[$name] = ['observed' => false, 'reason' => 'PRT not evaluated'];
                            continue;
                        }
                        if ($result->get_errors()) {
                            throw new \RuntimeException('STACK PRT evaluation error in slot ' . $slot . ' / ' . $name);
                        }
                        $prts[$name] = ['observed' => true, 'notes' => $result->get_answernotes(false), 'path' => $result->get_path(),
                            'errors' => $result->get_errors()];
                        if ($options->attempt && $options->feedback != \question_display_options::HIDDEN) {
                            $feedback[] = html_to_text((string)$result->get_feedback(), 0, false);
                        }
                    }
                    $visible = (bool)array_filter($feedback, fn($text) => trim($text) !== '');
                    $learningeligible = $valid && $choices[$slot]['kind'] !== 'invalid';
                    $state = policy::learn($profile, $state, $scenario[$slot]['skills'], $visible, $learningeligible,
                        $config['seed'], $expectedstep, $slot);
                    $results[$slot] = ['questionid' => $question->id, 'variant' => $qa->get_variant(),
                        'candidate' => $choices[$slot]['id'], 'kind' => $choices[$slot]['kind'],
                        'answers' => $choices[$slot]['answers'], 'state' => (string)$qa->get_state(),
                        'fraction' => $qa->get_fraction(), 'valid' => $valid,
                        'decision' => $decisions[$slot], 'feedbackvisible' => $visible, 'learningeligible' => $learningeligible, 'feedback' => $feedback, 'prts' => $prts];
                }
                $DB->insert_record('local_vlearner_event', (object)['runid' => $runid, 'stepno' => $expectedstep,
                    'attemptid' => $attempt->id, 'timecreated' => time(),
                    'payloadjson' => json_encode(['before' => $before, 'after' => $state, 'results' => $results],
                        JSON_UNESCAPED_UNICODE | JSON_THROW_ON_ERROR)]);
                $run->stepno = $expectedstep;
                $run->statejson = json_encode($state, JSON_THROW_ON_ERROR);
                $run->timemodified = time();
                $run->error = null;
                $continues = policy::draw($config['seed'], $expectedstep . ':persist') < $profile['persistence'];
                $run->status = $expectedstep >= $config['rounds'] ? 'completed' : (!$continues ? 'stopped' :
                    ($config['automatic'] ? 'queued' : 'paused'));
                $DB->update_record('local_vlearner_run', $run);
                $transaction->allow_commit();
            } catch (\Throwable $error) {
                $transaction->rollback($error);
            }
            self::queue($run);
        } catch (\Throwable $error) {
            if (isset($run) && (int)$USER->id === (int)$run->userid) {
                $DB->update_record('local_vlearner_run', (object)['id' => $runid, 'status' => 'failed',
                    'error' => substr($error->getMessage(), 0, 2000), 'timemodified' => time()]);
            }
            // Explicit failure status: task retry must not create another submission silently.
        } finally {
            $lock->release();
        }
    }
}
