<?php

/**
 * Store retry details in the WordPress custom table.
 *
 * @package        WooCommerce Subscriptions
 * @subpackage     WCS_Retry_Store
 * @category       Class
 * @author         Prospress
 */
class WCS_Retry_Database_Store extends WCS_Retry_Store {

	/**
	 * Custom table name we're using to store our retries data.
	 *
	 * @var string
	 */
	protected static $table = 'woocommerce_subscriptions_payment_retries';

	/**
	 * Init method.
	 *
	 * @return null|void
	 */
	public function init() {
	}

	/**
	 * Save the details of a retry to the database
	 *
	 * @param WCS_Retry $retry the Retry we want to save.
	 *
	 * @return int the retry's ID
	 */
	public function save( WCS_Retry $retry ) {
		global $wpdb;

		$wpdb->insert(
			$wpdb->prefix . self::$table,
			array(
				'id'       => $retry->get_id(),
				'order_id' => $retry->get_order_id(),
				'status'   => $retry->get_status(),
				'date_gmt' => $retry->get_date_gmt(),
				'rule_raw' => wp_json_encode( $retry->get_rule()->get_raw_data() ),
			),
			array(
				'%d',
				'%d',
				'%s',
				'%s',
				'%s',
			)
		);

		return absint( $wpdb->insert_id );
	}

	/**
	 * Get the details of a retry from the database
	 *
	 * @param int $retry_id The retry we want to get.
	 *
	 * @return null|WCS_Retry
	 */
	public function get_retry( $retry_id ) {
		global $wpdb;

		$retry     = null;
		$raw_retry = $wpdb->get_row(
			$wpdb->prepare(
				"SELECT * FROM {$wpdb->prefix}{$this::$table} WHERE id = %d LIMIT 1",
				$retry_id
			)
		);

		if ( $raw_retry ) {
			$retry = new WCS_Retry( array(
				'id'       => $raw_retry->id,
				'order_id' => $raw_retry->order_id,
				'status'   => $raw_retry->status,
				'date_gmt' => $raw_retry->date_gmt,
				'rule_raw' => json_decode( $raw_retry->rule_raw ),
			) );
		}

		return $retry;
	}

	/**
	 * Get all the retries ordered by date.
	 *
	 * @param array $args The query arguments.
	 *
	 * @return array
	 */
	public function get_retries( $args ) {
		global $wpdb;

		$retries = array();

		// @todo Parse date query.
		$args = wp_parse_args( $args, array(
			'status'     => 'any',
			'date_query' => array(),
		) );

		$where = '';
		if ( 'any' !== $args['status'] ) {
			$where .= $wpdb->prepare(
				' WHERE status = %s',
				$args['status']
			);
		}

		$retry_ids = $wpdb->get_col( "SELECT id from {$wpdb->prefix}{$this::$table} {$where} ORDER BY date_gmt DESC" );

		foreach ( $retry_ids as $retry_post_id ) {
			$retries[ $retry_post_id ] = $this->get_retry( $retry_post_id );
		}

		return $retries;
	}

	/**
	 * Get the IDs of all retries from the database for a given order
	 *
	 * @param int $order_id the order we want to get the retries for.
	 *
	 * @return array
	 */
	public function get_retry_ids_for_order( $order_id ) {
		global $wpdb;

		$retry_ids = $wpdb->get_col(
			$wpdb->prepare(
				"SELECT id from {$wpdb->prefix}{$this::$table} WHERE order_id = %d ORDER BY ID ASC",
				$order_id
			)
		);

		return $retry_ids;
	}
}
