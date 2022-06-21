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
 * Tests for external function get_user_badge_by_hash_test.
 *
 * @package    core_badges
 * @category   external
 * @copyright  2022 Rodrigo Mady <rodrigo.mady@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 4.0
 */

namespace core_badges\external;

use externallib_advanced_testcase;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');
require_once($CFG->libdir . '/badgeslib.php');

/**
 * Tests for external function get_user_badge_by_hash_test.
 *
 * @package    core_badges
 * @category   external
 * @copyright  2022 Rodrigo Mady <rodrigo.mady@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 4.0
 */
class get_user_badge_by_hash_test extends externallib_advanced_testcase {

    /** @var stdClass $course */
    private $course;

    /** @var stdClass $teacher */
    private $teacher;

    /** @var stdClass $student1 */
    private $student1;

    /** @var stdClass $student2 */
    private $student2;

    /** @var stdClass $siteissuedbadge */
    private $sitebadge;

    /** @var stdClass $courseissuedbadge */
    private $coursebadge;

    /**
     * Set up for every test
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

        // Mock up a site badge.
        $now = time();
        $badge = new \stdClass();
        $badge->id = null;
        $badge->name = "Test badge site";
        $badge->description  = "Testing badges site";
        $badge->timecreated  = $now;
        $badge->timemodified = $now;
        $badge->usercreated  = (int) $this->teacher->id;
        $badge->usermodified = (int) $this->teacher->id;
        $badge->expiredate    = null;
        $badge->expireperiod  = null;
        $badge->type = BADGE_TYPE_SITE;
        $badge->courseid = null;
        $badge->messagesubject = "Test message subject for badge";
        $badge->message = "Test message body for badge";
        $badge->attachment = 1;
        $badge->notification = 0;
        $badge->status = BADGE_STATUS_ACTIVE;
        $badge->version = '1';
        $badge->language = 'en';
        $badge->imageauthorname = 'Image author';
        $badge->imageauthoremail = 'imageauthor@example.com';
        $badge->imageauthorurl = 'http://image-author-url.domain.co.nz';
        $badge->imagecaption = 'Caption';

        $badgeid   = $DB->insert_record('badge', $badge, true);
        $badge->id = $badgeid;
        $sitebadge = new \badge($badgeid);
        $sitebadge->issue($this->student1->id, true);
        $siteissuedbadge = $DB->get_record('badge_issued', [ 'badgeid' => $badge->id ]);

        $badge->issuername = $sitebadge->issuername;
        $badge->issuercontact = $sitebadge->issuercontact;
        $badge->issuerurl  = $sitebadge->issuerurl;
        $badge->nextcron   = $sitebadge->nextcron;
        $badge->issuedid   = (int) $siteissuedbadge->id;
        $badge->uniquehash = $siteissuedbadge->uniquehash;
        $badge->dateissued = (int) $siteissuedbadge->dateissued;
        $badge->dateexpire = $siteissuedbadge->dateexpire;
        $badge->visible    = (int) $siteissuedbadge->visible;
        $badge->email      = $this->student1->email;
        $context           = \context_system::instance();
        $badge->badgeurl   = \moodle_url::make_webservice_pluginfile_url($context->id, 'badges', 'badgeimage', $badge->id, '/',
                                                                            'f3')->out(false);

        // Hack the database to adjust the time each badge was issued.
        $DB->set_field('badge_issued', 'dateissued', $now, ['userid' => $this->student1->id, 'badgeid' => $badgeid]);
        $badge->status = BADGE_STATUS_ACTIVE_LOCKED;

        // Add an endorsement for the badge.
        $endorsement              = new \stdClass();
        $endorsement->badgeid     = $badgeid;
        $endorsement->issuername  = 'Issuer name';
        $endorsement->issuerurl   = 'http://endorsement-issuer-url.domain.co.nz';
        $endorsement->issueremail = 'endorsementissuer@example.com';
        $endorsement->claimid     = 'http://claim-url.domain.co.nz';
        $endorsement->claimcomment = 'Claim comment';
        $endorsement->dateissued  = $now;
        $endorsement->id          = $sitebadge->save_endorsement($endorsement);
        $badge->endorsement       = (array) $endorsement;

        // Add 2 alignments.
        $alignment          = new \stdClass();
        $alignment->badgeid = $badgeid;
        $alignment->id      = $sitebadge->save_alignment($alignment);
        $badge->alignment[] = (array) $alignment;

        $alignment->id        = $sitebadge->save_alignment($alignment);
        $badge->alignment[]   = (array) $alignment;
        $badge->relatedbadges = [];
        $this->sitebadge[]    = (array) $badge;

        // Now a course badge.
        $badge->id          = null;
        $badge->name        = "Test badge course";
        $badge->description = "Testing badges course";
        $badge->type        = BADGE_TYPE_COURSE;
        $badge->courseid    = (int) $this->course->id;

        $badge->id     = $DB->insert_record('badge', $badge, true);
        $coursebadge   = new \badge($badge->id );
        $coursebadge->issue($this->student1->id, true);
        $courseissuedbadge = $DB->get_record('badge_issued', [ 'badgeid' => $badge->id ]);

        $badge->issuername = $coursebadge->issuername;
        $badge->issuercontact = $coursebadge->issuercontact;
        $badge->issuerurl  = $coursebadge->issuerurl;
        $badge->nextcron   = $coursebadge->nextcron;
        $badge->issuedid   = (int) $courseissuedbadge->id;
        $badge->uniquehash = $courseissuedbadge->uniquehash;
        $badge->dateissued = (int) $courseissuedbadge->dateissued;
        $badge->dateexpire = $courseissuedbadge->dateexpire;
        $badge->visible    = (int) $courseissuedbadge->visible;
        $badge->email      = $this->student1->email;
        $context           = \context_course::instance($badge->courseid);
        $badge->badgeurl   = \moodle_url::make_webservice_pluginfile_url($context->id, 'badges', 'badgeimage', $badge->id , '/',
                                                                            'f3')->out(false);

        // Hack the database to adjust the time each badge was issued.
        $DB->set_field('badge_issued', 'dateissued', $now, array('userid' => $this->student1->id, 'badgeid' => $badge->id ));

        unset($badge->endorsement);
        $badge->alignment    = [];
        $this->coursebadge[] = (array) $badge;
        // Make the site badge a related badge.
        $sitebadge->add_related_badges([$badge->id]);
        $this->sitebadge[0]['relatedbadges'][0] = [
            'id'   => (int) $coursebadge->id,
            'name' => $coursebadge->name
        ];
        $this->coursebadge[0]['relatedbadges'][0] = [
            'id'   => (int) $sitebadge->id,
            'name' => $sitebadge->name
        ];
    }

    /**
     * Test get user badge by hash.
     * These is a basic test since the badges_get_my_user_badges used by the external function already has unit tests.
     * @covers ::get_user_badge_by_hash
     */
    public function test_get_user_badge_by_hash() {

        $this->setUser($this->student1);

        $result = get_user_badge_by_hash::execute($this->sitebadge[0]['uniquehash']);
        $result = \external_api::clean_returnvalue(get_user_badge_by_hash::execute_returns(), $result);
        $this->assertEquals($this->sitebadge, $result['badge']);

        $result = get_user_badge_by_hash::execute($this->coursebadge[0]['uniquehash']);
        $result = \external_api::clean_returnvalue(get_user_badge_by_hash::execute_returns(), $result);
        $this->assertEquals($this->coursebadge, $result['badge']);
    }

    /**
     * Test get user site badge by hash with restrictions.
     * @covers ::get_user_badge_by_hash
     */
    public function test_get_other_user_site_badge_by_hash() {
        $this->setUser($this->student2);

        $result = get_user_badge_by_hash::execute($this->sitebadge[0]['uniquehash']);
        $result = \external_api::clean_returnvalue(get_user_badge_by_hash::execute_returns(), $result);

        // Check that we don't have permissions for view the complete information for site badges.
        if (isset($result['badge'][0]['type']) && $result['badge'][0]['type'] == BADGE_TYPE_SITE) {
            $this->assertFalse(isset($result['badge'][0]['message']));

            // Check that we have permissions to see all the data in alignments and related badges.
            foreach ($result['badge'][0]['alignment'] as $alignment) {
                $this->assertTrue(isset($alignment['id']));
            }

            foreach ($result['badge'][0]['relatedbadges'] as $relatedbadge) {
                $this->assertTrue(isset($relatedbadge['id']));
            }
        } else {
            $this->assertTrue(isset($result['badge'][0]['message']));
        }
    }

    /**
     * Test get user course badge by hash with restrictions.
     * @covers ::get_user_badge_by_hash
     */
    public function test_get_other_user_course_badge_by_hash() {
        $this->setUser($this->student2);

        $result = get_user_badge_by_hash::execute($this->coursebadge[0]['uniquehash']);
        $result = \external_api::clean_returnvalue(get_user_badge_by_hash::execute_returns(), $result);

        // Check that we don't have permissions for view the complete information for course badges.
        if (isset($result['badge'][0]['type']) && $result['badge'][0]['type'] == BADGE_TYPE_COURSE) {
            $this->assertFalse(isset($result['badge'][0]['message']));
        } else {
            $this->assertTrue(isset($result['badge'][0]['message']));
        }
    }
}
