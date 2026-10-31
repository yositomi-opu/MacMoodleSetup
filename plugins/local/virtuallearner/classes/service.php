<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace local_virtuallearner;
defined('MOODLE_INTERNAL') || die();

final class service {
    public static function require_manager(): void {
        require_capability('local/virtuallearner:manage', \context_system::instance());
    }

    public static function profile(string $name, array $config): int {
        global $DB, $USER;
        self::require_manager();
        $name = trim($name);
        if ($name === '' || \core_text::strlen($name) > 100) {
            throw new \InvalidArgumentException('Profile name: 1–100 characters');
        }
        return $DB->insert_record('local_vlearner_profile', (object)['name' => $name,
            'configjson' => json_encode(policy::profile($config), JSON_THROW_ON_ERROR),
            'ownerid' => $USER->id, 'timecreated' => time()]);
    }

    /** All sample data is additive and isolated; no existing course can become a lab. */
    public static function demo(string $name): int {
        global $DB, $USER, $CFG;
        self::require_manager();
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/course/modlib.php');
        require_once($CFG->dirroot . '/mod/quiz/locallib.php');
        require_once($CFG->dirroot . '/question/format/xml/format.php');
        $transaction = $DB->start_delegated_transaction();
        $course = create_course((object)['category' => $DB->get_field_sql('SELECT MIN(id) FROM {course_categories}'),
            'fullname' => trim($name) ?: 'Virtual Learner Lab',
            'shortname' => 'VL-LAB-' . bin2hex(random_bytes(6)), 'visible' => 1, 'enablecompletion' => 0]);
        $quiz = (object)['modulename' => 'quiz', 'module' => $DB->get_field('modules', 'id', ['name' => 'quiz'], MUST_EXIST), 'course' => $course->id, 'section' => 0,
            'name' => 'Virtual Learner Lab: expansion', 'intro' => 'Synthetic students only', 'introformat' => FORMAT_HTML,
            'visible' => 1, 'preferredbehaviour' => 'deferredfeedback', 'attempts' => 0,
            'attemptonlast' => 0, 'grademethod' => 1, 'grade' => 10, 'sumgrades' => 0,
            'questionsperpage' => 1, 'navmethod' => 'free', 'shuffleanswers' => 0,
            'timeopen' => 0, 'timeclose' => 0, 'timelimit' => 0, 'overduehandling' => 'autosubmit',
            'graceperiod' => 0, 'decimalpoints' => 2, 'questiondecimalpoints' => -1,
            'reviewattempt' => 0x11110, 'reviewcorrectness' => 0x11110, 'reviewmarks' => 0x11110,
            'reviewspecificfeedback' => 0x11110, 'reviewgeneralfeedback' => 0x11110,
            'reviewrightanswer' => 0x11110, 'reviewoverallfeedback' => 0x11110,
            'quizpassword' => '', 'showuserpicture' => 0, 'showblocks' => 0, 'subnet' => '', 'browsersecurity' => '-', 'delay1' => 0, 'delay2' => 0];
        foreach (['attempt', 'correctness', 'maxmarks', 'marks', 'specificfeedback', 'generalfeedback', 'rightanswer', 'overallfeedback'] as $field) {
            foreach (['immediately', 'open', 'closed'] as $when) { $quiz->{$field . $when} = 1; }
        }
        $module = add_moduleinfo($quiz, $course);
        $context = \context_course::instance($course->id);
        if (is_dir($CFG->dirroot . '/mod/qbank')) {
            $cm = \core_question\local\bank\question_bank_helper::create_default_open_instance($course, 'Virtual Learner Lab');
            $context = \context_module::instance($cm->id);
        }
        $category = (object)['name' => 'Virtual Learner Lab', 'contextid' => $context->id, 'info' => '',
            'infoformat' => FORMAT_HTML, 'stamp' => make_unique_id_code(), 'parent' => 0, 'sortorder' => 999];
        $category->id = $DB->insert_record('question_categories', $category);
        $format = new \qformat_xml();
        $format->setCategory($category); $format->setCourse($course); $format->setContexts([$context]);
        $format->setFilename(__DIR__ . '/../sample.xml'); $format->setRealfilename('virtuallearner-sample.xml');
        $format->setCatfromfile(false); $format->setContextfromfile(false); $format->setStoponerror(true);
        $format->set_display_progress(false);
        ob_start();
        try {
            if (!$format->importpreprocess() || !$format->importprocess() || !$format->importpostprocess()
                    || count($format->questionids) !== 1) {
                throw new \RuntimeException('Sample question import failed');
            }
        } finally {
            ob_end_clean();
        }
        $qid = (int)reset($format->questionids);
        $quizrecord = $DB->get_record('quiz', ['id' => $module->instance], '*', MUST_EXIST);
        quiz_add_quiz_question($qid, $quizrecord);
        \mod_quiz\quiz_settings::create($quizrecord->id)->get_grade_calculator()->recompute_quiz_sumgrades();
        $manual = enrol_get_plugin('manual');
        if (!$DB->record_exists('enrol', ['courseid' => $course->id, 'enrol' => 'manual'])) {
            $manual->add_instance($course);
        }
        $scenario = [1 => ['questionid' => $qid, 'variant' => 1, 'skills' => ['LA000001'], 'responses' => [
            ['id' => 'correct', 'kind' => 'correct', 'answers' => ['ans1' => 'x^2+2*x+1']],
            ['id' => 'missing_cross_term', 'kind' => 'misconception', 'skills' => ['LA000001'],
                'answers' => ['ans1' => 'x^2+1']],
            ['id' => 'arithmetic_slip', 'kind' => 'slip', 'answers' => ['ans1' => 'x^2+3*x+1']],
            ['id' => 'blank', 'kind' => 'invalid', 'answers' => ['ans1' => '']],
        ]]];
        $id = $DB->insert_record('local_vlearner_lab', (object)['courseid' => $course->id,
            'quizid' => $module->instance, 'ownerid' => $USER->id, 'scenariojson' => json_encode($scenario),
            'timecreated' => time()]);
        $transaction->allow_commit();
        return $id;
    }

    public static function create_runs(int $labid, int $profileid, array $scenario, int $seed, int $rounds,
            int $count = 1, bool $automatic = true): array {
        global $DB, $USER, $CFG;
        self::require_manager();
        if ($rounds < 1 || $rounds > 20 || $count < 1 || $count > 20 || $seed < 1 || $seed > 2147480000) {
            throw new \InvalidArgumentException('Rounds/students: 1–20; seed: 1–2147480000');
        }
        $lab = $DB->get_record('local_vlearner_lab', ['id' => $labid], '*', MUST_EXIST);
        $profile = $DB->get_record('local_vlearner_profile', ['id' => $profileid], '*', MUST_EXIST);
        $config = policy::profile(policy::decode($profile->configjson));
        $scenario = policy::scenario($scenario, $config);
        $course = get_course($lab->courseid);
        $instance = $DB->get_record('enrol', ['courseid' => $course->id, 'enrol' => 'manual', 'status' => ENROL_INSTANCE_ENABLED], '*', MUST_EXIST);
        $roles = get_archetype_roles('student');
        $role = reset($roles);
        if (!$role) { throw new \RuntimeException('Student role missing'); }
        require_once($CFG->dirroot . '/user/lib.php');
        $transaction = $DB->start_delegated_transaction(); $ids = [];
        for ($i = 0; $i < $count; $i++) {
            $token = bin2hex(random_bytes(8));
            // Core task execution requires an active auth method. Generate an unshared, random password.
            $userid = user_create_user((object)['username' => 'vl_' . $token, 'auth' => 'manual', 'password' => bin2hex(random_bytes(32)),
                'firstname' => 'Virtual', 'lastname' => 'Learner ' . $token,
                'email' => 'vl_' . $token . '@example.invalid', 'confirmed' => 1,
                'mnethostid' => $CFG->mnet_localhost_id, 'lang' => current_language()], true, false);
            enrol_get_plugin('manual')->enrol_user($instance, $userid, $role->id);
            $snapshot = ['policyversion' => policy::VERSION, 'profile' => $config, 'scenario' => $scenario,
                'seed' => $seed + $i, 'rounds' => $rounds, 'automatic' => $automatic,
                'profilename' => $profile->name, 'moodleversion' => $CFG->version,
                'stackversion' => get_config('qtype_stack', 'version')];
            $now = time();
            $ids[] = $DB->insert_record('local_vlearner_run', (object)['labid' => $labid,
                'profileid' => $profileid, 'userid' => $userid, 'ownerid' => $USER->id, 'status' => 'queued',
                'stepno' => 0, 'configjson' => json_encode($snapshot, JSON_THROW_ON_ERROR),
                'statejson' => json_encode(policy::initial($config, $seed + $i), JSON_THROW_ON_ERROR),
                'timecreated' => $now, 'timemodified' => $now]);
        }
        $transaction->allow_commit();
        foreach ($ids as $id) {
            runner::queue($DB->get_record('local_vlearner_run', ['id' => $id], '*', MUST_EXIST));
        }
        return $ids;
    }

    public static function control(int $id, string $action): void {
        global $DB;
        self::require_manager();
        $lock = \core\lock\lock_config::get_lock_factory('local_virtuallearner')->get_lock('run:' . $id, 5);
        if (!$lock) { throw new \RuntimeException('Step running; retry at the next boundary'); }
        try {
            $run = $DB->get_record('local_vlearner_run', ['id' => $id], '*', MUST_EXIST);
            if ($action === 'pause' && $run->status === 'queued') { $run->status = 'paused'; }
            else if ($action === 'resume' && in_array($run->status, ['paused', 'failed'], true)) { $run->status = 'queued'; }
            else { throw new \InvalidArgumentException('Action not available in this state'); }
            $run->timemodified = time();
            $DB->update_record('local_vlearner_run', $run);
            runner::queue($run);
        } finally { $lock->release(); }
    }
}
