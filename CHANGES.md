# Changes

## 0.3.1 (2026102204) - 2026-10-22

- Code style updated to pass the current Moodle coding standard (moodle-cs) with no errors or warnings.
- Added PHPUnit tests for the manager and the privacy provider, plus a test data generator.

## 0.3.0 (2026102203) - 2026-10-22

- Added the CPD compliance report: every member's points, categories met and compliance status for a chosen cycle, 50 per page. Requires `local/cpdtracker:viewreport`.
- Added shortcuts to the admin screens and the report on My CPD for users with the matching capabilities.

## 0.2.0 (2026102201) - 2026-10-22

- Added admin screens to add, edit and delete CPD cycles and categories, and to reorder categories. Requires `local/cpdtracker:manage`.
- Cycles cannot overlap and always cover whole days.
- Cycles and categories with logged activities cannot be deleted, and a cycle's dates cannot be changed to exclude existing activities.

## 0.1.0 (2026102200) - 2026-10-22

- First version: CPD cycles with a required points total, categories with optional minimum and maximum points, members log, edit and delete their own activities, and My CPD shows each member's points and compliance status.
- Privacy API support for member activities.
