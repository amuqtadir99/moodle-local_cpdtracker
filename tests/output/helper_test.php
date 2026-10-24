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

use basic_testcase;

#[\PHPUnit\Framework\Attributes\CoversClass(helper::class)]
/**
 * Tests for the output helper (progress bars).
 *
 * @package    local_cpdtracker
 * @category   test
 * @copyright  2026 | WITWEB SOLUTIONS | www.witweb.com.au | Developer: Abdul Muqtadir | Github: amuqtadir99
 * @license    https://www.gnu.org/copyleft/gpl.html GNU GPL v3 or later
 * @covers     \local_cpdtracker\output\helper
 */
final class helper_test extends basic_testcase {
    /**
     * Percentage cases.
     *
     * @return array
     */
    public static function percent_provider(): array {
        return [
            'nothing yet' => [0, 30, 0],
            'part way, rounded down' => [10, 30, 33],
            'exactly met' => [30, 30, 100],
            'over the target' => [120, 30, 100],
            'no target' => [0, 0, 100],
            'negative value' => [-5, 30, 0],
        ];
    }

    /**
     * Test percent().
     *
     * @dataProvider percent_provider
     * @param float $value
     * @param float $target
     * @param int $expected
     */
    #[\PHPUnit\Framework\Attributes\DataProvider('percent_provider')]
    public function test_percent(float $value, float $target, int $expected): void {
        $this->assertSame($expected, helper::percent($value, $target));
    }

    public function test_progress_bar_is_accessible_and_escaped(): void {
        $html = helper::progress_bar(15, 30, 'R&D <script>');

        $this->assertStringContainsString('role="progressbar"', $html);
        $this->assertStringContainsString('aria-valuenow="50"', $html);
        $this->assertStringContainsString('aria-valuemin="0"', $html);
        $this->assertStringContainsString('aria-valuemax="100"', $html);
        $this->assertStringContainsString('aria-label="R&amp;D &lt;script&gt;"', $html);
        $this->assertStringNotContainsString('<script>', $html);
        $this->assertStringContainsString('bg-warning', $html);
        $this->assertStringContainsString('bg-success', helper::progress_bar(30, 30, 'Done'));
    }
}
