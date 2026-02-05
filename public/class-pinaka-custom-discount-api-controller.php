<?php
/**
 * REST API Custom Discount API controller
 *
 * Handles requests to the discount API endpoint.
 *
 * @author   WooThemes
 * @category API
 * @package WooCommerce\RestApi
 * @since    3.0.0
 */

if ( ! defined( 'ABSPATH' ) ) {
	exit;
}
use WooCommerce\RestApi;

/**
 * REST API Report Top Sellers controller class.
 *
 * @package WooCommerce\RestApi
 * @extends WC_REST_Report_Sales_V1_Controller
 */
class Pinaka_Custom_Discount_Api_Controller {

	/**
	 * Endpoint namespace.
	 *
	 * @var string
	 */
	protected $namespace = 'pinaka-pos/v1';

	/**
	 * Route base.
	 *
	 * @var string
	 */
	protected $rest_base = 'custom-discount';


    /**
	 * Register the routes for sales reports.
	 */
	public function register_routes() {
		register_rest_route(
			$this->namespace,
			$this->rest_base . '/apply/(?P<order_id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array($this, 'custom_discount_api_handler'),
				'permission_callback' => array($this, 'check_user_role_permission'),
				'args'                => array(
					'order_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);

		register_rest_route(
			$this->namespace,
			$this->rest_base . '/remove/(?P<order_id>\d+)',
			array(
				'methods'             => 'POST',
				'callback'            => array($this, 'remove_custom_discount_handler'),
				'permission_callback' => array($this, 'check_user_role_permission'),
				'args'                => array(
					'order_id' => array(
						'required' => true,
						'type'     => 'integer',
					),
				),
			)
		);

		register_rest_route(
            $this->namespace, 
             $this->rest_base . '/get-all-discounts-for-admin', 
             [
                'methods'  => 'GET',
                'callback' => array( $this, 'pinaka_get_all_discounts_for_admin' ),
                'permission_callback' => '__return_true', // or token
				'args' => array(
					'page' => array(
						'description'       => 'Current page number',
						'type'              => 'integer',
						'default'           => 1,
						'sanitize_callback' => 'absint',
					),
					'per_page' => array(
						'description'       => 'Number of records per page',
						'type'              => 'integer',
						'default'           => 10,
						'sanitize_callback' => 'absint',
					),
					'search' => array(
						'description'       => 'Search by staff name or shift title',
						'type'              => 'string',
						'sanitize_callback' => 'sanitize_text_field',
					)
				),
            ]
        );

		register_rest_route(
			$this->namespace,
			$this->rest_base . '/create-discount',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'create_discount' ),
				'permission_callback' => array( $this, 'check_user_role_permission' ),

			)
		);

		register_rest_route(
			$this->namespace,
			$this->rest_base . '/update-discount',
			array(
				'methods'             => 'POST',
				'callback'            => array( $this, 'update_discount' ),
				'permission_callback' => array( $this, 'check_user_role_permission' ),
			)
		);


	}
	
	/**
	 * Check whether a given request has permission to view system status.
	 *
	 * @param  WP_REST_Request $request Full details about the request.
	 * @return WP_Error|boolean
	 */
	public function check_user_role_permission( $request ) {

		$user_id = get_current_user_id();
		$user    = get_userdata( $user_id );

		if ( ! $user ) {
			return new WP_Error(
				'pinakapos_rest_cannot_view',
				esc_html__( 'Sorry, you cannot give permission.', 'pinaka-pos' ),
				[ 'status' => rest_authorization_required_code() ]
			);
		}

		// Get all registered roles
		global $wp_roles;
		if ( ! isset( $wp_roles ) ) {
			$wp_roles = new WP_Roles();
		}
		$all_roles = array_keys( $wp_roles->get_names() );

		// Allow if user has any of the registered roles
		foreach ( $user->roles as $role ) {
			if ( in_array( $role, $all_roles, true ) ) {
				return true;
			}
		}

		return new WP_Error(
			'pinakapos_rest_cannot_view',
			esc_html__( 'Sorry, you cannot give permission.', 'pinaka-pos' ),
			[ 'status' => rest_authorization_required_code() ]
		);
	}

    /**
	 * Get token by sending POST request to pinaka-pos/v1/token.
	 *
	 * @param WP_REST_Request $request The request.
	 * @return WP_REST_Response The response.
	 */

	public function custom_discount_api_handler($request) {
		$order_id = absint($request->get_param('order_id'));
		$code     = sanitize_text_field($request->get_param('discount_code'));

		if (!$order_id || !$code) {
			return new WP_REST_Response(['success' => false, 'message' => 'Missing order ID or discount code.'], 400);
		}

		$order = wc_get_order($order_id);
		if (!$order || !$order instanceof WC_Order) {
			return new WP_REST_Response(['success' => false, 'message' => 'Invalid order ID.'], 404);
		}

		// Look up discount post by title
		$discount_post = get_page_by_title($code, OBJECT, 'discounts');
		if (!$discount_post || $discount_post->post_status !== 'publish') {
			return new WP_REST_Response(['success' => false, 'message' => 'Invalid or inactive discount code.'], 404);
		}

		// Load discount fields
		$discount_type   = get_post_meta($discount_post->ID, '_discount_type', true);
		$coupon_amount   = floatval(get_post_meta($discount_post->ID, '_coupon_amount', true));
		$min_spend       = floatval(get_post_meta($discount_post->ID, '_minimum_amount', true));
		$max_usage       = intval(get_post_meta($discount_post->ID, '_maximum_amount', true));
		$expiry_date     = get_post_meta($discount_post->ID, '_expiry_date', true);
		$usage_count     = intval(get_post_meta($discount_post->ID, '_discount_usage_count', true));
		$min_quantity    = intval(get_post_meta($discount_post->ID, '_min_qty', true));
		$max_quantity    = intval(get_post_meta($discount_post->ID, '_max_qty', true));
		$allowed_products = get_post_meta($discount_post->ID, '_product_ids', true); // should be an array

		if (!is_array($allowed_products)) {
			$allowed_products = [];
		}

		// Validate expiration
		if ($expiry_date && strtotime($expiry_date) < time()) {
			return new WP_REST_Response(['success' => false, 'message' => 'This discount code has expired.'], 400);
		}

		// Validate usage limit
		if ($max_usage && $usage_count >= $max_usage) {
			return new WP_REST_Response(['success' => false, 'message' => 'This discount code has reached its usage limit.'], 400);
		}

		$order_total = floatval($order->get_subtotal());
		$order_items = $order->get_items();
		
		$total_quantity = 0;
		$has_allowed_product = empty($allowed_products); // if no restriction, allow all

		foreach ($order_items as $item) {
			$product_id = $item->get_product_id();
			$quantity   = $item->get_quantity();

			$total_quantity += $quantity;

			if (in_array($product_id, $allowed_products)) {
				$has_allowed_product = true;
			}
		}

		// Validate min/max quantity
		if ($min_quantity && $total_quantity < $min_quantity) {
			return new WP_REST_Response(['success' => false, 'message' => 'Minimum quantity not met for this discount.'], 400);
		}
		if ($max_quantity && $total_quantity > $max_quantity) {
			return new WP_REST_Response(['success' => false, 'message' => 'Maximum quantity exceeded for this discount.'], 400);
		}

		// Validate product presence
		if (!$has_allowed_product) {
			return new WP_REST_Response(['success' => false, 'message' => 'This discount code is not applicable to the products in the order.'], 400);
		}

		// Validate min spend
		if ($min_spend && $order_total < $min_spend) {
			return new WP_REST_Response(['success' => false, 'message' => 'Minimum spend not met for this discount.'], 400);
		}

		// Calculate discount amount
		$discount_amount = ($discount_type === 'percent')
			? ($order_total * ($coupon_amount / 100))
			: $coupon_amount;

		// Add discount as a negative fee
		$fee = new WC_Order_Item_Fee();
		$fee->set_name(sprintf('Custom Discount (%s)', $code));
		$fee->set_amount(-$discount_amount);
		$fee->set_total(-$discount_amount);
		$fee->set_tax_status('none');
		$order->add_item($fee);
		$order->update_meta_data('_custom_discount_code', $code);

		// Increment usage count
		update_post_meta($discount_post->ID, '_discount_usage_count', $usage_count + 1);

		// Recalculate and save
		$order->calculate_totals();
		$order->save();

		return new WP_REST_Response([
			'success'         => true,
			'message'         => 'Discount applied to order.',
			'discount_code'   => $code,
			'discount_amount' => wc_price($discount_amount),
			'order_id'        => $order_id,
		]);
	}

	
	// public function custom_discount_api_handler($request) {
	// 	$order_id = absint($request->get_param('order_id'));
	// 	$code     = sanitize_text_field($request->get_param('discount_code'));
	
	// 	if (!$order_id || !$code) {
	// 		return new WP_REST_Response([
	// 			'success' => false,
	// 			'message' => 'Missing order ID or discount code.',
	// 		], 400);
	// 	}
	
	// 	$order = wc_get_order($order_id);
	// 	if (!$order || !is_a($order, 'WC_Order')) {
	// 		return new WP_REST_Response([
	// 			'success' => false,
	// 			'message' => 'Invalid order ID.',
	// 		], 404);
	// 	}
	
	// 	$custom_discounts = [
	// 		'BULK10' => ['type' => 'percent', 'amount' => 10, 'min_spend' => 20],
	// 		'FLAT20' => ['type' => 'fixed', 'amount' => 20, 'min_spend' => 100],
	// 	];
	
	// 	if (!isset($custom_discounts[$code])) {
	// 		return new WP_REST_Response([
	// 			'success' => false,
	// 			'message' => 'Invalid discount code.',
	// 		], 400);
	// 	}
	
	// 	$discount = $custom_discounts[$code];
	// 	$order_total = (float) $order->get_subtotal();
	
	// 	if ($order_total < (float) $discount['min_spend']) {
	// 		return new WP_REST_Response([
	// 			'success' => false,
	// 			'message' => sprintf('Minimum spend for %s is $%s.', $code, $discount['min_spend']),
	// 		], 400);
	// 	}
	
	// 	// Calculate discount amount
	// 	$discount_amount = ($discount['type'] === 'percent')
	// 		? $order_total * ((float) $discount['amount'] / 100)
	// 		: (float) $discount['amount'];
	
	// 	// Create negative fee item
	// 	$fee = new WC_Order_Item_Fee();
	// 	$fee->set_name(sprintf('Custom Discount (%s)', $code));
	// 	$fee->set_amount(-1 * abs((float) $discount_amount));
	// 	$fee->set_total(-1 * abs((float) $discount_amount));
	// 	$fee->set_tax_status('none'); // Important to prevent tax calculations
	// 	$order->add_item($fee);
	
	// 	// Recalculate totals (forces correct order total update)
	// 	$order->calculate_totals(false); // false disables taxes
	
	// 	// Save custom code to order meta
	// 	$order->update_meta_data('_custom_discount_code', $code);
	// 	$order->save();
	
	// 	return new WP_REST_Response([
	// 		'success' => true,
	// 		'message' => 'Discount applied to order.',
	// 		'order_id' => $order_id,
	// 		'discount_applied' => wc_price($discount_amount),
	// 	]);
	// }

	public function remove_custom_discount_handler( $request ) {
		$order_id     = absint( $request->get_param( 'order_id' ) );
		$discount_code = sanitize_text_field( $request->get_param( 'discount_code' ) );
	
		$order = wc_get_order( $order_id );
	
		if ( ! $order || ! $order instanceof WC_Order ) {
			return new WP_REST_Response( [ 'success' => false, 'message' => 'Invalid order ID.' ], 404 );
		}
	
		$removed = false;
	
		foreach ( $order->get_fees() as $item_id => $fee ) {
			$fee_name = $fee->get_name();
	
			if (
				( $discount_code && stripos( $fee_name, $discount_code ) !== false ) ||
				( ! $discount_code && stripos( $fee_name, 'Custom Discount' ) !== false )
			) {
				$order->remove_item( $item_id );
				$removed = true;
			}
		}
	
		if ( $removed ) {
			$order->calculate_totals();
			$order->save();
	
			return new WP_REST_Response( [
				'success' => true,
				'message' => 'Custom discount removed.',
				'order_id' => $order_id,
			] );
		}
	
		return new WP_REST_Response( [
			'success' => false,
			'message' => 'No matching custom discount found on order.',
		], 404 );
	}

	public function pinaka_get_all_discounts_for_admin( WP_REST_Request $request ) {

		$page   = max( 1, (int) $request->get_param( 'page' ) );
		$limit  = max( 10, (int) $request->get_param( 'per_page' ) );
		$search = sanitize_text_field( $request->get_param( 'search' ) );
		$status = sanitize_text_field( $request->get_param( 'status' ) );

		$args = array(
			'post_type'      => 'discounts',
			'post_status'    => 'publish',
			'posts_per_page' => $limit,
			'paged'          => $page,
			'orderby'        => 'date',
			'order'          => 'DESC',
		);

		// 🔍 Search by title
		if ( ! empty( $search ) ) {
			$args['s'] = $search;
		}

		$query = new WP_Query( $args );
		$discounts = array();
		foreach ( $query->posts as $post ) {
			$discounts[] = array(
				'id'               => $post->ID,
				'code'             => $post->post_title,
				'discount_type'    => get_post_meta( $post->ID, '_discount_type', true ),
				'coupon_amount'    => get_post_meta( $post->ID, '_discount_amount', true ),
				'minimum_amount'   => get_post_meta( $post->ID, '_minimum_amount', true ),
				'maximum_amount'   => get_post_meta( $post->ID, '_maximum_amount', true ),
				'expiry_date'      => get_post_meta( $post->ID, '_expiry_date', true ),
				'usage_count'      => get_post_meta( $post->ID, '_discount_usage_count', true ),
				'min_qty'          => get_post_meta( $post->ID, '_min_qty', true ),
				'max_qty'          => get_post_meta( $post->ID, '_max_qty', true ),
				'product_ids'      => get_post_meta( $post->ID, '_product_ids', true ),
			);
		}


		return rest_ensure_response( array(
			'data' => $discounts,
			'pagination' => array(
				'page'        => $page,
				'per_page'    => $limit,
				'total'       => (int) $query->found_posts,
				'total_pages' => (int) $query->max_num_pages,
				'has_more'    => $page < $query->max_num_pages,
			),
		) );
	}

	public function create_discount( WP_REST_Request $request ) {
		$code           = sanitize_text_field( $request->get_param( 'code' ) );
		$discount_type  = sanitize_text_field( $request->get_param( 'discount_type' ) );
		$coupon_amount  = floatval( $request->get_param( 'coupon_amount' ) );
		$minimum_amount = floatval( $request->get_param( 'minimum_amount' ) );
		$maximum_amount = floatval( $request->get_param( 'maximum_amount' ) );
		$expiry_date    = sanitize_text_field( $request->get_param( 'expiry_date' ) );
		$min_qty        = intval( $request->get_param( 'min_qty' ) );
		$max_qty        = intval( $request->get_param( 'max_qty' ) );
		$product_ids    = array_map( 'intval', (array) $request->get_param( 'product_ids' ) );

		// Create discount post
		$discount_post = array(
			'post_title'  => $code,
			'post_type'   => 'discounts',
			'post_status' => 'publish',
		);

		$discount_id = wp_insert_post( $discount_post );

		if ( is_wp_error( $discount_id ) ) {
			return new WP_REST_Response( [ 'success' => false, 'message' => 'Failed to create discount.' ], 500 );
		}

		// Save meta fields
		update_post_meta( $discount_id, '_discount_type', $discount_type );
		update_post_meta( $discount_id, '_discount_amount', $coupon_amount );
		update_post_meta( $discount_id, '_minimum_amount', $minimum_amount );
		update_post_meta( $discount_id, '_maximum_amount', $maximum_amount );
		update_post_meta( $discount_id, '_expiry_date', $expiry_date );
		update_post_meta( $discount_id, '_min_qty', $min_qty );
		update_post_meta( $discount_id, '_max_qty', $max_qty );
		update_post_meta( $discount_id, '_product_ids', $product_ids );
		update_post_meta( $discount_id, '_discount_usage_count', 0 );

		return new WP_REST_Response( [ 'success' => true, 'message' => 'Discount created successfully.', 'discount_id' => $discount_id ], 201 );	
	}
	
	public function update_discount( WP_REST_Request $request ) {
		$discount_id   = intval( $request->get_param( 'discount_id' ) );
		$code          = sanitize_text_field( $request->get_param( 'code' ) );
		$discount_type = sanitize_text_field( $request->get_param( 'discount_type' ) );
		$coupon_amount = floatval( $request->get_param( 'amount' ) );
		$minimum_amount = floatval( $request->get_param( 'minimum_amount' ) );
		$maximum_amount = floatval( $request->get_param( 'maximum_amount' ) );
		$expiry_date    = sanitize_text_field( $request->get_param( 'date_expires' ) );
		$min_qty        = intval( $request->get_param( 'min_qty' ) );
		$max_qty        = intval( $request->get_param( 'max_qty' ) );
		$product_ids    = array_map( 'intval', (array) $request->get_param( 'product_ids' ) );

		$discount_post = array(
			'ID'          => $discount_id,
			'post_title'  => $code,
		);

		$result = wp_update_post( $discount_post );

		if ( is_wp_error( $result ) ) {
			return new WP_REST_Response( [ 'success' => false, 'message' => 'Failed to update discount.' ], 500 );
		}

		update_post_meta( $discount_id, '_discount_type', $discount_type );
		update_post_meta( $discount_id, '_discount_amount', $coupon_amount );
		update_post_meta( $discount_id, '_minimum_amount', $minimum_amount );
		update_post_meta( $discount_id, '_maximum_amount', $maximum_amount );
		update_post_meta( $discount_id, '_expiry_date', $expiry_date );
		update_post_meta( $discount_id, '_min_qty', $min_qty );
		update_post_meta( $discount_id, '_max_qty', $max_qty );
		update_post_meta( $discount_id, '_product_ids', $product_ids );

		return new WP_REST_Response( [ 'success' => true, 'message' => 'Discount updated successfully.', 'discount_id' => $discount_id ], 200 );	
	}
	


}
