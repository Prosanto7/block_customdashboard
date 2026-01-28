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
 * JavaScript for child selector and zoom filter in Custom Dashboard block.
 *
 * @module     block_customdashboard/selector
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

define(['jquery'], function($) {
    return {
        init: function() {
            // Child selector for parents.
            $('#child-selector').on('change', function() {
                const selectedChildId = $(this).val();
                const currentUrl = new URL(window.location.href);
                currentUrl.searchParams.set('selectedchild', selectedChildId);
                window.location.href = currentUrl.toString();
            });

            // Zoom filter for students and teachers.
            $('#zoom-filter').on('change', function() {
                const filterValue = $(this).val();
                const container = $('#zoom-classes-container');
                const items = container.find('.zoom-class-item');

                if (filterValue === 'today') {
                    items.each(function() {
                        if ($(this).data('filter') === 'today') {
                            $(this).show();
                        } else {
                            $(this).hide();
                        }
                    });
                } else if (filterValue === 'upcoming') {
                    items.each(function() {
                        if ($(this).data('filter') === 'upcoming') {
                            $(this).show();
                        } else {
                            $(this).hide();
                        }
                    });
                }

                // Check if there are visible items.
                const visibleItems = items.filter(':visible').length;
                const listGroup = container.find('.list-group');

                // First, remove any existing no-data message.
                container.find('.no-data-message').remove();

                if (visibleItems === 0) {
                    listGroup.hide();
                    listGroup.after(
                        '<p class="text-muted no-data-message">No zoom classes found.</p>'
                    );
                } else {
                    listGroup.show();
                }
            });
        }
    };
});
