<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace local_virtuallearner\task;
defined('MOODLE_INTERNAL') || die();
final class advance extends \core\task\adhoc_task {
    public function execute() {
        $data = $this->get_custom_data();
        \local_virtuallearner\runner::step((int)$data->runid, (int)$data->step);
    }
}
