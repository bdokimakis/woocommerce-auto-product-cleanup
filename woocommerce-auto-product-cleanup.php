<?php
/**
 * Plugin Name:       Woocommerce Auto Product Cleanup
 * Version:           1.0.0
 * Author:            b.dokimakis
 * Author URI:        https://b.dokimakis.gr
 * Text Domain:       woocommerce_auto_product_cleanup
 * Domain Path:       /languages
 */
 
add_action('wp_ajax_nopriv_woocommerce_auto_product_cleanup', 'woocommerce_auto_product_cleanup' );
add_action('wp_ajax_woocommerce_auto_product_cleanup', 'woocommerce_auto_product_cleanup' );

function woocommerce_auto_product_cleanup() {
	
	if ($_GET['key'] == "faag3fev43fv56tynhxgv433rtyh") {
	
		$starttime = microtime(true);
		// Get all out-of-stock products.
		$args = array(
			'post_type' => 'product',
			'post_status' => 'any',
			'posts_per_page' => 5000,
			'fields' => 'ids',
			'meta_query' => array(
				array(
					'key' => '_stock_status',
					'value' => 'outofstock'
				)
			)
		);
		
		$query = new WP_Query($args);
		
		$products_ids = $query->posts;

		// For each product, check if it exists in any orders from within the last 6 months.
		foreach ($products_ids as $product_id) {
			$product = wc_get_product($product_id);

			if (empty(get_orders_ids_by_product_id($product_id, array_keys(wc_get_order_statuses())))) {
				// If not, it's safe to delete it.
				echo '<span style="color:red">Deleting: ' . $product->get_title() . ' - ' . $product->get_sku() . '</span><br>';
				remove_attachments($product_id);
				wp_delete_post($product_id, true);
			}
		}
		echo round(microtime(true) - $starttime, 2);
	}
	else {
		echo "Nope.";
	}
}

function remove_attachments( $post_id ) {

	if ( $post_id && $post_id > 0 ) {
		$allowed_to_remove = apply_filters( 'autoremove_attachments_allowed', true );

		if ( $allowed_to_remove ) {
			$args = array(
				'post_type'   => 'attachment',
				'post_parent' => $post_id,
				'post_status' => 'any',
				'nopaging'    => true,

				// Optimize query for performance.
				'no_found_rows'          => true,
				'update_post_meta_cache' => false,
				'update_post_term_cache' => false,
			);
			$query = new WP_Query( $args );

			if ( $query->have_posts() ) {
				while ( $query->have_posts() ) {
					$query->the_post();

					wp_delete_attachment( $query->post->ID, true );
				}
			}

			wp_reset_postdata();
		}
	}
}

function get_orders_ids_by_product_id( $product_id, $order_status, $after = '-6 months' ){
    global $wpdb;

    $results = $wpdb->get_col("
        SELECT order_items.order_id
        FROM {$wpdb->prefix}woocommerce_order_items as order_items
        LEFT JOIN {$wpdb->prefix}woocommerce_order_itemmeta as order_item_meta ON order_items.order_item_id = order_item_meta.order_item_id
        LEFT JOIN {$wpdb->posts} AS posts ON order_items.order_id = posts.ID
        WHERE posts.post_type = 'shop_order'
        AND posts.post_status IN ( '" . implode( "','", $order_status ) . "' )
		AND posts.post_date > '" . date("Y-m-d h:i:s", strtotime($after)) . "' 
        AND order_items.order_item_type = 'line_item'
        AND order_item_meta.meta_key = '_product_id'
        AND order_item_meta.meta_value = '$product_id'
    ");

    return $results;
}