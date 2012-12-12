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
 * Adds or updates assessment rubric in a course
 *
 * @package     mod
 * @subpackage  assessment
 * @author      Andy Chan, CITE, HKU <ctchan.andy@gmail.com>
 * @copyright   2012 Andy Chan <ctchan.andy@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once("../../../config.php");
require_once("../lib.php"); // includes rubric/lib.php

$submitmode = optional_param('submitmode', '', PARAM_TEXT);
if ($submitmode == 'dimension' || $submitmode == 'haverange' || $submitmode == 'rowcol') {
    unset($_POST['sesskey']);
    unset($_POST['_qf__rubric_edit_form']);
}

$course = required_param('course', PARAM_INT);
$rubricid = optional_param('rubric', 0, PARAM_INT);
$updatewnd = optional_param('updatewnd', 0, PARAM_INT);
$return = optional_param('return', "/mod/assessment/rubric/index.php?id=$course", PARAM_LOCALURL);
$copyrubric = optional_param('copyrubric', 0, PARAM_INT);
$haverange = optional_param('haverange', 0, PARAM_INT);
$rowcoldefine = optional_param('rowcoldefine', 1, PARAM_INT);

$dimension_row = optional_param('dimension_row', 4, PARAM_INT);
$dimension_col = optional_param('dimension_col', 4, PARAM_INT);

$existform = new stdClass();
$existform->course = $course;
$existform->rubric = $rubricid;
$existform->updatewnd = $updatewnd;

if (! $course = $DB->get_record("course", array("id"=>$course))) {
    print_error('coursemisconf', 'assessment');
}

require_login($course->id);

$coursecontext = get_context_instance(CONTEXT_COURSE, $course->id);

$url = new moodle_url('/mod/assessment/rubric/edit.php');
$url->param('submitmode', $submitmode);
$url->param('course', $course->id);
$url->param('updatewnd', $updatewnd);
$url->param('return', $return);
$url->param('copyrubric', $copyrubric);
$url->param('haverange', $haverange);
$url->param('rowcoldefine', $rowcoldefine);
$url->param('dimension_row', $dimension_row);
$url->param('dimension_col', $dimension_col);
$PAGE->set_url($url);

$PAGE->set_pagelayout('popup');

$rubric = new rubric($rubricid, 0, $course);

// redirected from `case: copy` @ mod.php
if($copyrubric){
    $old_rubric = new rubric($copyrubric);
    
    // setup attribs
    $rubric->name = $old_rubric->name;
    $rubric->rowcoldefine = $old_rubric->rowcoldefine;
    
    $old_rubric->get_rowspecs();
    $old_rubric->get_colspecs();
    $old_rubric->get_specs();
    
    $rubric->add_rowspecs($old_rubric->rowspecs);
    $rubric->add_colspecs($old_rubric->colspecs);
    $rubric->add_specs($old_rubric->specs);
    
    $rubric->rowspec_map = $old_rubric->rowspec_map;
    $rubric->colspec_map = $old_rubric->colspec_map;
    $rubric->spec_map = $old_rubric->spec_map;
} else {
    $rubric->get_rowspecs();
    $rubric->get_colspecs();
    $rubric->get_specs();
}

$existform->name = isset($_POST['name']) ? $_POST['name']:$rubric->name;

$existform->description = isset($_POST['description']) ? $_POST['description']:$rubric->description;

$draftid_editor = file_get_submitted_draft_itemid('rubric_description');
$currenttext = file_prepare_draft_area($draftid_editor, $coursecontext->id, 'mod_assessment', 'rubric_description', empty($rubricid) ? null : $rubricid, array('subdirs'=>0), empty($existform->description) ? '' : $existform->description);
$existform->description = array('text'=>$currenttext,
                                'format'=>editors_get_preferred_format(),
                                'itemid'=>$draftid_editor);

if (isset($_POST['dimension_row'])) {
    $existform->dimension_row = ($dimension_row != $rubric->rowspec_count) ? $dimension_row : $rubric->rowspec_count;
} else {
    $existform->dimension_row = $rubric->rowspec_count ? $rubric->rowspec_count : 4;
}
if (isset($_POST['dimension_col'])) {
    $existform->dimension_col = ($dimension_col != $rubric->colspec_count) ? $dimension_col : $rubric->colspec_count;
} else {
    $existform->dimension_col = $rubric->colspec_count ? $rubric->colspec_count : 4;
}

if (isset($_POST['haverange'])) {
    $existform->haverange = $haverange;
} else if (isset($rubric->colspec_map[1]) && $rubric->colspecs[$rubric->colspec_map[1]]['maxpoints'] > 0) {
    $existform->haverange = $haverange = 1;
}

if (isset($_POST['rowcoldefine'])) {
    $rowcoldefine = $_POST['rowcoldefine'];
} else {
    $rowcoldefine = $rubric->rowcoldefine;
}

$existform->rowcoldefine = $rowcoldefine;

$strassessments = get_string("modulenameplural", "assessment");
$strrubrics = get_string("rubrics", "assessment");
$stractivity = get_string( $rubric->id ? "update" : "create" , "assessment");

$PAGE->navbar->add($strassessments, new moodle_url($CFG->wwwroot."/mod/assessment/index.php?id=".$rubric->course->id));
$PAGE->navbar->add($strrubrics, new moodle_url($CFG->wwwroot."/mod/assessment/rubric/index.php?id=".$rubric->course->id));
$PAGE->navbar->add($stractivity);

// If the rubric is in use, we can't modify it
if($rubric->is_used_to_grade()){
    if(!$updatewnd){
        $PAGE->set_title($strrubrics);
        echo $OUTPUT->header();
    }
    echo '<br />';
    if ($DB->record_exists('modules', array('name'=>'sampleassessment'))) {
        notice(get_string('rubricinusebyass', 'assessment'), "{$CFG->wwwroot}$return");
    } else {
        notice(get_string('rubricinusebyassandsass', 'assessment'), "{$CFG->wwwroot}$return");
    }
}

require_once('edit_form.php');
$reform = new rubric_edit_form('edit.php', null, 'post');
$reform->set_data($existform);

// add elements to the form on the fly because there're dynamic factors
$mform = $reform->getform();
$rubrictable = '<div class="rbtablediv"><table id="rbtable">';

if ($rowcoldefine == 1) {
    $textarea_width = floor(900/($existform->dimension_col+1));
    for ($i=0; $i<$existform->dimension_row+1; $i++) {
        $rubrictable .= '<tr>';
        for ($j=0; $j<$existform->dimension_col+1; $j++) {
            $rubrictable .= '<td valign="top">';
            // first row defining level name and weight 
            if ($i == 0) {
                if ($j != 0) {
                    $levelname = '';
                    $points = '';
                    $maxpoints = '';
                    if (sizeof($rubric->colspecs) > 0 && isset($rubric->colspec_map[$j])) {
                        $levelname = $rubric->colspecs[$rubric->colspec_map[$j]]['name'];
                        $points = $rubric->colspecs[$rubric->colspec_map[$j]]['points'];
                        $maxpoints = $rubric->colspecs[$rubric->colspec_map[$j]]['maxpoints'];
                        if ($maxpoints == 0) $maxpoints = '';
                    } else {
                        if (isset($_POST['colname_'.$j])) $levelname = $_POST['colname_'.$j];
                        if (isset($_POST['colweight_'.$j])) $points = $_POST['colweight_'.$j];
                        if (isset($_POST['colmaxweight_'.$j])) $maxpoints = $_POST['colmaxweight_'.$j];
                    }
                    if ($j == 1) $rubrictable .= get_string('mingrade', 'assessment');
                    if ($j == ($existform->dimension_col)) $rubrictable .= get_string('maxgrade', 'assessment');
                    $rubrictable .= '<br />';
                    $rubrictable .= '<input type="text" class="colname" name="colname_'.$j.'" size="18" value="'.$levelname.'" />';
                    $rubrictable .= '<br />';
                    $rubrictable .= '<input type="text" class="colweight" name="colweight_'.$j.'" maxlength="4" size="3" value="'.$points.'" />';
                    if ($haverange == 1) {
                       $rubrictable .= ' '.get_string('to', 'assessment').' <input type="text" class="colmaxweight" name="colmaxweight_'.$j.'" maxlength="4" size="3" value="'.$maxpoints.'" />';
                    }
                    $rubrictable .= ' '.get_string('pts', 'assessment');
                }
            } else {
                // first column defining criteria name
                if ($j == 0) {
                    $criname = '';
                    if (sizeof($rubric->rowspecs) > 0 && isset($rubric->rowspec_map[$i])) {
                        $criname = $rubric->rowspecs[$rubric->rowspec_map[$i]]['name'];
                        $enablecustompoint = $rubric->rowspecs[$rubric->rowspec_map[$i]]['custompoint'];
                    } else {
                        if (isset($_POST['rowname_'.$i])) $criname = $_POST['rowname_'.$i];
                        $enablecustompoint = isset($_POST['custompoint_'.$i]) && $_POST['custompoint_'.$i] == 1;
                    }
                    $rubrictable .= '<input type="text" class="rowname" name="rowname_'.$i.'" size="18" value="'.$criname.'" />';
                    $custompointchecked = $enablecustompoint ? ' checked="checked"':'';
                    $haverangedisplay = $enablecustompoint ? 'block':'none';
                    $rubrictable .= '<br />'.get_string('custompoint', 'assessment').
                                    '<input type="checkbox" id="custompoint_'.$i.'" name="custompoint_'.$i.'" value="1" onclick="enable_custom_point('.$i.')" '.$custompointchecked.' />'.
                                    '<div id="haverangediv_'.$i.'" style="display:'.$haverangedisplay.'">'.get_string('haverange', 'assessment').
                                    ' <select id="haverange_'.$i.'" name="haverange_'.$i.'" onchange="row_have_range('.$i.')">'.
                                    '<option value="0">'.get_string('no').'</option>'.
                                    '<option value="1">'.get_string('yes').'</option>'.
                                    '</select></div>';
                } else {
                    $specid = '';
                    $description = '';
                    $points = '';
                    $maxpoints = '';
                    if (sizeof($rubric->specs) > 0 && isset($rubric->rowspec_map[$i]) && isset($rubric->colspec_map[$j])) {
                        $specid = $rubric->spec_map[$rubric->rowspec_map[$i]][$rubric->colspec_map[$j]];
                        $description = $rubric->specs[$specid]['description'];
                        $enablecustompoint = $rubric->rowspecs[$rubric->rowspec_map[$i]]['custompoint'];
                        if ($enablecustompoint) {
                           $points = $rubric->specs[$specid]['points'];
                           $maxpoints = $rubric->specs[$specid]['maxpoints'];
                           $enablemaxpoints = $maxpoints ? 1 : 0;
                        } else {
                           $enablemaxpoints = 0;
                        }
                    } else {
                        $enablecustompoint = isset($_POST['custompoint_'.$i]) && $_POST['custompoint_'.$i] == 1;
                        $enablemaxpoints = $enablecustompoint && isset($_POST['haverange_'.$i]) && $_POST['haverange_'.$i] == 1;
                        if (isset($_POST['rbdescription_'.$i.'_'.$j]))
                           $description = $_POST['rbdescription_'.$i.'_'.$j];
                    }
                    
                    $custompointdisplay = $enablecustompoint ? '':'none';
                    $custompointdisable = $enablecustompoint ? '':' disabled="disabled"';
                    $maxpointsdisplay = $enablemaxpoints ? '':'none';
                    $maxpointsdisable = $enablemaxpoints ? '':' disabled="disabled"';
                    $textarea_resize = 'onblur="this.style.height=\'50px\'" onfocus="this.style.height=\'100px\'"';
                    $rubrictable .= '<div id="pointsdiv_'.$i.'_'.$j.'" style="display:'.$custompointdisplay.'">
                                     <input type="text" class="colweight" id="points_'.$i.'_'.$j.'" name="points_'.$i.'_'.$j.'" maxlength="4" size="3" value="'.$points.'"'.$custompointdisable.' />'.
                                     '<span id="maxpointsspan_'.$i.'_'.$j.'" style="display:'.$maxpointsdisplay.'"> '.
                                     get_string('to', 'assessment').
                                     ' <input type="text" class="colmaxweight" id="maxpoints_'.$i.'_'.$j.'" name="maxpoints_'.$i.'_'.$j.'" maxlength="4" size="3" value="'.$maxpoints.'"'.$maxpointsdisable.' /></span>'.
                                     ' '.get_string('pts', 'assessment').'</div>'.
                                     '<textarea class="celldesc" '.$textarea_resize.' style="height:width:50px;'.$textarea_width.'px" name="rbdescription_'.$i.'_'.$j.'">'.$description.'</textarea>';
                }
            }
            $rubrictable .= '</td>';
        }
        $rubrictable .= '</tr>';
    }
} else {
    $textarea_width = floor(900/($existform->dimension_row+1));
    for ($j=0; $j<$existform->dimension_col+1; $j++) {
        $rubrictable .= '<tr>';
        for ($i=0; $i<$existform->dimension_row+1; $i++) {
            $rubrictable .= '<td valign="top">';
            // first row defining criteria name
            if ($j == 0) {
                if ($i != 0) {
                    $criname = '';
                    if (sizeof($rubric->rowspecs) > 0 && isset($rubric->rowspec_map[$i])) {
                        $criname = $rubric->rowspecs[$rubric->rowspec_map[$i]]['name'];
                        $enablecustompoint = $rubric->rowspecs[$rubric->rowspec_map[$i]]['custompoint'];
                    } else {
                        if (isset($_POST['rowname_'.$i])) $criname = $_POST['rowname_'.$i];
                        $enablecustompoint = isset($_POST['custompoint_'.$i]) && $_POST['custompoint_'.$i] == 1;
                    }
                    $rubrictable .= '<input type="text" class="rowname" name="rowname_'.$i.'" size="18" value="'.$criname.'" />';
                    $custompointchecked = $enablecustompoint ? ' checked="checked"':'';
                    $haverangedisplay = $enablecustompoint ? 'block':'none';
                    $rubrictable .= '<br />'.get_string('custompoint', 'assessment').
                                    '<input type="checkbox" id="custompoint_'.$i.'" name="custompoint_'.$i.'" value="1" onclick="enable_custom_point('.$i.')" '.$custompointchecked.' />'.
                                    '<div id="haverangediv_'.$i.'" style="display:'.$haverangedisplay.'">'.get_string('haverange', 'assessment').
                                    '<select id="haverange_'.$i.'" name="haverange_'.$i.'" onchange="row_have_range('.$i.')">'.
                                    '<option value="0">'.get_string('no').'</option>'.
                                    '<option value="1">'.get_string('yes').'</option>'.
                                    '</select></div>';
                }
            } else { // first column defining level name and weight
                if ($i == 0) {
                    $levelname = '';
                    $points = '';
                    $maxpoints = '';
                    if (sizeof($rubric->colspecs) > 0 && isset($rubric->colspec_map[$j])) {
                        $levelname = $rubric->colspecs[$rubric->colspec_map[$j]]['name'];
                        $points = $rubric->colspecs[$rubric->colspec_map[$j]]['points'];
                        $maxpoints = $rubric->colspecs[$rubric->colspec_map[$j]]['maxpoints'];
                        if ($maxpoints == 0) $maxpoints = '';
                    } else {
                        if (isset($_POST['colname_'.$j])) $levelname = $_POST['colname_'.$j];
                        if (isset($_POST['colweight_'.$j])) $points = $_POST['colweight_'.$j];
                        if (isset($_POST['colmaxweight_'.$j])) $maxpoints = $_POST['colmaxweight_'.$j];
                    }
                    if ($j == 1) $rubrictable .= get_string('mingrade', 'assessment');
                    if ($j == ($existform->dimension_col)) $rubrictable .= get_string('maxgrade', 'assessment');
                    $rubrictable .= '<br />';
                    $rubrictable .= '<input type="text" class="colname" name="colname_'.$j.'" size="18" value="'.$levelname.'" />';
                    $rubrictable .= '<br />';
                    $rubrictable .= '<input type="text" class="colweight" name="colweight_'.$j.'" maxlength="4" size="3" value="'.$points.'" />';
                    if ($haverange == 1) {
                       $rubrictable .= ' '.get_string('to', 'assessment').' <input type="text" class="colmaxweight" name="colmaxweight_'.$j.'" maxlength="4" size="3" value="'.$maxpoints.'" />';
                    }
                    $rubrictable .= ' '.get_string('pts', 'assessment');
                } else {
                    $specid = '';
                    $description = '';
                    $points = '';
                    $maxpoints = '';
                    if (sizeof($rubric->specs) > 0 && isset($rubric->rowspec_map[$i]) && isset($rubric->colspec_map[$j])) {
                        $specid = $rubric->spec_map[$rubric->rowspec_map[$i]][$rubric->colspec_map[$j]];
                        $description = $rubric->specs[$specid]['description'];
                        
                        $enablecustompoint = $rubric->rowspecs[$rubric->rowspec_map[$i]]['custompoint'];
                        if ($enablecustompoint) {
                           $points = $rubric->specs[$specid]['points'];
                           $maxpoints = $rubric->specs[$specid]['maxpoints'];
                           $enablemaxpoints = $maxpoints ? 1 : 0;
                        } else {
                           $enablemaxpoints = 0;
                        }
                    } else {
                        $enablecustompoint = isset($_POST['custompoint_'.$i]) && $_POST['custompoint_'.$i] == 1;
                        $enablemaxpoints = $enablecustompoint && isset($_POST['haverange_'.$i]) && $_POST['haverange_'.$i] == 1;
                        if (isset($_POST['rbdescription_'.$i.'_'.$j]))
                           $description = $_POST['rbdescription_'.$i.'_'.$j];
                    }
                    
                    $custompointdisplay = $enablecustompoint ? '':'none';
                    $custompointdisable = $enablecustompoint ? '':' disabled="disabled"';
                    $maxpointsdisplay = $enablemaxpoints ? '':'none';
                    $maxpointsdisable = $enablemaxpoints ? '':' disabled="disabled"';
                    $rubrictable .= '<div id="pointsdiv_'.$i.'_'.$j.'" style="display:'.$custompointdisplay.'">
                                     <input type="text" class="colweight" id="points_'.$i.'_'.$j.'" name="points_'.$i.'_'.$j.'" maxlength="4" size="3" value="'.$points.'"'.$custompointdisable.' />'.
                                     '<span id="maxpointsspan_'.$i.'_'.$j.'" style="display:'.$maxpointsdisplay.'"> '.
                                     get_string('to', 'assessment').
                                     ' <input type="text" class="colmaxweight" id="maxpoints_'.$i.'_'.$j.'" name="maxpoints_'.$i.'_'.$j.'" maxlength="4" size="3" value="'.$maxpoints.'"'.$maxpointsdisable.' /></span>'.
                                     ' '.get_string('pts', 'assessment').'</div>'.
                                     '<textarea class="celldesc" style="width:'.$textarea_width.'px" name="rbdescription_'.$i.'_'.$j.'">'.$description.'</textarea>';
                }
            }
            $rubrictable .= '</td>';
        }
        $rubrictable .= '</tr>';
    }
}
$rubrictable .= '</table>';
$rubrictable .= '<p>'.get_string('rubricformattips', 'assessment').'</p></div>';
$mform->addElement('html', '<br />'.$rubrictable);

$mform->closeHeaderBefore('buttonar');

$buttonarray = array();
$buttonarray[] =& $mform->createElement('submit', 'submitbutton', get_string('save', 'assessment'));
$buttonarray[] =& $mform->createElement('cancel');
$mform->addGroup($buttonarray, 'buttonar', '', array(''), false);

$error = new stdClass;
$error->error = false;

if ($reform->is_cancelled()) {
    close_window();
} else if ($fromform = $reform->get_data()){
    $totalpoints = 0;
    
    $error = $rubric->validate($_POST);
    
    if(!$error->error){
       $rubric->name = $fromform->name;
       $rubric->itemid = $fromform->description['itemid'];
       $rubric->description = $fromform->description['text'];
       $rubric->rowcoldefine = $fromform->rowcoldefine;
       $rubric->points = $totalpoints;
       
       if(!$rubric->commit()) {
           echo '<br />';
           print_error('errorcreaterubric', 'assessment', $CFG->wwwroot.$return);
       }
       
       $rubric->description = file_save_draft_area_files($rubric->itemid, $coursecontext->id, 'mod_assessment', 'rubric_description', $rubric->id, array('subdirs'=>0), $rubric->description);
       
       // insert criteria name and order
       $rubric->rowspecs = array();
       for($i=1; $i<$dimension_row+1; $i++) {
           $rowspec = new object;
           $rowspec->id = $i-1;
           $rowspec->rubricid = $rubric->id;
           $rowspec->displayorder = $i;
           $rowspec->name = $_POST['rowname_'.$i];
           $rowspec->custompoint = isset($_POST['custompoint_'.$i]) ? $_POST['custompoint_'.$i] : 0;
           $rubric->add_rowspec($rowspec);
       }
       
       // insert level name, order and weight
       $rubric->colspecs = array();
       for($j=1; $j<$dimension_col+1; $j++) {
           $colspec = new object;
           $colspec->id = $j-1;
           $colspec->rubricid = $rubric->id;
           $colspec->displayorder = $j;
           $colspec->name = $_POST['colname_'.$j];
           $colspec->points = $_POST['colweight_'.$j];
           if (isset($_POST['colmaxweight_'.$j]))
              $colspec->maxpoints = $_POST['colmaxweight_'.$j];
           else
              $colspec->maxpoints = 0;
           $rubric->add_colspec($colspec);
       }
       
       if(!$rubric->commit()){
           echo '<br />';
           print_error('errorcreaterubric', 'assessment', $CFG->wwwroot.$return);
       }
       
       $rubric->allspecid = implode(',', array_keys($rubric->specs));
       $rubric->specs = array();
       for($i=1; $i<$dimension_row+1; $i++) {
           for($j=1; $j<$dimension_col+1; $j++) {
               $spec = new object;
               $spec->id = ($i-1)*$dimension_col+($j-1);
               $spec->rubricrowid = $rubric->rowspec_map[$i];
               $spec->rubriccolid = $rubric->colspec_map[$j];
               $spec->description = $_POST['rbdescription_'.$i.'_'.$j];
               $spec->points = isset($_POST['points_'.$i.'_'.$j]) ? $_POST['points_'.$i.'_'.$j] : '0';
               $spec->maxpoints = isset($_POST['maxpoints_'.$i.'_'.$j]) ? $_POST['maxpoints_'.$i.'_'.$j] : '0';
               $rubric->add_spec($spec);
           }
       }
       
       // recalculate total points
       $rubric->computePoints();
       $rubric->_orig_points = 0;
       
       if($rubric->commit()){
           if($updatewnd){
               print "Success.<br />";
               if ($rubricid != 0)
                  $rubric->update_form_rubric('update');
               else
                  $rubric->update_form_rubric('create');
               close_window();
               die;
           }
           redirect($CFG->wwwroot.$return);
       }else{
           echo '<br />';
           print_error('errorcreaterubric', 'assessment', $CFG->wwwroot.$return);
       }
    }
}

if(!$copyrubric)
    $rubric->name = $rubric->deSlash($rubric->name);

if(!$updatewnd){
    $PAGE->set_title($strrubrics);
}

echo $OUTPUT->header();

echo "<br />";
if($error->error) $rubric->formerr($error->fatal, $error->message, $return);

$form_name = 'modrubric';

print '<script type="text/javascript">
       function row_have_range(rownum) {
         col = '.$existform->dimension_col.';
         have_range = document.getElementById("haverange_"+rownum).value;
         if (have_range == 1) {
           for (var i=1; i<col+1; i++) {
              document.getElementById("maxpoints_"+rownum+"_"+i).disabled = false;
              document.getElementById("maxpointsspan_"+rownum+"_"+i).style.display = "";
           }
         } else {
           for (var i=1; i<col+1; i++) {
              document.getElementById("maxpoints_"+rownum+"_"+i).disabled = true;
              document.getElementById("maxpointsspan_"+rownum+"_"+i).style.display = "none";
           }
         }
       }
       
       function enable_custom_point(rownum) {
          col = '.$existform->dimension_col.';
          is_custom = document.getElementById("custompoint_"+rownum).checked;
          if (is_custom) {
             document.getElementById("haverangediv_"+rownum).style.display = "block";
             document.getElementById("haverange_"+rownum).disabled = false;
             for (var i=1; i<col+1; i++) {
                document.getElementById("points_"+rownum+"_"+i).disabled = false;
                document.getElementById("pointsdiv_"+rownum+"_"+i).style.display = "block";
             }
          } else {
             document.getElementById("haverangediv_"+rownum).style.display = "none";
             document.getElementById("haverange_"+rownum).disabled = true;
             for (var i=1; i<col+1; i++) {
                document.getElementById("points_"+rownum+"_"+i).disabled = true;
                document.getElementById("pointsdiv_"+rownum+"_"+i).style.display = "none";
             }
          }
       }
       </script>';
$reform->display();

if(!$updatewnd) $rubric->view_footer();

echo $OUTPUT->footer();
?>