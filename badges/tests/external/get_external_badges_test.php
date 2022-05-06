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

/**
 * Tests for external function get_external_badges_test.
 *
 * @package    core_badges
 * @category   external
 * @copyright  2022 Rodrigo Mady <rodrigo.mady@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 4.1
 */

namespace core_badges\external;

use externallib_advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->libdir . '/badgeslib.php');

use core_badges\helper;

/**
 * Tests for external function get_external_badges_test.
 *
 * @package    core_badges
 * @category   external
 * @copyright  2022 Rodrigo Mady <rodrigo.mady@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 4.1
 */
class get_external_badges_test extends externallib_advanced_testcase {

    /** @var stdClass $course */
    private $course;

    /** @var stdClass $teacher */
    private $teacher;

    /** @var stdClass $student1 */
    private $student1;

    /** @var stdClass $student2 */
    private $student2;

    /** @var stdClass $backpackuser1 */
    private $backpackuser1;

    /** @var stdClass $backpackuser2 */
    private $backpackuser2;

    /**
     * Set up for every test.
     */
    public function setUp(): void {
        global $DB;
        $this->resetAfterTest();
        $this->setAdminUser();

        // Setup test data.
        $this->course = $this->getDataGenerator()->create_course();

        // Create users and enrolments.
        $this->student1 = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->student2 = $this->getDataGenerator()->create_and_enrol($this->course, 'student');
        $this->teacher  = $this->getDataGenerator()->create_and_enrol($this->course, 'editingteacher');

        // Create one external backpack.
        $total = $DB->count_records('badge_external_backpack');
        $this->assertEquals(1, $total);

        $data                 = new \stdClass();
        $data->apiversion     = OPEN_BADGES_V2P1;
        $data->backpackapiurl = 'https://dc.imsglobal.org/obchost/ims/ob/v2p1';
        $data->backpackweburl = 'https://dc.imsglobal.org';
        badges_create_site_backpack($data);
        $backpack = $DB->get_record('badge_external_backpack', ['backpackweburl' => $data->backpackweburl]);

        // Student 1 is connected to the backpack to be removed and has 2 collections.
        $this->backpackuser1 = helper::create_fake_backpack([
            'userid'             => $this->student1->id,
            'externalbackpackid' => $backpack->id
        ]);
        helper::create_fake_backpack_collection(['backpackid' => $this->backpackuser1->id]);
        helper::create_fake_backpack_collection(['backpackid' => $this->backpackuser1->id]);

        // Student 2 is connected to a different backpack and has 1 collection.
        $this->backpackuser2 = helper::create_fake_backpack(['userid' => $this->student2->id]);
        helper::create_fake_backpack_collection(['backpackid' => $this->backpackuser2->id]);
    }

    /**
     * Helper.
     *
     * @param int $userid
     *
     * @return array|bool|mixed
     */
    protected function get_external_badges(int $userid): ?array {
        $result = get_external_badges::execute($userid);
        return \external_api::clean_returnvalue(get_external_badges::execute_returns(), $result);
    }

    /**
     * Test get user badge by hash.
     * These is a basic test since the badges_get_my_user_badges used by the external function already has unit tests.
     *
     * @covers ::get_external_badges
     */
    public function test_get_external_badges() {
        global $DB;
        $this->setUser($this->student1);
        // Check the set up data.
        $total = $DB->count_records('badge_external_backpack');
        $this->assertEquals(2, $total);
        $total = $DB->count_records('badge_backpack');
        $this->assertEquals(2, $total);
        $total = $DB->count_records('badge_external');
        $this->assertEquals(3, $total);
        // Check student one external badges access.
        $result = $this->get_external_badges($this->student1->id);
        $this->assertTrue($result['status']);
        // Check student two external badges access.
        $result = $this->get_external_badges($this->student2->id);
        $this->assertTrue($result['status']);
        // Check teacher external badges access.
        $result = $this->get_external_badges($this->teacher->id);
        $this->assertFalse($result['status']);
    }

    /**
     * Verify the http_client delegates to curl during a "GET" request.
     *
     * @covers ::get_external_badges
     */
    public function test_get_external_badges_client_get_request() {
        $mockcurl = $this->createMock(\curl::class);
        $url      = 'https://example.com';
        $mockcurl->expects($this->once($url))
            ->method('get')
            ->with(
                $this->equalTo($url),
                $this->equalTo([]),
                $this->equalTo(['CURLOPT_HEADER' => 1])
            );
        $mockcurl->expects($this->any())
            ->method('get_info')
            ->willReturnCallback(function() {
                return ['header_size' => 0, 'http_code' => 200];
            });
        $mockcurl->expects($this->once())
            ->method('setHeader')
            ->with($this->equalTo(['someheader' => 'headervalue']));

        $mockcurl->setHeader(['someheader' => 'headervalue']);
        $mockcurl->get($url, [], ['CURLOPT_HEADER' => 1]);
    }
}
