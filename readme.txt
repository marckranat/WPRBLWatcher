=== WP RBL Watcher ===
Contributors: marckranat
Tags: rbl, blacklist, monitoring, ip, spam
Requires at least: 5.0
Tested up to: 6.4
Stable tag: 1.0.0
Requires PHP: 7.4
License: Free for any use

Monitor IP addresses against Real-time Blackhole Lists (RBLs). Track up to 250 IPs per user with daily/weekly reports.

== Description ==

WP RBL Watcher is a comprehensive Real-time Blackhole List (RBL) monitoring system for WordPress. It allows you to monitor up to 250 IP addresses per user account against multiple reliable RBLs.

**Features:**

* Monitor up to 250 IP addresses per user
* Check against 26+ reliable RBLs (based on rblmon.com, excluding false-positive prone lists)
* Automatic rate limiting to respect RBL query policies
* Daily or weekly email reports
* Reports show only blacklisted IPs, sorted by number of listings
* CSV export available
* Manual check option
* WordPress-native integration

**RBLs Monitored:**

The plugin monitors reliable RBLs from rblmon.com's list, excluding those known for false positives:
* Barracuda
* SpamCop
* SORBS (multiple lists)
* SpamRats
* Spamhaus (XBL, ZEN)
* DroneBL
* And many more...

UCEPROTECT and other false-positive prone RBLs have been excluded for accuracy.

== Installation ==

1. Upload the plugin files to the `/wp-content/plugins/wprbl-watcher` directory, or install the plugin through the WordPress plugins screen directly.
2. Activate the plugin through the 'Plugins' screen in WordPress.
3. The plugin will automatically create the necessary database tables upon activation.
4. Navigate to 'RBL Monitor' in the WordPress admin menu to start adding IP addresses.

== Frequently Asked Questions ==

= How many IPs can I monitor? =

Up to 250 IP addresses per user account.

= How often are checks performed? =

You can run manual checks anytime, or set up WordPress cron to run automatic checks. The plugin includes scheduled events that can be configured.

= Are email reports sent automatically? =

Yes, if you enable email notifications in preferences. Reports can be sent daily or weekly based on your preference.

= Which RBLs are checked? =

The plugin checks against 26+ reliable RBLs from rblmon.com's monitored list, excluding RBLs known for false positives like UCEPROTECT.

= How do I enable debug logging? =

To enable detailed debug logging for troubleshooting RBL lookups, add this line to your `wp-config.php` file:

`define('WPRBL_DEBUG', true);`

Debug logs will be written to `wp-content/wprbl-debug.log` and include:
* DNS lookup queries
* DNS response details
* Validation steps
* Final listing status

This is useful for diagnosing issues with specific RBLs or IP addresses. Remember to disable debug logging in production by removing or setting the constant to `false`.

== Screenshots ==

1. Dashboard showing IP addresses and status
2. Blacklisted IPs report
3. Preferences configuration

== Changelog ==

= 1.0.0 =
* Initial release
* Monitor up to 250 IPs per user
* Check against 26+ reliable RBLs
* Daily/weekly email reports
* CSV export
* WordPress admin interface

== Upgrade Notice ==

= 1.0.0 =
Initial release of WP RBL Watcher.

