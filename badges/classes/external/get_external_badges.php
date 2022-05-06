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

namespace core_badges\external;
use external_api;
use external_function_parameters;
use external_single_structure;
use external_multiple_structure;
use external_value;
use external_warnings;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->libdir . '/externallib.php');
require_once($CFG->libdir . '/badgeslib.php');

/**
 * External service to get user external badges.
 *
 * This is mainly used by the mobile application.
 *
 * @package   core_badges
 * @category  external
 * @copyright 2022 Rodrigo Mady <rodrigo.mady@moodle.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since     Moodle 4.1
 */
class get_external_badges extends external_api {
    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     * @since Moodle 4.1
     */
    public static function execute_parameters(): external_function_parameters {
        return new external_function_parameters([
            'userid' => new external_value(PARAM_INT, 'User id', VALUE_REQUIRED),
        ]);
    }

    /**
     * Execute the get user external badges.
     *
     * @param int $userid
     * @return array
     * @throws \restricted_context_exception
     * @since Moodle 4.1
     */
    public static function execute(int $userid): array {
        global $CFG, $DB;

        // Initialize return variables.
        $warnings = [];
        $status   = false;
        $result   = [
            'badges'   => [],
            'status'   => $status,
            'warnings' => $warnings,
        ];

        // Validate the user id.
        [
            'userid' => $userid
        ] = self::validate_parameters(self::execute_parameters(), [
            'userid' => $userid,
        ]);

        // Check $CFG settings for badges.
        if (empty($CFG->enablebadges)) {
            throw new moodle_exception('badgesdisabled', 'badges');
        }
        // Check $CFG settings for external badges.
        if (empty($CFG->badges_allowexternalbackpack)) {
            throw new moodle_exception('externalbackpackdisabled', 'badges');
        }

        $backpack     = $DB->get_record('badge_backpack', ['userid' => $userid]);
        $sitebackpack = badges_get_user_backpack();
        if ($sitebackpack && $backpack) {
            // If backpack is connected, need to select collections.
            $bp          = new \core_badges\backpack_api($sitebackpack, $backpack);
            $collections = $DB->get_records('badge_external', array('backpackid' => $backpack->id));
            foreach ($collections as $collection) {
                if ($badges = $bp->get_badges($collection, true)) {
                    $result['badges'] = array_map( function ($badge) {
                        return [
                            'id'          => $badge->id,
                            'name'        => $badge->name,
                            'type'        => $badge->type,
                            'image'       => $badge->image,
                            'hostedurl'   => $badge->hostedUrl,
                            'description' => $badge->description,
                            'issuedon'    => $badge->issuedOn,
                            'issuer'      => (array) $badge->issuer,
                            'recipient'   => (array) $badge->assertion->recipient,
                            'criteria'    => (array) $badge->assertion->badgeclass->criteria
                        ];
                    }, $badges);
                }
            }
            $status = true;
        } else if (empty($sitebackpack)) {
            $status     = false;
            $warnings[] = [
                'item'        => $userid,
                'warningcode' => 'sitebackpackisnotconnected',
                'message'     => get_string('error:sitebackpackisnotconnected', 'badges')
            ];
        } else {
            $status     = false;
            $warnings[] = [
                'item'        => $userid,
                'warningcode' => 'backpackisnotconnected',
                'message'     => get_string('error:backpackisnotconnected', 'badges')
            ];
        }
        $result['status']   = $status;
        $result['warnings'] = $warnings;
        return $result;
    }

    /**
     * Describe the return structure of the external service.
     *
     * @return external_single_structure
     * @since Moodle 4.1
     */
    public static function execute_returns(): external_single_structure {
        return new external_single_structure([
            'status' => new external_value(PARAM_BOOL, 'Whether the fetch was successful'),
            'badges'  => new external_multiple_structure(
                new external_single_structure([
                    'id'          => new external_value(PARAM_URL, 'Badgr id'),
                    'name'        => new external_value(PARAM_TEXT, 'Badge name'),
                    'image'       => new external_value(PARAM_URL, 'Image URL'),
                    'issuedon'    => new external_value(PARAM_TEXT, 'Issued date'),
                    'type'        => new external_value(PARAM_TEXT, 'Badgr type', VALUE_OPTIONAL),
                    'hostedurl'   => new external_value(PARAM_URL, 'Hosted URL', VALUE_OPTIONAL),
                    'description' => new external_value(PARAM_TEXT, 'Description', VALUE_OPTIONAL),
                    'issuer'      => new external_single_structure([
                        'id'      => new external_value(PARAM_URL, 'Issuer Badgr URL', VALUE_OPTIONAL),
                        '@context' => new external_value(PARAM_RAW, 'Issuer context', VALUE_OPTIONAL),
                        'type'    => new external_value(PARAM_TEXT, 'Issuer type', VALUE_OPTIONAL),
                        'name'    => new external_value(PARAM_TEXT, 'Issuer name', VALUE_OPTIONAL),
                        'url'     => new external_value(PARAM_URL, 'Issuer URL', VALUE_OPTIONAL),
                        'email'   => new external_value(PARAM_EMAIL, 'Issuer email', VALUE_OPTIONAL),
                        'image'   => new external_value(PARAM_URL, 'Issuer image', VALUE_OPTIONAL),
                        'description' => new external_value(PARAM_TEXT, 'Issuer description', VALUE_OPTIONAL),
                    ]),
                    'recipient'     => new external_single_structure([
                        'identity'  => new external_value(PARAM_TEXT, 'Recipient identity', VALUE_OPTIONAL),
                        'hashed'    => new external_value(PARAM_TEXT, 'Recipient hash', VALUE_OPTIONAL),
                        'type'      => new external_value(PARAM_TEXT, 'Recipient type', VALUE_OPTIONAL),
                        'plainid'   => new external_value(PARAM_TEXT, 'Recipient plain text identity', VALUE_OPTIONAL),
                        'salt'      => new external_value(PARAM_TEXT, 'Recipient salt', VALUE_OPTIONAL),
                    ]),
                    'criteria'      => new external_single_structure([
                        'id'        => new external_value(PARAM_URL, 'Criteria id URL', VALUE_OPTIONAL),
                        'narrative' => new external_value(PARAM_TEXT, 'How the user earn the badge', VALUE_OPTIONAL),
                    ]),
                ]
            )
            ),
            'warnings' => new external_warnings()
        ]);
    }
}
