<?php
// SPDX-License-Identifier: GPL-3.0-or-later
namespace local_virtuallearner\privacy;
defined('MOODLE_INTERNAL') || die();

final class provider implements \core_privacy\local\metadata\provider, \core_privacy\local\request\plugin\provider {
    public static function get_metadata(\core_privacy\local\metadata\collection $collection): \core_privacy\local\metadata\collection {
        foreach (['local_vlearner_lab', 'local_vlearner_profile', 'local_vlearner_run', 'local_vlearner_event'] as $table) {
            $collection->add_database_table($table, [], 'privacy:metadata');
        }
        return $collection;
    }
    public static function get_contexts_for_userid(int $userid): \core_privacy\local\request\contextlist {
        global $DB;
        $list = new \core_privacy\local\request\contextlist();
        if ($DB->record_exists('local_vlearner_profile', ['ownerid' => $userid])
                || $DB->record_exists('local_vlearner_lab', ['ownerid' => $userid])
                || $DB->record_exists_select('local_vlearner_run', 'ownerid = :owner OR userid = :student', ['owner' => $userid, 'student' => $userid])) {
            $list->add_system_context();
        }
        return $list;
    }
    public static function export_user_data(\core_privacy\local\request\approved_contextlist $contexts): void {
        global $DB;
        if (!in_array(\context_system::instance()->id, $contexts->get_contextids())) { return; }
        $id = $contexts->get_user()->id;
        $runs = $DB->get_records_select('local_vlearner_run', 'ownerid = :owner OR userid = :student', ['owner' => $id, 'student' => $id]);
        $events = [];
        foreach ($runs as $run) { $events[$run->id] = $DB->get_records('local_vlearner_event', ['runid' => $run->id]); }
        \core_privacy\local\request\writer::with_context(\context_system::instance())->export_data(
            [get_string('pluginname', 'local_virtuallearner')], (object)['runs' => $runs, 'events' => $events,
                'profiles' => $DB->get_records('local_vlearner_profile', ['ownerid' => $id]),
                'labs' => $DB->get_records('local_vlearner_lab', ['ownerid' => $id])]);
    }
    public static function delete_data_for_all_users_in_context(\context $context): void {
        global $DB;
        if ($context->contextlevel !== CONTEXT_SYSTEM) { return; }
        // Core quiz attempts/users belong to core providers. Never remove courses or submissions here.
        foreach (['local_vlearner_event', 'local_vlearner_run', 'local_vlearner_profile', 'local_vlearner_lab'] as $table) {
            $DB->delete_records($table);
        }
    }
    public static function delete_data_for_user(\core_privacy\local\request\approved_contextlist $contexts): void {
        global $DB;
        if (!in_array(\context_system::instance()->id, $contexts->get_contextids())) { return; }
        $id = $contexts->get_user()->id;
        $runs = $DB->get_records_select('local_vlearner_run', 'ownerid = :owner OR userid = :student', ['owner' => $id, 'student' => $id]);
        foreach ($runs as $run) {
            $DB->delete_records('local_vlearner_event', ['runid' => $run->id]);
            $DB->delete_records('local_vlearner_run', ['id' => $run->id]);
        }
        $DB->delete_records('local_vlearner_profile', ['ownerid' => $id]);
        $DB->set_field('local_vlearner_lab', 'ownerid', 0, ['ownerid' => $id]);
    }
}
