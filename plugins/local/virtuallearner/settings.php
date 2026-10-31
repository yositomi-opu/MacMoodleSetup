<?php
// SPDX-License-Identifier: GPL-3.0-or-later
defined('MOODLE_INTERNAL') || die();
if ($hassiteconfig) {
    $ADMIN->add('localplugins', new admin_externalpage('local_virtuallearner',
        get_string('pluginname', 'local_virtuallearner'), new moodle_url('/local/virtuallearner/index.php')));
}
