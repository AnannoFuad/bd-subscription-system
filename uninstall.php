<?php
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
	exit;
}

/*
 * Intentionally conservative.
 * Do not auto-delete subscription data on uninstall in the free version.
 * Add optional cleanup later only if you clearly document it.
 */
