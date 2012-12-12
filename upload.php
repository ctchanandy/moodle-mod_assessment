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
 * Process submission from the form
 *
 * @package     mod
 * @subpackage  assessment
 * @author      Andy Chan, CITE, HKU <ctchan.andy@gmail.com>
 * @copyright   2012 Andy Chan <ctchan.andy@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../config.php");
require_once("locallib.php");
require_once("upload_form.php");

// Required parameters
$id = required_param('id', PARAM_INT); // CM ID
$userid = optional_param('userid', 0, PARAM_INT);
$groupid = optional_param('groupid', 0, PARAM_INT);

// Get required objects
if (! $cm = $DB->get_record("course_modules", array("id"=>$id))) {
    print_error('invalidcoursemodule');
}
if (! $course = $DB->get_record("course", array("id"=>$cm->course))) {
    print_error('coursemisconf', 'assessment');
}
if (! $assessment = $DB->get_record("assessment", array("id"=>$cm->instance))) {
    print_error('invalidid', 'assessment');
}

if (!$userid && !$groupid) {
    print_error('errornouseridgroupid', 'assessment');
}

if ($userid) {
    if (!$user = $DB->get_record('user', array('id'=>$userid))) {
        print_error('errornouser', 'assessment');
    }
    $workid = $userid;
    $workmode = 'user';
} else if ($groupid) {
    if (!$group = $DB->get_record('groups', array('id'=>$groupid))) {
        print_error('errornogroup', 'assessment');
    }
    $workid = $groupid;
    $workmode = 'group';
}

require_login($course->id, false, $cm);

$context = get_context_instance(CONTEXT_MODULE, $cm->id);
if (!has_capability('mod/assessment:upload', $context)) {
    print_error('errorcannotviewcontent', 'assessment');
}

// Setup page and header
$url = new moodle_url('/mod/assessment/upload.php');
$url->param('id', $id);
$url->param($workmode.'id', $workid);

$strassessments = get_string('modulenameplural', 'assessment');
$strassessment = get_string('modulename', 'assessment');
$strsubmission = get_string('submission', 'assessment');

$PAGE->set_pagelayout('popup');
$PAGE->set_url($url);
$PAGE->set_title(format_string($assessment->name)." : $strsubmission");
echo $OUTPUT->header();

// Form: display or process submitted data
$customdata = array();
$customdata['assessment'] = $assessment;
$customdata['context'] = $context;
$customdata['cm'] = $cm;
$customdata['course'] = $course;
if ($assessment->numfiles > 1) {
    $customdata['fileui_options'] = array('subdirs'=>1, 'maxbytes'=>$course->maxbytes, 'maxfiles'=>$assessment->numfiles);
} else {
    $customdata['fileui_options'] = array('subdirs'=>0, 'maxbytes'=>$CFG->userquota, 'maxfiles'=>1);
}
$mform = new mod_assessment_upload_form(null, $customdata);

//default 'action' for form is strip_querystring(qualified_me())
if ($mform->is_cancelled()) {
    close_window();
} else if ($fromform = $mform->get_data()) {
    //this branch is where you process validated data.
    if (!$title = $fromform->title) {
        $title = get_string("notitle", "assessment");
    }
    $assessmentinstance = new assessment_base($cm->id, $assessment, $cm, $course);
    
    $timenow = time();
    
    // add new submission record
    $newsubmission = new stdClass;
    $newsubmission->assessmentid = $assessment->id;
    $newsubmission->userid = $userid;
    $newsubmission->groupid = $groupid;
    $newsubmission->title = clean_param($title, PARAM_CLEAN);
    $newsubmission->itemid = $fromform->description['itemid'];
    $newsubmission->description = $fromform->description['text'];
    $newsubmission->timecreated = $timenow;
    if ($timenow > $assessment->submitend) {
        $newsubmission->late = 1;
    }
    $newsubmission->url = '';
    if ($fromform->url && is_valid_url($fromform->url)) {
        $newsubmission->url = $fromform->url;
    }
    
    if ($submission = $assessmentinstance->get_submission($workid)) {
        // update the submission
        $newsubmission->id = $submission->id;
        $newsubmission->timecreated = $submission->timecreated;
        //save new files.
        $newsubmission->description = file_save_draft_area_files($newsubmission->itemid, $context->id, 'mod_assessment', 'submission_description', $newsubmission->id, array('subdirs'=>0), $newsubmission->description);
        file_save_draft_area_files($fromform->submission, $context->id, 'mod_assessment', 'submission', $newsubmission->id, array('subdirs' => 0));
        
        $transaction = $DB->start_delegated_transaction();
        try {
            $DB->update_record('assessment_submissions', $newsubmission);
            $transaction->allow_commit();
        } catch(Exception $e) {
            $transaction->rollback($e);
            //return false;
        }
        add_to_log($course->id, "assessment", "update submission", "upload_form.php?id=".$cm->id."&userid=".$userid, $assessment->name, $cm->id);
    } else {
        // insert the submission
        // Begin transaction
        $transaction = $DB->start_delegated_transaction();
        try {
            $newsubmission->id = $DB->insert_record("assessment_submissions", $newsubmission);
            //now do filestuff
            file_save_draft_area_files($fromform->submission, $context->id, 'mod_assessment', 'submission', $newsubmission->id, array('subdirs' => 0));
            $newsubmission->description = file_save_draft_area_files($newsubmission->itemid, $context->id, 'mod_assessment', 'submission_description', $newsubmission->id, array('subdirs'=>0), $newsubmission->description);
            $DB->set_field('assessment_submissions', 'description', $newsubmission->description, array('id'=>$newsubmission->id));
            $transaction->allow_commit();
        } catch(Exception $e) {
            $transaction->rollback($e);
            //return false;
        }
        
        add_to_log($course->id, "assessment", "add submission", "upload.php?id=".$cm->id."&userid=".$userid, $assessment->name, $cm->id);
    }
} else {
    // this branch is executed if the form is submitted but the data doesn't validate and the form should be redisplayed
    // or on the first display of the form.
    $assessmentinstance = new assessment_base($cm->id, $assessment, $cm, $course);
    
    if ($submission = $assessmentinstance->get_submission($workid)) {
        $title = $submission->title;
        $description = $submission->description;
        $url = $submission->url;
        $timecreated = $submission->timecreated;
    } else {
        $title = '';
        $description = '';
        $url = '';
        $timecreated = -1;
    }
    
    $mformdata = array();
    $mformdata[$workmode.'id'] = $workid;
    $mformdata['title'] = $title;
    $mformdata['url'] = $url;
    
    $draftitemid = file_get_submitted_draft_itemid('submission');
    file_prepare_draft_area($draftitemid, $context->id, 'mod_assessment', 'submission', empty($submission->id)?null:$submission->id);
    $mformdata['submission'] = $draftitemid;
    
    $draftid_editor = file_get_submitted_draft_itemid('submission_description');
    $currenttext = file_prepare_draft_area($draftid_editor, $context->id, 'mod_assessment', 'submission_description', empty($submission->id) ? null : $submission->id, array('subdirs'=>0), empty($description) ? '' : $description);
    $mformdata['description'] = array('text'=>$currenttext,
                                      'format'=>editors_get_preferred_format(),
                                      'itemid'=>$draftid_editor);
    $mform->set_data($mformdata);
    
    add_to_log($course->id, 'assessment', 'view submission form', 'upload.php?id='.$cm->id.'&'.$workmode.'id='.$workid, fullname($USER, true));
    
    echo $OUTPUT->heading(get_string('updatesubmission', 'assessment'));
    echo $OUTPUT->box_start('generalbox assessmentuploadform');
    $mform->display();
    echo $OUTPUT->box_end();
    echo $OUTPUT->footer();
    exit;
}

// close this window and reload it opener
close_window(0, true);

echo $OUTPUT->footer();
?>