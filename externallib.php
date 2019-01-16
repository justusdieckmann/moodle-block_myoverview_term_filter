<?php

/**
 * Myoverview term filter external file
 *
 * @package    local_myoverview_term_filter
 * @copyright  2019 Justus Dieckmann <justusdieckmann@wwu.de>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
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
                        'limit' => $limit,
                        'offset' => $offset,
                        'sort' => $sort,
                )
        );

        $term = $params['term'];
        $limit = $params['limit'];
        $offset = $params['offset'];
        $sort = $params['sort'];

        self::validate_context(context_user::instance($USER->id));

        $requiredproperties = core_course\external\course_summary_exporter::define_properties();
        $fields = join(',', array_keys($requiredproperties));
        $hiddencourses = get_hidden_courses_on_timeline();
        $courses = [];

        // If the timeline requires the hidden courses then restrict the result to only $hiddencourses else exclude.

        $courses = course_get_enrolled_courses_for_logged_in_user(0, $offset, $sort, $fields,
                COURSE_DB_QUERY_LIMIT, [], $hiddencourses);

        $favouritecourseids = [];
        $ufservice = \core_favourites\service_factory::get_service_for_user_context(\context_user::instance($USER->id));
        $favourites = $ufservice->find_favourites_by_type('core_course', 'courses');

        if ($favourites) {
            $favouritecourseids = array_map(
                    function($favourite) {
                        return $favourite->itemid;
                    }, $favourites);
        }

        list($filteredcourses, $processedcount) = course_filter_courses_by_term(
                $courses,
                $term,
                $limit
        );

        $renderer = $PAGE->get_renderer('core');
        $formattedcourses = array_map(function($course) use ($renderer, $favouritecourseids) {
            context_helper::preload_from_record($course);
            $context = context_course::instance($course->id);
            $isfavourite = false;
            if (in_array($course->id, $favouritecourseids)) {
                $isfavourite = true;
            }
            $exporter = new core_course\external\course_summary_exporter($course, ['context' => $context, 'isfavourite' => $isfavourite]);
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
                        'courses' => new external_multiple_structure(core_course\external\course_summary_exporter::get_read_structure(), 'Course'),
                        'nextoffset' => new external_value(PARAM_INT, 'Offset for the next request')
                )
        );
    }

}