<?php 
function display_classes_shortcode($atts) {
    // Set default attributes
    $atts = shortcode_atts(array(
        'posts_per_page' => -1,
        'order' => 'ASC',
        'orderby' => 'title',
        'search_term' => ''
    ), $atts);
    
    // Set up query arguments
    $args = array(
        'post_type' => 'class',
        'post_status' => 'publish',
        'posts_per_page' => $atts['posts_per_page'],
        'order' => $atts['order'],
        'orderby' => $atts['orderby']
    );
    
    // Add search term if provided
    if (!empty($atts['search_term'])) {
        $args['s'] = sanitize_text_field($atts['search_term']);
    }
    
    $classes = get_posts($args);
    
    // Start output buffering
    ob_start();
    
    // Include styles
    echo get_styles();
    
    if (empty($classes)) {
        echo '<p>No classes found.</p>';
        return ob_get_clean();
    }
    
    echo '<div class="category-grid" style="padding:20px 0px;">';
    
    foreach ($classes as $class) {
        $class_title = get_the_title($class->ID);
        $class_content = $class->post_content;
        $isFree = has_term('Free', 'class', $class->ID);
        $isPremium = has_term('Premium', 'class', $class->ID);
        
        // Skip if neither free nor premium
        if (!$isFree && !$isPremium) {
            continue;
        }
        
        echo '<div class="category-button">';
        
        
        
        if ($isFree) {
            printf(
                '<button onclick="window.location.href=\'%s\';" style="border-radius: 10px; width: 100%%; height: 100%%;">
                    <h5>[FREE] - %s</h5>
                </button>',
                esc_url(home_url('/checkout?class=' . $class->ID)),
                esc_html($class_title)
            );
        } elseif ($isPremium) {
            printf(
                '<button onclick="window.location.href=\'%s\';" style="border-radius: 10px; width: 100%%; height: 100%%;">
                    <h5>%s</h5>
                </button>',
                esc_url(home_url('/checkout?class=' . $class->ID)),
                esc_html($class_title)
            );
        }
        
        echo '</div>';
    }
    
    echo '</div>';
    
    // Return the buffered content
    return ob_get_clean();
}

// Register the shortcode
add_shortcode('display_classes', 'display_classes_shortcode');
function is_user_enrolled($user_id, $class_id) {
    $enrolled_students = get_post_meta($class_id, 'enrolled_students', true) ?: array();
    $classes_enrolled = get_user_meta($user_id, 'classes_enrolled', true) ?: array();

    return in_array($user_id, $enrolled_students) || in_array($class_id, $classes_enrolled);
}


// Add a shortcode for front-end user enrollment
function enrollment_shortcode() {
    $current_user = wp_get_current_user();

    // Check if the user is logged in
    if (is_user_logged_in()) {
        
        echo get_styles();
        // Check if a key is submitted
        $enrollment_result = process_enrollment_key();
        if ($enrollment_result !== null) {
            if ($enrollment_result['success']) { ?>
                <p class="enrollment-success"><?php echo $enrollment_result['message']; ?></p>
                <script>
                    setTimeout(function() {
                        window.location.href = "<?php echo $enrollment_result['redirect_url']; ?>";
                    }, 3000);
                </script>
                <p class="enrollment-error">If you are not redirected, <a href="<?php echo $enrollment_result['redirect_url']; ?>">click here</a>.</p>
            <?php } else { ?>
                <p class="enrollment-error"><?php echo $enrollment_result['message']; ?></p>
            <?php }
        } ?>

        <style>
            .custom-transparent-button {
                border: 2px solid #58c2f7; /* Replace with your desired border color */
                background: transparent;
                color: #58c2f7; /* Replace with your desired text color */
                border-radius: 10px;
                width: 49%;
                cursor: pointer;
                transition: background 0.3s ease;
            }
        
            .custom-transparent-button:hover {
                background: #ffffff; /* Replace with your desired hover color */
            }
            .go-back-button {
                margin-top: 20px;
                margin-bottom: 20px; 
                height:50px;
            }
            
            .button-container {
                display: flex;
                justify-content: space-between;
                margin-top: 10px;
            }
            .button-primary {
                width: calc(33.33% - 10px); /* Adjust the width as needed */
                margin: 5px;
            }  
        </style>
        
        <div class="ast-container">
            <div style="width:100%;">
                <div>
                    <div class="button-container">
                        <input type="button" value="←" class="go-back-button" onclick="window.location.href='/profile';" style="border-radius: 10px;">
                    </div>

                    <h3>Enroll Class</h3>
                    
                    <form method="post">
                        <label for="enroll_key">Enter Class Key:</label><br>
                        <input type="text" name="enroll_key" id="enroll_key" style="border-radius: 10px;width:100%; margin-top:10px;" required><br>
                        <button type="submit" class="button-primary" style="border-radius: 10px;width:100%; margin-top:10px;height:75px;margin-left: 0px;margin-right: 0px;">Enroll</button>
                        <div hidden>NO CLASS KEYS? CLICK <a href="<?php echo home_url('free-classes'); ?>" target="_blank">HERE</a>.</div>
                    </form>

                    <h3>Available Classes</h3>
                    <div style="width:100%;">
                        <input type="text" id="class_search" placeholder="Search for a class here" style="border-radius: 10px;width:100%;">
                    </div>
                    <div id="available_classes"></div>
                    
                    <script>
                        var ajaxurl = "/wp-admin/admin-ajax.php";

                        function updateClassList(searchTerm) {
                            jQuery.ajax({
                                url: ajaxurl,
                                type: "POST",
                                data: {
                                    action: "get_classes_by_search",
                                    search_term: searchTerm,
                                },
                                success: function(response) {
                                    document.getElementById("available_classes").innerHTML = response;
                                },
                                error: function(xhr, status, error) {
                                    console.log("Error:", error);
                                }
                            });
                        }

                        document.getElementById("class_search").addEventListener("input", function() {
                            var searchTerm = this.value;
                            updateClassList(searchTerm);
                        });

                        updateClassList("");
                    </script>

                    <script>
                        var ajaxurl = "/wp-admin/admin-ajax.php";
                        
                        function enrollFreeClass(classId) {
                            jQuery.ajax({
                                url: ajaxurl,
                                type: "POST",
                                data: {
                                    action: "get_unused_enrollment_key",
                                    class_id: classId,
                                },
                                success: function(response) {
                                    document.getElementById("enroll_key").value = response;
                                },
                                error: function(xhr, status, error) {
                                    console.log("Error:", error);
                                }
                            });
                        }
                    </script>
                </div>
            </div>
        </div>

    <?php } else { ?>
        <p class="enrollment-error">You must be logged in to enroll.</p>
    <?php }
}
function process_enrollment_key() {
    if (isset($_POST['enroll_key'])) {
        $enroll_key = sanitize_text_field($_POST['enroll_key']);
        $class_keys = get_term_meta(305, 'class_keys', true) ?: array();
        $user_id = get_current_user_id();
        $class_id = null;

        // Check if the key is valid and unused
        foreach ($class_keys as $key => $data) {
            if ($key === $enroll_key && ($data['status'] === 'Unused' || ($data['status'] === 'Used' && $data['user'] == $user_id))) {
                $class_id = $data['class'];
                break;
            }
        }

        if ($class_id) {
            // Enroll the user in the associated class
            $enrolled_students = get_post_meta($class_id, 'enrolled_students', true) ?: array();
            $enrolled_students = array_diff($enrolled_students, array($user_id));
            $enrolled_students[] = $user_id;
            update_post_meta($class_id, 'enrolled_students', $enrolled_students);

            $classes_enrolled = get_user_meta($user_id, 'classes_enrolled', true);
            if (is_array($classes_enrolled)) {
                $classes_enrolled = array_diff($classes_enrolled, array($class_id));
                $classes_enrolled[] = $class_id;
            } else {
                $classes_enrolled = array($class_id);
            }
            update_user_meta($user_id, 'classes_enrolled', $classes_enrolled);

            // Change 'Used' keys for the same class and user to 'Expired'
            foreach ($class_keys as $key => &$data) {
                if ($data['class'] == $class_id && $data['status'] === 'Used' && $data['user'] == $user_id) {
                    $data['status'] = 'Expired';
                }
            }
            delete_user_meta($user_id, 'free_trial_' . $class_id);
            // Update the current key status, user, and add timestamp
            $class_keys[$enroll_key]['status'] = 'Used';
            $class_keys[$enroll_key]['user'] = $user_id;
            $class_keys[$enroll_key]['used_timestamp'] = current_time('mysql');
            update_term_meta(305, 'class_keys', $class_keys);

            $response = array(
                'success' => true,
                'message' => 'Enrollment successful! Redirecting...',
                'redirect_url' => home_url()
            );
        } else {
            $response = array(
                'success' => false,
                'message' => 'Invalid key, key has already been used by another user, or you are already enrolled.'
            );
        }
        return $response;
    }
    return null;
}
function get_classes_by_search() {
    if (!isset($_POST['search_term'])) {
        wp_send_json_error('Search term is required');
        return;
    }

    $search_term = sanitize_text_field($_POST['search_term']);
    $current_user = wp_get_current_user();
    
    // Get enrolled classes and ensure it's an array
    $classes_enrolled = get_user_meta($current_user->ID, 'classes_enrolled', true);
    $classes_enrolled = !empty($classes_enrolled) ? (array)$classes_enrolled : array();

    $args = array(
        'post_type' => 'class',
        'posts_per_page' => -1,
        's' => $search_term,
    );
    
    $classes = get_posts($args);
    
    if (empty($classes)) {
        echo '<p>No matching classes found.</p>';
        wp_die();
        return;
    }

    echo '<div class="category-grid" style="padding:20px 0px;">';
    
    foreach ($classes as $class) {
        // Skip if user is already enrolled
        if (in_array($class->ID, $classes_enrolled)) {
            continue;
        }

        $class_title = get_the_title($class->ID);
        $isFree = has_term('Free', 'class', $class->ID);
        $isPremium = has_term('Premium', 'class', $class->ID);

        // Skip if neither free nor premium
        if (!$isFree && !$isPremium) {
            continue;
        }

        echo '<div class="category-button">';
        
        if ($isFree) {
            printf(
                '<button onclick="enrollFreeClass(%d)" style="border-radius: 10px; width: 100%%; height: 100%%;">
                    <h5>[FREE] - %s</h5>
                </button>',
                $class->ID,
                esc_html($class_title)
            );
        } elseif ($isPremium) {
            printf(
                '<button onclick="window.location.href=\'%s\';" style="border-radius: 10px; width: 100%%; height: 100%%;">
                    <h5>%s</h5>
                    
                </button>',
                esc_url(home_url('/checkout?class=' . $class->ID)),
                esc_html($class_title)
            );
        }
        
        echo '</div>';
    }
    
    echo '</div>';
    wp_die();
}

// Register the shortcode
add_shortcode('enrollment_form', 'enrollment_shortcode');
add_action('wp_ajax_get_unused_enrollment_key', 'get_unused_enrollment_key');
add_action('wp_ajax_nopriv_get_unused_enrollment_key', 'get_unused_enrollment_key');
add_action('wp_ajax_get_classes_by_search', 'get_classes_by_search');
add_action('wp_ajax_nopriv_get_classes_by_search', 'get_classes_by_search');


function get_unused_enrollment_key() {
    $class_id = isset($_POST['class_id']) ? intval($_POST['class_id']) : 0;

    // Your logic to get an unused enrollment key for the specified class
    // Replace this with your actual implementation
    $unused_key = get_unused_key_for_class($class_id);

    //echo $unused_key;
    echo "Click the no class keys link below.";

    wp_die();
}

function get_unused_key_for_class($class_id) {
    // Your logic to get an unused enrollment key for the specified class
    // Replace this with your actual implementation

    // Example: Get the first unused key for the class
    $class_keys = get_term_meta(305, 'class_keys', true) ?: array();
    foreach ($class_keys as $key => $data) {
        if ($data['class'] === $class_id && $data['status'] === 'Unused') {
            return $key;
        }
    }

    return ''; // Return an empty string if no unused key is found
}

function enrollclass($class, $user_id) {

        
    $class_id = $class;

    // Add user to the enrolled students list
    $enrolled_students = get_post_meta($class_id, 'enrolled_students', true) ?: array();
    $enrolled_students[] = $user_id;
    update_post_meta($class_id, 'enrolled_students', $enrolled_students);
    
    $classes_enrolled = get_user_meta($user_id, 'classes_enrolled', true);
    
    if (is_array($classes_enrolled)) {
        $classes_enrolled[] = $class_id;
    } else {
        $classes_enrolled = array($class_id);
    }
    
    update_user_meta($user_id, 'classes_enrolled', $classes_enrolled);
    
    // Redirect to /profile after successful enrollment
    wp_redirect('/profile');
    exit;

   
}


function getClassCategoryStatus($post_id) {
    
   // Check if the post has the "Free" category
    $isFree = has_term('Free', 'class', $post_id);

    // Check if the post has the "Premium" category
    $isPremium = has_term('Premium', 'class', $post_id);

    // Determine the category status
    if ($isFree) {
        $adsFilePath = ABSPATH . 'wp-content/plugins/WordPress-Plugin-Template-master/includes/ads.php';

        // Check if the file exists before getting contents
        if (file_exists($adsFilePath)) {
            ob_start(); // Start output buffering
            include $adsFilePath;
            $adsContent = ob_get_clean(); // Capture the output and clean the buffer
            return $adsContent;
        } 
    } elseif ($isPremium) {
        return '';
    } else {
        return ''; // or any other status you want to handle
    }
    

}

// Add this to your theme's functions.php or a custom plugin file

// Add submenu page
add_action('admin_menu', 'add_class_keys_submenu');
function add_class_keys_submenu() {
    add_submenu_page(
        'edit.php?post_type=class', // Parent slug
        'Class Keys', // Page title
        'Class Keys', // Menu title
        'manage_options', // Capability
        'class-keys', // Menu slug
        'display_class_keys' // Function to display the page content
    );
}

function display_class_keys() {
    $class_keys = get_term_meta(305, 'class_keys', true);
    if (!is_array($class_keys)) {
        $class_keys = array();
    }

    // Handle key deletion
    if (isset($_POST['delete_keys']) && !empty($_POST['keys_to_delete'])) {
        foreach ($_POST['keys_to_delete'] as $key_to_delete) {
            if (isset($class_keys[$key_to_delete])) {
                $class_id = $class_keys[$key_to_delete]['class'];
                $user_id  = isset($class_keys[$key_to_delete]['user']) ? $class_keys[$key_to_delete]['user'] : null;

                // Remove from enrolled_students
                if ($class_id) {
                    $enrolled_students = get_post_meta($class_id, 'enrolled_students', true) ?: array();
                    if ($user_id && in_array($user_id, $enrolled_students)) {
                        $enrolled_students = array_diff($enrolled_students, array($user_id));
                        update_post_meta($class_id, 'enrolled_students', $enrolled_students);
                    }
                }

                // Remove from classes_enrolled
                if ($user_id) {
                    $classes_enrolled = get_user_meta($user_id, 'classes_enrolled', true) ?: array();
                    if (in_array($class_id, $classes_enrolled)) {
                        $classes_enrolled = array_diff($classes_enrolled, array($class_id));
                        update_user_meta($user_id, 'classes_enrolled', $classes_enrolled);
                    }
                }

                // Delete the key
                unset($class_keys[$key_to_delete]);
            }
        }
        update_term_meta(305, 'class_keys', $class_keys);
        echo '<div class="updated"><p>Selected keys have been deleted and related data has been updated.</p></div>';
    }

    // Handle key addition
    if (isset($_POST['add_keys'])) {
        $num_keys = intval($_POST['num_keys']);
        $class_id = intval($_POST['class_id']);
        $duration = intval($_POST['duration']);

        for ($i = 0; $i < $num_keys; $i++) {
            $new_key = generate_unique_key($class_keys);
            $class_keys[$new_key] = array(
                'class'          => $class_id,
                'status'         => 'Unused',
                'duration'       => $duration,
                'used_timestamp' => null, // Important for sorting
            );
        }
        update_term_meta(305, 'class_keys', $class_keys);
        echo '<div class="updated"><p>New keys have been added.</p></div>';
    }

    echo '<div class="wrap">';
    echo '<h1>Class Keys</h1>';

    // Form to add new keys
    echo '<h2>Add New Keys</h2>';
    echo '<form method="post">';
    echo '<label for="num_keys">Number of Keys:</label>';
    echo '<input type="number" name="num_keys" min="1" required>';
    echo '<label for="class_id">Class:</label>';
    echo '<select name="class_id" required>';
    $classes = get_posts(array('post_type' => 'class', 'numberposts' => -1));
    foreach ($classes as $class) {
        echo '<option value="' . $class->ID . '">' . esc_html($class->post_title) . '</option>';
    }
    echo '</select>';
    echo '<label for="duration">Duration:</label>';
    echo '<select name="duration" required>';
    echo '<option value="1">1 month</option>';
    echo '<option value="3">3 months</option>';
    echo '<option value="6">6 months</option>';
    echo '<option value="12">12 months</option>';
    echo '</select>';
    echo '<input type="submit" name="add_keys" value="Add Keys" class="button button-primary">';
    echo '</form>';

    // Class filter dropdown
    $current_url = add_query_arg(array());
    echo '<form method="get" action="' . $current_url . '">';
    echo '<select name="sort">';
    echo '<option value="">All Classes</option>';
    foreach ($classes as $class) {
        echo '<option value="' . $class->ID . '" ' . selected($_GET['sort'], $class->ID, false) . '>' . esc_html($class->post_title) . '</option>';
    }
    echo '</select>';
    echo '<input type="submit" value="Filter" class="button">';
    echo '</form>';

    $selected_class = isset($_GET['sort']) ? intval($_GET['sort']) : null;

    // ✅ Sort keys by latest used timestamp (nulls go last)
    uasort($class_keys, function ($a, $b) {
        $t1 = isset($a['used_timestamp']) && $a['used_timestamp'] ? strtotime($a['used_timestamp']) : 0;
        $t2 = isset($b['used_timestamp']) && $b['used_timestamp'] ? strtotime($b['used_timestamp']) : 0;
        return $t2 <=> $t1; // Descending order
    });

    // Display keys table
    echo '<h2>Existing Keys</h2>';
    echo '<form method="post">';
    echo '<table class="wp-list-table widefat fixed striped">';
    echo '<thead><tr><th><input type="checkbox" id="select-all"></th><th>Key</th><th>Class</th><th>Status</th><th>User</th><th>Used Timestamp</th><th>Duration</th><th>Expiry Date</th></tr></thead>';
    echo '<tbody>';

    foreach ($class_keys as $key => $data) {
        if ($selected_class && $data['class'] != $selected_class) {
            continue;
        }

        $timestamp = $data['used_timestamp'];
        $duration  = isset($data['duration']) ? $data['duration'] : 0;

        $expiry_date = $timestamp
            ? date('Y-m-d H:i:s', strtotime($timestamp . " + " . round($duration * 30.44) . " days"))
            : 'N/A';

        echo '<tr>';
        echo '<td><input type="checkbox" name="keys_to_delete[]" value="' . esc_attr($key) . '"></td>';
        echo '<td>' . esc_html($key) . '</td>';
        echo '<td>' . esc_html(get_the_title($data['class'])) . '</td>';
        echo '<td>' . esc_html($data['status']) . '</td>';
        echo '<td>' . (isset($data['user']) ? esc_html(get_userdata($data['user'])->user_login) : 'N/A') . '</td>';
        echo '<td>' . ($timestamp ? esc_html($timestamp) : 'N/A') . '</td>';
        echo '<td>' . esc_html($data['duration']) . ' month' . ($data['duration'] > 1 ? 's' : '') . '</td>';
        echo '<td>' . esc_html($expiry_date) . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
    echo '<input type="submit" name="delete_keys" value="Delete Selected Keys" class="button button-secondary">';
    echo '</form>';
    echo '</div>';

    echo '<script>
        jQuery(document).ready(function($) {
            $("#select-all").click(function() {
                $("input[name=\'keys_to_delete[]\']").prop("checked", this.checked);
            });
        });
    </script>';
}

// Function to generate a unique key
function generate_unique_key($existing_keys) {
    do {
        $key = substr(md5(uniqid(mt_rand(), true)), 0, 8);
    } while (isset($existing_keys[$key]));
    return $key;
}


function display_available_class_keys_shortcode() {
    ob_start(); // Start output buffering
    $class_keys = get_term_meta(305, 'class_keys', true) ?: array();
    $classes = array();
    // Group keys by class and filter out Used or Expired keys, and keys with duration != 1
    foreach ($class_keys as $key => $data) {
        if ($data['status'] !== 'Used' && $data['status'] !== 'Expired' && $data['duration'] == 1) {
            $class_id = $data['class'];
            if (!isset($classes[$class_id])) {
                $classes[$class_id] = array(
                    'title' => get_the_title($class_id),
                    'keys' => array()
                );
            }
            $classes[$class_id]['keys'][] = array(
                'key' => $key,
                'duration' => $data['duration']
            );
        }
    }
    // Display the classes and their available keys
    if (!empty($classes)) {
        echo '<div class="available-class-keys">';
        foreach ($classes as $class_id => $class_data) {
            if (!empty($class_data['keys'])) {
                echo '<h3>' . esc_html($class_data['title']) . '</h3>';
                echo '<ul>';
                foreach ($class_data['keys'] as $key_data) {
                    echo '<li>Key: ' . esc_html($key_data['key']) . '</li>';
                }
                echo '</ul>';
            }
        }
        echo '</div>';
    } else {
        echo '<p>No available class keys found with duration 1.</p>';
    }
    return ob_get_clean(); // Return the buffered content
}
// Register the shortcode
add_shortcode('display_available_class_keys', 'display_available_class_keys_shortcode');
function class_checkout_shortcode_v0($atts) {
    $class_id = isset($_GET['class']) ? intval($_GET['class']) : 0;
    $is_trial_selected = isset($_GET['trial']); // Check if the trial is selected via GET

    if ($class_id === 0) {
        return 'No class specified.';
    }

    $current_user = wp_get_current_user();
    $is_logged_in = $current_user->ID > 0;

    $class = get_post($class_id);
    if (!$class || $class->post_type !== 'class') {
        return 'Invalid class.';
    }

    $prices = get_post_meta($class_id, 'price', true);
    if (!$prices || !is_array($prices)) {
        return 'Class prices not set.';
    }

    $has_free_trial = false;
    if ($is_logged_in) {
        $has_free_trial = get_user_meta($current_user->ID, 'free_trial_' . $class_id, true);
    }
    echo handle_class_checkout();
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
                if (!selectedButton.length) {
                    $('#total-price').text('0.00');
                    $('#selected-price').val('0.00');
                    return;
                }
                
                var price = selectedButton.hasClass('free-trial') ? 0 : parseFloat(selectedButton.data('price')) || 0;
                
                // Apply educational discount if edu email is detected
                if (isEduEmail($('#email').val()) && price > 0) {
                    price = Math.round((price / 2) * 100) / 100; // Educational discount: divide by 2, round to 2 decimals
                }
                
                $('#total-price').text(price.toFixed(2));
                $('#selected-price').val(price.toFixed(2)); // Set the selected price in the hidden input
                $('#educational-discount').val(isEduEmail($('#email').val()) ? '1' : '0');
                
                // Update discount indicator
                updateDiscountIndicator();
            }
            
            function isEduEmail(email) {
                return email && email.toLowerCase().includes('.edu');
            }
            
            function updateDiscountIndicator() {
                var email = $('#email').val();
                if (isEduEmail(email)) {
                    if (!$('#edu-discount-notice').length) {
                        $('#email').after('<div id="edu-discount-notice" style="color: green; font-size: 12px; margin-bottom: 10px;">✓ Educational discount applied!</div>');
                    }
                } else {
                    $('#edu-discount-notice').remove();
                }
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
            
            // Update total when email changes (for edu discount)
            $('#email').on('input blur', function() {
                updateTotal();
            });

            function togglePaymentFields(show) {
                if (show) {
                    $('.payment-container').show();
                } else {
                    $('.payment-container').hide();
                }
            }

            // Password confirmation validation
            $('#confirm_password').on('blur', function() {
                var password = $('#password').val();
                var confirmPassword = $(this).val();
                
                if (password && confirmPassword && password !== confirmPassword) {
                    alert('Passwords do not match');
                    $(this).focus();
                }
            });

            // Handle checkout button click
            $('#checkout-btn').on('click', function(e) {
                e.preventDefault();
                
                if (!$('.duration-button.selected').length) {
                    alert('Please select a duration');
                    return;
                }

                // Validate registration fields if user is not logged in
                <?php if (!$is_logged_in): ?>
                var firstName = $('#first_name').val().trim();
                var lastName = $('#last_name').val().trim();
                var email = $('#email').val().trim();
                var username = $('#username').val().trim();
                var password = $('#password').val();
                var confirmPassword = $('#confirm_password').val();
                
                if (!firstName) {
                    alert('Please enter your first name');
                    $('#first_name').focus();
                    return;
                }
                
                if (!lastName) {
                    alert('Please enter your last name');
                    $('#last_name').focus();
                    return;
                }
                
                if (!email) {
                    alert('Please enter your email');
                    $('#email').focus();
                    return;
                }
                
                if (!username) {
                    alert('Please enter a username');
                    $('#username').focus();
                    return;
                }
                
                if (!password) {
                    alert('Please enter a password');
                    $('#password').focus();
                    return;
                }
                
                if (password !== confirmPassword) {
                    alert('Passwords do not match');
                    $('#confirm_password').focus();
                    return;
                }
                
                if (password.length < 6) {
                    alert('Password must be at least 6 characters long');
                    $('#password').focus();
                    return;
                }
                <?php endif; ?>

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
            font-family: Arial, sans-serif;
        }
        .checkout-left, .checkout-right {
            width: 48%;
            padding: 20px;
            border-radius: 5px;
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
        input[type="text"], input[type="email"], input[type="password"], textarea, select {
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
        
        /* Name fields row */
        .name-row {
            display: flex;
            gap: 10px;
        }
        .name-field {
            flex: 1;
        }
        .name-field label {
            margin-bottom: 5px;
        }
        .name-field input {
            margin-bottom: 15px;
        }
        
        @media (max-width: 768px) {
            .class-checkout-container {
                flex-direction: column;
            }
            .checkout-left, .checkout-right {
                width: 100%;
                margin-bottom: 20px;
            }
            .name-row {
                flex-direction: column;
                gap: 0;
            }
        }
        /* Highlight free trial button */
        .duration-button.free-trial.selected {
            background-color: #ffeb3b;
            color: black;
            font-size: 24px;
            font-weight: 700;
        }
        
        /* Price styling with strikethrough */
        .price-display {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
        }
        
        .original-price {
            font-size: 12px;
            color: #999;
            text-decoration: line-through;
        }
        
        .discounted-price {
            font-weight: bold;
            color: white;
            font-size: 24px;
            border-radius: 4px;
            display: inline-block;
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
        
        
        <div class="checkout-right">
            <h4><?php echo $is_logged_in ? 'Billing Details' : 'Create Account'; ?></h4>
            <form id="class-checkout-form" method="post" enctype="multipart/form-data">
                <input type="hidden" id="selected-price" name="price" value="">
                <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                <input type="hidden" id="selected-duration" name="duration" value="">
                <input type="hidden" id="free-trial" name="free_trial" value="">
                <input type="hidden" id="selected-payment-method" name="payment_method" value="online">
                <input type="hidden" id="payment_reference" name="payment_reference" value="">
                <input type="hidden" id="account_name" name="account_name" value="">
                <input type="hidden" id="educational-discount" name="educational_discount" value="0">
                <input type="hidden" name="is_logged_in" value="<?php echo $is_logged_in ? '1' : '0'; ?>">

                <div class="name-row">
                    <div class="name-field">
                        <label for="first_name">First Name:</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo $is_logged_in ? esc_attr($current_user->first_name) : ''; ?>" required>
                    </div>
                    <div class="name-field">
                        <label for="last_name">Last Name:</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo $is_logged_in ? esc_attr($current_user->last_name) : ''; ?>" required>
                    </div>
                </div>

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo $is_logged_in ? esc_attr($current_user->user_email) : ''; ?>" required>
                <div style="font-size: 10px; color: #666; margin-top: -10px; margin-bottom: 15px;">
                    TIP: Use your school email to have an extra discount!
                </div>

                <?php if (!$is_logged_in): ?>
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                    
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                    
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                <?php endif; ?>
            </form>
        </div>
        <div class="checkout-left">
            <h4>Order Summary</h4>
            <div id="order-details">
                <div><strong>Class:</strong></div>
                <h4><?php echo esc_html($class->post_title); ?></h4>
                
               
                
                <?php if (!$has_free_trial) : ?>
                <div class="duration-buttons" style="grid-template-columns: unset; margin-bottom: 10px;">
                        <div class="duration-button free-trial" data-duration="0.1" data-price="0" data-trial="1" style="font-size: 24px;font-weight: 700;">
                            3 DAY FREE TRIAL
                        </div>
                </div>
                <?php endif; ?>
                
                <div class="duration-buttons">
                    <?php foreach ($prices as $duration => $price) : 
                        $original_price = round($price * 2, 2); // Original price is 2x the current price
                    ?>
                        <div class="duration-button" data-duration="<?php echo esc_attr($duration); ?>" data-price="<?php echo esc_attr($price); ?>">
                            <div class="price-display">
                                <div><?php echo esc_html($duration . ' month(s)'); ?></div>
                                <div class="original-price">₱<?php echo number_format($original_price, 2); ?></div>
                                <div class="discounted-price">₱<?php echo number_format($price, 2); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            <div id="total-amount">
                <p><strong>Total:</strong> ₱<span id="total-price">0.00</span></p>
            </div>

            <button id="checkout-btn" type="button">
                <?php echo $is_logged_in ? 'Checkout' : 'Create Account & Checkout'; ?>
            </button>
        </div>
    </div>
    <div>
         <?php 
                // Display class description if present
                $class_content = trim($class->post_content);
                if (!empty($class_content)) : ?>
                    <div class="class-description">
                        <?php echo wp_kses_post(wpautop($class_content)); ?>
                    </div>
                <?php endif; ?>
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

/**
 * Modified Class Checkout with PayMaya Integration
 */

function class_checkout_shortcode($atts) {
    $class_id = isset($_GET['class']) ? intval($_GET['class']) : 0;
    $is_trial_selected = isset($_GET['trial']);

    if ($class_id === 0) {
        return 'No class specified.';
    }

    $current_user = wp_get_current_user();
    $is_logged_in = $current_user->ID > 0;

    $class = get_post($class_id);
    if (!$class || $class->post_type !== 'class') {
        return 'Invalid class.';
    }

    $prices = get_post_meta($class_id, 'price', true);
    if (!$prices || !is_array($prices)) {
        return 'Class prices not set.';
    }

    $has_free_trial = false;
    if ($is_logged_in) {
        $has_free_trial = get_user_meta($current_user->ID, 'free_trial_' . $class_id, true);
    }
    
    echo handle_class_checkout();
    ob_start();
    wp_enqueue_script('jquery');
    
    lf_loading_screen();
    ?>
    <script>
        var ajaxurl = "/wp-admin/admin-ajax.php";
        jQuery(document).ready(function($) {
            // Set default payment method to paymaya
            $('#selected-payment-method').val('paymaya');
            
            function updateTotal() {
                var selectedButton = $('.duration-button.selected');
                if (!selectedButton.length) {
                    $('#total-price').text('0.00');
                    $('#selected-price').val('0.00');
                    return;
                }
                
                var price = selectedButton.hasClass('free-trial') ? 0 : parseFloat(selectedButton.data('price')) || 0;
                var originalPrice = price;
                
                // Apply educational discount if edu email is detected (half of already discounted price)
                if (isEduEmail($('#email').val()) && price > 0) {
                    price = Math.round((price / 2) * 100) / 100;
                }
                
                $('#total-price').text(price.toFixed(2));
                $('#selected-price').val(price.toFixed(2));
                $('#selected-original-price').val(originalPrice.toFixed(2));
                $('#educational-discount').val(isEduEmail($('#email').val()) ? '1' : '0');
                
                updateDiscountIndicator();
                updatePriceDisplays();
            }
            
            function isEduEmail(email) {
                return email && email.toLowerCase().includes('.edu');
            }
            
            function updateDiscountIndicator() {
                var email = $('#email').val();
                var selectedButton = $('.duration-button.selected');
                
                if (isEduEmail(email) && selectedButton.length && !selectedButton.hasClass('free-trial')) {
                    var originalPrice = parseFloat(selectedButton.data('price'));
                    var discountedPrice = Math.round((originalPrice / 2) * 100) / 100;
                    
                    if (!$('#edu-discount-notice').length) {
                        $('#email').after('<div id="edu-discount-notice" style="color: green; font-size: 12px; margin-bottom: 10px;">✓ Educational discount applied! Price: ₱' + originalPrice.toFixed(2) + ' → ₱' + discountedPrice.toFixed(2) + '</div>');
                    } else {
                        $('#edu-discount-notice').html('✓ Educational discount applied! Price: ₱' + originalPrice.toFixed(2) + ' → ₱' + discountedPrice.toFixed(2));
                    }
                     updatePriceDisplays();
                } else {
                    $('#edu-discount-notice').remove();
                }
            }

            function updatePriceDisplays() {
                var email = $('#email').val();
                var isEdu = isEduEmail(email);
                
                var $button = $(this);
                    var originalPrice = parseFloat($button.data('original-price'));
                    var currentPrice = parseFloat($button.data('price'));
                    var displayPrice = currentPrice;
                    
                    if (isEdu) {
                        displayPrice = Math.round((currentPrice / 2) * 100) / 100;
                    }
                    
                    // Update the displayed discounted price
                    $button.find('.discounted-price').text('₱' + displayPrice.toFixed(2));
                    
                    // Add edu indicator if edu email
                    if (isEdu && !$button.find('.edu-indicator').length) {
                        $button.find('.price-display').append('<div class="edu-indicator" style="font-size: 10px; color: #4CAF50; font-weight: normal;">EDU Discount!</div>');
                    } else if (!isEdu) {
                        $button.find('.edu-indicator').remove();
                    }
            }

            function toggleFreeTrial() {
                var email = $('#email').val();
                if (isEduEmail(email)) {
                    $('#free-trial-container').show();
                    updatePriceDisplays();
                } else {
                    $('#free-trial-container').hide();
                    // If free trial was selected and email is no longer edu, deselect it
                    if ($('.duration-button.free-trial').hasClass('selected')) {
                        $('.duration-button.free-trial').removeClass('selected');
                        $('#selected-duration').val('');
                        $('#free-trial').val('0');
                        updateTotal();
                    }
                }
            }

            $('.duration-button').on('click', function() {
                $('.duration-button').removeClass('selected');
                $(this).addClass('selected');
                $('#selected-duration').val($(this).data('duration'));

                if ($(this).hasClass('free-trial')) {
                    $('#free-trial').val($(this).data('trial'));
                } else {
                    $('#free-trial').val('0');
                }

                updateTotal();
                updateDiscountIndicator();
            });
            
            $('#email').on('input blur', function() {
                toggleFreeTrial();
                updateTotal();
            });

            // Password confirmation validation
            $('#confirm_password').on('blur', function() {
                var password = $('#password').val();
                var confirmPassword = $(this).val();
                
                if (password && confirmPassword && password !== confirmPassword) {
                    alert('Passwords do not match');
                    $(this).focus();
                }
            });

            // Handle checkout button click
            $('#checkout-btn').on('click', function(e) {
                e.preventDefault();
                
                if (!$('.duration-button.selected').length) {
                    alert('Please select a duration');
                    return;
                }

                // Validate registration fields if user is not logged in
                <?php if (!$is_logged_in): ?>
                var firstName = $('#first_name').val().trim();
                var lastName = $('#last_name').val().trim();
                var email = $('#email').val().trim();
                var username = $('#username').val().trim();
                var password = $('#password').val();
                var confirmPassword = $('#confirm_password').val();
                
                if (!firstName) {
                    alert('Please enter your first name');
                    $('#first_name').focus();
                    return;
                }
                
                if (!lastName) {
                    alert('Please enter your last name');
                    $('#last_name').focus();
                    return;
                }
                
                if (!email) {
                    alert('Please enter your email');
                    $('#email').focus();
                    return;
                }
                
                if (!username) {
                    alert('Please enter a username');
                    $('#username').focus();
                    return;
                }
                
                if (!password) {
                    alert('Please enter a password');
                    $('#password').focus();
                    return;
                }
                
                if (password !== confirmPassword) {
                    alert('Passwords do not match');
                    $('#confirm_password').focus();
                    return;
                }
                
                if (password.length < 6) {
                    alert('Password must be at least 6 characters long');
                    $('#password').focus();
                    return;
                }
                <?php endif; ?>

                // If free trial is selected, submit directly
                if ($('.duration-button.selected').hasClass('free-trial')) {
                    $('#class-checkout-form').submit();
                    return;
                }

                // For paid options, initiate PayMaya payment
                $('#checkout-btn').prop('disabled', true).text('Processing...');
                initiatePayMayaPayment();
            });

            function initiatePayMayaPayment() {
                var formData = $('#class-checkout-form').serialize();
                
                $.ajax({
                    url: ajaxurl,
                    type: 'POST',
                    data: formData + '&action=create_paymaya_checkout',
                    success: function(response) {
                        if (response.success) {
                            // Redirect to PayMaya checkout page
                            window.location.href = response.data.checkout_url;
                        } else {
                            alert('Payment initiation failed: ' + response.data.message);
                            $('#checkout-btn').prop('disabled', false).text('<?php echo $is_logged_in ? 'Checkout' : 'Create Account & Checkout'; ?>');
                        }
                    },
                    error: function() {
                        alert('An error occurred. Please try again.');
                        $('#checkout-btn').prop('disabled', false).text('<?php echo $is_logged_in ? 'Checkout' : 'Create Account & Checkout'; ?>');
                    }
                });
            }

            <?php if ($is_trial_selected): ?>
                $('.duration-button.free-trial').click();
            <?php endif; ?>
        });
    </script>
    <style>
        .class-checkout-container {
            display: flex;
            justify-content: space-between;
            max-width: 1200px;
            margin: 0 auto;
            font-family: Arial, sans-serif;
        }
        .checkout-left, .checkout-right {
            width: 48%;
            padding: 20px;
            border-radius: 5px;
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
            transition: all 0.3s;
        }
        .duration-button:hover {
            border-color: #999;
        }
        .duration-button.selected {
            background-color: var(--ast-global-color-0);
            color: white;
        }
        h2, h4 {
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
        input[type="text"], input[type="email"], input[type="password"], textarea, select {
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
        .name-row {
            display: flex;
            gap: 10px;
        }
        .name-field {
            flex: 1;
        }
        .name-field label {
            margin-bottom: 5px;
        }
        .name-field input {
            margin-bottom: 15px;
        }
        .duration-button.free-trial.selected {
            background-color: #ffeb3b;
            color: black;
            font-size: 24px;
            font-weight: 700;
        }
        .price-display {
            display: flex;
            flex-direction: column;
            align-items: center;
            gap: 2px;
        }
        .original-price {
            font-size: 12px;
            color: #999;
            text-decoration: line-through;
        }
        .discounted-price {
            font-weight: bold;
            color: white;
            font-size: 24px;
            border-radius: 4px;
            display: inline-block;
        }
        .duration-button.selected .discounted-price {
            color: white;
        }
        #checkout-btn {
            background-color: var(--ast-global-color-0);
            color: white;
            padding: 15px 30px;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            width: 100%;
            font-size: 16px;
            font-weight: bold;
            transition: opacity 0.3s;
        }
        #checkout-btn:hover {
            opacity: 0.9;
        }
        #checkout-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
        }
        .paymaya-badge {
            text-align: center;
            margin-top: 15px;
            padding-top: 15px;
            border-top: 1px solid #eee;
        }
        .paymaya-badge img {
            height: 25px;
            vertical-align: middle;
            margin-right: 8px;
        }
        .paymaya-badge span {
            font-size: 12px;
            color: #666;
        }
        @media (max-width: 768px) {
            .class-checkout-container {
                flex-direction: column;
            }
            .checkout-left, .checkout-right {
                width: 100%;
                margin-bottom: 20px;
            }
            .name-row {
                flex-direction: column;
                gap: 0;
            }
        }
    </style>
    
    <div class="class-checkout-container">
        <div class="checkout-right">
            <h4><?php echo $is_logged_in ? 'Billing Details' : 'Create Account'; ?></h4>
            <form id="class-checkout-form" method="post">
                <input type="hidden" id="selected-price" name="price" value="">
                <input type="hidden" id="selected-original-price" name="original_price" value="">
                <input type="hidden" name="class_id" value="<?php echo $class_id; ?>">
                <input type="hidden" id="selected-duration" name="duration" value="">
                <input type="hidden" id="free-trial" name="free_trial" value="">
                <input type="hidden" id="selected-payment-method" name="payment_method" value="paymaya">
                <input type="hidden" id="educational-discount" name="educational_discount" value="0">
                <input type="hidden" name="is_logged_in" value="<?php echo $is_logged_in ? '1' : '0'; ?>">

                <div class="name-row">
                    <div class="name-field">
                        <label for="first_name">First Name:</label>
                        <input type="text" id="first_name" name="first_name" value="<?php echo $is_logged_in ? esc_attr($current_user->first_name) : ''; ?>" required>
                    </div>
                    <div class="name-field">
                        <label for="last_name">Last Name:</label>
                        <input type="text" id="last_name" name="last_name" value="<?php echo $is_logged_in ? esc_attr($current_user->last_name) : ''; ?>" required>
                    </div>
                </div>

                <label for="email">Email:</label>
                <input type="email" id="email" name="email" value="<?php echo $is_logged_in ? esc_attr($current_user->user_email) : ''; ?>" required>
                <div style="font-size: 10px; color: #666; margin-top: -10px; margin-bottom: 15px;">
                    TIP: Use your school email (.edu) to get 50% discount OR 30 days free trial(One time use only)!
                </div>

                <?php if (!$is_logged_in): ?>
                    <label for="username">Username:</label>
                    <input type="text" id="username" name="username" required>
                    
                    <label for="password">Password:</label>
                    <input type="password" id="password" name="password" required>
                    
                    <label for="confirm_password">Confirm Password:</label>
                    <input type="password" id="confirm_password" name="confirm_password" required>
                <?php endif; ?>
            </form>
        </div>
        
        <div class="checkout-left">
            <h4>Order Summary</h4>
            <div id="order-details">
                <div><strong>Class:</strong></div>
                <h4><?php echo esc_html($class->post_title); ?></h4>
                
                <?php if (!$has_free_trial) : ?>
                <div class="duration-buttons" id="free-trial-container" style="grid-template-columns: unset; margin-bottom: 10px; display: none;">
                    <div class="duration-button free-trial" data-duration="1" data-price="0" data-trial="1" style="font-size: 24px;font-weight: 700;">
                        30 DAY FREE TRIAL 
                    </div>
                </div>
                <?php endif; ?>
                
                <div class="duration-buttons">
                    <?php foreach ($prices as $duration => $price) : 
                        $original_price = round($price * 2, 2);
                    ?>
                        <div class="duration-button" data-duration="<?php echo esc_attr($duration); ?>" data-price="<?php echo esc_attr($price); ?>" data-original-price="<?php echo esc_attr($original_price); ?>">
                            <div class="price-display">
                                <div><?php echo esc_html($duration . ' month(s)'); ?></div>
                                <div class="original-price">₱<?php echo number_format($original_price, 2); ?></div>
                                <div class="discounted-price">₱<?php echo number_format($price, 2); ?></div>
                            </div>
                        </div>
                    <?php endforeach; ?>
                </div>
            </div>
            
            <div id="total-amount">
                <p><strong>Total:</strong> ₱<span id="total-price">0.00</span></p>
            </div>

            <button id="checkout-btn" type="button">
                <?php echo $is_logged_in ? 'Checkout' : 'Create Account & Checkout'; ?>
            </button>
            
            <div class="paymaya-badge">
                <img src="https://cdn.paymaya.com/web/v2/assets/images/logo_colored.svg" alt="PayMaya" onerror="this.style.display='none'">
                <span></span>
                <span>Secure payment powered by PayMaya. Process under Bentamo - BNTM Technologies Inc.</span>
            </div>
        </div>
    </div>
    
    <div>
        <?php 
        $class_content = trim($class->post_content);
        if (!empty($class_content)) : ?>
            <div class="class-description">
                <?php echo wp_kses_post(wpautop($class_content)); ?>
            </div>
        <?php endif; ?>
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

        $is_logged_in = isset($_POST['is_logged_in']) && $_POST['is_logged_in'] == '1';
        $user_id = 0;

        // Handle user creation or validation
        if (!$is_logged_in) {
            // Create new user
            $username = sanitize_user($_POST['username']);
            $password = $_POST['password'];
            $confirm_password = $_POST['confirm_password'];
            $email = sanitize_email($_POST['email']);

            // Validate registration fields
            if (empty($username)) {
                $errors[] = "Username is required.";
            } elseif (username_exists($username)) {
                $errors[] = "Username already exists. Please choose a different username.";
            }

            if (empty($password)) {
                $errors[] = "Password is required.";
            } elseif (strlen($password) < 6) {
                $errors[] = "Password must be at least 6 characters long.";
            } elseif ($password !== $confirm_password) {
                $errors[] = "Passwords do not match.";
            }

            if (empty($email)) {
                $errors[] = "Email is required.";
            } elseif (!is_email($email)) {
                $errors[] = "Please enter a valid email address.";
            } elseif (email_exists($email)) {
                $errors[] = "Email already exists. Please use a different email address.";
            }

            // Create user if no errors
            if (empty($errors)) {
                $user_id = wp_create_user($username, $password, $email);
                
                if (is_wp_error($user_id)) {
                    $errors[] = "Failed to create user account: " . $user_id->get_error_message();
                } else {
                    // Set user's display name using separate first and last name
                    $first_name = sanitize_text_field($_POST['first_name']);
                    $last_name = sanitize_text_field($_POST['last_name']);
                    $display_name = trim($first_name . ' ' . $last_name);
                    
                    wp_update_user(array(
                        'ID' => $user_id,
                        'first_name' => $first_name,
                        'last_name' => $last_name,
                        'display_name' => $display_name
                    ));

                    // Log in the new user
                    wp_set_current_user($user_id);
                    wp_set_auth_cookie($user_id);
                }
            }
        } else {
            // Use current logged-in user
            $user_id = get_current_user_id();
            if (!$user_id) {
                $errors[] = "You must be logged in to complete this transaction.";
            }
        }

        // Get name from separate fields
        $first_name = sanitize_text_field($_POST['first_name']);
        $last_name = sanitize_text_field($_POST['last_name']);
        $name = trim($first_name . ' ' . $last_name);
        
        if (empty($first_name)) {
            $errors[] = "First name is required.";
        }
        if (empty($last_name)) {
            $errors[] = "Last name is required.";
        }

        $email = sanitize_email($_POST['email']);
        if (empty($email)) {
            $errors[] = "Email is required.";
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
            // Validate that the email is edu for free trial
            $email = sanitize_email($_POST['email']);
            if (strpos(strtolower($email), '.edu') === false) {
                $errors[] = "Free trial is only available for educational (.edu) email addresses.";
            } else {
                update_user_meta($user_id, 'free_trial_' . $class_id, true);
                $duration = 1; // 30 days (1 month)
            }
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
            'post_status'   => 'pending',
            'post_author'   => $user_id,
        ));

        if ($transaction_id) {
            // Add transaction details as post meta
            update_post_meta($transaction_id, 'class_id', $class_id);
            update_post_meta($transaction_id, 'duration', $duration);
            update_post_meta($transaction_id, 'user_id', $user_id);
            update_post_meta($transaction_id, 'email', $email);
            update_post_meta($transaction_id, 'name', $name);
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
            $account_created_message = !$is_logged_in ? "<p><strong>Account Created:</strong> Your account has been created successfully. You are now logged in.</p>" : "";
            $edu_discount_message = (isset($_POST['educational_discount']) && $_POST['educational_discount'] == '1') ? " (Educational Discount Applied - 50% OFF)" : "";
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
            $account_created_message
            <p><strong>Payment Method:</strong> $payment_method</p>
            <p><strong>Payment Reference:</strong> $payment_reference</p>
            <p><strong>Account Name:</strong> $account_name</p>
            <p><strong>Price:</strong> ₱{$_POST['price']}{$edu_discount_message}</p>
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
                // Email failed to sendla
                $errors[] = "Failed to send message";
            }
             wp_redirect(home_url('/thank-you?ref=' . $ref_number));
        } else {
            $error_message = implode(' ', $errors);
            wp_redirect(add_query_arg('error', urlencode($error_message), wp_get_referer()));
        }
        exit;
    }
}
//add_action('init', 'handle_class_checkout');


function verify_class_key() {
    $class_id = intval($_POST['class_id']);
    $key = sanitize_text_field($_POST['key']);

    $class_keys = get_term_meta(305, 'class_keys', true) ?: array();

    if (isset($class_keys[$key]) && 
        $class_keys[$key]['class'] == $class_id && 
        $class_keys[$key]['status'] == 'Unused') {
        wp_send_json_success(array(
            'message' => 'Valid key. Total set to ₱0.00',
            'duration' => $class_keys[$key]['duration']
        ));
    } else {
        wp_send_json_error(array('message' => 'Invalid or used key.'));
    }
}
add_action('wp_ajax_verify_class_key', 'verify_class_key');
add_action('wp_ajax_nopriv_verify_class_key', 'verify_class_key');
function display_transaction_receipt() {
    if (!isset($_GET['ref'])) {
        return "<p>Transaction reference not found.</p>";
    }
    
    $ref_number = sanitize_text_field($_GET['ref']);
    $provided_key = isset($_GET['key']) ? sanitize_text_field($_GET['key']) : '';
    
    $transaction = get_posts(array(
        'post_type' => 'transaction',
        'title' => $ref_number,
        'posts_per_page' => 1,
        'post_status' => array('pending', 'publish'),
    ));
    
    if (empty($transaction)) {
        return "<p>Transaction not found.</p>";
    }
    
    $transaction_id = $transaction[0]->ID;
    
    // Retrieve transaction meta
    $class_id = get_post_meta($transaction_id, 'class_id', true);
    $duration = get_post_meta($transaction_id, 'duration', true);
    $name = get_post_meta($transaction_id, 'name', true);
    $email = get_post_meta($transaction_id, 'email', true);
    $address = get_post_meta($transaction_id, 'address', true);
    $payment_method = get_post_meta($transaction_id, 'payment_method', true);
    $price = get_post_meta($transaction_id, 'price', true);
    $user_id = get_post_meta($transaction_id, 'user_id', true);
    $stored_key = get_post_meta($transaction_id, 'key', true);
    $used_key = get_post_meta($transaction_id, 'used_key', true);
    $payment_status = get_post_meta($transaction_id, 'payment_status', true);
    
    // Check if key parameter matches stored key and payment hasn't been processed yet
    if (!empty($provided_key) && $provided_key === $stored_key && $payment_status !== 'completed' && empty($used_key)) {
        // Generate and create class keys
        $class_keys = get_term_meta(305, 'class_keys', true) ?: array();
        $new_used_key = generate_unique_key($class_keys);
        
        $class_keys[$new_used_key] = array(
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
        
        // Update transaction
        update_post_meta($transaction_id, 'used_key', $new_used_key);
        update_post_meta($transaction_id, 'payment_status', 'completed');
        update_post_meta($transaction_id, 'payment_date', current_time('mysql'));
        
        wp_update_post(array(
            'ID' => $transaction_id,
            'post_status' => 'publish'
        ));
        
        // Update the used_key variable for display
        $used_key = $new_used_key;
        $payment_status = 'completed';
        
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
        <p><strong>Class Key:</strong> $new_used_key</p>
        <p>You can now access your class. Start learning today!</p>
        <a href='" . home_url() . "' style='display: inline-block; padding: 10px 20px; background-color: #4CAF50; color: white; text-decoration: none; border-radius: 5px; margin-top: 15px;'>Go to Dashboard</a>
        </body>
        </html>";
        
        $headers = array('Content-Type: text/html; charset=UTF-8');
        wp_mail($to, $subject, $message, $headers);
    }
    
    // Decide message based on payment status
    if ($payment_status === 'completed' && !empty($used_key)) {
        $enrollment_message = "✅ Your enrollment is successful, and your payment has been confirmed. You are officially enrolled in the class and can start answering right away!";
    } else {
        $enrollment_message = "⚠️ Your payment is being processed. Please wait while we confirm your payment. Once confirmed, you will receive your class key and can start learning!";
    }
    
    // Display receipt in a modern UI/UX style
    ob_start();
    ?>
    <div class="receipt-container" style="max-width: 600px; margin: 0 auto; padding: 20px; border: 1px solid #ccc; border-radius: 10px;">
        <h4 style="text-align: center;">Transaction Receipt</h4>
        <hr style="border: none; height: 1px; background-color: #ccc;">
        <p><strong>Transaction Reference:</strong> <?php echo esc_html($ref_number); ?></p>
        <p><strong>Class:</strong> <?php echo get_the_title($class_id); ?></p>
        <p><strong>Duration:</strong> <?php echo esc_html($duration); ?> months</p>
        <p><strong>Name:</strong> <?php echo esc_html($name); ?></p>
        <p><strong>Email:</strong> <?php echo esc_html($email); ?></p>
        <?php if (!empty($address)): ?>
        <p><strong>Address:</strong> <?php echo esc_html($address); ?></p>
        <?php endif; ?>
        <p><strong>Payment Method:</strong> <?php echo esc_html($payment_method); ?></p>
        <p><strong>Price:</strong> ₱<?php echo number_format($price, 2); ?></p>
        <?php if (!empty($used_key)): ?>
        <p><strong>Class Key:</strong> <?php echo esc_html($used_key); ?></p>
        <?php endif; ?>
        <div style="margin-top: 20px; padding: 15px; background-color: <?php echo $payment_status === 'completed' ? '#e0f7fa' : '#fff3cd'; ?>; border-left: 4px solid <?php echo $payment_status === 'completed' ? '#00acc1' : '#ffc107'; ?>;">
            <p style="margin: 0; color: <?php echo $payment_status === 'completed' ? '#007c91' : '#856404'; ?>;">
                <?php echo $enrollment_message; ?>
            </p>
        </div>
        <?php if ($payment_status === 'completed'): ?>
        <div style="text-align: center; margin-top: 20px;">
            <a href="<?php echo home_url('profile?class_id='.$class_id); ?>" class="button" style="color: #fff; text-decoration: none; border-radius: 5px;">Start Looksfam</a>
        </div>
        <?php endif; ?>
    </div>
    <style>
        .receipt-container p {
            margin-bottom: 10px;
        }
    </style>
    <?php
    return ob_get_clean();
}

add_shortcode('transaction_receipt', 'display_transaction_receipt');



function add_transactions_submenu() {
    add_submenu_page(
        'edit.php?post_type=transaction',
        'All Transactions',
        'All Transactions',
        'manage_options',
        'all-transactions',
        'display_transactions_page'
    );
}
add_action('admin_menu', 'add_transactions_submenu');
function display_transactions_page() {
    ?>
    <div class="wrap">
        <h1>All Transactions</h1>
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th>Transaction ID</th>
                    <th>User</th>
                    <th>Class</th>
                    <th>Duration</th>
                    <th>Price</th>
                    <th>Payment Method</th>
                    <th>Account Name</th>
                    <th>Proof of Payment</th>
                    <th>Class Key</th>
                    <th>Key Status</th>
                    <th>Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php
                $transactions = get_posts(array('post_type' => 'transaction', 'post_status' => array('pending', 'publish'), 'numberposts' => -1));
                foreach ($transactions as $transaction) {
                    $user_id = get_post_meta($transaction->ID, 'user_id', true);
                    $user = get_userdata($user_id);
                    $class_id = get_post_meta($transaction->ID, 'class_id', true);
                    $class = get_post($class_id);
                    $duration = get_post_meta($transaction->ID, 'duration', true);
                    $name = get_post_meta($transaction->ID, 'name', true);
                    $price = get_post_meta($transaction->ID, 'price', true);
                    $payment_method = get_post_meta($transaction->ID, 'payment_method', true);
                    $account_name = get_post_meta($transaction->ID, 'account_name', true);
                    $proof_of_payment = get_post_meta($transaction->ID, 'proof_of_payment', true);
                    $class_key = get_post_meta($transaction->ID, 'used_key', true);

                    // Fetch class keys from term meta (assuming term_id is 305)
                    $class_keys = get_term_meta(305, 'class_keys', true) ?: array();
                    $key_status = isset($class_keys[$class_key]) ? $class_keys[$class_key]['status'] : 'N/A';
                    ?>
                    <tr>
                        <td><?php echo $transaction->ID; ?></td>
                        <td><?php echo $name; ?></td>
                        <td><?php echo $class ? $class->post_title : 'N/A'; ?></td>
                        <td><?php echo $duration; ?></td>
                        <td><?php echo $price; ?></td>
                        <td><?php echo $payment_method; ?></td>
                        <td><?php echo $account_name; ?></td>
                        <td>
                            <?php if ($proof_of_payment): ?>
                                <a href="#" class="show-proof" data-proof="<?php echo esc_url($proof_of_payment); ?>">View Proof</a>
                            <?php else: ?>
                                N/A
                            <?php endif; ?>
                        </td>
                        <td><?php echo $class_key; ?></td>
                        <td><?php echo $key_status; ?></td>
                        <td>
                            <?php if ($class_key): ?>
                                <button class="expire-key" data-key="<?php echo $class_key; ?>">Expire Key</button>
                            <?php endif; ?>
                            <button class="delete-transaction" data-transaction-id="<?php echo $transaction->ID; ?>">Delete Transaction</button>
                        </td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
    </div>

    <div id="proof-modal" class="modal">
        <div class="modal-content">
            <span class="close">&times;</span>
            <img id="proof-image" src="" alt="Proof of Payment">
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        var modal = $('#proof-modal');
        var img = $('#proof-image');
        var span = $('.close');

        $('.show-proof').click(function(e) {
            e.preventDefault();
            var proofUrl = $(this).data('proof');
            img.attr('src', proofUrl);
            modal.show();
        });

        span.click(function() {
            modal.hide();
        });

        $(window).click(function(e) {
            if ($(e.target).is(modal)) {
                modal.hide();
            }
        });

        // Expire class key action
        $('.expire-key').click(function() {
            var key = $(this).data('key');
            if (confirm('Are you sure you want to expire this key?')) {
                $.post(ajaxurl, {
                    action: 'expire_class_key',
                    key: key
                }, function(response) {
                    if (response.success) {
                        alert('Key expired successfully');
                        location.reload();
                    } else {
                        alert('Failed to expire key');
                    }
                });
            }
        });

        // Delete transaction action
        $('.delete-transaction').click(function() {
            var transaction_id = $(this).data('transaction-id');
            if (confirm('Are you sure you want to delete this transaction?')) {
                $.post(ajaxurl, {
                    action: 'delete_transaction',
                    transaction_id: transaction_id
                }, function(response) {
                    if (response.success) {
                        alert('Transaction deleted successfully');
                        location.reload();
                    } else {
                        alert('Failed to delete transaction');
                    }
                });
            }
        });
    });
    </script>

    <style>
    .modal {
        display: none;
        position: fixed;
        z-index: 1;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.4);
    }

    .modal-content {
        background-color: #fefefe;
        margin: 15% auto;
        padding: 20px;
        border: 1px solid #888;
        width: 80%;
        max-width: 600px;
    }

    .close {
        color: #aaa;
        float: right;
        font-size: 28px;
        font-weight: bold;
    }

    .close:hover,
    .close:focus {
        color: black;
        text-decoration: none;
        cursor: pointer;
    }

    #proof-image {
        max-width: 100%;
        height: auto;
    }
    </style>
    <?php
}

// Function to expire class key
function expire_class_key() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }

    $key = sanitize_text_field($_POST['key']);
    $class_keys = get_term_meta(305, 'class_keys', true) ?: array();

    if (isset($class_keys[$key])) {
        $class_keys[$key]['status'] = 'Revoke';
        update_term_meta(305, 'class_keys', $class_keys);
        wp_send_json_success();
    } else {
        wp_send_json_error('Key not found');
    }
}
add_action('wp_ajax_expire_class_key', 'expire_class_key');

// Function to delete a transaction
function delete_transaction() {
    if (!current_user_can('manage_options')) {
        wp_send_json_error('Unauthorized');
        return;
    }

    $transaction_id = intval($_POST['transaction_id']);
    
    if (wp_delete_post($transaction_id, true)) {
        wp_send_json_success();
    } else {
        wp_send_json_error('Failed to delete transaction');
    }
}
add_action('wp_ajax_delete_transaction', 'delete_transaction');
?>