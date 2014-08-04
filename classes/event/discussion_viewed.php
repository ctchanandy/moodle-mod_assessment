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
 * The mod_assessment discussion viewed event.
 *
 * @package    mod_assessment
 * @copyright  2014 Andy Chan
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace mod_assessment\event;

defined('MOODLE_INTERNAL') || die();

/**
 * The mod_assessment discussion viewed event class.
 *
 * @package    mod_assessment
 * @since      Moodle 2.7
 * @copyright  2014 Andy Chan
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class discussion_viewed extends \core\event\base {

    /**
     * Returns description of what happened.
     *
     * @return string
     */
    public function get_description() {
        return "The user with id '$this->userid' viewed the discussion with id '$this->objectid' in the assessment activity
            with the course module id '$this->contextinstanceid'.";
    }

    /**
     * Return legacy data for add_to_log().
     *
     * @return array
     */
    protected function get_legacy_logdata() {
        $param = '';
        if ($this->other['parent']) {
            $param .= '&parent='.$this->other['parent'];
        }
        $legacylogdata = array($this->courseid,
            'assessment',
            'view discussion',
            'discuss.php?d='.$this->objectid.$param,
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
        return get_string('eventdiscussionviewed', 'mod_assessment');
    }

    /**
     * Get URL related to the action
     *
     * @return \moodle_url
     */
    public function get_url() {
        $param = array('d'=>$this->objectid);
        if ($this->other['parent']) {
            $param['parent'] = $this->other['parent'];
        }
        return new \moodle_url('/mod/assessment/discuss.php', $param);
    }

    /**
     * Init method.
     *
     * @return void
     */
    protected function init() {
        $this->data['crud'] = 'r';
        $this->data['edulevel'] = self::LEVEL_PARTICIPATING;
        $this->data['objecttable'] = 'assessment_discussions';
    }
}
