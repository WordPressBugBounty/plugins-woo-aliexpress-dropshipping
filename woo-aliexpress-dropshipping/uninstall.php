<?php
/**
 * Uninstall script for Sharkdropship for AliExpress, eBay, Amazon, Etsy and Temu
 *
 * @package Sharkdropship_Multisupplier_WooCommerce
 */

// If uninstall not called from WordPress, exit
if ( ! defined( 'WP_UNINSTALL_PLUGIN' ) ) {
    exit;
}

// Delete user meta for current user
delete_user_meta( get_current_user_id(), 'multisupplier_extension_notice_dismissed' );

// Delete all user meta for this plugin using WordPress API
$users = get_users( array( 'fields' => 'ID' ) );
foreach ( $users as $user_id ) {
    $user_meta = get_user_meta( $user_id );
    if ( $user_meta ) {
        foreach ( $user_meta as $meta_key => $meta_values ) {
            if ( strpos( $meta_key, 'multisupplier_' ) === 0 ) {
                delete_user_meta( $user_id, $meta_key );
            }
        }
    }
}

// Clear any cached data that has been removed
wp_cache_flush(); 