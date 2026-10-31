<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace local_virtuallearner\form;
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/formslib.php');
final class experiment extends \moodleform {
    public function definition() {
        $m = $this->_form;
        $m->addElement('header', 'runheader', get_string('newrun', 'local_virtuallearner'));
        $m->addElement('hidden', 'labid', $this->_customdata['lab']->id); $m->setType('labid', PARAM_INT);
        $m->addElement('select', 'profileid', get_string('profile', 'local_virtuallearner'), $this->_customdata['profiles']);
        foreach (['seed' => 1, 'rounds' => 3, 'count' => 1] as $key => $value) {
            $m->addElement('text', $key, get_string($key, 'local_virtuallearner'), ['type' => 'number', 'min' => 1]);
            $m->setType($key, PARAM_INT); $m->setDefault($key, $value); $m->addRule($key, null, 'required');
        }
        $models = [];
        foreach (['rule', 'dina', 'jev', 'compare_dina', 'compare_jev'] as $model) {
            $models[$model] = get_string('model_' . $model, 'local_virtuallearner');
        }
        $m->addElement('select', 'decisionmodel', get_string('decisionmodel', 'local_virtuallearner'), $models);
        $m->addHelpButton('decisionmodel', 'decisionmodel', 'local_virtuallearner');
        foreach (['initialstate', 'qmatrix', 'skilllabels'] as $key) {
            $m->addElement('textarea', $key, get_string($key, 'local_virtuallearner'), ['rows' => 4, 'cols' => 70]);
            $m->setType($key, PARAM_RAW); $m->setDefault($key, '{}');
            $m->addHelpButton($key, $key, 'local_virtuallearner');
        }
        $m->addElement('advcheckbox', 'automatic', get_string('automatic', 'local_virtuallearner'));
        $m->setDefault('automatic', 1);
        $m->addElement('textarea', 'scenario', get_string('scenario', 'local_virtuallearner'), ['rows' => 16, 'cols' => 90]);
        $m->setType('scenario', PARAM_RAW); $m->setDefault('scenario', json_encode(json_decode($this->_customdata['lab']->scenariojson), JSON_PRETTY_PRINT | JSON_UNESCAPED_UNICODE));
        $m->addHelpButton('scenario', 'scenario', 'local_virtuallearner');
        $this->add_action_buttons(false, get_string('newrun', 'local_virtuallearner'));
    }
    public function validation($data, $files) {
        global $DB;
        $errors = parent::validation($data, $files);
        try {
            $profile = $DB->get_record('local_vlearner_profile', ['id' => $data['profileid']], '*', MUST_EXIST);
            $config = \local_virtuallearner\policy::profile(\local_virtuallearner\policy::decode($profile->configjson));
            $scenario = \local_virtuallearner\policy::decode($data['scenario']);
            $options = \local_virtuallearner\experiment::options(self::options($data), $config, $scenario);
            \local_virtuallearner\experiment::scenario($scenario, $options, $config);
            if (in_array($options['model'], ['jev', 'compare_jev'], true) && !\local_virtuallearner\jev::available()) {
                $errors['decisionmodel'] = get_string('jevnotconfigured', 'local_virtuallearner');
            }
        } catch (\Throwable $e) { $errors['scenario'] = $e->getMessage(); }
        foreach (['rounds', 'count'] as $key) {
            if ($data[$key] < 1 || $data[$key] > 20) { $errors[$key] = '1–20'; }
        }
        if ($data['seed'] < 1 || $data['seed'] > 2147480000) { $errors['seed'] = '1–2147480000'; }
        return $errors;
    }
    public static function options(array $data): array {
        return ['model' => $data['decisionmodel'] ?? 'rule',
            'initial' => \local_virtuallearner\policy::decode($data['initialstate'] ?: '{}'),
            'qmatrix' => \local_virtuallearner\policy::decode($data['qmatrix'] ?: '{}'),
            'skilllabels' => \local_virtuallearner\policy::decode($data['skilllabels'] ?: '{}')];
    }

}
