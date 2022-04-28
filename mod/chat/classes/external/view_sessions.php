<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, or
// (at your option) any later version.
//
// Moodle is distributed in the hope that it will be useful,
// but WITHOUT ANY WARRANTY; without even the implied warranty of
// MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
// GNU General Public License for more details.
//
// You should have received a copy of the GNU General Public License
// along with Moodle.  If not, see <http://www.gnu.org/licenses/>.

namespace mod_chat\external;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_value;
use external_warnings;
use lang_string;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');

/**
 * External service to log viewed previous chat sessions
 *
 * This is mainly used by the mobile application.
 *
 * @package   mod_chat
 * @category  external
 * @copyright 2022 Rodrigo Mady <rodrigo.mady@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since Moodle 4.0
 */
class view_sessions extends external_api {
    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 4.0
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'cmid'   => new external_value(PARAM_INT, 'course module id', VALUE_REQUIRED),
            'start'  => new external_value(PARAM_INT, 'Start of period', VALUE_DEFAULT, 0),
            'end'    => new external_value(PARAM_INT, 'End of period', VALUE_DEFAULT, 0),
        ]);
    }

    /**
     * Execute the chat view sessions event.
     *
     * @param int $cmid the chat course module id
     * @param null|int $start
     * @param null|int $end
     * @return array
     * @throws \restricted_context_exception
     * @since Moodle 4.0
     */
    public static function execute(int $cmid, int $start, int $end): array {
        global $DB;
        $warnings = [];
        $status   = false;
        // Validate the cmid ID.
        [
            'cmid'  => $cmid,
            'start' => $start,
            'end'   => $end,
        ] = self::validate_parameters(self::execute_parameters(), [
            'cmid'  => $cmid,
            'start' => $start,
            'end'   => $end,
        ]);
        if (!$cm = get_coursemodule_from_id('chat', $cmid)) {
            throw new \moodle_exception('invalidcoursemodule', 'error');
        }
        if (!$chat = $DB->get_record('chat', ['id' => $cm->instance])) {
            throw new \moodle_exception('invalidcoursemodule', 'error');
        }

        $context = \context_module::instance($cm->id);
        // Check capability.
        if (!has_capability('mod/chat:readlog', $context)) {
            $warnings[] = [
                'item'        => $cm->id,
                'warningcode' => 'nopermissiontoseethechatlog',
                'message'     => get_string('nopermissiontoseethechatlog', 'chat')
            ];
        }
        $params = array(
            'context'  => $context,
            'objectid' => $chat->id,
            'other'    => array(
                'start' => $start,
                'end'   => $end
            )
        );
        if (empty($warnings) && $event = \mod_chat\event\sessions_viewed::create($params)) {
            $status = true;
            $event->add_record_snapshot('chat', $chat);
            $event->trigger();
        }
        $result = [
            'status'   => $status,
            'warnings' => $warnings
        ];
        return $result;
    }

    /**
     * Describe the return structure of the external service.
     *
     * @return external_single_structure
     * @since Moodle 4.0
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status'   => new external_value(PARAM_BOOL, 'status: true if success'),
            'warnings' => new external_warnings()
        ]);
    }
}
