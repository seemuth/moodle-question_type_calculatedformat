moodle-question_type_calculatedformat
=====================================

This Moodle question type supports improved dataset value formatting and answer formatting, including different numeric formats:

* Binary
* Octal
* Decimal
* Hexadecimal

It was modified from the Calculated question type at the University of Wisconsin - Madison by Daniel Seemuth.

Installation
------------

To install using git, type this command in the root of your Moodle install:

    git clone git://github.com/seemuth/moodle-question_type_calculatedformat.git question/type/calculatedformat

Then add /question/type/calculatedformat to your .gitignore file.

After you install this question type, navigate to /admin/index.php of your Moodle site.
This will prompt you to finish installing the question type.

Text Formatting
---------------

For question text and feedback text, you can format numbers in a variety of ways. Some examples include:

* `{%x={A}}` means format the wildcard {A} as a hexadecimal integer with as many digits as are needed. E.g., 2AF
* `{%4x={A}}` means format the wildcard {A} as a 4-digit hexadecimal integer. E.g., 02AF
* `{%4x={{A}+{B}}}` means format the sum of wildcards {A} and {B} as a 4-digit hexadecimal integer. E.g., 02AF
* `{%p4x={A}}` means format the wildcard {A} as a 4-digit hexadecimal integer with prefix 0x. E.g., 0x02AF
* `{%p4.3x={A}}` means format the wildcard {A} as a hexadecimal number with 4 integer digits and 3 fractional digits. E.g., 0x02AF.E00
* `{%_16b={A}}` means format the wildcard {A} as a 16-bit binary number with groups of 4 digits separated by the underscore character (`_`). E.g., 0011_1010_0000_1111
* `{%p_16b={A}}` means format the wildcard {A} as a 16-bit binary number with groups of 4 digits separated by the underscore character (`_`) with prefix 0b. E.g., 0b0011_1010_0000_1111
* `{%p,9o={A}}` means format the wildcard {A} as a 9-digit octal number with groups of 3 digits separated by a comma and with prefix 0o. E.g., 0o012,345,670

Formatting options (which appear between the percent sign and before any length specifiers) include:

* `p`: show base prefix (`0b`, `0o`, `0d`, or `0x`)
* `_`: display in groups of 4 digits separated by `_`
* `,`: display in groups of 3 digits separated by `,`

Length specifiers can be:
* _not given_: display in as many digits as needed
* _NUM_: display in at least _NUM_ integer digits if decimal, or display in exactly _NUM_ integer digits if binary, octal, or hexadecimal
* _NUM1_`.`_NUM2_: display integer portion in at least _NUM1_ digits if decimal, or in exactly _NUM1_ digits if binary, octal, or hexadecimal; followed by exactly _NUM2_ fractional digits

The base indicator (which appears immediately before the equals sign) can be:

* `b` for binary
* `o` for octal
* `d` for decimal
* `x` for hexadecimal.

Binary, octal, and hexadecimal numbers are masked to the appropriate number of digits if the number of digits is specified and positive. To display a binary, octal, or hexadecimal number without masking, do not include a digit length. E.g., `{%x={A}}`

Usage Guide
-----------

(This Usage Guide is written for Moodle v2.6.)

To use this question type, create or edit a quiz, and choose the "Calculated format" question type.

For example, we will create a question to convert a decimal number between 0 and 10 to binary, fixed-point format:

1. Create or edit a quiz. For this example, we will use the "Question behavior" quiz settings of shuffle: yes, interactive with multiple tries.
2. Click "Add a question ...".
3. Choose "Calculated format".
4. Enter a question name, e.g., "Convert decimal to fixed-point binary".
5. Enter the question text, e.g., "What is {A} in fixed-point binary format with 4 integer bits and 3 fractional bits? For purposes of testing, the answer should be {%4.3b={A}}.".
6. Under "Correct answer base and format":
  1. Set "Require answers in base" to "Binary (base 2)".
  2. Set "Require exact number of digits" to "Yes".
  3. Set "Number of integer digits" to 4.
  4. Set "Fractional digits" to 3.
  5. Set "Show base?" to "Show base as subscript."
7. Under "Answers":
  1. Set "Answer 1 formula =" to "{A}".
  2. Set "Grade" to 100%.
  3. Set "Tolerance ±" to 0.
  4. Set tolerance "Type" to "Nominal".
8. Click "Save changes".
9. Set "Wild card {A}" to "will use a new shared dataset".
10. Under "Synchronize the data from shared datasets with other questions in a quiz", choose "Synchronize".
11. Click "Next page".
12. Under "Add": under "Add item": click "Add".
13. Under "Add": under "Add item": click "Add" a second time (after the page reloads).
14. Below the "Delete" section, choose 5 "set(s) of wild card(s) values" and click "Display".
15. Under "Set 1" and "Set 2", set the "Shared wild card {A}" to 1.25 for one and 6.125 for the other.
16. Click "Save changes".
17. Preview the quiz. For this question you should see something like:
    `What is 1.25 in fixed-point binary format with 4 integer bits and 3 fractional bits? For purposes of testing, the answer should be 0001.010.`
18. Enter "1.010" and click "Check". You should see a message saying, "Incorrect number of digits".
19. Enter "0002.010" and click "Check". You should see a message saying, "You must enter a valid number."
20. Enter the correct answer and click "Check". Your answer should be marked as correct.
