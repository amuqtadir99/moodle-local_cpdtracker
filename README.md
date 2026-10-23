# CPD Tracker for Moodle

[![Moodle Plugin CI](https://github.com/amuqtadir99/moodle-local_cpdtracker/actions/workflows/ci.yml/badge.svg)](https://github.com/amuqtadir99/moodle-local_cpdtracker/actions/workflows/ci.yml)
[![Moodle 4.5 to 5.3](https://img.shields.io/badge/Moodle-4.5%20to%205.3-f98012?logo=moodle&logoColor=white)](https://moodledev.io/general/releases)
[![PHP 8.1 to 8.4](https://img.shields.io/badge/PHP-8.1--8.4-777bb4?logo=php&logoColor=white)](https://www.php.net/)
[![Release](https://img.shields.io/github/v/tag/amuqtadir99/moodle-local_cpdtracker?label=release&color=brightgreen)](https://github.com/amuqtadir99/moodle-local_cpdtracker/tags)
[![Maturity: stable](https://img.shields.io/badge/maturity-stable-brightgreen)](CHANGES.md)
[![License: GPL v3](https://img.shields.io/badge/license-GPL%20v3-blue)](LICENSE)

**Continuing Professional Development (CPD) compliance tracking for Moodle**, built for professional bodies, associations and regulated workforces that need members to earn and evidence CPD points every cycle.

Members record their CPD activities in Moodle and see, at any time, exactly where they stand: points earned, category requirements met and whether they are compliant. Administrators define the rules once, and the plugin applies them consistently to every member.

Component: `local_cpdtracker`

---

## Highlights

- **CPD cycles.** Define the period members must earn points in, such as a calendar or membership year, and the total points required.
- **Flexible categories.** Use as many categories as you need. Require a minimum number of points in a category (for example, 10 points of structured learning) and cap how many points a category can contribute (for example, no more than 10 points of self-directed reading).
- **Hours or points.** Let members enter hours for a category and convert them to points at your rate, for example 1.5 points per hour, or keep direct points entry.
- **Your own activity form.** Add questions to the log-activity form, such as provider, learning outcomes, cost or a reflection, using short text, paragraph, dropdown, checkbox, date and number fields. Mark any question as required.
- **Quick yearly setup.** Start a new cycle with a copy of an earlier cycle's categories.
- **Member self-service.** Members log, edit and delete their own activities while a cycle is open.
- **Clear progress.** The My CPD page shows each member's total with progress bars, a per-category breakdown with your guidance for each category, and a clear compliant or not-yet-compliant status.
- **Download my CPD record.** Members download their own record as CSV, Excel, ODS, PDF and other formats enabled on your site, including their answers to your questions.
- **Compliance report.** Staff see every member's points and status for any cycle, paged and sorted by surname.
- **Data integrity built in.** Cycles cannot overlap, activities must fall inside their cycle and cannot be future-dated, and cycles or categories that hold member records cannot be deleted.
- **Audit trail.** Changes to activities, cycles and categories are recorded in Moodle's logs.
- **Privacy ready.** Full Moodle Privacy API support, so member data, including answers to your questions, is included in data exports and removed on deletion requests.

## How compliance is calculated

A member is **compliant** for a cycle when both of these are true:

1. Their **counted points** reach the cycle's required total.
2. Every category's **minimum** is met.

Counted points are the points logged in each category, limited by that category's cap. Points logged above a cap are kept on record but do not count towards the total.

In a category that converts hours, an activity's points are its hours multiplied by the category's points-per-hour rate, worked out when the activity is saved.

**Example.** A cycle requires 30 points. *Structured learning* has a minimum of 10 points. *Self-directed* has a cap of 10 points.

| Category | Logged | Counted | Minimum met |
|---|---|---|---|
| Structured learning (min 10) | 12 | 12 | Yes |
| Self-directed (cap 10) | 15 | 10 | Yes |
| **Total** | **27** | **22 of 30** | **Not yet compliant** |

The member needs 8 more points, and only points in Structured learning (or another uncapped category) will move them closer.

## Requirements

CPD Tracker supports every Moodle version that currently receives security updates:

| Moodle | PHP |
|---|---|
| 4.5 LTS | 8.1 to 8.3 |
| 5.1 | 8.2 to 8.4 |
| 5.2 | 8.3 to 8.4 |
| 5.3 LTS | 8.3 to 8.4 |

Any database supported by your Moodle version can be used. Every release is tested automatically on each Moodle version above, with PostgreSQL and MariaDB, at the lowest and highest supported PHP version.

Always run the latest patch release of your PHP version, as PHP security fixes are only shipped in new patch releases.

## Installation

### Option 1: Install from a ZIP file

1. Download the plugin ZIP file.
2. Go to **Site administration > Plugins > Install plugins** and upload the file.
3. If you downloaded the ZIP from GitHub, select **Show more...** and enter `cpdtracker` in **Rename the root directory**. GitHub ZIP files contain a folder named `moodle-local_cpdtracker-main`, which Moodle would otherwise reject.
4. Select **Install plugin from the ZIP file** and follow the prompts.

### Option 2: Install with Git

From your Moodle root folder, on Moodle 5.1 and later:

```bash
git clone https://github.com/amuqtadir99/moodle-local_cpdtracker.git public/local/cpdtracker
```

On Moodle 4.5:

```bash
git clone https://github.com/amuqtadir99/moodle-local_cpdtracker.git local/cpdtracker
```

Then visit **Site administration > Notifications** to complete the installation, or run:

```bash
php admin/cli/upgrade.php
```

### Option 3: Copy the files manually

Copy the plugin files into `public/local/cpdtracker` (Moodle 5.1 and later) or `local/cpdtracker` (Moodle 4.5), then visit **Site administration > Notifications**.

## Getting started

### 1. Create a cycle

Go to **Site administration > Plugins > Local plugins > CPD Tracker > Manage CPD cycles** and select **Add cycle**. Enter:

- a name, for example *2026 CPD year*
- the start and end dates
- the total points members must earn

A cycle runs from the start of its first day to the end of its last day. Cycles cannot overlap, so each member always has one current cycle.

To reuse last year's structure, choose a cycle in **Copy categories from**. The new cycle starts with a copy of every category, including minimums, caps and points-per-hour rates, which you can then adjust.

### 2. Add categories

After saving a new cycle you are taken straight to its categories. For each category, set:

- **Description**: guidance shown to members on My CPD, for example what counts in this category.
- **Minimum points**: the points a member must earn in this category to be compliant. Use 0 for no minimum.
- **Maximum points**: the most points from this category that count towards the total. Use 0 for no cap.
- **Points per hour**: set a rate to let members enter hours instead of points. Use 0 for members to enter points directly. Changing the rate affects activities logged or edited afterwards; points already saved are not recalculated.

Use the arrows to set the order members see the categories in.

### 3. Design the activity form (optional)

Go to **Site administration > Plugins > Local plugins > CPD Tracker > Activity form fields** to add your own questions to the log-activity form. Choose from short text, text area, dropdown menu, checkbox, date and number fields, group them into sections, mark them as required and set their order.

Answers are saved with each activity, shown under the activity on My CPD and included in the member's downloaded record.

### 4. Let members log activities

Members open **My CPD** from their profile page, or go to `/local/cpdtracker/index.php`, and select **Log activity**. Each activity records:

- category
- title and description
- activity date, which must fall inside the cycle and cannot be in the future
- points, or hours for categories that convert hours to points
- answers to any questions you added

Activities can be edited or deleted while the cycle is open. Once a cycle ends, its records are locked.

My CPD shows progress towards the cycle total and each category minimum. Members can download their record for the cycle from the bottom of the page.

### 5. Monitor compliance

Go to **Site administration > Plugins > Local plugins > CPD Tracker > CPD compliance report**, choose a cycle and review each member's counted points, categories met, number of activities and compliance status.

## Permissions

| Capability | Default role | Allows |
|---|---|---|
| `local/cpdtracker:logactivity` | Authenticated user | Logging, editing and deleting own activities, and viewing own compliance |
| `local/cpdtracker:manage` | Manager | Creating, editing and deleting cycles and categories, and designing the activity form |
| `local/cpdtracker:viewreport` | Manager | Viewing every member's points and compliance status |

Users with the `manage` or `viewreport` capability also see shortcuts to those screens on their My CPD page.

The compliance report lists **members**: active, confirmed users who hold `local/cpdtracker:logactivity`. To limit tracking to a specific group, create a role with this capability, assign it to that group, and remove the capability from the Authenticated user role.

## Data rules

To keep CPD records reliable, the plugin enforces these rules:

- Cycles cannot overlap.
- A cycle's dates cannot be changed to exclude activities that have already been logged.
- Cycles and categories that contain logged activities cannot be deleted.
- A category's cap must be 0 (no cap) or at least its minimum.
- Members can only add, change or delete activities while a cycle is open, and only their own.
- Activity dates cannot be in the future.
- To keep numbers realistic, one activity can record at most 10,000 points or hours, required points, minimums and caps can be at most 100,000, and a points-per-hour rate at most 1,000.

## Privacy

CPD Tracker stores the activities members log: category, title, description, date, points, hours and answers to any questions you added. These records belong to the member and are stored in their user context.

The plugin implements the Moodle Privacy API, so member activities and answers are:

- listed in the site's data registry
- included when a member's data is exported
- removed when a member's data is deleted

Cycles, categories and the activity form are site configuration and contain no personal data. The plugin does not send data outside your Moodle site.

Downloaded records are protected against spreadsheet formula injection: text that would start a formula is prefixed so spreadsheets show it as plain text.

## For developers

### Project structure

```
cpdtracker/
├── classes/
│   ├── manager.php            Business logic: points, hours, compliance, cycles, categories, report, export
│   ├── customfield/           Custom fields handler for the activity form
│   ├── event/                 Events for activities, cycles and categories
│   ├── form/                  Activity, cycle and category forms
│   ├── output/                Progress bar helper
│   └── privacy/provider.php   Privacy API implementation
├── db/                        Schema, upgrade and uninstall steps, capabilities
├── lang/en/                   Language strings
├── tests/                     PHPUnit tests and test data generator
├── index.php                  My CPD
├── edit.php                   Log, edit or delete an activity
├── download.php               Download a member's own CPD record
├── customfields.php           Design the activity form
├── manage.php                 List and delete cycles
├── editcycle.php              Add or edit a cycle
├── categories.php             List, reorder and delete a cycle's categories
├── editcategory.php           Add or edit a category
├── report.php                 Compliance report
├── lib.php                    My CPD link on the user's profile page
├── settings.php               Site administration entries
└── version.php                Plugin version and requirements
```

All business logic lives in `classes/manager.php`. Pages only handle input, access checks and output.

### Running the tests

The plugin includes PHPUnit tests for the business logic and the privacy provider. From your Moodle root, initialise the PHPUnit environment, then run the plugin's test suite.

On Moodle 5.1 and later:

```bash
php public/admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite local_cpdtracker_testsuite
```

On Moodle 4.5:

```bash
php admin/tool/phpunit/cli/init.php
vendor/bin/phpunit --testsuite local_cpdtracker_testsuite
```

The tests declare their metadata both as PHPUnit attributes (read by PHPUnit 11 on Moodle 5.x) and as doc-comment annotations (read by PHPUnit 9 on Moodle 4.5).

Tests create data with the plugin's generator:

```php
$generator = $this->getDataGenerator()->get_plugin_generator('local_cpdtracker');
$cycle = $generator->create_cycle(['requiredpoints' => 30]);
```

### Coding standards

The code follows the [Moodle coding style](https://moodledev.io/general/development/policies/codingstyle). On every push and pull request, [GitHub Actions](.github/workflows/ci.yml) runs [moodle-plugin-ci](https://moodlehq.github.io/moodle-plugin-ci/) against each supported Moodle version: PHP lint, the Moodle code checker and PHPDoc checker (no warnings allowed), plugin validation, upgrade savepoints and the PHPUnit tests.

## Support and contributing

- **Report a bug or request a feature:** [open an issue](https://github.com/amuqtadir99/moodle-local_cpdtracker/issues). Please include your Moodle version and the steps to reproduce the problem.
- **Contribute:** pull requests are welcome. Please follow the Moodle coding style and include tests for any change to the business logic.

## Changelog

See [CHANGES.md](CHANGES.md) for the release history.

## License

Copyright 2026 WITWEB SOLUTIONS ([www.witweb.com.au](https://www.witweb.com.au)). Developed by Abdul Muqtadir ([@amuqtadir99](https://github.com/amuqtadir99)).

This program is free software: you can redistribute it and/or modify it under the terms of the GNU General Public License as published by the Free Software Foundation, either version 3 of the License, or (at your option) any later version. See [LICENSE](LICENSE) for the full text.

This program is distributed in the hope that it will be useful, but WITHOUT ANY WARRANTY; without even the implied warranty of MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.
