<?php
/**
 * Plugin Name: Sharkdropship for AliExpress, eBay, Amazon, Etsy and Temu
 * Description: Multi-supplier dropshipping & affiliate solution. Import products from AliExpress, eBay, Amazon, Etsy and Temu to your WooCommerce store.
 * Version: 3.0.0
 * Author: Sharkdropship Team
 * Contributors: sharkdropship
 * Text Domain: sharkdropship-multisupplier
 * Domain Path: /languages
 * Requires at least: 5.0
 * Tested up to: 6.8
 * Requires PHP: 7.4
 * WC requires at least: 5.0
 * WC tested up to: 8.0
 * License: GPL v2 or later
 * License URI: https://www.gnu.org/licenses/gpl-2.0.html
 * Donate link: https://sharkdropship.com/donate
 *
 * @package Sharkdropship_Multisupplier_WooCommerce
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

// Define plugin constants
define( 'SHARKDROPSHIP_MULTISUPPLIER_VERSION', '3.0.0' );
define( 'SHARKDROPSHIP_MULTISUPPLIER_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );
define( 'SHARKDROPSHIP_MULTISUPPLIER_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'SHARKDROPSHIP_MULTISUPPLIER_PLUGIN_BASENAME', plugin_basename( __FILE__ ) );

// Main plugin class
class Sharkdropship_Multisupplier_WooCommerce {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'init', array( $this, 'init' ) );
    }
    
    /**
     * Initialize the plugin
     */
    public function init() {
        // Check if WooCommerce is active
        if ( ! class_exists( 'WooCommerce' ) ) {
            add_action( 'admin_notices', array( $this, 'woocommerce_missing_notice' ) );
            return;
        }
        
        // Load admin functionality
        if ( is_admin() ) {
            require_once SHARKDROPSHIP_MULTISUPPLIER_PLUGIN_DIR . 'includes/class-multisupplier-admin.php';
            new Sharkdropship_Multisupplier_Admin();
        }
        
        // Load frontend functionality
        require_once SHARKDROPSHIP_MULTISUPPLIER_PLUGIN_DIR . 'includes/class-multisupplier-frontend.php';
        new Sharkdropship_Multisupplier_Frontend();
        
        // Load AJAX handlers
        require_once SHARKDROPSHIP_MULTISUPPLIER_PLUGIN_DIR . 'includes/class-multisupplier-ajax.php';
        new Sharkdropship_Multisupplier_Ajax();
    }
    
    /**
     * WooCommerce missing notice
     */
    public function woocommerce_missing_notice() {
        ?>
        <div class="notice notice-error">
            <p><?php esc_html_e( 'Sharkdropship for AliExpress, eBay, Amazon, Etsy and Temu requires WooCommerce to be installed and activated.', 'sharkdropship-multisupplier' ); ?></p>
        </div>
        <?php
    }
}

// Initialize the plugin
new Sharkdropship_Multisupplier_WooCommerce();

// Activation hook
register_activation_hook( __FILE__, 'sharkdropship_multisupplier_activate' );

/**
 * Plugin activation function
 */
function sharkdropship_multisupplier_activate() {
    // Check if WooCommerce is active
    if ( ! class_exists( 'WooCommerce' ) ) {
        deactivate_plugins( SHARKDROPSHIP_MULTISUPPLIER_PLUGIN_BASENAME );
        wp_die( esc_html__( 'This plugin requires WooCommerce to be installed and activated.', 'sharkdropship-multisupplier' ) );
    }
    
    // Flush rewrite rules
    flush_rewrite_rules();
}

// Deactivation hook
register_deactivation_hook( __FILE__, 'sharkdropship_multisupplier_deactivate' );

/**
 * Plugin deactivation function
 */
function sharkdropship_multisupplier_deactivate() {
    flush_rewrite_rules();
} 