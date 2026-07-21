<?php
function class_checkout_shortcode($atts) {
    $class_id = isset($_GET['class']) ? intval($_GET['class']) : 0;
    $is_trial_selected = isset($_GET['trial']); // Check if the trial is selected via GET

    if ($class_id === 0) {
        return 'No class specified.';
    }

    $current_user = wp_get_current_user();
    if (!$current_user->ID) {
        return 'Please log in to checkout.';
    }

    $class = get_post($class_id);
    if (!$class || $class->post_type !== 'class') {
        return 'Invalid class.';
    }

    $prices = get_post_meta($class_id, 'price', true);
    if (!$prices || !is_array($prices)) {
        return 'Class prices not set.';
    }

    $has_free_trial = get_user_meta($current_user->ID, 'free_trial_' . $class_id, true);

    ob_start();
    wp_enqueue_script('jquery');
    ?>
    <script>
        var ajaxurl = "/wp-admin/admin-ajax.php";
        jQuery(document).ready(function($) {
            // Set default payment method to online
            $('#selected-payment-method').val('online');
            
            function updateTotal() {
                var selectedButton = $('.duration-button.selected');
                var price = selectedButton.hasClass('free-trial') ? 0 : parseFloat(selectedButton.data('price'));
                $('#total-price').text(price.toFixed(2));
                $('#selected-price').val(price.toFixed(2)); // Set the selected price in the hidden input
            }

            $('.duration-button').on('click', function() {
                $('.duration-button').removeClass('selected');
                $(this).addClass('selected');
                $('#selected-duration').val($(this).data('duration'));

                if ($(this).hasClass('free-trial')) {
                    togglePaymentFields(false);
                    $('#free-trial').val($(this).data('trial'));
                } else {
                    togglePaymentFields(true);
                    $('#free-trial').val('0');
                }

                updateTotal();
            });

            function togglePaymentFields(show) {
                if (show) {
                    $('.payment-container').show();
                } else {
                    $('.payment-container').hide();
                }
            }

            // Handle checkout button click
            $('#checkout-btn').on('click', function(e) {
                e.preventDefault();
                
                if (!$('.duration-button.selected').length) {
                    alert('Please select a duration');
                    return;
                }

                // If free trial is selected, submit directly
                if ($('.duration-button.selected').hasClass('free-trial')) {
                    $('#class-checkout-form').submit();
                    return;
                }

                // Show payment modal for paid options
                $('#payment-modal').show();
            });

            // Handle modal close
            $('.close-modal, #payment-modal').on('click', function(e) {
                if (e.target === this) {
                    $('#payment-modal').hide();
                }
            });

            // Handle proceed payment button in modal
            $('#proceed-payment').on('click', function() {
                var referenceNumber = $('#modal-reference-number').val().trim();
                var accountName = $('#modal-account-name').val().trim();
                
                if (!referenceNumber) {
                    alert('Please enter a payment reference number');
                    return;
                }
                
                if (!accountName) {
                    alert('Please enter the account name');
                    return;
                }
                
                // Set the values to the form
                $('#payment_reference').val(referenceNumber);
                $('#account_name').val(accountName);
                
                // Close modal and submit form
                $('#payment-modal').hide();
                $('#class-checkout-form').submit();
            });
            // Add this to your existing JavaScript section
            $('#download-qr').on('click', function() {
                var qrImage = $('.qr-container img')[0];
                var canvas = document.createElement('canvas');
                var ctx = canvas.getContext('2d');
                
                canvas.width = qrImage.naturalWidth;
                canvas.height = qrImage.naturalHeight;
                
                ctx.drawImage(qrImage, 0, 0);
                
                var link = document.createElement('a');
                link.download = 'qr.png';
                link.href = canvas.toDataURL();
                link.click();
            });
            <?php if ($is_trial_selected): ?>
                $('.duration-button.free-trial').click(); // Automatically select the free trial if trial is set in GET
            <?php endif; ?>
        });
    </script>
    <style>
        /* Existing styles */
        .class-checkout-container {
            display: flex;
            justify-content: space-between;
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            font-family: Arial, sans-serif;
        }
        .checkout-left, .checkout-right {
            width: 48%;
            padding: 20px;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .duration-buttons {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            margin-bottom: 20px;
        }
        .duration-button {
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            cursor: pointer;
            text-align: center;
        }
        .duration-button.selected {
            background-color: var(--ast-global-color-0);
            color: white;
        }
        h2 {
            color: #333;
            border-bottom: 2px solid #ddd;
            padding-bottom: 10px;
            margin-bottom: 20px;
        }
        label {
            display: block;
            margin-bottom: 5px;
            color: #666;
        }
        input[type="text"], input[type="email"], textarea, select {
            width: 100%;
            padding: 10px;
            margin-bottom: 15px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        #total-amount {
            font-size: 18px;
            font-weight: bold;
            margin: 20px 0;
        }
        #class-key-input {
            margin-top: 20px;
            padding-top: 20px;
            border-top: 1px solid #ddd;
        }
        /* Class description styling */
        .class-description {
            background-color: #f9f9f9;
            padding: 15px;
            border-radius: 5px;
            margin: 15px 0;
            border-left: 4px solid var(--ast-global-color-0);
            font-size: 14px;
            line-height: 1.5;
            color: #555;
        }
        .class-description p {
            margin: 0 0 10px 0;
        }
        .class-description p:last-child {
            margin-bottom: 0;
        }
        @media (max-width: 768px) {
            .class-checkout-container {
                flex-direction: column;
            }
            .checkout-left, .checkout-right {
                width: 100%;
                margin-bottom: 20px;
            }
        }
        /* Highlight free trial button */
        .duration-button.free-trial.selected {
            background-color: #ffeb3b;
            color: black;
            font-size: 24px;
            font-weight: 700;
        }
        
        /* Modal styles */
        #payment-modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            max-width: 100%;
            height: 100%;
            background-color: rgba(0,0,0,0.5);
        }
        
        .modal-content {
            background-color: #fefefe;
            margin: 5% auto;
            padding: 20px;
            border-radius: 10px;
            width: 90%;
            max-width: 500px;
            position: relative;
        }
        
        .close-modal {
            color: #aaa;
            float: right;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            position: absolute;
            right: 15px;
            top: 10px;
        }
        
        .close-modal:hover {
            color: black;
        }
        
        .qr-container {
            text-align: center;
            margin: 20px 0;
        }
        
        .qr-container img {
            max-width: 100%;
            height: auto;
        }
        
        .modal-form-group {
            margin-bottom: 15px;
        }
        
        .modal-form-group label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        
        .modal-form-group input {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
            box-sizing: border-box;
        }
        
        #proceed-payment {
            background-color: var(--ast-global-color-0);
            color: white;
            padding: 12px 20px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
        }
        
        #proceed-payment:hover {
            opacity: 0.8;
        }
    </style>
    
    <div class="class-checkout-container">
        <div class="checkout-left">
            <h4>Billing Details</h4>
            <form id="class-checkout-form" method="post" enctype="multipart/form-data">
                <input type="hidden" id="selected-price" name="price" value="">
                <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                <input type="hidden" id="selected-duration" name="duration" value="">
                <input type="hidden" id="free-trial" name="free_trial" value="">
                <input type="hidden" id="selected-payment-method" name="payment_method" value="online">
                <input type="hidden" id="payment_reference" name="payment_reference" value="">
                <input type="hidden" id="account_name" name="account_name" value="">

                <label for="name">Name:</label>
                <input type="text" id="name" name="name" value="<?php echo esc_attr($current_user->first_name).' '.esc_attr($current_user->last_name); ?>" required>

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo esc_attr($current_user->user_email); ?>" required>

                <label for="address">Address:</label>
                <textarea id="address" name="address" required><?php echo esc_textarea(get_user_meta($current_user->ID, 'address', true)); ?></textarea>
            </form>
        </div>

        <div class="checkout-right">
            <h4>Order Summary</h4>
            <div id="order-details">
                <div><strong>Class:</strong></div>
                <h4><?php echo esc_html($class->post_title); ?></h4>
                
                <?php 
                // Display class description if present
                $class_content = trim($class->post_content);
                if (!empty($class_content)) : ?>
                    <div class="class-description">
                        <?php echo wp_kses_post(wpautop($class_content)); ?>
                    </div>
                <?php endif; ?>
                
                <div class="duration-buttons">
                    <?php foreach ($prices as $duration => $price) : ?>
                        <div class="duration-button" data-duration="<?php echo esc_attr($duration); ?>" data-price="<?php echo esc_attr($price); ?>">
                            <?php echo esc_html($duration . ' month(s) - ₱' . number_format($price, 2)); ?>
                        </div>
                    <?php endforeach; ?>
                </div>
                <?php if (!$has_free_trial) : ?>
                <div class="duration-buttons" style="grid-template-columns: unset;">
                        <div class="duration-button free-trial" data-duration="0.1" data-price="0" data-trial="1" style="font-size: 24px;font-weight: 700;">
                            3 DAY FREE TRIAL
                        </div>
                </div>
                <?php endif; ?>
            </div>
            <div id="total-amount">
                <p><strong>Total:</strong> ₱<span id="total-price">0.00</span></p>
            </div>

            <button id="checkout-btn" type="button">Checkout</button>
        </div>
    </div>
    
    <!-- Payment Modal -->
    <div id="payment-modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <h5 style="text-align: center; margin-bottom: 20px;color:#000;">Complete Your Payment</h5>
            
            <div class="qr-container">
                <p><strong style="color:#000;">Scan QR Code to Pay:</strong></p>
                <img src="https://www.looksfam.co/wp-content/uploads/2025/09/qr.jpg" alt="Payment QR Code" />
                <button id="download-qr" type="button" style="margin-top: 10px; padding: 8px 15px; background-color: #007cba; color: white; border: none; border-radius: 4px; cursor: pointer;">
                    Download QR Code
                </button>
            </div>
            
            <div class="modal-form-group">
                <label for="modal-reference-number">Payment Reference Number:</label>
                <input type="text" id="modal-reference-number" placeholder="Enter your payment reference number" required>
            </div>
            
            <div class="modal-form-group">
                <label for="modal-account-name">Account Name Used for Payment:</label>
                <input type="text" id="modal-account-name" placeholder="Enter the account name you used to pay" required>
            </div>
            
            <button id="proceed-payment">Complete Checkout</button>
        </div>
    </div>
    
    <?php
    return ob_get_clean();
}

add_shortcode('class_checkout', 'class_checkout_shortcode');


function handle_class_checkout() {
    if (!isset($_POST['confirm_payment']) && !isset($_POST['class_id'])) return;
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['class_id']) ) {
        $errors = array();

        // Validate inputs
        $class_id = intval($_POST['class_id']);
        if (!get_post($class_id) || get_post_type($class_id) !== 'class') {
            $errors[] = "Invalid class selected.";
        }

        $duration = floatval($_POST['duration']);
        if ($duration <= 0 && !isset($_POST['free_trial'])) {
            $errors[] = "Invalid duration selected.";
        }

        $user_id = get_current_user_id();
        if (!$user_id) {
            $errors[] = "You must be logged in to complete this transaction.";
        }

        $name = sanitize_text_field($_POST['name']);
        if (empty($name)) {
            $errors[] = "Name is required.";
        }

        $email = sanitize_text_field($_POST['email']);
        if (empty($email)) {
            $errors[] = "Email is required.";
        }

        $address = sanitize_textarea_field($_POST['address']);
        if (empty($address)) {
            $errors[] = "Address is required.";
        }
        
        $payment_method = 'online'; // Set default to online
        $payment_reference = sanitize_text_field($_POST['payment_reference']);
        $account_name = sanitize_text_field($_POST['account_name']);

        // Validate payment details for non-free trial
        if (!isset($_POST['free_trial']) || $_POST['free_trial'] != '1') {
            if (empty($payment_reference)) {
                $errors[] = "Payment reference number is required.";
            }
            if (empty($account_name)) {
                $errors[] = "Account name is required.";
            }
        }

        // If the user selects a free trial
        if (isset($_POST['free_trial']) && $_POST['free_trial'] == '1') {
            update_user_meta($user_id, 'free_trial_' . $class_id, true);
            $duration = 0.1; // 3 days
        } else {
            delete_user_meta($user_id, 'free_trial_' . $class_id);
        }

        $class_key = isset($_POST['class_key']) ? sanitize_text_field($_POST['class_key']) : '';
        $class_keys = get_term_meta(305, 'class_keys', true) ?: array();
        $is_valid_key = false;

        if ($class_key) {
            if (isset($class_keys[$class_key]) && 
                $class_keys[$class_key]['class'] == $class_id && 
                $class_keys[$class_key]['status'] == 'Unused' &&
                $class_keys[$class_key]['duration'] == $duration) {
                $is_valid_key = true;
            } else {
                $errors[] = "Invalid or already used class key.";
            }
        }

        // If there are errors, redirect back to the checkout page with error messages
        if (!empty($errors)) {
            $error_message = implode(' ', $errors);
            wp_redirect(add_query_arg('error', urlencode($error_message), wp_get_referer()));
            exit;
        }

        // Generate a unique reference number
        $ref_number = 'TXN-' . strtoupper(substr(md5(uniqid(mt_rand(), true)), 0, 8));

        // Create a new transaction post
        $transaction_id = wp_insert_post(array(
            'post_title'    => $ref_number,
            'post_type'     => 'transaction',
            'post_status'   => 'publish',
            'post_author'   => $user_id,
        ));

        if ($transaction_id) {
            // Add transaction details as post meta
            update_post_meta($transaction_id, 'class_id', $class_id);
            update_post_meta($transaction_id, 'duration', $duration);
            update_post_meta($transaction_id, 'user_id', $user_id);
            update_post_meta($transaction_id, 'email', $email);
            update_post_meta($transaction_id, 'name', $name);
            update_post_meta($transaction_id, 'address', $address);
            update_post_meta($transaction_id, 'payment_method', $payment_method);
            update_post_meta($transaction_id, 'price', sanitize_text_field($_POST['price'])); 
            update_post_meta($transaction_id, 'payment_reference', $payment_reference);
            update_post_meta($transaction_id, 'account_name', $account_name);

            if (empty($errors)) {
                // Use the provided key or generate a new one
                $used_key = $is_valid_key ? $class_key : generate_unique_key($class_keys);
                $class_keys[$used_key] = array(
                    'class' => $class_id,
                    'status' => 'Used',
                    'user' => $user_id,
                    'used_timestamp' => current_time('mysql'),
                    'duration' => $duration
                );
                update_term_meta(305, 'class_keys', $class_keys);

                // Enroll user in the class
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

                update_post_meta($transaction_id, 'used_key', $used_key);
            }
            
            // Send the receipt via email
            $to = $email;
            $subject = 'Transaction Receipt';
            $message = "
            <html>
            <head>
            <title>Transaction Receipt</title>
            </head>
            <body>
            <h2>Transaction Receipt</h2>
            <p><strong>Transaction Reference:</strong> $ref_number</p>
            <p><strong>Class:</strong> " . get_the_title($class_id) . "</p>
            <p><strong>Duration:</strong> $duration months</p>
            <p><strong>Name:</strong> $name</p>
            <p><strong>Email:</strong> $email</p>
            <p><strong>Address:</strong> $address</p>
            <p><strong>Payment Method:</strong> $payment_method</p>
            <p><strong>Payment Reference:</strong> $payment_reference</p>
            <p><strong>Account Name:</strong> $account_name</p>
            <p><strong>Price:</strong> ₱{$_POST['price']}</p>
            <p>Your enrollment is successful, but payment needs to be verified. Don't worry, you are already enrolled in the class. If your payment is not verified, your class key will be revoked, and you will need to repurchase the class.</p>
            <a href='" . home_url() . "' class='button' style='color: #fff; text-decoration: none; border-radius: 5px;'>Start Looksfam</a>
            </body>
            </html>";
            
            $headers = array('Content-Type: text/html; charset=UTF-8');
            
            // Attempt to send the email and check for success
            $email_sent = wp_mail($to, $subject, $message, $headers);
            
        } else {
            $errors[] = "Failed to create transaction record.";
        }

        // After processing, redirect with success or error message
        if (empty($errors)) {
            if ($email_sent) {
                // Email was sent successfully
                wp_redirect(home_url('/thank-you?ref=' . $ref_number));
            } else {
                // Email failed to send
                $errors[] = "Failed to send message";
            }
        } else {
            $error_message = implode(' ', $errors);
            wp_redirect(add_query_arg('error', urlencode($error_message), wp_get_referer()));
        }
        exit;
    }
}
add_action('init', 'handle_class_checkout');

function topscoredisplay($class_id, $user_id, $mode = 'looksfam') {
    $activity_results = get_post_meta($class_id, 'activity_results', true);
    if (empty($activity_results)) return 0;

    $exam_topic_id   = get_post_meta($class_id, 'exam_topic', true);
    $exam_subject_id = get_post_meta($class_id, 'exam_subject', true);
    $parent_term_id  = $exam_topic_id ?: $exam_subject_id;

    $relevant_categories = $parent_term_id ? [$parent_term_id] : [];
    if ($parent_term_id) {
        $subcategories = get_terms([
            'taxonomy'   => 'question_category',
            'parent'     => $parent_term_id,
            'hide_empty' => false,
        ]);
        $relevant_categories = array_merge($relevant_categories, wp_list_pluck($subcategories, 'term_id'));
    }

    $user_stats = array_reduce($activity_results, function($acc, $result) use ($user_id, $relevant_categories) {
        if ($result['user_id'] == $user_id && in_array($result['question_category'], $relevant_categories)) {
            $acc['q_sessions']++;
            $acc['total_algo_look'] += questionstatlooksfam($result['question_id'], $user_id);
            if ($result['is_correct']) $acc['correct_answers']++;
            $acc['sessions'][$result['session_id']] = true; // unique sessions
        }
        return $acc;
    }, ['q_sessions' => 0, 'total_algo_look' => 0, 'correct_answers' => 0, 'sessions' => []]);

    // Default "looksfam"
    if ($mode === 'looksfam') {
        return ($user_stats['total_algo_look'] > 0 && $user_stats['correct_answers'] > 0) 
            ? round(($user_stats['total_algo_look'] / ($user_stats['q_sessions'] * 100)) * 100, 2) 
            : 0;
    }

    // "overall" mode → % correct / unique sessions
    if ($mode === 'overall') {
        $unique_sessions = count($user_stats['sessions']);
        return ($unique_sessions > 0) 
            ? round(($user_stats['correct_answers'] / $user_stats['q_sessions']) * 100, 2) 
            : 0;
    }

    return 0;
}

function questionstatlooksfam($question_ids, $user_id) {
    $question_post = get_post_meta($question_ids, 'question_results', true);
    
    $userCorrectCount = 0;
    $userSessionCount = 0;
    $userTotalQuestions = 0;
    $otherUsersCorrectCount = 0;
    $otherUsersSessionCount = 0;
    $otherUsersTotalQuestions = 0;
    $latestSessionId = '';
    $currentTime = current_time('timestamp');
    $firstAnswerTime = $currentTime;
    $lastAnswerTime = 0;
    $latestQuestionTime = 0;

    foreach ($question_post as $result) {
        $time = strtotime($result['timestamp']);
        $daysDifference = floor(($currentTime - $time) / (60 * 60 * 24));
        
        // Update latest question time
        $latestQuestionTime = max($latestQuestionTime, $time);
        
        // Apply time-based weighting
        /*if ($daysDifference <= 1) {
            $weightFactor = 1;
        } elseif ($daysDifference <= 3) {
            $weightFactor = 0.8;
        } elseif ($daysDifference <= 10) {
            $weightFactor = 0.5;
        } elseif ($daysDifference <= 20) {
            $weightFactor = 0.2;
        } else {
            $weightFactor = 0.1;
        }*/
        
        //$weightedCorrectCount = $result['is_correct'] * $weightFactor;
        $weightedCorrectCount = $result['is_correct'] * 1;
        
        if ($result['user_id'] == $user_id) {
            $userTotalQuestions++;
            $userCorrectCount += $weightedCorrectCount;
            
            $sessionId = $result['session_id'];
            if ($sessionId !== $latestSessionId) {
                $latestSessionId = $sessionId;
                $userSessionCount++;
            }
            
            $firstAnswerTime = min($firstAnswerTime, $time);
            $lastAnswerTime = max($lastAnswerTime, $time);
        } else {
            $otherUsersTotalQuestions++;
            $otherUsersCorrectCount += $weightedCorrectCount;
            
            $sessionId = $result['session_id'];
            if ($sessionId !== $latestSessionId) {
                $latestSessionId = $sessionId;
                $otherUsersSessionCount++;
            }
        }
    }
    
    // Calculate average metrics
    $avgOtherUsersCorrectRate = $otherUsersTotalQuestions > 0 ? $otherUsersCorrectCount / $otherUsersTotalQuestions : 0;
    $avgOtherUsersSessionCount = $otherUsersTotalQuestions > 0 ? $otherUsersSessionCount / $otherUsersTotalQuestions : 1;
    
    // Calculate user's performance relative to others
    $userCorrectRate = $userTotalQuestions > 0 ? $userCorrectCount / $userTotalQuestions : 0;
    $relativeCorrectRate = $avgOtherUsersCorrectRate > 0 ? $userCorrectRate / $avgOtherUsersCorrectRate : 1;
    
    // Calculate session efficiency (higher correct count with lower session count)
    $sessionEfficiency = $userSessionCount > 0 ? $userCorrectCount / $userSessionCount : 0;
    $avgOtherSessionEfficiency = $avgOtherUsersSessionCount > 0 ? $avgOtherUsersCorrectRate / $avgOtherUsersSessionCount : 0;
    $relativeSessionEfficiency = $avgOtherSessionEfficiency > 0 ? $sessionEfficiency / $avgOtherSessionEfficiency : 1;
    
    // Calculate retention factor (span of answering)
    $retentionSpan = max(1, ($lastAnswerTime - $firstAnswerTime) / (60 * 60 * 24)); // in days
    $retentionFactor = min(1, log($retentionSpan + 1) / log(31)); // normalized to 30 days
    
    // Calculate decay factor based on time since last question
    $timeSinceLastQuestion = max(0, ($currentTime - $latestQuestionTime) / (60 * 60 * 24)); // in days
    $decayFactor = exp(-0.1 * $timeSinceLastQuestion); // Exponential decay with rate 0.1
    
    // Calculate final percentage score
    $baseScore = $relativeCorrectRate * $relativeSessionEfficiency * 100;
    $percentageScore = $baseScore * (0.7 + 0.3 * $retentionFactor); // Retention factor contributes up to 30%
    
    // Apply decay factor
    $percentageScore *= $decayFactor;
    
    // Adjust score based on session count comparison
    if ($userSessionCount < $avgOtherUsersSessionCount) {
        $percentageScore *= 1.2; // Boost score if user has fewer sessions than average
    } else {
        $percentageScore *= 0.8; // Reduce score if user has more sessions than average
    }
    
    // Clamp the score to be between 0 and 100
    $percentageScore = min(max($percentageScore, 0), 100);
    
    return round($percentageScore, 2);
}

function questionoveralllooksfam($question_ids, $user_id) {
    $question_post = get_post_meta($question_ids, 'question_results', true);
    
    $displaylook_total = 0;
    $unique_sessions = array();
    
    // Check if question_post is array to avoid errors
    if (!is_array($question_post)) {
        return 0;
    }
    
    foreach ($question_post as $result) {
        // Create a unique key combining user_id and session_id
        $session_key = $result['user_id'] . '_' . $result['session_id'];
        
        // Only process if this is a unique combination
        if (!in_array($session_key, $unique_sessions)) {
            $displaylook = questionstatlooksfam($question_ids, $result['user_id']);
            $displaylook_total += $displaylook;
            
            // Add this combination to our tracking array
            $unique_sessions[] = $session_key;
        }
    }
    
    // Calculate percentage based on unique sessions count
    $unique_session_count = count($unique_sessions);
    $percentageScore = $unique_session_count > 0 ? $displaylook_total / $unique_session_count : 0;
    
    // Clamp the score to be between 0 and 100
    $percentageScore = min(max($percentageScore, 0), 100);
    
    return $percentageScore;
}
function get_questions($category_id, $limit = 10, $batch_size = 500) {
    // Get all subcategories
    $subcategories = get_term_children($category_id, 'question_category');

    // Add the main category to the list
    $all_categories = array_merge([$category_id], $subcategories);

    $paged = 1;
    $found_posts = true;

    $questions_0 = array();
    $questions_1_50 = array();
    $questions_50_plus = array();

    // Batch fetch questions
    while ($found_posts) {
        $args = array(
            'post_type'      => 'question',
            'posts_per_page' => $batch_size,
            'paged'          => $paged,
            'orderby'        => 'rand',
            'tax_query'      => array(
                array(
                    'taxonomy'         => 'question_category',
                    'field'            => 'term_id',
                    'terms'            => $all_categories,
                    'include_children' => true,
                ),
            ),
            'fields' => 'ids', // Only get IDs for speed
        );

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            foreach ($query->posts as $question_id) {
                $user_id = get_current_user_id();
                $displaylook = questionstatlooksfam($question_id, $user_id);

                $question = array(
                    'ID'           => $question_id,
                    'post_title'   => get_the_title($question_id),
                    'post_content' => get_post_field('post_content', $question_id),
                    'displaylook'  => $displaylook
                );

                if ($displaylook == 0) {
                    $questions_0[] = $question;
                } elseif ($displaylook >= 1 && $displaylook <= 50) {
                    $questions_1_50[] = $question;
                } else {
                    $questions_50_plus[] = $question;
                }

                // Early break if we already have enough
                if (
                    count($questions_0) + count($questions_1_50) + count($questions_50_plus) >= ($limit * 3)
                ) {
                    $found_posts = false;
                    break;
                }
            }
            $paged++;
        } else {
            $found_posts = false;
        }
        wp_reset_postdata();
    }

    // Calculate the number of questions to select from each group
    $limit_0      = min(ceil($limit * 0.7), count($questions_0));
    $limit_1_50   = min(ceil($limit * 0.3), count($questions_1_50));
    $limit_50_plus = $limit - $limit_0 - $limit_1_50;

    $selected_questions = array_merge(
        array_slice($questions_0, 0, $limit_0),
        array_slice($questions_1_50, 0, $limit_1_50),
        array_slice($questions_50_plus, 0, $limit_50_plus)
    );

    // If we don't have enough, backfill
    while (count($selected_questions) < $limit) {
        if (!empty($questions_0) && count($selected_questions) < $limit) {
            $selected_questions[] = array_shift($questions_0);
        } elseif (!empty($questions_1_50) && count($selected_questions) < $limit) {
            $selected_questions[] = array_shift($questions_1_50);
        } elseif (!empty($questions_50_plus) && count($selected_questions) < $limit) {
            $selected_questions[] = array_shift($questions_50_plus);
        } else {
            break;
        }
    }

    // Sort the final list by displaylook
    usort($selected_questions, function ($a, $b) {
        return $a['displaylook'] - $b['displaylook'];
    });

    return array_slice($selected_questions, 0, $limit);
}


function display_activity_statistics_ui($cat, $user_id, $class_id, $type) {
    ob_start();
    echo getClassCategoryStatus($class_id);
    hover_looks();
    //echo get_styles();
    ?>
    <style>
        .stat-con {
            width:unset!important;
        }
        .button-container-menu {
            margin: 5px 0px;
            display: flex;
            gap: 10px;
            flex-wrap: wrap;
        }
        
        .custom-transparent-button {
            height:70px;
            border: 2px solid #58c2f7;
            background: transparent;
            color: #58c2f7;
            border-radius: 10px;
            flex: 1;
            cursor: pointer;
            transition: background 0.3s ease;
            padding: 10px;
            text-align: center;
        }
        
        .custom-button {
            height:70px;
            border-radius: 10px;
            flex: 1;
            cursor: pointer;
            transition: background 0.3s ease;
            padding: 10px;
            text-align: center;
        }
        
        .custom-transparent-button:hover {
            background: #ffffff;
        }
        
        .custom-transparent-button:disabled {
            opacity: 0.5;
            cursor: not-allowed;
            background-color: #e9ecef;
        }
        .go-back-button {
            margin-top: 20px;
            margin-bottom: 20px; 
            height: 50px;
        }
         .stats-line {
            margin-top: 5px; 
            display: flex; 
            align-items: center;    
            border-bottom: 1px solid rgba(255, 255, 255, 0.1);
            padding: 12px 8px;
            transition: background 0.2s ease;
            border-radius: 6px;
        }
        
        .stats-line:hover {
            background: rgba(88, 194, 247, 0.1);
        }
        
        .stat-con {
            width: 33.33%;
            font-size: 14px;
            line-height: 1.4;
        }
        
        .stats-header-line {
            background: rgba(88, 194, 247, 0.2);
            font-weight: bold;
            color: #58c2f7;
            border-bottom: 2px solid #58c2f7;
        }
        
        .stats-header-line:hover {
            background: rgba(88, 194, 247, 0.2);
        }
        
        /* Mobile responsive */
        @media (max-width: 768px) {
            .stats-line {
                flex-direction: column !important;
                text-align: center !important;
                padding: 15px 10px;
                gap: 5px;
            }
            .stat-con {
                width: 100% !important;
                text-align: center !important;
                margin: 2px 0;
                font-size: 13px;
            }
            .stats-header-line .stat-con {
                font-size: 14px;
                font-weight: bold;
            }
        }
        
        /* Statistics sections styling */
        .stats-section {
            margin-top: 25px;
            margin-bottom: 25px;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 12px;
            padding: 20px;
            box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        }
        
        .stats-header {
            margin-bottom: 15px;
            color: #58c2f7;
            font-size: 18px;
            font-weight: 600;
            text-align: center;
            border-bottom: 2px solid #58c2f7;
            padding-bottom: 10px;
        }
        
        .stats-divider {
            margin: 20px 0;
            border-top: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .stats-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 25px;
            margin-bottom: 25px;
        }
        
        .stat-forbid{
            z-index: 999;
            position: absolute!important;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            text-align: center;
            width: 90%;
            font-weight: bold;
            background: rgba(0,0,0,0.8);
            padding: 15px;
            border-radius: 8px;
            color: white;
            font-size: 14px;
        }
        
        .stats-column {
            flex: 1;
            min-width: 0;
        }
        
        @media (max-width: 768px) {
            .stats-row {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .stats-column {
                width: 100%;
            }
            
            .stats-section {
                padding: 15px;
            }
            
            .stats-header {
                font-size: 16px;
            }
            
            .stat-forbid {
                font-size: 12px;
                padding: 12px;
            }
        }
        
        @media (max-width: 768px) {
            .button-container {
                flex-direction: column;
            }
            
            .custom-transparent-button {
                width: 100%;
            }
        }
        
        .dashboard-container {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 20px;
            margin: 20px 0;
        }
        
        .dashboard-card {
            background: white;
            border-radius: 15px;
            padding: 25px;
            box-shadow: 0 8px 25px rgba(0,0,0,0.15);
           color: #58c2f7;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            position: relative;
            overflow: hidden;
            display:flex;
            flex-direction: column;
            justify-content: space-between;
        }
        
        .dashboard-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 15px 35px rgba(0,0,0,0.2);
        }
        
        .dashboard-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 4px;
            background: linear-gradient(90deg, #58c2f7, #4facfe);
        }
        
        .card-header {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 15px;
        }
        
        .card-title {
            font-size: 16px;
            font-weight: 600;
            margin: 0;
            color: #58c2f7;
        }
        
        .card-value {
            font-size: 40px;
            font-weight: bold;
            color: #58c2f7;
            text-shadow: 0 2px 4px rgba(0,0,0,0.2);
        }
        
        .card-icon {
            font-size: 24px;
            opacity: 0.7;
        }
        
        .info-icon {
            cursor: pointer;
            margin-left: 8px;
            color: #58c2f7;
            font-weight: bold;
            background: rgba(88,194,247,0.2);
            transition: background 0.3s ease;
        }
        
        .info-icon:hover {
            background: rgba(88,194,247,0.4);
        }
        
        .description-box {
            position: absolute;
            background: rgba(0,0,0,0.9);
            color: white;
            padding: 10px;
            border-radius: 8px;
            font-size: 12px;
            width: 200px;
            z-index: 1000;
            border: 1px solid #58c2f7;
            box-shadow: 0 4px 15px rgba(0,0,0,0.3);
            
            left: 20%;
            margin-top: 5px;
        }
        
        /* Mobile Responsive */
        @media (max-width: 768px) {
            .dashboard-container {
                grid-template-columns: 1fr 1fr;
                gap: 15px;
                margin: 15px 0;
            }
            
            .dashboard-card {
                padding: 20px;
            }
            
            .card-value {
                font-size: 35px;
            }
            
            .description-box {
                width: 180px;
                font-size: 11px;
                left: 50%;
            }
            
        }
        
        @media (max-width: 480px) {
            .dashboard-card {
                padding: 15px;
            }
            
            .card-title {
                font-size: 14px;
            }
            
            .card-value {
                font-size: 32px;
            }
        }
        
        .bb {
             position: relative;
            width: 100%;
            background: #00000012;
            
        }
        
        .bb::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            z-index:999;
            backdrop-filter: blur(15px);
            -webkit-backdrop-filter: blur(15px);
            background: rgba(0, 0, 0, 0.2);
            pointer-events: none;
        }
        
        .bb > * {
            position: relative;
        }
        
        /* Improved table-like structure for stats */
        .stats-table {
            background: rgba(255, 255, 255, 0.03);
            border-radius: 8px;
            overflow: hidden;
            border: 1px solid rgba(255, 255, 255, 0.1);
        }
        
        .question-title {
            font-weight: 500;
            color: #ffffff;
            overflow: hidden;
            text-overflow: ellipsis;
            display: -webkit-box;
            -webkit-line-clamp: 2;
            -webkit-box-orient: vertical;
            line-height: 1.3;
        }
        
        .rating-value {
            font-weight: bold;
            color: #58c2f7;
            font-size: 16px;
        }
        
        @media (max-width: 480px) {
            .question-title {
                font-size: 12px;
                -webkit-line-clamp: 3;
            }
            
            .rating-value {
                font-size: 14px;
            }
        }
    </style>
    <div class="button-container" >
        <input type="button" value="←" class="go-back-button" onclick="window.location.href='<?php echo home_url('/profile?class_id=' . $class_id); ?>';" style="border-radius: 10px;">
      <?php echo dropdown_menu(); ?>
        </div>
         <div class="button-container" style="flex-direction: row;display:none;">
            <a href="<?php echo home_url('/profile?class_id=' . $class_id); ?>" 
               
               style="border-radius: 10px; display: inline-block;  text-align: center;">
                ← Go Back
            </a>
        </div>
    <div class="button-container" style="display: flex;">
        <div style="width: 100%;">
            <h4 style="margin-bottom:0px">Progress<br></h4>
            
            <?php
            
            $category_l = intval($_GET['cat']); // Get the category ID from the URL
          
            
            // Count the total number of questions in the selected category
            //$total_questions = count($questions_in_category); // Make sure this value is preserved
            // Initialize storage
            $questions_in_category = array();
            $per_page = 100;
            $paged = 1;
            
            do {
                $args = array(
                    'post_type'      => 'question',
                    'tax_query'      => array(
                        array(
                            'taxonomy'         => 'question_category',
                            'field'            => 'term_id',
                            'terms'            => $category_l,
                            'include_children' => true,
                        ),
                    ),
                    'posts_per_page' => $per_page,
                    'paged'          => $paged,
                );
            
                // Fetch questions in batches
                $batch = get_posts($args);
            
                // Merge batch into main array
                $questions_in_category = array_merge($questions_in_category, $batch);
            
                // Next page
                $paged++;
            
            } while (count($batch) === $per_page); // Continue until fewer than 100 are returned
            
            // Now $questions_in_category has all questions
            $total_questions = count($questions_in_category);
           
            
            // Calculate the LooksFam accuracy as the percentage of correct answers over the total unique questions.
            $user_questions = get_user_meta($user_id, 'questions', true);
            //$total_questions = 0;
            $correct_answers = 0; // Initialize correct answers counter
            $all_correct_answers = 0; // Initialize correct answers counter
            $sessions = 0; // Initialize sessions counter
            $q_sessions = 0; // 
            $displaylook = 0;
            $displayed_session = array(); // Track sessions
            
            if (!empty($user_questions) && is_array($user_questions)) {
                // Array to track displayed question IDs
                $displayed_questions = array();
            
                foreach ($user_questions as $question) {
                   
                    // Skip if the question has already been processed
                    if (in_array($question['question_id'], $displayed_questions)) {
                        continue;
                    }
            
                    // Add the question ID to the displayed list
                    $displayed_questions[] = $question['question_id'];
            
                    // Check if the question belongs to the selected category
                    if ($category_l != $question['question_category']) {
                        continue; // Skip if it doesn't belong to the desired category
                    }
            
                    // Check if the answer is correct
                    if ($question['is_correct']) {
                        $correct_answers++;
                    }
            
                    // Track unique sessions
                    if (!in_array($question['session_id'], $displayed_session)) {
                        $displayed_session[] = $question['session_id'];
                        $sessions++;
                    }
                  $q_sessions ++; 
                  
                $displaylook += questionstatlooksfam($question['question_id'], $user_id);
                }
            } else {
                echo '<p>No questions answered yet.</p>';
            }
            $all_correct_answers = $correct_answers;
            // Calculate LooksFam accuracy as a percentage of correct answers over total questions
            $looksfamacc = ($total_questions > 0) ? ($correct_answers / $total_questions) * 100 : 0;
            $looksfamacc_x_accuracy_display = ($sessions > 0) ? ($displaylook /( $q_sessions*100)) * 100 : 0;
            $looksfamacc_q = ($total_questions > 0) ? ($q_sessions / $total_questions) * 100 : 0;
            
            // Display the star rating and the accuracy percentage
            //displayStarRating($looksfamacc);
           // echo '<h2 style=" margin-bottom: 0px;">' . round($looksfamacc_q, 2) . '%</h2>';
             // Calculate required correct answers for each tier (20% increments)
            /*
            $tier2_required = ceil($total_questions * 0.1); // 20% of total questions
            $tier3_required = ceil($total_questions * 0.25); // 40% of total questions
            $tier4_required = ceil($total_questions * 0.6); // 60% of total questions
            $tier5_required = ceil($total_questions * 0.8); // 80% of total questions
            $tier6_required = ceil($total_questions * 1.0); // 100% of total questions*/
            
            $tier2_required = 10; // 20% of total questions
            $tier3_required = 35; // 40% of total questions
            $tier4_required = 70; // 60% of total questions
            $tier5_required = 100; // 80% of total questions
            $tier6_required = 150; // 100% of total questions
            
            $has_free_trial = get_user_meta($user_id, 'free_trial_' . $class_id, true);
             // Updated tier calculations
            $tier1 = ($sessions >= 5) ? '1' : null;
            $tier2 = (($sessions >= 10) && ($all_correct_answers >= $tier2_required)) ? '1' : null;
            $tier3 = (($sessions >= 20) && ($all_correct_answers >= $tier3_required)) ? '1' : null;
            $tier4 = (($sessions >= 50) && ($looksfamacc_x_accuracy_display >= 30) && ($all_correct_answers >= $tier4_required)) ? '1' : null;
            $tier5 = (($sessions >= 70) && ($looksfamacc_x_accuracy_display >= 50) && ($all_correct_answers >= $tier5_required)) ? '1' : null;
            //$tier6 = (($sessions >= 100) && ($looksfamacc_x_accuracy_display >= 50) && ($all_correct_answers >= $tier6_required)) ? '1' : null;
            $tier6 = $has_free_trial;
            

            ?>
            
        </div>
    </div>
       <div class="dashboard-container">
            <div id="info-description" class="description-box" style="display: none;">
                This rating is an AI algorithm percentage based on your familiarity and retention on this question.
            </div>
    <!-- Looksfam Accuracy Card -->
    <div class="dashboard-card" >
        <div class="card-header">
            <h3 class="card-title">
                Looksfam Accuracy
                <span class="info-icon" onclick="toggleDescription()">&#8505;</span>
                
            </h3>
        </div>
        <div class="card-value"><?php echo round($looksfamacc_x_accuracy_display, 2).'%'; ?></div>
    </div>

    <!-- Unique Correct Answers Card -->
    <div class="dashboard-card" >
        <div class="card-header">
            <h3 class="card-title">Overall Question Accuracy</h3>
        </div>
        <div class="card-value"><?php echo round($looksfamacc, 2).'%'; ?></div>
    </div>

    <!-- Total Questions Answered Card -->
    <div class="dashboard-card" >
        <div class="card-header">
            <h3 class="card-title">Total Questions Answered</h3>
        </div>
        <div class="card-value"><?php echo $q_sessions; ?></div>
    </div>

    <!-- Question on this topic Card (Hidden by default) -->
    <div class="dashboard-card" style=" display: none;">
        <div class="card-header">
            <h3 class="card-title">Question on this topic</h3>
        </div>
        <div class="card-value"><?php echo $total_questions; ?></div>
    </div>

    <!-- Exams Taken Card -->
    <div class="dashboard-card" >
        <div class="card-header">
            <h3 class="card-title">Exams Taken</h3>
        </div>
        <div class="card-value"><?php echo $sessions; ?></div>
    </div>

</div>

<script>
function toggleDescription() {
    const desc = document.getElementById('info-description');
    desc.style.display = desc.style.display === 'none' ? 'block' : 'none';
}
</script>
    <div class="button-container-menu">
        <button class=" custom-button " onclick="window.location.href='<?php echo home_url('/'.$type.'?exam_id=8080&cat=' . $category_l . '&take=1&class_id=' . $class_id); ?>';" >Start</button>
        <button  class="custom-transparent-button"<?php if(!isset($tier1)){echo "disabled";}?> onclick="window.location.href='<?php echo home_url('/solutions?id=' . $exam_id . '&cat=' . $category_l . '&class_id=' . $class_id); ?>';">
        Review Questions<br><?php 
        if(!isset($tier1)){ 
                $exam_needed = 5 - $sessions;
                $message_parts = [];
                                    
                if ($exam_needed > 0) {
                    $message_parts[] = "Answer $exam_needed more exam" . ($exam_needed > 1 ? "s" : "");
                }
                                        
                                       
                                    
                if (!empty($message_parts)) {
        echo implode(", ", $message_parts) . " to unlock!";
    }
        }
        ?></button>
    </div>
    <p style="margin-bottom:0px" >
        Unli Questions Mode Coming Soon.
    </p>
    
    <div style="margin-top:0px;">
        <h3 style="margin-bottom:0px;text-align:center">Statistics<br></h3>
        
        
        <!-- First row of tables -->
            <div class="stats-row">
                <!-- Top 3 Category Questions -->
                <div class="stats-column">
                    <div class="stats-section">
                        <h5 class="stats-header">Your Best 3 Questions</h5>
                        <div class="stats-line">
                            <div class="stat-con" style="flex-grow: 1; text-align: left;">Question</div>
                            <div class="stat-con" style="text-align: right;">Looksfam Rating</div>
                        </div>
                        
                            <?php
                            
                            if (!empty($user_questions) && is_array($user_questions)) {
                                $displayed_questions = array();
                                $questions_to_display = [];
                                
                                foreach ($user_questions as $question) {
                                    if (in_array($question['question_id'], $displayed_questions)) {
                                        continue;
                                    }
                                    if ($category_l != $question['question_category']) {
                                        continue;
                                    }
                                    $displayed_questions[] = $question['question_id'];
                                    $displaylook = questionstatlooksfam($question['question_id'], $user_id);
                                    $displayoveralllook = questionoveralllooksfam($question['question_id'], $user_id);
                                    
                                    $questions_to_display[] = [
                                        'question_id' => $question['question_id'],
                                        'title' => get_the_title($question['question_id']),
                                        'displaylook' => $displaylook,
                                        'displayoveralllook' => $displayoveralllook
                                    ];
                                }
                                
                                // Sort by displaylook in descending order
                                usort($questions_to_display, function($a, $b) {
                                    return $b['displaylook'] - $a['displaylook'];
                                });
                                
                                
                            }
                            if (isset($tier2)){
                                // Display top 3
                                $top_3 = array_slice($questions_to_display, 0, 3);
                                ?><div class="stats"><?php
                                foreach ($top_3 as $question) {
                                    ?>
                                    <div class="stats-line" data-id="<?php echo $question['question_id']?>">
                                        <div class="stat-con" style="flex-grow: 1; text-align: left;"><?php echo $question['title']; ?></div>
                                        <div class="stat-con" style="text-align: right;color: #58c2f7;"><?php echo $question['displaylook']; ?>%</div>
                                    </div>
                                    <?php
                                }
                                 ?></div><?php
                                
                            }else{
                                 ?>
                                 <div class="stats bb">
                                    <div class="stats-line">
                                        <div class="stat-con" style="flex-grow: 1; text-align: left;">This is a random question.</div>
                                        <div class="stat-con" style="text-align: right;color: #58c2f7;">0%</div>
                                    </div>
                                     <div class="stats-line" >
                                        <div class="stat-con" style="flex-grow: 1; text-align: left;">Unlocked it. You have to beat the goal!</div>
                                        <div class="stat-con" style="text-align: right;color: #58c2f7;">0%</div>
                                    </div>
                                     <div class="stats-line">
                                        <div class="stat-con" style="flex-grow: 1; text-align: left;">No Cheating! I repeat no cheating!</div>
                                        <div class="stat-con" style="text-align: right;color: #58c2f7;">0%</div>
                                    </div>
                                    <div class="stat-forbid">
                                     <?php 
                                        $exam_needed = 10 - $sessions;
                                        $correct_needed = $tier2_required - $all_correct_answers;
                                        $message_parts = [];
                                    
                                        if ($exam_needed > 0) {
                                            $message_parts[] = "Answer $exam_needed more exam" . ($exam_needed > 1 ? "s" : "");
                                        }
                                        
                                        if ($correct_needed > 0) {
                                            $message_parts[] = "Get $correct_needed more correct answer" . ($correct_needed > 1 ? "s" : "");
                                        }
                                    
                                        if (!empty($message_parts)) {
                                            echo implode(" and ", $message_parts) . " to unlock!";
                                        }
                                        ?>
                                        
                                        
                                        
                                        
                                    </div>
                                    
                                </div>
                                    <?php
                                
                            }
                           
                            ?>
                    </div>
                </div>
        
                <!-- Bottom 3 Category Questions -->
                <div class="stats-column">
                    <div class="stats-section">
                        <h5 class="stats-header">Your Weakest 3 Questions</h5>
                        <div class="stats-line">
                            <div class="stat-con" style="flex-grow: 1; text-align: left;">Question</div>
                            <div class="stat-con" style="text-align: right;">Looksfam Rating</div>
                        </div>
                        <?php
                            
                            if (isset($tier3)){
                                ?><div class="stats">
                                <?php
                                    if (!empty($questions_to_display)) {
                                        // Get bottom 3
                                        $bottom_3 = array_slice($questions_to_display, -3);
                                        foreach ($bottom_3 as $question) {
                                            ?>
                                            <div class="stats-line" data-id="<?php echo $question['question_id']?>">
                                                <div class="stat-con" style="flex-grow: 1; text-align: left;"><?php echo $question['title']; ?></div>
                                                <div class="stat-con" style="text-align: right;color: #58c2f7;"><?php echo $question['displaylook']; ?>%</div>
                                            </div>
                                            <?php
                                        }
                                    }
                                    ?>
                                 </div><?php
                                
                            }else{
                                 ?>
                                 <div class="stats bb">
                                    <div class="stats-line">
                                        <div class="stat-con" style="flex-grow: 1; text-align: left;">This is a random question.</div>
                                        <div class="stat-con" style="text-align: right;color: #58c2f7;">0%</div>
                                    </div>
                                     <div class="stats-line" >
                                        <div class="stat-con" style="flex-grow: 1; text-align: left;">Unlocked it. You have to beat the goal!</div>
                                        <div class="stat-con" style="text-align: right;color: #58c2f7;">0%</div>
                                    </div>
                                     <div class="stats-line">
                                        <div class="stat-con" style="flex-grow: 1; text-align: left;">No Cheating! I repeat no cheating!</div>
                                        <div class="stat-con" style="text-align: right;color: #58c2f7;">0%</div>
                                    </div>
                                    
                                    <div class="stat-forbid">
                                     <?php 
                                        $exam_needed = 20 - $sessions;
                                        $correct_needed = $tier3_required - $all_correct_answers;
                                        $message_parts = [];
                                    
                                        if ($exam_needed > 0) {
                                            $message_parts[] = "Answer $exam_needed more exam" . ($exam_needed > 1 ? "s" : "");
                                        }
                                        
                                        if ($correct_needed > 0) {
                                            $message_parts[] = "Get $correct_needed more correct answer" . ($correct_needed > 1 ? "s" : "");
                                        }
                                    
                                        if (!empty($message_parts)) {
                                            echo implode(" and ", $message_parts) . " to unlock!";
                                        }
                                        ?>   
                                    </div>
                                </div>
                                    <?php
                                
                            }
                           
                            ?>
                        
                        
                    </div>
                </div>
            </div>
        
            <!-- Second row of tables -->
            <div class="stats-row">
                <!-- Top 3 Overall Questions -->
                <div class="stats-column">
                    <div class="stats-section">
                        <h5 class="stats-header">Overall Top Questions</h5>
                        <div class="stats-line">
                            <div class="stat-con" style="flex-grow: 1; text-align: left;">Question</div>
                            <div class="stat-con" style="text-align: right;">Overall Rating</div>
                        </div>
                        
                        <?php
                            
                            if (isset($tier4)){
                                ?><div class="stats">
                                <?php
                                     if (!empty($questions_to_display)) {
                                        // Create a copy of the array for overall sorting
                                        $overall_questions = $questions_to_display;
                                        
                                        // Sort by overall rating (displayoveralllook) in descending order
                                        usort($overall_questions, function($a, $b) {
                                            return $b['displayoveralllook'] - $a['displayoveralllook'];
                                        });
                                        
                                        // Display top 3 by overall rating
                                        $top_3_overall = array_slice($overall_questions, 0, 3);
                                        foreach ($top_3_overall as $question) {
                                            ?>
                                            <div class="stats-line" data-id="<?php echo $question['question_id']?>">
                                                <div class="stat-con" style="flex-grow: 1; text-align: left;"><?php echo $question['title']; ?></div>
                                                <div class="stat-con" style="text-align: right;color: #58c2f7;"><?php echo $question['displayoveralllook']; ?>%</div>
                                            </div>
                                            <?php
                                        }
                                    }
                                    ?>
                                 </div><?php
                                
                            }else{
                                 ?>
                                 <div class="stats bb">
                                    <div class="stats-line">
                                        <div class="stat-con" style="flex-grow: 1; text-align: left;">This is a random question.</div>
                                        <div class="stat-con" style="text-align: right;color: #58c2f7;">0%</div>
                                    </div>
                                     <div class="stats-line" >
                                        <div class="stat-con" style="flex-grow: 1; text-align: left;">Unlocked it. You have to beat the goal!</div>
                                        <div class="stat-con" style="text-align: right;color: #58c2f7;">0%</div>
                                    </div>
                                     <div class="stats-line">
                                        <div class="stat-con" style="flex-grow: 1; text-align: left;">No Cheating! I repeat no cheating!</div>
                                        <div class="stat-con" style="text-align: right;color: #58c2f7;">0%</div>
                                    </div>
                                    
                                    <div class="stat-forbid">
                                        
                                         <?php 
                                            $exam_needed = 50 - $sessions;
                                            $looksfam_needed = 50 - $looksfamacc_x_accuracy_display;
                                            $looksfam_needed = number_format($looksfam_needed, 2); // Format to 2 decimal places
                                            $message_parts = [];
                                        
                                            if ($exam_needed > 0) {
                                                $message_parts[] = "Answer $exam_needed more exam" . ($exam_needed > 1 ? "s" : "");
                                            }
                                            
                                            if ($looksfam_needed > 0) {
                                                $message_parts[] = "Get $looksfam_needed LOOKSFAM RATING";
                                            }
                                        
                                            if (!empty($message_parts)) {
                                                echo implode(" and ", $message_parts) . " to unlock!";
                                            }
                                        ?>
 
                                    </div>
                                </div>
                                    <?php
                                
                            }
                           
                            ?>
                    
                    </div>
                </div>
        
                <!-- Bottom 3 Overall Questions -->
                <div class="stats-column">
                    <div class="stats-section">
                        <h5 class="stats-header">Overall Worst Questions</h5>
                        <div class="stats-line">
                            <div class="stat-con" style="flex-grow: 1; text-align: left;">Question</div>
                            <div class="stat-con" style="text-align: right;">Overall Rating</div>
                        </div>
                        
                        <?php
                            
                            if (isset($tier5)){
                                ?><div class="stats">
                                <?php
                                    if (!empty($overall_questions)) {
                                        // Get bottom 3 by overall rating
                                        $bottom_3_overall = array_slice($overall_questions, -3);
                                        foreach ($bottom_3_overall as $question) {
                                            ?>
                                            <div class="stats-line" data-id="<?php echo $question['question_id']?>">
                                                <div class="stat-con" style="flex-grow: 1; text-align: left;"><?php echo $question['title']; ?></div>
                                                <div class="stat-con" style="text-align: right;color: #58c2f7;"><?php echo $question['displayoveralllook']; ?>%</div>
                                            </div>
                                            <?php
                                        }
                                    }
                                    ?>
                                 </div><?php
                                
                            }else{
                                 ?>
                                 <div class="stats bb">
                                    <div class="stats-line">
                                        <div class="stat-con" style="flex-grow: 1; text-align: left;">This is a random question.</div>
                                        <div class="stat-con" style="text-align: right;color: #58c2f7;">0%</div>
                                    </div>
                                     <div class="stats-line" >
                                        <div class="stat-con" style="flex-grow: 1; text-align: left;">Unlocked it. You have to beat the goal!</div>
                                        <div class="stat-con" style="text-align: right;color: #58c2f7;">0%</div>
                                    </div>
                                     <div class="stats-line">
                                        <div class="stat-con" style="flex-grow: 1; text-align: left;">No Cheating! I repeat no cheating!</div>
                                        <div class="stat-con" style="text-align: right;color: #58c2f7;">0%</div>
                                    </div>
                                    <div class="stat-forbid">
                                      <?php 
                                            $exam_needed = 70 - $sessions;
                                            $looksfam_needed = 50 - $looksfamacc_x_accuracy_display;
                                            $looksfam_needed = number_format($looksfam_needed, 2); // Format to 2 decimal places
                                            $message_parts = [];
                                        
                                            if ($exam_needed > 0) {
                                                $message_parts[] = "Answer $exam_needed more exam" . ($exam_needed > 1 ? "s" : "");
                                            }
                                            
                                            if ($looksfam_needed > 0) {
                                                $message_parts[] = "Get $looksfam_needed LOOKSFAM RATING";
                                            }
                                        
                                            if (!empty($message_parts)) {
                                                echo implode(" and ", $message_parts) . " to unlock!";
                                            }
                                        ?>
                                    </div>
                                </div>
                                    <?php
                                
                            }
                           
                            ?>
                       
                    </div>
                </div>
            </div>
            <div class="stats-section">
                <h5 class="stats-header">All Question stats</h5>
                <div class="stats-line">
                    <div class="stat-con" style="flex-grow: 1; text-align: left;">Question</div>
                    <div class="stat-con" style="text-align: center;"> Looksfam Rating </div>
                    
                    <div class="stat-con" style="flex-grow: 1; text-align: right;">Others Rating</div>
                </div>
                <?php
                            
                            if (!$tier6){
                                ?>
                                <div class="stats">
                                <?php
                                if (!empty($user_questions) && is_array($user_questions)) {
                                    
                                
                                    
                                    // Display sorted questions
                                    foreach ($questions_to_display as $question) {
                                        ?>
                                        <div class="stats-line" data-id="<?php echo $question['question_id']?>" >
                                            <div class="stat-con" style="flex-grow: 1; text-align: left;"><?php echo $question['title']; ?></div>
                                            <div class="stat-con" style="text-align: center;color: #58c2f7;"><?php echo $question['displaylook']; ?>%</div>
                                            <div class="stat-con" style="flex-grow: 1; text-align: right;"><?php echo $question['displayoveralllook']; ?>%</div>
                                        </div>
                                        <?php
                                    }
                                    
                                    }?>
                                 </div>
                                 <?php
                                
                            }else{
                                 ?>
                                 <div class="stats bb">
                                    <div class="stats-line">
                                        <div class="stat-con" style="flex-grow: 1; text-align: left;">This is a random question.</div>
                                        <div class="stat-con" style="text-align: right;color: #58c2f7;">0%</div>
                                    </div>
                                     <div class="stats-line" >
                                        <div class="stat-con" style="flex-grow: 1; text-align: left;">Unlocked it. You have to beat the goal!</div>
                                        <div class="stat-con" style="text-align: right;color: #58c2f7;">0%</div>
                                    </div>
                                     <div class="stats-line">
                                        <div class="stat-con" style="flex-grow: 1; text-align: left;">No Cheating! I repeat no cheating!</div>
                                        <div class="stat-con" style="text-align: right;color: #58c2f7;">0%</div>
                                    </div>
                                    <div class="stat-forbid">
                                     🔒<br>For Paid Users only!

                                    </div>
                                </div>
                                    <?php
                                
                            }
                           
                            ?>
                <div class="stats ">
                        
                </div> 
            <?php
            if (empty($displayed_questions)) {
                ?>
                
                <?php
            }
        
            ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function add_fake_answers_admin_menu() {
    add_submenu_page(
        'tools.php',
        ' Exam Answers',
        'Generate  Answers',
        'manage_options',
        'fake-exam-answers',
        'fake_answers_admin_page'
    );
}
add_action('admin_menu', 'add_fake_answers_admin_menu');
function fake_answers_admin_page() {
    ?>
    <div class="wrap">
        <h1>Generate Fake Exam Answers</h1>
        <p>Select a class and configure the exam simulation parameters.</p>

        <table class="form-table">
            <tr>
                <th><label for="class_id">Choose Class:</label></th>
                <td>
                    <?php 
                    $classes = get_posts(['post_type' => 'class', 'numberposts' => -1]);
                    ?>
                    <select id="class_id" style="width: 300px;">
                        <option value="">Select a class</option>
                        <?php foreach ($classes as $class): ?>
                            <option value="<?php echo esc_attr($class->ID); ?>">
                                <?php echo esc_html($class->post_title); ?>
                            </option>
                        <?php endforeach; ?>
                    </select>
                </td>
            </tr>
            <tr>
                <th><label for="num_students">Number of Students:</label></th>
                <td>
                    <input type="number" id="num_students" value="10" min="1" max="50" style="width: 100px;">
                    <p class="description">How many students should participate (randomly selected from enrolled students)</p>
                </td>
            </tr>
            <tr>
                <th><label for="num_questions">Number of Questions:</label></th>
                <td>
                    <input type="number" id="num_questions" value="10" min="1" max="100" style="width: 100px;">
                    <p class="description">How many questions each student should answer</p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button id="generate-fake-answers" class="button button-primary">Generate Fake Exam Session</button>
        </p>

        <div id="fake-answers-results" style="margin-top:20px; max-height:500px; overflow-y:scroll; border:1px solid #ccc; padding:15px; background: #f9f9f9;">
            <p>No results yet.</p>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        $('#generate-fake-answers').click(function() {
            var class_id = $('#class_id').val();
            var num_students = $('#num_students').val();
            var num_questions = $('#num_questions').val();
            
            if(!class_id) { 
                alert("Please select a class"); 
                return; 
            }
            
            if(!num_students || num_students < 1) {
                alert("Please enter a valid number of students (minimum 1)");
                return;
            }
            
            if(!num_questions || num_questions < 1) {
                alert("Please enter a valid number of questions (minimum 1)");
                return;
            }

            $('#fake-answers-results').html("<p><strong>Generating exam session...</strong></p>");

            $.post(ajaxurl, {
                action: 'generate_fake_answers',
                class_id: class_id,
                num_students: num_students,
                num_questions: num_questions
            }, function(response) {
                $('#fake-answers-results').html(response);
            }).fail(function(xhr, status, error) {
                console.error("AJAX Error:", error);
                $('#fake-answers-results').html("<p style='color:red;'>Error generating answers. Check console for details.</p>");
            });
        });
    });
    </script>
    <?php
}

add_action('wp_ajax_generate_fake_answers', 'generate_fake_answers_ajax');
function generate_fake_answers_ajax() {
    global $wpdb;

    $class_id = intval($_POST['class_id']);
    $num_students = intval($_POST['num_students']);
    $num_questions = intval($_POST['num_questions']);
    
    if (!$class_id) { wp_die("Invalid class."); }
    if ($num_students < 1) { wp_die("Invalid number of students."); }
    if ($num_questions < 1) { wp_die("Invalid number of questions."); }

    // Get subject/topic from class meta
    $subject_id = get_post_meta($class_id, 'exam_subject', true);
    $topic_id   = get_post_meta($class_id, 'exam_topic', true);
    $cat_id     = $topic_id ?: $subject_id;

    // Get enrolled students
    $enrolled_students = get_post_meta($class_id, 'enrolled_students', true);
    if (empty($enrolled_students)) {
        echo "<p style='color:red;'>No enrolled students found in this class.</p>";
        wp_die();
    }

    // Check if we have enough students
    if (count($enrolled_students) < $num_students) {
        echo "<p style='color:orange;'>Warning: Only " . count($enrolled_students) . " students enrolled, but you requested $num_students. Using all available students.</p>";
        $num_students = count($enrolled_students);
    }

    // Get random questions
    $questions = get_questions($cat_id, $num_questions);
    if (empty($questions)) {
        echo "<p style='color:red;'>No questions found for this subject/topic.</p>";
        wp_die();
    }

    if (count($questions) < $num_questions) {
        echo "<p style='color:orange;'>Warning: Only " . count($questions) . " questions available, but you requested $num_questions. Using all available questions.</p>";
        $num_questions = count($questions);
    }

    // Randomly select students
    $selected_students = array_rand(array_flip($enrolled_students), $num_students);
    if (!is_array($selected_students)) {
        $selected_students = [$selected_students]; // Handle case when only 1 student
    }

    // Generate session ID for this exam
    $session_id =  substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);
    
    echo "<div style='margin-bottom: 20px; padding: 10px; background: #e8f4fd; border-left: 4px solid #0073aa;'>";
    echo "<h3>Exam Session: $session_id</h3>";
    echo "<p><strong>Class:</strong> " . esc_html(get_the_title($class_id)) . "</p>";
    echo "<p><strong>Students:</strong> $num_students | <strong>Questions:</strong> $num_questions</p>";
    echo "</div>";

    $answers_batch = [];
    $choices = ['A','B','C','D'];
    $total_answers = 0;
    $correct_answers = 0;

    echo "<table class='widefat striped'><thead><tr>
        <th>Student</th><th>Question #</th><th>Question Title</th><th>Answer</th><th>Correct Answer</th><th>Result</th>
    </tr></thead><tbody>";

    // Each selected student answers all questions
    foreach ($selected_students as $student_id) {
        $student_info = get_userdata($student_id);
        $student_name = $student_info ? $student_info->display_name : "Unknown Student";
        
        // Decide this student's accuracy (random between 40–85%)
        $student_accuracy = rand(40, 85) / 100;
        $student_correct_target = ceil($num_questions * $student_accuracy);
        $student_correct_count = 0;
        
        // Shuffle questions for this student
        $student_questions = $questions;
        shuffle($student_questions);
        
        for ($q_num = 0; $q_num < $num_questions; $q_num++) {
            $question = $student_questions[$q_num];
            $correct_answer = get_post_meta($question['ID'], 'correct_answer', true);
            
            // Determine if this answer should be correct
            $remaining_questions = $num_questions - $q_num;
            $remaining_correct_needed = $student_correct_target - $student_correct_count;
            
            if ($remaining_correct_needed > 0 && 
                ($remaining_correct_needed >= $remaining_questions || rand(1, 100) <= 60)) {
                // Give correct answer
                $fake_answer = $correct_answer;
                $is_correct = 1;
                $student_correct_count++;
                $correct_answers++;
            } else {
                // Give wrong answer
                $wrong_choices = array_diff($choices, [$correct_answer]);
                $fake_answer = $wrong_choices[array_rand($wrong_choices)];
                $is_correct = 0;
            }
            
            $total_answers++;
            
            $answers_batch[] = [
                'exam_id'           => 0,
                'class_id'          => $class_id,
                'exam_name'         => 'Simulated Exam - ' . date('M j, Y H:i'),
                'question_id'       => intval($question['ID']),
                'user_id'           => $student_id,
                'user_answer'       => $fake_answer,
                'is_correct'        => $is_correct,
                'timestamp'         => current_time('mysql'),
                'session_id'        => $session_id,
                'question_category' => $cat_id,
            ];

            $result_icon = $is_correct ? "<span style='color:green;'>✓</span>" : "<span style='color:red;'>✗</span>";
            
            echo "<tr>
                <td>" . esc_html($student_name) . "</td>
                <td>" . ($q_num + 1) . "</td>
                <td>" . esc_html($question['post_title']) . "</td>
                <td><strong>$fake_answer</strong></td>
                <td><strong>$correct_answer</strong></td>
                <td>$result_icon</td>
            </tr>";
        }
    }

    echo "</tbody></table>";

    // Show summary statistics
    $overall_accuracy = $total_answers > 0 ? round(($correct_answers / $total_answers) * 100, 1) : 0;
    
    echo "<div style='margin-top: 20px; padding: 15px; background: #f0f0f0; border: 1px solid #ddd;'>";
    echo "<h3>Exam Summary</h3>";
    echo "<p><strong>Total Answers:</strong> $total_answers</p>";
    echo "<p><strong>Correct Answers:</strong> $correct_answers</p>";
    echo "<p><strong>Overall Accuracy:</strong> $overall_accuracy%</p>";
    echo "<p><strong>Session ID:</strong> $session_id</p>";
    echo "</div>";

    // Save to database
    if (!empty($answers_batch)) {
        $saved = save_exam_answers_batch($answers_batch);
        if ($saved) {
            echo "<div style='margin-top: 10px; padding: 10px; background: #d4edda; border: 1px solid #c3e6cb; color: #155724;'>";
            echo "<strong>✓ Success:</strong> All answers have been saved to the database.";
            echo "</div>";
        } else {
            echo "<div style='margin-top: 10px; padding: 10px; background: #f8d7da; border: 1px solid #f5c6cb; color: #721c24;'>";
            echo "<strong>✗ Error:</strong> Failed to save answers to database.";
            echo "</div>";
        }
    }

    wp_die();
}


function handle_flashcard_exam_submission() {
    if (isset($_POST['submit_flashcard_exam']) && isset($_POST['user_answers'])) {
        // Generate a random 5-character session ID
        $session_id = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 5);
        $class_id = intval($_GET['class_id']);
        $cat = intval($_GET['cat']);
        $category = get_term_by('id', $cat, 'question_category');

        // Verify the nonce for security
        if (isset($_POST['flashcard_exam_submission_nonce']) && wp_verify_nonce($_POST['flashcard_exam_submission_nonce'], 'flashcard_exam_submission_nonce')) {
            $user_id = get_current_user_id();
            $user_answers = $_POST['user_answers'];
            $exam_id = intval($_GET['id']); // Get the exam ID from the URL parameter
            $exam_name = get_the_title($exam_id); // Get the exam name
        
            
            // Save question results to the respective question
            $question_results = get_post_meta($question_id, 'question_results', true);
            $question_results = is_array($question_results) ? $question_results : array();

            
            // Save question results to the respective question
            $activity_results = get_post_meta($class_id, 'activity_results', true);
            $activity_results = is_array($activity_results) ? $activity_results : array();

            
            // Save question results to the respective question
            $user_questions = get_user_meta($user_id, 'questions', true);
            $user_questions = is_array($user_questions) ? $user_questions : array();

            // Check if 'cat' is present in the URL
            $is_cat_present = isset($_GET['cat']);

            
            // Prepare batch data for efficient database insertion
            $answers_batch = array();
            
            // Check each question's answer
            foreach ($user_answers as $question_id => $user_answer) {
                // Skip questions that weren't answered
                if (empty($user_answer)) {
                    continue;
                }

                $correct_answer = get_post_meta($question_id, 'correct_answer', true);
                $is_correct = ($user_answer === $correct_answer) ? '1' : '0';
                
                // Get the category of the question
                $question_categories = wp_get_post_terms($question_id, 'question_category', array("fields" => "names"));
                $question_category = !empty($question_categories) ? $question_categories[0] : 'Uncategorized';

                $result_entry = array(
                    'exam_id' => $exam_id,
                    'class_id' => $class_id,
                    'exam_name' => $exam_name,
                    'question_id' => $question_id,
                    'user_id' => $user_id,
                    'user_answer' => $user_answer,
                    'is_correct' => $is_correct,
                    'timestamp' => current_time('mysql'),
                    'session_id' => $session_id,
                    'question_category' => $cat,
                );
                

                
                $question_results[] = $result_entry;
                $activity_results[] = $result_entry;
                update_post_meta($question_id, 'question_results', $question_results);
                update_post_meta($class_id, 'activity_results', $activity_results); 
                
                // Prepare data for batch insert
                $answers_batch[] = array(
                    'exam_id' => $exam_id,
                    'class_id' => $class_id,
                    'exam_name' => $exam_name,
                    'question_id' => intval($question_id),
                    'user_id' => $user_id,
                    'user_answer' => sanitize_text_field($user_answer),
                    'is_correct' => $is_correct,
                    'timestamp' => current_time('mysql'),
                    'session_id' => $session_id,
                    'question_category' => $cat,
                );
            }
            // Save all answers in a single batch operation
            if (!empty($answers_batch)) {
                $save_result = save_exam_answers_batch($answers_batch);
                
                if (!$save_result) {
                    // Fallback to individual saves if batch fails
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'exam_answers';
                    
                    foreach ($answers_batch as $answer_data) {
                        $wpdb->insert($table_name, $answer_data);
                    }
                }
            }
            if ($is_cat_present) {
                // Save the questions to the user's meta data
                $confirmation_url = home_url('/confirm?id=' . $exam_id . '&done=1&class_id=' . $class_id . '&cat=' . $cat . '&session_id=' . $session_id); 
            } else {
                // Save exam results to the database
                $confirmation_url = home_url('/confirm?id=' . $exam_id . '&done=1&class_id=' . $class_id . '&session_id=' . $session_id); 
            }

            // Redirect to confirmation page after successful submission
            wp_redirect($confirmation_url);
            exit;
        }
    }
}
add_action('template_redirect', 'handle_flashcard_exam_submission');