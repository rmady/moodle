<?php
// This file is part of Moodle - http://moodle.org/
//
// Moodle is free software: you can redistribute it and/or modify
// it under the terms of the GNU General Public License as published by
// the Free Software Foundation, either version 3 of the License, orMoodle 4.4
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
use core_external\external_multiple_structure;
use core_external\external_value;
use core_external\external_warnings;

/**
 * External function to remove an assignment submissions.
 *
 * @package    mod_assign
 * @author     Rodrigo Mady <rodrigo.mady@moodle.com>
 * @copyright  2024 Moodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remove_submissions extends external_api {

    /**
     * Describes the parameters for remove submissions.
     *
     * @return external_function_parameters
     * @since Moodle 4.4
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters ([
                'assignid' => new external_value(PARAM_INT, 'Assignment instance id'),
                'userids' => new external_multiple_structure(
                    new external_value(PARAM_INT, 'user id'),
                    '1 or more user ids'
                ),
            ]
        );
    }

    /**
     * Call to remove assignment submissions from the last attempt.
     *
     * @param int $assignid The id of the assignment
     * @param array $userids Array of user ids to remove submissions
     * @return array result status and warnings
     * @since Moodle 4.4
     */
    public static function execute(int $assignid, array $userids): array {
        $result = $warnings = $errors = [];

        [
            'assignid' => $assignid,
            'userids'  => $userids
        ] = self::validate_parameters(self::execute_parameters(), [
            'assignid' => $assignid,
            'userids'  => $userids,
        ]);

        // Validate and get the assign.
        list($assign, $course, $cm, $context) = self::validate_assign($assignid);

        foreach ($userids as $userid) {
            if (!$assign->get_user_submission($userid, false)) {
                $errors[] = "Userid {$userid} error: No submission to remove";
            } else {
                $assign->remove_submission($userid);
            }
        }

        $errors = !empty($assign->get_error_messages()) ? array_merge($errors, $assign->get_error_messages()) : $errors;

        foreach ($errors as $errormsg) {
            $warnings[] = self::generate_warning(
                $assignid,
                'couldnotremovesubmission',
                $errormsg
            );
        }

        $result['status']   = empty($errors);
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describes the remove submissions return value.
     *
     * @return external_single_structure
     * @since Moodle 4.4
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'True if the assignment(s) was successfully removed and false if was not.'),
            'warnings' => new external_warnings(),
        ]);
    }
}
