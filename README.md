# SFS Shield aka Stop Forum Spam Shield


## Description

SFS-Shield (StopForumSpam Shield) is a WordPress plugin that protects your site from spam registrations by integrating with the [StopForumSpam API](https://www.stopforumspam.com/). This lightweight plugin blocks spam users in real-time, offers manual IP and email checks, logs blocked attempts, and scans existing users for spam. Designed for simplicity and reliability, it’s perfect for WordPress administrators looking to keep their site secure.


- 🛡️ **Real-Time Spam Blocking:** Automatically checks new registrations against StopForumSpam’s database, using customizable confidence and frequency thresholds.
- 🕵️‍♂️ **Manual Checks:** Verify IPs or emails for spam activity with a simple interface.
- 📝 **Blocked Attempts Logging:** Records emails, IPs, usernames, and reasons for blocked registrations.
- 🧹 **User Scanning:** Scans existing users to identify spammers, with a progress bar for tracking.
- ⚡ **Easy Configuration:** Adjust settings via Settings > SFS-Shield in the WordPress admin.

Note: This plugin relies on the external StopForumSpam API, subject to its [usage terms](https://www.stopforumspam.com/usage).

---

## Installation

1. Download the plugin from the [WordPress Plugin Directory](https://wordpress.org/plugins/) (coming soon) or this repository.
2. Upload the `sfs-shield` folder to `/wp-content/plugins/` or install via the WordPress Plugins screen.
3. Activate the plugin through the Plugins menu in WordPress.
4. Go to **Settings > SFS-Shield** to configure thresholds.
5. Access the **Scan Users** page via the admin bar to check existing users.

---

## Frequently Asked Questions

### Does this plugin require an API key?

No. The StopForumSpam API is free and does not require an API key for basic usage.

### What are the confidence and frequency thresholds?

- **Confidence** (0–100%) reflects the likelihood a user is a spammer.
- **Frequency** is the minimum number of times the IP/email appears in the StopForumSpam database.

---

<!-- ## Screenshots
TODO:
1. **Settings page** for configuring thresholds.
2. **Scan Users** page with spam detection results.
3. **Manual check** interface for IPs and emails. -->

---

## Changelog

### 0.1.0
- Added AJAX-based user deletion with UI messages.
- Updated scan results to reflect deletions dynamically.
- Improved JavaScript for scan and delete actions.
- Initial release.

---

## Privacy Notice

This plugin sends user **IP addresses** and **email addresses** to the [StopForumSpam API](https://www.stopforumspam.com/) to check for spam.  
No personal data is stored beyond logs of blocked attempts.  
Ensure your site's usage complies with **GDPR** or other privacy regulations.

---

## Development Setup

To contribute or test the plugin, set up a local development environment.

---

### Prerequisites

- PHP 7.4 or higher  
- WordPress 5.0 or higher (tested up to 6.6)  
- Composer  
- WP-CLI (recommended for testing)  
- Node.js (optional, for JavaScript linting)

---

### Steps

1. **Clone the Repository**

    ```bash
    git clone https://github.com/[your-username]/sfs-shield.git
    cd sfs-shield
    ```
2. **Install Dependencies**:
    ```bash
    composer install
    ```

3. **Set Up WordPress Test Environment**:
    ```bash
    wp scaffold plugin-tests sfs-shield
    ```
    Configure `tests/wp-tests-config.php` with your test database settings.
4. **Run Tests**:
    ```bash
    composer test
    ```
4. **Check Coding Standards**:
    ```bash
    composer lint
    ```
4. **Fix issues**:
    ```bash
    composer fix
    ```
4. **Test Compatibility:** Use `wp-env` for multiple WordPress versions:
    ```bash
    npm install -g @wordpress/env
    wp-env start --wp-version=6.6
    wp-env start --wp-version=5.0
    ```
## Contributing
Contributions are welcome! Follow these steps:

1. Fork the repository.
2. Create a feature branch:
    ```bash
    git checkout -b feature/your-feature
    ```
3. Commit changes:
    ```bash
    git commit -m "Add your feature"
    ```
4. Push to your fork:
    ```bash
    git push origin feature/your-feature
    ```
5. Open a pull request.

Ensure code follows [WordPress Coding Standards](https://developer.wordpress.org/coding-standards/) and includes tests.
## License

This plugin is licensed under the [GNU General Public License v2.0 or later](https://www.gnu.org/licenses/gpl-2.0.html).

## Credits
- Built by Ashish Patel, a WordPress plugin developer and enthusiast.
Powered by the [StopForumSpam API](https://www.stopforumspam.com/).
Uses [Composer](https://getcomposer.org/) and [PHPUnit](https://phpunit.de/) for development.

## Support
- WordPress.org: Support forum (available after plugin approval).
- GitHub Issues: Report bugs or suggest features at GitHub Issues.
- Contact: [ashishpatel.dev](https://ashishpatel.dev).

Thank you for using SFS-Shield! Let’s keep WordPress spam-free!