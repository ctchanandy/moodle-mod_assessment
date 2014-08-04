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
 * Edit grade and view grade page
 *
 * @package     mod
 * @subpackage  assessment
 * @author      Andy Chan, CITE, HKU <ctchan.andy@gmail.com>
 * @copyright   2012 Andy Chan <ctchan.andy@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("locallib.php");

$id   = optional_param('id', 0, PARAM_INT);          // Course module ID
$a    = optional_param('a', 0, PARAM_INT);           // Assessment ID
$mode = optional_param('mode', 'all', PARAM_ALPHA);  // What mode are we in?
$type = optional_param('type', 0, PARAM_INT);
$by  = optional_param('by', '', PARAM_ALPHA);

$url = new moodle_url('/mod/assessment/assessment_grades.php');

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
    $url->param('id', $id);
} else {
    if (!$assessment = $DB->get_record("assessment", array("id"=>$a))) {
        print_error('invalidcoursemodule');
    }
    if (! $course = $DB->get_record("course", array("id"=>$assessment->course))) {
        print_error('coursemisconf', 'assessment');
    }
    if (! $cm = get_coursemodule_from_instance("assessment", $assessment->id, $course->id)) {
        print_error('invalidcoursemodule');
    }
    $url->param('a', $a);
}

require_login($course->id, false, $cm);
require_capability('mod/assessment:grade', context_module::instance($cm->id));

if ($mode !== 'all') {
    $url->param('mode', $mode);
}
$url->param('type', $type);
$url->param('by', $by);

$PAGE->set_pagelayout('popup');
$PAGE->set_url($url);
$PAGE->requires->js('/mod/assessment/assessment.js');

/// Load up the required assessment code
$assessment->workmode = $assessment->workmode ? 'group' : 'user';
$assessmentinstance = new assessment_base($cm->id, $assessment, $cm, $course);
$assessmentinstance->process_assessment_grades($mode, $type, $by);   // Display or process the submissions
?>