<?php
define('CLI_SCRIPT', true);
require '/var/www/html/config.php';
require_once $CFG->dirroot . '/question/type/stack/stack/cas/connector.healthcheck.class.php';
\core\session\manager::set_user(get_admin());
$check = new stack_cas_healthcheck(get_config('qtype_stack'));
foreach ($check->get_test_results() as $test) {
    echo ($test['result'] === false ? 'FAIL ' : 'OK   ') . $test['tag'] . ': ' .
        strip_tags($test['summary'] ?? '') . "\n";
    if ($test['result'] === false) { echo strip_tags($test['details'] ?? '') . "\n"; }
}
exit($check->get_overall_result() ? 0 : 1);
