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

namespace mod_assign\external;

use core_external\external_function_parameters;
use core_external\external_single_structure;
use core_external\external_value;
use core_external\external_warnings;

/**
 * External function to remove an assignment submission.
 *
 * @package    mod_assign
 * @author     Rodrigo Mady <rodrigo.mady@moodle.com>
 * @copyright  2023 Moodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remove_submission extends external_api {

    /**
     * Describes the parameters for remove_submission.
     *
     * @return external_function_parameters
     * @since Moodle 4.3
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters ([
                'assignid' => new external_value(PARAM_INT, 'Assignment instance id'),
                'userid'   => new external_value(PARAM_INT, 'User id'),
            ]
        );
    }

    /**
     * Call to remove an assignment submission from the last attempt.
     *
     * @param int $assignid Assignment ID.
     * @param int $userid User ID.
     * @return array
     * @since Moodle 4.3
     */
    public static function execute(int $assignid, int $userid): array {
        global $USER;

        $result = $warnings = [];
        $status = false;

        [
            'assignid' => $assignid,
            'userid'   => $userid
        ] = self::validate_parameters(self::execute_parameters(), [
            'assignid' => $assignid,
            'userid'   => $userid
        ]);

        // Validate and get the assign.
        list($assign, $course, $cm, $context) = self::validate_assign($assignid);

        if (!$assign->can_edit_submission($userid, $USER->id)) {
            $warnings[] = self::generate_warning(
                $assignid,
                'usercantremovesubmission',
                get_string('usercantremovesubmission', 'assign')
            );
        }
        if (!($submission = $assign->get_user_submission($userid, false))) {
            $warnings[] = self::generate_warning(
                $assignid,
                'userdonthavesubmission',
                get_string('userdonthavesubmission', 'assign')
            );
        }

        // We can only remove draft and submitted attempts.
        if (empty($warnings) && isset($submission->status) &&
            ($submission->status === ASSIGN_SUBMISSION_STATUS_DRAFT ||
            $submission->status === ASSIGN_SUBMISSION_STATUS_SUBMITTED)) {
            $status = $assign->remove_submission($userid);
        }

        $result['status']   = $status;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the remove_submission return value.
     *
     * @return external_single_structure
     * @since Moodle 4.3
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'True if the assignment was successfully removed and false if was not.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
