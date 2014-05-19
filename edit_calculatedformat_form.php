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
 * Defines the editing form for the calculated question type.
 *
 * @package    qtype
 * @subpackage calculatedformat
 * @copyright  2007 Jamie Pratt me@jamiep.org
 * @copyright  2014 Daniel P. Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/numerical/edit_numerical_form.php');


/**
 * Calculated question type with number formatting editing form definition.
 *
 * @copyright  2007 Jamie Pratt me@jamiep.org
 * @copyright  2014 Daniel P. Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_calculatedformat_edit_form extends qtype_numerical_edit_form {
    /**
     * Handle to the question type for this question.
     *
     * @var qtype_calculated
     */
    public $qtypeobj;
    public $questiondisplay;
    public $activecategory;
    public $categorychanged = false;
    public $initialname = '';
    public $reload = false;

    public function __construct($submiturl, $question, $category, $contexts,
            $formeditable = true) {
        global $CFG, $DB;
        $this->question = $question;
        $this->reload = optional_param('reload', false, PARAM_BOOL);

        if (!$this->reload) { // Use database data as this is first pass.
            if (isset($this->question->id)) {
                // Remove prefix #{..}# if exists.
                $this->initialname = $question->name;
                $regs = array();
                if (preg_match('~#\{([^[:space:]]*)#~', $question->name , $regs)) {
                    $question->name = str_replace($regs[0], '', $question->name);
                };
            }
        }
        parent::__construct($submiturl, $question, $category, $contexts, $formeditable);
    }

    public function get_per_answer_fields($mform, $label, $gradeoptions,
            &$repeatedoptions, &$answersoption) {
        $repeated = parent::get_per_answer_fields($mform, $label, $gradeoptions,
                $repeatedoptions, $answersoption);

        // Reorganise answer options group. 0 is the answer. 1 is tolerance. 2 is Grade.
        $answeroptions = $repeated[0]->getElements();
        // Tolerance field will be part of its own group.
        $tolerance = $answeroptions[1];

        // Update Answer options group to contain only answer and grade fields.
        $answeroptions[0]->setSize(55);
        $answeroptions = array($answeroptions[0], $answeroptions[2]);
        $repeated[0]->setElements($answeroptions);

        // Update answer field and group label.
        $repeated[0]->setLabel(get_string('answerformula', 'qtype_calculatedformat', '{no}') . ' =');
        $answeroptions[0]->setLabel(get_string('answerformula', 'qtype_calculatedformat', '{no}') . ' =');

        // Get feedback field to re append later.
        $feedback = array_pop($repeated);

        // Create tolerance group.
        $answertolerance = array();
        $tolerance->setLabel(get_string('tolerance', 'qtype_calculatedformat') . '=');
        $answertolerance[] = $tolerance;
        $answertolerance[] = $mform->createElement('select', 'tolerancetype',
                get_string('tolerancetype', 'qtype_calculatedformat'), $this->qtypeobj->tolerance_types());
        $repeated[] = $mform->createElement('group', 'answertolerance',
                 get_string('tolerance', 'qtype_calculatedformat'), $answertolerance, null, false);
        $repeatedoptions['tolerance']['default'] = 0.01;

        // Add feedback.
        $repeated[] = $feedback;

        return $repeated;
    }

    /**
     * Add question-type specific form fields.
     *
     * @param MoodleQuickForm $mform the form being built.
     */
    protected function definition_inner($mform) {
        $this->qtypeobj = question_bank::get_qtype($this->qtype());
        $label = get_string('sharedwildcards', 'qtype_calculatedformat');
        $mform->addElement('hidden', 'initialcategory', 1);
        $mform->addElement('hidden', 'reload', 1);
        $mform->setType('initialcategory', PARAM_INT);
        $mform->setType('reload', PARAM_BOOL);
        $html2 = $this->qtypeobj->print_dataset_definitions_category($this->question);
        $mform->insertElementBefore(
                $mform->createElement('static', 'listcategory', $label, $html2), 'name');
        if (isset($this->question->id)) {
            $mform->insertElementBefore($mform->createElement('static', 'initialname',
                    get_string('questionstoredname', 'qtype_calculatedformat'),
                    $this->initialname), 'name');
        };
        $addfieldsname = 'updatecategory';
        $addstring = get_string('updatecategory', 'qtype_calculatedformat');
        $mform->registerNoSubmitButton($addfieldsname);

        $mform->insertElementBefore(
                $mform->createElement('submit', $addfieldsname, $addstring), 'listcategory');
        $mform->registerNoSubmitButton('createoptionbutton');

        // Editing as regular question.
        $mform->setType('single', PARAM_INT);

        $mform->addElement('hidden', 'shuffleanswers', '1');
        $mform->setType('shuffleanswers', PARAM_INT);
        $mform->addElement('hidden', 'answernumbering', 'abc');
        $mform->setType('answernumbering', PARAM_SAFEDIR);

        // Correct answer format elements.
        $mform->addElement('header', 'correctanswersection',
            get_string('correctanswerbaseformat', 'qtype_calculatedformat'));
        $bases = array(
            0 => get_string('anybase', 'qtype_calculatedformat'),
            2 => get_string('binary', 'qtype_calculatedformat'),
            8 => get_string('octal', 'qtype_calculatedformat'),
            10 => get_string('decimal', 'qtype_calculatedformat'),
            16 => get_string('hexadecimal', 'qtype_calculatedformat'),
        );
        $mform->addElement('select', 'correctanswerbase',
            get_string('requirebase', 'qtype_calculatedformat'),
            $bases);
        $mform->addHelpButton('correctanswerbase', 'requirebase',
            'qtype_calculatedformat');
        $mform->setDefault('correctanswerbase', 10);
        $mform->setType('correctanswerbase', PARAM_INT);

        $mform->addElement('text', 'correctanswerlengthint',
            get_string('correctanswerlengthint', 'qtype_calculatedformat'));
        $mform->addRule('correctanswerlengthint',
            get_string('missingcorrectanswerlength', 'qtype_calculatedformat'),
            'required');
        $mform->addHelpButton('correctanswerlengthint', 'correctanswerlengthint',
            'qtype_calculatedformat');
        $mform->setDefault('correctanswerlengthint', 1);
        $mform->setType('correctanswerlengthint', PARAM_INT);

        $exactdigitsoptions = array(
            0 => get_string('mindigits', 'qtype_calculatedformat'),
            1 => get_string('exactdigits', 'qtype_calculatedformat'),
        );
        $mform->addElement('select', 'exactdigits',
            get_string('minexactdigits', 'qtype_calculatedformat'),
            $exactdigitsoptions);
        $mform->addHelpButton('exactdigits', 'minexactdigits',
            'qtype_calculatedformat');
        $mform->setDefault('exactdigits', 0);
        $mform->setType('exactdigits', PARAM_INT);

        $mform->addElement('text', 'correctanswerlengthfrac',
            get_string('correctanswerlengthfrac', 'qtype_calculatedformat'));
        $mform->addRule('correctanswerlengthfrac',
            get_string('missingcorrectanswerlength', 'qtype_calculatedformat'),
            'required');
        $mform->addHelpButton('correctanswerlengthfrac', 'correctanswerlengthfrac',
            'qtype_calculatedformat');
        $mform->setDefault('correctanswerlengthfrac', 0);
        $mform->setType('correctanswerlengthfrac', PARAM_INT);

        $groupoptions = array(
            0 => get_string('nogrouping', 'qtype_calculatedformat'),
            3 => get_string(
                'groupthree', 'qtype_calculatedformat',
                get_string('thousandssep', 'langconfig')
            ),
            4 => get_string('groupfour', 'qtype_calculatedformat', '_'),
        );
        $mform->addElement('select', 'correctanswergroupdigits',
            get_string('groupdigits', 'qtype_calculatedformat'),
            $groupoptions);
        $mform->addHelpButton('correctanswergroupdigits', 'groupdigits',
            'qtype_calculatedformat');
        $mform->setDefault('correctanswergroupdigits', 0);
        $mform->setType('correctanswergroupdigits', PARAM_INT);

        $this->add_per_answer_fields($mform, get_string('answerhdr', 'qtype_calculatedformat', '{no}'),
                question_bank::fraction_options(), 1, 1);

        $repeated = array();

        $this->add_unit_options($mform, $this);
        $this->add_unit_fields($mform, $this);
        $this->add_interactive_settings();

        // Hidden elements.
        $mform->addElement('hidden', 'synchronize', '');
        $mform->setType('synchronize', PARAM_INT);
        $mform->addElement('hidden', 'wizard', 'datasetdefinitions');
        $mform->setType('wizard', PARAM_ALPHA);
    }

    public function data_preprocessing($question) {
        $question = parent::data_preprocessing($question);
        $question = $this->data_preprocessing_answers($question);
        $question = $this->data_preprocessing_hints($question);
        $question = $this->data_preprocessing_units($question);
        $question = $this->data_preprocessing_unit_options($question);

        if (isset($question->options->synchronize)) {
            $question->synchronize = $question->options->synchronize;
        }
        if (isset($question->options->correctanswerbase)) {
            $question->correctanswerbase = $question->options->correctanswerbase;
        }
        if (isset($question->options->correctanswerlengthint)) {
            $question->correctanswerlengthint = $question->options->correctanswerlengthint;
        }
        if (isset($question->options->correctanswerlengthfrac)) {
            $question->correctanswerlengthfrac = $question->options->correctanswerlengthfrac;
        }
        if (isset($question->options->correctanswergroupdigits)) {
            $question->correctanswergroupdigits = $question->options->correctanswergroupdigits;
        }
        if (isset($question->options->exactdigits)) {
            $question->exactdigits = $question->options->exactdigits;
        }

        return $question;
    }

    protected function data_preprocessing_answers($question, $withanswerfiles = false) {
        $question = parent::data_preprocessing_answers($question, $withanswerfiles);
        if (empty($question->options->answers)) {
            return $question;
        }

        $key = 0;
        foreach ($question->options->answers as $answer) {
            // See comment in the parent method about this hack.
            unset($this->_form->_defaultValues["tolerancetype[$key]"]);

            $question->tolerancetype[$key]       = $answer->tolerancetype;
            $key++;
        }

        return $question;
    }

    public function qtype() {
        return 'calculated';
    }

    public function validation($data, $files) {

        $errors = parent::validation($data, $files);

        // Verifying for errors in {=...} in question text.
        $qtext = "";
        $qtextremaining = $data['questiontext']['text'];
        $possibledatasets = $this->qtypeobj->find_dataset_names($data['questiontext']['text']);
        foreach ($possibledatasets as $name => $value) {
            $qtextremaining = str_replace('{'.$name.'}', '1', $qtextremaining);
        }
        while (preg_match('~\{=([^[:space:]}]*)}~', $qtextremaining, $regs1)) {
            $qtextsplits = explode($regs1[0], $qtextremaining, 2);
            $qtext = $qtext.$qtextsplits[0];
            $qtextremaining = $qtextsplits[1];
            if (!empty($regs1[1]) && $formulaerrors =
                    qtype_calculatedformat_find_formula_errors($regs1[1])) {
                if (!isset($errors['questiontext'])) {
                    $errors['questiontext'] = $formulaerrors.':'.$regs1[1];
                } else {
                    $errors['questiontext'] .= '<br/>'.$formulaerrors.':'.$regs1[1];
                }
            }
        }

        // Check that the answers use datasets.
        $answers = $data['answer'];
        $mandatorydatasets = array();
        foreach ($answers as $key => $answer) {
            $mandatorydatasets += $this->qtypeobj->find_dataset_names($answer);
        }
        if (empty($mandatorydatasets)) {
            foreach ($answers as $key => $answer) {
                $errors['answeroptions['.$key.']'] =
                        get_string('atleastonewildcard', 'qtype_calculatedformat');
            }
        }

        // Validate the answer format.
        foreach ($answers as $key => $answer) {
            $trimmedanswer = trim($answer);
        }

        return $errors;
    }

    protected function is_valid_answer($answer, $data) {
        return !qtype_calculatedformat_find_formula_errors($answer);
    }

    protected function valid_answer_message($answer) {
        if (!$answer) {
            return get_string('mustenteraformulaorstar', 'qtype_numerical');
        } else {
            return qtype_calculatedformat_find_formula_errors($answer);
        }
    }
}
