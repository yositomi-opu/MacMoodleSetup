<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace local_virtuallearner\form;
defined('MOODLE_INTERNAL') || die();
global $CFG;
require_once($CFG->libdir . '/formslib.php');
final class profile extends \moodleform {
    public function definition() {
        $m = $this->_form;
        $m->addElement('header', 'profileheader', get_string('newprofile', 'local_virtuallearner'));
        $m->addElement('text', 'name', get_string('name', 'local_virtuallearner'), ['maxlength' => 100]);
        $m->setType('name', PARAM_TEXT); $m->addRule('name', null, 'required');
        $m->addElement('textarea', 'skills', get_string('skills', 'local_virtuallearner'), ['rows' => 3, 'cols' => 60]);
        $m->setType('skills', PARAM_RAW); $m->setDefault('skills', '{"LA000001":0}');
        $m->addHelpButton('skills', 'skills', 'local_virtuallearner');
        foreach (array_diff(array_keys(\local_virtuallearner\policy::defaults()), ['skills']) as $key) {
            $m->addElement('text', $key, get_string($key, 'local_virtuallearner'), ['type' => 'number', 'min' => 0, 'max' => 1, 'step' => '0.01']);
            $m->setType($key, PARAM_RAW); $m->setDefault($key, \local_virtuallearner\policy::defaults()[$key]);
            $m->addRule($key, null, 'required');
        }
        $this->add_action_buttons(false, get_string('newprofile', 'local_virtuallearner'));
    }
    public static function config(array $data): array {
        $result = ['skills' => \local_virtuallearner\policy::decode($data['skills'])];
        foreach (array_diff(array_keys(\local_virtuallearner\policy::defaults()), ['skills']) as $key) {
            if (!is_numeric($data[$key])) { throw new \InvalidArgumentException('Expected a probability: ' . $key); }
            $result[$key] = (float)$data[$key];
        }
        return \local_virtuallearner\policy::profile($result);
    }
    public function validation($data, $files) {
        $errors = parent::validation($data, $files);
        try { self::config($data); } catch (\Throwable $e) { $errors['skills'] = $e->getMessage(); }
        return $errors;
    }
}
