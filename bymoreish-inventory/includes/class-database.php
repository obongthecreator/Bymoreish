<?php
/**
 * Database class – manages all custom tables for Bymoreish Inventory.
 *
 * Table prefix: bym_
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class Bymoreish_Database {

	/** @var Bymoreish_Database|null */
	private static $instance = null;

	/** @var wpdb */
	private $wpdb;

	// Table name constants (without $wpdb->prefix – we use our own prefix).
	const TABLE_USERS             = 'bym_users';
	const TABLE_BRANCHES          = 'bym_branches';
	const TABLE_PRODUCTS          = 'bym_products';
	const TABLE_PRODUCT_EXTRAS    = 'bym_product_extras';
	const TABLE_ORDERS            = 'bym_orders';
	const TABLE_ORDER_ITEMS       = 'bym_order_items';
	const TABLE_STOCK             = 'bym_stock';
	const TABLE_IMPORTS           = 'bym_imports';
	const TABLE_EXPENSES          = 'bym_expenses';
	const TABLE_EXPENSE_ITEMS     = 'bym_expense_items';
	const TABLE_FINANCIAL_SUMMARY = 'bym_financial_summary';
	const TABLE_SETTINGS          = 'bym_settings';

	// -----------------------------------------------------------------------
	// Singleton
	// -----------------------------------------------------------------------

	private function __construct() {
		global $wpdb;
		$this->wpdb = $wpdb;
	}

	public static function get_instance(): self {
		if ( self::$instance === null ) {
			self::$instance = new self();
		}
		return self::$instance;
	}

	// -----------------------------------------------------------------------
	// Table creation
	// -----------------------------------------------------------------------

	/**
	 * Creates all custom tables.  Safe to call on every activation because
	 * every statement uses CREATE TABLE IF NOT EXISTS.
	 */
	public function create_tables() {
		require_once ABSPATH . 'wp-admin/includes/upgrade.php';

		$charset_collate = $this->wpdb->get_charset_collate();

		$this->create_table_users( $charset_collate );
		$this->create_table_branches( $charset_collate );
		$this->create_table_products( $charset_collate );
		$this->create_table_product_extras( $charset_collate );
		$this->create_table_orders( $charset_collate );
		$this->create_table_order_items( $charset_collate );
		$this->create_table_stock( $charset_collate );
		$this->create_table_imports( $charset_collate );
		$this->create_table_expenses( $charset_collate );
		$this->create_table_expense_items( $charset_collate );
		$this->create_table_financial_summary( $charset_collate );
		$this->create_table_settings( $charset_collate );
	}

	// -- Individual table creators ------------------------------------------

	private function create_table_users( string $charset_collate ) {
		$table = $this->table( self::TABLE_USERS );
		$sql   = "CREATE TABLE IF NOT EXISTS {$table} (
			id               BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			username         VARCHAR(100)        NOT NULL,
			password_hash    VARCHAR(255)        NOT NULL,
			full_name        VARCHAR(200)        NOT NULL DEFAULT '',
			email            VARCHAR(200)        NOT NULL DEFAULT '',
			phone            VARCHAR(50)         NOT NULL DEFAULT '',
			role             ENUM('superadmin','admin','staff') NOT NULL DEFAULT 'staff',
			branch           VARCHAR(100)        NOT NULL DEFAULT '',
			profile_picture  VARCHAR(500)        NOT NULL DEFAULT '',
			created_at       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at       DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			is_active        TINYINT(1)          NOT NULL DEFAULT 1,
			PRIMARY KEY  (id),
			UNIQUE KEY username (username)
		) {$charset_collate};";
		$this->wpdb->query( $sql );
	}

	private function create_table_branches( string $charset_collate ) {
		$table = $this->table( self::TABLE_BRANCHES );
		$sql   = "CREATE TABLE IF NOT EXISTS {$table} (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name        VARCHAR(200)        NOT NULL,
			slug        VARCHAR(200)        NOT NULL,
			description TEXT                         DEFAULT NULL,
			status      ENUM('active','coming_soon') NOT NULL DEFAULT 'active',
			created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY slug (slug)
		) {$charset_collate};";
		$this->wpdb->query( $sql );
	}

	private function create_table_products( string $charset_collate ) {
		$table   = $this->table( self::TABLE_PRODUCTS );
		$branches = $this->table( self::TABLE_BRANCHES );
		$sql     = "CREATE TABLE IF NOT EXISTS {$table} (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			name        VARCHAR(300)        NOT NULL,
			category    VARCHAR(200)        NOT NULL DEFAULT '',
			price       DECIMAL(10,2)       NOT NULL DEFAULT '0.00',
			unit        VARCHAR(100)        NOT NULL DEFAULT '',
			description TEXT                         DEFAULT NULL,
			is_active   TINYINT(1)          NOT NULL DEFAULT 1,
			branch_id   BIGINT(20) UNSIGNED          DEFAULT NULL,
			created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY branch_id (branch_id)
		) {$charset_collate};";
		$this->wpdb->query( $sql );
	}

	private function create_table_product_extras( string $charset_collate ) {
		$table    = $this->table( self::TABLE_PRODUCT_EXTRAS );
		$products = $this->table( self::TABLE_PRODUCTS );
		$sql      = "CREATE TABLE IF NOT EXISTS {$table} (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id  BIGINT(20) UNSIGNED NOT NULL,
			extra_name  VARCHAR(200)        NOT NULL,
			extra_price DECIMAL(10,2)       NOT NULL DEFAULT '0.00',
			is_active   TINYINT(1)          NOT NULL DEFAULT 1,
			PRIMARY KEY (id),
			KEY product_id (product_id)
		) {$charset_collate};";
		$this->wpdb->query( $sql );
	}

	private function create_table_orders( string $charset_collate ) {
		$table = $this->table( self::TABLE_ORDERS );
		$sql   = "CREATE TABLE IF NOT EXISTS {$table} (
			id                BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_number      VARCHAR(50)         NOT NULL,
			branch_id         BIGINT(20) UNSIGNED          DEFAULT NULL,
			staff_id          BIGINT(20) UNSIGNED          DEFAULT NULL,
			customer_name     VARCHAR(200)        NOT NULL DEFAULT '',
			customer_phone    VARCHAR(50)         NOT NULL DEFAULT '',
			customer_type     ENUM('new','old')   NOT NULL DEFAULT 'new',
			customer_remarks  TEXT                         DEFAULT NULL,
			payment_mode      ENUM(
			                      'transfer',
			                      'card',
			                      'cash',
			                      'transfer_card',
			                      'transfer_cash',
			                      'card_cash',
			                      'all'
			                  )                   NOT NULL DEFAULT 'cash',
			transfer_amount   DECIMAL(10,2)       NOT NULL DEFAULT '0.00',
			card_amount       DECIMAL(10,2)       NOT NULL DEFAULT '0.00',
			cash_amount       DECIMAL(10,2)       NOT NULL DEFAULT '0.00',
			grand_total       DECIMAL(10,2)       NOT NULL DEFAULT '0.00',
			status            ENUM('pending','prepared','delivered') NOT NULL DEFAULT 'pending',
			payment_confirmed TINYINT(1)          NOT NULL DEFAULT 0,
			created_at        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at        DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			order_date        DATE                NOT NULL,
			PRIMARY KEY (id),
			UNIQUE KEY order_number (order_number),
			KEY branch_id (branch_id),
			KEY staff_id  (staff_id),
			KEY order_date (order_date)
		) {$charset_collate};";
		$this->wpdb->query( $sql );
	}

	private function create_table_order_items( string $charset_collate ) {
		$table  = $this->table( self::TABLE_ORDER_ITEMS );
		$orders = $this->table( self::TABLE_ORDERS );
		$sql    = "CREATE TABLE IF NOT EXISTS {$table} (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			order_id      BIGINT(20) UNSIGNED NOT NULL,
			product_id    BIGINT(20) UNSIGNED          DEFAULT NULL,
			product_name  VARCHAR(300)        NOT NULL DEFAULT '',
			product_price DECIMAL(10,2)       NOT NULL DEFAULT '0.00',
			quantity      INT(10) UNSIGNED    NOT NULL DEFAULT 1,
			extras        JSON                         DEFAULT NULL,
			extras_total  DECIMAL(10,2)       NOT NULL DEFAULT '0.00',
			item_total    DECIMAL(10,2)       NOT NULL DEFAULT '0.00',
			PRIMARY KEY (id),
			KEY order_id   (order_id),
			KEY product_id (product_id)
		) {$charset_collate};";
		$this->wpdb->query( $sql );
	}

	private function create_table_stock( string $charset_collate ) {
		$table = $this->table( self::TABLE_STOCK );
		$sql   = "CREATE TABLE IF NOT EXISTS {$table} (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id  BIGINT(20) UNSIGNED NOT NULL,
			branch_id   BIGINT(20) UNSIGNED NOT NULL,
			in_stock    INT(10) UNSIGNED    NOT NULL DEFAULT 0,
			new_stock   INT(10) UNSIGNED    NOT NULL DEFAULT 0,
			total_stock INT(10) UNSIGNED    NOT NULL DEFAULT 0,
			sold_stock  INT(10) UNSIGNED    NOT NULL DEFAULT 0,
			stock_left  INT(10)             NOT NULL DEFAULT 0,
			remarks     TEXT                         DEFAULT NULL,
			stock_date  DATE                NOT NULL,
			staff_id    BIGINT(20) UNSIGNED          DEFAULT NULL,
			created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY product_id (product_id),
			KEY branch_id  (branch_id),
			KEY stock_date (stock_date)
		) {$charset_collate};";
		$this->wpdb->query( $sql );
	}

	private function create_table_imports( string $charset_collate ) {
		$table = $this->table( self::TABLE_IMPORTS );
		$sql   = "CREATE TABLE IF NOT EXISTS {$table} (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			product_id  BIGINT(20) UNSIGNED NOT NULL,
			branch_id   BIGINT(20) UNSIGNED NOT NULL,
			quantity    INT(10) UNSIGNED    NOT NULL DEFAULT 0,
			import_date DATE                NOT NULL,
			staff_id    BIGINT(20) UNSIGNED          DEFAULT NULL,
			created_at  DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY product_id  (product_id),
			KEY branch_id   (branch_id),
			KEY import_date (import_date)
		) {$charset_collate};";
		$this->wpdb->query( $sql );
	}

	private function create_table_expenses( string $charset_collate ) {
		$table = $this->table( self::TABLE_EXPENSES );
		$sql   = "CREATE TABLE IF NOT EXISTS {$table} (
			id           BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			branch_id    BIGINT(20) UNSIGNED NOT NULL,
			staff_id     BIGINT(20) UNSIGNED          DEFAULT NULL,
			expense_date DATE                NOT NULL,
			grand_total  DECIMAL(10,2)       NOT NULL DEFAULT '0.00',
			remarks      TEXT                         DEFAULT NULL,
			created_at   DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			KEY branch_id    (branch_id),
			KEY expense_date (expense_date)
		) {$charset_collate};";
		$this->wpdb->query( $sql );
	}

	private function create_table_expense_items( string $charset_collate ) {
		$table    = $this->table( self::TABLE_EXPENSE_ITEMS );
		$expenses = $this->table( self::TABLE_EXPENSES );
		$sql      = "CREATE TABLE IF NOT EXISTS {$table} (
			id          BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			expense_id  BIGINT(20) UNSIGNED NOT NULL,
			description VARCHAR(500)        NOT NULL DEFAULT '',
			price       DECIMAL(10,2)       NOT NULL DEFAULT '0.00',
			quantity    INT(10) UNSIGNED    NOT NULL DEFAULT 1,
			total       DECIMAL(10,2)       NOT NULL DEFAULT '0.00',
			PRIMARY KEY (id),
			KEY expense_id (expense_id)
		) {$charset_collate};";
		$this->wpdb->query( $sql );
	}

	private function create_table_financial_summary( string $charset_collate ) {
		$table = $this->table( self::TABLE_FINANCIAL_SUMMARY );
		$sql   = "CREATE TABLE IF NOT EXISTS {$table} (
			id             BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			branch_id      BIGINT(20) UNSIGNED NOT NULL,
			summary_date   DATE                NOT NULL,
			total_sales    DECIMAL(10,2)       NOT NULL DEFAULT '0.00',
			transfer_sales DECIMAL(10,2)       NOT NULL DEFAULT '0.00',
			card_sales     DECIMAL(10,2)       NOT NULL DEFAULT '0.00',
			cash_sales     DECIMAL(10,2)       NOT NULL DEFAULT '0.00',
			total_expenses DECIMAL(10,2)       NOT NULL DEFAULT '0.00',
			profit         DECIMAL(10,2)       NOT NULL DEFAULT '0.00',
			created_at     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP,
			updated_at     DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY branch_date (branch_id, summary_date),
			KEY branch_id    (branch_id),
			KEY summary_date (summary_date)
		) {$charset_collate};";
		$this->wpdb->query( $sql );
	}

	private function create_table_settings( string $charset_collate ) {
		$table = $this->table( self::TABLE_SETTINGS );
		$sql   = "CREATE TABLE IF NOT EXISTS {$table} (
			id            BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
			setting_key   VARCHAR(200)        NOT NULL,
			setting_value LONGTEXT                     DEFAULT NULL,
			updated_at    DATETIME            NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
			PRIMARY KEY (id),
			UNIQUE KEY setting_key (setting_key)
		) {$charset_collate};";
		$this->wpdb->query( $sql );
	}

	// -----------------------------------------------------------------------
	// Default data
	// -----------------------------------------------------------------------

	/**
	 * Inserts default branches and the superadmin user.
	 * Uses INSERT IGNORE so it is safe to call multiple times.
	 */
	public function insert_default_data() {
		$this->insert_default_branches();
		$this->insert_default_superadmin();
	}

	private function insert_default_branches() {
		$table    = $this->table( self::TABLE_BRANCHES );
		$branches = [
			[
				'name'        => 'Behind Marlima',
				'slug'        => 'behind-marlima',
				'description' => 'Main branch located behind Marlima.',
				'status'      => 'active',
			],
			[
				'name'        => 'Hilltop',
				'slug'        => 'hilltop',
				'description' => 'Hilltop branch – opening soon.',
				'status'      => 'coming_soon',
			],
			[
				'name'        => 'Food Truck',
				'slug'        => 'food-truck',
				'description' => 'Mobile food truck – opening soon.',
				'status'      => 'coming_soon',
			],
		];

		foreach ( $branches as $branch ) {
			$this->wpdb->query(
				$this->wpdb->prepare(
					"INSERT IGNORE INTO {$table}
					 (name, slug, description, status)
					 VALUES (%s, %s, %s, %s)",
					$branch['name'],
					$branch['slug'],
					$branch['description'],
					$branch['status']
				)
			);
		}
	}

	private function insert_default_superadmin() {
		$table = $this->table( self::TABLE_USERS );

		// Only insert if the superadmin account does not already exist.
		$exists = $this->get_user_by_username( 'superadmin' );
		if ( $exists ) {
			return;
		}

		// Default credential – must be changed after first login.
		$password_hash = wp_hash_password( 'bymoreish2024' );

		$this->wpdb->insert(
			$table,
			[
				'username'      => 'superadmin',
				'password_hash' => $password_hash,
				'full_name'     => 'Super Administrator',
				'email'         => 'admin@bymoreish.com',
				'role'          => 'superadmin',
				'branch'        => 'all',
				'is_active'     => 1,
			],
			[ '%s', '%s', '%s', '%s', '%s', '%s', '%d' ]
		);
	}

	// -----------------------------------------------------------------------
	// Query helpers
	// -----------------------------------------------------------------------

	/**
	 * Returns the user row for the given username, or null if not found.
	 *
	 * @param string $username
	 * @return array|null
	 */
	public function get_user_by_username( string $username ): ?array {
		$table = $this->table( self::TABLE_USERS );
		$row   = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$table} WHERE username = %s AND is_active = 1 LIMIT 1",
				$username
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Verifies a plain-text password against a stored hash.
	 *
	 * @param string $password   Plain-text password submitted by the user.
	 * @param string $hash       Hash stored in the database.
	 * @return bool
	 */
	public function verify_password( string $password, string $hash ): bool {
		return (bool) wp_check_password( $password, $hash );
	}

	// -----------------------------------------------------------------------
	// Generic CRUD helpers
	// -----------------------------------------------------------------------

	/**
	 * Returns all rows from a table, with optional WHERE conditions.
	 *
	 * @param string $table_const  One of the TABLE_* class constants.
	 * @param array  $where        Associative array of column => value.
	 * @param string $order_by     Optional ORDER BY clause (e.g. 'created_at DESC').
	 * @return array
	 */
	public function get_rows( string $table_const, array $where = [], string $order_by = '' ): array {
		$table = $this->table( $table_const );
		$sql   = "SELECT * FROM {$table}";

		if ( ! empty( $where ) ) {
			$conditions = [];
			$values     = [];
			foreach ( $where as $col => $val ) {
				if ( $val === null ) {
					$conditions[] = "`{$col}` IS NULL";
				} else {
					$conditions[] = "`{$col}` = %s";
					$values[]     = $val;
				}
			}
			$sql .= ' WHERE ' . implode( ' AND ', $conditions );

			if ( ! empty( $values ) ) {
				// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
				$sql = $this->wpdb->prepare( $sql, ...$values );
			}
		}

		if ( $order_by !== '' ) {
			// $order_by is developer-controlled (not user input), safe to append.
			$sql .= " ORDER BY {$order_by}";
		}

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		$results = $this->wpdb->get_results( $sql, ARRAY_A );

		return $results ?: [];
	}

	/**
	 * Returns a single row by primary key.
	 *
	 * @param string $table_const
	 * @param int    $id
	 * @return array|null
	 */
	public function get_row_by_id( string $table_const, int $id ): ?array {
		$table = $this->table( $table_const );
		$row   = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$table} WHERE id = %d LIMIT 1",
				$id
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Inserts a row and returns the new primary-key value (or 0 on failure).
	 *
	 * @param string $table_const
	 * @param array  $data   Column => value pairs.
	 * @return int
	 */
	public function insert_row( string $table_const, array $data ): int {
		$table = $this->table( $table_const );
		$result = $this->wpdb->insert( $table, $data );
		if ( $result === false ) {
			return 0;
		}
		return (int) $this->wpdb->insert_id;
	}

	/**
	 * Updates rows matching $where with $data.
	 *
	 * @param string $table_const
	 * @param array  $data
	 * @param array  $where
	 * @return int|false  Number of rows updated, or false on error.
	 */
	public function update_row( string $table_const, array $data, array $where ) {
		$table = $this->table( $table_const );
		return $this->wpdb->update( $table, $data, $where );
	}

	/**
	 * Deletes rows matching $where.
	 *
	 * @param string $table_const
	 * @param array  $where
	 * @return int|false
	 */
	public function delete_rows( string $table_const, array $where ) {
		$table = $this->table( $table_const );
		return $this->wpdb->delete( $table, $where );
	}

	// -----------------------------------------------------------------------
	// Specialised query methods
	// -----------------------------------------------------------------------

	/**
	 * Retrieves a setting value by key.
	 *
	 * @param string $key
	 * @param mixed  $default
	 * @return mixed
	 */
	public function get_setting( string $key, $default = null ) {
		$table = $this->table( self::TABLE_SETTINGS );
		$row   = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT setting_value FROM {$table} WHERE setting_key = %s LIMIT 1",
				$key
			),
			ARRAY_A
		);

		return ( $row !== null ) ? $row['setting_value'] : $default;
	}

	/**
	 * Upserts a setting.
	 *
	 * @param string $key
	 * @param mixed  $value
	 */
	public function update_setting( string $key, $value ) {
		$table = $this->table( self::TABLE_SETTINGS );
		$this->wpdb->query(
			$this->wpdb->prepare(
				"INSERT INTO {$table} (setting_key, setting_value)
				 VALUES (%s, %s)
				 ON DUPLICATE KEY UPDATE setting_value = VALUES(setting_value)",
				$key,
				(string) $value
			)
		);
	}

	/**
	 * Returns all active branches.
	 *
	 * @return array
	 */
	public function get_active_branches(): array {
		return $this->get_rows( self::TABLE_BRANCHES, [ 'status' => 'active' ], 'name ASC' );
	}

	/**
	 * Returns all branches regardless of status.
	 *
	 * @return array
	 */
	public function get_all_branches(): array {
		return $this->get_rows( self::TABLE_BRANCHES, [], 'name ASC' );
	}

	/**
	 * Returns orders for a given branch and date range.
	 *
	 * @param int    $branch_id
	 * @param string $date_from  Y-m-d
	 * @param string $date_to    Y-m-d
	 * @return array
	 */
	public function get_orders_by_branch_and_date( int $branch_id, string $date_from, string $date_to ): array {
		$table = $this->table( self::TABLE_ORDERS );
		$sql   = $this->wpdb->prepare(
			"SELECT * FROM {$table}
			 WHERE branch_id = %d
			   AND order_date BETWEEN %s AND %s
			 ORDER BY created_at DESC",
			$branch_id,
			$date_from,
			$date_to
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $this->wpdb->get_results( $sql, ARRAY_A ) ?: [];
	}

	/**
	 * Returns order items for a given order.
	 *
	 * @param int $order_id
	 * @return array
	 */
	public function get_order_items( int $order_id ): array {
		return $this->get_rows( self::TABLE_ORDER_ITEMS, [ 'order_id' => $order_id ], 'id ASC' );
	}

	/**
	 * Returns the latest stock record for a product+branch combination.
	 *
	 * @param int $product_id
	 * @param int $branch_id
	 * @return array|null
	 */
	public function get_latest_stock( int $product_id, int $branch_id ): ?array {
		$table = $this->table( self::TABLE_STOCK );
		$row   = $this->wpdb->get_row(
			$this->wpdb->prepare(
				"SELECT * FROM {$table}
				 WHERE product_id = %d AND branch_id = %d
				 ORDER BY stock_date DESC, id DESC
				 LIMIT 1",
				$product_id,
				$branch_id
			),
			ARRAY_A
		);

		return $row ?: null;
	}

	/**
	 * Returns the financial summary for a branch and date range.
	 *
	 * @param int    $branch_id
	 * @param string $date_from
	 * @param string $date_to
	 * @return array
	 */
	public function get_financial_summary( int $branch_id, string $date_from, string $date_to ): array {
		$table = $this->table( self::TABLE_FINANCIAL_SUMMARY );
		$sql   = $this->wpdb->prepare(
			"SELECT * FROM {$table}
			 WHERE branch_id = %d
			   AND summary_date BETWEEN %s AND %s
			 ORDER BY summary_date ASC",
			$branch_id,
			$date_from,
			$date_to
		);

		// phpcs:ignore WordPress.DB.PreparedSQL.NotPrepared
		return $this->wpdb->get_results( $sql, ARRAY_A ) ?: [];
	}

	/**
	 * Generates the next sequential order number.
	 *
	 * Format: BYM-YYYYMMDD-NNNN
	 *
	 * @return string
	 */
	public function generate_order_number(): string {
		$table = $this->table( self::TABLE_ORDERS );
		$today = current_time( 'Y-m-d' );
		$prefix = 'BYM-' . str_replace( '-', '', $today ) . '-';

		$last = $this->wpdb->get_var(
			$this->wpdb->prepare(
				"SELECT order_number FROM {$table}
				 WHERE order_number LIKE %s
				 ORDER BY id DESC
				 LIMIT 1",
				$this->wpdb->esc_like( $prefix ) . '%'
			)
		);

		if ( $last ) {
			$parts  = explode( '-', $last );
			$seq    = (int) end( $parts );
		} else {
			$seq = 0;
		}

		return $prefix . str_pad( (string) ( $seq + 1 ), 4, '0', STR_PAD_LEFT );
	}

	// -----------------------------------------------------------------------
	// Internal helpers
	// -----------------------------------------------------------------------

	/**
	 * Returns the full, prefixed table name.
	 *
	 * We deliberately do NOT use $wpdb->prefix so that our tables are always
	 * named bym_* regardless of the WordPress table prefix configured by the
	 * site owner.
	 *
	 * @param string $table_const  Value of a TABLE_* constant.
	 * @return string
	 */
	private function table( string $table_const ): string {
		return $table_const;
	}

	/**
	 * Returns the last database error, if any.
	 *
	 * @return string
	 */
	public function last_error(): string {
		return (string) $this->wpdb->last_error;
	}
}
