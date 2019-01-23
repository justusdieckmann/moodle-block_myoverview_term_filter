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
 * Contains the class for the My overview block.
 *
 * @package    block_myoverview_term_filter
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

use block_myoverview_term_filter\util\filter_helper;

/**
 * My overview block class.
 *
 * @package    block_myoverview_term_filter
 * @copyright  Mark Nelson <markn@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class block_myoverview_term_filter extends block_base {

    /**
     * Init.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_myoverview_term_filter');
    }

    /**
     * Returns the contents.
     *
     * @return stdClass contents of block
     */
    public function get_content() {
        if (isset($this->content)) {
            return $this->content;
        }

        list($terms, $defaults) = filter_helper::get_semester_by_user();
        $group = get_user_preferences('block_myoverview_term_filter_user_grouping_preference');
        $sort = get_user_preferences('block_myoverview_term_filter_user_sort_preference');
        $view = get_user_preferences('block_myoverview_term_filter_user_view_preference');
        $paging = get_user_preferences('block_myoverview_term_filter_user_paging_preference');

        $renderable = new \block_myoverview_term_filter\output\main($group, $sort, $view, $paging, $terms, $defaults);
        $renderer = $this->page->get_renderer('block_myoverview_term_filter');

        $this->content = new stdClass();
        $this->content->text = $renderer->render($renderable);
        $this->content->footer = '';

        return $this->content;
    }

    /**
     * Locations where block can be displayed.
     *
     * @return array
     */
    public function applicable_formats() {
        return array('my' => true);
    }
}
