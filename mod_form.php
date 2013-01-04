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
 * assessment instance add/edit form
 *
 * @package     mod
 * @subpackage  assessment
 * @author      Andy Chan, CITE, HKU <ctchan.andy@gmail.com>
 * @copyright   2012 Andy Chan <ctchan.andy@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die;

require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class mod_assessment_mod_form extends moodleform_mod {
    
	function definition() {
		global $CFG, $COURSE, $DB, $PAGE;
        
        $mform = $this->_form;
        
        $assessment = new stdClass();
        if (!empty($this->_instance)) {
            if(!$assessment = $DB->get_record('assessment', array('id'=>(int)$this->_instance))) {
                print_error('invalidid', 'assessment');
            }
        }
        
//-------------------------------------------------------------------------------
        /// Adding the "general" fieldset, where all the common settings are showed
        $mform->addElement('header', 'general', get_string('general', 'form'));
        
        /// Adding the standard "name" field
        $mform->addElement('text', 'name', get_string('assessmentname', 'assessment'), array('size'=>'64'));
		if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEANHTML);
        }
		$mform->addRule('name', null, 'required', null, 'client');
        
        $this->add_intro_editor(true, get_string('description', 'assignment'));
        
        // Build-in file submission detail
        $submissionfilesoptions = array();
        for ($i=1; $i<=20; $i++) {
            $submissionfilesoptions[$i] = $i;
        }
        
        $mform->addElement('select', 'numfiles', get_string('submissionfilesnum', 'assessment'), $submissionfilesoptions);
        
        $mform->addElement('date_time_selector', 'submitstart', get_string('submitstart', 'assessment'), array('optional'=>true));
        $mform->addElement('date_time_selector', 'submitend', get_string('submitend', 'assessment'), array('optional'=>true));
        $mform->disabledIf('submitstart', 'submissionfilesnumenabled');
        $mform->disabledIf('submitend', 'submissionfilesnumenabled');
        
        $groups = groups_get_all_groups($COURSE->id);
        if (!empty($groups)) {
            $workmodeoptions = array(0 => get_string('individualwork', 'assessment'),
                                    1 => get_string('groupwork', 'assessment'));
            $mform->addElement('select', 'workmode', get_string('workmode', 'assessment'), $workmodeoptions);
        }
        
        $mform->addElement('selectyesno', 'forum', get_string('forum', 'assessment'));
        $mform->setDefault('forum', 0);
        
        // Construct the rubric dropdown list
        $rubricoptions = array();
        $rubricoptions[0] = get_string('singlegrade', 'assessment');
        $rubricoptions[1] = '-----------------------------------------';
        
        $rubricselect_onchange = 'updateElem(this.options[this.selectedIndex].value, '.$COURSE->id.', \''.$CFG->wwwroot.'\', \''.sesskey().'\')';
        $rubricselect = $mform->addElement('select', 'rubricid', get_string('loadrubric', 'assessment'), array(), array('onchange' => $rubricselect_onchange));
        $rubricselect->addOption(get_string('singlegrade', 'assessment'), '0');
        $rubricselect->addOption('-----------------------------------------', 'line1', array('disabled' => 'disabled'));
        if(!$rubrics = rubric_get_list($COURSE->id)){
            $rubricselect->addOption(get_string('norubrics', 'assessment'), '0', array('disabled' => 'disabled'));
        } else {
            foreach ($rubrics as $rub_key => $rubric) {
                if(!is_object($rubric)) break; // TOP_COURSE produces this
                $rubricselect->addOption($rubric->text, $rubric->value);
            }
        }
        $rubricselect->addOption('-----------------------------------------', 'line2', array('disabled' => 'disabled'));
        $rubricselect->addOption(get_string('viewrubriclist', 'assessment'), 'import');
        $rubricselect->addOption(get_string('createnewrubric', 'assessment'), 'new');
        
        $this->standard_grading_coursemodule_elements();
        
        // load supporting javascript
        $PAGE->requires->js('/mod/assessment/mod_form-script.js');
        
        /// Adding the rest of assessment settings, spreading all them into this fieldset
        /// or adding more fieldsets ('header' elements) if needed for better logic
    
        /// Setting for teacher assessment
        $mform->addElement('header', 'teacherfieldset', get_string('teacherassessment', 'assessment'));
        $mform->addElement('selectyesno', 'teacher', get_string("enable"));
        $mform->setDefault('teacher', 1);

        $mform->addElement('text', 'teacherweight', get_string('weight', 'assessment'), array('size'=>'20'));
        $mform->setType('teacherweight', PARAM_TEXT);
        $mform->addRule('teacherweight', null, 'numeric', null, 'client');

        $mform->addElement('date_time_selector', 'teacherstart', get_string('start', 'assessment'), array('optional'=>true));
        $mform->addElement('date_time_selector', 'teacherend', get_string('end', 'assessment'), array('optional'=>true));
        $mform->addElement('date_time_selector', 'teacherpublish', get_string('publish', 'assessment'));

        $mform->disabledIf('teacherweight', 'teacher', 'eq', '0');
        $mform->disabledIf('teacherstart', 'teacher', 'eq', '0');
        $mform->disabledIf('teacherend', 'teacher', 'eq', '0');
        $mform->disabledIf('teacherpublish', 'teacher', 'eq', '0');
		
        /// Setting for self assessment
        $mform->addElement('header', 'selffieldset', get_string('selfassessment', 'assessment'));
        $mform->addElement('selectyesno', 'self', get_string("enable"));
        $mform->setDefault('self', 0);

        $mform->addElement('text', 'selfweight', get_string('weight', 'assessment'), array('size'=>'20'));
        $mform->setType('selfweight', PARAM_TEXT);
        $mform->addRule('selfweight', null, 'numeric', null, 'client');

        $mform->addElement('date_time_selector', 'selfstart', get_string('start', 'assessment'), array('optional'=>true));
        $mform->addElement('date_time_selector', 'selfend', get_string('end', 'assessment'), array('optional'=>true));
        $mform->addElement('date_time_selector', 'selfpublish', get_string('publish', 'assessment'));

        $mform->disabledIf('selfweight', 'self', 'eq', '0');
        $mform->disabledIf('selfstart', 'self', 'eq', '0');
        $mform->disabledIf('selfend', 'self', 'eq', '0');
        $mform->disabledIf('selfpublish', 'self', 'eq', '0');
		
        /// Setting for peer assessment
        $mform->addElement('header', 'peerfieldset', get_string('peerassessment', 'assessment'));
        $mform->addElement('selectyesno', 'peer', get_string("enable"));
        $mform->setDefault('peer', 0);

        $mform->addElement('text', 'peerweight', get_string('weight', 'assessment'), array('size'=>'20'));
        $mform->setType('peerweight', PARAM_TEXT);
        $mform->addRule('peerweight', null, 'numeric', null, 'client');
		
		$peernumoptions = array();
        for ($i=1; $i<=30; $i++) {
            $peernumoptions[$i] = $i;
        }
        $mform->addElement('select', 'peernum', get_string('noofpeertoassess', 'assessment'), $peernumoptions);
        
        if (!empty($groups)) {
            $peergroupmodeoptions = array(0 => get_string('studenttostudent', 'assessment'),
                                        1 => get_string('studenttogroup', 'assessment'),
                                        2 => get_string('grouptogroup', 'assessment'));
         $mform->addElement('select', 'peergroupmode', get_string('peergroupmode', 'assessment'), $peergroupmodeoptions);
        } else {
            $mform->addElement('hidden', 'peergroupmode', 0);
        }
        
        $mform->disabledIf('peerweight', 'peer', 'eq', '0');
        $mform->disabledIf('peerstart', 'peer', 'eq', '0');
        $mform->disabledIf('peerend', 'peer', 'eq', '0');
        $mform->disabledIf('peerpublish', 'peer', 'eq', '0');
        $mform->disabledIf('peernum', 'peer', 'eq', '0');
        $mform->disabledIf('peergroupmode', 'peer', 'eq', '0');
        
        $mform->addElement('date_time_selector', 'peerstart', get_string('start', 'assessment'), array('optional'=>true));
        $mform->addElement('date_time_selector', 'peerend', get_string('end', 'assessment'), array('optional'=>true));
        $mform->addElement('date_time_selector', 'peerpublish', get_string('publish', 'assessment'));
        
//-------------------------------------------------------------------------------
        // add standard elements, common to all modules
        $this->standard_coursemodule_elements();
//-------------------------------------------------------------------------------
        // add standard buttons, common to all modules
        $this->add_action_buttons();
	}
    
	function data_preprocessing(&$default_values){
        global $COURSE, $DB;
        $mform =& $this->_form;
      
	    if ($teacherassessment = $DB->get_record('assessment_types', array('assessmentid'=>$default_values['id'], 'type'=>0))) {
            $mform->addElement('hidden', 'teacherassessment', $teacherassessment->id);
            
            $default_values['teacherweight'] = rtrim($teacherassessment->weight, '0');
            if ($teacherassessment->timestart != 0)
                $default_values['teacherstart'] = $teacherassessment->timestart;
            if ($teacherassessment->timeend != 0)
                $default_values['teacherend'] = $teacherassessment->timeend;
            if ($teacherassessment->timepublish != 0)
                $default_values['teacherpublish'] = $teacherassessment->timepublish;
            $default_values['teacher'] = 1;
	    } else {
            $default_values['teacher'] = 0;
        }
	   
	    if ($selfassessment = $DB->get_record('assessment_types', array('assessmentid'=>$default_values['id'], 'type'=>1))) {
	        $mform->addElement('hidden', 'selfassessment', $selfassessment->id);
            
            $default_values['selfweight'] = rtrim($selfassessment->weight, '0');
            if ($selfassessment->timestart != 0)
                $default_values['selfstart'] = $selfassessment->timestart;
            if ($selfassessment->timeend != 0)
                $default_values['selfend'] = $selfassessment->timeend;
            if ($selfassessment->timepublish != 0)
                $default_values['selfpublish'] = $selfassessment->timepublish;
            $default_values['self'] = 1;
	    } else {
            $default_values['self'] = 0;
        }
	   
	    if ($peerassessment = $DB->get_record('assessment_types', array('assessmentid'=>$default_values['id'], 'type'=>2))) {
            $mform->addElement('hidden', 'peerassessment', $peerassessment->id);
            
            $default_values['peerweight'] = rtrim($peerassessment->weight, '0');
            if ($peerassessment->timestart != 0)
                $default_values['peerstart'] = $peerassessment->timestart;
            if ($peerassessment->timeend != 0)
                $default_values['peerend'] = $peerassessment->timeend;
            if ($peerassessment->timepublish != 0)
                $default_values['peerpublish'] = $peerassessment->timepublish;
            $default_values['peernum'] = $peerassessment->peernum;
            $default_values['peergroupmode'] = $peerassessment->peergroupmode;
            $default_values['peer'] = 1;
	    } else {
            $default_values['peer'] = 0;
        }
      
        if (isset($default_values['forum']) && $default_values['forum'] != 0) {
            $mform->addElement('hidden', 'forumid', $default_values['forum']);
                $default_values['forum'] = 1;
        }
	}
   
    /**
     * Validate the group setting (individual/group work, peer assessment mode)
     *
     * @param int    $group_mode        group mode in general setting
     * @param int    $work_mode         student work mode: individual or group
     * @param int    $peer_assess_mode  peer assessment mode: assess peer or group
     */
    function checkGroupMode($modes) {
        $group_mode = $modes[0];
        $work_mode = $modes[1];
        $peer_assess_mode = $modes[2];
        if ($group_mode == 0) { // No group: only one combination of setting is allowed
            if ($work_mode == 1 || $peer_assess_mode != 0) {
                return false;
            }
        } else if ($group_mode == 1) { // Separate Group: work mode can be individual or group
            if ($peer_assess_mode != 0) {
                return false;
            }
        } else if ($group_mode == 2) { // Visible Group
            if ($work_mode == 0 && $peer_assess_mode != 0) {
                return false;
            }
            if ($work_mode == 1 && $peer_assess_mode == 0) {
                return false;
            }
        }
        return true;
    }
    
    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        
        $assessment_type = array('teacher', 'self', 'peer');
        
        if (!isset($data['numfiles']) && (!isset($data['modinstance']) || empty($data['modinstance']))) {
            $errors['modinstance']= get_string('modinstancenotselected', 'assessment');
        }
        
        if ($data['submitstart'] > $data['submitend']) {
            $errors['submitend'] = get_string('endearlythanstart', 'assessment');
        }
        
        $total_weight = 0;
        for ($i=0; $i<sizeof($assessment_type); $i++) {
            $type = $assessment_type[$i];
            if ($data[$type] != '0') {
                // Validate weights
                if (empty($data[$type.'weight'])) {
                    $errors[$type.'weight']= get_string('emptyweight', 'assessment');
                } else {
                    if (!is_numeric($data[$type.'weight']) || $data[$type.'weight'] < 0 || $data[$type.'weight'] > 1) {
                        $errors[$type.'weight']= get_string('invalidweight', 'assessment');
                    } else {
                        $total_weight += $data[$type.'weight'];
                    }
                }
                
                // Validate dates
                if (!empty($data[$type.'start']) && !empty($data[$type.'end'])) {
                    if ($data[$type.'start'] > $data[$type.'end']) {
                        $errors[$type.'end'] = get_string('endearlythanstart', 'assessment');
                    }
                    if (!empty($data[$type.'publish'])) {
                        if ($data[$type.'start'] > $data[$type.'publish']) {
                            $errors[$type.'publish'] = get_string('publishearlythanstart', 'assessment');
                        }
                    }
                }
            }
        }
        
        // Total weight invalid, display error messages on enabled assessment only
        if (trim($total_weight) != 1) {
            for ($i=0; $i<sizeof($assessment_type); $i++) {
                $type = $assessment_type[$i];
                if ($data[$type] != '0') {
                    $errors[$type.'weight']= get_string('invalidtotalweight', 'assessment');
                }
            }
        }
        
        // Group mode
        if (isset($data['groupmode']) && isset($data['peergroupmode']) && isset($data['workmode'])) {
            if ($data['groupmode'] == 0) { // No group: only one combination of setting is allowed
                if ($data['workmode'] == 1) {
                    $errors['workmode'] = get_string('nogroup_invalidworkmode', 'assessment');
                }
                if ($data['peergroupmode'] != 0) {
                    $errors['peergroupmode'] = get_string('nogroup_invalidpeergroupmode', 'assessment');
                }
            } else if ($data['groupmode'] == 1) { // Separate Group: work mode can be individual or group
                if ($data['peergroupmode'] != 0) {
                    $errors['peergroupmode'] = get_string('separategroup_invalidpeergroupmode', 'assessment');
                }
            } else if ($data['groupmode'] == 2) { // Visible Group
                if ($data['workmode'] == 0 && $data['peergroupmode'] != 0) {
                    $errors['peergroupmode'] = get_string('visiblegroup_invalidpeergroupmode1', 'assessment');
                }
                if ($data['workmode'] == 1 && $data['peergroupmode'] == 0) {
                    $errors['peergroupmode'] = get_string('visiblegroup_invalidpeergroupmode2', 'assessment');
                }
            }
        }
        
        return $errors;
    }
}
?>