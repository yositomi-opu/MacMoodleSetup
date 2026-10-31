<?php
// SPDX-License-Identifier: GPL-3.0-or-later
defined('MOODLE_INTERNAL') || die();
if ($hassiteconfig) {
    $settings = new admin_settingpage('local_virtuallearner_settings', get_string('jevsettings', 'local_virtuallearner'));
    $settings->add(new admin_setting_heading('local_virtuallearner/jev', get_string('jevsettings', 'local_virtuallearner'),
        get_string('jevsettings_help', 'local_virtuallearner')));
    $ADMIN->add('localplugins', $settings);
    $ADMIN->add('localplugins', new admin_externalpage('local_virtuallearner',
        get_string('pluginname', 'local_virtuallearner'), new moodle_url('/local/virtuallearner/index.php')));
}
