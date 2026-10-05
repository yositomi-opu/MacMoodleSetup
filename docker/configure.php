<?php
define('CLI_SCRIPT', true);
require '/var/www/html/config.php';
require_once $CFG->libdir . '/filterlib.php';
if (!get_config('local_macmoodlesetup', 'configured')) {
    foreach (['platform'=>'server', 'maximacommandserver'=>'http://maxima:8080/maxima',
              'maximaversion'=>'default', 'castimeout'=>60] as $key=>$value) {
        set_config($key, $value, 'qtype_stack');
    }
    filter_set_global_state('multilang', TEXTFILTER_ON, 1);
    filter_set_global_state('mathjaxloader', TEXTFILTER_ON, 2);
    filter_set_global_state('activitynames', TEXTFILTER_DISABLED);
    set_config('timezone', 'Asia/Tokyo');
    set_config('configured', 1, 'local_macmoodlesetup');
}
echo "Moodle / STACK configuration ready.\n";
