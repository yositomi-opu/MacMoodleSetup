<?php
// SPDX-License-Identifier: GPL-3.0-or-later
defined('MOODLE_INTERNAL') || die();
$capabilities = ['local/virtuallearner:manage' => [
    'riskbitmask' => RISK_CONFIG | RISK_DATALOSS, 'captype' => 'write',
    'contextlevel' => CONTEXT_SYSTEM, 'archetypes' => ['manager' => CAP_ALLOW],
]];
