<?php
/**
 * Plugin Name: SFS-Shield
 * Description: Protects your WordPress site from spam registrations using the StopForumSpam API.
 * Version: 0.1.0
 * Author: Ashish Patel
 * License: GPLv2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 *
 * @package SFS-Shield
 * @since 0.0.1
 */

// Prevent direct access.
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

// Load Composer autoloader.
if ( file_exists( __DIR__ . '/vendor/autoload.php' ) ) {
	require_once __DIR__ . '/vendor/autoload.php';
}

// Initialize the plugin.
new StopForumSpam\RegistrationCheck();
