<?php

defined('MOODLE_INTERNAL') || die();

/**
 * Lists all semesters where the user has courses enrolled
 *
 * @return array
 * @throws coding_exception
 */
function get_semester_by_user() {
    $TERM1START = "04-01";
    $TERM2START = "10-01";

    $otherCourses = false;
    $courses = enrol_get_my_courses('id, shortname', 'startdate DESC');
    $semester = [];
    foreach ($courses as $c) {

        // If course start date is undefined, set course term to "other".
        if ($c->startdate == 0) {
            $otherCourses = true;
        } // "Semester" mode.
        else {
            // Prepare date information.
            $coursestartyday = usergetdate($c->startdate)['yday'];
            $coursestartyear = usergetdate($c->startdate)['year'];
            $term1startyday = usergetdate(make_timestamp($coursestartyear, explode('-', $TERM1START)[0],
                    explode('-', $TERM1START)[1]))['yday'];
            $term2startyday = usergetdate(make_timestamp($coursestartyear, explode('-', $TERM2START)[0],
                    explode('-', $TERM2START)[1]))['yday'];

            // If course start date's day comes before first term start day,
            // set course term to second term of former year.
            if ($coursestartyday < $term1startyday) {
                $semester[$coursestartyear - 1][1] = true;
            }
            // If course start date's day comes on or after first term start day but before second term start day,
            // set course term to first term of current year.
            else if ($coursestartyday < $term2startyday) {
                $semester[$coursestartyear][0] = true;
            }
            // If course start date's day comes on or after second term start day,
            // set course term to second term of current year.
            else {
                $semester[$coursestartyear][1] = true;
            }
        }
    }

    $output = [];

    foreach ($semester as $y => $a) {
        if (isset($a[0])) {
            $terminfo = new stdClass();
            $terminfo->id = $y . '-0';
            $terminfo->name = "SS" . $y;
            $output[] = $terminfo;
        }
        if (isset($a[1])) {
            $terminfo = new stdClass();
            $terminfo->id = $y . '-1';
            $terminfo->name = "WS" . ($y) . "/" . ($y + 1);
            $output[] = $terminfo;
        }
    }

    if ($otherCourses) {
        $terminfo = array(); //new stdClass();
        $terminfo->id = 'other';
        $terminfo->name = get_string('other', 'block_course_overview_campus');
        $output[] = $terminfo;
    }

    $output[0]->active = true;
    //TODO

    return $output;
}

/**
 * Search the given $courses for any that match the given $classification up to the specified
 * $limit.
 *
 * This function will return the subset of courses that match the classification as well as the
 * number of courses it had to process to build that subset.
 *
 * It is recommended that for larger sets of courses this function is given a Generator that loads
 * the courses from the database in chunks.
 *
 * @param array|Traversable $courses List of courses to process
 * @param string $classification One of the COURSE_TIMELINE_* constants
 * @param int $limit Limit the number of results to this amount
 * @return array First value is the filtered courses, second value is the number of courses processed
 */
function course_filter_courses_by_term($courses, string $term, int $limit = 0): array {
    $termsplit = explode("-", $term);
    if (count($termsplit) != 2) {
        $message = 'Term must be of the form YYYY-0 for first or YYYY-1 for the second semester of the year';
        throw new moodle_exception($message);
    }

    $year = intval($termsplit[0], 10);

    if($termsplit[1] == '0') {
        $startdate = mktime(0,0,0, 4,1,$year);
        $enddate = mktime(0,0,0, 10,1,$year);
    } else {
        $startdate = mktime(0,0,0, 10,1,$year);
        $enddate = mktime(0,0,0, 4,1,$year + 1);
    }

    $filteredcourses = [];
    $numberofcoursesprocessed = 0;
    $filtermatches = 0;

    foreach ($courses as $course) {
        $numberofcoursesprocessed++;
        $pref = false;//get_user_preferences('block_myoverview_hidden_course_' . $course->id, 0);

        // Added as of MDL-63457 toggle viewability for each user.
        if ((!empty($course->startdate) && $course->startdate >= $startdate && $course->startdate <= $enddate) && !$pref) {
            $filteredcourses[] = $course;
            $filtermatches++;
        }

        if ($limit && $filtermatches >= $limit) {
            // We've found the number of requested courses. No need to continue searching.
            break;
        }
    }

    // Return the number of filtered courses as well as the number of courses that were searched
    // in order to find the matching courses. This allows the calling code to do some kind of
    // pagination.
    return [$filteredcourses, $numberofcoursesprocessed];
}