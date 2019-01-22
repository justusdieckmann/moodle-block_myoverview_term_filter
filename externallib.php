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
 * Myoverview term filter external file
 *
 * @package    local_myoverview_term_filter
 * @copyright  2019 Justus Dieckmann <justusdieckmann@wwu.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->libdir . "/externallib.php");

class local_block_myoverview_term_filter_external extends external_api {

    /**
     * Returns description of method parameters
     *
     * @return external_function_parameters
     */
    public static function get_enrolled_courses_by_term_parameters() {
        return new external_function_parameters(
                array(
                        'term' => new external_value(PARAM_TEXT, 'term to filter for', VALUE_DEFAULT, null),
                        'classification' => new external_value(PARAM_ALPHA, 'future, inprogress, or past'),
                        'limit' => new external_value(PARAM_INT, 'Result set limit', VALUE_DEFAULT, 0),
                        'offset' => new external_value(PARAM_INT, 'Result set offset', VALUE_DEFAULT, 0),
                        'sort' => new external_value(PARAM_TEXT, 'Sort string', VALUE_DEFAULT, null)
                )
        );
    }

    /**
     * Get courses matching the given term.
     *
     * NOTE: The offset applies to the unfiltered full set of courses before the classification
     * filtering is done.
     * E.g.
     * If the user is enrolled in 5 courses:
     * c1, c2, c3, c4, and c5
     * And c4 and c5 are 'future' courses
     *
     * If a request comes in for future courses with an offset of 1 it will mean that
     * c1 is skipped (because the offset applies *before* the classification filtering)
     * and c4 and c5 will be return.
     *
     * @param  string $term
     * @param  int $limit Result set limit
     * @param  int $offset Offset the full course set before timeline classification is applied
     * @param  string $sort SQL sort string for results
     * @return array list of courses and warnings
     * @throws  invalid_parameter_exception
     */
    public static function get_enrolled_courses_by_term(
            string $term,
            string $classification,
            int $limit = 0,
            int $offset = 0,
            string $sort = null
    ) {
        global $CFG, $PAGE, $USER;
        require_once($CFG->dirroot . '/course/lib.php');
        require_once($CFG->dirroot . '/blocks/myoverview_term_filter/locallib.php');

        $params = self::validate_parameters(self::get_enrolled_courses_by_term_parameters(),
                array(
                        'term' => $term,
                        'classification' => $classification,
                        'limit' => $limit,
                        'offset' => $offset,
                        'sort' => $sort,
                )
        );

        $term = $params['term'];
        $classification = $params['classification'];
        $limit = $params['limit'];
        $offset = $params['offset'];
        $sort = $params['sort'];

        switch ($classification) {
            case COURSE_TIMELINE_ALL:
                break;
            case COURSE_TIMELINE_PAST:
                break;
            case COURSE_TIMELINE_INPROGRESS:
                break;
            case COURSE_TIMELINE_FUTURE:
                break;
            case COURSE_FAVOURITES:
                break;
            case COURSE_TIMELINE_HIDDEN:
                break;
            default:
                throw new invalid_parameter_exception('Invalid classification');
        }

        self::validate_context(context_user::instance($USER->id));

        $requiredproperties = core_course\external\course_summary_exporter::define_properties();
        $fields = join(',', array_keys($requiredproperties));
        $hiddencourses = get_hidden_courses_on_timeline();
        $courses = [];

        // If the timeline requires the hidden courses then restrict the result to only $hiddencourses else exclude.
        if ($classification == COURSE_TIMELINE_HIDDEN) {
            $courses = course_get_enrolled_courses_for_logged_in_user(0, $offset, $sort, $fields,
                    COURSE_DB_QUERY_LIMIT, $hiddencourses);
        } else {
            $courses = course_get_enrolled_courses_for_logged_in_user(0, $offset, $sort, $fields,
                    COURSE_DB_QUERY_LIMIT, [], $hiddencourses);
        }

        $favouritecourseids = [];
        $ufservice = \core_favourites\service_factory::get_service_for_user_context(\context_user::instance($USER->id));
        $favourites = $ufservice->find_favourites_by_type('core_course', 'courses');

        if ($favourites) {
            $favouritecourseids = array_map(
                    function($favourite) {
                        return $favourite->itemid;
                    }, $favourites);
        }

        if ($classification == COURSE_FAVOURITES) {
            list($filteredcourses, $processedcount) = course_filter_courses_by_favourites_and_term(
                    $courses,
                    $favouritecourseids,
                    $term,
                    $limit
            );
        } else {
            list($filteredcourses, $processedcount) = course_filter_courses_by_timeline_classification_and_term(
                    $courses,
                    $classification,
                    $term,
                    $limit
            );
        }

        $renderer = $PAGE->get_renderer('core');
        $formattedcourses = array_map(function($course) use ($renderer, $favouritecourseids) {
            context_helper::preload_from_record($course);
            $context = context_course::instance($course->id);
            $isfavourite = false;
            if (in_array($course->id, $favouritecourseids)) {
                $isfavourite = true;
            }
            $exporter = new core_course\external\course_summary_exporter($course,
                    ['context' => $context, 'isfavourite' => $isfavourite]);
            return $exporter->export($renderer);
        }, $filteredcourses);

        return [
                'courses' => $formattedcourses,
                'nextoffset' => $offset + $processedcount
        ];
    }

    /**
     * Returns description of method result value
     *
     * @return external_description
     */
    public static function get_enrolled_courses_by_term_returns() {
        return new external_single_structure(
                array(
                        'courses' => new external_multiple_structure(
                                core_course\external\course_summary_exporter::get_read_structure(), 'Course'),
                        'nextoffset' => new external_value(PARAM_INT, 'Offset for the next request')
                )
        );
    }

}