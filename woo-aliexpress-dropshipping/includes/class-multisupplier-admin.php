<?php
/**
 * Admin functionality for Sharkdropship for AliExpress, eBay, Amazon, Etsy and Temu
 *
 * @package Sharkdropship_Multisupplier_WooCommerce
 */

// Prevent direct access
if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

/**
 * Admin class
 */
class Sharkdropship_Multisupplier_Admin {
    
    /**
     * Constructor
     */
    public function __construct() {
        add_action( 'admin_menu', array( $this, 'add_multisupplier_admin_menu' ) );
        add_action( 'admin_enqueue_scripts', array( $this, 'enqueue_multisupplier_admin_scripts' ) );
        add_action( 'wp_ajax_multisupplier_get_products', array( $this, 'multisupplier_ajax_get_products' ) );
        add_action( 'wp_ajax_multisupplier_update_views', array( $this, 'multisupplier_ajax_update_views' ) );
        add_action( 'wp_ajax_multisupplier_dismiss_notice', array( $this, 'multisupplier_ajax_dismiss_notice' ) );
        
        // Add button to product edit page
        add_action( 'post_submitbox_misc_actions', array( $this, 'add_multisupplier_edit_button' ) );
        add_action( 'admin_head', array( $this, 'add_multisupplier_edit_styles' ) );
    }
    
    /**
     * Add admin menu
     */
    public function add_multisupplier_admin_menu() {
        add_menu_page(
            esc_html__( 'Sharkdropship for AliExpress, eBay, Amazon, etsy and Temu', 'sharkdropship-multisupplier' ),
            esc_html__( 'Sharkdropship for AliExpress, eBay, Amazon, etsy and Temu', 'sharkdropship-multisupplier' ),
            'manage_options',
            'sharkdropship-multisupplier',
            array( $this, 'multisupplier_admin_page' ),
            'dashicons-cart',
            31
        );
    }
    
    /**
     * Enqueue admin scripts and styles
     */
    public function enqueue_multisupplier_admin_scripts( $hook ) {
        // Add custom CSS for shark icon on all admin pages
        wp_add_inline_style( 'admin-bar', '
            #adminmenu .toplevel_page_sharkdropship-multisupplier .wp-menu-image::before {
                content: "🦈" !important;
                font-size: 18px !important;
                line-height: 1.3 !important;
            }
        ' );
        
        if ( 'toplevel_page_sharkdropship-multisupplier' !== $hook ) {
            return;
        }
        
        wp_enqueue_script(
            'multisupplier-admin',
            SHARKDROPSHIP_MULTISUPPLIER_PLUGIN_URL . 'assets/js/multisupplier-admin.js',
            array( 'jquery' ),
            SHARKDROPSHIP_MULTISUPPLIER_VERSION,
            true
        );
        
        wp_enqueue_style(
            'multisupplier-admin',
            SHARKDROPSHIP_MULTISUPPLIER_PLUGIN_URL . 'assets/css/multisupplier-admin.css',
            array(),
            SHARKDROPSHIP_MULTISUPPLIER_VERSION
        );
        
        wp_localize_script( 'multisupplier-admin', 'multisupplier_ajax', array(
            'ajax_url' => admin_url( 'admin-ajax.php' ),
            'nonce'    => wp_create_nonce( 'multisupplier_nonce' ),
            'currency_symbol' => get_woocommerce_currency_symbol(),
            'strings'  => array(
                'loading' => esc_html__( 'Loading...', 'sharkdropship-multisupplier' ),
                'error'   => esc_html__( 'An error occurred. Please try again.', 'sharkdropship-multisupplier' ),
            ),
        ) );
    }
    
    /**
     * Admin page content
     */
    public function multisupplier_admin_page() {
        // Check user capabilities
        if ( ! current_user_can( 'manage_options' ) ) {
            wp_die( esc_html__( 'You do not have sufficient permissions to access this page.', 'sharkdropship-multisupplier' ) );
        }
        // Nonce verification for GET form
        if ( isset( $_GET['multisupplier_admin_nonce'] ) && ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_GET['multisupplier_admin_nonce'] ) ), 'multisupplier_admin_action' ) ) {
            wp_die( esc_html__( 'Security check failed.', 'sharkdropship-multisupplier' ) );
        }
        
        $current_page = isset( $_GET['paged'] ) ? absint( $_GET['paged'] ) : 1;
        $search_query = isset( $_GET['search'] ) ? sanitize_text_field( wp_unslash( $_GET['search'] ) ) : '';
        
        // Validate sort_by parameter
        $allowed_sort_fields = array( 'title', 'date', 'views', 'sales', 'revenue' );
        $sort_by = isset( $_GET['sort'] ) ? sanitize_text_field( wp_unslash( $_GET['sort'] ) ) : 'title';
        if ( ! in_array( $sort_by, $allowed_sort_fields, true ) ) {
            $sort_by = 'title';
        }
        
        // Validate sort_order parameter
        $allowed_sort_orders = array( 'ASC', 'DESC' );
        $sort_order = isset( $_GET['order'] ) ? strtoupper( sanitize_text_field( wp_unslash( $_GET['order'] ) ) ) : 'ASC';
        if ( ! in_array( $sort_order, $allowed_sort_orders, true ) ) {
            $sort_order = 'ASC';
        }
        
        // Validate views_filter parameter
        $allowed_views_filters = array( '', '0-10', '11-50', '51-100', '100+' );
        $views_filter = isset( $_GET['views_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['views_filter'] ) ) : '';
        if ( ! in_array( $views_filter, $allowed_views_filters, true ) ) {
            $views_filter = '';
        }
        
        // Validate supplier filter parameter
        $allowed_supplier_filters = array( '', 'aliexpress', 'ebay', 'amazon', 'etsy', 'temu' );
        $supplier_filter = isset( $_GET['supplier_filter'] ) ? sanitize_text_field( wp_unslash( $_GET['supplier_filter'] ) ) : '';
        if ( ! in_array( $supplier_filter, $allowed_supplier_filters, true ) ) {
            $supplier_filter = '';
        }
        
        $products = $this->get_multisupplier_products( $current_page, $search_query, $sort_by, $sort_order, $views_filter, $supplier_filter );
        $total_products = $this->get_total_multisupplier_products( $search_query, $views_filter, $supplier_filter );
        $total_pages = ceil( $total_products / 20 );
        
        // Get metrics data
        $total_sales = $this->get_total_multisupplier_sales();
        $total_revenue = $this->get_total_multisupplier_revenue();
        $conversion_rate = $this->get_multisupplier_conversion_rate();
        $average_order_value = $this->get_multisupplier_average_order_value();
        $products_this_month = $this->get_multisupplier_products_this_month();
        $top_supplier = $this->get_multisupplier_top_supplier();
        
        ?>
        <div class="wrap">
            <h1 class="wp-heading-inline"><?php esc_html_e( 'Sharkdropship for AliExpress, eBay, Amazon, etsy and Temu Dashboard', 'sharkdropship-multisupplier' ); ?></h1>
            
            <?php if ( ! get_user_meta( get_current_user_id(), 'multisupplier_extension_notice_dismissed', true ) ) : ?>
            <!-- Chrome Extension Notice -->
            <div class="multisupplier-extension-notice">
                <div class="notice-header">
                    <div class="notice-badge">
                        <span class="dashicons dashicons-warning"></span>
                        <?php esc_html_e( 'REQUIRED', 'sharkdropship-multisupplier' ); ?>
                    </div>
                    <h2><?php esc_html_e( '🚀 Install Chrome Extension to Import Products', 'sharkdropship-multisupplier' ); ?></h2>
                </div>
                <div class="notice-content">
                    <div class="notice-icon">
                        <span class="dashicons dashicons-admin-plugins"></span>
                    </div>
                    <div class="notice-text">
                        <h3><?php esc_html_e( 'Chrome Extension Required for Product Import', 'sharkdropship-multisupplier' ); ?></h3>
                        <div class="notice-benefits">
                            <div class="benefit-item">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php esc_html_e( 'One-click product import from AliExpress, eBay, Amazon, Etsy and Temu', 'sharkdropship-multisupplier' ); ?>
                            </div>
                            <div class="benefit-item">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php esc_html_e( 'Automatic image and price extraction', 'sharkdropship-multisupplier' ); ?>
                            </div>
                            <div class="benefit-item">
                                <span class="dashicons dashicons-yes-alt"></span>
                                <?php esc_html_e( 'Bulk import multiple products', 'sharkdropship-multisupplier' ); ?>
                            </div>
                        </div>
                        <div class="notice-actions">
                            <a href="https://chromewebstore.google.com/detail/sharkdropship-for-temu-al/ajbncoijgeclkangiahiphilnolbdmmh?hl=en" target="_blank" class="button button-primary button-large" id="download-extension">
                                <span class="dashicons dashicons-download"></span>
                                <?php esc_html_e( '📥 Install Chrome Extension Now', 'sharkdropship-multisupplier' ); ?>
                            </a>
                            <a href="https://www.youtube.com/watch?v=Cs-06Xtf_V4" target="_blank" class="button button-secondary" id="learn-more">
                                <span class="dashicons dashicons-video-alt3"></span>
                                <?php esc_html_e( 'Watch Demo Video', 'sharkdropship-multisupplier' ); ?>
                            </a>
                            <button type="button" class="notice-dismiss" id="dismiss-notice" title="<?php esc_attr_e( 'Dismiss this notice', 'sharkdropship-multisupplier' ); ?>">
                                <span class="dashicons dashicons-no-alt"></span>
                            </button>
                        </div>
                    </div>
                </div>
            </div>
            <?php endif; ?>
            
            <!-- Metrics Dashboard -->
            <div class="multisupplier-metrics-dashboard">
                <div class="metric-card">
                    <div class="metric-icon">
                        <span class="dashicons dashicons-products"></span>
                    </div>
                    <div class="metric-content">
                        <div class="metric-value"><?php echo esc_html( number_format( $total_products ) ); ?></div>
                        <div class="metric-label"><?php esc_html_e( 'Total Products', 'sharkdropship-multisupplier' ); ?></div>
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon">
                        <span class="dashicons dashicons-cart"></span>
                    </div>
                    <div class="metric-content">
                        <div class="metric-value"><?php echo esc_html( number_format( $total_sales ) ); ?></div>
                        <div class="metric-label"><?php esc_html_e( 'Total Sales', 'sharkdropship-multisupplier' ); ?></div>
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon">
                        <span class="dashicons dashicons-money-alt"></span>
                    </div>
                    <div class="metric-content">
                        <div class="metric-value"><?php echo esc_html( get_woocommerce_currency_symbol() . number_format( $total_revenue, 2 ) ); ?></div>
                        <div class="metric-label"><?php esc_html_e( 'Total Revenue', 'sharkdropship-multisupplier' ); ?></div>
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon">
                        <span class="dashicons dashicons-chart-line"></span>
                    </div>
                    <div class="metric-content">
                        <div class="metric-value"><?php echo esc_html( $conversion_rate . '%' ); ?></div>
                        <div class="metric-label"><?php esc_html_e( 'Conversion Rate', 'sharkdropship-multisupplier' ); ?></div>
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon">
                        <span class="dashicons dashicons-tag"></span>
                    </div>
                    <div class="metric-content">
                        <div class="metric-value"><?php echo esc_html( get_woocommerce_currency_symbol() . number_format( $average_order_value, 2 ) ); ?></div>
                        <div class="metric-label"><?php esc_html_e( 'Avg Order Value', 'sharkdropship-multisupplier' ); ?></div>
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon">
                        <span class="dashicons dashicons-calendar-alt"></span>
                    </div>
                    <div class="metric-content">
                        <div class="metric-value"><?php echo esc_html( number_format( $products_this_month ) ); ?></div>
                        <div class="metric-label"><?php esc_html_e( 'Added This Month', 'sharkdropship-multisupplier' ); ?></div>
                    </div>
                </div>
                
                <div class="metric-card">
                    <div class="metric-icon">
                        <span class="dashicons dashicons-star-filled"></span>
                    </div>
                    <div class="metric-content">
                        <div class="metric-value"><?php echo esc_html( $top_supplier ); ?></div>
                        <div class="metric-label"><?php esc_html_e( 'Top Supplier', 'sharkdropship-multisupplier' ); ?></div>
                    </div>
                </div>
            </div>
            
            <!-- Search and Filter -->
            <div class="multisupplier-filters-section">
                <form method="get" action="">
                    <?php wp_nonce_field( 'multisupplier_admin_action', 'multisupplier_admin_nonce' ); ?>
                    <input type="hidden" name="page" value="sharkdropship-multisupplier">
                    
                    <div class="filters-container">
                        <div class="search-box">
                            <input type="text" 
                                   id="search-input"
                                   name="search" 
                                   value="<?php echo esc_attr( $search_query ); ?>" 
                                   placeholder="<?php esc_attr_e( 'Search products...', 'sharkdropship-multisupplier' ); ?>">
                            <button type="submit" class="search-btn">
                                <span class="dashicons dashicons-search"></span>
                            </button>
                        </div>
                        
                        <div class="filter-controls">
                            <select name="supplier_filter" id="supplier-filter">
                                <option value=""><?php esc_html_e( 'All Suppliers', 'sharkdropship-multisupplier' ); ?></option>
                                <option value="aliexpress" <?php selected( $supplier_filter, 'aliexpress' ); ?>><?php esc_html_e( 'AliExpress', 'sharkdropship-multisupplier' ); ?></option>
                                <option value="ebay" <?php selected( $supplier_filter, 'ebay' ); ?>><?php esc_html_e( 'eBay', 'sharkdropship-multisupplier' ); ?></option>
                                <option value="amazon" <?php selected( $supplier_filter, 'amazon' ); ?>><?php esc_html_e( 'Amazon', 'sharkdropship-multisupplier' ); ?></option>
                                <option value="etsy" <?php selected( $supplier_filter, 'etsy' ); ?>><?php esc_html_e( 'Etsy', 'sharkdropship-multisupplier' ); ?></option>
                                <option value="temu" <?php selected( $supplier_filter, 'temu' ); ?>><?php esc_html_e( 'Temu', 'sharkdropship-multisupplier' ); ?></option>
                            </select>
                            
                            <select name="views_filter" id="views-filter">
                                <option value=""><?php esc_html_e( 'All Views', 'sharkdropship-multisupplier' ); ?></option>
                                <option value="0-10" <?php selected( $views_filter, '0-10' ); ?>><?php esc_html_e( '0-10 Views', 'sharkdropship-multisupplier' ); ?></option>
                                <option value="11-50" <?php selected( $views_filter, '11-50' ); ?>><?php esc_html_e( '11-50 Views', 'sharkdropship-multisupplier' ); ?></option>
                                <option value="51-100" <?php selected( $views_filter, '51-100' ); ?>><?php esc_html_e( '51-100 Views', 'sharkdropship-multisupplier' ); ?></option>
                                <option value="100+" <?php selected( $views_filter, '100+' ); ?>><?php esc_html_e( '100+ Views', 'sharkdropship-multisupplier' ); ?></option>
                            </select>
                            
                            <select name="sort" id="sort-select">
                                <option value="title" <?php selected( $sort_by, 'title' ); ?>><?php esc_html_e( 'Sort by Title', 'sharkdropship-multisupplier' ); ?></option>
                                <option value="date" <?php selected( $sort_by, 'date' ); ?>><?php esc_html_e( 'Sort by Date', 'sharkdropship-multisupplier' ); ?></option>
                                <option value="views" <?php selected( $sort_by, 'views' ); ?>><?php esc_html_e( 'Sort by Views', 'sharkdropship-multisupplier' ); ?></option>
                                <option value="sales" <?php selected( $sort_by, 'sales' ); ?>><?php esc_html_e( 'Sort by Sales', 'sharkdropship-multisupplier' ); ?></option>
                                <option value="revenue" <?php selected( $sort_by, 'revenue' ); ?>><?php esc_html_e( 'Sort by Revenue', 'sharkdropship-multisupplier' ); ?></option>
                            </select>
                            
                            <select name="order" id="order-select">
                                <option value="ASC" <?php selected( $sort_order, 'ASC' ); ?>><?php esc_html_e( 'Ascending', 'sharkdropship-multisupplier' ); ?></option>
                                <option value="DESC" <?php selected( $sort_order, 'DESC' ); ?>><?php esc_html_e( 'Descending', 'sharkdropship-multisupplier' ); ?></option>
                            </select>
                            
                            <button type="submit" class="button button-primary"><?php esc_html_e( 'Apply', 'sharkdropship-multisupplier' ); ?></button>
                            <a href="<?php echo esc_url( admin_url( 'admin.php?page=sharkdropship-multisupplier' ) ); ?>" class="button button-secondary"><?php esc_html_e( 'Clear', 'sharkdropship-multisupplier' ); ?></a>
                        </div>
                    </div>
                </form>
            </div>
            
            <!-- Products Table -->
            <div class="multisupplier-plugin-table-container">
                <?php if ( empty( $products ) ) : ?>
                    <div class="multisupplier-no-products-card" style="max-width: 480px; margin: 60px auto; padding: 32px 24px; background: #fff; border-radius: 12px; box-shadow: 0 2px 16px rgba(0,0,0,0.07); text-align: center;">
                        <div style="font-size: 48px; color: #ff9900; margin-bottom: 16px;">
                            <span class="dashicons dashicons-cart"></span>
                        </div>
                        <h2 style="margin-bottom: 12px;">
                            <?php esc_html_e( 'No multi-supplier products found', 'sharkdropship-multisupplier' ); ?>
                        </h2>
                        <p style="margin-bottom: 24px; color: #555;">
                            <?php esc_html_e( 'You have not imported any products from AliExpress, eBay, Amazon, Etsy or Temu yet. To get started, install the Sharkdropship Chrome extension and import your first product!', 'sharkdropship-multisupplier' ); ?>
                        </p>
                        <a href="https://chromewebstore.google.com/detail/sharkdropship-for-temu-al/ajbncoijgeclkangiahiphilnolbdmmh?hl=en" target="_blank" class="button button-primary button-large" style="font-size: 16px; padding: 10px 32px;">
                            <?php esc_html_e( 'Get Chrome Extension', 'sharkdropship-multisupplier' ); ?>
                        </a>
                    </div>
                <?php else : ?>
                    <table class="wp-list-table widefat fixed striped">
                        <thead>
                            <tr>
                                <th class="column-product"><?php esc_html_e( 'Product', 'sharkdropship-multisupplier' ); ?></th>
                                <th class="column-supplier"><?php esc_html_e( 'Supplier', 'sharkdropship-multisupplier' ); ?></th>
                                <th class="column-price"><?php esc_html_e( 'Price', 'sharkdropship-multisupplier' ); ?></th>
                                <th class="column-views"><?php esc_html_e( 'Views', 'sharkdropship-multisupplier' ); ?></th>
                                <th class="column-sales"><?php esc_html_e( 'Sales', 'sharkdropship-multisupplier' ); ?></th>
                                <th class="column-revenue"><?php esc_html_e( 'Revenue', 'sharkdropship-multisupplier' ); ?></th>
                                <th class="column-date"><?php esc_html_e( 'Date Added', 'sharkdropship-multisupplier' ); ?></th>
                                <th class="column-actions"><?php esc_html_e( 'Actions', 'sharkdropship-multisupplier' ); ?></th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ( $products as $product ) : ?>
                            <tr>
                                <td class="column-product">
                                    <div class="product-info">
                                        <div class="product-image">
                                            <?php 
                                            $thumbnail = get_the_post_thumbnail( $product->ID, array( 60, 60 ) );
                                            if ( $thumbnail ) {
                                                echo wp_kses_post( $thumbnail );
                                            } else {
                                                echo '<span class="dashicons dashicons-products"></span>';
                                            }
                                            ?>
                                        </div>
                                        <div class="product-details">
                                            <a href="<?php echo esc_url( get_edit_post_link( $product->ID ) ); ?>" class="product-title">
                                                <?php echo esc_html( $product->post_title ); ?>
                                            </a>
                                            <div class="product-id">
                                                <?php printf( esc_html__( 'ID: %d', 'sharkdropship-multisupplier' ), esc_html( $product->ID ) ); ?>
                                            </div>
                                        </div>
                                    </div>
                                </td>
                                <td class="column-supplier" data-label="<?php esc_attr_e( 'Supplier', 'sharkdropship-multisupplier' ); ?>">
                                    <div class="supplier-badge <?php echo esc_attr( $this->get_supplier_from_url( get_post_meta( $product->ID, 'productUrl', true ) ) ); ?>">
                                        <?php 
                                        $product_url = get_post_meta( $product->ID, 'productUrl', true );
                                        $supplier = $this->get_supplier_from_url( $product_url );
                                        echo esc_html( ucfirst( $supplier ) );
                                        ?>
                                    </div>
                                </td>
                                <td class="column-price" data-label="<?php esc_attr_e( 'Price', 'sharkdropship-multisupplier' ); ?>">
                                    <div class="product-price">
                                        <?php echo esc_html( get_woocommerce_currency_symbol() . get_post_meta( $product->ID, '_price', true ) ); ?>
                                    </div>
                                </td>
                                <td class="column-views" data-label="<?php esc_attr_e( 'Views', 'sharkdropship-multisupplier' ); ?>">
                                    <div class="views-count">
                                        <?php echo esc_html( $this->get_multisupplier_product_views( $product->ID ) ); ?>
                                    </div>
                                </td>
                                <td class="column-sales" data-label="<?php esc_attr_e( 'Sales', 'sharkdropship-multisupplier' ); ?>">
                                    <div class="sales-count">
                                        <?php echo esc_html( $this->get_multisupplier_product_sales( $product->ID ) ); ?>
                                    </div>
                                </td>
                                <td class="column-revenue" data-label="<?php esc_attr_e( 'Revenue', 'sharkdropship-multisupplier' ); ?>">
                                    <div class="revenue-amount">
                                        <?php echo esc_html( get_woocommerce_currency_symbol() . number_format( $this->get_multisupplier_product_revenue( $product->ID ), 2 ) ); ?>
                                    </div>
                                </td>
                                <td class="column-date" data-label="<?php esc_attr_e( 'Date Added', 'sharkdropship-multisupplier' ); ?>">
                                    <div class="date-added">
                                        <?php echo esc_html( get_the_date( 'Y-m-d', $product->ID ) ); ?>
                                    </div>
                                </td>
                                <td class="column-actions" data-label="<?php esc_attr_e( 'Actions', 'sharkdropship-multisupplier' ); ?>">
                                    <div class="action-buttons">
                                        <a href="<?php echo esc_url( get_edit_post_link( $product->ID ) ); ?>" class="button button-edit">
                                            <span class="dashicons dashicons-edit"></span> <?php esc_html_e( 'Edit', 'sharkdropship-multisupplier' ); ?>
                                        </a>
                                        <a href="<?php echo esc_url( get_permalink( $product->ID ) ); ?>" class="button button-view" target="_blank">
                                            <span class="dashicons dashicons-visibility"></span> <?php esc_html_e( 'View', 'sharkdropship-multisupplier' ); ?>
                                        </a>
                                        <button type="button" class="button button-delete" data-product-id="<?php echo esc_attr( $product->ID ); ?>" data-product-title="<?php echo esc_attr( $product->post_title ); ?>">
                                            <span class="dashicons dashicons-trash"></span> <?php esc_html_e( 'Delete', 'sharkdropship-multisupplier' ); ?>
                                        </button>
                                    </div>
                                </td>
                            </tr>
                            <?php endforeach; ?>
                        </tbody>
                    </table>
                <?php endif; ?>
            </div>
            
            <!-- Delete Confirmation Modal -->
            <div class="delete-confirmation-modal" id="deleteConfirmationModal">
                <div class="delete-confirmation-content">
                    <div class="delete-confirmation-icon">
                        <span class="dashicons dashicons-warning"></span>
                    </div>
                    <h3 class="delete-confirmation-title"><?php esc_html_e( 'Delete Product', 'sharkdropship-multisupplier' ); ?></h3>
                    <p class="delete-confirmation-message"><?php esc_html_e( 'Are you sure you want to delete this product? This action cannot be undone.', 'sharkdropship-multisupplier' ); ?></p>
                    <div class="delete-confirmation-actions">
                        <button type="button" class="button button-cancel" id="cancelDelete"><?php esc_html_e( 'Cancel', 'sharkdropship-multisupplier' ); ?></button>
                        <button type="button" class="button button-confirm" id="confirmDelete"><?php esc_html_e( 'Delete', 'sharkdropship-multisupplier' ); ?></button>
                    </div>
                </div>
            </div>
            
            <!-- Pagination -->
            <?php if ( $total_pages > 1 ) : ?>
            <div class="multisupplier-pagination">
                <?php
                echo wp_kses_post( paginate_links( array(
                    'base' => add_query_arg( 'paged', '%#%' ),
                    'format' => '',
                    'prev_text' => esc_html__( '&laquo; Previous', 'sharkdropship-multisupplier' ),
                    'next_text' => esc_html__( 'Next &raquo;', 'sharkdropship-multisupplier' ),
                    'total' => $total_pages,
                    'current' => $current_page,
                ) ) );
                ?>
            </div>
            <?php endif; ?>
        </div>
        <?php
    }
    
    /**
     * Get supplier from URL
     */
    private function get_supplier_from_url( $url ) {
        if ( ! $url ) {
            return 'unknown';
        }
        
        $url_lower = strtolower( $url );
        
        if ( strpos( $url_lower, 'aliexpress' ) !== false ) {
            return 'aliexpress';
        } elseif ( strpos( $url_lower, 'ebay' ) !== false ) {
            return 'ebay';
        } elseif ( strpos( $url_lower, 'amazon' ) !== false ) {
            return 'amazon';
        } elseif ( strpos( $url_lower, 'etsy' ) !== false ) {
            return 'etsy';
        } elseif ( strpos( $url_lower, 'temu' ) !== false ) {
            return 'temu';
        }
        
        return 'unknown';
    }
    
    /**
     * Get multi-supplier products
     */
    private function get_multisupplier_products( $page = 1, $search = '', $sort_by = 'title', $sort_order = 'ASC', $views_filter = '', $supplier_filter = '' ) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'any',
            'posts_per_page' => 20,
            'paged' => $page,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'productUrl',
                    'value' => 'aliexpress',
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'productUrl',
                    'value' => 'ebay',
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'productUrl',
                    'value' => 'amazon',
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'productUrl',
                    'value' => 'etsy',
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'productUrl',
                    'value' => 'temu',
                    'compare' => 'LIKE',
                ),
            ),
        );
        
        // Add supplier filter
        if ( ! empty( $supplier_filter ) ) {
            $args['meta_query'] = array(
                array(
                    'key' => 'productUrl',
                    'value' => $supplier_filter,
                    'compare' => 'LIKE',
                ),
            );
        }
        
        // Add search
        if ( ! empty( $search ) ) {
            $args['s'] = $search;
        }
        
        // Add sorting
        switch ( $sort_by ) {
            case 'date':
                $args['orderby'] = 'date';
                break;
            case 'views':
                $args['meta_key'] = 'product_views';
                $args['orderby'] = 'meta_value_num';
                break;
            case 'sales':
                $args['meta_key'] = 'product_sales';
                $args['orderby'] = 'meta_value_num';
                break;
            case 'revenue':
                $args['meta_key'] = 'product_revenue';
                $args['orderby'] = 'meta_value_num';
                break;
            default:
                $args['orderby'] = 'title';
                break;
        }
        
        $args['order'] = $sort_order;
        
        $query = new WP_Query( $args );
        return $query->posts;
    }
    
    /**
     * Get total multi-supplier products count
     */
    private function get_total_multisupplier_products( $search = '', $views_filter = '', $supplier_filter = '' ) {
        $args = array(
            'post_type' => 'product',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'productUrl',
                    'value' => 'aliexpress',
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'productUrl',
                    'value' => 'ebay',
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'productUrl',
                    'value' => 'amazon',
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'productUrl',
                    'value' => 'etsy',
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'productUrl',
                    'value' => 'temu',
                    'compare' => 'LIKE',
                ),
            ),
        );
        
        // Add supplier filter
        if ( ! empty( $supplier_filter ) ) {
            $args['meta_query'] = array(
                array(
                    'key' => 'productUrl',
                    'value' => $supplier_filter,
                    'compare' => 'LIKE',
                ),
            );
        }
        
        if ( ! empty( $search ) ) {
            $args['s'] = $search;
        }
        
        $query = new WP_Query( $args );
        return $query->found_posts;
    }
    
    /**
     * Get product views
     */
    private function get_multisupplier_product_views( $product_id ) {
        return get_post_meta( $product_id, 'product_views', true ) ?: 0;
    }
    
    /**
     * Get product sales
     */
    private function get_multisupplier_product_sales( $product_id ) {
        // Check cache first
        $cache_key = 'multisupplier_product_sales_' . $product_id;
        $sales_count = wp_cache_get( $cache_key, 'multisupplier' );
        
        if ( false === $sales_count ) {
            // Use WordPress API instead of direct database query
            $orders = wc_get_orders( array(
                'status' => array( 'wc-completed', 'wc-processing' ),
                'limit' => -1,
                'return' => 'ids',
            ) );
            
            $sales_count = 0;
            foreach ( $orders as $order_id ) {
                $order = wc_get_order( $order_id );
                if ( $order ) {
                    foreach ( $order->get_items() as $item ) {
                        if ( $item->get_product_id() == $product_id ) {
                            $sales_count++;
                        }
                    }
                }
            }
            
            // Cache the result for 1 hour
            wp_cache_set( $cache_key, $sales_count, 'multisupplier', HOUR_IN_SECONDS );
        }
        
        return intval( $sales_count );
    }
    
    /**
     * Get product revenue
     */
    private function get_multisupplier_product_revenue( $product_id ) {
        // Check cache first
        $cache_key = 'multisupplier_product_revenue_' . $product_id;
        $revenue = wp_cache_get( $cache_key, 'multisupplier' );
        
        if ( false === $revenue ) {
            // Use WordPress API instead of direct database query
            $orders = wc_get_orders( array(
                'status' => array( 'wc-completed', 'wc-processing' ),
                'limit' => -1,
            ) );
            
            $revenue = 0.00;
            foreach ( $orders as $order ) {
                foreach ( $order->get_items() as $item ) {
                    if ( $item->get_product_id() == $product_id ) {
                        $revenue += $item->get_total();
                    }
                }
            }
            
            // Cache the result for 1 hour
            wp_cache_set( $cache_key, $revenue, 'multisupplier', HOUR_IN_SECONDS );
        }
        
        return floatval( $revenue );
    }
    
    /**
     * Get total sales
     */
    private function get_total_multisupplier_sales() {
        // Check cache first
        $cache_key = 'multisupplier_total_sales';
        $total_sales = wp_cache_get( $cache_key, 'multisupplier' );
        
        if ( false === $total_sales ) {
            // Use WordPress API instead of direct database query
            $multisupplier_products = get_posts( array(
                'post_type' => 'product',
                'post_status' => 'any',
                'posts_per_page' => -1,
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => 'productUrl',
                        'value' => 'aliexpress',
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key' => 'productUrl',
                        'value' => 'ebay',
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key' => 'productUrl',
                        'value' => 'amazon',
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key' => 'productUrl',
                        'value' => 'etsy',
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key' => 'productUrl',
                        'value' => 'temu',
                        'compare' => 'LIKE',
                    ),
                ),
                'fields' => 'ids',
            ) );
            
            $total_sales = 0;
            foreach ( $multisupplier_products as $product_id ) {
                $sales = $this->get_multisupplier_product_sales( $product_id );
                $total_sales += $sales;
            }
            
            // Cache the result for 1 hour
            wp_cache_set( $cache_key, $total_sales, 'multisupplier', HOUR_IN_SECONDS );
        }
        
        return $total_sales;
    }
    
    /**
     * Get conversion rate
     */
    private function get_multisupplier_conversion_rate() {
        $total_views = 0;
        $total_sales = 0;
        
        // Get all multi-supplier products
        $multisupplier_products = get_posts( array(
            'post_type' => 'product',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'productUrl',
                    'value' => 'aliexpress',
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'productUrl',
                    'value' => 'ebay',
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'productUrl',
                    'value' => 'amazon',
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'productUrl',
                    'value' => 'etsy',
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'productUrl',
                    'value' => 'temu',
                    'compare' => 'LIKE',
                ),
            ),
            'fields' => 'ids',
        ) );
        
        foreach ( $multisupplier_products as $product_id ) {
            $views = $this->get_multisupplier_product_views( $product_id );
            $sales = $this->get_multisupplier_product_sales( $product_id );
            $total_views += $views;
            $total_sales += $sales;
        }
        
        if ( $total_views > 0 ) {
            return round( ( $total_sales / $total_views ) * 100, 2 );
        }
        
        return 0;
    }
    
    /**
     * Get average order value
     */
    private function get_multisupplier_average_order_value() {
        $total_sales = $this->get_total_multisupplier_sales();
        $total_revenue = $this->get_total_multisupplier_revenue();
        
        if ( $total_sales > 0 ) {
            return round( $total_revenue / $total_sales, 2 );
        }
        
        return 0;
    }
    
    /**
     * Get products added this month
     */
    private function get_multisupplier_products_this_month() {
        $current_month_start = date( 'Y-m-01' );
        $current_month_end = date( 'Y-m-t' );
        
        $products_this_month = get_posts( array(
            'post_type' => 'product',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'date_query' => array(
                array(
                    'after' => $current_month_start,
                    'before' => $current_month_end,
                    'inclusive' => true,
                ),
            ),
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'productUrl',
                    'value' => 'aliexpress',
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'productUrl',
                    'value' => 'ebay',
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'productUrl',
                    'value' => 'amazon',
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'productUrl',
                    'value' => 'etsy',
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'productUrl',
                    'value' => 'temu',
                    'compare' => 'LIKE',
                ),
            ),
            'fields' => 'ids',
        ) );
        
        return count( $products_this_month );
    }
    
    /**
     * Get top performing supplier
     */
    private function get_multisupplier_top_supplier() {
        $suppliers = array( 'aliexpress', 'ebay', 'amazon', 'etsy', 'temu' );
        $supplier_revenue = array();
        
        foreach ( $suppliers as $supplier ) {
            $supplier_revenue[ $supplier ] = 0;
        }
        
        // Get all multi-supplier products
        $multisupplier_products = get_posts( array(
            'post_type' => 'product',
            'post_status' => 'any',
            'posts_per_page' => -1,
            'meta_query' => array(
                'relation' => 'OR',
                array(
                    'key' => 'productUrl',
                    'value' => 'aliexpress',
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'productUrl',
                    'value' => 'ebay',
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'productUrl',
                    'value' => 'amazon',
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'productUrl',
                    'value' => 'etsy',
                    'compare' => 'LIKE',
                ),
                array(
                    'key' => 'productUrl',
                    'value' => 'temu',
                    'compare' => 'LIKE',
                ),
            ),
            'fields' => 'ids',
        ) );
        
        foreach ( $multisupplier_products as $product_id ) {
            $product_url = get_post_meta( $product_id, 'productUrl', true );
            $supplier = $this->get_supplier_from_url( $product_url );
            if ( in_array( $supplier, $suppliers, true ) ) {
                $revenue = $this->get_multisupplier_product_revenue( $product_id );
                $supplier_revenue[ $supplier ] += $revenue;
            }
        }
        
        // Find the supplier with highest revenue
        $top_supplier = array_keys( $supplier_revenue, max( $supplier_revenue ), true );
        
        if ( ! empty( $top_supplier ) && $supplier_revenue[ $top_supplier[0] ] > 0 ) {
            return ucfirst( $top_supplier[0] );
        }
        
        return esc_html__( 'None', 'sharkdropship-multisupplier' );
    }
    
    /**
     * Get total revenue
     */
    private function get_total_multisupplier_revenue() {
        // Check cache first
        $cache_key = 'multisupplier_total_revenue';
        $total_revenue = wp_cache_get( $cache_key, 'multisupplier' );
        
        if ( false === $total_revenue ) {
            // Use WordPress API instead of direct database query
            $multisupplier_products = get_posts( array(
                'post_type' => 'product',
                'post_status' => 'any',
                'posts_per_page' => -1,
                'meta_query' => array(
                    'relation' => 'OR',
                    array(
                        'key' => 'productUrl',
                        'value' => 'aliexpress',
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key' => 'productUrl',
                        'value' => 'ebay',
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key' => 'productUrl',
                        'value' => 'amazon',
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key' => 'productUrl',
                        'value' => 'etsy',
                        'compare' => 'LIKE',
                    ),
                    array(
                        'key' => 'productUrl',
                        'value' => 'temu',
                        'compare' => 'LIKE',
                    ),
                ),
                'fields' => 'ids',
            ) );
            
            $total_revenue = 0;
            foreach ( $multisupplier_products as $product_id ) {
                $revenue = $this->get_multisupplier_product_revenue( $product_id );
                $total_revenue += $revenue;
            }
            
            // Cache the result for 1 hour
            wp_cache_set( $cache_key, $total_revenue, 'multisupplier', HOUR_IN_SECONDS );
        }
        
        return $total_revenue;
    }
    
    /**
     * Clear plugin cache
     */
    private function clear_multisupplier_cache() {
        wp_cache_delete_group( 'multisupplier' );
    }
    
    /**
     * AJAX get products
     */
    public function multisupplier_ajax_get_products() {
        // Verify nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'multisupplier_nonce' ) ) {
            wp_send_json_error( esc_html__( 'Security check failed.', 'sharkdropship-multisupplier' ) );
        }
        
        $page = isset( $_POST['page'] ) ? absint( $_POST['page'] ) : 1;
        $search = isset( $_POST['search'] ) ? sanitize_text_field( wp_unslash( $_POST['search'] ) ) : '';
        $sort_by = isset( $_POST['sort_by'] ) ? sanitize_text_field( wp_unslash( $_POST['sort_by'] ) ) : 'title';
        $sort_order = isset( $_POST['sort_order'] ) ? sanitize_text_field( wp_unslash( $_POST['sort_order'] ) ) : 'ASC';
        $views_filter = isset( $_POST['views_filter'] ) ? sanitize_text_field( wp_unslash( $_POST['views_filter'] ) ) : '';
        $supplier_filter = isset( $_POST['supplier_filter'] ) ? sanitize_text_field( wp_unslash( $_POST['supplier_filter'] ) ) : '';
        
        $products = $this->get_multisupplier_products( $page, $search, $sort_by, $sort_order, $views_filter, $supplier_filter );
        
        wp_send_json_success( $products );
    }
    
    /**
     * AJAX update views
     */
    public function multisupplier_ajax_update_views() {
        // Verify nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'multisupplier_nonce' ) ) {
            wp_send_json_error( esc_html__( 'Security check failed.', 'sharkdropship-multisupplier' ) );
        }
        
        $product_id = isset( $_POST['product_id'] ) ? absint( $_POST['product_id'] ) : 0;
        
        if ( ! $product_id ) {
            wp_send_json_error( esc_html__( 'Invalid product ID.', 'sharkdropship-multisupplier' ) );
        }
        
        $current_views = get_post_meta( $product_id, 'product_views', true );
        $new_views = $current_views ? absint( $current_views ) + 1 : 1;
        update_post_meta( $product_id, 'product_views', $new_views );
        
        // Clear cache for this product
        wp_cache_delete( 'multisupplier_product_sales_' . $product_id, 'multisupplier' );
        wp_cache_delete( 'multisupplier_product_revenue_' . $product_id, 'multisupplier' );
        
        wp_send_json_success( array( 'views' => $new_views ) );
    }
    
    /**
     * AJAX dismiss notice
     */
    public function multisupplier_ajax_dismiss_notice() {
        // Verify nonce
        $nonce = isset( $_POST['nonce'] ) ? sanitize_text_field( wp_unslash( $_POST['nonce'] ) ) : '';
        if ( ! $nonce || ! wp_verify_nonce( $nonce, 'multisupplier_nonce' ) ) {
            wp_send_json_error( esc_html__( 'Security check failed.', 'sharkdropship-multisupplier' ) );
        }
        
        update_user_meta( get_current_user_id(), 'multisupplier_extension_notice_dismissed', true );
        
        wp_send_json_success();
    }
    
    /**
     * Check if product is from multi-supplier
     */
    private function is_multisupplier_product( $url ) {
        $suppliers = array( 'aliexpress', 'ebay', 'amazon', 'etsy', 'temu' );
        $url_lower = strtolower( $url );
        
        foreach ( $suppliers as $supplier ) {
            if ( strpos( $url_lower, $supplier ) !== false ) {
                return true;
            }
        }
        
        return false;
    }
    
    /**
     * Add styles for the edit page button
     */
    public function add_multisupplier_edit_styles() {
        $screen = get_current_screen();
        
        if ( $screen && $screen->post_type === 'product' ) {
            ?>
            <style type="text/css">
                /* Edit page styles */
                .multisupplier-edit-section {
                    border-top: 1px solid #ddd;
                    padding-top: 12px;
                    margin-top: 12px;
                }
                
                .multisupplier-edit-badge {
                    margin-bottom: 8px;
                }
                
                .supplier-label-edit {
                    display: inline-block;
                    padding: 4px 12px;
                    border-radius: 20px;
                    font-size: 11px;
                    font-weight: 700;
                    text-transform: uppercase;
                    color: white;
                    letter-spacing: 0.5px;
                    box-shadow: 0 2px 4px rgba(0,0,0,0.1);
                    min-width: 80px;
                    text-align: center;
                }
                
                .supplier-label-edit.aliexpress {
                    background: linear-gradient(135deg, #ff4747 0%, #ff6b6b 100%);
                    border: 1px solid #e63939;
                }
                
                .supplier-label-edit.ebay {
                    background: linear-gradient(135deg, #86b817 0%, #9ed929 100%);
                    border: 1px solid #6b9a12;
                }
                
                .supplier-label-edit.amazon {
                    background: linear-gradient(135deg, #ff9900 0%, #ffb84d 100%);
                    border: 1px solid #e68a00;
                }
                
                .supplier-label-edit.etsy {
                    background: linear-gradient(135deg, #f56400 0%, #ff8533 100%);
                    border: 1px solid #cc5500;
                }
                
                .supplier-label-edit.temu {
                    background: linear-gradient(135deg, #ff6b35 0%, #ff8f5c 100%);
                    border: 1px solid #e65a2b;
                }
                
                .multisupplier-edit-original {
                    display: inline-flex !important;
                    align-items: center;
                    justify-content: center;
                    gap: 6px;
                    font-size: 12px !important;
                    font-weight: 600 !important;
                    padding: 8px 16px !important;
                    height: auto !important;
                    line-height: 1.4 !important;
                    background: linear-gradient(135deg, #0073aa 0%, #0099cc 100%) !important;
                    border: 1px solid #005a87 !important;
                    border-radius: 6px !important;
                    color: white !important;
                    text-decoration: none !important;
                    transition: all 0.2s ease-in-out !important;
                    box-shadow: 0 2px 4px rgba(0,115,170,0.2) !important;
                    width: 100% !important;
                    margin-top: 8px !important;
                }
                
                .multisupplier-edit-original:hover {
                    background: linear-gradient(135deg, #005a87 0%, #0073aa 100%) !important;
                    border-color: #004466 !important;
                    color: white !important;
                    transform: translateY(-1px) !important;
                    box-shadow: 0 4px 8px rgba(0,115,170,0.3) !important;
                }
                
                .multisupplier-edit-original:active {
                    transform: translateY(0) !important;
                    box-shadow: 0 2px 4px rgba(0,115,170,0.2) !important;
                }
                
                .multisupplier-edit-original .dashicons {
                    font-size: 14px;
                    width: 14px;
                    height: 14px;
                    margin-top: 1px;
                }
            </style>
            <?php
        }
    }
    
    /**
     * Add button to product edit page
     */
    public function add_multisupplier_edit_button() {
        global $post;
        
        // Only for products
        if ( $post->post_type !== 'product' ) {
            return;
        }
        
        $product_url = get_post_meta( $post->ID, 'productUrl', true );
        
        if ( $product_url && $this->is_multisupplier_product( $product_url ) ) {
            $supplier = $this->get_supplier_from_url( $product_url );
            $supplier_name = ucfirst( $supplier );
            
            ?>
            <div class="misc-pub-section multisupplier-edit-section">
                <div class="multisupplier-edit-badge">
                    <span class="supplier-label-edit <?php echo esc_attr( $supplier ); ?>">
                        <?php echo esc_html( $supplier_name ); ?>
                    </span>
                </div>
                <a href="<?php echo esc_url( $product_url ); ?>" 
                   target="_blank" 
                   class="button button-secondary multisupplier-edit-original" 
                   title="<?php esc_attr_e( 'View Original Product', 'sharkdropship-multisupplier' ); ?>">
                    <span class="dashicons dashicons-external"></span>
                    <?php esc_html_e( 'View Original Product', 'sharkdropship-multisupplier' ); ?>
                </a>
            </div>
            <?php
        }
    }
} 