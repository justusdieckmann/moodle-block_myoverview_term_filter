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

namespace block_myoverview_term_filter\util;

defined('MOODLE_INTERNAL') || die();

use \stdClass;
use moodle_exception;

class filter_helper {

    /** Lists all semesters where the user has courses enrolled. Also gives the default term for each grouping method
     *
     * @return array
     */
    public static function get_semester_by_user() {
        $term1start = "04-01";
        $term2start = "10-01";

        $othercourses = false;
        $courses = enrol_get_my_courses('id, shortname', 'startdate ASC');
        $semester = [];
        foreach ($courses as $c) {

            // If course start date is undefined, set course term to "other".
            if ($c->startdate == 0) {
                $othercourses = true;
            } else {
                // Prepare date information.
                $coursestartyday = usergetdate($c->startdate)['yday'];
                $coursestartyear = usergetdate($c->startdate)['year'];
                $term1startyday = usergetdate(make_timestamp($coursestartyear, explode('-', $term1start)[0],
                        explode('-', $term1start)[1]))['yday'];
                $term2startyday = usergetdate(make_timestamp($coursestartyear, explode('-', $term2start)[0],
                        explode('-', $term2start)[1]))['yday'];

                // If course start date's day comes before first term start day,
                // set course term to second term of former year.
                if ($coursestartyday < $term1startyday) {
                    $semester[$coursestartyear - 1][1] = true;
                } else if ($coursestartyday < $term2startyday) {
                    // If course start date's day comes on or after first term start day but before second term start day,
                    // set course term to first term of current year.
                    $semester[$coursestartyear][0] = true;
                } else {
                    // If course start date's day comes on or after second term start day,
                    // set course term to second term of current year.
                    $semester[$coursestartyear][1] = true;
                }
            }
        }

        $terms = [];

        $currentterm = self::get_current_term();
        $userlatestterm = 'all';
        $userpreviousterm = 'all';

        foreach ($semester as $y => $a) {
            if (isset($a[0])) {
                $terminfo = new stdClass();
                $terminfo->id = $y . '-0';
                $terminfo->name = "SS" . $y;
                $terms[] = $terminfo;

                if ($y < $currentterm[0]) {
                    $userlatestterm = $terminfo->id;
                    $userpreviousterm = $terminfo->id;
                } else if ($currentterm[0] == $y && $currentterm[1] == 0) {
                    $userlatestterm = $terminfo->id;
                }
            }
            if (isset($a[1])) {
                $terminfo = new stdClass();
                $terminfo->id = $y . '-1';
                $terminfo->name = "WS" . ($y) . "/" . ($y + 1);
                $terms[] = $terminfo;

                if ($y < $currentterm[0]) {
                    $userlatestterm = $terminfo->id;
                    $userpreviousterm = $terminfo->id;
                } else if ($currentterm[0] == $y) {
                    if ($currentterm[1] === 0) {
                        $userlatestterm = $terminfo->id;
                        $userpreviousterm = $terminfo->id;
                    } else if ($currentterm[1] === 1) {
                        $userlatestterm = $terminfo->id;
                    }
                }
            }
        }

        $terminfo = new stdClass();
        $terminfo->id = 'all';
        $terminfo->name = get_string('all', 'block_course_overview_campus');
        $terms[] = $terminfo;

        $terms = array_reverse($terms);

        if ($othercourses) {
            $terminfo = new stdClass();
            $terminfo->id = 'other';
            $terminfo->name = get_string('other', 'block_course_overview_campus');
            $terms[] = $terminfo;
        }

        $defaults = self::get_defaults($userlatestterm, $userpreviousterm);

        return array($terms, $defaults);
    }

    /**
     * Returns the current term
     *
     * @return array : [YYYY (year), 0|1 (First / Second term)]
     */
    private static function get_current_term() {
        // TODO Settings.
        $term1start = "04-01";
        $term2start = "10-01";

        $time = time();
        $yday = usergetdate($time)['yday'];
        $year = usergetdate($time)['year'];
        $term1startyday = usergetdate(make_timestamp($year, explode('-', $term1start)[0],
                explode('-', $term1start)[1]))['yday'];
        $term2startyday = usergetdate(make_timestamp($year, explode('-', $term2start)[0],
                explode('-', $term2start)[1]))['yday'];
        if ($yday < $term1startyday) {
            $thisterm = array(($year - 1), 1);
        } else if ($yday < $term2startyday) {
            $thisterm = array($year, 0);
        } else {
            $thisterm = array($year, 1);
        }
        return $thisterm;
    }

    /**
     * @param string $latestterm Term-ID (YYYY-0 / YYYY-1 / all / other) for the latest term in which the user has courses
     * @param string $previousterm Term-ID (YYYY-0 / YYYY-1 / all / other) for the latest term (before the current term) in which
     *         the user has courses
     * @return array the default-array
     */
    private static function get_defaults($latestterm, $previousterm) {
        $defaults = [];
        $defaults['default_all'] = $latestterm;
        $defaults['default_inprogress'] = 'all';
        $defaults['default_future'] = 'all';
        $defaults['default_past'] = $previousterm;
        $defaults['default_favourites'] = $latestterm;
        $defaults['default_hidden'] = 'all';
        return $defaults;
    }

    /**
     * Search the given $courses for any that match the given $classification and $term up to the specified
     * $limit.
     *
     * This function will return the subset of courses that are favourites and in the specified term as well as the
     * number of courses it had to process to build that subset.
     *
     * It is recommended that for larger sets of courses this function is given a Generator that loads
     * the courses from the database in chunks.
     *
     * @param array|Traversable $courses List of courses to process
     * @param array $favouritecourseids Array of favourite courses.
     * @param string $term Term to seach for: Term-ID (YYYY-0 / YYYY-1 / all / other)
     * @param int $limit Limit the number of results to this amount
     * @return array First value is the filtered courses, second value is the number of courses processed
     * @throws moodle_exception
     */
    public static function course_filter_courses_by_favourites_and_term(
            $courses,
            $favouritecourseids,
            string $term,
            int $limit = 0
    ): array {

        $other = false;
        $all = false;
        if ($term == "other") {
            $other = true;
        } else if ($term == "all") {
            $all = true;
        } else if (preg_match('/^\\d{4}-(0|1)$/', $term)) {
            list($startdate, $enddate) = self::get_duration_of_term($term);
        } else {
            $message = 'Term must be "all", "other" or  of the form YYYY-0 for first or YYYY-1 for the second semester of the year';
            throw new moodle_exception($message);
        }

        $filteredcourses = [];
        $numberofcoursesprocessed = 0;
        $filtermatches = 0;

        foreach ($courses as $course) {
            $numberofcoursesprocessed++;

            if (in_array($course->id, $favouritecourseids)) {
                if ($all || ($other && (empty($course->startdate) || $course->startdate <= 5000)) ||
                        (!$other && !empty($course->startdate) && $course->startdate >= $startdate &&
                                $course->startdate <= $enddate)) {
                    $filteredcourses[] = $course;
                    $filtermatches++;
                }
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

    /**
     * Search the given $courses for any that match the given $classification and $term up to the specified
     * $limit.
     *
     * This function will return the subset of courses that match the classification and term as well as the
     * number of courses it had to process to build that subset.
     *
     * It is recommended that for larger sets of courses this function is given a Generator that loads
     * the courses from the database in chunks.
     *
     * @param array|Traversable $courses List of courses to process
     * @param string $classification One of the COURSE_TIMELINE_* constants
     * @param string $term Term to seach for: Term-ID (YYYY-0 / YYYY-1 / all / other)
     * @param int $limit Limit the number of results to this amount
     * @return array First value is the filtered courses, second value is the number of courses processed
     * @throws moodle_exception
     */
    public static function course_filter_courses_by_timeline_classification_and_term(
            $courses,
            string $classification,
            string $term,
            int $limit = 0
    ): array {

        $all = false;
        $other = false;

        if ($term == "all") {
            $all = true;
        } else if ($term == "other") {
            $other = true;
        } else if (preg_match('/^\\d{4}-(0|1)$/', $term)) {
            list($startdate, $enddate) = self::get_duration_of_term($term);
        } else {
            $message = 'Term must be "all", "other" or  of the form YYYY-0 for first or YYYY-1 for the second semester of the year';
            throw new moodle_exception($message);
        }

        if (!in_array($classification,
                [COURSE_TIMELINE_ALL, COURSE_TIMELINE_PAST, COURSE_TIMELINE_INPROGRESS,
                        COURSE_TIMELINE_FUTURE, COURSE_TIMELINE_HIDDEN])) {
            $message = 'Classification must be one of COURSE_TIMELINE_ALL, COURSE_TIMELINE_PAST, '
                    . 'COURSE_TIMELINE_INPROGRESS or COURSE_TIMELINE_FUTURE';
            throw new moodle_exception($message);
        }

        $filteredcourses = [];
        $numberofcoursesprocessed = 0;
        $filtermatches = 0;

        foreach ($courses as $course) {
            $numberofcoursesprocessed++;
            $pref = get_user_preferences('block_myoverview_hidden_course_' . $course->id, 0);

            // TODO Remove 5000!
            if (($classification == COURSE_TIMELINE_HIDDEN && $pref) ||
                    (($classification == COURSE_TIMELINE_ALL || $classification == course_classify_for_timeline($course)) &&
                            !$pref)) {
                if ($all || ($other && (empty($course->startdate) || $course->startdate <= 5000)) || (!$other &&
                                !empty($course->startdate) && $course->startdate >= $startdate && $course->startdate <= $enddate)) {
                    $filteredcourses[] = $course;
                    $filtermatches++;
                }
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

    public static function get_duration_of_term($term) {
        $termsplit = explode("-", $term);
        $year = intval($termsplit[0], 10);

        if ($termsplit[1] == '0') {
            $startdate = mktime(0, 0, 0, 4, 1, $year);
            $enddate = mktime(0, 0, 0, 10, 1, $year);
        } else {
            $startdate = mktime(0, 0, 0, 10, 1, $year);
            $enddate = mktime(0, 0, 0, 4, 1, $year + 1);
        }
        return array($startdate, $enddate);
    }

}