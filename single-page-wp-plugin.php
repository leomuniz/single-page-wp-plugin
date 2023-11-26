<?php
/**
 * Single Page WP Plugin
 *
 * @package           single-page-wp-plugin
 * @author            Léo Muniz
 * @copyright         2023 Léo Muniz
 * @license           GPL-2.0-or-later
 *
 * @wordpress-plugin
 * Plugin Name: Single Page WP Plugin
 * Plugin URI: https://leomuniz.dev
 * Description: A simple plugin in a single page with custom table, shortcodes to insert and retrieve data and public REST API endpoints.
 * Version: 1.0.0
 * Requires at least: 6.0
 * Requires PHP: 7.4
 * Author: Leo Muniz
 * Author URI: https://leomuniz.dev
 * License: GPL-2.0+
 * License URI: http://www.gnu.org/licenses/gpl-2.0.txt
 * Update URI: https://leomuniz.dev
 * Text Domain: single-page-wp-plugin
 * Domain Path: /languages
 */

// If this file is called directly, abort.
if ( ! defined( 'WPINC' ) ) {
	die;
}


/**
 * Custom table name.
 *
 * @since 1.0.0
 */
define( 'SP_WP_PLUGIN_VERSION', '1.0.0' );


/**
 * Plugin URL.
 *
 * @since 1.0.0
 */
define( 'SP_WP_PLUGIN_URL', plugin_dir_url( __FILE__ ) );


/**
 * Custom table name.
 *
 * @since 1.0.0
 */
define( 'SP_WP_PLUGIN_TABLENAME', 'sp_wp_plugin' );


/**
 * Plugin initialization function. Define shortcodes and set hooks.
 *
 * @since 1.0.0
 */
function sp_wp_plugin_my_init() {

	sp_wp_plugin_maybe_create_table();

	add_shortcode( 'sp_wp_display_form', 'sp_wp_plugin_shortcode_display_form' );
	add_shortcode( 'sp_wp_display_list', 'sp_wp_plugin_shortcode_display_list' );

	add_action( 'wp_enqueue_scripts', 'sp_wp_plugin_enqueue_styles' );
	add_action( 'rest_api_init', 'sp_wp_plugin_register_api_routes' );
}
add_action( 'init', 'sp_wp_plugin_my_init' );


/**
 * Create custom table if not exists.
 *
 * @since 1.0.0
 */
function sp_wp_plugin_maybe_create_table() {

	global $wpdb;

	$tablename       = $wpdb->prefix . SP_WP_PLUGIN_TABLENAME;
	$charset_collate = $wpdb->get_charset_collate();

	$sql = "CREATE TABLE IF NOT EXISTS {$tablename} (
		id bigint(20) NOT NULL AUTO_INCREMENT,
		name varchar(200) NOT NULL,
		email varchar(200) NOT NULL,
		rating tinyint (20) NOT NULL,
		comment text,
		date_created datetime NOT NULL DEFAULT CURRENT_TIMESTAMP,
		date_modified datetime NOT NULL DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
		PRIMARY KEY  (id)
	) {$charset_collate};";

	require_once ABSPATH . 'wp-admin/includes/upgrade.php';

	dbDelta( $sql );
}


/**
 * Enqueue basic styles for form and table when using one of the shortcodes.
 *
 * @since 1.0.0
 */
function sp_wp_plugin_enqueue_styles() {

	global $post;

	if ( is_singular() && has_shortcode( $post->post_content, 'sp_wp_display_form' ) ) {
		wp_enqueue_style( 'sp-wp-plugin-form-style', SP_WP_PLUGIN_URL . '/assets/css/form-style.css', array(), SP_WP_PLUGIN_VERSION, 'all' );
	}

	if ( is_singular() && has_shortcode( $post->post_content, 'sp_wp_display_list' ) ) {
		wp_enqueue_style( 'sp-wp-plugin-form-style', SP_WP_PLUGIN_URL . '/assets/css/form-style.css', array(), SP_WP_PLUGIN_VERSION, 'all' );
		wp_enqueue_style( 'sp-wp-plugin-table-style', SP_WP_PLUGIN_URL . '/assets/css/table-style.css', array(), SP_WP_PLUGIN_VERSION, 'all' );
	}
}


/**
 * Shortcode [sp_wp_display_form] to display the form to input data.
 * It also processes incoming $_POST when submitting the form.
 *
 * @since 1.0.0
 */
function sp_wp_plugin_shortcode_display_form() {

	$inserted = sp_wp_plugin_process_form_submission();

	ob_start();

	?>
	<?php if ( ! empty( $inserted ) ) : ?>
		<p><?php esc_html_e( 'Thank you for submitting your review!', 'single-page-wp-plugin' ); ?></p>
	<?php else : ?>
		<?php do_action( 'sp_wp_plugin_before_display_form' ); ?>
		<form action="" method="POST">
			<fieldset>
				<legend><?php esc_html_e( 'Submit a Review', 'single-page-wp-plugin' ); ?></legend>
				<?php wp_nonce_field( 'sp_wp_plugin_nonce_' . get_the_ID(), '_wp_nonce' ); ?>

				<label for="name"><?php esc_html_e( 'Name', 'single-page-wp-plugin' ); ?>*</label>
				<input type="text" id="name" name="sp_wp_plugin_name" placeholder="<?php esc_attr_e( 'Enter your name', 'single-page-wp-plugin' ); ?>" required>

				<label for="email"><?php esc_html_e( 'Email', 'single-page-wp-plugin' ); ?>*</label>
				<input type="email" id="email" name="sp_wp_plugin_email" placeholder="<?php esc_attr_e( 'Enter your e-mail', 'single-page-wp-plugin' ); ?>" required>

				<label for="rating"><?php esc_html_e( 'Rating', 'single-page-wp-plugin' ); ?>* (1 to 5)</label>
				<input type="number" id="rating" name="sp_wp_plugin_rating" min="1" max="5" placeholder="<?php esc_attr_e( 'Enter your rating between 1 and 5', 'single-page-wp-plugin' ); ?>" required>

				<label for="comment"><?php esc_html_e( 'Comment', 'single-page-wp-plugin' ); ?></label>
				<textarea id="comment" name="sp_wp_plugin_comment" rows="4" placeholder="<?php esc_attr_e( 'Enter your comment', 'single-page-wp-plugin' ); ?>"></textarea>
			</fieldset>

			<input type="submit" value="<?php esc_attr_e( 'Submit Review', 'single-page-wp-plugin' ); ?>">
		</form>
		<?php do_action( 'sp_wp_plugin_after_display_form' ); ?>
	<?php endif; ?>
	<?php

	return ob_get_clean();
}


/**
 * Check the new review form submission and process the $_POST data.
 *
 * @since 1.0.0
 */
function sp_wp_plugin_process_form_submission() {

	if ( ! empty( $_POST ) && isset( $_POST['sp_wp_plugin_name'] ) ) {

		if ( empty( $_POST['_wp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wp_nonce'] ) ), 'sp_wp_plugin_nonce_' . get_the_ID() ) ) {
			die( esc_html( __( 'Something went wrong.', 'single-page-wp-plugin' ) ) );
		}

		$data = array(
			'name'    => ! empty( $_POST['sp_wp_plugin_name'] ) ? sanitize_text_field( wp_unslash( $_POST['sp_wp_plugin_name'] ) ) : '',
			'email'   => ! empty( $_POST['sp_wp_plugin_email'] ) ? sanitize_email( wp_unslash( $_POST['sp_wp_plugin_email'] ) ) : '',
			'rating'  => ! empty( $_POST['sp_wp_plugin_rating'] ) ? absint( $_POST['sp_wp_plugin_rating'] ) : '',
			'comment' => ! empty( $_POST['sp_wp_plugin_comment'] ) ? sanitize_text_field( wp_unslash( $_POST['sp_wp_plugin_comment'] ) ) : '',
		);

		return sp_wp_plugin_insert_data( $data );
	}

	return false;
}

/**
 * Shortcode [sp_wp_display_form] to display a list with the data in the custom table.
 * It also process incoming $_POST when searching for an entry.
 *
 * @since 1.0.0
 */
function sp_wp_plugin_shortcode_display_list() {

	$get_entries = sp_wp_plugin_get_entries();
	$get_entries = apply_filters( 'sp_wp_plugin_data_entries', $get_entries );

	ob_start();

	do_action( 'sp_wp_plugin_before_display_data_table' );

	?>
	<?php if ( empty( $get_entries['result'] ) && empty( $get_entries['search_query'] ) ) : ?>
		<h4><?php esc_html_e( 'There are no reviews yet!', 'single-page-wp-plugin' ); ?></h4>
	<?php else : ?>
		<table>
			<thead>
				<tr>
					<th><?php esc_html_e( 'Name', 'single-page-wp-plugin' ); ?></th>
					<th><?php esc_html_e( 'Rating', 'single-page-wp-plugin' ); ?></th>
					<th><?php esc_html_e( 'Comment', 'single-page-wp-plugin' ); ?></th>
				</tr>
			</thead>
			<tbody>
				<?php if ( empty( $get_entries['result'] ) ) : ?>
					<tr>
						<td colspane="3">No reviews found!</td>
					</tr>
				<?php else : ?>
					<?php foreach ( $get_entries['result'] as $review ) : ?>
						<tr>
							<td><?php echo esc_html( $review->name ); ?></td>
							<td><?php echo esc_html( $review->rating ); ?></td>
							<td><?php echo esc_html( $review->comment ); ?></td>
						</tr>
					<?php endforeach; ?>
				<?php endif; ?>
			</tbody>
		</table>

		<?php do_action( 'sp_wp_plugin_between_data_table_and_search_form' ); ?>

		<form action="" method="POST">
			<fieldset>
				<legend><?php esc_html_e( 'Search review', 'single-page-wp-plugin' ); ?></legend>

				<?php wp_nonce_field( 'sp_wp_plugin_search_nonce_' . get_the_ID(), '_wp_nonce' ); ?>

				<label for="search"><?php esc_html_e( 'Search', 'single-page-wp-plugin' ); ?>:</label>
				<input type="text" id="search" name="sp_wp_plugin_q" placeholder="<?php esc_attr_e( 'Enter text', 'single-page-wp-plugin' ); ?>" value="<?php echo esc_attr( $get_entries['search_query'] ); ?>">
			</fieldset>

			<input type="submit" value="<?php esc_attr_e( 'Search', 'single-page-wp-plugin' ); ?>">
		</form>
	<?php endif; ?>
	<?php

	do_action( 'sp_wp_plugin_after_display_data_table' );

	return ob_get_clean();
}

/**
 * Get table entries possibly filtered by $_POST search query.
 *
 * @since 1.0.0
 */
function sp_wp_plugin_get_entries() {

	$search_query = '';

	if ( isset( $_POST['sp_wp_plugin_q'] ) ) {

		if ( empty( $_POST['_wp_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['_wp_nonce'] ) ), 'sp_wp_plugin_search_nonce_' . get_the_ID() ) ) {
			die( esc_html( __( 'Something went wrong.', 'single-page-wp-plugin' ) ) );
		}

		$search_query = trim( sanitize_text_field( wp_unslash( $_POST['sp_wp_plugin_q'] ) ) );
	}

	return array(
		'search_query' => $search_query,
		'result'       => sp_wp_plugin_get_my_table_data( 0, 10, 'id', 'desc', $search_query ),
	);
}

/**
 * Shortcode [sp_wp_display_form] to display a list with the data in the custom table.
 * It also process incoming $_POST when searching for an entry.
 *
 * @since 1.0.0
 *
 * @param integer $page     Page to fetch data.
 * @param integer $per_page Number of rows to fetch per page.
 * @param string  $orderby  Column to order the request by.
 * @param string  $order    Ascending (asc) or descending (desc) order.
 * @param string  $search   Search query.
 */
function sp_wp_plugin_get_my_table_data( $page = 0, $per_page = 10, $orderby = 'id', $order = 'asc', $search = '' ) {

	global $wpdb;

	if ( 'id' !== $orderby && 'name' !== $orderby && 'email' !== $orderby && 'date_created' !== $orderby && 'date_updated' !== $orderby ) {
		return array();
	}

	if ( 'asc' !== $order && 'desc' !== $order ) {
		return array();
	}

	$tablename = $wpdb->prefix . SP_WP_PLUGIN_TABLENAME;
	$params    = array();

	$where_clause = '';
	if ( ! empty( $search ) ) {
		$where_clause  = ' WHERE name LIKE %s ';
		$where_clause .= ' OR email LIKE %s ';
		$where_clause .= ' OR comment LIKE %s ';

		array_push( $params, "%{$search}%", "%{$search}%", "%{$search}%" );
	}

	$query = "SELECT * FROM {$tablename} {$where_clause} ORDER BY {$orderby} {$order} LIMIT %d,%d;";
	array_push( $params, absint( $page ), absint( $per_page ) );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery, WordPress.DB.PreparedSQL.NotPrepared
	return $wpdb->get_results( $wpdb->prepare( $query, $params ) );
}


/**
 * Insert data into the custom table.
 *
 * @since 1.0.0
 *
 * @param array $data Fields to be inserted on the table.
 */
function sp_wp_plugin_insert_data( $data ) {

	global $wpdb;

	if ( empty( $data['name'] ) || empty( $data['email'] ) || empty( $data['rating'] ) ) {
		return false;
	}

	// Add date fields if not provided.
	if ( ! isset( $data['date_created'] ) ) {
		$data['date_created'] = current_time( 'mysql' );
	}

	if ( ! isset( $data['date_modified'] ) ) {
		$data['date_modified'] = current_time( 'mysql' );
	}

	$data = apply_filters( 'sp_wp_plugin_insert_data', $data );

	do_action( 'sp_wp_plugin_before_insert_data', $data );

	// phpcs:ignore WordPress.DB.DirectDatabaseQuery
	$result = $wpdb->insert(
		$wpdb->prefix . SP_WP_PLUGIN_TABLENAME,
		$data,
		array( '%s', '%s', '%d', '%s', '%s', '%s' )
	);

	do_action( 'sp_wp_plugin_after_insert_data', $data );

	return ( false !== $result );
}


/**
 * Register API routes to add and get data from the custom table.
 *
 * @since 1.0.0
 */
function sp_wp_plugin_register_api_routes() {

	$custom_endpoint = 'review/v1';

	register_rest_route(
		$custom_endpoint,
		'/add/',
		array(
			'methods'             => 'POST',
			'callback'            => 'sp_wp_plugin_api_insert_review',
			'permission_callback' => '__return_true',
		)
	);

	register_rest_route(
		$custom_endpoint,
		'/get/',
		array(
			'methods'             => 'GET',
			'callback'            => 'sp_wp_plugin_select_reviews',
			'permission_callback' => '__return_true',
		)
	);
}


/**
 * Process API review/v1/add endpoint. Insert data on the custom table.
 *
 * @since 1.0.0
 *
 * @param array $data Fields to be inserted on the table.
 */
function sp_wp_plugin_api_insert_review( $data ) {

	$name    = sanitize_text_field( $data['name'] );
	$email   = sanitize_email( $data['email'] );
	$rating  = absint( $data['rating'] );
	$comment = sanitize_textarea_field( $data['comment'] );

	$result = sp_wp_plugin_insert_data(
		array(
			'name'    => $name,
			'email'   => $email,
			'rating'  => $rating,
			'comment' => $comment,
		)
	);

	if ( $result ) {
		return new WP_REST_Response( 'Data inserted successfully', 200 );
	} else {
		return new WP_REST_Response( 'Failed to insert data', 500 );
	}
}


/**
 * Process API review/v1/get endpoint. Retrieve all the fields from the custom table.
 *
 * @since 1.0.0
 *
 * @param array $request Parameters to filter the query.
 */
function sp_wp_plugin_select_reviews( $request ) {

	$search_query = isset( $request['q'] ) ? sanitize_text_field( $request['q'] ) : '';
	$page         = isset( $request['page'] ) ? absint( $request['page'] ) : 0;
	$per_page     = isset( $request['per_page'] ) ? absint( $request['per_page'] ) : 10;

	$response = new WP_REST_Response( sp_wp_plugin_get_my_table_data( $page, $per_page, 'id', 'desc', $search_query ), 200 );

	$response->set_headers( array( 'Content-Type' => 'application/json' ) );

	return $response;
}
