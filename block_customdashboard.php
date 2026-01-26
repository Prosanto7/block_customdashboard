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
 * Custom Dashboard block.
 *
 * @package    block_customdashboard
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

defined('MOODLE_INTERNAL') || die();

require_once($CFG->dirroot . '/local/parentmanager/lib.php');

/**
 * Custom Dashboard block class.
 */
class block_customdashboard extends block_base {

    /**
     * Initialize the block.
     */
    public function init() {
        $this->title = get_string('pluginname', 'block_customdashboard');
    }

    /**
     * Multiple instances not allowed.
     *
     * @return bool
     */
    public function instance_allow_multiple() {
        return false;
    }

    /**
     * Which page types this block may appear on.
     *
     * @return array
     */
    public function applicable_formats() {
        return [
            'my' => true,
            'all' => false,
        ];
    }

    /**
     * Return true if content should be displayed.
     *
     * @return bool
     */
    public function has_config() {
        return false;
    }

    /**
     * Get the block content.
     *
     * @return stdClass
     */
    public function get_content() {
        global $USER, $PAGE;

        if ($this->content !== null) {
            return $this->content;
        }

        $this->content = new stdClass();
        $this->content->text = '';
        $this->content->footer = '';

        // Get children for the current user.
        $children = local_parentmanager_get_children($USER->id);

        if (empty($children)) {
            $this->content->text = html_writer::div(
                get_string('nochildrenassigned', 'block_customdashboard'),
                'alert alert-info'
            );
            return $this->content;
        }

        // Get selected child from session or use first child.
        $selectedchildid = optional_param('selectedchild', 0, PARAM_INT);
        if ($selectedchildid && isset($children[$selectedchildid])) {
            // Store in user preference.
            set_user_preference('block_customdashboard_selectedchild', $selectedchildid);
        } else {
            $selectedchildid = get_user_preferences('block_customdashboard_selectedchild', 0);
            if (!$selectedchildid || !isset($children[$selectedchildid])) {
                // Get first child.
                $selectedchildid = reset($children)->id;
                set_user_preference('block_customdashboard_selectedchild', $selectedchildid);
            }
        }

        // Get renderer.
        $renderer = $PAGE->get_renderer('block_customdashboard');
        $this->content->text = $renderer->render_dashboard($children, $selectedchildid);

        return $this->content;
    }
}
