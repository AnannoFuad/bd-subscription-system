<?php
if ( ! defined( 'ABSPATH' ) ) {
	exit;
}

class BDSS_CTA {

	public static function get_purchase_url( $context = 'default' ) {
		$type = BDSS_Settings::get( 'purchase_destination_type' );

		if ( 'shop' === $type ) {
			$url = self::get_shop_url();
			if ( ! empty( $url ) ) {
				return $url;
			}
		}

		if ( 'product_category' === $type ) {
			$term_id = absint( BDSS_Settings::get( 'purchase_destination_category' ) );

			if ( $term_id > 0 ) {
				$url = get_term_link( $term_id, 'product_cat' );

				if ( ! is_wp_error( $url ) && ! empty( $url ) ) {
					return $url;
				}
			}
		}

		$shop_url = self::get_shop_url();
		if ( ! empty( $shop_url ) ) {
			return $shop_url;
		}

		return home_url( '/' );
	}

	private static function get_shop_url() {
		if ( function_exists( 'wc_get_page_permalink' ) ) {
			$url = wc_get_page_permalink( 'shop' );

			if ( ! empty( $url ) ) {
				return $url;
			}
		}

		return '';
	}
}