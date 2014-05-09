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
 * Serve question type files
 *
 * @since      2.6
 * @package    qtype_calculatedformat
 * @copyright  Dongsheng Cai <dongsheng@moodle.com>
 * @copyright  2014 Daniel P. Seemuth
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */


defined('MOODLE_INTERNAL') || die();


/**
 * Checks file access for calculatedformat questions.
 *
 * @package  qtype_calculatedformat
 * @category files
 * @param stdClass $course course object
 * @param stdClass $cm course module object
 * @param stdClass $context context object
 * @param string $filearea file area
 * @param array $args extra arguments
 * @param bool $forcedownload whether or not force download
 * @param array $options additional options affecting the file serving
 * @return bool
 */
function qtype_calculatedformat_pluginfile($course, $cm, $context, $filearea, $args, $forcedownload, array $options=array()) {
    global $CFG;
    require_once($CFG->libdir . '/questionlib.php');
    question_pluginfile($course, $context, 'qtype_calculatedformat', $filearea, $args, $forcedownload, $options);
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
function qtype_calculatedformat_format_in_base($x, $base = 10, $lengthint = 1, $lengthfrac = 0) {
    $digits = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";

    if (($base < 2) || ($base > 36)) {
        throw new moodle_exception('illegalbase', 'qtype_calculatedformat', $base);
    }

    if ($lengthint < 1) {
        $lengthint = 1;
    }

    if ($lengthfrac < 0) {
        $lengthfrac = 0;
    }

    $answer = $x;
    // Convert to positive answer.
    if ($answer < 0) {
        $answer = -$answer;
        $sign = '-';
    } else {
        $sign = '';
    }

    // Round properly to correct # of digits.
    $answer *= pow($base, $lengthfrac);
    $answer = intval(round($answer));

    if ($answer == 0) {
        $sign = '';
    }

    // Convert to string in given base (in reverse order at first).
    $x = '';
    while ($answer > 0) {
        $mod = $answer % $base;
        $answer = intval(floor($answer / $base));

        $x .= digits[$mod];
    }

    // Insert required number of digits.
    $needed = $lengthint + $lengthfrac - strlen($x);
    if ($needed > 0) {
        $x .= str_repeat('0', $needed);
    }

    // Reverse string to get proper format.
    $x = strrev($x);

    // Insert radix point if there are fractional digits.
    if ($lengthfrac > 0) {
        $x = substr_replace($x, '.', -$lengthfrac, 0);
    }

    return $x;
}
