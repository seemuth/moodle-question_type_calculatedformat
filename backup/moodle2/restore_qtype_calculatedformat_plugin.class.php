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
 * @package    moodlecore
 * @subpackage backup-moodle2
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @copyright  2014 Daniel P. Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * restore plugin class that provides the necessary information
 * needed to restore one calculatedformat qtype plugin
 *
 * @copyright  2010 onwards Eloy Lafuente (stronk7) {@link http://stronk7.com}
 * @copyright  2014 Daniel P. Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class restore_qtype_calculatedformat_plugin extends restore_qtype_plugin {

    /**
     * Returns the paths to be handled by the plugin at question level
     */
    protected function define_question_plugin_structure() {

        $paths = array();

        // This qtype uses question_answers, add them.
        $this->add_question_question_answers($paths);

        // This qtype uses question_numerical_options and question_numerical_units, add them.
        $this->add_question_numerical_options($paths);
        $this->add_question_numerical_units($paths);

        // This qtype uses question datasets, add them.
        $this->add_question_datasets($paths);

        // Add own qtype stuff.
        $elename = 'calculatedformat_record';
        $elepath = $this->get_pathfor('/calculatedformat_records/calculatedformat_record');
        $paths[] = new restore_path_element($elename, $elepath);

        $elename = 'calculatedformat_option';
        $elepath = $this->get_pathfor('/calculatedformat_options/calculatedformat_option');
        $paths[] = new restore_path_element($elename, $elepath);

        return $paths; // And we return the interesting paths.
    }

    /**
     * Process the qtype/calculatedformat_record element
     */
    public function process_calculatedformat_record($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Detect if the question is created or mapped.
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ?
                true : false;

        // If the question has been created by restore, we need to create its
        // question_calculatedformat too.
        if ($questioncreated) {
            // Adjust some columns.
            $data->questionid = $newquestionid;
            $data->answerid = $this->get_mappingid('question_answer', $data->answerid);
            // Insert record.
            $newitemid = $DB->insert_record('qtype_calculatedfmt', $data);
        }
    }

    /**
     * Process the qtype/calculatedformat_option element
     */
    public function process_calculatedformat_option($data) {
        global $DB;

        $data = (object)$data;
        $oldid = $data->id;

        // Detect if the question is created or mapped.
        $oldquestionid   = $this->get_old_parentid('question');
        $newquestionid   = $this->get_new_parentid('question');
        $questioncreated = $this->get_mappingid('question_created', $oldquestionid) ?
                true : false;

        // If the question has been created by restore, we need to create its
        // question_calculatedformat too.
        if ($questioncreated) {
            // Adjust some columns.
            $data->questionid = $newquestionid;
            // Insert record.
            $newitemid = $DB->insert_record('qtype_calculatedfmt_opts', $data);
        }
    }
}
