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
 * assessment module upgrade
 *
 * @package     mod
 * @subpackage  assessment
 * @author      Andy Chan, CITE, HKU <ctchan.andy@gmail.com>
 * @copyright   2012 Andy Chan <ctchan.andy@gmail.com>
 * @license     http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

function xmldb_assessment_upgrade($oldversion) {
    global $CFG, $DB;

    $dbman = $DB->get_manager();
    
    //===== 1.9.0 upgrade line ======//

    if ($oldversion < 2012011200) {
    
        // Define index modinstance (not unique) to be dropped form assessment
        $table = new xmldb_table('assessment');
        $index = new xmldb_index('modinstance', XMLDB_INDEX_NOTUNIQUE, array('modinstance'));

        // Conditionally launch drop index modinstance
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }

        // Define field modinstance to be dropped from assessment
        $table = new xmldb_table('assessment');
        $field = new xmldb_field('modinstance');

        // Conditionally launch drop field modinstance
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        
        // assessment savepoint reached
        upgrade_mod_savepoint(true, 2012011200, 'assessment');
    }
    
    if ($oldversion < 2012032900) {
        // 1. Drop 4 unused fields from "assessment_peer"
        // 2. Add new fields "type" to store assessment type and "assessmentid" to associate with assessment
        // 3. Rename the table to "assessment_types"
        // 4. Set all current records in "assessment_peer" with "type" = 3 first 
        // 5. Move all records from "assessment_individual" to "assessment_types"
        // 6. Drop the table "assessment_individual"
        // 7. Drop "teacher", "self" and "peer" in the table "assessment" 
        
        // Define field anonymous to be dropped from assessment_peer
        $table = new xmldb_table('assessment_peer');
        $field = new xmldb_field('anonymous');

        // Conditionally launch drop field anonymous
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        
        // Define field visible to be dropped from assessment_peer
        $table = new xmldb_table('assessment_peer');
        $field = new xmldb_field('visible');

        // Conditionally launch drop field visible
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }

        // Define field poll to be dropped from assessment_peer
        $table = new xmldb_table('assessment_peer');
        $field = new xmldb_field('poll');

        // Conditionally launch drop field poll
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        
        // Define field havegroup to be dropped from assessment_peer
        $table = new xmldb_table('assessment_peer');
        $field = new xmldb_field('havegroup');

        // Conditionally launch drop field havegroup
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        
        // Define field assessmentid to be added to assessment_peer
        $table = new xmldb_table('assessment_peer');
        $field = new xmldb_field('assessmentid', XMLDB_TYPE_INTEGER, '10', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'id');

        // Conditionally launch add field assessmentid
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Define index assessmentid (not unique) to be added to assessment_peer
        $table = new xmldb_table('assessment_peer');
        $index = new xmldb_index('assessmentid', XMLDB_INDEX_NOTUNIQUE, array('assessmentid'));

        // Conditionally launch add index assessmentid
        if (!$dbman->index_exists($table, $index)) {
            $dbman->add_index($table, $index);
        }
        
         // Define field type to be added to assessment_peer
        $table = new xmldb_table('assessment_peer');
        $field = new xmldb_field('type', XMLDB_TYPE_INTEGER, '1', XMLDB_UNSIGNED, XMLDB_NOTNULL, null, '0', 'assessmentid');

        // Conditionally launch add field type
        if (!$dbman->field_exists($table, $field)) {
            $dbman->add_field($table, $field);
        }
        
        // Define table assessment_peer to be renamed to assessment_types
        $table = new xmldb_table('assessment_peer');

        // Launch rename table for assessment_peer
        $dbman->rename_table($table, 'assessment_types');
        
        // Set all current records in "assessment_peer" with "type" = 3 first
        $DB->set_field_select('assessment_types', 'type', 2, 'id > 0');
        
        // Move all records from "assessment_individual" to "assessment_types"
        if ($assessments = $DB->get_records('assessment')) {
            foreach ($assessments as $a) {
                if ($a->teacher) {
                    if ($ai = $DB->get_record('assessment_individual', array('id'=>$a->teacher))) {
                        $ai->peernum = 0;
                        $ai->peergroupmode = 0;
                        $ai->type = 0;
                        $ai->assessmentid = $a->id;
                        $ai->id = $DB->insert_record('assessment_types', $ai);
                        $DB->set_field_select('assessment_grades', 'assessmentid', $ai->id, 'type = 0 AND assessmentid = '.$a->teacher);
                    }
                }
                if ($a->self) {
                    if ($ai = $DB->get_record('assessment_individual', array('id'=>$a->self))) {
                        $ai->peernum = 0;
                        $ai->peergroupmode = 0;
                        $ai->type = 1;
                        $ai->assessmentid = $a->id;
                        $ai->id = $DB->insert_record('assessment_types', $ai);
                        $DB->set_field_select('assessment_grades', 'assessmentid', $ai->id, 'type = 1 AND assessmentid = '.$a->self);
                    }
                }
                if ($a->peer) {
                    if ($ai = $DB->get_record('assessment_types', array('id'=>$a->peer))) {
                        $ai->type = 2;
                        $ai->assessmentid = $a->id;
                        $ai->id = $DB->update_record('assessment_types', $ai);
                        $DB->set_field_select('assessment_grades', 'assessmentid', $ai->id, 'type = 2 AND assessmentid = '.$a->peer);
                    }
                }
            }
        }
        
        // Define table assessment_individual to be dropped
        $table = new xmldb_table('assessment_individual');

        // Conditionally launch drop table for assessment_individual
        if ($dbman->table_exists($table)) {
            $dbman->drop_table($table);
        }
        
        // Define index teacher (not unique) to be dropped form assessment
        $table = new xmldb_table('assessment');
        $index = new xmldb_index('teacher', XMLDB_INDEX_NOTUNIQUE, array('teacher'));

        // Conditionally launch drop index teacher
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        
        // Define field teacher to be dropped from assessment
        $table = new xmldb_table('assessment');
        $field = new xmldb_field('teacher');

        // Conditionally launch drop field teacher
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        
        // Define index self (not unique) to be dropped form assessment
        $table = new xmldb_table('assessment');
        $index = new xmldb_index('self', XMLDB_INDEX_NOTUNIQUE, array('self'));

        // Conditionally launch drop index self
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        
        // Define field self to be dropped from assessment
        $table = new xmldb_table('assessment');
        $field = new xmldb_field('self');

        // Conditionally launch drop field self
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        
        // Define index peer (not unique) to be dropped form assessment
        $table = new xmldb_table('assessment');
        $index = new xmldb_index('peer', XMLDB_INDEX_NOTUNIQUE, array('peer'));

        // Conditionally launch drop index peer
        if ($dbman->index_exists($table, $index)) {
            $dbman->drop_index($table, $index);
        }
        
        // Define field peer to be dropped from assessment
        $table = new xmldb_table('assessment');
        $field = new xmldb_field('peer');

        // Conditionally launch drop field peer
        if ($dbman->field_exists($table, $field)) {
            $dbman->drop_field($table, $field);
        }
        
        // assessment savepoint reached
        upgrade_mod_savepoint(true, 2012032900, 'assessment');
    }
    
    if ($oldversion < 2012033000) {

        // Define table assessment_rubric to be renamed to assessment_rubrics
        $table = new xmldb_table('assessment_rubric');

        // Launch rename table for assessment_rubrics
        $dbman->rename_table($table, 'assessment_rubrics');
        
         // Define key rubricid (foreign) to be dropped form assessment_rubric_col_specs
        $table = new xmldb_table('assessment_rubric_col_specs');
        $key = new xmldb_key('rubricid', XMLDB_KEY_FOREIGN, array('rubricid'), 'assessment_rubric', array('id'));

        // Launch drop key rubricid
        $dbman->drop_key($table, $key);
        
        // Define key rubricid (foreign) to be added to assessment_rubric_col_specs
        $table = new xmldb_table('assessment_rubric_col_specs');
        $key = new xmldb_key('rubricid', XMLDB_KEY_FOREIGN, array('rubricid'), 'assessment_rubrics', array('id'));

        // Launch add key rubricid
        $dbman->add_key($table, $key);
        
        // Define key rubricid (foreign) to be dropped form assessment_rubric_row_specs
        $table = new xmldb_table('assessment_rubric_row_specs');
        $key = new xmldb_key('rubricid', XMLDB_KEY_FOREIGN, array('rubricid'), 'assessment_rubric', array('id'));

        // Launch drop key rubricid
        $dbman->drop_key($table, $key);
        
         // Define key rubricid (foreign) to be added to assessment_rubric_row_specs
        $table = new xmldb_table('assessment_rubric_row_specs');
        $key = new xmldb_key('rubricid', XMLDB_KEY_FOREIGN, array('rubricid'), 'assessment_rubrics', array('id'));

        // Launch add key rubricid
        $dbman->add_key($table, $key);
        
        // assessment savepoint reached
        upgrade_mod_savepoint(true, 2012033000, 'assessment');
    }
    
    return true;
}

?>