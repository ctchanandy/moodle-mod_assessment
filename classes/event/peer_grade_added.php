<?php
// This file is part of Moodle - http://moodle.org/
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
 * The mod_assessment peer grade added event.
 *
 * @package    mod_assessment
 * @copyright  2014 Andy Chan
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_assessment\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_assessment peer grade added event class.
 *
 * @package    mod_assessment
 * @since      Moodle 2.7
 * @copyright  2014 Andy Chan
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class peer_grade_added extends \core\event\base {

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        if ($this->other['userid']) {
            $grade_target = 'userid='.$this->other['userid'];
        } else if ($this->other['groupid']) {
            $grade_target = 'groupid='.$this->other['groupid'];
        }
        return "The user with id '$this->userid' added the peer grade for student ($grade_target) in the assessment activity
            with the course module id '$this->contextinstanceid'.";
    }

    /**
     * Return legacy data for add_to_log().
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        if ($this->other['userid']) {
            $param = '&userid='.$this->other['userid'];
        } else if ($this->other['groupid']) {
            $param = '&groupid='.$this->other['groupid'];
        }
        if ($this->other['markergroupid']) $param .= '&markergroupid='.$this->other['markergroupid'];
        if ($this->other['type']) $param .= '&type='.$this->other['type'];
        $legacylogdata = array($this->courseid,
            'assessment',
            'add grades (peer)',
            'assessment_grades.php?mode=single&id='.$this->objectid.$param,
            $this->objectid,
            $this->contextinstanceid);

        return $legacylogdata;
    }

    /**
     * Return localised event name.
     *
     * @return string
     */
    public static function get_name() {
        return get_string('eventpeergradeadded', 'mod_assessment');
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        $param = array('id'=>$this->objectid, 'mode'=>'single');
        if ($this->other['userid']) {
            $param['userid'] = $this->other['userid'];
        } else if ($this->other['groupid']) {
            $param['groupid'] = $this->other['groupid'];
        }
        if ($this->other['markergroupid']) $param['markergroupid'] = $this->other['markergroupid'];
        if ($this->other['type']) $param['type'] = $this->other['type'];
        return new \moodle_url('/mod/assessment/assessment_grades.php', $param);
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'c';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'assessment_grades';
    }
}
