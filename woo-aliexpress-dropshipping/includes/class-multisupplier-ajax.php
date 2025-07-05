<?php
/**
 * AJAX functionality for Sharkdropship for AliExpress, eBay, Amazon, Etsy and Temu
 *
 * @package Sharkdropship_Multisupplier_WooCommerce
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * AJAX class
 */
class Sharkdropship_Multisupplier_Ajax {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_ajax_multisupplier_frontend_track_view', array( $this, 'multisupplier_frontend_track_view' ) );
        add_action( 'wp_ajax_nopriv_multisupplier_frontend_track_view', array( $this, 'multisupplier_frontend_track_view' ) );
        add_action( 'wp_ajax_multisupplier_delete_product', array( $this, 'multisupplier_delete_product' ) );
    }
    
    /**
     * Frontend track view AJAX handler
     */
    public function multisupplier_frontend_track_view() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'multisupplier_frontend_nonce' ) ) {
            wp_send_json_error( esc_html__( 'Security check failed.', 'sharkdropship-multisupplier' ) );
        }
        
        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        
        if ( ! $product_id ) {
            wp_send_json_error( esc_html__( 'Invalid product ID.', 'sharkdropship-multisupplier' ) );
        }
        
        // Verify this is a valid product
        $product = get_post( $product_id );
        if ( ! $product || $product->post_type !== 'product' ) {
            wp_send_json_error( esc_html__( 'Invalid product.', 'sharkdropship-multisupplier' ) );
        }
        
        // Check if this is a multi-supplier product
        $product_url = get_post_meta( $product_id, 'productUrl', true );
        if ( ! $product_url || ! $this->is_multisupplier_product( $product_url ) ) {
            wp_send_json_error( esc_html__( 'Not a multi-supplier product.', 'sharkdropship-multisupplier' ) );
        }
        
        // Check if already viewed in this session
        $viewed_products = array();
        if ( isset( $_SESSION['multisupplier_viewed_products'] ) && is_array( $_SESSION['multisupplier_viewed_products'] ) ) {
            $viewed_products = array_map( 'absint', $_SESSION['multisupplier_viewed_products'] );
            $viewed_products = array_filter( $viewed_products );
        }
        
        if ( in_array( $product_id, $viewed_products, true ) ) {
            wp_send_json_success( array( 'message' => esc_html__( 'Already viewed in this session.', 'sharkdropship-multisupplier' ) ) );
        }
        
        // Increment view count
        $current_views = get_post_meta( $product_id, 'product_views', true );
        $new_views = $current_views ? absint( $current_views ) + 1 : 1;
        update_post_meta( $product_id, 'product_views', $new_views );
        
        // Mark as viewed in session
        $viewed_products[] = $product_id;
        $_SESSION['multisupplier_viewed_products'] = $viewed_products;
        
        wp_send_json_success( array( 
            'views' => $new_views,
            'message' => esc_html__( 'View tracked successfully.', 'sharkdropship-multisupplier' )
        ) );
    }
    
    /**
     * Check if product is from a supported supplier
     */
    private function is_multisupplier_product( $url ) {
        if ( ! $url ) {
            return false;
        }
        
        $url_lower = strtolower( $url );
        
        return (
            strpos( $url_lower, 'aliexpress' ) !== false ||
            strpos( $url_lower, 'ebay' ) !== false ||
            strpos( $url_lower, 'amazon' ) !== false ||
            strpos( $url_lower, 'etsy' ) !== false ||
            strpos( $url_lower, 'temu' ) !== false
        );
    }
    
    /**
     * Delete product AJAX handler
     */
    public function multisupplier_delete_product() {
        // Verify nonce
        if ( ! isset( $_POST['nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['nonce'] ) ), 'multisupplier_nonce' ) ) {
            wp_send_json_error( esc_html__( 'Security check failed.', 'sharkdropship-multisupplier' ) );
        }
        
        // Check user capabilities
        if ( ! current_user_can( 'delete_posts' ) ) {
            wp_send_json_error( esc_html__( 'You do not have permission to delete products.', 'sharkdropship-multisupplier' ) );
        }
        
        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        
        if ( ! $product_id ) {
            wp_send_json_error( esc_html__( 'Invalid product ID.', 'sharkdropship-multisupplier' ) );
        }
        
        // Verify this is a valid product
        $product = get_post( $product_id );
        if ( ! $product || $product->post_type !== 'product' ) {
            wp_send_json_error( esc_html__( 'Invalid product.', 'sharkdropship-multisupplier' ) );
        }
        
        // Check if this is a multi-supplier product
        $product_url = get_post_meta( $product_id, 'productUrl', true );
        if ( ! $product_url || ! $this->is_multisupplier_product( $product_url ) ) {
            wp_send_json_error( esc_html__( 'Not a multi-supplier product.', 'sharkdropship-multisupplier' ) );
        }
        
        // Delete the product
        $result = wp_delete_post( $product_id, true );
        
        if ( $result ) {
            wp_send_json_success( array(
                'message' => esc_html__( 'Product deleted successfully.', 'sharkdropship-multisupplier' ),
                'product_id' => $product_id
            ) );
        } else {
            wp_send_json_error( esc_html__( 'Failed to delete product.', 'sharkdropship-multisupplier' ) );
        }
    }
} 