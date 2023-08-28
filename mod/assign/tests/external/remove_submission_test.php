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

require_once($CFG->dirroot . '/mod/assign/tests/generator.php');
require_once("$CFG->dirroot/mod/assign/tests/fixtures/event_mod_assign_fixtures.php");
require_once("$CFG->dirroot/mod/assign/tests/externallib_advanced_testcase.php");

/**
 * Test the remove_submission external function.
 *
 * @package    mod_assign
 * @category   test
 * @covers     \mod_assign\external\remove_submission
 * @author     Rodrigo Mady <rodrigo.mady@moodle.com>
 * @copyright  2023 Moodle
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class remove_submission_test extends \mod_assign\externallib_advanced_testcase {

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
    protected function prepare_and_add_submission(): array {
        $course  = $this->getDataGenerator()->create_course();
        $teacher = $this->getDataGenerator()->create_and_enrol($course, 'teacher');
        $student = $this->getDataGenerator()->create_and_enrol($course, 'student');
        $assign  = $this->create_instance($course);
        $this->add_submission($student, $assign);
        $this->submit_for_grading($student, $assign);
        return [$course, $student, $teacher, $assign];
    }

    /**
     * Test submission_removed by WS with invalid assing id.
     *
     */
    public function test_submission_removed_with_invalid_assign_id() {
        $this->expectException(\dml_exception::class);
        list($course, $student, $teacher, $assign) = $this->prepare_and_add_submission();
        remove_submission::execute('123', $student->id);
    }

    /**
     * Test submission_removed by WS with invalid user id.
     *
     */
    public function test_submission_removed_with_invalid_user_id() {
        global $DB;
        list($course, $student, $teacher, $assign) = $this->prepare_and_add_submission();
        // Assing mandadoty capabilities to remove one assingment attempt.
        $this->setUser($student);
        $role = $DB->get_record('role', ['shortname' => 'student'], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);
        assign_capability('mod/assign:submit', CAP_ALLOW, $role->id, $context->id, true);
        assign_capability('mod/assign:editothersubmission', CAP_ALLOW, $role->id, $context->id, true);
        $result = remove_submission::execute($assign->get_instance()->id, '123');
        $this->assertFalse($result['status']);
        $this->assertCount(1, $result['warnings']);
        $this->assertEquals($assign->get_instance()->id, $result['warnings'][0]['itemid']);
        $this->assertEquals('userdonthavesubmission', $result['warnings'][0]['warningcode']);
    }

    /**
     * Test submission_removed by WS.
     *
     */
    public function test_submission_removed() {
        global $DB;
        list($course, $student, $teacher, $assign) = $this->prepare_and_add_submission();
        // Assing mandadoty capabilities to remove one assingment attempt.
        $this->setUser($teacher);
        $role = $DB->get_record('role', ['shortname' => 'teacher'], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);
        assign_capability('mod/assign:submit', CAP_ALLOW, $role->id, $context->id, true);
        assign_capability('mod/assign:editothersubmission', CAP_ALLOW, $role->id, $context->id, true);
        $result = remove_submission::execute($assign->get_instance()->id, $student->id);
        $this->assertTrue($result['status']);
        $this->assertEmpty($result['warnings']);
        // Send the same request again.
        $result = remove_submission::execute($assign->get_instance()->id, $student->id);
        $this->assertCount(0, $result['warnings']);
        $this->assertFalse($result['status']);
    }

    /**
     * Test submission_removed without capabilities by WS.
     *
     */
    public function test_submission_removed_without_capabilities() {
        global $DB;
        list($course, $student, $teacher, $assign) = $this->prepare_and_add_submission();
        // Disable mandadoty capabilities to remove one assingment attempt.
        $this->setUser($teacher);
        $role = $DB->get_record('role', ['shortname' => 'teacher'], '*', MUST_EXIST);
        $context = \context_course::instance($course->id);
        assign_capability('mod/assign:submit', CAP_PROHIBIT, $role->id, $context->id, true);
        assign_capability('mod/assign:editothersubmission', CAP_PROHIBIT, $role->id, $context->id, true);
        $result = remove_submission::execute($assign->get_instance()->id, $student->id);
        $this->assertCount(1, $result['warnings']);
        $this->assertFalse($result['status']);
        $this->assertEquals('usercantremovesubmission', $result['warnings'][0]['warningcode']);
    }
}
