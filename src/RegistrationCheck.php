<?php
/**
 * Main plugin class for SFS-Shield.
 *
 * @package SFS-Shield
 * @since 0.0.1
 */
namespace SFSShield;

class RegistrationCheck {
	private $api_url = 'http://api.stopforumspam.org/api';
	private $confidence_threshold;
	private $frequency_threshold;

	public function __construct() {
		// Load settings or set defaults
		$this->confidence_threshold = get_option( 'sfs_confidence_threshold', 80 );
		$this->frequency_threshold  = get_option( 'sfs_frequency_threshold', 1 );

		// Hook into WordPress registration
		add_filter( 'registration_errors', array( $this, 'check_spam_registration' ), 10, 3 );

		// Add admin settings and scan page
		add_action( 'admin_menu', array( $this, 'add_admin_pages' ) );
		add_action( 'admin_init', array( $this, 'register_settings' ) );

		// Handle manual check
		add_action( 'admin_post_sfs_manual_check', array( $this, 'handle_manual_check' ) );

		// Handle user deletion (fallback for non-AJAX)
		add_action( 'admin_post_sfs_delete_users', array( $this, 'handle_delete_users' ) );
		add_action( 'admin_get_sfs_delete_users', array( $this, 'handle_delete_users' ) );

		// AJAX handlers
		add_action( 'wp_ajax_sfs_scan_users', array( $this, 'ajax_scan_users' ) );
		add_action( 'wp_ajax_sfs_delete_users', array( $this, 'ajax_delete_users' ) );

		// Add admin bar menu
		add_action( 'admin_bar_menu', array( $this, 'add_admin_bar_menu' ), 100 );

		// Create database table on activation
		register_activation_hook( __FILE__, array( $this, 'create_blocked_log_table' ) );

		// Enqueue scripts
		add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_scripts' ) );
	}

	/**
	 * Enqueue scripts for scan page
	 */
	public function enqueue_scripts( $hook ) {
		if ( strpos( $hook, 'sfs-scan-users' ) === false ) {
			return;
		}
		$js_path  = plugin_dir_path( __FILE__ ) . 'sfs-scan.js';
		$css_path = plugin_dir_path( __FILE__ ) . 'sfs-scan.css';

		if ( file_exists( $js_path ) ) {
			wp_enqueue_script( 'sfs-scan-script', plugin_dir_url( __FILE__ ) . 'sfs-scan.js', array( 'jquery' ), '1.0.3', true );
			wp_localize_script(
				'sfs-scan-script',
				'sfsScan',
				array(
					'ajax_url'   => admin_url( 'admin-ajax.php' ),
					'nonce'      => wp_create_nonce( 'sfs_delete_users' ),
					'scan_nonce' => wp_create_nonce( 'sfs_scan_users' ),
				)
			);
		}
		if ( file_exists( $css_path ) ) {
			wp_enqueue_style( 'sfs-scan-style', plugin_dir_url( __FILE__ ) . 'sfs-scan.css', array(), '1.0.1' );
		}
	}

	/**
	 * Create database table for blocked logs
	 */
	public function create_blocked_log_table() {
		global $wpdb;
		$table_name      = $wpdb->prefix . 'sfs_blocked_log';
		$charset_collate = $wpdb->get_charset_collate();

		$sql = "CREATE TABLE $table_name (
            id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
            email VARCHAR(255) NOT NULL,
            ip VARCHAR(100) NOT NULL,
            username VARCHAR(255) NOT NULL,
            block_reason TEXT NOT NULL,
            block_time DATETIME NOT NULL,
            PRIMARY KEY (id)
        ) $charset_collate;";

		require_once ABSPATH . 'wp-admin/includes/upgrade.php';
		dbDelta( $sql );
	}

	/**
	 * Check if the registrant is a spammer using StopForumSpam API
	 */
	public function check_spam_registration( $errors, $sanitized_user_login, $user_email ) {
		// Get user IP
		$ip = $this->get_user_ip();

		// Prepare API query
		$query_params = array(
			'email'    => urlencode( $user_email ),
			'ip'       => urlencode( $ip ),
			'username' => urlencode( $sanitized_user_login ),
			'f'        => 'json',
		);
		$query_string = http_build_query( $query_params );
		$url          = $this->api_url . '?' . $query_string;

		// Make API request
		$response = wp_remote_get( $url, array( 'timeout' => 5 ) );

		if ( is_wp_error( $response ) ) {
			// Log error but allow registration to proceed
			error_log( 'StopForumSpam API error: ' . $response->get_error_message() );
			return $errors;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! $data || ! isset( $data['success'] ) || $data['success'] != 1 ) {
			// Log error but allow registration
			error_log( 'StopForumSpam API invalid response' );
			return $errors;
		}

		// Check spam indicators
		$is_spammer   = false;
		$spam_details = array();

		// Check email
		if ( isset( $data['email']['frequency'] ) && $data['email']['frequency'] >= $this->frequency_threshold ) {
			if ( $data['email']['confidence'] >= $this->confidence_threshold ) {
				$is_spammer     = true;
				$spam_details[] = sprintf(
					'Email flagged (frequency: %d, confidence: %.2f%%)',
					$data['email']['frequency'],
					$data['email']['confidence']
				);
			}
		}

		// Check IP
		if ( isset( $data['ip']['frequency'] ) && $data['ip']['frequency'] >= $this->frequency_threshold ) {
			if ( $data['ip']['confidence'] >= $this->confidence_threshold ) {
				$is_spammer     = true;
				$spam_details[] = sprintf(
					'IP flagged (frequency: %d, confidence: %.2f%%)',
					$data['ip']['frequency'],
					$data['ip']['confidence']
				);
			}
		}

		// Check username
		if ( isset( $data['username']['frequency'] ) && $data['username']['frequency'] >= $this->frequency_threshold ) {
			if ( $data['username']['confidence'] >= $this->confidence_threshold ) {
				$is_spammer     = true;
				$spam_details[] = sprintf(
					'Username flagged (frequency: %d, confidence: %.2f%%)',
					$data['username']['frequency'],
					$data['username']['confidence']
				);
			}
		}

		if ( $is_spammer ) {
			$error_message = 'Registration blocked: Identified as potential spam. Contact site administrator for assistance.';
			$errors->add( 'spam_detected', $error_message );

			// Log the spam attempt
			$block_reason = implode( '; ', $spam_details );
			error_log( 'Blocked spam registration attempt: ' . $block_reason );

			// Save to database
			$this->log_blocked_attempt( $user_email, $ip, $sanitized_user_login, $block_reason );
		}

		return $errors;
	}

	/**
	 * Log blocked registration attempt to database
	 */
	private function log_blocked_attempt( $email, $ip, $username, $block_reason ) {
		global $wpdb;
		$table_name = $wpdb->prefix . 'sfs_blocked_log';

		$wpdb->insert(
			$table_name,
			array(
				'email'        => $email,
				'ip'           => $ip,
				'username'     => $username,
				'block_reason' => $block_reason,
				'block_time'   => current_time( 'mysql' ),
			),
			array( '%s', '%s', '%s', '%s', '%s' )
		);
	}

	/**
	 * Get the user's IP address
	 */
	private function get_user_ip() {
		$ip = $_SERVER['REMOTE_ADDR'];

		// Check for proxy headers
		if ( ! empty( $_SERVER['HTTP_X_FORWARDED_FOR'] ) ) {
			$proxy_ips = explode( ',', $_SERVER['HTTP_X_FORWARDED_FOR'] );
			$ip        = trim( $proxy_ips[0] );
		} elseif ( ! empty( $_SERVER['HTTP_CLIENT_IP'] ) ) {
			$ip = $_SERVER['HTTP_CLIENT_IP'];
		}

		return filter_var( $ip, FILTER_VALIDATE_IP ) ? $ip : '';
	}

	/**
	 * Handle manual IP or email check
	 */
	public function handle_manual_check() {
		// Verify nonce
		if ( ! check_admin_referer( 'sfs_manual_check' ) ) {
			wp_die( 'Security check failed' );
		}

		// Get and validate input
		$ip    = isset( $_POST['sfs_manual_ip'] ) ? sanitize_text_field( $_POST['sfs_manual_ip'] ) : '';
		$email = isset( $_POST['sfs_manual_email'] ) ? sanitize_email( $_POST['sfs_manual_email'] ) : '';

		// Ensure at least one input is provided
		if ( empty( $ip ) && empty( $email ) ) {
			set_transient( 'sfs_manual_check_result', 'Please provide an IP address or email.', 30 );
			wp_safe_redirect( admin_url( 'options-general.php?page=stopforumspam-settings' ) );
			exit;
		}

		// Validate inputs
		if ( $ip && ! filter_var( $ip, FILTER_VALIDATE_IP ) ) {
			set_transient( 'sfs_manual_check_result', 'Invalid IP address provided.', 30 );
			wp_safe_redirect( admin_url( 'options-general.php?page=stopforumspam-settings' ) );
			exit;
		}
		if ( $email && ! is_email( $email ) ) {
			set_transient( 'sfs_manual_check_result', 'Invalid email address provided.', 30 );
			wp_safe_redirect( admin_url( 'options-general.php?page=stopforumspam-settings' ) );
			exit;
		}

		// Prepare API query
		$query_params = array( 'f' => 'json' );
		if ( $ip ) {
			$query_params['ip'] = urlencode( $ip );
		}
		if ( $email ) {
			$query_params['email'] = urlencode( $email );
		}
		$query_string = http_build_query( $query_params );
		$url          = $this->api_url . '?' . $query_string;

		// Make API request
		$response = wp_remote_get( $url, array( 'timeout' => 5 ) );

		if ( is_wp_error( $response ) ) {
			set_transient( 'sfs_manual_check_result', 'API error: ' . $response->get_error_message(), 30 );
			wp_safe_redirect( admin_url( 'options-general.php?page=stopforumspam-settings' ) );
			exit;
		}

		$body = wp_remote_retrieve_body( $response );
		$data = json_decode( $body, true );

		if ( ! $data || ! isset( $data['success'] ) || $data['success'] != 1 ) {
			set_transient( 'sfs_manual_check_result', 'Invalid API response', 30 );
			wp_safe_redirect( admin_url( 'options-general.php?page=stopforumspam-settings' ) );
			exit;
		}

		// Prepare result
		$result = '';
		if ( $ip && isset( $data['ip'] ) ) {
			$result .= sprintf(
				'IP: %s<br>Frequency: %d<br>Confidence: %.2f%%<br>%s<br>',
				esc_html( $ip ),
				isset( $data['ip']['frequency'] ) ? $data['ip']['frequency'] : 0,
				isset( $data['ip']['confidence'] ) ? $data['ip']['confidence'] : 0,
				( $data['ip']['frequency'] >= $this->frequency_threshold && $data['ip']['confidence'] >= $this->confidence_threshold )
					? '<strong>Flagged as potential spammer</strong>'
					: 'Not flagged as spammer'
			);
		}
		if ( $email && isset( $data['email'] ) ) {
			$result .= sprintf(
				'Email: %s<br>Frequency: %d<br>Confidence: %.2f%%<br>%s',
				esc_html( $email ),
				isset( $data['email']['frequency'] ) ? $data['email']['frequency'] : 0,
				isset( $data['email']['confidence'] ) ? $data['email']['confidence'] : 0,
				( $data['email']['frequency'] >= $this->frequency_threshold && $data['email']['confidence'] >= $this->confidence_threshold )
					? '<strong>Flagged as potential spammer</strong>'
					: 'Not flagged as spammer'
			);
		}

		set_transient( 'sfs_manual_check_result', $result, 30 );
		wp_safe_redirect( admin_url( 'options-general.php?page=stopforumspam-settings' ) );
		exit;
	}

	/**
	 * AJAX handler for scanning users
	 */
	public function ajax_scan_users() {
		check_ajax_referer( 'sfs_scan_users', 'nonce' );

		$offset       = isset( $_POST['offset'] ) ? intval( $_POST['offset'] ) : 0;
		$batch_size   = 5; // Process 5 users per request
		$users        = get_users(
			array(
				'offset' => $offset,
				'number' => $batch_size,
			)
		);
		$total_users  = count_users()['total_users'];
		$scan_results = get_transient( 'sfs_scan_results' ) ?: array();

		foreach ( $users as $user ) {
			$email = $user->user_email;
			$ip    = get_user_meta( $user->ID, 'last_login_ip', true ) ?: '';

			if ( empty( $email ) ) {
				continue;
			}

			// Prepare API query
			$query_params = array(
				'f'     => 'json',
				'email' => urlencode( $email ),
			);
			if ( $ip ) {
				$query_params['ip'] = urlencode( $ip );
			}
			$query_string = http_build_query( $query_params );
			$url          = $this->api_url . '?' . $query_string;

			// Make API request
			$response = wp_remote_get( $url, array( 'timeout' => 5 ) );

			if ( is_wp_error( $response ) ) {
				error_log( 'StopForumSpam API error for user ' . $user->ID . ': ' . $response->get_error_message() );
				continue;
			}

			$body = wp_remote_retrieve_body( $response );
			$data = json_decode( $body, true );

			if ( ! $data || ! isset( $data['success'] ) || $data['success'] != 1 ) {
				error_log( 'StopForumSpam API invalid response for user ' . $user->ID );
				continue;
			}

			$is_spammer = false;
			$confidence = 0;
			$details    = array();

			if ( isset( $data['email']['frequency'] ) && $data['email']['frequency'] >= $this->frequency_threshold ) {
				if ( $data['email']['confidence'] >= $this->confidence_threshold ) {
					$is_spammer = true;
					$confidence = max( $confidence, $data['email']['confidence'] );
					$details[]  = sprintf( 'Email (freq: %d, conf: %.2f%%)', $data['email']['frequency'], $data['email']['confidence'] );
				}
			}

			if ( $ip && isset( $data['ip']['frequency'] ) && $data['ip']['frequency'] >= $this->frequency_threshold ) {
				if ( $data['ip']['confidence'] >= $this->confidence_threshold ) {
					$is_spammer = true;
					$confidence = max( $confidence, $data['ip']['confidence'] );
					$details[]  = sprintf( 'IP (freq: %d, conf: %.2f%%)', $data['ip']['frequency'], $data['ip']['confidence'] );
				}
			}

			$scan_results[ $user->ID ] = array(
				'email'      => $email,
				'username'   => $user->user_login,
				'confidence' => $confidence,
				'is_spammer' => $is_spammer,
				'details'    => implode( '; ', $details ),
			);
		}

		// Update transient
		set_transient( 'sfs_scan_results', $scan_results, HOUR_IN_SECONDS );

		// Calculate progress
		$processed = min( $offset + $batch_size, $total_users );
		$progress  = ( $total_users > 0 ) ? ( $processed / $total_users ) * 100 : 100;

		wp_send_json(
			array(
				'success' => true,
				'data'    => array(
					'progress'  => $progress,
					'processed' => $processed,
					'total'     => $total_users,
					'complete'  => $processed >= $total_users,
				),
			)
		);
	}

	/**
	 * AJAX handler for deleting users
	 */
	public function ajax_delete_users() {
		check_ajax_referer( 'sfs_delete_users', 'nonce' );

		// Check permissions
		if ( ! current_user_can( 'delete_users' ) ) {
			wp_send_json_error( array( 'message' => 'You do not have permission to delete users.' ) );
		}

		// Get selected users
		$user_ids = isset( $_POST['user_ids'] ) ? array_map( 'intval', (array) $_POST['user_ids'] ) : array();
		$user_ids = array_filter( $user_ids );

		if ( empty( $user_ids ) ) {
			wp_send_json_error( array( 'message' => 'No users selected for deletion.' ) );
		}

		// Current user cannot delete themselves
		$current_user_id = get_current_user_id();
		$deleted         = 0;
		$failed          = array();

		require_once ABSPATH . 'wp-admin/includes/user.php';

		foreach ( $user_ids as $user_id ) {
			if ( $user_id == $current_user_id ) {
				$failed[] = "Skipped deletion of current user (ID: $user_id).";
				continue;
			}

			if ( wp_delete_user( $user_id ) ) {
				++$deleted;
			} else {
				$failed[] = "Failed to delete user (ID: $user_id).";
			}
		}

		// Update scan results transient
		if ( $deleted > 0 ) {
			$scan_results = get_transient( 'sfs_scan_results' ) ?: array();
			foreach ( $user_ids as $user_id ) {
				if ( isset( $scan_results[ $user_id ] ) ) {
					unset( $scan_results[ $user_id ] );
				}
			}
			set_transient( 'sfs_scan_results', $scan_results, HOUR_IN_SECONDS );
		}

		// Prepare response
		$message = sprintf( 'Deleted %d user(s).', $deleted );
		if ( ! empty( $failed ) ) {
			$message .= ' ' . implode( ' ', $failed );
		}

		if ( $deleted > 0 ) {
			wp_send_json_success(
				array(
					'message'     => $message,
					'deleted_ids' => array_diff( $user_ids, array( $current_user_id ) ),
				)
			);
		} else {
			wp_send_json_error( array( 'message' => $message ) );
		}
	}

	/**
	 * Handle user deletion (fallback for non-AJAX)
	 */
	public function handle_delete_users() {
		// Verify nonce
		if ( ! check_admin_referer( 'sfs_delete_users' ) ) {
			wp_die( 'Security check failed' );
		}

		// Get selected users from POST or GET
		$user_ids = array();
		if ( isset( $_POST['sfs_delete_users'] ) ) {
			$user_ids = (array) $_POST['sfs_delete_users'];
		} elseif ( isset( $_GET['sfs_delete_users'] ) ) {
			$user_ids = (array) $_GET['sfs_delete_users'];
		}
		$user_ids = array_map( 'intval', array_filter( $user_ids ) );

		// Current user cannot delete themselves
		$current_user_id = get_current_user_id();
		$deleted         = 0;

		require_once ABSPATH . 'wp-admin/includes/user.php';

		foreach ( $user_ids as $user_id ) {
			if ( $user_id == $current_user_id ) {
				continue;
			}

			if ( wp_delete_user( $user_id ) ) {
				++$deleted;
			}
		}

		// Update scan results transient
		if ( $deleted > 0 ) {
			$scan_results = get_transient( 'sfs_scan_results' ) ?: array();
			foreach ( $user_ids as $user_id ) {
				if ( isset( $scan_results[ $user_id ] ) ) {
					unset( $scan_results[ $user_id ] );
				}
			}
			set_transient( 'sfs_scan_results', $scan_results, HOUR_IN_SECONDS );
		}

		$message = sprintf( 'Deleted %d user(s).', $deleted );
		if ( $deleted == 0 && ! empty( $user_ids ) ) {
			$message .= ' No users were deleted, possibly due to permissions or invalid user IDs.';
		}
		set_transient( 'sfs_delete_result', $message, 30 );

		wp_safe_redirect( admin_url( 'admin.php?page=sfs-scan-users' ) );
		exit;
	}

	/**
	 * Add admin pages
	 */
	public function add_admin_pages() {
		add_options_page(
			'SFS-Shield Settings',
			'SFS-Shield',
			'manage_options',
			'sfs-shield-settings',
			array( $this, 'render_settings_page' )
		);

		add_submenu_page(
			null,
			'Scan Users',
			'Scan Users',
			'manage_options',
			'sfs-scan-users',
			array( $this, 'render_scan_users_page' )
		);
	}

	/**
	 * Add StopForumSpam to admin bar
	 */
	public function add_admin_bar_menu( $wp_admin_bar ) {
		$wp_admin_bar->add_node(
			array(
				'id'    => 'stopforumspam',
				'title' => 'StopForumSpam',
				'href'  => admin_url( 'options-general.php?page=stopforumspam-settings' ),
				'meta'  => array(
					'title' => 'StopForumSpam Settings',
				),
			)
		);

		$wp_admin_bar->add_node(
			array(
				'id'     => 'sfs-scan-users',
				'parent' => 'stopforumspam',
				'title'  => 'Scan Users',
				'href'   => admin_url( 'admin.php?page=sfs-scan-users' ),
				'meta'   => array(
					'title' => 'Scan Existing Users',
				),
			)
		);
	}

	/**
	 * Register settings
	 */
	public function register_settings() {
		register_setting(
			'sfs_settings_group',
			'sfs_confidence_threshold',
			array(
				'type'              => 'number',
				'sanitize_callback' => array( $this, 'sanitize_confidence' ),
				'default'           => 80,
			)
		);
		register_setting(
			'sfs_settings_group',
			'sfs_frequency_threshold',
			array(
				'type'              => 'integer',
				'sanitize_callback' => array( $this, 'sanitize_frequency' ),
				'default'           => 1,
			)
		);

		add_settings_section(
			'sfs_main_section',
			'StopForumSpam Settings',
			null,
			'stopforumspam-settings'
		);

		add_settings_field(
			'sfs_confidence_threshold',
			'Confidence Threshold (%)',
			array( $this, 'render_confidence_field' ),
			'stopforumspam-settings',
			'sfs_main_section'
		);

		add_settings_field(
			'sfs_frequency_threshold',
			'Frequency Threshold',
			array( $this, 'render_frequency_field' ),
			'stopforumspam-settings',
			'sfs_main_section'
		);
	}

	/**
	 * Sanitize confidence input
	 */
	public function sanitize_confidence( $value ) {
		$value = floatval( $value );
		return max( 0, min( 100, $value ) );
	}

	/**
	 * Sanitize frequency input
	 */
	public function sanitize_frequency( $value ) {
		$value = intval( $value );
		return max( 0, $value );
	}

	/**
	 * Render settings page
	 */
	public function render_settings_page() {
		// Get manual check result if available
		$manual_check_result = get_transient( 'sfs_manual_check_result' );

		// Get blocked logs
		global $wpdb;
		$table_name   = $wpdb->prefix . 'sfs_blocked_log';
		$blocked_logs = $wpdb->get_results( "SELECT * FROM $table_name ORDER BY block_time DESC LIMIT 50" );
		?>
		<div class="wrap">
			<h1>StopForumSpam shield</h1>
			<form method="post" action="options.php">
				<?php
				settings_fields( 'sfs_settings_group' );
				do_settings_sections( 'stopforumspam-settings' );
				submit_button();
				?>
			</form>

			<h2>Manual Check</h2>
			<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
				<input type="hidden" name="action" value="sfs_manual_check">
				<?php wp_nonce_field( 'sfs_manual_check' ); ?>
				<p>
					<label for="sfs_manual_ip">IP Address:</label><br>
					<input type="text" id="sfs_manual_ip" name="sfs_manual_ip" placeholder="e.g., 192.168.1.1" />
				</p>
				<p>
					<label for="sfs_manual_email">Email Address:</label><br>
					<input type="email" id="sfs_manual_email" name="sfs_manual_email" placeholder="e.g., user@example.com" />
				</p>
				<?php submit_button( 'Check' ); ?>
			</form>

			<?php if ( $manual_check_result ) : ?>
				<h3>Check Result</h3>
				<p><?php echo wp_kses_post( $manual_check_result ); ?></p>
				<?php delete_transient( 'sfs_manual_check_result' ); ?>
			<?php endif; ?>

			<h2>Blocked Registration Attempts</h2>
			<?php if ( $blocked_logs ) : ?>
				<table class="wp-list-table widefat fixed striped">
					<thead>
						<tr>
							<th>Email</th>
							<th>IP Address</th>
							<th>Username</th>
							<th>Reason</th>
							<th>Time</th>
						</tr>
					</thead>
					<tbody>
						<?php foreach ( $blocked_logs as $log ) : ?>
							<tr>
								<td><?php echo esc_html( $log->email ); ?></td>
								<td><?php echo esc_html( $log->ip ); ?></td>
								<td><?php echo esc_html( $log->username ); ?></td>
								<td><?php echo esc_html( $log->block_reason ); ?></td>
								<td><?php echo esc_html( $log->block_time ); ?></td>
							</tr>
						<?php endforeach; ?>
					</tbody>
				</table>
			<?php else : ?>
				<p>No blocked attempts recorded yet.</p>
			<?php endif; ?>
			<p>This plugin uses the <a href="https://www.stopforumspam.com/" target="_blank">StopForumSpam API</a> to check for spam registrations.</p>
		</div>
		<?php
	}

	/**
	 * Render scan users page
	 */
	public function render_scan_users_page() {
		$scan_results  = get_transient( 'sfs_scan_results' );
		$delete_result = get_transient( 'sfs_delete_result' );
		?>
		<div class="wrap">
			<h1>Scan Existing Users</h1>
			<p>Click the button below to scan all users against StopForumSpam. Progress will be shown below.</p>
			<button id="sfs-scan-users-btn" class="button button-primary">Scan Users</button>
			<div id="sfs-progress-bar" style="display: none;">
				<div class="sfs-progress">
					<div id="sfs-progress-fill" style="width: 0%;"></div>
				</div>
				<p id="sfs-progress-text">Scanning: 0 of 0 users (0%)</p>
			</div>
			<div id="sfs-delete-messages" style="margin-top: 10px;"></div>

			<?php if ( $scan_results ) : ?>
				<h2>Scan Results</h2>
				<form id="sfs-delete-users-form" method="post">
					<input type="hidden" name="action" value="sfs_delete_users">
					<?php wp_nonce_field( 'sfs_delete_users' ); ?>
					<table class="wp-list-table widefat fixed striped">
						<thead>
							<tr>
								<th><input type="checkbox" id="sfs_select_all"></th>
								<th>Email</th>
								<th>Username</th>
								<th>Confidence Score</th>
								<th>Details</th>
								<th>Action</th>
							</tr>
						</thead>
						<tbody id="sfs-users-table">
							<?php foreach ( $scan_results as $user_id => $result ) : ?>
								<tr data-user-id="<?php echo esc_attr( $user_id ); ?>">
									<td>
										<?php if ( $result['is_spammer'] && $user_id != get_current_user_id() ) : ?>
											<input type="checkbox" name="sfs_delete_users[]" value="<?php echo esc_attr( $user_id ); ?>">
										<?php endif; ?>
									</td>
									<td><?php echo esc_html( $result['email'] ); ?></td>
									<td><?php echo esc_html( $result['username'] ); ?></td>
									<td><?php echo esc_html( number_format( $result['confidence'], 2 ) ) . '%'; ?></td>
									<td><?php echo esc_html( $result['details'] ); ?></td>
									<td>
										<?php if ( $result['is_spammer'] && $user_id != get_current_user_id() ) : ?>
											<button class="button button-secondary sfs-delete-user" data-user-id="<?php echo esc_attr( $user_id ); ?>">Delete</button>
										<?php endif; ?>
									</td>
								</tr>
							<?php endforeach; ?>
						</tbody>
					</table>
					<p>
						<input type="submit" class="button button-primary" value="Delete Selected Users">
					</p>
				</form>
				<script>
					document.getElementById('sfs_select_all').addEventListener('change', function() {
						var checkboxes = document.querySelectorAll('input[name="sfs_delete_users[]"]');
						checkboxes.forEach(function(checkbox) {
							checkbox.checked = this.checked;
						}, this);
					});
				</script>
			<?php endif; ?>

			<?php if ( $delete_result ) : ?>
				<h3>Deletion Result</h3>
				<p><?php echo esc_html( $delete_result ); ?></p>
				<?php delete_transient( 'sfs_delete_result' ); ?>
			<?php endif; ?>
		</div>
		<?php
	}

	/**
	 * Render confidence field
	 */
	public function render_confidence_field() {
		$value = get_option( 'sfs_confidence_threshold', 80 );
		?>
		<input type="number" name="sfs_confidence_threshold" value="<?php echo esc_attr( $value ); ?>" min="0" max="100" step="0.1" />
		<p class="description">Set the confidence level (0-100%) above which a user is considered a spammer.</p>
		<?php
	}

	/**
	 * Render frequency field
	 */
	public function render_frequency_field() {
		$value = get_option( 'sfs_frequency_threshold', 1 );
		?>
		<input type="number" name="sfs_frequency_threshold" value="<?php echo esc_attr( $value ); ?>" min="0" />
		<p class="description">Set the minimum number of appearances in the StopForumSpam database to flag a user.</p>
		<?php
	}
}

// Initialize the plugin
new StopForumSpam_Registration_Check();
?>
