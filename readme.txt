=== Daily Editorial Tracker ===
Contributors: lailsonoliveira
Tags: editorial, publishing, posts, reports, newsroom
Requires at least: 6.0
<<<<<<< HEAD
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.0
=======
Tested up to: 6.8
Requires PHP: 7.4
Stable tag: 1.2.0
>>>>>>> 7dc0dd3 (Release 1.2.0 - Editorial goals per author, fix Tested up to)
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Track posts by author, date range, status, and editorial goal, with dashboard summaries and CSV export.

== Description ==

Daily Editorial Tracker gives newsroom and publishing teams a clear overview of editorial activity in WordPress.

Use the report screen to:

* Filter posts by start and end date.
* Filter by published, draft, pending, or scheduled status.
<<<<<<< HEAD
* Set an editorial goal per author.
* See whether each author has reached the selected goal.
=======
* Set and save a different editorial goal for each author.
* See whether each author has reached their saved goal.
>>>>>>> 7dc0dd3 (Release 1.2.0 - Editorial goals per author, fix Tested up to)
* Open the filtered author's posts from the report.
* Export report data as a CSV file.
* View a summary of today's published posts in the WordPress dashboard.

The plugin does not send data to external services and does not track visitors or administrators.

== Installation ==

1. Upload the `daily-editorial-tracker` folder to `/wp-content/plugins/`, or install the ZIP through the WordPress Plugins screen.
2. Activate **Daily Editorial Tracker**.
3. Open **Editorial Tracker** in the WordPress admin menu.

== Frequently Asked Questions ==

= Who can access the editorial report? =

Only administrators and other users with the `manage_options` capability can access the report, dashboard widget, and CSV export.

= Which content types are included? =

The current version reports standard WordPress posts.

= Does the plugin send information to another service? =

No. All report data stays in your WordPress installation.

== Screenshots ==

1. Editorial report with date, status, and author-goal filters.

== Changelog ==

<<<<<<< HEAD
=======
= 1.2.0 =
* Added persistent individual editorial goals for each author.
* Applied each author's saved goal to the report and CSV export.
* Added a responsive author-goal settings panel.
* Fixed dashboard widget links after the report moved to the Tools menu.
* Updated Brazilian Portuguese and Spanish translations.

>>>>>>> 7dc0dd3 (Release 1.2.0 - Editorial goals per author, fix Tested up to)
= 1.1.2 =
* Added Brazilian Portuguese and Spanish translation files.
* Added a POT template to support future translations.

= 1.1.1 =
* Moved the report page under the WordPress Tools menu for better admin hierarchy integration.
* Updated admin asset loading for the new menu location.

= 1.1.0 =
* Renamed the plugin to Daily Editorial Tracker.
* Added full internationalization support.
* Prepared all interface strings for translation through WordPress.org.
* Improved permissions, date validation, and CSV export security.
* Updated plugin headers and WordPress.org documentation.

= 1.0.1 =
* Fixed the admin menu.
* Improved CSV security.

= 1.0.0 =
* Initial release.
<<<<<<< HEAD
=======

== Upgrade Notice ==

= 1.2.0 =
Author goals are now saved individually. Review the new author-goal panel after updating.
>>>>>>> 7dc0dd3 (Release 1.2.0 - Editorial goals per author, fix Tested up to)
