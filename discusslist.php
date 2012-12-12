<?php
// This file is part of Assessment module for Moodle - http://moodle.org/
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
 * Displays a list of posts in an assessment activity
 *
 * @package     mod
 * @subpackage  assessment
 * @author      Andy Chan, CITE, HKU <ctchan.andy@gmail.com>
 * @copyright   2012 Andy Chan <ctchan.andy@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once('../../config.php');

$a = required_param('a', PARAM_INT);                // assessment id

if (!$assessment = $DB->get_record('assessment', array('id'=>$a))) {
    print_error('invalidid', 'assessment');
}

if (!$course = $DB->get_record('course', array('id'=>$assessment->course))) {
    print_error('coursemisconf', 'assessment');
}

if (!$cm = get_coursemodule_from_instance('assessment', $assessment->id, $course->id)) {
    print_error('invalidcoursemodule');
}

$url = new moodle_url('/mod/assessment/discusslist.php');
$url->param('a', $assessment->id);
$PAGE->set_url($url);

require_course_login($course, true, $cm);

// move this down fix for MDL-6926
require_once('locallib.php');

$modcontext = get_context_instance(CONTEXT_MODULE, $cm->id);
if (!has_capability('mod/assessment:viewdiscussion', $modcontext)) {
    notice(get_string('noviewdiscussionspermission', 'assessment'));
}

$logparameters = "a=$assessment->id";

add_to_log($course->id, 'assessment', 'view discussion list', "discusslist.php?$logparameters", $assessment->id, $cm->id);

$PAGE->set_pagelayout('base');
$PAGE->navbar->add(get_string('discussionlist', 'assessment'));
$PAGE->set_title($assessment->name);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

/// Check to see if groups are being used in this forum
/// If so, make sure the current person is allowed to see this discussion
/// Also, if we know they should be able to reply, then explicitly set $canreply for performance reasons

assessment_print_discussions($course, $assessment);

echo $OUTPUT->footer();
?>