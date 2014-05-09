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
 * Calculated question with number formatting definition class.
 *
 * @package    qtype
 * @subpackage calculatedformat
 * @copyright  2011 The Open University
 * @copyright  2014 Daniel P. Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/question/type/numerical/question.php');
require_once($CFG->dirroot . '/question/type/calculated/question.php');


/**
 * Represents a calculated question with number formatting.
 *
 * @copyright  2011 The Open University
 * @copyright  2014 Daniel P. Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_calculatedformat_question extends qtype_calculated_question
    implements qtype_calculatedformat_question_with_expressions {

    public function start_attempt(question_attempt_step $step, $variant) {
        qtype_calculatedformat_question_helper::start_attempt($this, $step, $variant);

        // Skip parent (qtype_calculated_question), because that would conflict
        qtype_numerical_question::start_attempt($step, $variant);
    }

    public function apply_attempt_state(question_attempt_step $step) {
        qtype_calculatedformat_question_helper::apply_attempt_state($this, $step);

        // Skip parent (qtype_calculated_question), because that would conflict
        qtype_numerical_question::apply_attempt_state($step);
    }

    public function calculate_all_expressions() {
        $this->questiontext = $this->vs->replace_expressions_in_text($this->questiontext);
        $this->generalfeedback = $this->vs->replace_expressions_in_text($this->generalfeedback);

        foreach ($this->answers as $ans) {
            if ($ans->answer && $ans->answer !== '*') {
                $ans->answer = $this->vs->calculate($ans->answer);
            }
            $ans->feedback = $this->vs->replace_expressions_in_text($ans->feedback);
        }
    }

    public function get_correct_response() {
        $answer = $this->get_correct_answer();
        if (!$answer) {
            return array();
        }

        $response = array('answer' => $this->vs->format_in_base($answer->answer,
            $answer->correctanswerbase,
            $answer->correctanswerlengthint, $answer->correctanswerlengthfrac));

        if ($this->has_separate_unit_field()) {
            $response['unit'] = $this->ap->get_default_unit();
        } else if ($this->unitdisplay == qtype_numerical::UNITINPUT) {
            $response['answer'] = $this->ap->add_unit($response['answer']);
        }

        return $response;
    }

}


/**
 * This interface defines the method that a question type must implement if it
 * is to work with {@link qtype_calculatedformat_question_helper}.
 *
 * As well as this method, the class that implements this interface must have
 * fields
 * public $datasetloader; // of type qtype_calculated_dataset_loader
 * public $vs; // of type qtype_calculatedformat_variable_substituter
 *
 * @copyright  2011 The Open University
 * @copyright  2014 Daniel P. Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
interface qtype_calculatedformat_question_with_expressions {
    /**
     * Replace all the expression in the question definition with the values
     * computed from the selected dataset by calling $this->vs->calculate() and
     * $this->vs->replace_expressions_in_text() on the parts of the question
     * that require it.
     */
    public function calculate_all_expressions();
}


/**
 * Helper class for questions that use datasets. Works with the interface
 * {@link qtype_calculatedformat_question_with_expressions} and the class
 * {@link qtype_calculated_dataset_loader} to set up the value of each variable
 * in start_attempt, and restore that in apply_attempt_state.
 *
 * @copyright  2011 The Open University
 * @copyright  2014 Daniel P. Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
abstract class qtype_calculatedformat_question_helper {
    public static function start_attempt(
            qtype_calculatedformat_question_with_expressions $question,
            question_attempt_step $step, $variant) {

        $question->vs = new qtype_calculatedformat_variable_substituter(
                $question->datasetloader->get_values($variant),
                get_string('decsep', 'langconfig'));
        $question->calculate_all_expressions();

        foreach ($question->vs->get_values() as $name => $value) {
            $step->set_qt_var('_var_' . $name, $value);
        }
    }

    public static function apply_attempt_state(
        qtype_calculatedformat_question_with_expressions $question,
        question_attempt_step $step) {

        $values = array();
        foreach ($step->get_qt_data() as $name => $value) {
            if (substr($name, 0, 5) === '_var_') {
                $values[substr($name, 5)] = $value;
            }
        }

        $question->vs = new qtype_calculatedformat_variable_substituter(
                $values, get_string('decsep', 'langconfig'));
        $question->calculate_all_expressions();
    }
}


/**
 * This class holds the current values of all the variables used by a
 * calculatedformat question.
 *
 * It can compute formulae using those values, and can substitute equations
 * embedded in text.
 *
 * In contrast to {@link qtype_calculated_question_helper}, this class
 * automatically converts appropriate HTML entities to their corresponding
 * characters: &amp; &lt; &gt;
 *
 * @copyright  2011 The Open University
 * @copyright  2014 Daniel P. Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_calculatedformat_variable_substituter {
    /** @var array variable name => value */
    protected $values;

    /** @var string character to use for the decimal point in displayed numbers. */
    protected $decimalpoint;

    /** @var array variable names wrapped in {...}. Used by {@link substitute_values()}. */
    protected $search;

    /**
     * @var array variable values, with negative numbers wrapped in (...).
     * Used by {@link substitute_values_for_eval()}.
     */
    protected $safevalue;

    /**
     * @var array variable values, with negative numbers wrapped in (...).
     * Used by {@link substitute_values()}.
     */
    protected $prettyvalue;

    /**
     * Constructor
     * @param array $values variable name => value.
     */
    public function __construct(array $values, $decimalpoint) {
        $this->values = $values;
        $this->decimalpoint = $decimalpoint;

        // Prepare an array for {@link substitute_values()}.
        $this->search = array();
        $this->replace = array();
        foreach ($values as $name => $value) {
            if (!is_numeric($value)) {
                $a = new stdClass();
                $a->name = '{' . $name . '}';
                $a->value = $value;
                throw new moodle_exception('notvalidnumber', 'qtype_calculatedformat', '', $a);
            }

            $this->search[] = '{' . $name . '}';
            $this->safevalue[] = '(' . $value . ')';
            $this->prettyvalue[] = $this->format_simple($value);
        }
    }

    /**
     * Display a number formatted only by replacing the decimal point with
     * the appropriate character.
     * @param number $x the number to format
     * @return string formatted number.
     */
    public function format_simple($x) {
        return str_replace('.', $this->decimalpoint, $x);
    }

    /**
     * Given a number format:
     *      `%' NUM (`.' NUM)? [bdoh]
     * format the number in the given base with the given # of digits.
     *
     * If the number format is not valid, return null.
     *
     * @param string $fmt the number format, e.g.:
     *      4.2d for 4 integer digits, 2 fractional digits, in base 10 (decimal)
     * @param number $x the number to format
     * @return string formatted number.
     */
    public function format_by_fmt($fmt, $x) {
        if (preg_match('/^%(\d+)(?:\.(\d+))?([bodxBODX])$/', $fmt, $regs)) {
            list($fullmatch, $lengthint, $lengthfrac, $basestr) = $regs;

            $base = 0;
            if ($basestr == 'b') {
                $base = 2;

            } else if ($basestr == 'o') {
                $base = 8;

            } else if ($basestr == 'd') {
                $base = 10;

            } else if ($basestr == 'h') {
                $base = 16;

            } else {
                throw new coding_exception('Invalid base: ' . $basestr);
            }

            $lengthint = intval($lengthint);
            $lengthfrac = intval($lengthfrac);

            return $this->format_in_base($x, $base, $lengthint, $lengthfrac);
        }

        // Not a valid format.
        return null;
    }

    /**
     * Display a number properly formatted in a certain base, with a certain
     * number of digits before and after the radix point.
     * @param number $x the number to format
     * @param int $lengthint expand to this many digits before the radix point
     * @param int $lengthfrac restrict to this many digits after the radix point
     * @param int $base render number in this base (2 <= $base <= 36)
     * @return string formatted number.
     */
    public function format_in_base($x, $base = 10, $lengthint = 1, $lengthfrac = 0) {
        $formatted = qtype_calculatedformat_format_in_base($x, $base, $lengthint, $lengthfrac);
        return str_replace('.', $this->decimalpoint, $formatted);
    }

    /**
     * Return an array of the variables and their values.
     * @return array name => value.
     */
    public function get_values() {
        return $this->values;
    }

    /**
     * Evaluate an expression using the variable values.
     * @param string $expression the expression. A PHP expression with placeholders
     *      like {a} for where the variables need to go.
     * @return float the computed result.
     */
    public function calculate($expression) {
        return $this->calculate_raw($this->substitute_values_for_eval($expression));
    }

    /**
     * Evaluate an expression after the variable values have been substituted.
     *
     * In contrast to {@link qtype_calculated_question_helper}, this class
     * automatically converts appropriate HTML entities to their corresponding
     * characters: &amp; &lt; &gt;
     *
     * @param string $expression the expression. A PHP expression with placeholders
     *      like {a} for where the variables need to go.
     * @return float the computed result.
     */
    protected function calculate_raw($expression) {
        $html_ops = array('&amp;', '&lt;', '&gt;');
        $raw_ops = array('&', '<', '>');
        $expression = str_replace($html_ops, $raw_ops, $expression);

        // This validation trick from http://php.net/manual/en/function.eval.php .
        if (!@eval('return true; $result = ' . $expression . ';')) {
            throw new moodle_exception('illegalformulasyntax', 'qtype_calculatedformat', '', $expression);
        }
        return eval('return ' . $expression . ';');
    }

    /**
     * Substitute variable placehodlers like {a} with their value wrapped in ().
     * @param string $expression the expression. A PHP expression with placeholders
     *      like {a} for where the variables need to go.
     * @return string the expression with each placeholder replaced by the
     *      corresponding value.
     */
    protected function substitute_values_for_eval($expression) {
        return str_replace($this->search, $this->safevalue, $expression);
    }

    /**
     * Substitute variable placehodlers like {a} with their value without wrapping
     * the value in anything.
     * @param string $text some content with placeholders
     *      like {a} for where the variables need to go.
     * @return string the expression with each placeholder replaced by the
     *      corresponding value.
     */
    protected function substitute_values_pretty($text) {
        return str_replace($this->search, $this->prettyvalue, $text);
    }

    /**
     * Replace any embedded variables (like {a}), formulae (like {={a} + {b}}),
     * or formatted formulae (like {%8.0h={a} + {b}})
     * in some text with the corresponding values.
     * @param string $text the text to process.
     * @return string the text with values substituted.
     */
    public function replace_expressions_in_text($text) {
        $vs = $this; // Can't see to use $this in a PHP closure.
        $re_format = '(\d+(?:\.\d+)?[bodxBODX])?';
        $re_expr = '=([^{}]*(?:\{[^{}]+}[^{}]*)*)';
        $text = preg_replace_callback('~\{' . $re_format . $re_expr . '}~',
                function ($matches) use ($vs) {
                    $calc = $vs->calculate($matches[2]);
                    $ret = $vs->format_by_fmt($matches[1], $calc);
                    if (is_null($ret)) {
                        $ret = $vs->format_simple($calc);
                    }
                }, $text);
        return $this->substitute_values_pretty($text);
    }

}
