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
 * Overview page for an assessment activity
 *
 * @package     mod
 * @subpackage  assessment
 * @author      Andy Chan, CITE, HKU <ctchan.andy@gmail.com>
 * @copyright   2012 Andy Chan <ctchan.andy@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("locallib.php");

$id = optional_param('id', 0, PARAM_INT); // Course Module ID, or
$a  = optional_param('a', 0, PARAM_INT);  // assessment ID

if ($id) {
    if (! $cm = get_coursemodule_from_id("assessment", $id)) {
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
        print_error('coursemisconf', 'assessment');
    }
    if (! $assessment = $DB->get_record("assessment", array("id"=>$cm->instance))) {
        print_error('invalidid', 'assessment');
    }
} else {
    if (! $assessment = $DB->get_record("assessment", array("id"=>$a))) {
        print_error('invalidid', 'assessment');
    }
    if (! $course = $DB->get_record("course", array("id"=>$assessment->course))) {
        print_error('coursemisconf', 'assessment');
    }
    if (! $cm = get_coursemodule_from_instance("assessment", $assessment->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
}

$url = new moodle_url('/mod/assessment/view.php');
$url->param('id', $id);
$url->param('a', $a);

$PAGE->set_url($url);
$PAGE->requires->js('/mod/assessment/assessment.js');
$PAGE->set_pagelayout('incourse');

require_course_login($course, true, $cm);

$context = get_context_instance(CONTEXT_MODULE, $cm->id);
$PAGE->set_context($context);

/// Print the page header
$strassessments = get_string("modulenameplural", "assessment");
$strassessment  = get_string("modulename", "assessment");

/// Print the main part of the page
$assessmentinstance = new assessment_base($cm->id, $assessment, $cm, $course);
$assessmentinstance->view();   // Actually display the assessment!

?>