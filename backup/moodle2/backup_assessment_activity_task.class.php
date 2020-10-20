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
 * @package mod
 * @subpackage assessment
 * @author Andy Chan <ctchan.andy@gmail.com>
 * @copyright 2012 Andy Chan <ctchan.andy@gmail.com>
 * @license   http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

require_once($CFG->dirroot . '/mod/assessment/backup/moodle2/backup_assessment_stepslib.php'); // Because it exists (must)
require_once($CFG->dirroot . '/mod/assessment/backup/moodle2/backup_assessment_settingslib.php'); // Because it exists (optional)
 
/**
 * assessment backup task that provides all the settings and steps to perform one
 * complete backup of the activity
 */
class backup_assessment_activity_task extends backup_activity_task {
 
    /**
     * Define (add) particular settings this activity can have
     */
    protected function define_my_settings() {
        // No particular settings for this activity
    }
 
    /**
     * Define (add) particular steps this activity can have
     */
    protected function define_my_steps() {
        // Assessment only has one structure step
        $this->add_step(new backup_assessment_activity_structure_step('assessment_structure', 'assessment.xml'));
    }
 
    /**
     * Code the transformations to perform in the activity in
     * order to get transportable (encoded) links
     */
    static public function encode_content_links($content) {
        global $CFG;
        
        $base = preg_quote($CFG->wwwroot, "/");
        
        // Link to the list of assessments
        $search="/(".$base."\/mod\/assessment\/index.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@ASSESSMENTINDEX*$2@$', $content);
 
        // Link to assessment view by moduleid
        $search="/(".$base."\/mod\/assessment\/view.php\?id\=)([0-9]+)/";
        $content= preg_replace($search, '$@ASSESSMENTVIEWBYID*$2@$', $content);
        /*
        // Link to submission
        $search="/(".$base."\/mod\/assessment\/view_submission.php\?id\=)([0-9]+)\&a\=([0-9]+)\&groupid\=([0-9]+)/";
        $content= preg_replace($search, '$@ASSESSMENTVIEWSUBMISSION*$2@*$3*$4$', $content);
        
        // Link to grade
        $search="/(".$base."\/mod\/assessment\/assessment_grade.php\?id\=)([0-9]+)\&userid\=([0-9]+)\&groupid\=([0-9]+)\&mode\=([A-Z]+)\&offset\=([0-9]+)\&markergroupud\=([0-9]+)\&type\=([0-9]+)/";
        $content= preg_replace($search, '$@ASSESSMENTVIEWSUBMISSION*$2@*$3*$4$', $content);
        */
        return $content;
    }
}