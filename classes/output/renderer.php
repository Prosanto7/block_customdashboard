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
 * Renderer for Custom Dashboard block.
 *
 * @package    block_customdashboard
 * @copyright  2026
 * @license    http://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */

namespace block_customdashboard\output;

defined('MOODLE_INTERNAL') || die();

use plugin_renderer_base;
use completion_info;
use grade_grade;
use grade_item;

/**
 * Renderer class for Custom Dashboard block.
 */
class renderer extends plugin_renderer_base {

    /**
     * Render the dashboard content.
     *
     * @param array $children Array of child users
     * @param int $selectedchildid Selected child ID
     * @return string HTML content
     */
    public function render_dashboard($children, $selectedchildid) {
        global $DB, $PAGE;

        // Prepare children for selector.
        $childrenoptions = [];
        foreach ($children as $child) {
            $childrenoptions[] = [
                'id' => $child->id,
                'fullname' => fullname($child),
                'selected' => ($child->id == $selectedchildid),
            ];
        }

        // Get courses for selected child.
        $courses = $this->get_child_courses($selectedchildid);

        $data = [
            'children' => $childrenoptions,
            'haschildren' => !empty($childrenoptions),
            'courses' => $courses,
            'hascourses' => !empty($courses),
        ];

        // Initialize JavaScript module.
        $PAGE->requires->js_call_amd('block_customdashboard/selector', 'init');
        $PAGE->requires->js_call_amd('block_customdashboard/modals', 'init');

        return $this->render_from_template('block_customdashboard/dashboard', $data);
    }

    /**
     * Get courses with progress, grades, and activity completion for a child.
     *
     * @param int $childid Child user ID
     * @return array Array of course data
     */
    private function get_child_courses($childid) {
        global $DB, $CFG;

        require_once($CFG->libdir . '/completionlib.php');
        require_once($CFG->libdir . '/gradelib.php');

        $courses = enrol_get_users_courses($childid, true, ['id', 'fullname', 'shortname', 'visible', 'enablecompletion']);

        $coursedata = [];

        foreach ($courses as $course) {
            if (!$course->visible) {
                continue;
            }

            $coursecontext = \context_course::instance($course->id);
            $courseurl = new \moodle_url('/course/view.php', ['id' => $course->id]);

            // Get course progress.
            $progress = $this->get_course_progress($course, $childid);

            // Get course grade.
            $gradeinfo = $this->get_course_grade($course, $childid);

            // Get activity completion.
            $activitycompletion = $this->get_activity_completion($course, $childid);

            // Get activity list.
            $activities = $this->get_activity_list($course, $childid);

            // Get grades list.
            $gradeslist = $this->get_grades_list($course, $childid);

            $coursedata[] = [
                'id' => $course->id,
                'fullname' => format_string($course->fullname, true, ['context' => $coursecontext]),
                'url' => $courseurl->out(false),
                'progress' => $progress['percentage'],
                'progresstext' => $progress['text'],
                'progressclass' => $progress['class'],
                'grade' => $gradeinfo['grade'],
                'gradetext' => $gradeinfo['text'],
                'gradeclass' => $gradeinfo['class'],
                'grademax' => $gradeinfo['grademax'],
                'activitycompleted' => $activitycompletion['completed'],
                'activitytotal' => $activitycompletion['total'],
                'activitypercentage' => $activitycompletion['percentage'],
                'activityclass' => $activitycompletion['class'],
                'activities' => $activities,
                'hasactivities' => !empty($activities),
                'gradeslist' => $gradeslist,
                'hasgradeslist' => !empty($gradeslist),
                'finalgrade' => $gradeinfo['grade'],
                'finalgradetext' => $gradeinfo['text'],
            ];
        }

        return $coursedata;
    }

    /**
     * Get course progress percentage.
     *
     * @param object $course Course object
     * @param int $userid User ID
     * @return array Progress data
     */
    private function get_course_progress($course, $userid) {
        $completion = new completion_info($course);

        if (!$completion->is_enabled()) {
            return [
                'percentage' => 0,
                'text' => get_string('notstarted', 'block_customdashboard'),
                'class' => 'bg-secondary',
            ];
        }

        $percentage = (int) \core_completion\progress::get_course_progress_percentage($course, $userid);

        if ($percentage === null) {
            $percentage = 0;
        }

        $text = get_string('notstarted', 'block_customdashboard');
        $class = 'bg-secondary';

        if ($percentage > 0 && $percentage < 100) {
            $text = get_string('inprogress', 'block_customdashboard');
            $class = 'bg-warning';
        } else if ($percentage == 100) {
            $text = get_string('completed', 'block_customdashboard');
            $class = 'bg-success';
        }

        return [
            'percentage' => $percentage,
            'text' => $text,
            'class' => $class,
        ];
    }

    /**
     * Get course grade.
     *
     * @param object $course Course object
     * @param int $userid User ID
     * @return array Grade data
     */
    private function get_course_grade($course, $userid) {
        global $CFG;

        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/querylib.php');

        $gradeitem = grade_item::fetch_course_item($course->id);

        if (!$gradeitem) {
            return [
                'grade' => '-',
                'text' => '-',
                'class' => 'bg-secondary',
            ];
        }

        $grade = new grade_grade(['itemid' => $gradeitem->id, 'userid' => $userid]);
        $grade->grade_item = $gradeitem;

        $finalgrade = $grade->finalgrade;

        if ($finalgrade === null) {
            return [
                'grade' => '-',
                'text' => '-',
                'class' => 'bg-secondary',
            ];
        }

        $gradetext = grade_format_gradevalue($finalgrade, $gradeitem, true, GRADE_DISPLAY_TYPE_REAL);

        // Determine grade class based on percentage.
        $percentage = 0;
        if ($gradeitem->grademax > 0) {
            $percentage = ($finalgrade / $gradeitem->grademax) * 100;
        }

        $class = 'bg-danger';
        if ($percentage >= 70) {
            $class = 'bg-success';
        } else if ($percentage >= 50) {
            $class = 'bg-warning';
        }

        return [
            'grade' => round($gradetext, 2),
            'text' => round($percentage, 1) . '%',
            'class' => $class,
            'grademax' => round($gradeitem->grademax, 2)
        ];
    }

    /**
     * Get activity completion statistics.
     *
     * @param object $course Course object
     * @param int $userid User ID
     * @return array Activity completion data
     */
    private function get_activity_completion($course, $userid) {
        $completion = new completion_info($course);

        if (!$completion->is_enabled()) {
            return [
                'completed' => 0,
                'total' => 0,
                'percentage' => 0,
                'class' => 'bg-secondary',
            ];
        }

        $modinfo = get_fast_modinfo($course, $userid);
        $completed = 0;
        $total = 0;

        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible) {
                continue;
            }

            if ($completion->is_enabled($cm) != COMPLETION_TRACKING_NONE) {
                $total++;
                $completiondata = $completion->get_data($cm, false, $userid);
                if ($completiondata->completionstate == COMPLETION_COMPLETE ||
                    $completiondata->completionstate == COMPLETION_COMPLETE_PASS) {
                    $completed++;
                }
            }
        }

        $percentage = $total > 0 ? round(($completed / $total) * 100, 1) : 0;

        $class = 'bg-secondary';
        if ($percentage > 0 && $percentage < 100) {
            $class = 'bg-warning';
        } else if ($percentage == 100) {
            $class = 'bg-success';
        }

        return [
            'completed' => $completed,
            'total' => $total,
            'percentage' => $percentage,
            'class' => $class,
        ];
    }

    /**
     * Get activity list with completion status.
     *
     * @param object $course Course object
     * @param int $userid User ID
     * @return array Activity list
     */
    private function get_activity_list($course, $userid) {
        $completion = new completion_info($course);
        $activities = [];

        if (!$completion->is_enabled()) {
            return $activities;
        }

        $modinfo = get_fast_modinfo($course, $userid);

        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible) {
                continue;
            }

            if ($completion->is_enabled($cm) != COMPLETION_TRACKING_NONE) {
                $completiondata = $completion->get_data($cm, false, $userid);
                $iscompleted = ($completiondata->completionstate == COMPLETION_COMPLETE ||
                    $completiondata->completionstate == COMPLETION_COMPLETE_PASS);

                $activities[] = [
                    'name' => format_string($cm->name, true, ['context' => $cm->context]),
                    'type' => get_string('modulename', $cm->modname),
                    'completed' => $iscompleted,
                    'completedtext' => $iscompleted ? 
                        get_string('completed', 'block_customdashboard') : 
                        get_string('notcompleted', 'block_customdashboard'),
                    'completedclass' => $iscompleted ? 'badge-success' : 'badge-secondary',
                ];
            }
        }

        return $activities;
    }

    /**
     * Get grades list for all activities.
     *
     * @param object $course Course object
     * @param int $userid User ID
     * @return array Grades list
     */
    private function get_grades_list($course, $userid) {
        global $CFG;

        require_once($CFG->libdir . '/gradelib.php');
        require_once($CFG->dirroot . '/grade/querylib.php');

        $gradeslist = [];
        $modinfo = get_fast_modinfo($course, $userid);

        foreach ($modinfo->get_cms() as $cm) {
            if (!$cm->uservisible) {
                continue;
            }

            $gradeitem = grade_item::fetch([
                'itemtype' => 'mod',
                'itemmodule' => $cm->modname,
                'iteminstance' => $cm->instance,
                'courseid' => $course->id,
            ]);

            if ($gradeitem) {
                $grade = new grade_grade(['itemid' => $gradeitem->id, 'userid' => $userid]);
                $grade->grade_item = $gradeitem;

                $gradevalue = $grade->finalgrade;
                $gradetext = get_string('na', 'block_customdashboard');
                $isscale = false;
                $scaleitems = [];
                $achievedscale = '';
                $grademax = 0;

                if ($gradevalue !== null && $gradevalue !== false) {
                    // Check if it's a scale or numeric grade.
                    if ($gradeitem->gradetype == GRADE_TYPE_SCALE) {
                        $isscale = true;
                        // Get scale items.
                        $scale = $gradeitem->load_scale();
                        if ($scale) {
                            $scaleitems = explode(',', $scale->scale);
                            // Get achieved scale item (grade value is 1-based index).
                            $scaleindex = intval($gradevalue) - 1;
                            if (isset($scaleitems[$scaleindex])) {
                                $achievedscale = trim($scaleitems[$scaleindex]);
                                $gradetext = $achievedscale;
                            }
                            // Clean scale items.
                            $scaleitems = array_map('trim', $scaleitems);
                        }
                    } else {
                        // Numeric grade.
                        $gradetext = grade_format_gradevalue($gradevalue, $gradeitem, true, GRADE_DISPLAY_TYPE_REAL);
                        $grademax = $gradeitem->grademax;
                    }
                }

                $gradeslist[] = [
                    'name' => format_string($cm->name, true, ['context' => $cm->context]),
                    'grade' => $gradetext,
                    'hasgrade' => ($gradevalue !== null && $gradevalue !== false),
                    'isscale' => $isscale,
                    'scaleitems' => $scaleitems,
                    'achievedscale' => $achievedscale,
                    'grademax' => $grademax,
                    'gradevalue' => $gradevalue,
                ];
            }
        }

        return $gradeslist;
    }
}
