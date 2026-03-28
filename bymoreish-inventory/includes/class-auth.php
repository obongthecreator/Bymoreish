<?php
/**
 * Authentication class for Bymoreish Inventory.
 *
 * Handles session-based custom authentication (NOT WordPress auth).
 * Session keys use the bym_ prefix to match the plugin-wide convention.
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bymoreish_Auth {

	/** @var Bymoreish_Auth|null */
	private static $instance = null;

	// -----------------------------------------------------------------------
	// Session key constants
	// -----------------------------------------------------------------------
	const SK_AUTHENTICATED = 'bym_authenticated';
	const SK_USER_ID       = 'bym_user_id';
	const SK_USERNAME      = 'bym_username';
	const SK_FULL_NAME     = 'bym_full_name';
	const SK_ROLE          = 'bym_role';
	const SK_BRANCH        = 'bym_branch';

	// Role hierarchy (lowest → highest)
	const ROLES = [ 'staff', 'admin', 'superadmin' ];

	// -----------------------------------------------------------------------
	// Singleton
	// -----------------------------------------------------------------------

	private function __construct() {}

	public static function get_instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// -----------------------------------------------------------------------
	// Session bootstrap
	// -----------------------------------------------------------------------

	private function ensure_session(): void {
		if ( ! session_id() ) {
			session_start();
		}
	}

	// -----------------------------------------------------------------------
	// Core authentication
	// -----------------------------------------------------------------------

	/**
	 * Verify credentials against bym_users and start a session on success.
	 *
	 * @param string $username
	 * @param string $password  Plain-text password.
	 * @return bool
	 */
	public function login( string $username, string $password ): bool {
		$this->ensure_session();

		$username = sanitize_text_field( $username );

		if ( empty( $username ) || empty( $password ) ) {
			return false;
		}

		$db   = Bymoreish_Database::get_instance();
		$user = $db->get_user_by_username( $username );

		if ( ! $user ) {
			return false;
		}

		if ( ! $db->verify_password( $password, $user['password_hash'] ) ) {
			return false;
		}

		if ( empty( $user['is_active'] ) ) {
			return false;
		}

		// Rotate session ID to prevent session fixation.
		session_regenerate_id( true );

		$_SESSION[ self::SK_AUTHENTICATED ] = true;
		$_SESSION[ self::SK_USER_ID ]       = (int) $user['id'];
		$_SESSION[ self::SK_USERNAME ]      = (string) $user['username'];
		$_SESSION[ self::SK_FULL_NAME ]     = (string) $user['full_name'];
		$_SESSION[ self::SK_ROLE ]          = (string) $user['role'];
		$_SESSION[ self::SK_BRANCH ]        = (string) $user['branch'];

		// Regenerate the AJAX nonce on each new login.
		$_SESSION['bym_nonce'] = bin2hex( random_bytes( 16 ) );

		return true;
	}

	/**
	 * Destroy the session and clear the session cookie.
	 */
	public function logout(): void {
		$this->ensure_session();

		$_SESSION = [];

		if ( ini_get( 'session.use_cookies' ) ) {
			$params = session_get_cookie_params();
			setcookie(
				session_name(),
				'',
				time() - 42000,
				$params['path'],
				$params['domain'],
				$params['secure'],
				$params['httponly']
			);
		}

		session_destroy();
	}

	/**
	 * Return true if the visitor has an active authenticated session.
	 */
	public function is_authenticated(): bool {
		$this->ensure_session();
		return ! empty( $_SESSION[ self::SK_AUTHENTICATED ] )
			&& ! empty( $_SESSION[ self::SK_USER_ID ] );
	}

	/**
	 * Return the current user's data from the session, or an empty array.
	 *
	 * @return array{id: int, username: string, full_name: string, role: string, branch: string}
	 */
	public function get_current_user(): array {
		if ( ! $this->is_authenticated() ) {
			return [];
		}

		return [
			'id'        => (int) ( $_SESSION[ self::SK_USER_ID ]   ?? 0 ),
			'username'  => (string) ( $_SESSION[ self::SK_USERNAME ] ?? '' ),
			'full_name' => (string) ( $_SESSION[ self::SK_FULL_NAME ] ?? '' ),
			'role'      => (string) ( $_SESSION[ self::SK_ROLE ]     ?? 'staff' ),
			'branch'    => (string) ( $_SESSION[ self::SK_BRANCH ]   ?? '' ),
		];
	}

	// -----------------------------------------------------------------------
	// Access control
	// -----------------------------------------------------------------------

	/**
	 * Redirect to the login page if the visitor is not authenticated.
	 *
	 * @param string $redirect_to  Optional URL to return to after login.
	 */
	public function require_auth( string $redirect_to = '' ): void {
		if ( ! $this->is_authenticated() ) {
			$login = home_url( '/bymoreish/login' );
			if ( $redirect_to !== '' ) {
				$login = add_query_arg( 'redirect_to', rawurlencode( $redirect_to ), $login );
			}
			wp_redirect( $login );
			exit;
		}
	}

	/**
	 * Ensure the current user has at least the given role.
	 * Redirects to the home dashboard if insufficient.
	 *
	 * @param string $minimum_role  'staff' | 'admin' | 'superadmin'
	 */
	public function require_role( string $minimum_role ): void {
		$user = $this->get_current_user();

		if ( ! $this->has_role( $user['role'] ?? '', $minimum_role ) ) {
			wp_redirect( home_url( '/bymoreish/home' ) );
			exit;
		}
	}

	/**
	 * Return true if $user_role is at or above $minimum_role in the hierarchy.
	 *
	 * @param string $user_role
	 * @param string $minimum_role
	 * @return bool
	 */
	public function has_role( string $user_role, string $minimum_role ): bool {
		$user_idx = array_search( $user_role, self::ROLES, true );
		$min_idx  = array_search( $minimum_role, self::ROLES, true );

		if ( $user_idx === false || $min_idx === false ) {
			return false;
		}

		return $user_idx >= $min_idx;
	}

	// -----------------------------------------------------------------------
	// Profile management
	// -----------------------------------------------------------------------

	/**
	 * Update allowed profile fields for a user.
	 *
	 * @param int   $user_id
	 * @param array $data  Keys: full_name, email, phone, branch, profile_picture.
	 * @return bool
	 */
	public function update_profile( int $user_id, array $data ): bool {
		$allowed_text   = [ 'full_name', 'email', 'phone', 'branch' ];
		$update_data    = [];

		foreach ( $allowed_text as $field ) {
			if ( array_key_exists( $field, $data ) ) {
				$update_data[ $field ] = sanitize_text_field( (string) $data[ $field ] );
			}
		}

		if ( array_key_exists( 'profile_picture', $data ) ) {
			$update_data['profile_picture'] = esc_url_raw( (string) $data['profile_picture'] );
		}

		if ( empty( $update_data ) ) {
			return false;
		}

		$db     = Bymoreish_Database::get_instance();
		$result = $db->update_row( Bymoreish_Database::TABLE_USERS, $update_data, [ 'id' => $user_id ] );

		if ( $result !== false ) {
			// Refresh the session if this is the currently logged-in user.
			$this->ensure_session();
			$session_uid = (int) ( $_SESSION[ self::SK_USER_ID ] ?? 0 );

			if ( $session_uid === $user_id ) {
				if ( isset( $update_data['full_name'] ) ) {
					$_SESSION[ self::SK_FULL_NAME ] = $update_data['full_name'];
				}
				if ( isset( $update_data['branch'] ) ) {
					$_SESSION[ self::SK_BRANCH ] = $update_data['branch'];
				}
			}
		}

		return $result !== false;
	}

	/**
	 * Change a user's password after verifying their current one.
	 *
	 * @param int    $user_id
	 * @param string $old_pass  Current plain-text password.
	 * @param string $new_pass  New plain-text password (min 6 chars).
	 * @return bool
	 */
	public function change_password( int $user_id, string $old_pass, string $new_pass ): bool {
		if ( strlen( $new_pass ) < 6 ) {
			return false;
		}

		$db   = Bymoreish_Database::get_instance();
		$user = $db->get_row_by_id( Bymoreish_Database::TABLE_USERS, $user_id );

		if ( ! $user ) {
			return false;
		}

		if ( ! $db->verify_password( $old_pass, $user['password_hash'] ) ) {
			return false;
		}

		$new_hash = wp_hash_password( $new_pass );
		$result   = $db->update_row(
			Bymoreish_Database::TABLE_USERS,
			[ 'password_hash' => $new_hash ],
			[ 'id' => $user_id ]
		);

		return $result !== false;
	}

	// -----------------------------------------------------------------------
	// Login form handler
	// -----------------------------------------------------------------------

	/**
	 * Process a POST login form submission.
	 *
	 * @return array{success: bool, error: string, redirect: string}
	 */
	public function handle_login_form(): array {
		if ( ! isset( $_SERVER['REQUEST_METHOD'] ) || $_SERVER['REQUEST_METHOD'] !== 'POST' ) {
			return [ 'success' => false, 'error' => '', 'redirect' => '' ];
		}

		$username = sanitize_text_field( wp_unslash( $_POST['username'] ?? '' ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized -- password intentionally unsanitized
		$password = wp_unslash( $_POST['password'] ?? '' );

		if ( empty( $username ) || empty( $password ) ) {
			return [
				'success'  => false,
				'error'    => 'Please enter your username and password.',
				'redirect' => '',
			];
		}

		if ( $this->login( $username, $password ) ) {
			$redirect_to = sanitize_text_field( wp_unslash( $_GET['redirect_to'] ?? '' ) );

			if ( ! empty( $redirect_to ) ) {
				$redirect_to = rawurldecode( $redirect_to );
				// Only allow same-site redirects to prevent open redirects.
				if ( strpos( $redirect_to, home_url() ) !== 0 ) {
					$redirect_to = '';
				}
			}

			if ( empty( $redirect_to ) ) {
				$redirect_to = home_url( '/bymoreish/home' );
			}

			return [ 'success' => true, 'error' => '', 'redirect' => $redirect_to ];
		}

		return [
			'success'  => false,
			'error'    => 'Invalid username or password.',
			'redirect' => '',
		];
	}
}
