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
 * @param int $base render number in this base (2, 8, 10, or 16)
 * @param int $lengthint expand to this many digits before the radix point
 * @param int $lengthfrac restrict to this many digits after the radix point
 * @param int $groupdigits optionally separate groups of this many digits
 * @param int $showprefix if true, then include base prefix
 * @return string formatted number.
 */
function qtype_calculatedformat_format_in_base($x, $base = 10, $lengthint = 1, $lengthfrac = 0, $groupdigits = 0, $showprefix = 0) {
    $digits = "0123456789ABCDEFGHIJKLMNOPQRSTUVWXYZ";

    if (($base != 2) && ($base != 8) && ($base != 10) && ($base != 16)) {
        throw new moodle_exception('illegalbase', 'qtype_calculatedformat', $base);
    }

    $base = intval($base);
    $lengthint = intval($lengthint);
    $lengthfrac = intval($lengthfrac);

    $masklengthint = $lengthint;

    if ($lengthint < 1) {
        $lengthint = 1;
        $masklengthint = 0;
    }

    if ($lengthfrac < 0) {
        $lengthfrac = 0;
    }

    if ($groupdigits < 0) {
        $groupdigits = 0;
    }

    $answer = $x;
    $sign = '';

    if (($base == 2) || ($base == 8) || ($base == 16)) {
        // Mask to exact number of digits, if required.
        $answer = qtype_calculatedformat_mask_value(
            $answer, $base, $masklengthint, $lengthfrac
        );

        // Round properly to correct # of digits.
        $answer *= pow($base, $lengthfrac);
        $answer = intval(round($answer));

    } else {
        // Convert to positive answer.
        if ($answer < 0) {
            $answer = -$answer;
            $sign = '-';
        }
    }

    if ($base == 2) {
        $x = sprintf('%0' . ($lengthint + $lengthfrac) . 'b', $answer);
    } else if ($base == 8) {
        $x = sprintf('%0' . ($lengthint + $lengthfrac) . 'o', $answer);
    } else if ($base == 16) {
        $x = sprintf('%0' . ($lengthint + $lengthfrac) . 'X', $answer);
    } else {
        $width = $lengthint;
        if ($lengthfrac > 0) {
            // Include fractional digits and decimal point.
            $width += $lengthfrac + 1;
        }
        $x = sprintf('%0' . $width . '.' . $lengthfrac . 'f', $answer);
    }

    if ($base != 10) {
        // Insert radix point if there are fractional digits.
        if ($lengthfrac > 0) {
            $x = substr_replace($x, '.', -$lengthfrac, 0);
        }
    }

    if (($base == 2) || ($base == 8) || ($base == 16)) {
        if ($masklengthint < 1) {
            // Strip leading zeros.
            $x = ltrim($x, '0');

            if (strlen($x) < 1) {
                $x = '0';

            } else if ($x[0] == '.') {
                $x = '0' . $x;
            }
        }
    }

    $prefix = '';
    if ($showprefix) {
        if ($base == 2) {
            $prefix = '0b';

        } else if ($base == 8) {
            $prefix = '0o';

        } else if ($base == 10) {
            $prefix = '0d';

        } else if ($base == 16) {
            $prefix = '0x';
        }
    }

    return $sign . $prefix . $x;
}

/**
 * Fix a number to exactly the required number of digits in binary, octal, or
 * hexadecimal.
 * @param number $x the number to fix
 * @param int $lengthint restrict to this many digits before the radix point
 * @param int $lengthfrac restrict to this many digits after the radix point
 * @return number number fitted to required number of digits
 */
function qtype_calculatedformat_mask_value($x, $base, $lengthint, $lengthfrac) {
    if (($base != 2) && ($base != 8) && ($base != 16)) {
        throw new moodle_exception('illegalbase', 'qtype_calculatedformat', $base);
    }

    $numbits = 0;
    for ($mask = 1; $mask < $base; $mask <<= 1) {
        $numbits++;
    }

    if ($lengthint < 1) {
        return $x;
    }

    $powbase = pow($base, $lengthfrac);

    // Round properly to correct # of digits.
    $x *= $powbase;
    $x = intval(round($x));

    $numbits *= ($lengthint + $lengthfrac);

    // Construct mask with exact bit length.
    $mask = 0;
    for ($i = 0; $i < $numbits; $i++) {
        $mask <<= 1;
        $mask |= 1;
    }

    // Mask off extra bits.
    $x &= $mask;

    // Convert back to fractional number.
    $x /= $powbase;

    return $x;
}
