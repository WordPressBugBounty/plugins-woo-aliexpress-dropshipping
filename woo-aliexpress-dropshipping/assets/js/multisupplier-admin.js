/**
 * Admin JavaScript for Sharkdropship for AliExpress, eBay, Amazon, Etsy and Temu
 *
 * @package Sharkdropship_Multisupplier_WooCommerce
 */

(function($) {
    'use strict';

    $(document).ready(function() {
        
        // Handle Chrome Extension Notice
        $('#dismiss-notice').on('click', function() {
            $('.multisupplier-extension-notice').fadeOut(300, function() {
                $(this).remove();
            });
            
            // Save dismissal preference
            $.ajax({
                url: multisupplier_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sharkdropship_dismiss_notice',
                    nonce: multisupplier_ajax.nonce,
                    notice: 'chrome_extension'
                }
            });
        });
        
        $('#download-extension').on('click', function(e) {
            e.preventDefault();
            // Open the actual Chrome Web Store extension page
            window.open('https://chromewebstore.google.com/detail/sharkdropship-for-temu-al/ajbncoijgeclkangiahiphilnolbdmmh?hl=en', '_blank');
        });
        
        $('#download-extension-no-products').on('click', function(e) {
            e.preventDefault();
            // Open the actual Chrome Web Store extension page
            window.open('https://chromewebstore.google.com/detail/sharkdropship-for-temu-al/ajbncoijgeclkangiahiphilnolbdmmh?hl=en', '_blank');
        });
        
        $('#watch-install-video-no-products').on('click', function(e) {
            e.preventDefault();
            // Open the installation tutorial video
            window.open('https://www.youtube.com/watch?v=Cs-06Xtf_V4', '_blank');
        });
        
        $('#learn-more').on('click', function(e) {
            e.preventDefault();
            // Open the demo video
            window.open('https://www.youtube.com/watch?v=Cs-06Xtf_V4', '_blank');
        });
        
        // Handle "Show Chrome Extension Notice" link
        $('#show-extension-notice').on('click', function(e) {
            e.preventDefault();
            
            // Reset the notice dismissal
            $.ajax({
                url: multisupplier_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sharkdropship_dismiss_notice',
                    nonce: multisupplier_ajax.nonce,
                    notice: 'reset_chrome_extension'
                },
                success: function(response) {
                    if (response.success) {
                        // Reload the page to show the notice
                        location.reload();
                    }
                }
            });
        });
        
        // Handle real-time search
        var searchTimeout;
        $('#search-input').on('input', function() {
            clearTimeout(searchTimeout);
            var searchQuery = $(this).val();
            
            searchTimeout = setTimeout(function() {
                updateProductsList();
            }, 500);
        });
        
        // Handle search submit button
        $('.search-btn').on('click', function(e) {
            e.preventDefault();
            updateProductsList();
        });
        
        // Handle sort and filter changes
        $('#sort-select, #views-filter, #order-select').on('change', function() {
            updateProductsList();
        });
        
        // Handle clear filters button
        $('.button-secondary').on('click', function(e) {
            e.preventDefault();
            
            // Clear all form inputs
            $('#search-input').val('');
            $('#views-filter, #sort-select, #order-select').prop('selectedIndex', 0);
            
            // Update the list
            updateProductsList();
        });
        
        // Function to update products list via AJAX
        function updateProductsList() {
            var searchQuery = $('#search-input').val();
            var sortBy = $('#sort-select').val();
            var sortOrder = $('#order-select').val();
            var viewsFilter = $('#views-filter').val();
            
            // Show loading indicator
            $('.wp-list-table tbody').html('<tr><td colspan="5" class="loading">' + multisupplier_ajax.strings.loading + '</td></tr>');
            
            $.ajax({
                url: multisupplier_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'sharkdropship_get_products',
                    nonce: multisupplier_ajax.nonce,
                    search: searchQuery,
                    sort: sortBy,
                    order: sortOrder,
                    views_filter: viewsFilter,
                    page: 1
                },
                success: function(response) {
                    if (response.success) {
                        displayProducts(response.data);
                                    } else {
                    $('.wp-list-table tbody').html('<tr><td colspan="5" class="error">' + multisupplier_ajax.strings.error + '</td></tr>');
                }
            },
            error: function() {
                $('.wp-list-table tbody').html('<tr><td colspan="5" class="error">' + multisupplier_ajax.strings.error + '</td></tr>');
            }
            });
        }
        
        // Function to display products
        function displayProducts(products) {
            var tbody = $('.wp-list-table tbody');
            tbody.empty();
            
            if (products.length === 0) {
                tbody.html('<tr><td colspan="5">' + multisupplier_ajax.strings.no_products + '</td></tr>');
                return;
            }
            
            $.each(products, function(index, product) {
                var productUrl = product.productUrl || '';
                var views = product.views || 0;
                var sales = product.sales || 0;
                var revenue = product.revenue || 0;
                var editUrl = ajaxurl + '?post=' + product.ID + '&action=edit';
                var previewUrl = product.guid;
                var status = product.post_status || 'publish';
                
                var row = '<tr>' +
                    '<td class="column-title">' +
                        '<div class="product-title-flex" style="display:flex;align-items:center;">' +
                            '<div class="product-thumb-admin-wrapper">' +
                                '<div class="product-thumb-admin-img product-thumb-admin-placeholder">?</div>' +
                            '</div>' +
                            '<div>' +
                                '<strong><a href="' + editUrl + '" class="row-title">' + product.post_title + '</a></strong>' +
                                '<div class="row-actions">' +
                                    '<span class="id">ID: ' + product.ID + '</span> | ' +
                                    '<span class="date">' + product.post_date + '</span>' +
                                '</div>' +
                            '</div>' +
                        '</div>' +
                    '</td>' +
                    '<td class="column-status">' +
                        '<span class="post-state">' + status.charAt(0).toUpperCase() + status.slice(1) + '</span>' +
                    '</td>' +
                    '<td class="column-views">' +
                        '<span class="views-count" data-product-id="' + product.ID + '">' + views + '</span>' +
                    '</td>' +
                    '<td class="column-sales">' +
                        '<div class="sales-info">' +
                            '<span class="sales-count" data-product-id="' + product.ID + '">' + sales + '</span>';
                
                if (revenue > 0) {
                    row += '<div class="sales-revenue"><small>' + multisupplier_ajax.strings.currency_symbol + parseFloat(revenue).toFixed(2) + '</small></div>';
                }
                
                row += '</div></td>' +
                    '<td class="column-actions">';
                
                if (productUrl) {
                                    row += '<a href="' + productUrl + '" target="_blank" class="button button-small button-supplier">' +
                       '<span class="dashicons dashicons-external"></span> ' + multisupplier_ajax.strings.open_supplier + '</a>';
                }
                
                row += '<a href="' + editUrl + '" class="button button-small button-edit">' +
                       '<span class="dashicons dashicons-edit"></span> ' + multisupplier_ajax.strings.edit + '</a>' +
                       '<a href="' + previewUrl + '" target="_blank" class="button button-small button-preview">' +
                       '<span class="dashicons dashicons-visibility"></span> ' + multisupplier_ajax.strings.preview + '</a>' +
                       '</td></tr>';
                
                tbody.append(row);
            });
        }
        
        // Handle pagination clicks - let PHP handle pagination naturally
        $(document).on('click', '.tablenav-pages a', function(e) {
            // Allow normal navigation, don't prevent default
            // This ensures PHP pagination works correctly
        });
        
        // Add strings to localized data
        if (typeof multisupplier_ajax !== 'undefined') {
            multisupplier_ajax.strings = multisupplier_ajax.strings || {};
            multisupplier_ajax.strings.no_products = 'No products found.';
            multisupplier_ajax.strings.open_supplier = 'Open Supplier';
            multisupplier_ajax.strings.edit = 'Edit';
            multisupplier_ajax.strings.preview = 'Preview';
            multisupplier_ajax.strings.delete = 'Delete';
            multisupplier_ajax.strings.delete_confirm = 'Are you sure you want to delete this product? This action cannot be undone.';
            multisupplier_ajax.strings.delete_success = 'Product deleted successfully.';
            multisupplier_ajax.strings.delete_error = 'Failed to delete product.';
            multisupplier_ajax.strings.cancel = 'Cancel';
        }
        
        // Delete product functionality
        var deleteProductId = null;
        var deleteProductTitle = null;
        
        // Show delete confirmation modal
        function showDeleteModal(productId, productTitle) {
            deleteProductId = productId;
            deleteProductTitle = productTitle;
            
            var modal = $('#deleteConfirmationModal');
            var message = modal.find('.delete-confirmation-message');
            
            // Update message with product title
            message.text(multisupplier_ajax.strings.delete_confirm.replace('this product', '"' + productTitle + '"'));
            
            modal.addClass('show');
        }
        
        // Hide delete confirmation modal
        function hideDeleteModal() {
            $('#deleteConfirmationModal').removeClass('show');
            deleteProductId = null;
            deleteProductTitle = null;
        }
        
        // Handle delete button click
        $(document).on('click', '.button-delete', function(e) {
            e.preventDefault();
            var productId = $(this).data('product-id');
            var productTitle = $(this).data('product-title');
            showDeleteModal(productId, productTitle);
        });
        
        // Handle cancel delete
        $(document).on('click', '#cancelDelete', function(e) {
            e.preventDefault();
            hideDeleteModal();
        });
        
        // Handle confirm delete
        $(document).on('click', '#confirmDelete', function(e) {
            e.preventDefault();
            
            if (!deleteProductId) {
                return;
            }
            
            var button = $(this);
            var originalText = button.text();
            
            // Disable button and show loading
            button.prop('disabled', true).text('Deleting...');
            
            $.ajax({
                url: multisupplier_ajax.ajax_url,
                type: 'POST',
                data: {
                    action: 'multisupplier_delete_product',
                    nonce: multisupplier_ajax.nonce,
                    product_id: deleteProductId
                },
                success: function(response) {
                    if (response.success) {
                        // Show success message
                        var notice = $('<div class="notice notice-success is-dismissible"><p>' + response.data.message + '</p></div>');
                        $('.wrap h1').after(notice);
                        
                        // Remove the row from the table
                        $('tr').each(function() {
                            var deleteButton = $(this).find('.button-delete[data-product-id="' + deleteProductId + '"]');
                            if (deleteButton.length > 0) {
                                $(this).fadeOut(300, function() {
                                    $(this).remove();
                                    
                                    // Check if table is empty
                                    if ($('.wp-list-table tbody tr').length === 0) {
                                        $('.wp-list-table tbody').html('<tr><td colspan="7" class="no-products">' + multisupplier_ajax.strings.no_products + '</td></tr>');
                                    }
                                });
                            }
                        });
                        
                        hideDeleteModal();
                    } else {
                        // Show error message
                        var notice = $('<div class="notice notice-error is-dismissible"><p>' + response.data + '</p></div>');
                        $('.wrap h1').after(notice);
                        hideDeleteModal();
                    }
                },
                error: function() {
                    // Show error message
                    var notice = $('<div class="notice notice-error is-dismissible"><p>' + multisupplier_ajax.strings.delete_error + '</p></div>');
                    $('.wrap h1').after(notice);
                    hideDeleteModal();
                },
                complete: function() {
                    // Re-enable button
                    button.prop('disabled', false).text(originalText);
                }
            });
        });
        
        // Close modal when clicking outside
        $(document).on('click', '.delete-confirmation-modal', function(e) {
            if (e.target === this) {
                hideDeleteModal();
            }
        });
        
        // Close modal with Escape key
        $(document).on('keydown', function(e) {
            if (e.key === 'Escape' && $('#deleteConfirmationModal').hasClass('show')) {
                hideDeleteModal();
            }
        });
        
    });

})(jQuery); 