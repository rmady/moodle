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

use mod_assign_test_generator;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once("$CFG->dirroot/mod/assign/tests/generator.php");
require_once("$CFG->dirroot/mod/assign/tests/fixtures/event_mod_assign_fixtures.php");
require_once("$CFG->dirroot/mod/assign/tests/externallib_advanced_testcase.php");

/**
 * Test the remove submissions external function.
 *
 * @package    mod_assign
 * @category   test
 * @covers     \mod_assign\external\remove_submissions
 * @author     Rodrigo Mady <rodrigo.mady@moodle.com>
 * @copyright  2024 Moodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remove_submissions_test extends \mod_assign\externallib_advanced_testcase {

    // Use the generator helper.
    use mod_assign_test_generator;
    /**
     * Called before every test.
     */
    protected function setUp(): void {
        parent::setUp();
        $this->resetAfterTest();
    }

    /**
     * Prepare and add submission.
     *
     * @return array
     */
    protected function prepare_and_add_submissions(): array {
        global $DB;
        $course   = $this->getDataGenerator()->create_course();
        $teacher  = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student1 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student2 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $student3 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign   = $this->create_instance($course);
        $this->add_submission($student1, $assign);
        $this->submit_for_grading($student1, $assign);
        $this->add_submission($student2, $assign);
        $this->submit_for_grading($student2, $assign);
        $role = $DB->get_record('role', ['shortname' => 'teacher'], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);
        assign_capability('mod/assign:editothersubmission', CAP_ALLOW, $role->id, $context->id, true);
        $this->setUser($teacher);
        return [$course, $student1, $student2, $student3, $teacher, $assign];
    }

    /**
     * Test remove submissions by WS with invalid assign id.
     *
     */
    public function test_remove_submissions_with_invalid_assign_id(): void {
        $this->expectException(\dml_exception::class);
        list($course, $student1, $student2, $student3, $teacher, $assign) = $this->prepare_and_add_submissions();
        remove_submissions::execute(123, [$student1->id]);
    }

    /**
     * Test remove submissions by WS.
     *
     */
    public function test_remove_submissions(): void {
        global $DB;
        list($course, $student1, $student2, $student3, $teacher, $assign) = $this->prepare_and_add_submissions();
        $submission1 = $assign->get_user_submission($student1->id, 0);
        $submission2 = $assign->get_user_submission($student2->id, 0);

        $result = remove_submissions::execute($assign->get_instance()->id, [$student1->id, $student2->id]);
        $this->assertTrue($result['status']);
        $this->assertEmpty($result['warnings']);

        // Make sure submissions were removed.
        $submission1query = $DB->get_record('assign_submission', ['id' => $submission1->id]);
        $submission2query = $DB->get_record('assign_submission', ['id' => $submission2->id]);
        $this->assertEquals(ASSIGN_SUBMISSION_STATUS_NEW, $submission1query->status);
        $this->assertEquals(ASSIGN_SUBMISSION_STATUS_NEW, $submission2query->status);
    }

    /**
     * Test remove submissions by WS with invalid user id.
     *
     */
    public function test_remove_submissions_with_invalid_user_id(): void {
        list($course, $student1, $student2, $student3, $teacher, $assign) = $this->prepare_and_add_submissions();
        $result = remove_submissions::execute($assign->get_instance()->id, [123]);
        $this->assertFalse($result['status']);
        $this->assertEquals('couldnotremovesubmission', $result['warnings'][0]['warningcode']);
    }

    /**
     * Test remove submissions without capabilities by WS.
     *
     */
    public function test_remove_submissions_without_capabilities(): void {
        global $DB;
        list($course, $student1, $student2, $student3, $teacher, $assign) = $this->prepare_and_add_submissions();
        // Disable mandatory capabilities to remove one assignment attempt.
        $role    = $DB->get_record('role', ['shortname' => 'teacher'], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);
        assign_capability('mod/assign:editothersubmission', CAP_PROHIBIT, $role->id, $context->id, true);

        $result = remove_submissions::execute($assign->get_instance()->id, [$student1->id, $student2->id, $student3->id]);
        $this->assertCount(3, $result['warnings']);
        $this->assertFalse($result['status']);
        $this->assertEquals('couldnotremovesubmission', $result['warnings'][0]['warningcode']);
        $this->assertEquals('couldnotremovesubmission', $result['warnings'][1]['warningcode']);
    }

    /**
     * Test user can remove own submission.
     *
     */
    public function test_remove_own_submission(): void {
        global $DB;
        list($course, $student1, $student2, $student3, $teacher, $assign) = $this->prepare_and_add_submissions();
        $this->setUser($student3);

        // Remove own submission when user has no submission to remove.
        $result = remove_submissions::execute($assign->get_instance()->id, [$student3->id]);
        $this->assertFalse($result['status']);
        $this->assertNotEmpty($result['warnings']);

        $this->add_submission($student3, $assign);
        // Remove own submission.
        $result = remove_submissions::execute($assign->get_instance()->id, [$student3->id]);
        $this->assertTrue($result['status']);
        $this->assertEmpty($result['warnings']);

        // Make sure submission was removed.
        $submission      = $assign->get_user_submission($student3->id, 0);
        $submissionquery = $DB->get_record('assign_submission', ['id' => $submission->id]);
        $this->assertEquals(ASSIGN_SUBMISSION_STATUS_NEW, $submissionquery->status);
    }

    /**
     * Test user can not remove another user's submission.
     *
     */
    public function test_cant_remove_another_user_submission(): void {
        list($course, $student1, $student2, $student3, $teacher, $assign) = $this->prepare_and_add_submissions();
        $student3 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $this->setUser($student1);
        $result = remove_submissions::execute($assign->get_instance()->id, [$student2->id, $student3->id]);
        $this->assertCount(2, $result['warnings']);
        $this->assertFalse($result['status']);
        $this->assertEquals('couldnotremovesubmission', $result['warnings'][0]['warningcode']);
        $this->assertEquals('couldnotremovesubmission', $result['warnings'][1]['warningcode']);
    }

    /**
     * Test when the user has no submissions to remove.
     *
     */
    public function test_remove_submissions_no_submission_to_delete(): void {
        global $DB;
        list($course, $student1, $student2, $student3, $teacher, $assign) = $this->prepare_and_add_submissions();
        $student3 = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $result = remove_submissions::execute($assign->get_instance()->id, [$student3->id, $student1->id, $student2->id]);
        $this->assertFalse($result['status']);
        $this->assertCount(1, $result['warnings']);
        $this->assertEquals('couldnotremovesubmission', $result['warnings'][0]['warningcode']);

        // Make sure the others submissions were removed.
        $submission      = $assign->get_user_submission($student1->id, 0);
        $submissionquery = $DB->get_record('assign_submission', ['id' => $submission->id]);
        $this->assertEquals(ASSIGN_SUBMISSION_STATUS_NEW, $submissionquery->status);
        $submission      = $assign->get_user_submission($student2->id, 0);
        $submissionquery = $DB->get_record('assign_submission', ['id' => $submission->id]);
        $this->assertEquals(ASSIGN_SUBMISSION_STATUS_NEW, $submissionquery->status);
    }
}
