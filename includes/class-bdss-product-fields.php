<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BDSS_Product_Fields {

	public static function init() {
		add_action( 'woocommerce_product_options_general_product_data', array( __CLASS__, 'render_fields' ) );
		add_action( 'woocommerce_process_product_meta', array( __CLASS__, 'save_fields' ) );
	}

	public static function render_fields() {
		echo '<div class="options_group">';

		woocommerce_wp_checkbox(
			array(
				'id'          => '_bdss_enable_subscription',
				'label'       => __( 'Enable BDSS Subscription', 'bd-simple-subscription' ),
				'description' => __( 'Treat this WooCommerce product as a BDSS subscription plan.', 'bd-simple-subscription' ),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'                => '_bdss_duration_days',
				'label'             => __( 'Subscription Duration (Days)', 'bd-simple-subscription' ),
				'description'       => __( 'Number of days the subscription stays active after purchase.', 'bd-simple-subscription' ),
				'type'              => 'number',
				'desc_tip'          => true,
				'custom_attributes' => array(
					'min'  => 1,
					'step' => 1,
				),
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_bdss_role_slug',
				'label'       => __( 'Granted Role Slug', 'bd-simple-subscription' ),
				'description' => __( 'Role to assign when this subscription is active. Example: bdss_subscriber', 'bd-simple-subscription' ),
				'desc_tip'    => true,
			)
		);

		woocommerce_wp_text_input(
			array(
				'id'          => '_bdss_plan_key',
				'label'       => __( 'Plan Key', 'bd-simple-subscription' ),
				'description' => __( 'Unique internal plan key for access checks. Example: monthly_plan', 'bd-simple-subscription' ),
				'desc_tip'    => true,
			)
		);

		echo '</div>';
	}

	public static function save_fields( $product_id ) {
		$product_id = absint( $product_id );

		if ( $product_id < 1 ) {
			return;
		}

		/*
		 * WooCommerce product edit screens already submit a product meta nonce.
		 * Plugin Check wants explicit nonce verification before processing $_POST.
		 */
		$nonce = isset( $_POST['woocommerce_meta_nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['woocommerce_meta_nonce'] ) ) : '';

		if ( empty( $nonce ) || ! wp_verify_nonce( $nonce, 'woocommerce_save_data' ) ) {
			return;
		}

		if ( ! current_user_can( 'edit_product', $product_id ) ) {
			return;
		}

		$enabled  = isset( $_POST['_bdss_enable_subscription'] ) ? 'yes' : 'no';
		$days     = isset( $_POST['_bdss_duration_days'] ) ? absint( $_POST['_bdss_duration_days'] ) : 0;
		$role     = isset( $_POST['_bdss_role_slug'] ) ? sanitize_key( wp_unslash( $_POST['_bdss_role_slug'] ) ) : '';
		$plan_key = isset( $_POST['_bdss_plan_key'] ) ? sanitize_key( wp_unslash( $_POST['_bdss_plan_key'] ) ) : '';

		if ( 'yes' === $enabled && $days < 1 ) {
			$days = 30;
		}

		if ( 'yes' === $enabled && empty( $role ) ) {
			$role = 'bdss_subscriber';
		}

		update_post_meta( $product_id, '_bdss_enable_subscription', $enabled );
		update_post_meta( $product_id, '_bdss_duration_days', $days );
		update_post_meta( $product_id, '_bdss_role_slug', $role );
		update_post_meta( $product_id, '_bdss_plan_key', $plan_key );
	}
}