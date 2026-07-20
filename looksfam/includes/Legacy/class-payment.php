<?php 
/**
 * PayMaya Admin Settings
 * Add this to your functions.php or create a separate plugin file
 */

// Add PayMaya Settings Menu
add_action('admin_menu', 'paymaya_add_admin_menu');
function paymaya_add_admin_menu() {
    add_menu_page(
        'PayMaya Settings',
        'PayMaya',
        'manage_options',
        'paymaya-settings',
        'paymaya_settings_page',
        'dashicons-money-alt',
        30
    );
}

// Register Settings
add_action('admin_init', 'paymaya_register_settings');
function paymaya_register_settings() {
    register_setting('paymaya_settings_group', 'paymaya_public_key');
    register_setting('paymaya_settings_group', 'paymaya_secret_key');
    register_setting('paymaya_settings_group', 'paymaya_mode'); // sandbox or live
    register_setting('paymaya_settings_group', 'paymaya_success_url');
    register_setting('paymaya_settings_group', 'paymaya_failure_url');
    register_setting('paymaya_settings_group', 'paymaya_cancel_url');
    register_setting('paymaya_settings_group', 'paymaya_webhook_secret');
}

// Settings Page HTML
function paymaya_settings_page() {
    ?>
    <div class="wrap">
        <h1>PayMaya Payment Gateway Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields('paymaya_settings_group'); ?>
            <?php do_settings_sections('paymaya_settings_group'); ?>
            
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Environment Mode</th>
                    <td>
                        <select name="paymaya_mode">
                            <option value="sandbox" <?php selected(get_option('paymaya_mode'), 'sandbox'); ?>>Sandbox (Test)</option>
                            <option value="live" <?php selected(get_option('paymaya_mode'), 'live'); ?>>Live (Production)</option>
                        </select>
                        <p class="description">Select Sandbox for testing, Live for production payments</p>
                    </td>
                </tr>
                
                <tr valign="top">
                    <th scope="row">Public Key</th>
                    <td>
                        <input type="text" name="paymaya_public_key" value="<?php echo esc_attr(get_option('paymaya_public_key')); ?>" class="regular-text" />
                        <p class="description">Your PayMaya Public API Key</p>
                    </td>
                </tr>
                
                <tr valign="top">
                    <th scope="row">Secret Key</th>
                    <td>
                        <input type="password" name="paymaya_secret_key" value="<?php echo esc_attr(get_option('paymaya_secret_key')); ?>" class="regular-text" />
                        <p class="description">Your PayMaya Secret API Key (keep this secure!)</p>
                    </td>
                </tr>
                
                <tr valign="top">
                    <th scope="row">Webhook Secret</th>
                    <td>
                        <input type="text" name="paymaya_webhook_secret" value="<?php echo esc_attr(get_option('paymaya_webhook_secret')); ?>" class="regular-text" />
                        <p class="description">Webhook secret for validating PayMaya callbacks</p>
                    </td>
                </tr>
                
                <tr valign="top">
                    <th scope="row">Success URL</th>
                    <td>
                        <input type="url" name="paymaya_success_url" value="<?php echo esc_attr(get_option('paymaya_success_url', home_url('/thank-you'))); ?>" class="regular-text" />
                        <p class="description">URL to redirect after successful payment</p>
                    </td>
                </tr>
                
                <tr valign="top">
                    <th scope="row">Failure URL</th>
                    <td>
                        <input type="url" name="paymaya_failure_url" value="<?php echo esc_attr(get_option('paymaya_failure_url', home_url('/payment-failed'))); ?>" class="regular-text" />
                        <p class="description">URL to redirect after failed payment</p>
                    </td>
                </tr>
                
                <tr valign="top">
                    <th scope="row">Cancel URL</th>
                    <td>
                        <input type="url" name="paymaya_cancel_url" value="<?php echo esc_attr(get_option('paymaya_cancel_url', home_url('/payment-cancelled'))); ?>" class="regular-text" />
                        <p class="description">URL to redirect if payment is cancelled</p>
                    </td>
                </tr>
            </table>
            
            <h2>Webhook Information</h2>
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">Webhook URL</th>
                    <td>
                        <input type="text" value="<?php echo home_url('/wp-json/paymaya/v1/webhook'); ?>" class="regular-text" readonly />
                        <button type="button" onclick="navigator.clipboard.writeText('<?php echo home_url('/wp-json/paymaya/v1/webhook'); ?>')" class="button">Copy</button>
                        <p class="description">Use this URL in your PayMaya dashboard for webhook notifications</p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button(); ?>
        </form>
        
        <hr>
        
        <h2>Test Connection</h2>
        <button type="button" id="test-paymaya-connection" class="button button-secondary">Test PayMaya Connection</button>
        <div id="test-result" style="margin-top: 10px;"></div>
        
        <script>
        jQuery(document).ready(function($) {
            $('#test-paymaya-connection').on('click', function() {
                var button = $(this);
                button.prop('disabled', true).text('Testing...');
                
                $.post(ajaxurl, {
                    action: 'test_paymaya_connection'
                }, function(response) {
                    $('#test-result').html('<div class="notice notice-' + (response.success ? 'success' : 'error') + '"><p>' + response.data.message + '</p></div>');
                    button.prop('disabled', false).text('Test PayMaya Connection');
                });
            });
        });
        </script>
    </div>
    <?php
}

// Test Connection AJAX Handler
add_action('wp_ajax_test_paymaya_connection', 'test_paymaya_connection');
function test_paymaya_connection() {
    $public_key = get_option('paymaya_public_key');
    $mode = get_option('paymaya_mode', 'sandbox');
    
    if (empty($public_key)) {
        wp_send_json_error(array('message' => 'Public key is not configured'));
    }
    
    $api_url = $mode === 'live'
        ? 'https://pg.maya.ph/checkout/v1/checkouts'
        : 'https://pg-sandbox.paymaya.com/checkout/v1/checkouts';
    
    $test_data = array(
        'totalAmount' => array(
            'value' => 100.00,
            'currency' => 'PHP'
        ),
        'buyer' => array(
            'firstName' => 'Test',
            'lastName' => 'User',
            'contact' => array(
                'email' => 'test@example.com'
            )
        ),
        'items' => array(
            array(
                'name' => 'Test Item',
                'quantity' => 1,
                'amount' => array('value' => 100.00),
                'totalAmount' => array('value' => 100.00)
            )
        ),
        'requestReferenceNumber' => 'TEST-' . time(),
        'redirectUrl' => array(
            'success' => home_url('/checkout-success'),
            'failure' => home_url('/checkout-failure'),
            'cancel'  => home_url('/checkout-cancel'),
        )
    );

    $response = wp_remote_post($api_url, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            // ✅ Use PUBLIC KEY here
            'Authorization' => 'Basic ' . base64_encode($public_key . ':')
        ),
        'body' => json_encode($test_data),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'Connection failed: ' . $response->get_error_message()));
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    $body = wp_remote_retrieve_body($response);
    
    if ($status_code === 201) {
        wp_send_json_success(array('message' => 'Connection successful! Maya API is working correctly.', 'body' => $body));
    } else {
        wp_send_json_error(array('message' => 'Connection failed with status ' . $status_code . ': ' . $body));
    }
}


/**
 * PayMaya Response Pages
 * Add these shortcodes to your success, failure, and cancellation pages
 */

// Thank You Page Shortcode
function paymaya_thank_you_page() {
    $txn_id = isset($_GET['txn']) ? intval($_GET['txn']) : 0;
    $ref = isset($_GET['ref']) ? sanitize_text_field($_GET['ref']) : '';
    
    ob_start();
    
    if ($txn_id > 0) {
        $transaction = get_post($txn_id);
        
        if ($transaction && $transaction->post_type === 'transaction') {
            $class_id = get_post_meta($txn_id, 'class_id', true);
            $duration = get_post_meta($txn_id, 'duration', true);
            $price = get_post_meta($txn_id, 'price', true);
            $payment_status = get_post_meta($txn_id, 'payment_status', true);
            $class = get_post($class_id);
            $class_title = $class ? $class->post_title : 'N/A';
            ?>
            <div class="paymaya-thank-you-container">
                <?php if ($payment_status === 'completed'): ?>
                    <div class="success-icon">✓</div>
                    <h2>Payment Successful!</h2>
                    <p class="subtitle">Thank you for your purchase. Your enrollment has been confirmed.</p>
                <?php else: ?>
                    <div class="pending-icon">⏳</div>
                    <h2>Payment Processing</h2>
                    <p class="subtitle">Your payment is being processed. You will receive a confirmation email shortly.</p>
                <?php endif; ?>
                
                <div class="order-details">
                    <h3>Order Details</h3>
                    <table>
                        <tr>
                            <td><strong>Reference:</strong></td>
                            <td><?php echo esc_html($transaction->post_title); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Class:</strong></td>
                            <td><?php echo esc_html($class_title); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Duration:</strong></td>
                            <td><?php echo esc_html($duration); ?> month<?php echo $duration > 1 ? 's' : ''; ?></td>
                        </tr>
                        <tr>
                            <td><strong>Amount:</strong></td>
                            <td>₱<?php echo number_format($price, 2); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Status:</strong></td>
                            <td><span class="status-<?php echo esc_attr($payment_status); ?>"><?php echo ucfirst($payment_status); ?></span></td>
                        </tr>
                    </table>
                </div>
                
                <?php if ($payment_status === 'completed'): ?>
                    <p class="info-text">You can now access your class and start learning!</p>
                    <div class="button-group">
                        <a href="<?php echo home_url('/my-classes'); ?>" class="btn btn-primary">View My Classes</a>
                        <a href="<?php echo home_url(); ?>" class="btn btn-secondary">Back to Home</a>
                    </div>
                <?php else: ?>
                    <p class="info-text">If your payment is successful, you will be automatically enrolled. Check your email for confirmation.</p>
                    <div class="button-group">
                        <a href="<?php echo home_url(); ?>" class="btn btn-primary">Back to Home</a>
                    </div>
                <?php endif; ?>
            </div>
            
            <style>
                .paymaya-thank-you-container {
                    max-width: 600px;
                    margin: 50px auto;
                    padding: 40px;
                    background: #ffffff;
                    border-radius: 10px;
                    text-align: center;
                    box-shadow: 0 2px 20px rgba(0,0,0,0.1);
                }
                .success-icon {
                    font-size: 80px;
                    color: #4CAF50;
                    margin-bottom: 20px;
                    line-height: 1;
                }
                .pending-icon {
                    font-size: 80px;
                    color: #FF9800;
                    margin-bottom: 20px;
                    line-height: 1;
                }
                .paymaya-thank-you-container h2 {
                    color: #333;
                    margin-bottom: 10px;
                    font-size: 28px;
                }
                .subtitle {
                    color: #666;
                    font-size: 16px;
                    margin-bottom: 30px;
                }
                .order-details {
                    background: #f9f9f9;
                    padding: 25px;
                    border-radius: 8px;
                    margin: 30px 0;
                    text-align: left;
                }
                .order-details h3 {
                    color: #333;
                    border-bottom: 2px solid #4CAF50;
                    padding-bottom: 10px;
                    margin-bottom: 20px;
                    margin-top: 0;
                }
                .order-details table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .order-details td {
                    padding: 8px 0;
                    color: #555;
                }
                .order-details td:first-child {
                    width: 35%;
                }
                .status-completed {
                    color: #4CAF50;
                    font-weight: bold;
                }
                .status-pending {
                    color: #FF9800;
                    font-weight: bold;
                }
                .info-text {
                    color: #666;
                    margin: 20px 0;
                    font-size: 15px;
                }
                .button-group {
                    margin-top: 30px;
                }
                .btn {
                    display: inline-block;
                    padding: 15px 30px;
                    text-decoration: none;
                    border-radius: 5px;
                    font-weight: bold;
                    margin: 5px;
                    transition: opacity 0.3s;
                }
                .btn:hover {
                    opacity: 0.9;
                }
                .btn-primary {
                    background-color: #4CAF50;
                    color: white;
                }
                .btn-secondary {
                    background-color: #2196F3;
                    color: white;
                }
                @media (max-width: 600px) {
                    .paymaya-thank-you-container {
                        margin: 20px;
                        padding: 30px 20px;
                    }
                    .btn {
                        display: block;
                        margin: 10px 0;
                    }
                }
            </style>
            <?php
        } else {
            ?>
            <div class="paymaya-thank-you-container">
                <p>Transaction not found.</p>
                <a href="<?php echo home_url(); ?>" class="btn btn-primary">Back to Home</a>
            </div>
            <?php
        }
    } elseif (!empty($ref)) {
        ?>
        <div class="paymaya-thank-you-container">
            <div class="success-icon">✓</div>
            <h2>Enrollment Successful!</h2>
            <p class="subtitle">Your enrollment has been confirmed.</p>
            
            <div class="order-details">
                <p><strong>Reference Number:</strong> <?php echo esc_html($ref); ?></p>
                <p style="margin-top: 15px;">Check your email for details.</p>
            </div>
            
            <a href="<?php echo home_url(); ?>" class="btn btn-primary">Start Learning</a>
        </div>
        
        <style>
            .paymaya-thank-you-container {
                max-width: 600px;
                margin: 50px auto;
                padding: 40px;
                background: #ffffff;
                border-radius: 10px;
                text-align: center;
                box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            }
            .success-icon {
                font-size: 80px;
                color: #4CAF50;
                margin-bottom: 20px;
                line-height: 1;
            }
            .paymaya-thank-you-container h2 {
                color: #333;
                margin-bottom: 10px;
                font-size: 28px;
            }
            .subtitle {
                color: #666;
                font-size: 16px;
                margin-bottom: 30px;
            }
            .order-details {
                background: #f9f9f9;
                padding: 25px;
                border-radius: 8px;
                margin: 30px 0;
            }
            .btn {
                display: inline-block;
                padding: 15px 30px;
                background-color: #4CAF50;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                font-weight: bold;
                transition: opacity 0.3s;
            }
            .btn:hover {
                opacity: 0.9;
            }
        </style>
        <?php
    } else {
        ?>
        <div class="paymaya-thank-you-container">
            <p>No transaction information found.</p>
            <a href="<?php echo home_url(); ?>" class="btn btn-primary">Back to Home</a>
        </div>
        
        <style>
            .paymaya-thank-you-container {
                max-width: 600px;
                margin: 50px auto;
                padding: 40px;
                background: #ffffff;
                border-radius: 10px;
                text-align: center;
                box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            }
            .btn {
                display: inline-block;
                padding: 15px 30px;
                background-color: #4CAF50;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                font-weight: bold;
                margin-top: 20px;
            }
        </style>
        <?php
    }
    
    return ob_get_clean();
}
add_shortcode('paymaya_thank_you', 'paymaya_thank_you_page');

// Payment Failed Page Shortcode
function paymaya_payment_failed_page() {
    $txn_id = isset($_GET['txn']) ? intval($_GET['txn']) : 0;
    
    ob_start();
    
    if ($txn_id > 0) {
        $transaction = get_post($txn_id);
        
        if ($transaction && $transaction->post_type === 'transaction') {
            $class_id = get_post_meta($txn_id, 'class_id', true);
            $price = get_post_meta($txn_id, 'price', true);
            $failure_reason = get_post_meta($txn_id, 'payment_failure_reason', true);
            $class = get_post($class_id);
            $class_title = $class ? $class->post_title : 'N/A';
            ?>
            <div class="paymaya-failed-container">
                <div class="failed-icon">✗</div>
                <h2>Payment Failed</h2>
                <p class="subtitle">We're sorry, but your payment could not be processed.</p>
                
                <?php if (!empty($failure_reason)): ?>
                    <div class="error-message">
                        <strong>Reason:</strong> <?php echo esc_html($failure_reason); ?>
                    </div>
                <?php endif; ?>
                
                <div class="order-details">
                    <h3>Order Details</h3>
                    <table>
                        <tr>
                            <td><strong>Reference:</strong></td>
                            <td><?php echo esc_html($transaction->post_title); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Class:</strong></td>
                            <td><?php echo esc_html($class_title); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Amount:</strong></td>
                            <td>₱<?php echo number_format($price, 2); ?></td>
                        </tr>
                    </table>
                </div>
                
                <p class="info-text">Please try again or contact support if the problem persists.</p>
                
                <div class="button-group">
                    <a href="<?php echo add_query_arg('class', $class_id, home_url('/checkout')); ?>" class="btn btn-primary">Try Again</a>
                    <a href="<?php echo home_url('/contact'); ?>" class="btn btn-warning">Contact Support</a>
                </div>
            </div>
            
            <style>
                .paymaya-failed-container {
                    max-width: 600px;
                    margin: 50px auto;
                    padding: 40px;
                    background: #ffffff;
                    border-radius: 10px;
                    text-align: center;
                    box-shadow: 0 2px 20px rgba(0,0,0,0.1);
                }
                .failed-icon {
                    font-size: 80px;
                    color: #f44336;
                    margin-bottom: 20px;
                    line-height: 1;
                }
                .paymaya-failed-container h2 {
                    color: #333;
                    margin-bottom: 10px;
                    font-size: 28px;
                }
                .subtitle {
                    color: #666;
                    font-size: 16px;
                    margin-bottom: 30px;
                }
                .error-message {
                    background: #ffebee;
                    padding: 15px;
                    border-radius: 5px;
                    margin: 20px 0;
                    color: #c62828;
                    border-left: 4px solid #f44336;
                }
                .order-details {
                    background: #f9f9f9;
                    padding: 25px;
                    border-radius: 8px;
                    margin: 30px 0;
                    text-align: left;
                }
                .order-details h3 {
                    color: #333;
                    border-bottom: 2px solid #f44336;
                    padding-bottom: 10px;
                    margin-bottom: 20px;
                    margin-top: 0;
                }
                .order-details table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .order-details td {
                    padding: 8px 0;
                    color: #555;
                }
                .order-details td:first-child {
                    width: 35%;
                }
                .info-text {
                    color: #666;
                    margin: 20px 0;
                    font-size: 15px;
                }
                .button-group {
                    margin-top: 30px;
                }
                .btn {
                    display: inline-block;
                    padding: 15px 30px;
                    text-decoration: none;
                    border-radius: 5px;
                    font-weight: bold;
                    margin: 5px;
                    transition: opacity 0.3s;
                }
                .btn:hover {
                    opacity: 0.9;
                }
                .btn-primary {
                    background-color: #4CAF50;
                    color: white;
                }
                .btn-warning {
                    background-color: #FF9800;
                    color: white;
                }
                @media (max-width: 600px) {
                    .paymaya-failed-container {
                        margin: 20px;
                        padding: 30px 20px;
                    }
                    .btn {
                        display: block;
                        margin: 10px 0;
                    }
                }
            </style>
            <?php
        } else {
            ?>
            <div class="paymaya-failed-container">
                <p>Transaction not found.</p>
                <a href="<?php echo home_url(); ?>" class="btn btn-primary">Back to Home</a>
            </div>
            <?php
        }
    } else {
        ?>
        <div class="paymaya-failed-container">
            <div class="failed-icon">✗</div>
            <h2>Payment Failed</h2>
            <p class="subtitle">No transaction information found.</p>
            <a href="<?php echo home_url(); ?>" class="btn btn-primary">Back to Home</a>
        </div>
        
        <style>
            .paymaya-failed-container {
                max-width: 600px;
                margin: 50px auto;
                padding: 40px;
                background: #ffffff;
                border-radius: 10px;
                text-align: center;
                box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            }
            .failed-icon {
                font-size: 80px;
                color: #f44336;
                margin-bottom: 20px;
                line-height: 1;
            }
            .paymaya-failed-container h2 {
                color: #333;
                margin-bottom: 10px;
                font-size: 28px;
            }
            .subtitle {
                color: #666;
                font-size: 16px;
                margin-bottom: 30px;
            }
            .btn {
                display: inline-block;
                padding: 15px 30px;
                background-color: #4CAF50;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                font-weight: bold;
                margin-top: 20px;
            }
        </style>
        <?php
    }
    
    return ob_get_clean();
}
add_shortcode('paymaya_payment_failed', 'paymaya_payment_failed_page');

// Payment Cancelled Page Shortcode
function paymaya_payment_cancelled_page() {
    $txn_id = isset($_GET['txn']) ? intval($_GET['txn']) : 0;
    
    ob_start();
    
    if ($txn_id > 0) {
        $transaction = get_post($txn_id);
        
        if ($transaction && $transaction->post_type === 'transaction') {
            $class_id = get_post_meta($txn_id, 'class_id', true);
            $price = get_post_meta($txn_id, 'price', true);
            $class = get_post($class_id);
            $class_title = $class ? $class->post_title : 'N/A';
            ?>
            <div class="paymaya-cancelled-container">
                <div class="cancelled-icon">⚠</div>
                <h2>Payment Cancelled</h2>
                <p class="subtitle">You have cancelled your payment.</p>
                
                <div class="order-details">
                    <h3>Order Details</h3>
                    <table>
                        <tr>
                            <td><strong>Reference:</strong></td>
                            <td><?php echo esc_html($transaction->post_title); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Class:</strong></td>
                            <td><?php echo esc_html($class_title); ?></td>
                        </tr>
                        <tr>
                            <td><strong>Amount:</strong></td>
                            <td>₱<?php echo number_format($price, 2); ?></td>
                        </tr>
                    </table>
                </div>
                
                <p class="info-text">No charges were made to your account. You can try again when you're ready.</p>
                
                <div class="button-group">
                    <a href="<?php echo add_query_arg('class', $class_id, home_url('/checkout')); ?>" class="btn btn-primary">Complete Purchase</a>
                    <a href="<?php echo home_url(); ?>" class="btn btn-secondary">Back to Home</a>
                </div>
            </div>
            
            <style>
                .paymaya-cancelled-container {
                    max-width: 600px;
                    margin: 50px auto;
                    padding: 40px;
                    background: #ffffff;
                    border-radius: 10px;
                    text-align: center;
                    box-shadow: 0 2px 20px rgba(0,0,0,0.1);
                }
                .cancelled-icon {
                    font-size: 80px;
                    color: #FF9800;
                    margin-bottom: 20px;
                    line-height: 1;
                }
                .paymaya-cancelled-container h2 {
                    color: #333;
                    margin-bottom: 10px;
                    font-size: 28px;
                }
                .subtitle {
                    color: #666;
                    font-size: 16px;
                    margin-bottom: 30px;
                }
                .order-details {
                    background: #f9f9f9;
                    padding: 25px;
                    border-radius: 8px;
                    margin: 30px 0;
                    text-align: left;
                }
                .order-details h3 {
                    color: #333;
                    border-bottom: 2px solid #FF9800;
                    padding-bottom: 10px;
                    margin-bottom: 20px;
                    margin-top: 0;
                }
                .order-details table {
                    width: 100%;
                    border-collapse: collapse;
                }
                .order-details td {
                    padding: 8px 0;
                    color: #555;
                }
                .order-details td:first-child {
                    width: 35%;
                }
                .info-text {
                    color: #666;
                    margin: 20px 0;
                    font-size: 15px;
                }
                .button-group {
                    margin-top: 30px;
                }
                .btn {
                    display: inline-block;
                    padding: 15px 30px;
                    text-decoration: none;
                    border-radius: 5px;
                    font-weight: bold;
                    margin: 5px;
                    transition: opacity 0.3s;
                }
                .btn:hover {
                    opacity: 0.9;
                }
                .btn-primary {
                    background-color: #4CAF50;
                    color: white;
                }
                .btn-secondary {
                    background-color: #9E9E9E;
                    color: white;
                }
                @media (max-width: 600px) {
                    .paymaya-cancelled-container {
                        margin: 20px;
                        padding: 30px 20px;
                    }
                    .btn {
                        display: block;
                        margin: 10px 0;
                    }
                }
            </style>
            <?php
        } else {
            ?>
            <div class="paymaya-cancelled-container">
                <p>Transaction not found.</p>
                <a href="<?php echo home_url(); ?>" class="btn btn-primary">Back to Home</a>
            </div>
            <?php
        }
    } else {
        ?>
        <div class="paymaya-cancelled-container">
            <div class="cancelled-icon">⚠</div>
            <h2>Payment Cancelled</h2>
            <p class="subtitle">No transaction information found.</p>
            <a href="<?php echo home_url(); ?>" class="btn btn-primary">Back to Home</a>
        </div>
        
        <style>
            .paymaya-cancelled-container {
                max-width: 600px;
                margin: 50px auto;
                padding: 40px;
                background: #ffffff;
                border-radius: 10px;
                text-align: center;
                box-shadow: 0 2px 20px rgba(0,0,0,0.1);
            }
            .cancelled-icon {
                font-size: 80px;
                color: #FF9800;
                margin-bottom: 20px;
                line-height: 1;
            }
            .paymaya-cancelled-container h2 {
                color: #333;
                margin-bottom: 10px;
                font-size: 28px;
            }
            .subtitle {
                color: #666;
                font-size: 16px;
                margin-bottom: 30px;
            }
            .btn {
                display: inline-block;
                padding: 15px 30px;
                background-color: #4CAF50;
                color: white;
                text-decoration: none;
                border-radius: 5px;
                font-weight: bold;
                margin-top: 20px;
            }
        </style>
        <?php
    }
    
    return ob_get_clean();
}
add_shortcode('paymaya_payment_cancelled', 'paymaya_payment_cancelled_page');

// Admin column to show PayMaya transaction details
add_filter('manage_transaction_posts_columns', 'paymaya_transaction_columns');
function paymaya_transaction_columns($columns) {
    $columns['payment_method'] = 'Payment Method';
    $columns['payment_status'] = 'Status';
    $columns['amount'] = 'Amount';
    $columns['user'] = 'User ID';
    $columns['email'] = 'Email';
    $columns['paymaya_id'] = 'PayMaya ID';
    return $columns;
}

add_action('manage_transaction_posts_custom_column', 'paymaya_transaction_column_content', 10, 2);
function paymaya_transaction_column_content($column, $post_id) {
    switch ($column) {
        case 'payment_method':
            $method = get_post_meta($post_id, 'payment_method', true);
            echo esc_html(ucfirst($method));
            break;
            
        case 'payment_status':
            $status = get_post_meta($post_id, 'payment_status', true);
            $color = '';
            switch ($status) {
                case 'completed':
                    $color = '#4CAF50';
                    break;
                case 'pending':
                    $color = '#FF9800';
                    break;
                case 'failed':
                    $color = '#f44336';
                    break;
                default:
                    $color = '#9E9E9E';
            }
            echo '<span style="color: ' . $color . '; font-weight: bold;">' . esc_html(ucfirst($status)) . '</span>';
            break;
            
        case 'amount':
            $price = get_post_meta($post_id, 'price', true);
            echo '₱' . number_format($price, 2);
            break;
        case 'user':
            $user = get_post_meta($post_id, 'user_id', true);
            echo $user;
            break;
        case 'email':
            $email = get_post_meta($post_id, 'email', true);
            echo $email;
            break;
            
        case 'paymaya_id':
            $paymaya_id = get_post_meta($post_id, 'paymaya_payment_id', true);
            echo !empty($paymaya_id) ? esc_html($paymaya_id) : '—';
            break;
    }
}


// Create PayMaya Checkout AJAX Handler
add_action('wp_ajax_create_paymaya_checkout', 'create_paymaya_checkout');
add_action('wp_ajax_nopriv_create_paymaya_checkout', 'create_paymaya_checkout');
function create_paymaya_checkout() {
    $errors = array();
    
    // Validate inputs
    $class_id = intval($_POST['class_id']);
    if (!get_post($class_id) || get_post_type($class_id) !== 'class') {
        wp_send_json_error(array('message' => 'Invalid class selected'));
    }

    $duration = floatval($_POST['duration']);
    $price = floatval($_POST['price']);
    
    if ($duration <= 0 || $price <= 0) {
        wp_send_json_error(array('message' => 'Invalid duration or price'));
    }

    $is_logged_in = isset($_POST['is_logged_in']) && $_POST['is_logged_in'] == '1';
    $user_id = 0;

    // Handle user creation or validation
    if (!$is_logged_in) {
        $username = sanitize_user($_POST['username']);
        $password = $_POST['password'];
        $email = sanitize_email($_POST['email']);

        // Validate registration fields
        if (empty($username) || username_exists($username)) {
            wp_send_json_error(array('message' => 'Invalid username or username already exists'));
        }

        if (empty($password) || strlen($password) < 6) {
            wp_send_json_error(array('message' => 'Password must be at least 6 characters'));
        }

        if (empty($email) || !is_email($email) || email_exists($email)) {
            wp_send_json_error(array('message' => 'Invalid email or email already exists'));
        }

        // Create user
        $user_id = wp_create_user($username, $password, $email);
        
        if (is_wp_error($user_id)) {
            wp_send_json_error(array('message' => 'Failed to create user account'));
        }

        // Set user's display name
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        
        wp_update_user(array(
            'ID' => $user_id,
            'first_name' => $first_name,
            'last_name' => $last_name,
            'display_name' => trim($first_name . ' ' . $last_name)
        ));

        // Log in the new user
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
    } else {
        $user_id = get_current_user_id();
        if (!$user_id) {
            wp_send_json_error(array('message' => 'You must be logged in'));
        }
    }

    // Get user details
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    $email = sanitize_email($_POST['email']);
    $name = trim($first_name . ' ' . $last_name);
    
    // Generate reference number
    $ref_number = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 16));
    $key = strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 6));
    
    // Create pending transaction
    $transaction_id = wp_insert_post(array(
        'post_title'    => $ref_number,
        'post_type'     => 'transaction',
        'post_status'   => 'pending',
        'post_author'   => $user_id,
    ));

    if (!$transaction_id) {
        wp_send_json_error(array('message' => 'Failed to create transaction'));
    }

    // Save transaction metadata
    update_post_meta($transaction_id, 'class_id', $class_id);
    update_post_meta($transaction_id, 'duration', $duration);
    update_post_meta($transaction_id, 'user_id', $user_id);
    update_post_meta($transaction_id, 'email', $email);
    update_post_meta($transaction_id, 'name', $name);
    update_post_meta($transaction_id, 'payment_method', 'paymaya');
    update_post_meta($transaction_id, 'key', $key);
    update_post_meta($transaction_id, 'price', $price);
    update_post_meta($transaction_id, 'payment_status', 'pending');
    
    // Get class details
    $class = get_post($class_id);
    
    // Get PayMaya settings
    $secret_key = get_option('paymaya_secret_key');
    $public_key = get_option('paymaya_public_key');
    $mode = get_option('paymaya_mode', 'sandbox');
    $success_url = get_option('paymaya_success_url', home_url('/thank-you'));
    $failure_url = get_option('paymaya_failure_url', home_url('/payment-failed'));
    $cancel_url = get_option('paymaya_cancel_url', home_url('/payment-cancelled'));
    
    if (empty($secret_key)) {
        wp_send_json_error(array('message' => 'PayMaya is not configured properly'));
    }
    
    // Determine API URL based on mode
    $api_url = $mode === 'live' 
        ? 'https://pg.maya.ph/checkout/v1/checkouts'
        : 'https://pg-sandbox.paymaya.com/checkout/v1/checkouts';
    
    // Prepare PayMaya checkout data
    $checkout_data = array(
        'totalAmount' => array(
            'value' => $price,
            'currency' => 'PHP'
        ),
        'buyer' => array(
            'firstName' => $first_name,
            'lastName' => $last_name,
            'contact' => array(
                'email' => $email
            )
        ),
        'items' => array(
            array(
                'name' => $class->post_title . ' (' . $duration . ' month' . ($duration > 1 ? 's' : '') . ')',
                'quantity' => 1,
                'amount' => array(
                    'value' => $price
                ),
                'totalAmount' => array(
                    'value' => $price
                )
            )
        ),
        'redirectUrl' => array(
            'success' => add_query_arg(array('ref' => $ref_number, 'key' => $key), $success_url),
            'failure' => add_query_arg('txn', $transaction_id, $failure_url),
            'cancel' => add_query_arg('txn', $transaction_id, $cancel_url)
        ),
        'requestReferenceNumber' => $ref_number,
        'metadata' => array(
            'transaction_id' => $transaction_id,
            'class_id' => $class_id,
            'user_id' => $user_id,
            'duration' => $duration
        )
    );
    
    // Make API request to PayMaya
    $response = wp_remote_post($api_url, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Basic ' . base64_encode($public_key . ':')
        ),
        'body' => json_encode($checkout_data),
        'timeout' => 30
    ));
    
    if (is_wp_error($response)) {
        wp_send_json_error(array('message' => 'Payment gateway error: ' . $response->get_error_message()));
    }
    
    $status_code = wp_remote_retrieve_response_code($response);
    $body = json_decode(wp_remote_retrieve_body($response), true);
    
    if ($status_code === 200 || $status_code === 201) {
        // Save PayMaya checkout ID
        update_post_meta($transaction_id, 'paymaya_checkout_id', $body['checkoutId']);
        update_post_meta($transaction_id, 'paymaya_redirect_url', $body['redirectUrl']);
        update_post_meta($transaction_id, 'paymaya_receipt', $body['receiptNumber']);
        delete_user_meta($user_id, 'free_trial_' . $class_id);
        // Update transaction post status to publish
        wp_update_post(array(
            'ID' => $transaction_id,
            'post_status' => 'publish'
        ));
        
        
        
        // Send the receipt via email
        $to = $email;
        $subject = 'Transaction Receipt';
        $account_created_message = !$is_logged_in ? "<p><strong>Account Created:</strong> Your account has been created successfully. You are now logged in.</p>" : "";
        $message = "
        <html>
        <head>
        <title>Transaction Receipt</title>
        </head>
        <body>
        <h2>Transaction Receipt</h2>
        <p><strong>Transaction Reference:</strong> $ref_number</p>
        <p><strong>Class:</strong> " . $class->post_title . "</p>
        <p><strong>Duration:</strong> $duration months</p>
        <p><strong>Name:</strong> $name</p>
        <p><strong>Email:</strong> $email</p>
        $account_created_message
        <p><strong>Payment Method:</strong> PayMaya</p>
        <p><strong>Price:</strong> ₱" . number_format($price, 2) . "</p>
        <p>Your enrollment is successful, but payment needs to be verified. Don't worry, you are already enrolled in the class. If your payment is not verified, your class key will be revoked, and you will need to repurchase the class.</p>
        <a href='" . home_url() . "' class='button' style='color: #fff; text-decoration: none; border-radius: 5px;'>Start Looksfam</a>
        </body>
        </html>";
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        
        // Attempt to send the email
        $email_sent = wp_mail($to, $subject, $message, $headers);
        
        wp_send_json_success(array(
            'checkout_url' => $body['redirectUrl'],
            'transaction_id' => $transaction_id,
            'email_sent' => $email_sent
        ));
    } else {
        wp_send_json_error(array(
            'message' => 'Payment initialization failed',
            'details' => isset($body['message']) ? $body['message'] : 'Unknown error'
        ));
    }
}

// Register REST API endpoint for webhook
add_action('rest_api_init', function() {
    register_rest_route('paymaya/v1', '/webhook', array(
        'methods' => 'POST',
        'callback' => 'paymaya_webhook_handler',
        'permission_callback' => '__return_true'
    ));
});

function paymaya_webhook_handler($request) {
    // Get webhook data
    $body = $request->get_body();
    $data = json_decode($body, true);
    
    // Log webhook for debugging
    error_log('PayMaya Webhook Received: ' . print_r($data, true));
    
    if (!$data) {
        return new WP_REST_Response(array('error' => 'Invalid JSON'), 400);
    }
    
    // Verify webhook signature (if configured)
    $webhook_secret = get_option('paymaya_webhook_secret');
    if (!empty($webhook_secret)) {
        $signature = $request->get_header('paymaya-signature');
        if ($signature) {
            $computed_signature = hash_hmac('sha256', $body, $webhook_secret);
            if ($signature !== $computed_signature) {
                error_log('PayMaya Webhook: Invalid signature');
                return new WP_REST_Response(array('error' => 'Invalid signature'), 401);
            }
        }
    }
    
    // Process webhook based on event type
    $event_type = isset($data['name']) ? $data['name'] : '';
    
    switch ($event_type) {
        case 'PAYMENT_SUCCESS':
        case 'CHECKOUT_SUCCESS':
            handle_payment_success($data);
            break;
            
        case 'PAYMENT_FAILED':
        case 'CHECKOUT_FAILURE':
            handle_payment_failure($data);
            break;
            
        case 'PAYMENT_EXPIRED':
            handle_payment_expired($data);
            break;
    }
    
    return new WP_REST_Response(array('status' => 'received'), 200);
}

function handle_payment_success($data) {
    // Extract transaction reference
    $ref_number = isset($data['requestReferenceNumber']) ? $data['requestReferenceNumber'] : '';
    
    if (empty($ref_number)) {
        error_log('PayMaya Webhook: No reference number found');
        return;
    }
    
    // Find transaction by reference number
    $transactions = get_posts(array(
        'post_type' => 'transaction',
        'title' => $ref_number,
        'posts_per_page' => 1
    ));
    
    if (empty($transactions)) {
        error_log('PayMaya Webhook: Transaction not found for ref: ' . $ref_number);
        return;
    }
    
    $transaction = $transactions[0];
    $transaction_id = $transaction->ID;
    
    // Check if already processed
    $payment_status = get_post_meta($transaction_id, 'payment_status', true);
    if ($payment_status === 'completed') {
        error_log('PayMaya Webhook: Transaction already processed');
        return;
    }
    
    // Get transaction details
    $class_id = get_post_meta($transaction_id, 'class_id', true);
    $duration = get_post_meta($transaction_id, 'duration', true);
    $user_id = get_post_meta($transaction_id, 'user_id', true);
    $email = get_post_meta($transaction_id, 'email', true);
    $name = get_post_meta($transaction_id, 'name', true);
    $price = get_post_meta($transaction_id, 'price', true);
    
    // Update transaction status
    wp_update_post(array(
        'ID' => $transaction_id,
        'post_status' => 'publish'
    ));
    
    update_post_meta($transaction_id, 'payment_status', 'completed');
    update_post_meta($transaction_id, 'payment_date', current_time('mysql'));
    update_post_meta($transaction_id, 'paymaya_payment_id', isset($data['paymentId']) ? $data['paymentId'] : '');
    
    // Generate and save class key
    $class_keys = get_term_meta(305, 'class_keys', true) ?: array();
    $used_key = generate_unique_key($class_keys);
    
    $class_keys[$used_key] = array(
        'class' => $class_id,
        'status' => 'Used',
        'user' => $user_id,
        'used_timestamp' => current_time('mysql'),
        'duration' => $duration
    );
    update_term_meta(305, 'class_keys', $class_keys);
    update_post_meta($transaction_id, 'used_key', $used_key);
    
    // Enroll user in class
    $enrolled_students = get_post_meta($class_id, 'enrolled_students', true) ?: array();
    if (!in_array($user_id, $enrolled_students)) {
        $enrolled_students[] = $user_id;
        update_post_meta($class_id, 'enrolled_students', $enrolled_students);
    }
    
    $classes_enrolled = get_user_meta($user_id, 'classes_enrolled', true) ?: array();
    if (!in_array($class_id, $classes_enrolled)) {
        $classes_enrolled[] = $class_id;
        update_user_meta($user_id, 'classes_enrolled', $classes_enrolled);
    }
    
    // Send confirmation email
    $class = get_post($class_id);
    $to = $email;
    $subject = 'Payment Successful - Class Enrollment Confirmed';
    $message = "
    <html>
    <head>
    <title>Payment Successful</title>
    </head>
    <body>
    <h2>Payment Successful!</h2>
    <p>Thank you for your payment. Your enrollment has been confirmed.</p>
    <p><strong>Transaction Reference:</strong> $ref_number</p>
    <p><strong>Class:</strong> " . esc_html($class->post_title) . "</p>
    <p><strong>Duration:</strong> $duration month" . ($duration > 1 ? 's' : '') . "</p>
    <p><strong>Amount Paid:</strong> ₱" . number_format($price, 2) . "</p>
    <p><strong>Name:</strong> $name</p>
    <p><strong>Email:</strong> $email</p>
    <p><strong>Class Key:</strong> $used_key</p>
    <p>You can now access your class. Start learning today!</p>
    <a href='" . home_url() . "' style='display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin-top: 15px;'>Go to Dashboard</a>
    </body>
    </html>";
    
    $headers = array('Content-Type: text/html; charset=UTF-8');
    wp_mail($to, $subject, $message, $headers);
    
    error_log('PayMaya Webhook: Payment processed successfully for ' . $ref_number);
}

function handle_payment_failure($data) {
    $ref_number = isset($data['requestReferenceNumber']) ? $data['requestReferenceNumber'] : '';
    
    if (empty($ref_number)) {
        return;
    }
    
    $transactions = get_posts(array(
        'post_type' => 'transaction',
        'title' => $ref_number,
        'posts_per_page' => 1
    ));
    
    if (!empty($transactions)) {
        $transaction_id = $transactions[0]->ID;
        update_post_meta($transaction_id, 'payment_status', 'failed');
        update_post_meta($transaction_id, 'payment_failure_reason', isset($data['message']) ? $data['message'] : 'Payment failed');
        
        wp_update_post(array(
            'ID' => $transaction_id,
            'post_status' => 'failed'
        ));
        
        error_log('PayMaya Webhook: Payment failed for ' . $ref_number);
    }
}

function handle_payment_expired($data) {
    $ref_number = isset($data['requestReferenceNumber']) ? $data['requestReferenceNumber'] : '';
    
    if (empty($ref_number)) {
        return;
    }
    
    $transactions = get_posts(array(
        'post_type' => 'transaction',
        'title' => $ref_number,
        'posts_per_page' => 1
    ));
    
    if (!empty($transactions)) {
        $transaction_id = $transactions[0]->ID;
        update_post_meta($transaction_id, 'payment_status', 'expired');
        
        wp_update_post(array(
            'ID' => $transaction_id,
            'post_status' => 'trash'
        ));
        
        error_log('PayMaya Webhook: Payment expired for ' . $ref_number);
    }
}

// Handle free trial checkout (keep original functionality)
function handle_class_checkout2() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['class_id']) && isset($_POST['free_trial']) && $_POST['free_trial'] == '1') {
        $errors = array();
        
        $class_id = intval($_POST['class_id']);
        $user_id = 0;
        $is_logged_in = isset($_POST['is_logged_in']) && $_POST['is_logged_in'] == '1';
        
        // Handle user creation for free trial
        if (!$is_logged_in) {
            $username = sanitize_user($_POST['username']);
            $password = $_POST['password'];
            $email = sanitize_email($_POST['email']);
            
            if (username_exists($username) || email_exists($email)) {
                wp_redirect(add_query_arg('error', 'Username or email already exists', wp_get_referer()));
                exit;
            }
            
            $user_id = wp_create_user($username, $password, $email);
            
            if (!is_wp_error($user_id)) {
                $first_name = sanitize_text_field($_POST['first_name']);
                $last_name = sanitize_text_field($_POST['last_name']);
                
                wp_update_user(array(
                    'ID' => $user_id,
                    'first_name' => $first_name,
                    'last_name' => $last_name,
                    'display_name' => trim($first_name . ' ' . $last_name)
                ));
                
                wp_set_current_user($user_id);
                wp_set_auth_cookie($user_id);
            } else {
                wp_redirect(add_query_arg('error', 'Failed to create account', wp_get_referer()));
                exit;
            }
        } else {
            $user_id = get_current_user_id();
        }
        
        // Mark free trial as used
        update_user_meta($user_id, 'free_trial_' . $class_id, true);
        
        $duration = 0.1; // 3 days
        $ref_number = 'TRIAL-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));
        
        // Create transaction for free trial
        $transaction_id = wp_insert_post(array(
            'post_title' => $ref_number,
            'post_type' => 'transaction',
            'post_status' => 'publish',
            'post_author' => $user_id,
        ));
        
        if ($transaction_id) {
            update_post_meta($transaction_id, 'class_id', $class_id);
            update_post_meta($transaction_id, 'duration', $duration);
            update_post_meta($transaction_id, 'user_id', $user_id);
            update_post_meta($transaction_id, 'email', $_POST['email']);
            update_post_meta($transaction_id, 'name', trim($_POST['first_name'] . ' ' . $_POST['last_name']));
            update_post_meta($transaction_id, 'payment_method', 'free_trial');
            update_post_meta($transaction_id, 'price', 0);
            update_post_meta($transaction_id, 'payment_status', 'completed');
            
            // Generate key and enroll
            $class_keys = get_term_meta(305, 'class_keys', true) ?: array();
            $used_key = generate_unique_key($class_keys);
            
            $class_keys[$used_key] = array(
                'class' => $class_id,
                'status' => 'Used',
                'user' => $user_id,
                'used_timestamp' => current_time('mysql'),
                'duration' => $duration
            );
            update_term_meta(305, 'class_keys', $class_keys);
            update_post_meta($transaction_id, 'used_key', $used_key);
            
            // Enroll user
            $enrolled_students = get_post_meta($class_id, 'enrolled_students', true) ?: array();
            if (!in_array($user_id, $enrolled_students)) {
                $enrolled_students[] = $user_id;
                update_post_meta($class_id, 'enrolled_students', $enrolled_students);
            }
            
            $classes_enrolled = get_user_meta($user_id, 'classes_enrolled', true) ?: array();
            if (!in_array($class_id, $classes_enrolled)) {
                $classes_enrolled[] = $class_id;
                update_user_meta($user_id, 'classes_enrolled', $classes_enrolled);
            }
            
            wp_redirect(home_url('/thank-you?ref=' . $ref_number));
            exit;
        }
    }
}

// Add to functions.php
add_action('admin_menu', function() {
    add_submenu_page(
        'paymaya-settings',
        'Manual Verification',
        'Manual Verify',
        'manage_options',
        'paymaya-manual-verify',
        'paymaya_manual_verify_page'
    );
});

function paymaya_manual_verify_page() {
    if (isset($_POST['verify_payment'])) {
        $txn_id = intval($_POST['transaction_id']);
        
        // Get PayMaya checkout ID
        $checkout_id = get_post_meta($txn_id, 'paymaya_checkout_id', true);
        
        if ($checkout_id) {
            // Check payment status from PayMaya
            $secret_key = get_option('paymaya_secret_key');
            $mode = get_option('paymaya_mode', 'sandbox');
            
            $api_url = $mode === 'live' 
                ? "https://pg.maya.ph/checkout/v1/checkouts/$checkout_id"
                : "https://pg-sandbox.paymaya.com/checkout/v1/checkouts/$checkout_id";
            
            $response = wp_remote_get($api_url, array(
                'headers' => array(
                    'Authorization' => 'Basic ' . base64_encode($secret_key . ':')
                )
            ));
            
            if (!is_wp_error($response)) {
                $body = json_decode(wp_remote_retrieve_body($response), true);
                
                if (isset($body['status']) && $body['status'] === 'COMPLETED') {
                    // Manually trigger payment success
                    handle_payment_success($body);
                    echo '<div class="notice notice-success"><p>Payment verified and processed!</p></div>';
                } else {
                    echo '<div class="notice notice-error"><p>Payment not completed. Status: ' . $body['status'] . '</p></div>';
                }
            }
        }
    }
    ?>
    <div class="wrap">
        <h1>Manual Payment Verification</h1>
        <form method="post">
            <table class="form-table">
                <tr>
                    <th>Transaction ID:</th>
                    <td>
                        <input type="number" name="transaction_id" required />
                        <p class="description">Enter the transaction post ID</p>
                    </td>
                </tr>
            </table>
            <button type="submit" name="verify_payment" class="button button-primary">Verify Payment</button>
        </form>
    </div>
    <?php
}
?>