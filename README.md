moodle-question_type_calculatedformat
=====================================

This Moodle question type supports improved dataset value formatting and answer formatting, including different numeric formats:

* Binary
* Octal
* Decimal
* Hexadecimal

It was modified from the Calculated question type at the University of Wisconsin - Madison by Dan Seemuth.

To install using git, type this command in the root of your Moodle install:

    git clone git://github.com/seemuth/moodle-question_type_calculatedformat.git question/type/calculatedformat

Then add /question/type/calculatedformat to your .gitignore file.

After you install this question type, navigate to /admin/index.php of your Moodle site.
This will prompt you to finish installing the question type.

To use this question type, create or edit a quiz, and choose the "Calculated numeric format" question type.
