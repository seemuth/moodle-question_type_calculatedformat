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
 * Question type class for the calculated question with number formatting type.
 *
 * @package    qtype
 * @subpackage calculatedformat
 * @copyright  1999 onwards Martin Dougiamas {@link http://moodle.com}
 * @copyright  2014 Daniel P. Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/questionbase.php');
require_once($CFG->dirroot . '/question/type/questiontypebase.php');
require_once($CFG->dirroot . '/question/type/numerical/question.php');
require_once($CFG->dirroot . '/question/type/numerical/questiontype.php');
require_once($CFG->dirroot . '/question/type/calculated/questiontype.php');
require_once($CFG->dirroot . '/question/type/calculatedformat/question.php');
require_once($CFG->dirroot . '/question/type/calculatedformat/lib.php');


/**
 * The calculated question with number formatting type.
 *
 * @copyright  1999 onwards Martin Dougiamas {@link http://moodle.com}
 * @copyright  2014 Daniel P. Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_calculatedformat extends qtype_calculated {

    public function get_question_options($question) {
        // First get the datasets and default options.
        // The code is used for calculated, calculatedsimple and calculatedmulti qtypes.
        global $CFG, $DB, $OUTPUT;
        if (!$question->options = $DB->get_record('question_calculatedfmt_opts',
                array('question' => $question->id))) {
            $question->options = new stdClass();
            $question->options->synchronize = 0;
            $question->options->single = 0;
            $question->options->answernumbering = 'abc';
            $question->options->shuffleanswers = 0;
            $question->options->correctfeedback = '';
            $question->options->partiallycorrectfeedback = '';
            $question->options->incorrectfeedback = '';
            $question->options->correctfeedbackformat = 0;
            $question->options->partiallycorrectfeedbackformat = 0;
            $question->options->incorrectfeedbackformat = 0;
            $question->options->correctanswerbase = 10;
            $question->options->correctanswerlengthint = 0;
            $question->options->correctanswerlengthfrac = 0;
        }

        if (!$question->options->answers = $DB->get_records_sql("
            SELECT a.*, c.tolerance, c.tolerancetype
            FROM {question_answers} a,
                 {question_calculatedfmt} c
            WHERE a.question = ?
            AND   a.id = c.answer
            ORDER BY a.id ASC", array($question->id))) {
                return false;
        }

        if ($this->get_virtual_qtype()->name() == 'numerical') {
            $this->get_virtual_qtype()->get_numerical_units($question);
            $this->get_virtual_qtype()->get_numerical_options($question);
        }

        $question->hints = $DB->get_records('question_hints',
                array('questionid' => $question->id), 'id ASC');

        if (isset($question->export_process)&&$question->export_process) {
            $question->options->datasets = $this->get_datasets_for_export($question);
        }
        return true;
    }

    public function save_question_options($question) {
        global $CFG, $DB;
        // The code is used for calculatedformat qtypes.
        $context = $question->context;
        if (isset($question->answer) && !isset($question->answers)) {
            $question->answers = $question->answer;
        }
        // Calculated options.
        $update = true;
        $options = $DB->get_record('question_calculatedfmt_opts',
                array('question' => $question->id));
        if (!$options) {
            $update = false;
            $options = new stdClass();
            $options->question = $question->id;
        }
        // As used only by calculated.
        if (isset($question->synchronize)) {
            $options->synchronize = $question->synchronize;
        } else {
            $options->synchronize = 0;
        }
        $options->single = 0;
        $options->answernumbering =  $question->answernumbering;
        $options->shuffleanswers = $question->shuffleanswers;

        $options->correctanswerbase = $question->correctanswerbase;
        $options->correctanswerlengthint = $question->correctanswerlengthint;
        $options->correctanswerlengthfrac = $question->correctanswerlengthfrac;

        foreach (array('correctfeedback', 'partiallycorrectfeedback',
                'incorrectfeedback') as $feedbackname) {
            $options->$feedbackname = '';
            $feedbackformat = $feedbackname . 'format';
            $options->$feedbackformat = 0;
        }

        if ($update) {
            $DB->update_record('question_calculatedfmt_opts', $options);
        } else {
            $DB->insert_record('question_calculatedfmt_opts', $options);
        }

        // Get old versions of the objects.
        $oldanswers = $DB->get_records('question_answers',
                array('question' => $question->id), 'id ASC');

        $oldoptions = $DB->get_records('question_calculatedfmt',
                array('question' => $question->id), 'answer ASC');

        // Save the units.
        $virtualqtype = $this->get_virtual_qtype();

        $result = $virtualqtype->save_units($question);
        if (isset($result->error)) {
            return $result;
        } else {
            $units = $result->units;
        }

        // Insert all the new answers.
        if (isset($question->answer) && !isset($question->answers)) {
            $question->answers = $question->answer;
        }
        foreach ($question->answers as $key => $answerdata) {
            if (is_array($answerdata)) {
                $answerdata = $answerdata['text'];
            }
            if (trim($answerdata) == '') {
                continue;
            }

            // Update an existing answer if possible.
            $answer = array_shift($oldanswers);
            if (!$answer) {
                $answer = new stdClass();
                $answer->question = $question->id;
                $answer->answer   = '';
                $answer->feedback = '';
                $answer->id       = $DB->insert_record('question_answers', $answer);
            }

            $answer->answer   = trim($answerdata);
            $answer->fraction = $question->fraction[$key];
            $answer->feedback = $this->import_or_save_files($question->feedback[$key],
                    $context, 'question', 'answerfeedback', $answer->id);
            $answer->feedbackformat = $question->feedback[$key]['format'];

            $DB->update_record("question_answers", $answer);

            // Set up the options object.
            if (!$options = array_shift($oldoptions)) {
                $options = new stdClass();
            }
            $options->question            = $question->id;
            $options->answer              = $answer->id;
            $options->tolerance           = trim($question->tolerance[$key]);
            $options->tolerancetype       = trim($question->tolerancetype[$key]);

            // Save options.
            if (isset($options->id)) {
                // Reusing existing record.
                $DB->update_record('question_calculatedfmt', $options);
            } else {
                // New options.
                $DB->insert_record('question_calculatedfmt', $options);
            }
        }

        // Delete old answer records.
        if (!empty($oldanswers)) {
            foreach ($oldanswers as $oa) {
                $DB->delete_records('question_answers', array('id' => $oa->id));
            }
        }

        // Delete old answer records.
        if (!empty($oldoptions)) {
            foreach ($oldoptions as $oo) {
                $DB->delete_records('question_calculatedfmt', array('id' => $oo->id));
            }
        }

        $result = $virtualqtype->save_unit_options($question);
        if (isset($result->error)) {
            return $result;
        }

        $this->save_hints($question);

        if (isset($question->import_process)&&$question->import_process) {
            $this->import_datasets($question);
        }
        // Report any problems.
        if (!empty($result->notice)) {
            return $result;
        }
        return true;
    }

    protected function initialise_question_instance(question_definition $question, $questiondata) {
        /* Do NOT call parent::initialise_question_instance, as parent is
         *  qtype_calculated, and some DB fields don't match:
         *      correctanswerlength
         *      correctanswerformat
         *
         * Instead, call method of question_type (qtype_calculated's parent).
         */
        question_type::initialise_question_instance($question, $questiondata);

        question_bank::get_qtype('numerical')->initialise_numerical_answers(
                $question, $questiondata);
        foreach ($questiondata->options->answers as $a) {
            $question->answers[$a->id]->tolerancetype = $a->tolerancetype;
        }

        $question->synchronised = $questiondata->options->synchronize;

        $question->correctanswerbase = $questiondata->options->correctanswerbase;
        $question->correctanswerlengthint = $questiondata->options->correctanswerlengthint;
        $question->correctanswerlengthfrac = $questiondata->options->correctanswerlengthfrac;

        $question->unitdisplay = $questiondata->options->showunits;
        $question->unitgradingtype = $questiondata->options->unitgradingtype;
        $question->unitpenalty = $questiondata->options->unitpenalty;
        $question->ap = $this->make_answer_processor(
            $questiondata->options->correctanswerbase,
            $questiondata->options->correctanswerlengthint,
            $questiondata->options->correctanswerlengthfrac,
            $questiondata->options->units, $questiondata->options->unitsleft);

        $question->datasetloader = new qtype_calculated_dataset_loader($questiondata->id);
    }

    public function make_answer_processor(
        $base, $lengthint, $lengthfrac, $units, $unitsleft
    ) {
        if (empty($units)) {
            return new qtype_calculatedformat_answer_processor($base, array());
        }

        $cleanedunits = array();
        foreach ($units as $unit) {
            $cleanedunits[$unit->unit] = $unit->multiplier;
        }

        return new qtype_calculatedformat_answer_processor(
            $base, $cleanedunits, $unitsleft
        );
    }

    public function validate_form($form) {
        switch($form->wizardpage) {
            case 'question':
                $calculatedmessages = array();
                if (empty($form->name)) {
                    $calculatedmessages[] = get_string('missingname', 'qtype_calculatedformat');
                }
                if (empty($form->questiontext)) {
                    $calculatedmessages[] = get_string('missingquestiontext', 'qtype_calculatedformat');
                }
                // Verify formulas.
                foreach ($form->answers as $key => $answer) {
                    if ('' === trim($answer)) {
                        $calculatedmessages[] = get_string(
                                'missingformula', 'qtype_calculatedformat');
                    }
                    if ($formulaerrors = qtype_calculatedformat_find_formula_errors($answer)) {
                        $calculatedmessages[] = $formulaerrors;
                    }
                    if (! isset($form->tolerance[$key])) {
                        $form->tolerance[$key] = 0.0;
                    }
                    if (! is_numeric($form->tolerance[$key])) {
                        $calculatedmessages[] = get_string('xmustbenumeric', 'qtype_numerical',
                                get_string('tolerance', 'qtype_calculatedformat'));
                    }
                }

                if (!empty($calculatedmessages)) {
                    $errorstring = "The following errors were found:<br />";
                    foreach ($calculatedmessages as $msg) {
                        $errorstring .= $msg . '<br />';
                    }
                    print_error($errorstring);
                }

                break;
            default:
                return parent::validate_form($form);
                break;
        }
        return true;
    }
    // This gets called by editquestion.php after the standard question is saved.
    public function print_next_wizard_page($question, $form, $course) {
        global $CFG, $COURSE;

        // Catch invalid navigation & reloads.
        if (empty($question->id)) {
            redirect('edit.php?courseid='.$COURSE->id, 'The page you are loading has expired.', 3);
        }

        // See where we're coming from.
        switch($form->wizardpage) {
            case 'question':
                require("$CFG->dirroot/question/type/calculatedformat/datasetdefinitions.php");
                break;
            case 'datasetdefinitions':
            case 'datasetitems':
                require("$CFG->dirroot/question/type/calculatedformat/datasetitems.php");
                break;
            default:
                print_error('invalidwizardpage', 'question');
                break;
        }
    }

    // This gets called by question2.php after the standard question is saved.
    public function &next_wizard_form($submiturl, $question, $wizardnow) {
        global $CFG, $COURSE;

        // Catch invalid navigation & reloads.
        if (empty($question->id)) {
            redirect('edit.php?courseid=' . $COURSE->id,
                    'The page you are loading has expired. Cannot get next wizard form.', 3);
        }

        // See where we're coming from.
        switch($wizardnow) {
            case 'datasetdefinitions':
                require("$CFG->dirroot/question/type/calculatedformat/datasetdefinitions_form.php");
                $mform = new question_dataset_dependent_definitions_form(
                        "$submiturl?wizardnow=datasetdefinitions", $question);
                break;
            case 'datasetitems':
                require("$CFG->dirroot/question/type/calculatedformat/datasetitems_form.php");
                $regenerate = optional_param('forceregeneration', false, PARAM_BOOL);
                $mform = new question_dataset_dependent_items_form(
                        "$submiturl?wizardnow=datasetitems", $question, $regenerate);
                break;
            default:
                print_error('invalidwizardpage', 'question');
                break;
        }

        return $mform;
    }

    /**
     * This method should be overriden if you want to include a special heading or some other
     * html on a question editing page besides the question editing form.
     *
     * @param question_edit_form $mform a child of question_edit_form
     * @param object $question
     * @param string $wizardnow is '' for first page.
     */
    public function display_question_editing_page($mform, $question, $wizardnow) {
        global $OUTPUT;
        switch ($wizardnow) {
            case '':
                // On the first page, the default display is fine.
                parent::display_question_editing_page($mform, $question, $wizardnow);
                return;

            case 'datasetdefinitions':
                echo $OUTPUT->heading_with_help(
                        get_string('choosedatasetproperties', 'qtype_calculatedformat'),
                        'questiondatasets', 'qtype_calculatedformat');
                break;

            case 'datasetitems':
                echo $OUTPUT->heading_with_help(get_string('editdatasets', 'qtype_calculatedformat'),
                        'questiondatasets', 'qtype_calculatedformat');
                break;
        }

        $mform->display();
    }

    /**
     * this version save the available data at the different steps of the question editing process
     * without using global $SESSION as storage between steps
     * at the first step $wizardnow = 'question'
     *  when creating a new question
     *  when modifying a question
     *  when copying as a new question
     *  the general parameters and answers are saved using parent::save_question
     *  then the datasets are prepared and saved
     * at the second step $wizardnow = 'datasetdefinitions'
     *  the datadefs final type are defined as private, category or not a datadef
     * at the third step $wizardnow = 'datasetitems'
     *  the datadefs parameters and the data items are created or defined
     *
     * @param object question
     * @param object $form
     * @param int $course
     * @param PARAM_ALPHA $wizardnow should be added as we are coming from question2.php
     */
    public function save_question($question, $form) {
        global $DB;
        if ($this->wizardpagesnumber() == 1 || $question->qtype == 'calculatedformatsimple') {
                $question = parent::save_question($question, $form);
            return $question;
        }

        $wizardnow =  optional_param('wizardnow', '', PARAM_ALPHA);
        $id = optional_param('id', 0, PARAM_INT); // Question id.
        // In case 'question':
        // For a new question $form->id is empty
        // when saving as new question.
        // The $question->id = 0, $form is $data from question2.php
        // and $data->makecopy is defined as $data->id is the initial question id.
        // Edit case. If it is a new question we don't necessarily need to
        // return a valid question object.

        // See where we're coming from.
        switch($wizardnow) {
            case '' :
            case 'question': // Coming from the first page, creating the second.
                if (empty($form->id)) { // or a new question $form->id is empty.
                    $question = parent::save_question($question, $form);
                    // Prepare the datasets using default $questionfromid.
                    $this->preparedatasets($form);
                    $form->id = $question->id;
                    $this->save_dataset_definitions($form);
                    if (isset($form->synchronize) && $form->synchronize == 2) {
                        $this->addnamecategory($question);
                    }
                } else if (!empty($form->makecopy)) {
                    $questionfromid =  $form->id;
                    $question = parent::save_question($question, $form);
                    // Prepare the datasets.
                    $this->preparedatasets($form, $questionfromid);
                    $form->id = $question->id;
                    $this->save_as_new_dataset_definitions($form, $questionfromid);
                    if (isset($form->synchronize) && $form->synchronize == 2) {
                        $this->addnamecategory($question);
                    }
                } else {
                    // Editing a question.
                    $question = parent::save_question($question, $form);
                    // Prepare the datasets.
                    $this->preparedatasets($form, $question->id);
                    $form->id = $question->id;
                    $this->save_dataset_definitions($form);
                    if (isset($form->synchronize) && $form->synchronize == 2) {
                        $this->addnamecategory($question);
                    }
                }
                break;
            case 'datasetdefinitions':
                // Calculated options.
                // It cannot go here without having done the first page,
                // so the question_calculatedfmt_opts should exist.
                // We only need to update the synchronize field.
                if (isset($form->synchronize)) {
                    $optionssynchronize = $form->synchronize;
                } else {
                    $optionssynchronize = 0;
                }
                $DB->set_field('question_calculatedfmt_opts', 'synchronize', $optionssynchronize,
                        array('question' => $question->id));
                if (isset($form->synchronize) && $form->synchronize == 2) {
                    $this->addnamecategory($question);
                }

                $this->save_dataset_definitions($form);
                break;
            case 'datasetitems':
                $this->save_dataset_items($question, $form);
                $this->save_question_calculatedformat($question, $form);
                break;
            default:
                print_error('invalidwizardpage', 'question');
                break;
        }
        return $question;
    }

    public function delete_question($questionid, $contextid) {
        global $DB;

        $DB->delete_records('question_calculatedfmt', array('question' => $questionid));
        $DB->delete_records('question_calculatedfmt_opts', array('question' => $questionid));
        $DB->delete_records('question_numerical_units', array('question' => $questionid));
        if ($datasets = $DB->get_records('question_datasets', array('question' => $questionid))) {
            foreach ($datasets as $dataset) {
                if (!$DB->get_records_select('question_datasets',
                        "question != ? AND datasetdefinition = ? ",
                        array($questionid, $dataset->datasetdefinition))) {
                    $DB->delete_records('question_dataset_definitions',
                            array('id' => $dataset->datasetdefinition));
                    $DB->delete_records('question_dataset_items',
                            array('definition' => $dataset->datasetdefinition));
                }
            }
        }
        $DB->delete_records('question_datasets', array('question' => $questionid));

        parent::delete_question($questionid, $contextid);
    }

    public function save_question_calculatedformat($question, $fromform) {
        global $DB;

        foreach ($question->options->answers as $key => $answer) {
            if ($options = $DB->get_record('question_calculatedfmt', array('answer' => $key))) {
                $options->tolerance = trim($fromform->tolerance[$key]);
                $options->tolerancetype  = trim($fromform->tolerancetype[$key]);
                $DB->update_record('question_calculatedfmt', $options);
            }
        }
    }

    public function comment_on_datasetitems($qtypeobj, $questionid, $questiontext,
        $answers, $data, $number
    ) {
        throw new coding_exception('Incompatible function comment_on_datasetitems');
    }

    public function comment_on_question_datasetitems($qtypeobj, $question,
        $answers, $data, $number
    ) {
        global $DB;
        $comment = new stdClass();
        $comment->stranswers = array();
        $comment->outsidelimit = false;
        $comment->answers = array();
        // Find a default unit.
        if (!empty($questionid) && $unit = $DB->get_record('question_numerical_units',
                array('question' => $question->id, 'multiplier' => 1.0))) {
            $unit = $unit->unit;
        } else {
            $unit = '';
        }

        $ap = qtype_calculatedformat::make_answer_processor(
            $question->options->correctanswerbase,
            $question->options->correctanswerlengthint,
            $question->options->correctanswerlengthfrac,
            $question->options->units, $question->options->unitsleft);

        $answers = fullclone($answers);
        $errors = '';
        $delimiter = ': ';
        $virtualqtype =  $qtypeobj->get_virtual_qtype();
        foreach ($answers as $key => $answer) {
            $formula = $this->substitute_variables($answer->answer, $data);
            $formattedanswer = qtype_calculatedformat_calculate_answer(
                $answer->answer, $data, $answer->tolerance,
                $answer->tolerancetype,
                $question->options->correctanswerbase,
                $question->options->correctanswerlengthint,
                $question->options->correctanswerlengthfrac,
                $unit);
            if ($formula === '*') {
                $answer->min = ' ';
            } else {
                eval('$ansvalue = '.$formula.';');
                $ans = new qtype_numerical_answer(0, $ansvalue, 0, '', 0, $answer->tolerance);
                $ans->tolerancetype = $answer->tolerancetype;
                list($answer->min, $answer->max) = $ans->get_tolerance_interval($answer);
            }
            if ($answer->min === '') {
                // This should mean that something is wrong.
                $comment->stranswers[$key] = " $formattedanswer->answer".'<br/><br/>';
            } else if ($formula === '*') {
                $comment->stranswers[$key] = $formula . ' = ' .
                        get_string('anyvalue', 'qtype_calculatedformat') . '<br/><br/><br/>';
            } else {
                $formula = shorten_text($formula, 57, true);
                $parsedanswer = $ap->parse_to_float($formattedanswer->answer);
                $comment->stranswers[$key] = $formula . ' = ' . $formattedanswer->answer . ' (' . $parsedanswer . ')<br/>';
                $correcttrue = new stdClass();
                $correcttrue->correct = $formattedanswer->answer;
                $correcttrue->true = '';
                if ($parsedanswer < $answer->min ||
                        $parsedanswer > $answer->max) {
                    $comment->outsidelimit = true;
                    $comment->answers[$key] = $key;
                    $comment->stranswers[$key] .=
                            get_string('trueansweroutsidelimits', 'qtype_calculatedformat', $correcttrue);
                } else {
                    $comment->stranswers[$key] .=
                            get_string('trueanswerinsidelimits', 'qtype_calculatedformat', $correcttrue);
                }
                $comment->stranswers[$key] .= '<br/>';
                $comment->stranswers[$key] .= get_string('min', 'qtype_calculatedformat') .
                        $delimiter . $answer->min . ' --- ';
                $comment->stranswers[$key] .= get_string('max', 'qtype_calculatedformat') .
                        $delimiter . $answer->max;
            }
        }
        return fullclone($comment);
    }

    public function evaluate_equations($str, $dataset) {
        $formula = $this->substitute_variables($str, $dataset);
        if ($error = qtype_calculatedformat_find_formula_errors($formula)) {
            return $error;
        }
        return $str;
    }

    public function substitute_variables_and_eval($str, $dataset) {
        $formula = $this->substitute_variables($str, $dataset);
        if ($error = qtype_calculatedformat_find_formula_errors($formula)) {
            return $error;
        }
        // Calculate the correct answer.
        if (empty($formula)) {
            $str = '';
        } else if ($formula === '*') {
            $str = '*';
        } else {
            $str = null;
            eval('$str = '.$formula.';');
        }
        return $str;
    }
}


function qtype_calculatedformat_calculate_answer($formula, $individualdata,
    $tolerance, $tolerancetype, $base, $lengthint, $lengthfrac, $unit = '') {
    // The return value has these properties: .
    // ->answer    the correct answer, formatted properly
    $calculated = new stdClass();
    // Exchange formula variables with the correct values...
    $answer = question_bank::get_qtype('calculatedformat')->substitute_variables_and_eval(
            $formula, $individualdata);
    if (!is_numeric($answer)) {
        // Something went wrong, so just return NaN.
        $calculated->answer = NAN;
        return $calculated;
    }

    $calculated->answer = qtype_calculatedformat_format_in_base(
        $answer, $base, $lengthint, $lengthfrac
    );

    if ($unit != '') {
        $calculated->answer = $calculated->answer . ' ' . $unit;
    }

    // Return the result.
    return $calculated;
}


function qtype_calculatedformat_find_formula_errors($formula) {
    // Validates the formula submitted from the question edit page.
    // Returns false if everything is alright
    // otherwise it constructs an error message.
    // Strip away dataset names.
    while (preg_match('~\\{[[:alpha:]][^>} <{"\']*\\}~', $formula, $regs)) {
        $formula = str_replace($regs[0], '1', $formula);
    }

    // Strip away empty space and lowercase it.
    $formula = strtolower(str_replace(' ', '', $formula));

    $safeoperatorchar = '-+/*%>:^\~<?=&|!'; /* */
    $operatorornumber = "[$safeoperatorchar.0-9a-fA-F_bodxBODX]";

    while (preg_match("~(^|[$safeoperatorchar,(])([a-z0-9_]*)" .
            "\\(($operatorornumber+(,$operatorornumber+((,$operatorornumber+)+)?)?)?\\)~",
        $formula, $regs)) {
        switch ($regs[2]) {
            // Simple parenthesis.
            case '':
                if ((isset($regs[4]) && $regs[4]) || strlen($regs[3]) == 0) {
                    return get_string('illegalformulasyntax', 'qtype_calculatedformat', $regs[0]);
                }
                break;

                // Zero argument functions.
            case 'pi':
                if ($regs[3]) {
                    return get_string('functiontakesnoargs', 'qtype_calculatedformat', $regs[2]);
                }
                break;

                // Single argument functions (the most common case).
            case 'abs': case 'acos': case 'acosh': case 'asin': case 'asinh':
            case 'atan': case 'atanh': case 'bindec': case 'ceil': case 'cos':
            case 'cosh': case 'decbin': case 'decoct': case 'deg2rad':
            case 'exp': case 'expm1': case 'floor': case 'is_finite':
            case 'is_infinite': case 'is_nan': case 'log10': case 'log1p':
            case 'octdec': case 'rad2deg': case 'sin': case 'sinh': case 'sqrt':
            case 'tan': case 'tanh':
                if (!empty($regs[4]) || empty($regs[3])) {
                    return get_string('functiontakesonearg', 'qtype_calculatedformat', $regs[2]);
                }
                break;

                // Functions that take one or two arguments.
            case 'log': case 'round':
                if (!empty($regs[5]) || empty($regs[3])) {
                    return get_string('functiontakesoneortwoargs', 'qtype_calculatedformat', $regs[2]);
                }
                break;

                // Functions that must have two arguments.
            case 'atan2': case 'fmod': case 'pow':
                if (!empty($regs[5]) || empty($regs[4])) {
                    return get_string('functiontakestwoargs', 'qtype_calculatedformat', $regs[2]);
                }
                break;

                // Functions that take two or more arguments.
            case 'min': case 'max':
                if (empty($regs[4])) {
                    return get_string('functiontakesatleasttwo', 'qtype_calculatedformat', $regs[2]);
                }
                break;

            default:
                return get_string('unsupportedformulafunction', 'qtype_calculatedformat', $regs[2]);
        }

        // Exchange the function call with '1' and then check for
        // another function call...
        if ($regs[1]) {
            // The function call is proceeded by an operator.
            $formula = str_replace($regs[0], $regs[1] . '1', $formula);
        } else {
            // The function call starts the formula.
            $formula = preg_replace("~^$regs[2]\\([^)]*\\)~", '1', $formula);
        }
    }

    if (preg_match("~[^$safeoperatorchar.0-9a-fA-F_bdoxBDOX]+~", $formula, $regs)) {
        return get_string('illegalformulasyntax', 'qtype_calculatedformat', $regs[0]);
    } else {
        // Formula just might be valid.
        return false;
    }
}


/**
 * This class processes numbers with units and in appropriate bases.
 *
 * @copyright 2010 The Open University
 * @copyright  2014 Daniel P. Seemuth
 * @license http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_calculatedformat_answer_processor
    extends qtype_numerical_answer_processor
{
    /** @var int required base, or 0 if any base is acceptable. */
    protected $base;

    public function __construct($base, $units, $unitsbefore = false,
        $decsep = null, $thousandssep = null
    ) {

        parent::__construct($units, $unitsbefore, $decsep, $thousandssep);

        if (!is_numeric($base)) {
            throw new moodle_exception('illegalbase', 'qtype_calculatedformat', $base);
        }

        $base = intval($base);
        if ($base < 2) {
            $this->base = 0;
        } else if ($base <= 36) {
            $this->base = $base;
        } else {
            throw new moodle_exception('illegalbase', 'qtype_calculatedformat', $base);
        }
    }

    /**
     * Create the regular expression that {@link parse_reponse_given_base()} requires.
     * @return string
     */
    protected function build_regex() {
        if (!is_null($this->regex)) {
            return $this->regex;
        }

        $decsep = preg_quote($this->decsep, '/');
        $thousandssep = preg_quote($this->thousandssep, '/');
        $digits = '0-9a-zA-Z';
        $signre = '([+-]?)';
        $baseprefixre = '((?:0[bodxBODX])?)';
        $intre = '([' . $thousandssep . $digits . '_]*)';
        $fracre = $decsep . '([' . $digits . '_]*)';

        $numberbit = "$signre$baseprefixre$intre(?:$fracre)?";

        if ($this->unitsbefore) {
            $this->regex = "/$numberbit$/";
        } else {
            $this->regex = "/^$numberbit/";
        }
        return $this->regex;
    }

    /**
     * Take a string which is a number with or without a sign, a base, and a
     * radix point, and possibly followed by one of the units, and split it
     * into bits.
     * @param string $response a value, optionally with a unit.
     * @return array five strings (some of which may be blank): the sign, the
     * base prefix (i.e., 0b, 0o, 0d, or 0x), the digits before
     * and after the decimal point, and the unit. All five will be
     * null if the response cannot be parsed.
     */
    protected function parse_response_given_base($response) {
        if (!preg_match($this->build_regex(), $response, $matches)) {
            return array(null, null, null, null, null);
        }

        $matches += array('', '', '', '', ''); // Fill in any missing matches.
        list($matchedpart, $sign, $baseprefix, $int, $frac) = $matches;

        if ($this->base >= 2) {
            // There should be no prefix in the response, so prepend to $int.
            if ($baseprefix !== '') {
                $int = $baseprefix . $int;
                $baseprefix = '';
            }
        }

        // Strip out thousands separators and group separators.
        $search = array($this->thousandssep, '_');
        $replace = array('', '');
        $int = str_replace($search, $replace, $int);
        $frac = str_replace($search, $replace, $frac);

        // Must be either something before, or something after the decimal point.
        // (The only way to do this in the regex would make it much more complicated.)
        if ($int === '' && $frac === '') {
            return array(null, null, null, null, null);
        }

        if ($this->unitsbefore) {
            $unit = substr($response, 0, -strlen($matchedpart));
        } else {
            $unit = substr($response, strlen($matchedpart));
        }
        $unit = trim($unit);

        return array($sign, $baseprefix, $int, $frac, $unit);
    }

    protected function parse_response($response) {
        throw new coding_exception('Incompatible function parse_response');
    }

    /**
     * Takes a number in almost any localised form, in a given or detected base,
     * and possibly with a unit after it. It separates off the unit, if present,
     * and converts to the default unit, by using the given unit multiplier.
     *
     * @param string $response a value, optionally with a base prefix, and
     *      optionally with a unit.
     * @return array(numeric, string, numeric) the value with the unit stripped,
     *      and normalised by the unit multiplier, if any, and the unit string,
     *      for reference.
     */
    public function apply_units($response, $separateunit = null) {
        // Strip spaces (which may be thousands separators).
        $response = str_replace(' ', '', $response);

        list($sign, $baseprefix, $int, $frac, $unit) = $this->parse_response_given_base($response);

        if (($int === null) && ($frac === null)) {
            return array(null, null, null);
        }

        if ($this->base >= 2) {
            $base = $this->base;
        } else {
            // Detect base from response.
            $baseprefix = strtolower($baseprefix);
            if ($baseprefix === '') {
                $base = 10;
            } else if ($baseprefix === '0b') {
                $base = 2;
            } else if ($baseprefix === '0o') {
                $base = 8;
            } else if ($baseprefix === '0d') {
                $base = 10;
            } else if ($baseprefix === '0x') {
                $base = 16;
            } else {
                return array(null, null, null);
            }
        }

        $basevalidchars = array(
            2 => '01',
            8 => '0-7',
            10 => '0-9',
            16 => '0-9a-fA-F',
        );
        $validcharsre = $basevalidchars[$base];
        if (!$validcharsre) {
            throw new moodle_exception('illegalbase', 'qtype_calculatedformat',
                $base);
        }

        // Convert as integer, then adjust for radix point afterward.
        $valuestr = $int . $frac;

        if (preg_match('[^' . $validcharsre . ']', $valuestr)) {
            return array(null, null, null);
        }

        $valuestrlen = strlen($valuestr);
        $value = 0;
        for ($i = 0; $i < $valuestrlen; $i++) {
            $c = $valuestr[$i];

            $value *= $base;

            if (($c >= '0') && ($c <= '9')) {
                $value += $c;
            } else if (($c >= 'A') && ($c <= 'Z')) {
                $value += (ord($c) - ord('A'));
            } else if (($c >= 'a') && ($c <= 'z')) {
                $value += ($ord(c) - ord('a'));
            } else {
                debugging('unexpected character: ' . $c);
                return array(null, null, null);
            }
        }

        if (strlen($frac) > 0) {
            // Adjust radix point.
            $value /= pow($base, strlen($frac));
        }

        if ($sign === '-') {
            $value = -$value;
        }

        if (!is_null($separateunit)) {
            $unit = $separateunit;
        }

        if ($this->is_known_unit($unit)) {
            $multiplier = 1 / $this->units[$unit];
        } else {
            $multiplier = null;
        }

        return array($value, $unit, $multiplier);
    }

    /**
     * Takes a number in almost any localised form, in a given or detected base,
     * and possibly with a unit after it. It applies the unit multiplier, if
     * applicable.
     *
     * @param string $response a value, optionally with a base prefix, and
     *      optionally with a unit.
     * @return false|numeric the value with the unit stripped, and with the
     *      multiplier applied (if present).
     *      Or return false if the response is invalid.
     */
    public function parse_to_float($response) {
        list($value, $unit, $multiplier) = $this->apply_units($response);
        if (is_null($value)) {
            return false;
        }
        if (!is_null($multiplier)) {
            $value *= $multiplier;
        }
        return $value;
    }
}
