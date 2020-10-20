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

/**
 * Define all the backup steps that will be used by the backup_assessment_activity_task
 */
 class backup_assessment_activity_structure_step extends backup_activity_structure_step {
 
    protected function define_structure() {
 
        // To know if we are including userinfo
        $userinfo = $this->get_setting_value('userinfo');
 
        // Define each element separated
        $assessment = new backup_nested_element('assessment', array('id'), array(
                          'grade', 'rubricid', 'name', 'intro', 'introformat', 'forum', 'numfiles', 'workmode', 
                          'submitstart', 'submitend', 'timemodified'));
        
        $rubrics = new backup_nested_element('rubrics');
        
        $rubric = new backup_nested_element('rubric', array('id'), array(
                                 'name', 'description', 'creatorid', 'courseid', 'points', 'rowcoldefine', 'timemodified'));
        
        $rubric_row_specs = new backup_nested_element('rubric_row_specs');
        
        $rubric_row_spec = new backup_nested_element('rubric_row_spec', array('id'), array(
                                          'displayorder', 'name', 'custompoint'));
        
        $rubric_col_specs = new backup_nested_element('rubric_col_specs');
        
        $rubric_col_spec = new backup_nested_element('rubric_col_spec', array('id'), array(
                                          'displayorder', 'name', 'points', 'maxpoints'));
        
        $rubric_specs = new backup_nested_element('rubric_specs');
        
        $rubric_spec = new backup_nested_element('rubric_spec', array('id'), array(
                                      'rubricrowid', 'rubriccolid', 'description', 'points', 'maxpoints'));
        
        $types = new backup_nested_element('types');
        
        $type = new backup_nested_element('type', array('id'), array(
                               'type', 'timestart', 'timeend', 'timepublish', 'weight', 'peernum', 'peergroupmode'));
        
        $submissions = new backup_nested_element('submissions');
        
        $submission = new backup_nested_element('submission', array('id'), array(
                                     'userid', 'groupid', 'title', 'description', 'timecreated', 'late', 'url'));
        
        $grades = new backup_nested_element('grades');
        
        $grade = new backup_nested_element('grade', array('id'), array(
                                'userid', 'groupid', 'marker', 'grade', 'type', 'comment', 'timemodified'));
        
        
        $grade_specs = new backup_nested_element('grade_specs');
        
        $grade_spec = new backup_nested_element('grade_spec', array('id'), array(
                                     'rubricspecid', 'value', 'description'));
        
        $discussions = new backup_nested_element('discussions');
        
        $discussion = new backup_nested_element('discussion', array('id'), array(
                                     'name', 'firstpost', 'userid', 'groupid', 'timemodified'));
        
        $posts = new backup_nested_element('posts');
        
        $post = new backup_nested_element('post', array('id'), array(
                               'parent', 'userid', 'timecreated', 'timemodified', 'subject', 'message', 'format'));
        
        $reads = new backup_nested_element('reads');
        
        $read = new backup_nested_element('read', array('id'), array( 
                               'userid', 'assessmentid', 'discussionid', 'firstread', 'lastread'));
        
        // Build the tree
        $assessment->add_child($rubrics);
        $rubrics->add_child($rubric);
        
        $rubric->add_child($rubric_row_specs);
        $rubric_row_specs->add_child($rubric_row_spec);
        
        $rubric->add_child($rubric_col_specs);
        $rubric_col_specs->add_child($rubric_col_spec);
        
        $rubric->add_child($rubric_specs);
        $rubric_specs->add_child($rubric_spec);
        
        $assessment->add_child($types);
        $types->add_child($type);
        
        $type->add_child($grades);
        $grades->add_child($grade);
        
        $grade->add_child($grade_specs);
        $grade_specs->add_child($grade_spec);
        
        $assessment->add_child($submissions);
        $submissions->add_child($submission);
        
        $assessment->add_child($discussions);
        $discussions->add_child($discussion);
        
        $discussion->add_child($posts);
        $posts->add_child($post);
        
        $assessment->add_child($reads);
        $reads->add_child($read);
        
        // Define sources
        $assessment->set_source_table('assessment', array('id' => backup::VAR_ACTIVITYID));
        
        $rubric->set_source_table('assessment_rubrics', array('id' => '../../rubricid'));
        
        $rubric_row_spec->set_source_table('assessment_rubric_row_specs', array('rubricid' => backup::VAR_PARENTID));
        
        $rubric_col_spec->set_source_table('assessment_rubric_col_specs', array('rubricid' => backup::VAR_PARENTID));
        
        $rubric_spec->set_source_sql('
            SELECT * FROM {assessment_rubric_specs} WHERE 
                rubricrowid IN (SELECT id FROM {assessment_rubric_row_specs} WHERE rubricid = ?) AND
                rubriccolid IN (SELECT id FROM {assessment_rubric_col_specs} WHERE rubricid = ?)', 
            array(backup::VAR_PARENTID, backup::VAR_PARENTID));
        
        $type->set_source_table('assessment_types', array('assessmentid' => backup::VAR_PARENTID));
        
        // All the rest of elements only happen if we are including user info
        if ($userinfo) {
            $submission->set_source_table('assessment_submissions', array('assessmentid' => backup::VAR_PARENTID));
            
            $grade->set_source_table('assessment_grades', array('assessmentid' => backup::VAR_PARENTID));
            
            $grade_spec->set_source_table('assessment_grade_specs', array('gradeid' => backup::VAR_PARENTID));
            
            $discussion->set_source_table('assessment_discussions', array('assessmentid' => backup::VAR_PARENTID));
        
            $post->set_source_table('assessment_posts', array('discussionid' => backup::VAR_PARENTID));
        
            $read->set_source_table('assessment_read', array('postid' => backup::VAR_PARENTID));
        }
        
        // Define id annotations
        $submission->annotate_ids('user', 'userid');
        $submission->annotate_ids('group', 'groupid');
        
        $grade->annotate_ids('user', 'userid');
        $grade->annotate_ids('user', 'marker');
        $grade->annotate_ids('group', 'groupid');
        
        $rubric->annotate_ids('user', 'creatorid');
        
        $discussion->annotate_ids('user', 'userid');
        $discussion->annotate_ids('group', 'groupid');
        
        $post->annotate_ids('user', 'userid');
        
        $read->annotate_ids('user', 'userid');
        
        // Define file annotations
        $assessment->annotate_files('mod_assessment', 'intro', null); // This file area hasn't itemid
        
        $submission->annotate_files('mod_assessment', 'submission_description', 'id');
        $submission->annotate_files('mod_assessment', 'submission', 'id');
        
        $grade->annotate_files('mod_assessment', 'grade_comment', 'id');
        
        $rubric->annotate_files('mod_assessment', 'rubric_description', 'id');
        
        $post->annotate_files('mod_assessment', 'message', 'id');
        
        // Return the root element (assessment), wrapped into standard activity structure
        return $this->prepare_activity_structure($assessment);
    }
}