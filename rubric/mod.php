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
 * Duplicate, update, create, delete, or load a rubric
 *
 * @package     mod
 * @subpackage  assessment
 * @author      Andy Chan, CITE, HKU <ctchan.andy@gmail.com>
 * @copyright   2012 Andy Chan <ctchan.andy@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../../config.php");
require_once("../lib.php"); // which includes rubric/lib.php

$course = required_param('course', PARAM_INT);
$action = required_param('action', PARAM_ALPHA); // update, create, delete, load, copy, view
$return = optional_param('return', "/mod/assessment/rubric/index.php?id=$course", PARAM_LOCALURL);

require_login($course);

confirm_sesskey();

if (! $course = $DB->get_record("course", array("id"=>$course))) {
    print_error('coursemisconf', 'assessment');
}

//$context = get_context_instance(CONTEXT_COURSE, $course->id);
$context = context_course::instance($course->id);
require_capability('moodle/course:manageactivities', $context);

$url = new moodle_url('/mod/assessment/rubric/mod.php');
$url->param('course', $course->id);
$url->param('action', $action);
$url->param('return', $return);
$PAGE->set_url($url);

$strassessments = get_string("modulenameplural", "assessment");
$strrubrics = get_string("rubrics", "assessment");

$PAGE->navbar->add($strassessments, new moodle_url("{$CFG->wwwroot}/mod/assessment/index.php?id={$course->id}"));
$PAGE->navbar->add($strrubrics, new moodle_url("{$CFG->wwwroot}/mod/assessment/rubric/index.php?id={$course->id}"));

switch($action){
    case 'popuplistview':
        redirect("index.php?return=$return&id={$course->id}&updatewnd=1");
        break;
    case 'create': 
        // Redirect to edit.php
        redirect("edit.php?return=$return&course={$course->id}&updatewnd=1");
        break;
    case 'update': 
        // Redirect to edit.php
        $id = required_param('rubric', PARAM_INT);
        $rubric = new rubric($id);

        if($course->id != $rubric->course->id){
            print_error('cidmismatchrid', 'assessment');
        }
        
        redirect("edit.php?return=$return&course={$course->id}&rubric=$id&updatewnd=1");
        
        break;
    case 'delete': 

        // checks to see if there are any dependent assessments, if not, deletes the rubric

        $id = required_param('rubric', PARAM_INT);

        $rubric = new rubric($id);
        $rubric->get_specs();
        
        // get rubric object for logging
        $rubric_obj = $DB->get_record('assessment_rubrics', array('id'=>$id));
        
        if($course->id != $rubric->course->id){
            print_error('cidmismatchrid', 'assessment');
        }

        $strrubric = get_string("rubric", "assessment");
        $strdelete = get_string("delete");

           $navlinks[] = array('name' => $strdelete, 'link' => '', 'type' => 'misc');

        // prints table of assessments
        if($assoc_list = $rubric->get_assoc_assessments() ){

            $table->head = array( $strassessments, '' );
            $table->align = array( 'left', 'center' );
            $table->size = array( '75%', '25%' );
            $table->width = '450';

            foreach($assoc_list as $item){
                $table->data[] = array( "<a href=\"{$CFG->wwwroot}/mod/assessment/view.php?id={$item->cm}\">{$item->name}</a>",
                                        "<a href=\"{$CFG->wwwroot}/course/modedit.php?update={$item->cm}&return=1\">Update</a>",
                                      );
            }

            $tableHTML = print_table($table, true);
            echo '<br />';
            
            $PAGE->set_title($strrubrics);
            $PAGE->set_heading($course->fullname);
            echo $OUTPUT->header();
            
            echo $OUTPUT->box(get_string('rubriccannotedit', 'assessment', $tableHTML), 'generalbox', 'notice');
            echo $OUTPUT->continue_button("{$CFG->wwwroot}$return");
            
            $rubric->view_footer();
        } else if(! $rubric->delete_instance()){ // delete it
            $PAGE->set_title($strrubrics);
            echo $OUTPUT->header();
            print_error('errordeleterubric', 'assessment', "{$CFG->wwwroot}/mod/assessment/rubric/index.php?id={$course->id}");
            $rubric->view_footer();
        } else { // success
            $event = \mod_assessment\event\rubric_deleted::create(array(
                'objectid' => $id,
                'courseid' => $course->id,
                'context' => context_course::instance($course->id)
            ));
            $event->add_record_snapshot('assessment_rubrics', $rubric_obj);
            $event->trigger();
            //add_to_log($course->id, "assessment", "delete rubric ($id)", "rubric/index.php?id={$course->id}", $rubric->name);
            redirect("{$CFG->wwwroot}$return");
        }
        break;
  case 'copy': 
        // duplicates all rubric data for current course and returns to edit it
        $updatewnd = optional_param('updatewnd', 0, PARAM_INT);
        $rubric = required_param('rubric', PARAM_INT);
        
        // Redirect to edit.php
        redirect("edit.php?&course={$course->id}&copyrubric=$rubric&updatewnd=$updatewnd");

        break;
    case 'popupcreate':
        redirect("edit.php?return=$return&course={$course->id}&updatewnd=1");
        break;
    case 'popupcopyimport':
        // show only disabled select boxes if there are no other rubrics available for duplication 
        $options = get_rubrics_as_options($course->id);
        if (empty($options)) {
            $rubricmenu = html_writer::select(array(), 'rubric', '', array("0"=>get_string('copyrubric', 'assessment')), array("id"=>"rubric", "disabled"=>"disabled"));
            
            // nothing to show ...
            echo '<div style="text-align:center"><form><fieldset class="invisiblefieldset">'.
                '<p>'.get_string('createrubricexist', 'assessment').'</p>'.
                $rubricmenu.
                "</fieldset></form></div>\n";
        } else {
            $rubricmenu = html_writer::select($options, 'rubric', '', array("0"=>get_string('copyrubric', 'assessment')), array("id"=>"rubric", "onchange"=>"submitform()"));
            
            echo "<div style=\"text-align:center\"><form method=\"post\" name=\"duplicate\" 
                    action=\"{$CFG->wwwroot}/mod/assessment/rubric/mod.php\">\n".
                '<fieldset class="invisiblefieldset">'.
                '<p>'.get_string('createrubricexist', 'assessment').'</p>'.
                $rubricmenu.
                '<input type="hidden" name="course" value="'.$course->id.'" />'.
                '<input type="hidden" name="sesskey" value="'.sesskey().'" />'.
                '<input type="hidden" name="action" value="copy" />'.
                '<input type="hidden" name="updatewnd" value="1" />'.
                "</fieldset></form></div>\n";
        }
    
        // Get name for duplicates
        echo '<script type="text/javascript">
                submitform = function(){
                    var obj = document.forms.duplicate.rubric;
                    if(obj.selectedIndex == 0) return;
                    document.forms.duplicate.submit();
                    return;
                }
              </script>';

        $rubric = new rubric(0,0,$course);
        $rubric->print_upload_form(1);

        // Close button
        echo '<center><input type="button" onclick="window.close()" value="'.get_string('close', 'assessment').'" /></center>';

        break;
    case 'view': 
        // prints out rubric (showing grades if available)
        // useful for previewing rubric (when deciding what to copy)
        $id = required_param('rubric', PARAM_INT);
        if(! $rubric = new rubric($id)){
            print_error('cannotgetrubricrecord', 'assessment', '', $id);
        }
        $rubric->view();
        break;
    default:
        print_error('noaction', 'assessment');
}
