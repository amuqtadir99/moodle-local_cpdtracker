# CPD Tracker (local_cpdtracker)

Continuing Professional Development (CPD) compliance tracking for Moodle, built for professional bodies and associations.

Members log CPD activities against categories within a cycle. Each member sees their own points total, per-category progress and whether they are compliant. Category caps and minimums are enforced automatically.

## Features

- CPD cycles with a required points total
- Categories per cycle with optional minimum and maximum (cap) points
- Members log, edit and delete their own activities while a cycle is open
- My CPD page: points total, per-category breakdown, compliance status
- Admin screens for cycles and categories
- Compliance report listing every member's points and status
- Privacy API support (export and delete of member data)

## Requirements

- Moodle 4.5 (LTS)

## Installation

1. Copy this folder to `local/cpdtracker` in your Moodle code.
2. Visit Site administration > Notifications to complete the install.
3. Members reach the plugin from their profile page (My CPD) or at `/local/cpdtracker/index.php`.

## Capabilities

| Capability | Default | Purpose |
|---|---|---|
| `local/cpdtracker:logactivity` | Authenticated user | Log own activities, see own compliance |
| `local/cpdtracker:manage` | Manager | Manage cycles and categories |
| `local/cpdtracker:viewreport` | Manager | View the member compliance report |

## Setting up cycles

1. Go to Site administration > Plugins > Local plugins > CPD Tracker > Manage CPD cycles. Users with `local/cpdtracker:manage` also get a Manage CPD cycles button on My CPD.
2. Add a cycle: name, start and end dates, and the total points members need. Cycles cannot overlap. A cycle runs from the start of its first day to the end of its last day.
3. Add categories to the cycle, each with an optional minimum and an optional cap (maximum counted points). Reorder them with the arrows.

Cycles and categories that already have logged activities cannot be deleted, and a cycle's dates cannot be changed to exclude existing activities.

## Compliance report

Site administration > Plugins > Local plugins > CPD Tracker > CPD compliance report lists every member with their points, categories met and compliance status for a chosen cycle, 50 per page, sorted by surname. Members are active users who hold `local/cpdtracker:logactivity`. Requires `local/cpdtracker:viewreport`.

## Running the tests

PHPUnit tests live in `tests/`. From your Moodle root, after initialising the PHPUnit environment (`php admin/tool/phpunit/cli/init.php`):

```
vendor/bin/phpunit --testsuite local_cpdtracker_testsuite
```

Test data is created with the plugin generator in `tests/generator/lib.php` (`$this->getDataGenerator()->get_plugin_generator('local_cpdtracker')`).

## Source and support

- Source code: https://github.com/amuqtadir99/moodle-local_cpdtracker
- Report a bug or request a feature: https://github.com/amuqtadir99/moodle-local_cpdtracker/issues

Developed by Abdul Muqtadir at WITWEB SOLUTIONS (https://www.witweb.com.au).

## License

GNU GPL v3 or later. See [LICENSE](LICENSE).
