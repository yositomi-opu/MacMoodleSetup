<?php
// SPDX-License-Identifier: GPL-3.0-or-later
// Virtual Learner Lab: deterministic, opt-in experiments on dedicated lab courses.
defined('MOODLE_INTERNAL') || die();
$plugin->component = 'local_virtuallearner';
$plugin->version = 2026103100;
$plugin->requires = 2024100700;
$plugin->maturity = MATURITY_ALPHA;
$plugin->release = '0.1.0';
$plugin->dependencies = ['mod_quiz' => ANY_VERSION, 'qtype_stack' => ANY_VERSION];
