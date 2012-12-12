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
 * Edit form of rubric
 *
 * @package     mod
 * @subpackage  assessment
 * @author      Andy Chan, CITE, HKU <ctchan.andy@gmail.com>
 * @copyright   2012 Andy Chan <ctchan.andy@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once ($CFG->dirroot.'/course/moodleform_mod.php');

class rubric_edit_form extends moodleform {

    function definition() {
        global $CFG, $COURSE;
        $mform =& $this->_form;

        $rubric = new stdClass();
        
        $mform->addElement('hidden', 'course', '');
        $mform->setType('course', PARAM_INT);
        
        $mform->addElement('hidden', 'rubric', '');
        $mform->setType('rubric', PARAM_INT);
        
        $mform->addElement('hidden', 'updatewnd', '');
        $mform->setType('updatewnd', PARAM_INT);
        
        $mform->addElement('hidden', 'submitmode', '');
        $mform->setType('submitmode', PARAM_TEXT);
        $mform->setDefault('submitmode', 'save');
                
    //-------------------------------------------------------------------------------
        $mform->addElement('header', 'general', get_string('general', 'form'));
        
        $mform->addElement('text', 'name', get_string('rubrictitle', 'assessment'), array('size'=>'64'));
        if (!empty($CFG->formatstringstriptags)) {
            $mform->setType('name', PARAM_TEXT);
        } else {
            $mform->setType('name', PARAM_CLEAN);
        }
        $mform->addRule('name', null, 'required', null, 'client');
        
        $editoroptions = array('maxfiles' => EDITOR_UNLIMITED_FILES, 'trusttext'=>true);
        $mform->addElement('editor', 'description', get_string('description'), null, $editoroptions);
        $mform->setType('description', PARAM_RAW);
        
        $DIMENSION_NUM = array(1=>1, 2=>2, 3=>3, 4=>4, 5=>5, 6=>6, 7=>7, 8=>8, 9=>9, 10=>10);
        $attributes = array('onchange'=>'this.form.submitmode.value="dimension";this.form.submit()');
        $mform->addElement('select', 'dimension_row', get_string('noofrows', 'assessment'), $DIMENSION_NUM, $attributes);
        $mform->setDefault('dimension_row', 4);
        $mform->addElement('select', 'dimension_col', get_string('noofcols', 'assessment'), $DIMENSION_NUM, $attributes);
        $mform->setDefault('dimension_col', 4);
        
        $rowcol = array(1=>get_string('rowcol1', 'assessment'), 2=>get_string('rowcol2', 'assessment'));
        $attributes = array('onchange'=>'this.form.submitmode.value="rowcol";this.form.submit()');
        $mform->addElement('select', 'rowcoldefine', get_string('rowcoldefine', 'assessment'), $rowcol, $attributes);
        $mform->setDefault('rowcoldefine', 1);
        
        $attributes = array('onchange'=>'this.form.submitmode.value="haverange";this.form.submit()');
        $mform->addElement('selectyesno', 'haverange', get_string('haverange', 'assessment'), $attributes);
        $mform->setDefault('haverange', 0);
        
    //-------------------------------------------------------------------------------
    
    }
    
    function getform() {
        $mform =& $this->_form;
        return $mform;
    }
    
    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        return $errors;
    }
}
?>