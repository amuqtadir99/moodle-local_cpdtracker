# CPD Tracker (local_cpdtracker)

Continuing Professional Development (CPD) compliance tracking for Moodle, built for professional bodies and associations.

Members log CPD activities against categories within a cycle. Each member sees their own points total, per-category progress and whether they are compliant. Category caps and minimums are enforced automatically.

## Features

- CPD cycles with a required points total
- Categories per cycle with optional minimum and maximum (cap) points
- Members log, edit and delete their own activities while a cycle is open
- My CPD page: points total, per-category breakdown, compliance status
- Privacy API support (export and delete of member data)

## Requirements

- Moodle 4.5 or later

## Installation

1. Copy this folder to `local/cpdtracker` in your Moodle code. On Moodle 5.1 and later, use `public/local/cpdtracker`.
2. Visit Site administration > Notifications to complete the install.
3. Members reach the plugin from their profile page (My CPD) or at `/local/cpdtracker/index.php`.

## Capabilities

| Capability | Default | Purpose |
|---|---|---|
| `local/cpdtracker:logactivity` | Authenticated user | Log own activities, see own compliance |
| `local/cpdtracker:manage` | Manager | Manage cycles and categories |
| `local/cpdtracker:viewreport` | Manager | View the member compliance report |

## Status

Alpha. Cycle and category management screens are not built yet; create them directly in the `local_cpdtracker_cycle` and `local_cpdtracker_category` tables for now.

## License

GNU GPL v3 or later. See https://www.gnu.org/licenses/gpl-3.0.html.
