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
 * Displays discussion
 *
 * @package     mod
 * @subpackage  assessment
 * @author      Andy Chan, CITE, HKU <ctchan.andy@gmail.com>
 * @copyright   2012 Andy Chan <ctchan.andy@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

//  Displays a post, and all the posts below it.
//  If no post is given, displays all posts in a discussion

require_once('../../config.php');

$d      = required_param('d', PARAM_INT);                // Discussion ID
$parent = optional_param('parent', 0, PARAM_INT);        // If set, then display this post and all children.
$mark   = optional_param('mark', '', PARAM_ALPHA);       // Used for tracking read posts if user initiated.
$postid = optional_param('postid', 0, PARAM_INT);        // Used for tracking read posts if user initiated.

if (!$discussion = $DB->get_record('assessment_discussions', array('id'=>$d))) {
    print_error('invaliddiscussionid', 'assessment');
}

if (!$assessment = $DB->get_record('assessment', array('id'=>$discussion->assessmentid))) {
    print_error('invalidid', 'assessment');
}

if (!$course = $DB->get_record('course', array('id'=>$assessment->course))) {
    print_error('coursemisconf', 'assessment');
}

if (!$cm = get_coursemodule_from_instance('assessment', $assessment->id, $course->id)) {
    print_error('invalidcoursemodule');
}

require_course_login($course, true, $cm);

$url = new moodle_url('/mod/assessment/discuss.php');
$url->param('d', $d);
$url->param('parent', $parent);
$url->param('mark', $mark);
$url->param('postid', $postid);
$PAGE->set_url($url);

// move this down fix for MDL-6926
require_once('locallib.php');

$modcontext = context_module::instance($cm->id);
$PAGE->set_context($modcontext);

require_capability('mod/assessment:viewdiscussion', $modcontext, NULL, true, 'noviewdiscussionspermission', 'assessment');

$event = \mod_assessment\event\discussion_viewed::create(array(
    'objectid' => $discussion->id,
    'courseid' => $course->id,
    'context' => context_module::instance($cm->id),
    'other' => array("parent"=>$parent)
));
$event->add_record_snapshot('assessment_discussions', $discussion);
$event->trigger();

if (!$parent) {
    $parent = $discussion->firstpost;
}

/*
$logparameters = "d=$discussion->id";
if ($parent) {
    $logparameters .= "&amp;parent=$parent";
} else {
    $parent = $discussion->firstpost;
}

add_to_log($course->id, 'assessment', 'view discussion', "discuss.php?$logparameters", $discussion->id, $cm->id);
*/

if (! $post = assessment_get_post_full($parent)) {
    print_error('discussionnotexist', 'assessment', "$CFG->wwwroot/mod/assessment/view.php?id=$cm->id");
}

if (!assessment_user_can_view_post($post, $course, $cm, $assessment, $discussion)) {
    print_error('cannotviewpost', 'assessment', "$CFG->wwwroot/mod/assessment/view.php?id=$cm->id");
}

//$PAGE->set_pagelayout('base');
$PAGE->set_pagelayout('popup');
$PAGE->navbar->add(get_string('discussionlist', 'assessment'), new moodle_url("discusslist.php?a=$assessment->id"));
$PAGE->navbar->add($discussion->name);
$PAGE->set_title($discussion->name);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

/// Check to see if groups are being used in this forum
/// If so, make sure the current person is allowed to see this discussion
/// Also, if we know they should be able to reply, then explicitly set $canreply for performance reasons

$canreply = assessment_user_can_post($assessment, $discussion, $USER, $cm, $course, $modcontext);

assessment_print_discussion($course, $cm, $assessment, $discussion, $post, $canreply);

// Force mark all as "read" once this page is loaded
assessment_tp_mark_discussion_read($USER, $discussion->id);

echo $OUTPUT->footer();

?>