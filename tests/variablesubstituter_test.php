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
 * Unit tests for qtype_calculatedformat_variable_substituter.
 *
 * @package    qtype
 * @subpackage calculatedformat
 * @copyright  2011 The Open University
 * @copyright  2014 Daniel P. Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();

global $CFG;
require_once($CFG->dirroot . '/question/type/calculatedformat/question.php');


/**
 * Unit tests for {@link qtype_calculatedformat_variable_substituter}.
 *
 * @copyright  2011 The Open University
 * @copyright  2014 Daniel P. Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class qtype_calculatedformat_variable_substituter_test extends advanced_testcase {
    public function test_simple_expression() {
        $vs = new qtype_calculatedformat_variable_substituter(
            array('a' => 1, 'b' => 2),
            '.',
            ',',
            false
        );
        $this->assertEquals(3, $vs->calculate('{a} + {b}'));
    }

    public function test_simple_expression_negatives() {
        $vs = new qtype_calculatedformat_variable_substituter(
            array('a' => -1, 'b' => -2),
            '.',
            ',',
            false
        );
        $this->assertEquals(1, $vs->calculate('{a}-{b}'));
    }

    public function test_cannot_use_nonnumbers() {
        $this->setExpectedException('moodle_exception');
        $vs = new qtype_calculatedformat_variable_substituter(
            array('a' => 'frog', 'b' => -2),
            '.',
            ',',
            false
        );
    }

    public function test_invalid_expression() {
        $this->setExpectedException('moodle_exception');
        $vs = new qtype_calculatedformat_variable_substituter(
            array('a' => 1, 'b' => 2),
            '.',
            ',',
            false
        );
        $vs->calculate('{a} + {b}?');
    }

    public function test_tricky_invalid_expression() {
        $this->setExpectedException('moodle_exception');
        $vs = new qtype_calculatedformat_variable_substituter(
            array('a' => 1, 'b' => 2),
            '.',
            ',',
            false
        );
        $vs->calculate('{a}{b}'); // Have to make sure this does not just evaluate to 12.
    }

    public function test_replace_expressions_in_text_simple_var() {
        $vs = new qtype_calculatedformat_variable_substituter(
            array('a' => 1, 'b' => 2),
            '.',
            ',',
            false
        );
        $this->assertEquals('1 + 2', $vs->replace_expressions_in_text('{a} + {b}'));
    }

    public function test_replace_expressions_in_confusing_text() {
        $vs = new qtype_calculatedformat_variable_substituter(
            array('a' => 1, 'b' => 2),
            '.',
            ',',
            false
        );
        $this->assertEquals("(1) 1\n(2) 2", $vs->replace_expressions_in_text("(1) {a}\n(2) {b}"));
    }

    public function test_replace_expressions_in_text_formula() {
        $vs = new qtype_calculatedformat_variable_substituter(
            array('a' => 1, 'b' => 2),
            '.',
            ',',
            false
        );
        $this->assertEquals('= 3', $vs->replace_expressions_in_text('= {={a} + {b}}'));
    }

    public function test_replace_expressions_in_text_negative() {
        $vs = new qtype_calculatedformat_variable_substituter(
            array('a' => -1, 'b' => 2),
            '.',
            ',',
            false
        );
        $this->assertEquals('temperatures -1 and 2',
                $vs->replace_expressions_in_text('temperatures {a} and {b}'));
    }

    public function test_replace_expressions_in_text_commas_for_decimals() {
        $vs = new qtype_calculatedformat_variable_substituter(
            array('phi' => 1.61803399, 'pi' => 3.14159265),
            ',',
            '.',
            false
        );
        $this->assertEquals('phi (1,61803399) + pi (3,14159265) = 4,75962664',
                $vs->replace_expressions_in_text('phi ({phi}) + pi ({pi}) = {={phi} + {pi}}'));
    }

    public function test_format_float_dot_mindigits() {
        $vs = new qtype_calculatedformat_variable_substituter(
            array('a' => -1, 'b' => 2),
            '.',
            ',',
            false
        );
        $this->assertSame('0', $vs->format_in_base(0.12345));

        $this->assertSame('0.12345', $vs->format_in_base(0.12345, 10, 1, 5));
        $this->assertSame('0.12', $vs->format_in_base(0.12345, 10, 1, 2));
        $this->assertSame('0.1235', $vs->format_in_base(0.12345, 10, 1, 4));

        $this->assertSame('00.12', $vs->format_in_base(0.12345, 10, 2, 2));
        $this->assertSame('0.0012', $vs->format_in_base(0.0012345, 10, 1, 4));

        $this->assertSame('12,345.01', $vs->format_in_base(12345.01, 10, 1, 2, 3));

        $val = 22.375;
        $this->assertSame('0x16.6', $vs->format_in_base($val, 16, 1, 1, 0, 1));
        $this->assertSame('16.6', $vs->format_in_base($val, 16, 1, 1));
        $this->assertSame('16', $vs->format_in_base($val, 16, 1, 0));

        $this->assertSame('0b1_0110.0110', $vs->format_in_base($val, 2, 4, 4, 4, 1));
        $this->assertSame('1_0110.0110', $vs->format_in_base($val, 2, 4, 4, 4));
        $this->assertSame('10110.0110', $vs->format_in_base($val, 2, 4, 4));
        $this->assertSame('10110.10', $vs->format_in_base($val, 2, 4, 2));

        $this->assertSame('0o26.3', $vs->format_in_base($val, 8, 1, 1, 4, 1));
        $this->assertSame('26.3', $vs->format_in_base($val, 8, 1, 1));
        $this->assertSame('26', $vs->format_in_base($val, 8, 1, 0));
    }

    public function test_format_float_comma_mindigits() {
        $vs = new qtype_calculatedformat_variable_substituter(
            array('a' => -1, 'b' => 2),
            ',',
            '.',
            false
        );
        $this->assertSame('0', $vs->format_in_base(0.12345));

        $this->assertSame('0,12345', $vs->format_in_base(0.12345, 10, 1, 5));
        $this->assertSame('0,12', $vs->format_in_base(0.12345, 10, 1, 2));
        $this->assertSame('0,1235', $vs->format_in_base(0.12345, 10, 1, 4));

        $this->assertSame('00,12', $vs->format_in_base(0.12345, 10, 2, 2));
        $this->assertSame('0,0012', $vs->format_in_base(0.0012345, 10, 1, 4));

        $this->assertSame('12.345,01', $vs->format_in_base(12345.01, 10, 1, 2, 3));

        $val = 22.375;
        $this->assertSame('0x16,6', $vs->format_in_base($val, 16, 1, 1, 0, 1));
        $this->assertSame('16,6', $vs->format_in_base($val, 16, 1, 1));
        $this->assertSame('16', $vs->format_in_base($val, 16, 1, 0));

        $this->assertSame('0b1_0110,0110', $vs->format_in_base($val, 2, 4, 4, 4, 1));
        $this->assertSame('1_0110,0110', $vs->format_in_base($val, 2, 4, 4, 4));
        $this->assertSame('10110,0110', $vs->format_in_base($val, 2, 4, 4));
        $this->assertSame('10110,10', $vs->format_in_base($val, 2, 4, 2));

        $this->assertSame('0o26,3', $vs->format_in_base($val, 8, 1, 1, 4, 1));
        $this->assertSame('26,3', $vs->format_in_base($val, 8, 1, 1));
        $this->assertSame('26', $vs->format_in_base($val, 8, 1, 0));
    }

    public function test_format_float_dot_exactdigits() {
        $vs = new qtype_calculatedformat_variable_substituter(
            array('a' => -1, 'b' => 2),
            '.',
            ',',
            true
        );
        $this->assertSame('0', $vs->format_in_base(0.12345));

        $this->assertSame('0.12345', $vs->format_in_base(0.12345, 10, 1, 5));
        $this->assertSame('0.12', $vs->format_in_base(0.12345, 10, 1, 2));
        $this->assertSame('0.1235', $vs->format_in_base(0.12345, 10, 1, 4));

        $this->assertSame('00.12', $vs->format_in_base(0.12345, 10, 2, 2));
        $this->assertSame('0.0012', $vs->format_in_base(0.0012345, 10, 1, 4));

        $this->assertSame('12,345.01', $vs->format_in_base(12345.01, 10, 1, 2, 3));

        $val = 22.375;
        $this->assertSame('0x6.6', $vs->format_in_base($val, 16, 1, 1, 0, 1));
        $this->assertSame('6.6', $vs->format_in_base($val, 16, 1, 1));
        $this->assertSame('6', $vs->format_in_base($val, 16, 1, 0));

        $this->assertSame('0b0110.0110', $vs->format_in_base($val, 2, 4, 4, 4, 1));
        $this->assertSame('0110.0110', $vs->format_in_base($val, 2, 4, 4, 4));
        $this->assertSame('0110.0110', $vs->format_in_base($val, 2, 4, 4));
        $this->assertSame('0110.10', $vs->format_in_base($val, 2, 4, 2));

        $this->assertSame('0o6.3', $vs->format_in_base($val, 8, 1, 1, 4, 1));
        $this->assertSame('6.3', $vs->format_in_base($val, 8, 1, 1));
        $this->assertSame('6', $vs->format_in_base($val, 8, 1, 0));
    }

    public function test_format_float_comma_exactdigits() {
        $vs = new qtype_calculatedformat_variable_substituter(
            array('a' => -1, 'b' => 2),
            ',',
            '.',
            true
        );
        $this->assertSame('0', $vs->format_in_base(0.12345));

        $this->assertSame('0,12345', $vs->format_in_base(0.12345, 10, 1, 5));
        $this->assertSame('0,12', $vs->format_in_base(0.12345, 10, 1, 2));
        $this->assertSame('0,1235', $vs->format_in_base(0.12345, 10, 1, 4));

        $this->assertSame('00,12', $vs->format_in_base(0.12345, 10, 2, 2));
        $this->assertSame('0,0012', $vs->format_in_base(0.0012345, 10, 1, 4));

        $this->assertSame('12.345,01', $vs->format_in_base(12345.01, 10, 1, 2, 3));

        $val = 22.375;
        $this->assertSame('0x6,6', $vs->format_in_base($val, 16, 1, 1, 0, 1));
        $this->assertSame('6,6', $vs->format_in_base($val, 16, 1, 1));
        $this->assertSame('6', $vs->format_in_base($val, 16, 1, 0));

        $this->assertSame('0b0110,0110', $vs->format_in_base($val, 2, 4, 4, 4, 1));
        $this->assertSame('0110,0110', $vs->format_in_base($val, 2, 4, 4, 4));
        $this->assertSame('0110,0110', $vs->format_in_base($val, 2, 4, 4));
        $this->assertSame('0110,10', $vs->format_in_base($val, 2, 4, 2));

        $this->assertSame('0o6,3', $vs->format_in_base($val, 8, 1, 1, 4, 1));
        $this->assertSame('6,3', $vs->format_in_base($val, 8, 1, 1));
        $this->assertSame('6', $vs->format_in_base($val, 8, 1, 0));
    }
}
