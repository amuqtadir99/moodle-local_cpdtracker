<?php
// This file is part of Moodle - https://moodle.org/
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
// along with Moodle.  If not, see <https://www.gnu.org/licenses/>.

namespace local_cpdtracker\output;

use html_writer;

/**
 * Small output helpers for CPD Tracker pages.
 *
 * @package    local_cpdtracker
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 */
class helper {
    /**
     * Percentage of a target reached, from 0 to 100.
     *
     * A target of 0 or less counts as already reached.
     *
     * @param float $value
     * @param float $target
     * @return int
     */
    public static function percent(float $value, float $target): int {
        if ($target <= 0) {
            return 100;
        }
        return (int)max(0, min(100, floor($value / $target * 100)));
    }

    /**
     * Accessible Bootstrap progress bar.
     *
     * @param float $value Points counted.
     * @param float $target Points needed.
     * @param string $label Accessible label, for example the category name.
     * @return string HTML
     */
    public static function progress_bar(float $value, float $target, string $label): string {
        $percent = self::percent($value, $target);
        $text = get_string('percentreached', 'local_cpdtracker', $percent);
        $bar = html_writer::div($text, 'progress-bar ' . ($percent >= 100 ? 'bg-success' : 'bg-warning text-dark'), [
            'role' => 'progressbar',
            'style' => 'width: ' . max($percent, 8) . '%',
            'aria-valuenow' => $percent,
            'aria-valuemin' => 0,
            'aria-valuemax' => 100,
            'aria-label' => $label,
        ]);
        return html_writer::div($bar, 'progress', ['style' => 'height: 1.25rem; min-width: 8rem']);
    }
}
