/**
 * Frontend JavaScript for Sharkdropship for AliExpress, eBay, Amazon, Etsy and Temu
 *
 * @package Sharkdropship_Multisupplier_WooCommerce
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Track product view when page loads
        if (typeof multisupplier_frontend !== 'undefined' && multisupplier_frontend.product_id) {
            trackProductView(multisupplier_frontend.product_id);
        }
        
        // Function to track product view
        function trackProductView(productId) {
            $.ajax({
                url: multisupplier_frontend.ajax_url,
                type: 'POST',
                data: {
                    action: 'multisupplier_frontend_track_view',
                    nonce: multisupplier_frontend.nonce,
                    product_id: productId
                },
                success: function(response) {
                    if (response.success) {
                        // View tracked successfully
                        if (typeof console !== 'undefined' && console.log) {
                            console.log('Product view tracked: ' + response.data.message);
                        }
                    } else {
                        // Handle error
                        if (typeof console !== 'undefined' && console.error) {
                            console.error('Failed to track product view: ' + response.data);
                        }
                    }
                },
                error: function(xhr, status, error) {
                    // Handle AJAX error
                    if (typeof console !== 'undefined' && console.error) {
                        console.error('AJAX error tracking product view:', error);
                    }
                }
            });
        }
        
    });

})(jQuery); 