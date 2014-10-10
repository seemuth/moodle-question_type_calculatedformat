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
 * Unit tests for the calculated question definition class with number formatting.
 *
 * @package    qtype
 * @subpackage calculatedformat
 * @copyright  2011 The Open University
 * @copyright  2014 Daniel P. Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/engine/tests/helpers.php');


/**
 * Unit tests for qtype_calculatedformat_definition.
 *
 * @copyright  2011 The Open University
 * @copyright  2014 Daniel P. Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_calculatedformat_question_test extends advanced_testcase {
    public function test_is_complete_response() {
        $question = test_question_maker::make_question('calculatedformat');

        $this->assertFalse($question->is_complete_response(array()));
        $this->assertTrue($question->is_complete_response(array('answer' => '0')));
        $this->assertTrue($question->is_complete_response(array('answer' => 0)));
        $this->assertFalse($question->is_complete_response(array('answer' => 'test')));
    }

    public function test_is_gradable_response() {
        $question = test_question_maker::make_question('calculatedformat');

        $this->assertFalse($question->is_gradable_response(array()));
        $this->assertTrue($question->is_gradable_response(array('answer' => '0')));
        $this->assertTrue($question->is_gradable_response(array('answer' => 0)));
        $this->assertTrue($question->is_gradable_response(array('answer' => 'test')));
    }

    public function test_grading() {
        $question = test_question_maker::make_question('calculatedformat');
        $question->start_attempt(new question_attempt_step(), 1);
        $values = $question->vs->get_values();

        $this->assertEquals(array(0, question_state::$gradedwrong),
                $question->grade_response(array('answer' => $values['a'] - $values['b'])));
        $this->assertEquals(array(1, question_state::$gradedright),
                $question->grade_response(array('answer' => $values['a'] + $values['b'])));
    }

    public function test_get_correct_response() {
        // Testing with -3.0 + 0.125.
        $question = test_question_maker::make_question('calculatedformat');
        $question->start_attempt(new question_attempt_step(), 3);
        $values = $question->vs->get_values();
        $this->assertSame(array('answer' => '-2.8750' ), $question->get_correct_response());
        $question->correctanswerlengthint = 2;
        $question->correctanswerlengthfrac = 2;
        $this->assertSame(array('answer' => '-02.88' ), $question->get_correct_response());
        $question->correctanswerlengthint = 1;
        $question->correctanswerlengthfrac = 0;
        $this->assertSame(array('answer' => '-3' ), $question->get_correct_response());
        $question->correctanswerbase = 2;
        $question->correctanswerlengthint = 1;
        $question->correctanswerlengthfrac = 4;
        $this->assertSame(array('answer' => '-0b10.1110' ), $question->get_correct_response());
        $question->correctanswerlengthint = 4;
        $this->assertSame(array('answer' => '-0b0010.1110' ), $question->get_correct_response());
        $question->vs->exactdigits = $question->exactdigits = 1;
        $this->assertSame(array('answer' => '0b1101.0010' ), $question->get_correct_response());
        $question->correctanswerbase = 16;
        $question->correctanswerlengthint = 2;
        $question->correctanswerlengthfrac = 2;
        $question->vs->exactdigits = $question->exactdigits = 1;
        $this->assertSame(array('answer' => '0xFD.20' ), $question->get_correct_response());
        $question->vs->exactdigits = $question->exactdigits = 0;
        $this->assertSame(array('answer' => '-0x02.E0' ), $question->get_correct_response());

        // Testing with 1.0 + 5.0.
        $question = test_question_maker::make_question('calculatedformat');
        $question->start_attempt(new question_attempt_step(), 1);
        $values = $question->vs->get_values();
        $this->assertSame(array('answer' => '6.0000' ), $question->get_correct_response());
        $question->correctanswerlengthint = 2;
        $question->correctanswerlengthfrac = 2;
        $this->assertSame(array('answer' => '06.00' ), $question->get_correct_response());
        $question->correctanswerlengthint = 1;
        $question->correctanswerlengthfrac = 0;
        $this->assertSame(array('answer' => '6' ), $question->get_correct_response());
        $question->correctanswerbase = 2;
        $question->correctanswerlengthint = 2;
        $question->correctanswerlengthfrac = 0;
        $question->vs->exactdigits = $question->exactdigits = 0;
        $this->assertSame(array('answer' => '0b110' ), $question->get_correct_response());
        $question->vs->exactdigits = $question->exactdigits = 1;
        $this->assertSame(array('answer' => '0b10' ), $question->get_correct_response());
        $question->correctanswerbase = 8;
        $question->correctanswerlengthint = 2;
        $question->correctanswerlengthfrac = 0;
        $question->vs->exactdigits = $question->exactdigits = 0;
        $this->assertSame(array('answer' => '0o06' ), $question->get_correct_response());
        $question->correctanswerbase = 16;
        $question->correctanswerlengthint = 2;
        $question->correctanswerlengthfrac = 0;
        $question->vs->exactdigits = $question->exactdigits = 0;
        $this->assertSame(array('answer' => '0x06' ), $question->get_correct_response());

        // Testing with 31337 + 0.125.
        $question = test_question_maker::make_question('calculatedformat');
        $question->start_attempt(new question_attempt_step(), 4);
        $values = $question->vs->get_values();
        $this->assertSame(array('answer' => '31337.1250' ), $question->get_correct_response());
        $question->correctanswerlengthint = 2;
        $question->correctanswerlengthfrac = 2;
        $this->assertSame(array('answer' => '31337.13' ), $question->get_correct_response());
        $question->correctanswerlengthint = 1;
        $question->correctanswerlengthfrac = 0;
        $this->assertSame(array('answer' => '31337' ), $question->get_correct_response());
        $thousandssep = get_string('thousandssep', 'langconfig');
        $question->correctanswergroupdigits = 3;
        $this->assertSame(array('answer' => "31{$thousandssep}337" ), $question->get_correct_response());
        $question->correctanswerlengthfrac = 4;
        $this->assertSame(array('answer' => "31{$thousandssep}337.1250" ), $question->get_correct_response());
        $question->correctanswerbase = 16;
        $question->correctanswerlengthint = 1;
        $question->correctanswerlengthfrac = 0;
        $question->correctanswergroupdigits = 4;
        $this->assertSame(array('answer' => '0x7A69' ), $question->get_correct_response());
        $question->correctanswerlengthfrac = 3;
        $this->assertSame(array('answer' => '0x7A69.200' ), $question->get_correct_response());
        $question->correctanswerbase = 2;
        $question->correctanswerlengthint = 1;
        $question->correctanswerlengthfrac = 0;
        $question->correctanswergroupdigits = 4;
        $this->assertSame(array('answer' => '0b111_1010_0110_1001' ), $question->get_correct_response());
        $question->correctanswergroupdigits = 0;
        $this->assertSame(array('answer' => '0b111101001101001' ), $question->get_correct_response());

    }

    public function test_get_question_summary() {
        $question = test_question_maker::make_question('calculatedformat');
        $question->start_attempt(new question_attempt_step(), 1);
        $values = $question->vs->get_values();

        $qsummary = $question->get_question_summary();
        $this->assertEquals('What is ' . $values['a'] . ' + ' . $values['b'] . '?', $qsummary);
    }

    public function test_summarise_response() {
        $question = test_question_maker::make_question('calculatedformat');
        $question->start_attempt(new question_attempt_step(), 1);
        $values = $question->vs->get_values();

        $this->assertEquals('3.1', $question->summarise_response(array('answer' => '3.1')));
    }

    public function test_classify_response() {
        $question = test_question_maker::make_question('calculatedformat');
        $question->start_attempt(new question_attempt_step(), 1);
        $values = $question->vs->get_values();

        $this->assertEquals(array(
                new question_classified_response(13, $values['a'] + $values['b'], 1.0)),
                $question->classify_response(array('answer' => $values['a'] + $values['b'])));
        $this->assertEquals(array(
                new question_classified_response(14, $values['a'] - $values['b'], 0.0)),
                $question->classify_response(array('answer' => $values['a'] - $values['b'])));
        $this->assertEquals(array(
                new question_classified_response(17, 7 * $values['a'], 0.0)),
                $question->classify_response(array('answer' => 7 * $values['a'])));
        $this->assertEquals(array(
                question_classified_response::no_response()),
                $question->classify_response(array('answer' => '')));
    }

    public function test_classify_response_no_star() {
        $question = test_question_maker::make_question('calculatedformat');
        unset($question->answers[17]);
        $question->start_attempt(new question_attempt_step(), 1);
        $values = $question->vs->get_values();

        $this->assertEquals(array(
                new question_classified_response(13, $values['a'] + $values['b'], 1.0)),
                $question->classify_response(array('answer' => $values['a'] + $values['b'])));
        $this->assertEquals(array(
                new question_classified_response(14, $values['a'] - $values['b'], 0.0)),
                $question->classify_response(array('answer' => $values['a'] - $values['b'])));
        $this->assertEquals(array(
                new question_classified_response(0, 7 * $values['a'], 0.0)),
                $question->classify_response(array('answer' => 7 * $values['a'])));
        $this->assertEquals(array(
                question_classified_response::no_response()),
                $question->classify_response(array('answer' => '')));
    }

    public function test_get_variants_selection_seed_q_not_synchronised() {
        $question = test_question_maker::make_question('calculatedformat');
        $this->assertEquals($question->stamp, $question->get_variants_selection_seed());
    }

    public function test_get_variants_selection_seed_q_synchronised_datasets_not() {
        $question = test_question_maker::make_question('calculatedformat');
        $question->synchronised = true;
        $this->assertEquals($question->stamp, $question->get_variants_selection_seed());
    }

    public function test_get_variants_selection_seed_q_synchronised() {
        $question = test_question_maker::make_question('calculatedformat');
        $question->synchronised = true;
        $question->datasetloader->set_are_synchronised($question->category, true);
        $this->assertEquals('category' . $question->category,
                $question->get_variants_selection_seed());
    }
}
