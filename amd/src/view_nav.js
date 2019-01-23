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
 * Manage the timeline view navigation for the overview block.
 *
 * @package    block_myoverview_term_filter
 * @copyright  2018 Bas Brands <bas@moodle.com>
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(
    [
        'jquery',
        'core/custom_interaction_events',
        'block_myoverview_term_filter/repository',
        'block_myoverview_term_filter/view',
        'block_myoverview_term_filter/selectors'
    ],
    function (
        $,
        CustomEvents,
        Repository,
        View,
        Selectors
    ) {

        var SELECTORS = {
            FILTERS: '[data-region="filter"]',
            FILTER_OPTION: '[data-filter]',
            DISPLAY_OPTION: '[data-display-option]',
            TERM_OPTION: '[data-term-option]'
        };

        /**
         * Update the user preference for the block.
         *
         * @param {String} filter The type of filter: display/sort/grouping.
         * @param {String} value The current preferred value.
         */
        var updatePreferences = function (filter, value) {
            var type = null;
            if (filter === 'display') {
                type = 'block_myoverview_term_filter_user_view_preference';
            } else if (filter === 'sort') {
                type = 'block_myoverview_term_filter_user_sort_preference';
            } else {
                type = 'block_myoverview_term_filter_user_grouping_preference';
            }

            Repository.updateUserPreferences({
                preferences: [{
                    type: type,
                    value: value
                }]
            });
        };

        /**
         * Selects the default term for the selected grouping-method
         *
         * @param {object} root The root element for the overview block
         */
        var selectTermForGrouping = function (root) {
            var datahead = root.find(Selectors.courseView.region);
            var termelement = root.find('#termdropdown');

            var oldTerm = datahead.attr('data-term');
            termelement.find('[data-value="' + oldTerm + '"]').removeClass("active");

            var grouping = datahead.attr('data-grouping');
            var term = termelement.attr('data-default-' + grouping);
            datahead.attr('data-term', term);
            termelement.find('[data-value="' + term + '"]').addClass("active");
            termelement.find('#selectedterm').text(termelement.find('[data-value="' + term + '"]').text());

        };

        /**
         * Event listener for the Display filter (cards, list).
         *
         * @param {object} root The root element for the overview block
         */
        var registerSelector = function (root) {

            var Selector = root.find(SELECTORS.FILTERS);

            CustomEvents.define(Selector, [CustomEvents.events.activate]);
            Selector.on(
                CustomEvents.events.activate,
                SELECTORS.FILTER_OPTION,
                function (e, data) {
                    var option = $(e.target);

                    if (option.hasClass('active')) {
                        // If it's already active then we don't need to do anything.
                        return;
                    }

                    var filter = option.attr('data-filter');
                    var pref = option.attr('data-pref');

                    root.find(Selectors.courseView.region).attr('data-' + filter, option.attr('data-value'));

                    selectTermForGrouping(root);

                    updatePreferences(filter, pref);
                    // Reset the views.
                    View.init(root);

                    data.originalEvent.preventDefault();
                }
            );

            CustomEvents.define(Selector, [CustomEvents.events.activate]);
            Selector.on(
                CustomEvents.events.activate,
                SELECTORS.TERM_OPTION,
                function (e, data) {
                    var option = $(e.target);

                    if (option.hasClass('active')) {
                        return;
                    }

                    root.find(Selectors.courseView.region).attr('data-term', option.attr('data-value'));
                    View.init(root);
                    data.originalEvent.preventDefault();
                }
            );

            CustomEvents.define(Selector, [CustomEvents.events.activate]);
            Selector.on(
                CustomEvents.events.activate,
                SELECTORS.DISPLAY_OPTION,
                function (e, data) {
                    var option = $(e.target);

                    if (option.hasClass('active')) {
                        return;
                    }

                    var filter = option.attr('data-display-option');
                    var pref = option.attr('data-pref');

                    root.find(Selectors.courseView.region).attr('data-display', option.attr('data-value'));
                    updatePreferences(filter, pref);
                    View.reset(root);
                    data.originalEvent.preventDefault();
                }
            );
        };

        /**
         * Initialise the timeline view navigation by adding event listeners to
         * the navigation elements.
         *
         * @param {object} root The root element for the myoverview_term_filter block
         */
        var init = function (root) {
            root = $(root);
            registerSelector(root);
            selectTermForGrouping(root);
        };

        return {
            init: init
        };
    });