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
 * This page lists all the instances of assessment in a particular course
 *
 * @package     mod
 * @subpackage  assessment
 * @author      Andy Chan, CITE, HKU <ctchan.andy@gmail.com>
 * @copyright   2012 Andy Chan <ctchan.andy@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");

$id = required_param('id', PARAM_INT);   // course

$url = new moodle_url('/mod/assessment/index.php', array('id'=>$id));

if (! $course = $DB->get_record("course", array("id"=>$id))) {
    print_error('coursemisconf');
}

require_course_login($course);
$PAGE->set_pagelayout('incourse');

add_to_log($course->id, "assessment", "view all", "index.php?id=$course->id", $course->fullname);

/// Get all required stringsassessment
$strassessments = get_string("modulenameplural", "assessment");
$strassessment  = get_string("modulename", "assessment");

/// Print the header
$PAGE->set_url($url);
$PAGE->navbar->add($strassessments);
$PAGE->set_title($strassessments);
$PAGE->set_heading($course->fullname);
echo $OUTPUT->header();

/// Get all the appropriate data
if (! $assessments = get_all_instances_in_course("assessment", $course)) {
    notice("There are no assessments", "../../course/view.php?id=$course->id");
    die;
}

/// Print the list of instances (your module will probably extend this)
$timenow = time();
$strname  = get_string("name");
$strweek  = get_string("week");
$strtopic  = get_string("topic");

$strteacherassessment = get_string('teacherassessment', 'assessment');
$strselfassessment = get_string('selfassessment', 'assessment');
$strpeerassessment = get_string('peerassessment', 'assessment');

$table = new html_table();

if ($course->format == "weeks") {
    $table->head  = array ($strweek, $strname, $strteacherassessment, $strselfassessment, $strpeerassessment);
} else if ($course->format == "topics") {
    $table->head  = array ($strtopic, $strname, $strteacherassessment, $strselfassessment, $strpeerassessment);
} else {
    $table->head  = array ($strname, $strteacherassessment, $strselfassessment, $strpeerassessment);
}

foreach ($assessments as $assessment) {
    if (!$assessment->visible) {
        //Show dimmed if the mod is hidden
        $link = "<a class=\"dimmed\" href=\"view.php?id=$assessment->coursemodule\">$assessment->name</a>";
    } else {
        //Show normal if the mod is visible
        $link = "<a href=\"view.php?id=$assessment->coursemodule\">$assessment->name</a>";
    }
    
    if ($DB->get_record('assessment_types', array('assessmentid'=>$assessment->id, 'type'=>0))) {
        $teacher = '<img src="pix/tick_green_big.gif" title="'.get_string('yes').'" />';
    } else {
        $teacher = '<img src="pix/cross_red_big.gif" title="'.get_string('no').'" />';
    }
    
    if ($DB->get_record('assessment_types', array('assessmentid'=>$assessment->id, 'type'=>1))) {
        $self = '<img src="pix/tick_green_big.gif" title="'.get_string('yes').'" />';
    } else {
        $self = '<img src="pix/cross_red_big.gif" title="'.get_string('no').'" />';
    }
    
    if ($DB->get_record('assessment_types', array('assessmentid'=>$assessment->id, 'type'=>2))) {
        $peer = '<img src="pix/tick_green_big.gif" title="'.get_string('yes').'" />';
    } else {
        $peer = '<img src="pix/cross_red_big.gif" title="'.get_string('no').'" />';
    }

    if ($course->format == "weeks" or $course->format == "topics") {
        $table->data[] = array ($assessment->section, $link, $teacher, $self, $peer);
    } else {
        $table->data[] = array ($link, $teacher, $self, $peer);
    }
}

echo "<br />";
echo html_writer::table($table);

/// Finish the page
echo $OUTPUT->footer();
?>