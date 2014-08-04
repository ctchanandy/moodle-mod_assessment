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
 * Page to view a submission
 *
 * @package     mod
 * @subpackage  assessment
 * @author      Andy Chan, CITE, HKU <ctchan.andy@gmail.com>
 * @copyright   2012 Andy Chan <ctchan.andy@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("locallib.php");

$id = required_param('id', PARAM_INT);
$a = required_param('a', PARAM_INT);

$userid = optional_param('userid', 0, PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);

if (!$userid && !$groupid) {
    print_error('errornouseridgroupid', 'assessment');
}

if ($userid) {
    $workid = $userid;
    $workmode = 'user';
} else if ($groupid) {
    $workid = $groupid;
    $workmode = 'group';
}

if ($id) {
    if (! $cm = get_coursemodule_from_id('assessment', $id)) {
        print_error('invalidcoursemodule');
    }
    if (! $assessment = $DB->get_record("assessment", array("id"=>$cm->instance))) {
        print_error('invalidid', 'assessment');
    }
    if (! $course = $DB->get_record("course", array("id"=>$assessment->course))) {
        print_error('coursemisconf', 'assessment');
    }
} else {
    if (!$assessment = $DB->get_record("assessment", array("id"=>$a))) {
        print_error('invalidid', 'assessment');
    }
    if (! $course = $DB->get_record("course", array("id"=>$assessment->course))) {
        print_error('coursemisconf', 'assessment');
    }
    if (! $cm = get_coursemodule_from_instance("assessment", $assessment->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
}

require_login($course->id, false, $cm);
require_capability('mod/assessment:grade', context_module::instance($cm->id));

$url = new moodle_url('/mod/assessment/view_submission.php');
$url->param('id', $id);
$url->param('a', $a);
$url->param($workmode.'id', $workid);
$PAGE->set_url($url);
$PAGE->set_pagelayout('popup');

/// Load up the required assessment code
$assessmentinstance = new assessment_base($cm->id, $assessment, $cm, $course);
$assessmentinstance->view_submission($workid);
?>