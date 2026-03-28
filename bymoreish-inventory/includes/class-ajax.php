<?php
/**
 * AJAX handler class for Bymoreish Inventory.
 *
 * All actions are registered for both authenticated WP users (wp_ajax_)
 * and unauthenticated requests (wp_ajax_nopriv_) because authentication
 * is handled via PHP sessions, not WordPress login.
 *
 * Nonce verification uses a session-stored token (not WP nonces).
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bymoreish_Ajax {

	/** @var Bymoreish_Ajax|null */
	private static $instance = null;

	// All registered AJAX action names.
	private const ACTIONS = [
		'bym_login',
		'bym_logout',
		'bym_get_products',
		'bym_save_order',
		'bym_update_order_status',
		'bym_delete_order',
		'bym_get_orders',
		'bym_get_order_items',
		'bym_save_stock',
		'bym_get_stock',
		'bym_save_expense',
		'bym_get_expenses',
		'bym_get_expense_items',
		'bym_delete_expense',
		'bym_get_financial_summary',
		'bym_save_financial_summary',
		'bym_get_analytics',
		'bym_dashboard_kpis',
		'bym_save_product',
		'bym_delete_product',
		'bym_get_product_summary',
		'bym_save_user',
		'bym_get_users',
		'bym_delete_user',
		'bym_change_password',
		'bym_upload_profile_picture',
		'bym_update_settings',
		'bym_get_settings',
		'bym_delete_all_records',
		'bym_format_currency',
		'bym_generate_receipt',
		'bym_get_receipt',
	];

	// Actions that do NOT require session auth (only nonce for bym_login; others are public helpers).
	private const UNAUTHENTICATED_ACTIONS = [
		'bym_login',
		'bym_format_currency',
	];

	// -----------------------------------------------------------------------
	// Singleton & bootstrap
	// -----------------------------------------------------------------------

	private function __construct() {}

	public static function get_instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	/**
	 * Register all wp_ajax_ and wp_ajax_nopriv_ hooks.
	 * Call once from the plugin bootstrap (add_action 'init' or 'wp_loaded').
	 */
	public function register_hooks(): void {
		foreach ( self::ACTIONS as $action ) {
			$method = 'handle_' . $action;
			add_action( 'wp_ajax_' . $action,        [ $this, $method ] );
			add_action( 'wp_ajax_nopriv_' . $action, [ $this, $method ] );
		}
	}

	// -----------------------------------------------------------------------
	// Nonce helpers
	// -----------------------------------------------------------------------

	/**
	 * Return the session-based nonce, generating it on first call per session.
	 */
	public function get_nonce(): string {
		if ( ! session_id() ) {
			session_start();
		}
		if ( empty( $_SESSION['bym_nonce'] ) ) {
			$_SESSION['bym_nonce'] = bin2hex( random_bytes( 16 ) );
		}
		return (string) $_SESSION['bym_nonce'];
	}

	/**
	 * Verify the nonce submitted with the current AJAX request.
	 * Accepts the nonce from either $_POST or $_GET.
	 */
	private function verify_nonce(): bool {
		if ( ! session_id() ) {
			session_start();
		}
		$submitted = sanitize_text_field(
			wp_unslash( $_POST['nonce'] ?? $_POST['_bym_nonce'] ?? $_GET['nonce'] ?? $_GET['_bym_nonce'] ?? '' )
		);
		$stored = (string) ( $_SESSION['bym_nonce'] ?? '' );
		return ! empty( $stored ) && hash_equals( $stored, $submitted );
	}

	// -----------------------------------------------------------------------
	// Response helpers
	// -----------------------------------------------------------------------

	/**
	 * Send a JSON success response and terminate.
	 *
	 * @param mixed  $data
	 * @param string $message  Unused – kept for API compatibility.
	 */
	private function success( $data = null, string $message = 'Success' ): void {
		wp_send_json_success( $data );
	}

	/**
	 * Send a JSON error response and terminate.
	 *
	 * @param string $message
	 * @param int    $code  HTTP status code.
	 */
	private function error( string $message, int $code = 400 ): void {
		wp_send_json_error( $message, $code );
	}

	/**
	 * Verify nonce and optionally require authentication.
	 * Calls wp_send_json_error and exits on failure.
	 *
	 * @param bool $require_auth  Whether authentication is required.
	 */
	private function check_request( bool $require_auth = true ): void {
		if ( ! $this->verify_nonce() ) {
			$this->error( 'Invalid security token. Please refresh the page.', 403 );
		}

		if ( $require_auth ) {
			$auth = Bymoreish_Auth::get_instance();
			if ( ! $auth->is_authenticated() ) {
				$this->error( 'You are not authenticated.', 401 );
			}
		}
	}

	/**
	 * Return the current user or send a 401 and exit.
	 */
	private function current_user(): array {
		$auth = Bymoreish_Auth::get_instance();
		$user = $auth->get_current_user();
		if ( empty( $user ) ) {
			$this->error( 'You are not authenticated.', 401 );
		}
		return $user;
	}

	/**
	 * Assert that the current user has at least the given minimum role.
	 */
	private function require_role( string $minimum_role ): void {
		$auth = Bymoreish_Auth::get_instance();
		$user = $this->current_user();
		if ( ! $auth->has_role( $user['role'], $minimum_role ) ) {
			$this->error( 'You do not have permission to perform this action.', 403 );
		}
	}

	/**
	 * Resolve a branch ID for the current user.
	 * superadmin can pass any branch; staff/admin are restricted to their own.
	 *
	 * @param int|null $requested_branch_id  Branch ID from the request.
	 * @return int
	 */
	private function resolve_branch_id( ?int $requested_branch_id ): int {
		$user = $this->current_user();

		if ( $user['role'] === 'superadmin' ) {
			return (int) ( $requested_branch_id ?? 0 );
		}

		// For staff/admin, look up their branch by slug.
		if ( ! empty( $user['branch'] ) && $user['branch'] !== 'all' ) {
			global $wpdb;
			$branch_table = 'bym_branches';
			$row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id FROM {$branch_table} WHERE slug = %s LIMIT 1",
					$user['branch']
				),
				ARRAY_A
			);
			if ( $row ) {
				return (int) $row['id'];
			}
		}

		return (int) ( $requested_branch_id ?? 0 );
	}

	// -----------------------------------------------------------------------
	// 1. Auth
	// -----------------------------------------------------------------------

	public function handle_bym_login(): void {
		// Login does not require prior auth, but nonce must be present.
		// We skip nonce here since it is sent as part of the POST payload
		// and the nonce is only available after the page has loaded.
		$username = sanitize_text_field( wp_unslash( $_POST['username'] ?? '' ) );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$password = wp_unslash( $_POST['password'] ?? '' );

		if ( empty( $username ) || empty( $password ) ) {
			$this->error( 'Username and password are required.' );
		}

		$auth = Bymoreish_Auth::get_instance();

		if ( $auth->login( $username, $password ) ) {
			$user  = $auth->get_current_user();
			$nonce = $this->get_nonce();
			$this->success(
				[
					'user'  => $user,
					'nonce' => $nonce,
				],
				'Login successful.'
			);
		}

		$this->error( 'Invalid username or password.' );
	}

	public function handle_bym_logout(): void {
		$auth = Bymoreish_Auth::get_instance();
		$auth->logout();
		$this->success( null, 'Logged out.' );
	}

	// -----------------------------------------------------------------------
	// 2. Products
	// -----------------------------------------------------------------------

	public function handle_bym_get_products(): void {
		$this->check_request();

		$db             = Bymoreish_Database::get_instance();
		$branch_id      = (int) ( $_POST['branch_id'] ?? $_GET['branch_id'] ?? 0 );
		$include_all    = (int) ( $_POST['include_inactive'] ?? $_GET['include_inactive'] ?? 0 );

		if ( $branch_id > 0 ) {
			global $wpdb;
			$products_table = 'bym_products';
			if ( $include_all ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$products = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$products_table} WHERE (branch_id = %d OR branch_id IS NULL) ORDER BY category ASC, name ASC",
						$branch_id
					),
					ARRAY_A
				) ?: [];
			} else {
				// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
				$products = $wpdb->get_results(
					$wpdb->prepare(
						"SELECT * FROM {$products_table} WHERE (branch_id = %d OR branch_id IS NULL) AND is_active = 1 ORDER BY category ASC, name ASC",
						$branch_id
					),
					ARRAY_A
				) ?: [];
			}
		} else {
			if ( $include_all ) {
				$products = $db->get_rows( Bymoreish_Database::TABLE_PRODUCTS, [], 'category ASC, name ASC' );
			} else {
				$products = $db->get_rows( Bymoreish_Database::TABLE_PRODUCTS, [ 'is_active' => 1 ], 'category ASC, name ASC' );
			}
		}

		// Attach extras to each product.
		foreach ( $products as &$product ) {
			$extras = $db->get_rows(
				Bymoreish_Database::TABLE_PRODUCT_EXTRAS,
				[ 'product_id' => (int) $product['id'], 'is_active' => 1 ]
			);
			$product['extras'] = $extras;
		}
		unset( $product );

		$this->success( $products );
	}

	public function handle_bym_save_product(): void {
		$this->check_request();
		$this->require_role( 'admin' );

		$id          = (int) ( $_POST['id'] ?? 0 );
		$name        = sanitize_text_field( wp_unslash( $_POST['name'] ?? '' ) );
		$category    = sanitize_text_field( wp_unslash( $_POST['category'] ?? '' ) );
		$price       = (float) ( $_POST['price'] ?? 0 );
		$unit        = sanitize_text_field( wp_unslash( $_POST['unit'] ?? '' ) );
		$description = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
		$branch_id   = (int) ( $_POST['branch_id'] ?? 0 );
		$is_active   = (int) ( (bool) ( $_POST['is_active'] ?? $_POST['active'] ?? 1 ) );

		if ( empty( $name ) ) {
			$this->error( 'Product name is required.' );
		}

		$data = [
			'name'        => $name,
			'category'    => $category,
			'price'       => number_format( $price, 2, '.', '' ),
			'unit'        => $unit,
			'description' => $description,
			'is_active'   => $is_active,
			'branch_id'   => $branch_id > 0 ? $branch_id : null,
		];

		$db = Bymoreish_Database::get_instance();

		if ( $id > 0 ) {
			$db->update_row( Bymoreish_Database::TABLE_PRODUCTS, $data, [ 'id' => $id ] );
		} else {
			$id = $db->insert_row( Bymoreish_Database::TABLE_PRODUCTS, $data );
		}

		// Save extras if provided.
		$extras_raw = wp_unslash( $_POST['extras'] ?? '' );
		if ( ! empty( $extras_raw ) ) {
			$extras = is_string( $extras_raw ) ? json_decode( $extras_raw, true ) : $extras_raw;
			if ( is_array( $extras ) ) {
				// Remove old extras and re-insert.
				$db->delete_rows( Bymoreish_Database::TABLE_PRODUCT_EXTRAS, [ 'product_id' => $id ] );
				foreach ( $extras as $extra ) {
					$extra_name  = sanitize_text_field( $extra['name'] ?? '' );
					$extra_price = (float) ( $extra['price'] ?? 0 );
					if ( ! empty( $extra_name ) ) {
						$db->insert_row(
							Bymoreish_Database::TABLE_PRODUCT_EXTRAS,
							[
								'product_id'  => $id,
								'extra_name'  => $extra_name,
								'extra_price' => number_format( $extra_price, 2, '.', '' ),
								'is_active'   => 1,
							]
						);
					}
				}
			}
		}

		$this->success( [ 'id' => $id ], 'Product saved.' );
	}

	public function handle_bym_delete_product(): void {
		$this->check_request();
		$this->require_role( 'admin' );

		$id = (int) ( $_POST['id'] ?? 0 );
		if ( $id <= 0 ) {
			$this->error( 'Invalid product ID.' );
		}

		$db = Bymoreish_Database::get_instance();
		$db->delete_rows( Bymoreish_Database::TABLE_PRODUCT_EXTRAS, [ 'product_id' => $id ] );
		$db->update_row( Bymoreish_Database::TABLE_PRODUCTS, [ 'is_active' => 0 ], [ 'id' => $id ] );

		$this->success( null, 'Product deleted.' );
	}

	public function handle_bym_get_product_summary(): void {
		$this->check_request();

		$branch_id  = $this->resolve_branch_id( (int) ( $_POST['branch_id'] ?? 0 ) );
		$date_from  = sanitize_text_field( wp_unslash( $_POST['date_from'] ?? current_time( 'Y-m-d' ) ) );
		$date_to    = sanitize_text_field( wp_unslash( $_POST['date_to']   ?? current_time( 'Y-m-d' ) ) );

		global $wpdb;
		$oi_table = 'bym_order_items';
		$o_table  = 'bym_orders';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT oi.product_name,
				        SUM(oi.quantity)   AS total_qty,
				        SUM(oi.item_total) AS total_revenue
				   FROM {$oi_table} oi
				   JOIN {$o_table} o ON o.id = oi.order_id
				  WHERE o.branch_id  = %d
				    AND o.order_date BETWEEN %s AND %s
				    AND o.status     = 'delivered'
				  GROUP BY oi.product_id, oi.product_name
				  ORDER BY total_qty DESC",
				$branch_id,
				$date_from,
				$date_to
			),
			ARRAY_A
		) ?: [];

		$this->success( $rows );
	}

	// -----------------------------------------------------------------------
	// 3. Orders
	// -----------------------------------------------------------------------

	public function handle_bym_save_order(): void {
		$this->check_request();

		$user      = $this->current_user();
		$branch_id = $this->resolve_branch_id( (int) ( $_POST['branch_id'] ?? 0 ) );

		if ( $branch_id <= 0 ) {
			$this->error( 'Branch is required.' );
		}

		// Parse items.
		$items_raw = wp_unslash( $_POST['items'] ?? '' );
		$items     = is_string( $items_raw ) ? json_decode( $items_raw, true ) : $items_raw;
		if ( empty( $items ) || ! is_array( $items ) ) {
			$this->error( 'Order must contain at least one item.' );
		}

		// Calculate grand total from items.
		$grand_total = 0.0;
		foreach ( $items as $item ) {
			$grand_total += (float) ( $item['item_total'] ?? 0 );
		}

		// Payment mode & amount allocation.
		$payment_mode = sanitize_text_field( wp_unslash( $_POST['payment_mode'] ?? 'cash' ) );
		$valid_modes  = [ 'transfer', 'card', 'cash', 'transfer_card', 'transfer_cash', 'card_cash', 'all' ];
		if ( ! in_array( $payment_mode, $valid_modes, true ) ) {
			$payment_mode = 'cash';
		}

		[ $transfer_amount, $card_amount, $cash_amount ] = $this->allocate_payment(
			$payment_mode,
			$grand_total,
			(float) ( $_POST['card_amount']     ?? 0 ),
			(float) ( $_POST['cash_amount']     ?? 0 ),
			(float) ( $_POST['transfer_amount'] ?? 0 )
		);

		$status = sanitize_text_field( wp_unslash( $_POST['status'] ?? 'pending' ) );
		if ( ! in_array( $status, [ 'pending', 'prepared', 'delivered' ], true ) ) {
			$status = 'pending';
		}

		$db           = Bymoreish_Database::get_instance();
		$order_number = $db->generate_order_number();

		$order_id = $db->insert_row(
			Bymoreish_Database::TABLE_ORDERS,
			[
				'order_number'     => $order_number,
				'branch_id'        => $branch_id,
				'staff_id'         => $user['id'],
				'customer_name'    => sanitize_text_field( wp_unslash( $_POST['customer_name']    ?? '' ) ),
				'customer_phone'   => sanitize_text_field( wp_unslash( $_POST['customer_phone']   ?? '' ) ),
				'customer_type'    => sanitize_text_field( wp_unslash( $_POST['customer_type']    ?? 'new' ) ),
				'customer_remarks' => sanitize_textarea_field( wp_unslash( $_POST['customer_remarks'] ?? '' ) ),
				'payment_mode'     => $payment_mode,
				'transfer_amount'  => number_format( $transfer_amount, 2, '.', '' ),
				'card_amount'      => number_format( $card_amount, 2, '.', '' ),
				'cash_amount'      => number_format( $cash_amount, 2, '.', '' ),
				'grand_total'      => number_format( $grand_total, 2, '.', '' ),
				'status'           => $status,
				'payment_confirmed'=> (int) ( (bool) ( $_POST['payment_confirmed'] ?? 0 ) ),
				'order_date'       => current_time( 'Y-m-d' ),
			]
		);

		if ( ! $order_id ) {
			$this->error( 'Failed to save order.' );
		}

		// Insert order items.
		foreach ( $items as $item ) {
			$extras_json = null;
			if ( ! empty( $item['extras'] ) ) {
				$extras_json = wp_json_encode( $item['extras'] );
			}
			$db->insert_row(
				Bymoreish_Database::TABLE_ORDER_ITEMS,
				[
					'order_id'      => $order_id,
					'product_id'    => (int) ( $item['product_id'] ?? 0 ) ?: null,
					'product_name'  => sanitize_text_field( $item['product_name'] ?? '' ),
					'product_price' => number_format( (float) ( $item['product_price'] ?? 0 ), 2, '.', '' ),
					'quantity'      => (int) ( $item['quantity'] ?? 1 ),
					'extras'        => $extras_json,
					'extras_total'  => number_format( (float) ( $item['extras_total'] ?? 0 ), 2, '.', '' ),
					'item_total'    => number_format( (float) ( $item['item_total'] ?? 0 ), 2, '.', '' ),
				]
			);
		}

		// Deduct stock immediately if order starts as delivered.
		if ( $status === 'delivered' ) {
			$this->deduct_stock_for_order( $order_id, $branch_id );
		}

		$this->success( [ 'order_id' => $order_id, 'order_number' => $order_number ], 'Order saved.' );
	}

	public function handle_bym_update_order_status(): void {
		$this->check_request();

		$order_id  = (int) ( $_POST['order_id'] ?? 0 );
		$new_status = sanitize_text_field( wp_unslash( $_POST['status'] ?? '' ) );

		if ( $order_id <= 0 ) {
			$this->error( 'Invalid order ID.' );
		}

		$valid_statuses = [ 'pending', 'prepared', 'delivered' ];
		if ( ! in_array( $new_status, $valid_statuses, true ) ) {
			$this->error( 'Invalid status.' );
		}

		$db    = Bymoreish_Database::get_instance();
		$order = $db->get_row_by_id( Bymoreish_Database::TABLE_ORDERS, $order_id );

		if ( ! $order ) {
			$this->error( 'Order not found.' );
		}

		$old_status = $order['status'];

		$db->update_row(
			Bymoreish_Database::TABLE_ORDERS,
			[ 'status' => $new_status ],
			[ 'id' => $order_id ]
		);

		// Deduct stock only when transitioning to 'delivered' for the first time.
		if ( $new_status === 'delivered' && $old_status !== 'delivered' ) {
			$this->deduct_stock_for_order( $order_id, (int) $order['branch_id'] );
		}

		$this->success( null, 'Order status updated.' );
	}

	public function handle_bym_delete_order(): void {
		$this->check_request();
		$this->require_role( 'admin' );

		$order_id = (int) ( $_POST['order_id'] ?? $_POST['id'] ?? 0 );
		if ( $order_id <= 0 ) {
			$this->error( 'Invalid order ID.' );
		}

		$db    = Bymoreish_Database::get_instance();
		$order = $db->get_row_by_id( Bymoreish_Database::TABLE_ORDERS, $order_id );

		if ( ! $order ) {
			$this->error( 'Order not found.' );
		}

		$user      = $this->current_user();
		$today     = current_time( 'Y-m-d' );
		$order_day = $order['order_date'];

		// Admin can only delete same-day orders; superadmin can delete any.
		if ( $user['role'] === 'admin' && $order_day !== $today ) {
			$this->error( 'Admins can only delete orders from today.' );
		}

		$db->delete_rows( Bymoreish_Database::TABLE_ORDER_ITEMS, [ 'order_id' => $order_id ] );
		$db->delete_rows( Bymoreish_Database::TABLE_ORDERS, [ 'id' => $order_id ] );

		$this->success( null, 'Order deleted.' );
	}

	public function handle_bym_get_orders(): void {
		$this->check_request();

		$branch_id = $this->resolve_branch_id( (int) ( $_POST['branch_id'] ?? $_POST['branch'] ?? 0 ) );
		$date_from = sanitize_text_field( wp_unslash( $_POST['date_from'] ?? current_time( 'Y-m-d' ) ) );
		$date_to   = sanitize_text_field( wp_unslash( $_POST['date_to']   ?? current_time( 'Y-m-d' ) ) );
		$status    = sanitize_text_field( wp_unslash( $_POST['status']    ?? '' ) );
		$page      = max( 1, (int) ( $_POST['page'] ?? 1 ) );
		$per_page  = max( 1, (int) ( $_POST['per_page'] ?? 20 ) );

		$db     = Bymoreish_Database::get_instance();
		$orders = $db->get_orders_by_branch_and_date( $branch_id, $date_from, $date_to );

		if ( $status !== '' ) {
			$orders = array_values( array_filter( $orders, fn( $o ) => $o['status'] === $status ) );
		}

		$total          = count( $orders );
		$total_revenue  = 0.0;
		$delivered_count = 0;
		$pending_count   = 0;

		foreach ( $orders as &$order ) {
			$order['items']      = $db->get_order_items( (int) $order['id'] );
			$order['item_count'] = count( $order['items'] );
			$order['total']      = $order['grand_total'];

			// Staff name from session or user lookup.
			if ( ! isset( $order['staff_name'] ) ) {
				$staff = $db->get_row_by_id( Bymoreish_Database::TABLE_USERS, (int) ( $order['staff_id'] ?? 0 ) );
				$order['staff_name'] = $staff['full_name'] ?? 'Unknown';
			}

			if ( $order['status'] === 'delivered' ) {
				$total_revenue += (float) $order['grand_total'];
				++$delivered_count;
			} else {
				++$pending_count;
			}
		}
		unset( $order );

		// Pagination.
		$pages       = (int) ceil( $total / $per_page );
		$offset      = ( $page - 1 ) * $per_page;
		$paged_orders = array_slice( $orders, $offset, $per_page );

		$this->success(
			[
				'orders'        => $paged_orders,
				'total'         => $total,
				'total_revenue' => $total_revenue,
				'delivered'     => $delivered_count,
				'pending'       => $pending_count,
				'pages'         => $pages,
			]
		);
	}

	public function handle_bym_generate_receipt(): void {
		$this->check_request();

		$order_id = (int) ( $_POST['order_id'] ?? 0 );
		if ( $order_id <= 0 ) {
			$this->error( 'Invalid order ID.' );
		}

		$db    = Bymoreish_Database::get_instance();
		$order = $db->get_row_by_id( Bymoreish_Database::TABLE_ORDERS, $order_id );
		if ( ! $order ) {
			$this->error( 'Order not found.' );
		}

		$items    = $db->get_order_items( $order_id );
		$settings = [
			'restaurant_name' => $db->get_setting( 'restaurant_name', 'Bymoreish' ),
			'address'         => $db->get_setting( 'address', 'Behind Marlima' ),
			'phone'           => $db->get_setting( 'phone', '' ),
			'receipt_footer'  => $db->get_setting( 'receipt_footer', 'Thank you for dining with us!' ),
		];

		// Staff name lookup.
		$staff = $db->get_row_by_id( Bymoreish_Database::TABLE_USERS, (int) ( $order['staff_id'] ?? 0 ) );
		$staff_name = $staff['full_name'] ?? 'Unknown';

		// Build receipt HTML for 80mm thermal printer.
		$html = '<div style="max-width:302px;font-family:monospace;font-size:12px;color:#000;padding:8px;">';
		$html .= '<div style="text-align:center;font-weight:bold;font-size:14px;">' . esc_html( $settings['restaurant_name'] ) . '</div>';
		if ( $settings['address'] ) {
			$html .= '<div style="text-align:center;font-size:11px;">' . esc_html( $settings['address'] ) . '</div>';
		}
		if ( $settings['phone'] ) {
			$html .= '<div style="text-align:center;font-size:11px;">' . esc_html( $settings['phone'] ) . '</div>';
		}
		$html .= '<hr style="border:none;border-top:1px dashed #000;margin:6px 0;">';
		$html .= '<div><strong>Order:</strong> ' . esc_html( $order['order_number'] ) . '</div>';
		$html .= '<div><strong>Date:</strong> ' . esc_html( $order['order_date'] . ' ' . ( $order['created_at'] ?? '' ) ) . '</div>';
		$html .= '<div><strong>Staff:</strong> ' . esc_html( $staff_name ) . '</div>';

		if ( ! empty( $order['customer_name'] ) ) {
			$html .= '<div><strong>Customer:</strong> ' . esc_html( $order['customer_name'] ) . '</div>';
		}
		if ( ! empty( $order['customer_phone'] ) ) {
			$html .= '<div><strong>Phone:</strong> ' . esc_html( $order['customer_phone'] ) . '</div>';
		}

		$html .= '<hr style="border:none;border-top:1px dashed #000;margin:6px 0;">';
		$html .= '<table style="width:100%;font-size:11px;">';
		$html .= '<tr><th style="text-align:left;">Item</th><th style="text-align:right;">Qty</th><th style="text-align:right;">Price</th><th style="text-align:right;">Total</th></tr>';

		foreach ( $items as $item ) {
			$html .= '<tr>';
			$html .= '<td>' . esc_html( $item['product_name'] ) . '</td>';
			$html .= '<td style="text-align:right;">' . esc_html( (string) $item['quantity'] ) . '</td>';
			$html .= '<td style="text-align:right;">₦' . number_format( (float) $item['product_price'], 0, '.', ',' ) . '</td>';
			$html .= '<td style="text-align:right;">₦' . number_format( (float) $item['item_total'], 0, '.', ',' ) . '</td>';
			$html .= '</tr>';
			if ( ! empty( $item['extras'] ) ) {
				$extras_data = is_string( $item['extras'] ) ? json_decode( $item['extras'], true ) : $item['extras'];
				if ( is_array( $extras_data ) ) {
					foreach ( $extras_data as $extra ) {
						$html .= '<tr><td style="font-size:10px;color:#555;">&nbsp;&nbsp;+ ' . esc_html( $extra['name'] ?? '' ) . '</td>';
						$html .= '<td></td><td></td>';
						$html .= '<td style="text-align:right;font-size:10px;color:#555;">₦' . number_format( (float) ( $extra['price'] ?? 0 ), 0, '.', ',' ) . '</td></tr>';
					}
				}
			}
		}
		$html .= '</table>';
		$html .= '<hr style="border:none;border-top:1px dashed #000;margin:6px 0;">';
		$html .= '<div style="text-align:right;font-size:13px;font-weight:bold;">Grand Total: ₦' . number_format( (float) $order['grand_total'], 0, '.', ',' ) . '</div>';
		$html .= '<div style="font-size:11px;"><strong>Payment:</strong> ' . esc_html( str_replace( '_', ' + ', $order['payment_mode'] ) ) . '</div>';

		if ( (float) $order['transfer_amount'] > 0 ) {
			$html .= '<div style="font-size:11px;">Transfer: ₦' . number_format( (float) $order['transfer_amount'], 0, '.', ',' ) . '</div>';
		}
		if ( (float) $order['card_amount'] > 0 ) {
			$html .= '<div style="font-size:11px;">Card: ₦' . number_format( (float) $order['card_amount'], 0, '.', ',' ) . '</div>';
		}
		if ( (float) $order['cash_amount'] > 0 ) {
			$html .= '<div style="font-size:11px;">Cash: ₦' . number_format( (float) $order['cash_amount'], 0, '.', ',' ) . '</div>';
		}

		$html .= '<hr style="border:none;border-top:1px dashed #000;margin:6px 0;">';
		$html .= '<div style="text-align:center;font-size:11px;">' . esc_html( $settings['receipt_footer'] ) . '</div>';
		$html .= '<div style="text-align:center;font-size:10px;color:#888;margin-top:4px;">Powered by Bymoreish POS</div>';
		$html .= '</div>';

		$this->success(
			[
				'order'    => $order,
				'items'    => $items,
				'settings' => $settings,
				'html'     => $html,
			]
		);
	}

	// -----------------------------------------------------------------------
	// 4. Stock
	// -----------------------------------------------------------------------

	public function handle_bym_save_stock(): void {
		$this->check_request();

		$user      = $this->current_user();
		$branch_id = $this->resolve_branch_id( (int) ( $_POST['branch_id'] ?? 0 ) );
		$stock_date = sanitize_text_field( wp_unslash( $_POST['stock_date'] ?? current_time( 'Y-m-d' ) ) );

		$entries_raw = wp_unslash( $_POST['entries'] ?? '' );
		$entries     = is_string( $entries_raw ) ? json_decode( $entries_raw, true ) : $entries_raw;

		if ( empty( $entries ) || ! is_array( $entries ) ) {
			$this->error( 'Stock entries are required.' );
		}

		$db      = Bymoreish_Database::get_instance();
		$saved   = 0;

		foreach ( $entries as $entry ) {
			$product_id = (int) ( $entry['product_id'] ?? 0 );
			if ( $product_id <= 0 ) {
				continue;
			}

			// Fetch new_stock from bym_imports for this product/branch/date.
			global $wpdb;
			$imports_table = 'bym_imports';
			$import_row    = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT SUM(quantity) AS qty FROM {$imports_table}
					 WHERE product_id = %d AND branch_id = %d AND import_date = %s",
					$product_id,
					$branch_id,
					$stock_date
				),
				ARRAY_A
			);
			$new_stock = (int) ( $import_row['qty'] ?? 0 );

			// in_stock is the carried-over stock_left from the previous day.
			$prev = $db->get_latest_stock( $product_id, $branch_id );
			// Only carry over if it's from a previous date (not today).
			if ( $prev && $prev['stock_date'] < $stock_date ) {
				$in_stock = max( 0, (int) $prev['stock_left'] );
			} elseif ( $prev && $prev['stock_date'] === $stock_date ) {
				// Re-saving today's entry: use the stored in_stock.
				$in_stock = (int) $prev['in_stock'];
			} else {
				$in_stock = (int) ( $entry['in_stock'] ?? 0 );
			}

			$sold_stock  = (int) ( $entry['sold_stock'] ?? 0 );
			$total_stock = $in_stock + $new_stock;
			$stock_left  = $total_stock - $sold_stock;
			$remarks     = sanitize_textarea_field( $entry['remarks'] ?? '' );

			$row_data = [
				'product_id'  => $product_id,
				'branch_id'   => $branch_id,
				'in_stock'    => $in_stock,
				'new_stock'   => $new_stock,
				'total_stock' => $total_stock,
				'sold_stock'  => $sold_stock,
				'stock_left'  => $stock_left,
				'remarks'     => $remarks,
				'stock_date'  => $stock_date,
				'staff_id'    => $user['id'],
			];

			// Check if a record exists for today.
			$existing = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT id FROM bym_stock
					 WHERE product_id = %d AND branch_id = %d AND stock_date = %s
					 LIMIT 1",
					$product_id,
					$branch_id,
					$stock_date
				),
				ARRAY_A
			);

			if ( $existing ) {
				$db->update_row( Bymoreish_Database::TABLE_STOCK, $row_data, [ 'id' => (int) $existing['id'] ] );
			} else {
				$db->insert_row( Bymoreish_Database::TABLE_STOCK, $row_data );
			}

			++$saved;
		}

		$this->success( [ 'saved' => $saved ], 'Stock saved.' );
	}

	public function handle_bym_get_stock(): void {
		$this->check_request();

		$branch_id  = $this->resolve_branch_id( (int) ( $_POST['branch_id'] ?? $_POST['branch'] ?? 0 ) );
		$stock_date = sanitize_text_field( wp_unslash( $_POST['stock_date'] ?? $_POST['date'] ?? current_time( 'Y-m-d' ) ) );
		$page       = max( 1, (int) ( $_POST['page'] ?? 1 ) );
		$per_page   = max( 1, (int) ( $_POST['per_page'] ?? 50 ) );

		global $wpdb;
		$s_table = 'bym_stock';
		$p_table = 'bym_products';
		$u_table = 'bym_users';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$rows = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT s.*, p.name AS product_name, p.unit AS product_unit,
				        u.full_name AS staff_name
				   FROM {$s_table} s
				   JOIN {$p_table} p ON p.id = s.product_id
				   LEFT JOIN {$u_table} u ON u.id = s.staff_id
				  WHERE s.branch_id  = %d
				    AND s.stock_date = %s
				  ORDER BY p.name ASC",
				$branch_id,
				$stock_date
			),
			ARRAY_A
		) ?: [];

		$total  = count( $rows );
		$pages  = (int) ceil( $total / $per_page );
		$offset = ( $page - 1 ) * $per_page;
		$paged  = array_slice( $rows, $offset, $per_page );

		$this->success(
			[
				'records' => $paged,
				'total'   => $total,
				'pages'   => $pages,
			]
		);
	}

	// -----------------------------------------------------------------------
	// 5. Expenses
	// -----------------------------------------------------------------------

	public function handle_bym_save_expense(): void {
		$this->check_request();

		$user        = $this->current_user();
		$branch_id   = $this->resolve_branch_id( (int) ( $_POST['branch_id'] ?? 0 ) );
		$expense_date = sanitize_text_field( wp_unslash( $_POST['expense_date'] ?? current_time( 'Y-m-d' ) ) );
		$remarks     = sanitize_textarea_field( wp_unslash( $_POST['remarks'] ?? '' ) );

		$items_raw = wp_unslash( $_POST['items'] ?? '' );
		$items     = is_string( $items_raw ) ? json_decode( $items_raw, true ) : $items_raw;

		if ( empty( $items ) || ! is_array( $items ) ) {
			$this->error( 'Expense must contain at least one item.' );
		}

		$grand_total = 0.0;
		foreach ( $items as $item ) {
			$grand_total += (float) ( $item['total'] ?? 0 );
		}

		$db         = Bymoreish_Database::get_instance();
		$expense_id = $db->insert_row(
			Bymoreish_Database::TABLE_EXPENSES,
			[
				'branch_id'    => $branch_id,
				'staff_id'     => $user['id'],
				'expense_date' => $expense_date,
				'grand_total'  => number_format( $grand_total, 2, '.', '' ),
				'remarks'      => $remarks,
			]
		);

		if ( ! $expense_id ) {
			$this->error( 'Failed to save expense.' );
		}

		foreach ( $items as $item ) {
			$qty   = max( 1, (int) ( $item['quantity'] ?? 1 ) );
			$price = (float) ( $item['price'] ?? 0 );
			$total = (float) ( $item['total'] ?? ( $qty * $price ) );
			$db->insert_row(
				Bymoreish_Database::TABLE_EXPENSE_ITEMS,
				[
					'expense_id'  => $expense_id,
					'description' => sanitize_text_field( $item['description'] ?? '' ),
					'price'       => number_format( $price, 2, '.', '' ),
					'quantity'    => $qty,
					'total'       => number_format( $total, 2, '.', '' ),
				]
			);
		}

		$this->success( [ 'expense_id' => $expense_id ], 'Expense saved.' );
	}

	public function handle_bym_get_expenses(): void {
		$this->check_request();

		$branch_id = $this->resolve_branch_id( (int) ( $_POST['branch_id'] ?? $_POST['branch'] ?? 0 ) );
		$date_from = sanitize_text_field( wp_unslash( $_POST['date_from'] ?? current_time( 'Y-m-d' ) ) );
		$date_to   = sanitize_text_field( wp_unslash( $_POST['date_to']   ?? current_time( 'Y-m-d' ) ) );
		$page      = max( 1, (int) ( $_POST['page'] ?? 1 ) );
		$per_page  = max( 1, (int) ( $_POST['per_page'] ?? 20 ) );

		global $wpdb;
		$e_table = 'bym_expenses';
		$u_table = 'bym_users';

		// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
		$all_expenses = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT e.*, u.full_name AS staff_name
				   FROM {$e_table} e
				   LEFT JOIN {$u_table} u ON u.id = e.staff_id
				  WHERE e.branch_id    = %d
				    AND e.expense_date BETWEEN %s AND %s
				  ORDER BY e.expense_date DESC, e.id DESC",
				$branch_id,
				$date_from,
				$date_to
			),
			ARRAY_A
		) ?: [];

		$db = Bymoreish_Database::get_instance();
		$total = count( $all_expenses );
		$monthly_total = 0.0;

		foreach ( $all_expenses as &$expense ) {
			$expense['items']      = $db->get_rows(
				Bymoreish_Database::TABLE_EXPENSE_ITEMS,
				[ 'expense_id' => (int) $expense['id'] ]
			);
			$expense['item_count'] = count( $expense['items'] );
			$expense['date']       = $expense['expense_date'];
			$monthly_total        += (float) $expense['grand_total'];
		}
		unset( $expense );

		// Pagination.
		$pages    = (int) ceil( $total / $per_page );
		$offset   = ( $page - 1 ) * $per_page;
		$paged    = array_slice( $all_expenses, $offset, $per_page );

		$this->success(
			[
				'expenses'      => $paged,
				'total'         => $total,
				'monthly_total' => $monthly_total,
				'pages'         => $pages,
			]
		);
	}

	public function handle_bym_delete_expense(): void {
		$this->check_request();
		$this->require_role( 'admin' );

		$expense_id = (int) ( $_POST['expense_id'] ?? $_POST['id'] ?? 0 );
		if ( $expense_id <= 0 ) {
			$this->error( 'Invalid expense ID.' );
		}

		$db      = Bymoreish_Database::get_instance();
		$expense = $db->get_row_by_id( Bymoreish_Database::TABLE_EXPENSES, $expense_id );

		if ( ! $expense ) {
			$this->error( 'Expense not found.' );
		}

		$user  = $this->current_user();
		$today = current_time( 'Y-m-d' );

		if ( $user['role'] === 'admin' && $expense['expense_date'] !== $today ) {
			$this->error( 'Admins can only delete expenses from today.' );
		}

		$db->delete_rows( Bymoreish_Database::TABLE_EXPENSE_ITEMS, [ 'expense_id' => $expense_id ] );
		$db->delete_rows( Bymoreish_Database::TABLE_EXPENSES, [ 'id' => $expense_id ] );

		$this->success( null, 'Expense deleted.' );
	}

	// -----------------------------------------------------------------------
	// 6. Financial summary
	// -----------------------------------------------------------------------

	public function handle_bym_get_financial_summary(): void {
		$this->check_request();

		$branch_id = $this->resolve_branch_id( (int) ( $_POST['branch_id'] ?? $_POST['branch'] ?? 0 ) );
		$date_from = sanitize_text_field( wp_unslash( $_POST['date_from'] ?? current_time( 'Y-m-d' ) ) );
		$date_to   = sanitize_text_field( wp_unslash( $_POST['date_to']   ?? current_time( 'Y-m-d' ) ) );

		$db   = Bymoreish_Database::get_instance();
		$rows = $db->get_financial_summary( $branch_id, $date_from, $date_to );

		// Compute aggregate totals across all rows.
		$totals = [
			'total_sales'    => 0.0,
			'transfer_sales' => 0.0,
			'card_sales'     => 0.0,
			'cash_sales'     => 0.0,
			'total_expenses' => 0.0,
			'profit'         => 0.0,
		];
		foreach ( $rows as $row ) {
			$totals['total_sales']    += (float) ( $row['total_sales'] ?? 0 );
			$totals['transfer_sales'] += (float) ( $row['transfer_sales'] ?? 0 );
			$totals['card_sales']     += (float) ( $row['card_sales'] ?? 0 );
			$totals['cash_sales']     += (float) ( $row['cash_sales'] ?? 0 );
			$totals['total_expenses'] += (float) ( $row['total_expenses'] ?? 0 );
			$totals['profit']         += (float) ( $row['profit'] ?? 0 );
		}

		$this->success(
			[
				'rows'   => $rows,
				'totals' => $totals,
			]
		);
	}

	public function handle_bym_save_financial_summary(): void {
		$this->check_request();
		$this->require_role( 'admin' );

		$branch_id     = $this->resolve_branch_id( (int) ( $_POST['branch_id'] ?? 0 ) );
		$summary_date  = sanitize_text_field( wp_unslash( $_POST['summary_date'] ?? current_time( 'Y-m-d' ) ) );
		$total_sales   = (float) ( $_POST['total_sales']    ?? 0 );
		$transfer_sales = (float) ( $_POST['transfer_sales'] ?? 0 );
		$card_sales    = (float) ( $_POST['card_sales']     ?? 0 );
		$cash_sales    = (float) ( $_POST['cash_sales']     ?? 0 );
		$total_expenses = (float) ( $_POST['total_expenses'] ?? 0 );
		$profit        = $total_sales - $total_expenses;

		global $wpdb;
		$fs_table = 'bym_financial_summary';

		$wpdb->query(
			$wpdb->prepare(
				"INSERT INTO {$fs_table}
				 (branch_id, summary_date, total_sales, transfer_sales, card_sales, cash_sales, total_expenses, profit)
				 VALUES (%d, %s, %f, %f, %f, %f, %f, %f)
				 ON DUPLICATE KEY UPDATE
				   total_sales    = VALUES(total_sales),
				   transfer_sales = VALUES(transfer_sales),
				   card_sales     = VALUES(card_sales),
				   cash_sales     = VALUES(cash_sales),
				   total_expenses = VALUES(total_expenses),
				   profit         = VALUES(profit)",
				$branch_id,
				$summary_date,
				$total_sales,
				$transfer_sales,
				$card_sales,
				$cash_sales,
				$total_expenses,
				$profit
			)
		);

		$this->success( [ 'profit' => $profit ], 'Financial summary saved.' );
	}

	// -----------------------------------------------------------------------
	// 7. Analytics
	// -----------------------------------------------------------------------

	public function handle_bym_get_analytics(): void {
		$this->check_request();

		$branch_id = $this->resolve_branch_id( (int) ( $_POST['branch_id'] ?? 0 ) );
		$date_from = sanitize_text_field( wp_unslash( $_POST['date_from'] ?? gmdate( 'Y-m-01', current_time( 'timestamp' ) ) ) );
		$date_to   = sanitize_text_field( wp_unslash( $_POST['date_to']   ?? current_time( 'Y-m-d' ) ) );

		global $wpdb;
		$o_table  = 'bym_orders';
		$oi_table = 'bym_order_items';
		$e_table  = 'bym_expenses';

		// Daily revenue.
		$daily_revenue = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT order_date AS date,
				        SUM(grand_total)    AS total_revenue,
				        SUM(transfer_amount) AS transfer,
				        SUM(card_amount)    AS card,
				        SUM(cash_amount)    AS cash,
				        COUNT(id)           AS order_count
				   FROM {$o_table}
				  WHERE branch_id  = %d
				    AND order_date BETWEEN %s AND %s
				    AND status     = 'delivered'
				  GROUP BY order_date
				  ORDER BY order_date ASC",
				$branch_id,
				$date_from,
				$date_to
			),
			ARRAY_A
		) ?: [];

		// Daily expenses.
		$daily_expenses = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT expense_date AS date, SUM(grand_total) AS total_expenses
				   FROM {$e_table}
				  WHERE branch_id    = %d
				    AND expense_date BETWEEN %s AND %s
				  GROUP BY expense_date
				  ORDER BY expense_date ASC",
				$branch_id,
				$date_from,
				$date_to
			),
			ARRAY_A
		) ?: [];

		// Top products by quantity sold.
		$top_products = $wpdb->get_results(
			$wpdb->prepare(
				"SELECT oi.product_name,
				        SUM(oi.quantity)   AS total_qty,
				        SUM(oi.item_total) AS total_revenue
				   FROM {$oi_table} oi
				   JOIN {$o_table} o ON o.id = oi.order_id
				  WHERE o.branch_id  = %d
				    AND o.order_date BETWEEN %s AND %s
				    AND o.status     = 'delivered'
				  GROUP BY oi.product_id, oi.product_name
				  ORDER BY total_qty DESC
				  LIMIT 10",
				$branch_id,
				$date_from,
				$date_to
			),
			ARRAY_A
		) ?: [];

		// Summary totals.
		$totals = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT SUM(grand_total)     AS total_revenue,
				        SUM(transfer_amount) AS total_transfer,
				        SUM(card_amount)     AS total_card,
				        SUM(cash_amount)     AS total_cash,
				        COUNT(id)            AS total_orders
				   FROM {$o_table}
				  WHERE branch_id  = %d
				    AND order_date BETWEEN %s AND %s
				    AND status     = 'delivered'",
				$branch_id,
				$date_from,
				$date_to
			),
			ARRAY_A
		) ?: [];

		$total_expenses_sum = $wpdb->get_var(
			$wpdb->prepare(
				"SELECT SUM(grand_total) FROM {$e_table}
				  WHERE branch_id = %d AND expense_date BETWEEN %s AND %s",
				$branch_id,
				$date_from,
				$date_to
			)
		);

		$this->success(
			[
				'daily_revenue'       => $daily_revenue,
				'daily_expenses'      => $daily_expenses,
				'top_products'        => $top_products,
				'totals'              => $totals,
				'total_expenses'      => (float) ( $total_expenses_sum ?? 0 ),
				'net_profit'          => (float) ( $totals['total_revenue'] ?? 0 ) - (float) ( $total_expenses_sum ?? 0 ),
			]
		);
	}

	// -----------------------------------------------------------------------
	// 8. User management
	// -----------------------------------------------------------------------

	public function handle_bym_save_user(): void {
		$this->check_request();
		$this->require_role( 'admin' );

		$current_user = $this->current_user();
		$id           = (int) ( $_POST['id'] ?? 0 );
		$username     = sanitize_text_field( wp_unslash( $_POST['username']  ?? '' ) );
		$full_name    = sanitize_text_field( wp_unslash( $_POST['full_name'] ?? '' ) );
		$email        = sanitize_email( wp_unslash( $_POST['email']          ?? '' ) );
		$phone        = sanitize_text_field( wp_unslash( $_POST['phone']     ?? '' ) );
		$role         = sanitize_text_field( wp_unslash( $_POST['role']      ?? 'staff' ) );
		$branch       = sanitize_text_field( wp_unslash( $_POST['branch']    ?? '' ) );
		$is_active    = (int) ( (bool) ( $_POST['is_active'] ?? 1 ) );

		if ( empty( $username ) || empty( $full_name ) ) {
			$this->error( 'Username and full name are required.' );
		}

		// Only superadmin can create/edit other superadmins.
		if ( $role === 'superadmin' && $current_user['role'] !== 'superadmin' ) {
			$this->error( 'Only superadmins can assign the superadmin role.' );
		}

		$db   = Bymoreish_Database::get_instance();
		$data = [
			'username'  => $username,
			'full_name' => $full_name,
			'email'     => $email,
			'phone'     => $phone,
			'role'      => $role,
			'branch'    => $branch,
			'is_active' => $is_active,
		];

		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$password = wp_unslash( $_POST['password'] ?? '' );

		if ( $id > 0 ) {
			// Update existing user.
			if ( ! empty( $password ) ) {
				if ( strlen( $password ) < 6 ) {
					$this->error( 'Password must be at least 6 characters.' );
				}
				$data['password_hash'] = wp_hash_password( $password );
			}
			$db->update_row( Bymoreish_Database::TABLE_USERS, $data, [ 'id' => $id ] );
		} else {
			// Create new user.
			if ( empty( $password ) ) {
				$this->error( 'Password is required for new users.' );
			}
			if ( strlen( $password ) < 6 ) {
				$this->error( 'Password must be at least 6 characters.' );
			}
			$data['password_hash'] = wp_hash_password( $password );
			$id = $db->insert_row( Bymoreish_Database::TABLE_USERS, $data );
			if ( ! $id ) {
				$this->error( 'Failed to create user. Username may already exist.' );
			}
		}

		$this->success( [ 'id' => $id ], 'User saved.' );
	}

	public function handle_bym_delete_user(): void {
		$this->check_request();
		$this->require_role( 'superadmin' );

		$user_id = (int) ( $_POST['user_id'] ?? $_POST['id'] ?? 0 );
		if ( $user_id <= 0 ) {
			$this->error( 'Invalid user ID.' );
		}

		// Prevent self-deletion.
		$current = $this->current_user();
		if ( (int) $current['id'] === $user_id ) {
			$this->error( 'You cannot delete your own account.' );
		}

		$db = Bymoreish_Database::get_instance();
		$db->update_row( Bymoreish_Database::TABLE_USERS, [ 'is_active' => 0 ], [ 'id' => $user_id ] );

		$this->success( null, 'User deleted.' );
	}

	public function handle_bym_upload_profile_picture(): void {
		$this->check_request();

		$user      = $this->current_user();
		$target_id = (int) ( $_POST['user_id'] ?? $_POST['id'] ?? $user['id'] );

		// Non-superadmin users can only update their own picture.
		if ( $user['role'] !== 'superadmin' && $target_id !== (int) $user['id'] ) {
			$this->error( 'You can only update your own profile picture.', 403 );
		}

		// Accept file from either 'profile_picture' or 'avatar' field names.
		if ( ! empty( $_FILES['profile_picture'] ) ) {
			$file = $_FILES['profile_picture'];
		} elseif ( ! empty( $_FILES['avatar'] ) ) {
			$file = $_FILES['avatar'];
		} else {
			$this->error( 'No file uploaded.' );
		}

		if ( $file['error'] !== UPLOAD_ERR_OK ) {
			$this->error( 'File upload error.' );
		}

		// Validate MIME type.
		$allowed_types = [ 'image/jpeg', 'image/png', 'image/gif', 'image/webp' ];
		$file_type     = wp_check_filetype( basename( $file['name'] ), null );
		$mime          = mime_content_type( $file['tmp_name'] );

		if ( ! in_array( $mime, $allowed_types, true ) ) {
			$this->error( 'Only JPEG, PNG, GIF, and WebP images are allowed.' );
		}

		if ( $file['size'] > 2 * 1024 * 1024 ) {
			$this->error( 'File size must not exceed 2 MB.' );
		}

		// Use WordPress upload directory.
		$upload_dir = wp_upload_dir();
		$dest_dir   = $upload_dir['basedir'] . '/bymoreish/profiles/';
		wp_mkdir_p( $dest_dir );

		$ext       = strtolower( pathinfo( $file['name'], PATHINFO_EXTENSION ) );
		$filename  = wp_unique_filename(
			$dest_dir,
			'user-' . $target_id . '-' . time() . '-' . bin2hex( random_bytes( 4 ) ) . '.' . $ext
		);
		$dest_path = $dest_dir . $filename;

		if ( ! move_uploaded_file( $file['tmp_name'], $dest_path ) ) {
			$this->error( 'Failed to save uploaded file.' );
		}

		$url = $upload_dir['baseurl'] . '/bymoreish/profiles/' . $filename;

		// Update the database.
		$db = Bymoreish_Database::get_instance();
		$db->update_row( Bymoreish_Database::TABLE_USERS, [ 'profile_picture' => $url ], [ 'id' => $target_id ] );

		$this->success( [ 'url' => $url ], 'Profile picture updated.' );
	}

	// -----------------------------------------------------------------------
	// 9. Settings
	// -----------------------------------------------------------------------

	public function handle_bym_get_settings(): void {
		$this->check_request();
		$this->require_role( 'admin' );

		$db   = Bymoreish_Database::get_instance();
		$keys = [
			'restaurant_name',
			'address',
			'phone',
			'email',
			'receipt_footer',
			'currency_symbol',
			'tax_rate',
			'logo_url',
		];

		$settings = [];
		foreach ( $keys as $key ) {
			$settings[ $key ] = $db->get_setting( $key, '' );
		}

		$this->success( $settings );
	}

	public function handle_bym_update_settings(): void {
		$this->check_request();
		$this->require_role( 'admin' );

		$allowed = [
			'restaurant_name',
			'address',
			'phone',
			'email',
			'receipt_footer',
			'currency_symbol',
			'tax_rate',
			'logo_url',
		];

		$db = Bymoreish_Database::get_instance();

		foreach ( $allowed as $key ) {
			if ( isset( $_POST[ $key ] ) ) {
				$value = sanitize_text_field( wp_unslash( $_POST[ $key ] ) );
				$db->update_setting( $key, $value );
			}
		}

		$this->success( null, 'Settings updated.' );
	}

	// -----------------------------------------------------------------------
	// 10. Superadmin: delete all records
	// -----------------------------------------------------------------------

	public function handle_bym_delete_all_records(): void {
		$this->check_request();
		$this->require_role( 'superadmin' );

		$confirm = sanitize_text_field( wp_unslash( $_POST['confirm'] ?? '' ) );
		if ( $confirm !== 'DELETE_ALL' ) {
			$this->error( 'Confirmation text is required. Send confirm=DELETE_ALL.' );
		}

		global $wpdb;

		$tables = [
			'bym_order_items',
			'bym_orders',
			'bym_stock',
			'bym_imports',
			'bym_expense_items',
			'bym_expenses',
			'bym_financial_summary',
		];

		foreach ( $tables as $table ) {
			// phpcs:ignore WordPress.DB.PreparedSQL.InterpolatedNotPrepared
			$wpdb->query( "TRUNCATE TABLE {$table}" );
		}

		$this->success( null, 'All records deleted.' );
	}

	// -----------------------------------------------------------------------
	// 11. Helper: format currency
	// -----------------------------------------------------------------------

	public function handle_bym_format_currency(): void {
		$amount = (float) ( $_POST['amount'] ?? $_GET['amount'] ?? 0 );
		$symbol = sanitize_text_field( wp_unslash( $_POST['symbol'] ?? '₦' ) );
		$formatted = $symbol . number_format( $amount, 2 );
		$this->success( [ 'formatted' => $formatted, 'amount' => $amount ] );
	}

	// -----------------------------------------------------------------------
	// 12. Dashboard KPIs
	// -----------------------------------------------------------------------

	public function handle_bym_dashboard_kpis(): void {
		$this->check_request();

		$user      = $this->current_user();
		$branch_id = $this->resolve_branch_id( null );
		$today     = current_time( 'Y-m-d' );

		global $wpdb;
		$o_table = 'bym_orders';
		$s_table = 'bym_stock';
		$e_table = 'bym_expenses';

		// Today's revenue (delivered orders).
		$today_revenue = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(grand_total), 0) FROM {$o_table}
				 WHERE branch_id = %d AND order_date = %s AND status = 'delivered'",
				$branch_id,
				$today
			)
		);

		// Today's order count.
		$today_orders = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM {$o_table}
				 WHERE branch_id = %d AND order_date = %s",
				$branch_id,
				$today
			)
		);

		// Pending orders (not yet delivered).
		$pending_orders = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(id) FROM {$o_table}
				 WHERE branch_id = %d AND order_date = %s AND status != 'delivered'",
				$branch_id,
				$today
			)
		);

		// Low stock alerts (items with stock_left <= 5 from latest stock date).
		$low_stock = (int) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COUNT(*) FROM {$s_table}
				 WHERE branch_id = %d AND stock_date = %s AND stock_left <= 5",
				$branch_id,
				$today
			)
		);

		// Today's expenses.
		$today_expenses = (float) $wpdb->get_var(
			$wpdb->prepare(
				"SELECT COALESCE(SUM(grand_total), 0) FROM {$e_table}
				 WHERE branch_id = %d AND expense_date = %s",
				$branch_id,
				$today
			)
		);

		$this->success(
			[
				'today_revenue'   => $today_revenue,
				'today_orders'    => $today_orders,
				'pending_orders'  => $pending_orders,
				'low_stock'       => $low_stock,
				'today_expenses'  => $today_expenses,
				'today_profit'    => $today_revenue - $today_expenses,
			]
		);
	}

	// -----------------------------------------------------------------------
	// 13. Change password
	// -----------------------------------------------------------------------

	public function handle_bym_change_password(): void {
		$this->check_request();

		$user_id          = (int) ( $_POST['id'] ?? 0 );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$current_password = wp_unslash( $_POST['current_password'] ?? '' );
		// phpcs:ignore WordPress.Security.ValidatedSanitizedInput.InputNotSanitized
		$new_password     = wp_unslash( $_POST['new_password'] ?? '' );

		$current_user = $this->current_user();

		// Users can only change their own password unless superadmin.
		if ( $current_user['role'] !== 'superadmin' && (int) $current_user['id'] !== $user_id ) {
			$this->error( 'You can only change your own password.', 403 );
		}

		if ( empty( $current_password ) || empty( $new_password ) ) {
			$this->error( 'Current password and new password are required.' );
		}

		if ( strlen( $new_password ) < 6 ) {
			$this->error( 'New password must be at least 6 characters.' );
		}

		$auth   = Bymoreish_Auth::get_instance();
		$result = $auth->change_password( $user_id, $current_password, $new_password );

		if ( ! $result ) {
			$this->error( 'Current password is incorrect or user not found.' );
		}

		$this->success( null, 'Password changed successfully.' );
	}

	// -----------------------------------------------------------------------
	// 14. Get order items (for history expansion)
	// -----------------------------------------------------------------------

	public function handle_bym_get_order_items(): void {
		$this->check_request();

		$order_id = (int) ( $_POST['order_id'] ?? 0 );
		if ( $order_id <= 0 ) {
			$this->error( 'Invalid order ID.' );
		}

		$db    = Bymoreish_Database::get_instance();
		$items = $db->get_order_items( $order_id );

		// Map field names for frontend consumption.
		$mapped = array_map( function ( $item ) {
			return [
				'id'         => $item['id'] ?? 0,
				'name'       => $item['product_name'] ?? '',
				'qty'        => $item['quantity'] ?? 0,
				'unit_price' => $item['product_price'] ?? '0.00',
				'extras'     => $item['extras'] ?? '',
				'total'      => $item['item_total'] ?? '0.00',
			];
		}, $items );

		$this->success( $mapped );
	}

	// -----------------------------------------------------------------------
	// 15. Get expense items (for history expansion)
	// -----------------------------------------------------------------------

	public function handle_bym_get_expense_items(): void {
		$this->check_request();

		$expense_id = (int) ( $_POST['expense_id'] ?? 0 );
		if ( $expense_id <= 0 ) {
			$this->error( 'Invalid expense ID.' );
		}

		$db    = Bymoreish_Database::get_instance();
		$items = $db->get_rows(
			Bymoreish_Database::TABLE_EXPENSE_ITEMS,
			[ 'expense_id' => $expense_id ]
		);

		// Map field names for frontend consumption.
		$mapped = array_map( function ( $item ) {
			return [
				'id'        => $item['id'] ?? 0,
				'name'      => $item['description'] ?? '',
				'qty'       => $item['quantity'] ?? 0,
				'unit_cost' => $item['price'] ?? '0.00',
				'total'     => $item['total'] ?? '0.00',
			];
		}, $items );

		$this->success( $mapped );
	}

	// -----------------------------------------------------------------------
	// 16. Get users (for admin panel)
	// -----------------------------------------------------------------------

	public function handle_bym_get_users(): void {
		$this->check_request();
		$this->require_role( 'admin' );

		$db    = Bymoreish_Database::get_instance();
		$users = $db->get_rows( Bymoreish_Database::TABLE_USERS, [], 'full_name ASC' );

		// Strip password hashes from the response.
		foreach ( $users as &$user ) {
			unset( $user['password_hash'] );
		}
		unset( $user );

		$this->success( $users );
	}

	// -----------------------------------------------------------------------
	// 17. Get receipt (alias for generate_receipt, used in history pages)
	// -----------------------------------------------------------------------

	public function handle_bym_get_receipt(): void {
		$this->handle_bym_generate_receipt();
	}

	// -----------------------------------------------------------------------
	// Private helpers
	// -----------------------------------------------------------------------

	/**
	 * Allocate payment amounts based on the selected payment mode.
	 *
	 * Logic:
	 * - Single modes: full grand_total goes to that mode.
	 * - Combo modes: first channel absorbs the full total; the user provides
	 *   the amount for the second channel, which is deducted from the first.
	 *
	 * @param string $mode
	 * @param float  $grand_total
	 * @param float  $card_amount      User-provided card amount (for combos).
	 * @param float  $cash_amount      User-provided cash amount (for combos).
	 * @param float  $transfer_amount  User-provided transfer amount (for 'all').
	 * @return float[]  [transfer, card, cash]
	 */
	private function allocate_payment(
		string $mode,
		float  $grand_total,
		float  $card_amount,
		float  $cash_amount,
		float  $transfer_amount
	): array {
		switch ( $mode ) {
			case 'transfer':
				return [ $grand_total, 0.0, 0.0 ];

			case 'card':
				return [ 0.0, $grand_total, 0.0 ];

			case 'cash':
				return [ 0.0, 0.0, $grand_total ];

			case 'transfer_card':
				// Card amount provided; transfer absorbs the remainder.
				$card     = min( $card_amount, $grand_total );
				$transfer = $grand_total - $card;
				return [ $transfer, $card, 0.0 ];

			case 'transfer_cash':
				// Cash amount provided; transfer absorbs the remainder.
				$cash     = min( $cash_amount, $grand_total );
				$transfer = $grand_total - $cash;
				return [ $transfer, 0.0, $cash ];

			case 'card_cash':
				// Cash amount provided; card absorbs the remainder.
				$cash = min( $cash_amount, $grand_total );
				$card = $grand_total - $cash;
				return [ 0.0, $card, $cash ];

			case 'all':
				// Transfer is provided, then card, then cash absorbs remainder.
				$transfer = min( $transfer_amount, $grand_total );
				$card     = min( $card_amount, $grand_total - $transfer );
				$cash     = $grand_total - $transfer - $card;
				return [ $transfer, $card, $cash ];

			default:
				return [ 0.0, 0.0, $grand_total ];
		}
	}

	/**
	 * Deduct sold quantities from stock for every item in the given order.
	 *
	 * Increments bym_stock.sold_stock and recalculates stock_left for the
	 * order's date. Creates a new stock row if none exists for that day.
	 *
	 * @param int $order_id
	 * @param int $branch_id
	 */
	private function deduct_stock_for_order( int $order_id, int $branch_id ): void {
		$db    = Bymoreish_Database::get_instance();
		$items = $db->get_order_items( $order_id );

		$order = $db->get_row_by_id( Bymoreish_Database::TABLE_ORDERS, $order_id );
		if ( ! $order ) {
			return;
		}
		$order_date = $order['order_date'];

		global $wpdb;

		foreach ( $items as $item ) {
			$product_id = (int) ( $item['product_id'] ?? 0 );
			if ( $product_id <= 0 ) {
				continue;
			}

			$qty = (int) ( $item['quantity'] ?? 1 );

			// Find or create today's stock record for this product/branch.
			$stock_row = $wpdb->get_row(
				$wpdb->prepare(
					"SELECT * FROM bym_stock
					 WHERE product_id = %d AND branch_id = %d AND stock_date = %s
					 LIMIT 1",
					$product_id,
					$branch_id,
					$order_date
				),
				ARRAY_A
			);

			if ( $stock_row ) {
				$new_sold  = (int) $stock_row['sold_stock'] + $qty;
				$new_left  = (int) $stock_row['total_stock'] - $new_sold;
				$db->update_row(
					Bymoreish_Database::TABLE_STOCK,
					[
						'sold_stock' => $new_sold,
						'stock_left' => $new_left,
					],
					[ 'id' => (int) $stock_row['id'] ]
				);
			} else {
				// No record for today: pull carried-over in_stock from previous day.
				$prev      = $db->get_latest_stock( $product_id, $branch_id );
				$in_stock  = ( $prev && $prev['stock_date'] < $order_date )
					? max( 0, (int) $prev['stock_left'] )
					: 0;

				$db->insert_row(
					Bymoreish_Database::TABLE_STOCK,
					[
						'product_id'  => $product_id,
						'branch_id'   => $branch_id,
						'in_stock'    => $in_stock,
						'new_stock'   => 0,
						'total_stock' => $in_stock,
						'sold_stock'  => $qty,
						'stock_left'  => $in_stock - $qty,
						'stock_date'  => $order_date,
					]
				);
			}
		}
	}
}
