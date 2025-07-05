<?php
/**
 * Frontend functionality for Sharkdropship for AliExpress, eBay, Amazon, Etsy and Temu
 *
 * @package Sharkdropship_Multisupplier_WooCommerce
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Frontend class
 */
class Sharkdropship_Multisupplier_Frontend {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'wp_head', array( $this, 'track_multisupplier_product_view' ) );
        add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_multisupplier_frontend_scripts' ) );
    }
    
    /**
     * Track product view
     */
    public function track_multisupplier_product_view() {
        // Only track on single product pages
        if ( ! is_product() ) {
            return;
        }
        
        global $post;
        
        if ( ! $post || $post->post_type !== 'product' ) {
            return;
        }
        
        // Check if this is a multi-supplier product
        $product_url = get_post_meta( $post->ID, 'productUrl', true );
        if ( ! $product_url || ! $this->is_multisupplier_product( $product_url ) ) {
            return;
        }
        
        // Only track once per session
        $viewed_products = array();
        if ( isset( $_SESSION['multisupplier_viewed_products'] ) && is_array( $_SESSION['multisupplier_viewed_products'] ) ) {
            $viewed_products = array_map( 'absint', $_SESSION['multisupplier_viewed_products'] );
            $viewed_products = array_filter( $viewed_products );
        }
        
        if ( ! in_array( $post->ID, $viewed_products, true ) ) {
            // Increment view count
            $current_views = get_post_meta( $post->ID, 'product_views', true );
            $new_views = $current_views ? absint( $current_views ) + 1 : 1;
            update_post_meta( $post->ID, 'product_views', $new_views );
            
            // Mark as viewed in session
            $viewed_products[] = absint( $post->ID );
            $_SESSION['multisupplier_viewed_products'] = $viewed_products;
            
            // Log the view for debugging (optional)
            // if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
            //     error_log( sprintf( 'Multi-supplier product view tracked: Product ID %d, Views: %d', $post->ID, $new_views ) );
            // }
        }
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
     * Enqueue frontend scripts
     */
    public function enqueue_multisupplier_frontend_scripts() {
        // Only enqueue on single product pages
        if ( ! is_product() ) {
            return;
        }
        
        global $post;
        
        if ( ! $post || $post->post_type !== 'product' ) {
            return;
        }
        
        // Check if this is a multi-supplier product
        $product_url = get_post_meta( $post->ID, 'productUrl', true );
        if ( ! $product_url || ! $this->is_multisupplier_product( $product_url ) ) {
            return;
        }
        
        wp_enqueue_script(
            'multisupplier-frontend',
            SHARKDROPSHIP_MULTISUPPLIER_PLUGIN_URL . 'assets/js/multisupplier-frontend.js',
            array( 'jquery' ),
            SHARKDROPSHIP_MULTISUPPLIER_VERSION,
            true
        );
        
        wp_localize_script( 'multisupplier-frontend', 'multisupplier_frontend', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'multisupplier_frontend_nonce' ),
            'product_id' => $post->ID,
        ) );
    }
} 