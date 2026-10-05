<?php
unset($CFG);
global $CFG;
$CFG = new stdClass();
$CFG->dbtype = 'pgsql';
$CFG->dblibrary = 'native';
$CFG->dbhost = 'db';
$CFG->dbname = 'moodle';
$CFG->dbuser = 'moodle';
$CFG->dbpass = getenv('DB_PASSWORD');
$CFG->prefix = 'mdl_';
$CFG->dboptions = ['dbpersist' => false, 'dbport' => 5432, 'dbsocket' => false];
$CFG->wwwroot = 'http://localhost:' . getenv('MOODLE_PORT');
$CFG->dataroot = '/var/moodledata';
$CFG->admin = 'admin';
$CFG->directorypermissions = 02770;
// Local authoring site: never send real email from a copied course.
$CFG->noemailever = true;
require_once(__DIR__ . '/lib/setup.php');
