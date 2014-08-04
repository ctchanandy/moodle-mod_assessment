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
 * Download all files submitted in an assessment activity
 *
 * @package     mod
 * @subpackage  assessment
 * @author      Andy Chan, CITE, HKU <ctchan.andy@gmail.com>
 * @copyright   2012 Andy Chan <ctchan.andy@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("lib.php");

$id = required_param('id', PARAM_INT);
$a = required_param('a', PARAM_INT);

$url = new moodle_url('/mod/assessment/downloadall.php');

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

$PAGE->set_url($url);

require_login($course->id, false, $cm);
require_capability('mod/assessment:teachergrade', context_module::instance($cm->id));

$context = context_module::instance($cm->id);

if (!$assessment->numfiles) {
    print_error('notrequirefile', 'assessment');
    exit;
}

$submissions = $DB->get_records("assessment_submissions", array("assessmentid"=>$assessment->id), "");

if (empty($submissions)) {
    print_error('nosubmission', 'assessment');
} else {
    require_once($CFG->libdir.'/filelib.php');
    
    $filesforzipping = array();
    $fs = get_file_storage();
    
    $filename = str_replace(' ', '_', clean_filename($course->shortname.'-'.$assessment->name.'-'.$assessment->id.".zip")); //name of new zip file.
    
    $groupmode = groups_get_activity_groupmode($cm);
    $workmode = $assessment->workmode;
    
    foreach ($submissions as $submission) {
        if ($workmode == 1) { // Group work
            $a_workid = $submission->groupid;
            $a_work = $DB->get_record("groups", array("id"=>$a_workid),'id, name'); //get group name
            $a_name = $a_work->name;
        } else {
            $a_workid = $submission->userid;
            $a_work = $DB->get_record("user", array("id"=>$a_workid),'id,username,firstname,lastname'); //get user firstname/lastname
            $a_name = fullname($a_work);
        }
        
        $files = $fs->get_area_files($context->id, 'mod_assessment', 'submission', $submission->id, "timemodified", false);
        
        foreach ($files as $file) {
            // get files new name.
            $fileext = substr($file->get_filename(), strrpos($file->get_filename(), '.'));
            $fileoriginal = str_replace($fileext, '', $file->get_filename());
            $fileforzipname =  clean_filename($a_name . "_" . $fileoriginal."_".$a_workid.$fileext);
            // save file name to array for zipping.
            $filesforzipping[$fileforzipname] = $file;
        }
    } // end of foreach loop
    
    if ($zipfile = assessment_pack_files($filesforzipping)) {
        send_temp_file($zipfile, $filename); //send file and delete after sending.
    }
}
?>