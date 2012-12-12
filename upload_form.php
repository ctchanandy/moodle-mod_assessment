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
 * Submission form
 *
 * @package     mod
 * @subpackage  assessment
 * @author      Andy Chan, CITE, HKU <ctchan.andy@gmail.com>
 * @copyright   2012 Andy Chan <ctchan.andy@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

if (!defined('MOODLE_INTERNAL')) {
    die('Direct access to this script is forbidden.');    ///  It must be included from a Moodle page
}

require_once($CFG->libdir.'/formslib.php');
   
class mod_assessment_upload_form extends moodleform {
    function definition() {
        $mform =& $this->_form;
        
        $formattr = $mform->getAttributes();
        $formattr['uploadform'] = 'submitform';
        $mform->setAttributes($formattr);
        
        // hidden params
        $mform->addElement('hidden', 'id', $this->_customdata['cm']->id);
        $mform->setType('offset', PARAM_INT);
        $mform->addElement('hidden', 'userid');
        $mform->setType('userid', PARAM_INT);
        
        $mform->addElement('hidden', 'groupid');
        $mform->setType('groupid', PARAM_INT);
        $mform->setDefault('groupid', 0);
        
        $mform->addElement('text', 'title', get_string('title', 'assessment'), 'size="48"');
        $mform->setType('title', PARAM_TEXT);
        $mform->addRule('title', get_string('required'), 'required', null, 'client');
        $mform->addRule('title', get_string('maximumchars', '', 255), 'maxlength', 255, 'client');
        
        $editoroptions = array('maxfiles' => EDITOR_UNLIMITED_FILES, 'trusttext'=>true, 'context'=>$this->_customdata['context']);
        $mform->addElement('editor', 'description', get_string('description'), null, $editoroptions);
        $mform->setType('description', PARAM_RAW);
        
        if ($this->_customdata['assessment']->numfiles) {
            // Provide an URL field
            $mform->addElement('text', 'url', get_string('link', 'assessment'), array('maxlength'=>'255', 'size'=>'60'));
            // Moodle 2.0: file handling changes
            $this->_customdata['fileui_options']['accepted_types'] = '*';
            $this->_customdata['fileui_options']['return_types'] = FILE_INTERNAL;
            $mform->addElement('filemanager', 'submission', get_string('submission', 'assessment'), null, $this->_customdata['fileui_options']);
        }
        // buttons
        $this->add_action_buttons();
    }
    
    function validation($data, $files) {
        $errors = parent::validation($data, $files);
        
        return $errors;
    }
}

?>