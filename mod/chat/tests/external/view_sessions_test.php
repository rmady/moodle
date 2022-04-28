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
 * Tests for external function mod_chat_view_sessions.
 *
 * @package    mod_chat
 * @category   external
 * @copyright  2022 Rodrigo Mady <rodrigo.mady@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 4.0
 */

namespace mod_chat\external;

use externallib_advanced_testcase;
use moodle_exception;

defined('MOODLE_INTERNAL') || die();

global $CFG;

require_once($CFG->dirroot . '/webservice/tests/helpers.php');

/**
 * Test Class for external function mod_chat_view_sessions.
 *
 * @package    mod_chat
 * @category   external
 * @copyright  2022 Rodrigo Mady <rodrigo.mady@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @since      Moodle 4.0
 */
class view_sessions_test extends externallib_advanced_testcase {

    /** @var stdClass $course */
    private $course;

    /** @var stdClass $student1 */
    private $student1;

    /** @var stdClass $student2 */
    private $student2;

    /** @var array $chat */
    private $chat;

    /**
     * Set up for every test
     */
    public function setUp(): void {
        global $DB;
        $this->resetAfterTest();

        // Setup test data.
        $this->course = $this->getDataGenerator()->create_course();

        // Create users and enrolments.
        $this->student1 = $this->getDataGenerator()->create_and_enrol($this->course, 'student1');
        $this->student2 = $this->getDataGenerator()->create_user('student2');

        // Mock up a chat.
        $chat = [
            'course'   => $this->course->id
        ];
        $chat    = $this->getDataGenerator()->create_module('chat', $chat);
        $context = \context_module::instance($chat->cmid);
        $roleid  = self::getDataGenerator()->create_role();
        self::getDataGenerator()->role_assign($roleid, $this->student1->id, $context->id);
        assign_capability('mod/chat:readlog', CAP_ALLOW, $roleid, $context, true);

        $this->chat = [
            'cmid'  => $chat->cmid,
            'start' => 0,
            'end'   => 0,
        ];

        // Login as user 1.
        $this->setUser($this->student1);
        $chatsid  = chat_login_user($chat->id, 'ajax', 0, $this->course);
        $chatuser = $DB->get_record('chat_users', ['sid' => $chatsid]);

        // Get the messages for this chat session.
        $messages = chat_get_session_messages($chat->id, false, 0, 0, 'timestamp DESC');

        // We should have just 1 system (enter) messages.
        $this->assertCount(1, $messages);

        // This is when the session starts (when the first message - enter - has been sent).
        $sessionstart = reset($messages)->timestamp;

        // Send some messages.
        chat_send_chatmessage($chatuser, 'hello!');
        chat_send_chatmessage($chatuser, 'bye bye!');

        // Get the messages for this chat session.
        $messages = chat_get_session_messages($chat->id, false, 0, 0, 'timestamp DESC');

        // We should have 2 user and 1 system (enter) messages.
        $this->assertCount(3, $messages);

        // Fetch the chat sessions from the messages we retrieved.
        $sessions = chat_get_sessions($messages, true);

        // There should be only one session.
        $this->assertCount(1, $sessions);
    }

    /**
     * Helper
     *
     * @param ... $params
     * @return array|bool|mixed
     */
    protected function view_sessions(...$params): array {
        $result = view_sessions::execute(...$params);
        return \external_api::clean_returnvalue(view_sessions::execute_returns(), $result);
    }

    /**
     * Test for webservice view sessions.
     * @covers ::mod_chat_view_sessions
     */
    public function test_view_sessions(): void {
        $this->setUser($this->student1);
        $result = $this->view_sessions($this->chat['cmid'], $this->chat['start'], $this->chat['end']);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertTrue($result['status']);
    }

    /**
     * Test for webservice view sessions without login.
     * @covers ::mod_chat_view_sessions
     */
    public function test_view_sessions_without_login(): void {
        $this->setUser($this->student2);
        $result = $this->view_sessions($this->chat['cmid'], $this->chat['start'], $this->chat['end']);
        $this->assertArrayHasKey('status', $result);
        $this->assertArrayHasKey('warnings', $result);
        $this->assertFalse($result['status']);
    }

    /**
     * Test execute with no valid instance of cmid.
     * @covers ::mod_chat_view_sessions
     */
    public function test_view_sessions_no_instance(): void {
        $this->expectException(moodle_exception::class);
        $this->view_sessions(1234, 0, 0);
    }
}
