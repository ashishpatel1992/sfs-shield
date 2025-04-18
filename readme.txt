=== SFS-Shield ===
Contributors: ashishpatel1992
Tags: spam, anti-spam, security, registration, stopforumspam, user management, spam protection
Requires at least: 5.0
Tested up to: 6.6
Stable tag: 0.0.1
Requires PHP: 7.4
License: GPLv2 or later
License URI: https://www.gnu.org/licenses/gpl-2.0.html

Protect your WordPress site from spam registrations with SFS-Shield, powered by the StopForumSpam API.

== Description ==
Say goodbye to spam registrations with **SFS-Shield** (StopForumSpam Shield)! This lightweight WordPress plugin integrates the [StopForumSpam API](https://www.stopforumspam.com/) to block spam users in real-time, ensuring your site remains secure and spam-free. Designed for ease of use, it offers powerful tools to combat bots and suspicious sign-ups, making it a must-have for any WordPress site administrator.

**Key Features:**
- **Real-Time Spam Blocking**: Checks new user registrations against StopForumSpam’s database, using customizable confidence and frequency thresholds to block spammers.
- **Manual IP and Email Checks**: Easily verify specific IPs or emails for spam activity via a user-friendly interface.
- **Blocked Attempts Logging**: Logs details of blocked registrations, including emails, IPs, usernames, and reasons for blocking.
- **Existing User Scanning**: Scans current users to identify potential spammers, with a progress bar for tracking.
- **Simple Setup**: Configure settings in the intuitive **Settings > SFS-Shield** panel.

As the first plugin by [Your Name], **SFS-Shield** combines reliability and simplicity, leveraging the trusted StopForumSpam API to keep your site clean. Install it today to protect your WordPress site from spam!

*Note*: This plugin relies on the external StopForumSpam API, subject to its [usage terms](https://www.stopforumspam.com/usage).

== Installation ==
1. Upload the `sfs-shield` folder to the `/wp-content/plugins/` directory, or install directly through the WordPress Plugins screen.
2. Activate the plugin through the **Plugins** menu in WordPress.
3. Navigate to **Settings > SFS-Shield** to configure confidence and frequency thresholds.
4. Use the **Scan Users** page (via the admin bar) to check existing users for spam.

== Frequently Asked Questions ==
= Does this plugin require an API key? =
No, the StopForumSpam API is free for basic usage and does not require an API key. For high-traffic sites, review StopForumSpam’s [usage terms](https://www.stopforumspam.com/usage).

= What are the confidence and frequency thresholds? =
The **confidence threshold** (0–100%) determines the likelihood a user is a spammer, based on StopForumSpam’s data. The **frequency threshold** sets the minimum number of appearances in their database to flag a user. Adjust these in the plugin settings.

= Can I scan existing users? =
Yes! The **Scan Users** feature checks all users against the StopForumSpam database, with a progress bar to monitor progress.

= Is this plugin GDPR-compliant? =
The plugin sends email addresses and IP addresses to the StopForumSpam API and logs blocked attempts locally. Ensure you inform users about data processing and comply with privacy laws like GDPR. See the Privacy section below.

== Screenshots ==
1. **Settings Page**: Configure confidence and frequency thresholds to customize spam detection.
2. **Manual Check Interface**: Verify IPs or emails for spam activity with a simple form.
3. **Scan Users Page**: Scan existing users and review spam detection results.
4. **Blocked Attempts Log**: View detailed logs of blocked registration attempts.

== Changelog ==
= 0.1.0 =
* Added AJAX-based user deletion for seamless spammer removal.
* Improved scan results table to update dynamically after deletions.
* Enhanced JavaScript for better performance in scanning and deletion.

= 0.0.1 =
* Initial release with real-time spam blocking, manual IP/email checks, blocked attempts logging, and user scanning.

== Upgrade Notice ==
= 0.1.0 =
This update introduces AJAX-based user deletion and dynamic table updates for a smoother experience. Update to enhance spam management.

= 0.0.1 =
Initial release. Install to start protecting your site from spam registrations.

== Privacy ==
SFS-Shield sends user email addresses and IP addresses to the StopForumSpam API to detect spam. Blocked registration attempts are logged locally in your WordPress database. To comply with privacy regulations like GDPR, inform your users about this data processing and obtain necessary consents.