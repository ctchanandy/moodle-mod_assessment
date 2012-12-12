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
 * Page for viewing a rubric or list of rubrics
 *
 * @package     mod
 * @subpackage  assessment
 * @author      Andy Chan, CITE, HKU <ctchan.andy@gmail.com>
 * @copyright   2012 Andy Chan <ctchan.andy@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
 
require_once("../../../config.php");
require_once("../lib.php");

$id = required_param('id', PARAM_INT);   // course
$rubricid = optional_param('rubric', 0, PARAM_INT);   // rubric id
$viewall = optional_param('viewall', 0, PARAM_INT);

if (! $course = $DB->get_record("course", array("id"=>$id))) {
    print_error('coursemisconf', 'assessment');
}

require_course_login($course);

$context = get_context_instance(CONTEXT_COURSE, $id);
require_capability('mod/assessment:teachergrade', $context);

$url = new moodle_url('/mod/assessment/rubric/index.php');
$url->param('id', $id);
$url->param('rubricid', $rubricid);
$url->param('viewall', $viewall);
$PAGE->set_url($url);

$PAGE->set_pagelayout('popup');

$strassessment = get_string("modulename", "assessment");
$strassessments = get_string("modulenameplural", "assessment");
$strrubrics = get_string("rubrics", "assessment");

$PAGE->navbar->add($strassessments, new moodle_url($CFG->wwwroot."/mod/assessment/index.php?id=".$course->id));

if(!empty($rubricid)){
    // display a specific rubric
    $rubric = new rubric($rubricid);
    add_to_log($course->id, "assessment", "view rubric (id=$rubricid)", "rubric/index.php?id=$course->id&rubric=$rubricid", $rubric->name);
    
    $PAGE->set_title("$strassessments - $strrubrics");
    echo $OUTPUT->header();
    $rubric->view();
} else {
    // display all rubrics in a course
    add_to_log($course->id, "assessment", "view all rubrics", "rubric/index.php?id=$course->id", $course->fullname);
    
    $strrubric = get_string("rubric", "assessment");
    $strusedby = get_string("usedby", "assessment");
    $strcreationdate = get_string("creationdate", "assessment");
    $strweek = get_string("week");
    $strname = get_string("name");
    $strcreatedby = get_string("createdby", "assessment");
    $strpoints = get_string("points", "assessment");
    $strdelete = get_string("delete", "assessment");
    $stroperations = get_string("operations", "assessment");
    $strinuse = get_string("inuse", "assessment");
    $strnodelete = get_string("nodelete", "assessment");
    
    
    $PAGE->set_title("$strassessments - $strrubrics");
    echo $OUTPUT->header();
    
    $add_new_rubric_form = 
        html_writer::start_tag('form', array('method'=>'post', 'action'=>$CFG->wwwroot.'/mod/assessment/rubric/mod.php')).
        html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'course', 'value'=>$course->id)).
        html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'sesskey', 'value'=>sesskey())).
        html_writer::empty_tag('input', array('type'=>'hidden', 'name'=>'action', 'value'=>'create')).
        html_writer::empty_tag('input', array('type'=>'submit', 'value'=>get_string('createrubric', 'assessment'))).
        html_writer::end_tag('form');
    
    // show only disabled select boxes if there are no other rubrics available to duplication 
    $options = get_rubrics_as_options($course->id);
    if (empty($options)) {
        // nothing to show ...
        $rubricmenu = html_writer::select(array(), 'rubric', '', array("0"=>get_string('addrubric', 'assessment')), array("id"=>"rubric", "disabled"=>"disabled"));
        $copy_rubric_form = $rubricmenu;
    } else {
        $rubricmenu = html_writer::select($options, 'rubric', '', array("0"=>get_string('copyrubric', 'assessment')), array("id"=>"rubric", "onchange"=>"submitform()"));
        $copy_rubric_form = 
            "<div style=\"white-space:nowrap\"><form method=\"post\" name=\"duplicate\" 
                action=\"{$CFG->wwwroot}/mod/assessment/rubric/mod.php\">\n".
            $rubricmenu.
            '<input type="hidden" name="course" value="'.$course->id.'" />'.
            '<input type="hidden" name="sesskey" value="'.sesskey().'" />'.
            '<input type="hidden" name="action" value="copy" />'.
            '<input type="hidden" name="updatewnd" value="1" />'.
            "</form></div>\n";
    }

    // no rubrics in course
    if (!$rubrics = rubric_get_list_in_course($course->id)) {
        echo html_writer::empty_tag('br');
        echo $OUTPUT->box_start();
        echo $OUTPUT->heading(get_string('norubrics','assessment'));
        echo $OUTPUT->box_end();

    // print out rubrics
    } else {
        // View all rubrics
        if ($viewall) {
            $allrubrics = rubric_get_list_in_course();
            $course_rubrics = array_intersect(array_keys($allrubrics), array_keys($rubrics));
            $rubrics = $allrubrics;
            echo '<h1>'.get_string('viewallrubrics', 'assessment').'<span style="font-size:12pt;"> (<a href="'.$CFG->wwwroot.'/mod/assessment/rubric/index.php?id='.$course->id.'">'.get_string('changecourserubrics', 'assessment').'</a>)</span></h1>';
        } else {
            echo '<h1>'.get_string('viewcourserubrics', 'assessment').$course->fullname.'<span style="font-size:12pt;"> (<a href="'.$CFG->wwwroot.'/mod/assessment/rubric/index.php?id='.$course->id.'&viewall=1">'.get_string('changeallrubrics', 'assessment').'</a>)</span></h1>';
        }
        
        // Javascript's confirm delete
        echo '<script type="text/javascript">
                function confirmDelete(name, pts) {
                  return confirm("Are you sure you want to delete rubric:\n\n\t"+name+" ("+pts+" points)");
                }
              </script>';
        
        $table = new html_table();
        $table->head  = array ($strname, $strpoints, $strcreatedby, $strcreationdate, $strinuse, '');
        $table->align = array ("center", "center", "center", "center", "center", "center");
        $table->size = array( null, null, null, null, null, '170px' );
        $table->width = '95%';

        foreach($rubrics as $rubric){

            $rubricname = "<a href=\"index.php?id={$course->id}&rubric={$rubric->id}\">{$rubric->name}</a>";
            $user = "<a href=\"{$CFG->wwwroot}/user/view.php?id={$rubric->userid}\">{$rubric->first} {$rubric->last}</a>";
            $count = ($rubric->count == 1 ? "1 $strassessment" : "{$rubric->count} $strassessments");

            $update_rubric_form = 
                "<form method=\"post\" action=\"{$CFG->wwwroot}/mod/assessment/rubric/mod.php\" style=\"float:left;\">\n".
                '<input type="hidden" name="course" value="'.$course->id.'" />'.
                '<input type="hidden" name="rubric" value="'.$rubric->id.'" />'.
                '<input type="hidden" name="sesskey" value="'.sesskey().'" />'.
                '<input type="hidden" name="action" value="update" />'.
                '<input type="submit" value="'.get_string('update').'" style="font-size:10pt" />'.
                "</form>\n";
                
            $copy_rubric_button_form = 
                "<form method=\"post\" action=\"{$CFG->wwwroot}/mod/assessment/rubric/mod.php\" style=\"float:left;\">\n".
                '<input type="hidden" name="course" value="'.$course->id.'" />'.
                '<input type="hidden" name="rubric" value="'.$rubric->id.'" />'.
                '<input type="hidden" name="sesskey" value="'.sesskey().'" />'.
                '<input type="hidden" name="action" value="copy" />'.
                '<input type="hidden" name="updatewnd" value="1" />'.
                '<input type="submit" value="'.get_string('copy').'"" style="font-size:10pt" />'.
                "</form>\n";

            $delete_rubric_form = 
                "<form method=\"post\" onsubmit=\"return confirmDelete('".
                    str_replace("'","\\'",str_replace("\\","\\\\",$rubric->name))."',{$rubric->points})\" 
                    action=\"{$CFG->wwwroot}/mod/assessment/rubric/mod.php\" style=\"float:left;\">\n".
                '<input type="hidden" name="course" value="'.$course->id.'" />'.
                '<input type="hidden" name="rubric" value="'.$rubric->id.'" />'.
                '<input type="hidden" name="sesskey" value="'.sesskey().'" />'.
                '<input type="hidden" name="action" value="delete" />'.
                '<input type="submit" value="'.get_string('delete').'" style="font-size:10pt" />'.
                "</form>\n";
            
            $buttons = $update_rubric_form."&nbsp;&nbsp;".$copy_rubric_button_form."&nbsp;&nbsp;".$delete_rubric_form;
            
            // Only can duplicate if it is not rubric in your course
            if ($viewall && !in_array($rubric->id, $course_rubrics)) {
                $buttons = $copy_rubric_button_form;
            }
            
            $table->data[] = array ($rubricname, $rubric->points, $user, userdate($rubric->timemodified), $count, $buttons);
        }

        echo "<br />";

        echo html_writer::table($table);
    }

    // Buttons
    echo "<br />
            <table style=\"width:100%;text-align:center\">
               <tr><td align=\"right\" style=\"width:48%\">$add_new_rubric_form</td>
                   <td align=\"left\" style=\"width:52%\">$copy_rubric_form</td></tr>
            </table>";

    // Get name for duplicates
    echo '<script type="text/javascript">
              submitform = function(){
                  var obj = document.forms.duplicate.rubric;
                  if(obj.selectedIndex == 0) return;
                  document.forms.duplicate.submit();
                  return;
              }
          </script>';
}

echo $OUTPUT->footer();
    
?>