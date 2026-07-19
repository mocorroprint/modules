<?php
/*
Plugin Name: Exam Pages Plugin
Description: Create and manage exams with questions, hierarchical categorization, and question categories.
Version: 1.0
Author: Technomad Technologies
*/

if (!defined('ABSPATH')) {
    exit;
}

// Load plugin class files.
require_once 'includes/class-wordpress-plugin-template.php';
require_once 'includes/class-wordpress-plugin-template-settings.php';

// Load plugin libraries.
require_once 'includes/lib/class-wordpress-plugin-template-admin-api.php';
require_once 'includes/lib/class-wordpress-plugin-template-post-type.php';
require_once 'includes/lib/class-wordpress-plugin-template-taxonomy.php';

require_once 'includes/class-seo.php';
require_once 'includes/class-payment.php';

require_once 'includes/class-post.php';
require_once 'includes/class-admin-class.php';
require_once 'includes/class-admin-exam.php';
require_once 'includes/class-admin-enroll.php';
require_once 'includes/class-admin-question.php';

require_once 'includes/class-display-activity.php'; 
require_once 'includes/class-display-confirmation.php'; 
require_once 'includes/class-display-exam.php'; 
require_once 'includes/class-display-exercise.php'; 
require_once 'includes/class-display-review.php'; 
require_once 'includes/class-display-user.php'; 
require_once 'includes/class-display-functions.php'; 


/**
 * Returns the main instance of WordPress_Plugin_Template to prevent the need to use globals.
 *
 * @since  1.0.0
 * @return object WordPress_Plugin_Template
 */
function wordpress_plugin_template() {
    $instance = WordPress_Plugin_Template::instance(__FILE__, '1.0.0');

    if (is_null($instance->settings)) {
        $instance->settings = WordPress_Plugin_Template_Settings::instance($instance);
    }

    return $instance;
}

wordpress_plugin_template();


//-------------------------
function stop_all_wp_queries() {
    global $wp_filter;
    
    // Nuclear option: Clear ALL wp_head and wp_footer hooks
    unset($wp_filter['wp_head']);
    unset($wp_filter['wp_footer']);
    unset($wp_filter['wp_print_styles']);
    unset($wp_filter['wp_print_scripts']);
    unset($wp_filter['wp_enqueue_scripts']);
    
    // Block all wp_options queries from plugins
    add_filter('pre_option_', '__return_false', 9999);
    
    // Block postmeta updates
    add_filter('update_post_metadata', '__return_null', 9999, 5);
    add_filter('add_post_metadata', '__return_null', 9999, 5);
    
    // Block transients
    add_filter('pre_transient_', '__return_false', 9999);
    add_filter('pre_site_transient_', '__return_false', 9999);
}

/**
 * Apply to specific page - Add this to functions.php

add_action('template_redirect', function() {
    if (is_page('profile')) {
        stop_all_wp_queries();
    }
}, 1); */

function hide_wp_login_pages() {
    global $pagenow;
    
    // Check if we're on login or register page
    if ($pagenow == 'wp-login.php') {
        $action = isset($_GET['action']) ? $_GET['action'] : '';
        
        // Allow specific actions like logout, lostpassword if needed
        $allowed_actions = array('logout', 'rp', 'resetpass');
        
        // If it's not an allowed action and user is not logged in, redirect
        if (!in_array($action, $allowed_actions) && !is_user_logged_in()) {
            wp_redirect(home_url('/404'));
            exit();
        }
    }
}
add_action('init', 'hide_wp_login_pages');

function get_all_user_ids() {
    $user_ids = get_users(array(
        'fields' => 'ID',
    ));

    return $user_ids;
}



// Check if the current user is enrolled in any of the associated classes
function is_user_enrolled_in_any_class($associated_classes) {
    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;

    foreach ($associated_classes as $class_id) {
        $enrolled_students = get_post_meta($class_id, 'enrolled_students', true);
        if (in_array($user_id, $enrolled_students)) {
            return true; // User is enrolled in this class
        }
    }

    return false; // User is not enrolled in any of the associated classes
}



function calculate_average_percentage_score($exam_id) {
    // Get all user IDs
    $user_ids = get_all_user_ids();

    // Initialize variables for total percentage score and the number of users
    $totalPercentageScore = 0;
    $userCount = 0;

    // Loop through each user
    foreach ($user_ids as $user_id) {
        // Calculate the percentage score for the current user
        $percentageScore = calculatePercentageScore($exam_id, $user_id);

        // Check if the percentage score is not null (user has taken the exam)
        if ($percentageScore !== 0) {
            // Add the percentage score to the total
            $totalPercentageScore += $percentageScore;

            // Increment the user count
            $userCount++;
        }else{
            
            
        }
    }

    // Calculate the average percentage score
    $averagePercentageScore = $userCount > 0 ? round(($totalPercentageScore / $userCount), 2) : 0;
    //$averagePercentageScore = $userCount > 0 ? $totalPercentageScore / $userCount : 0;
     // Limit to 2 decimals
   // $averagePercentageScore = number_format($averagePercentageScore, 2);

    return $averagePercentageScore;
}

function calculateLooksfamacc($exam_id, $user_id) {
    $prevExamQuery = get_post_meta($exam_id, 'exam_results', true);
    $selectedQuestions = get_post_meta($exam_id, 'selected_questions', true);

    $highestCorrectCount = 0;
    $sessionCorrectCount = 0;
    $sessionCount = 0;
    $latestSessionId = '';

    foreach ($prevExamQuery as $result) {
        if ($result['user_id'] == $user_id) {
            $correctCount = $result['is_correct'];
            $sessionCorrectCount += $correctCount;
            
            
            $sessionId = $result['session_id'];

            if ($sessionId === $latestSessionId) {
                $sessionCount = $sessionCount;
            } else {
                $latestSessionId = $sessionId;
               $sessionCount ++;
            }
            
            

            if ($sessionCorrectCount > $highestCorrectCount) {
                $highestCorrectCount = $sessionCorrectCount;
                
            }
        }
    }

   // $totalCount = (count($selectedQuestions)*($sessionCount+$sessionCount));
   // $percentageScore = ($totalCount > 0) && ($highestCorrectCount !==0) ? round((($highestCorrectCount+(count($selectedQuestions)*$sessionCount)) / $totalCount) * 100, 2) : 0;
    
    

    $totalCount = (count($selectedQuestions)*($sessionCount+1));
    $percentageScore = ($totalCount > 0) && ($highestCorrectCount !==0) ? round((($highestCorrectCount+count($selectedQuestions)) / $totalCount) * 100, 2) : 0;

    return $percentageScore;
} 

function displayquestionstat($exam_id, $user_id) {
    $exam_results = get_post_meta($exam_id, 'question_results', true);
    $selected_questions = get_post_meta($exam_id, 'selected_questions', true);
    
    ob_start();
    ?>
    <div class="stats-line">
        <div class="stat-con" style="flex-grow: 1; text-align: left;">Question</div>
        <div class="stat-con" style="text-align: center;"> Accuracy Rating</div>
        <div class="stat-con" style="flex-grow: 1; text-align: right;">Cumulative Rating</div>
    </div>
    
    <div class="stats-container" style="max-height: 50vh; overflow-y: auto;">
    <?php
    foreach ($selected_questions as $question_id) {
        $question = get_post($question_id);
        $question_title = esc_html($question->post_title);
        $question_ids = $question->ID;
        $question_post = get_post_meta($question_ids, 'question_results', true);
        $displaylook = questionstatlooksfam($question_ids, $user_id);
        $displayoveralllook = questionoveralllooksfam($question_ids, $user_id);
        ?>
        <div class="stats-line">
            <div class="stat-con" style="flex-grow: 1; text-align: left;"><?php echo $question_title; ?></div>
            <div class="stat-con" style="text-align: center;color: #58c2f7;"><?php echo $displaylook; ?>%</div>
            <div class="stat-con" style="flex-grow: 1; text-align: right;"><?php echo $displayoveralllook; ?>%</div>
        </div>
        <?php
    }
    ?>
    </div>
    <?php
    return ob_get_clean();
}


function user_displayquestionstat($exam_id, $user_id) {
    $selected_questions = get_post_meta($exam_id, 'selected_questions', true); // make this selected from user
    
    ob_start();
    ?>
    <div class="stats-line">
        <div class="stat-con" style="flex-grow: 1; text-align: left;">Question</div>
        <div class="stat-con" style="text-align: center;"> Accuracy Rating</div>
        <div class="stat-con" style="flex-grow: 1; text-align: right;">Cumulative Rating</div>
    </div>
    
    <div class="stats-container" style="max-height: 50vh; overflow-y: auto;">
    <?php
    // Collect questions and their stats
            foreach ($selected_questions as $question_id) {
                
                // Generate looksfam stats
                $displaylook = questionstatlooksfam($question_ids, $user_id);
                $displayoveralllook = questionoveralllooksfam($question_ids, $user_id);
                
                // Collect question data
                $questions_to_display[] = [
                    'question_id' => $question_id,
                    'title' => esc_html($question->post_title),
                    'displaylook' => $displaylook,
                    'displayoveralllook' => $displayoveralllook
                ];
            }
            
            // Sort questions by displaylook in descending order
            usort($questions_to_display, function($a, $b) {
                return $b['displaylook'] - $a['displaylook'];
            });
            
            // Display sorted questions
            foreach ($selected_questions as $question_id) {
                ?>
                <div class="stats-line" data-id="<?php echo $question['question_id']?>">
                    <div class="stat-con" style="flex-grow: 1; text-align: left;"><?php echo $question['title']; ?></div>
                    <div class="stat-con" style="text-align: center;color: #58c2f7;"><?php echo $question['displaylook']; ?>%</div>
                    <div class="stat-con" style="flex-grow: 1; text-align: right;"><?php echo $question['displayoveralllook']; ?>%</div>
                </div>
                <?php
            }
  
    ?>
    </div>
    <?php
    return ob_get_clean();
}
function questionstatlooksfam_batch($question_ids, $user_id, $class_id = null) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'exam_answers';
    
    if (empty($question_ids)) return [];
    
    // Single query to get all data for all questions at once
    $placeholders = implode(',', array_fill(0, count($question_ids), '%d'));
    $query_params = $question_ids;
    
    $query = "SELECT question_id, user_id, is_correct, timestamp, session_id 
              FROM $table_name 
              WHERE question_id IN ($placeholders)";
    
    // Optionally filter by class_id to reduce data
    if ($class_id) {
        $query .= " AND class_id = %d";
        $query_params[] = $class_id;
    }
    
    $query .= " ORDER BY question_id, timestamp ASC";
    
    $all_results = $wpdb->get_results(
        $wpdb->prepare($query, $query_params),
        ARRAY_A
    );
    
    if (empty($all_results)) {
        return array_fill_keys($question_ids, 0);
    }
    
    // Group by question_id
    $grouped = [];
    foreach ($all_results as $result) {
        $grouped[$result['question_id']][] = $result;
    }
    
    $scores = [];
    $current_time = current_time('timestamp');
    
    // Process each question
    foreach ($question_ids as $question_id) {
        $results = $grouped[$question_id] ?? [];
        
        if (empty($results)) {
            $scores[$question_id] = 0;
            continue;
        }
        
        // Initialize stats
        $stats = [
            'user' => [
                'correct' => 0,
                'total' => 0,
                'sessions' => [],
                'first_time' => $current_time,
                'last_time' => 0
            ],
            'others' => [
                'correct' => 0,
                'total' => 0,
                'sessions' => []
            ],
            'latest_time' => 0
        ];
        
        // Single pass through results
        foreach ($results as $result) {
            $time = strtotime($result['timestamp']);
            $stats['latest_time'] = max($stats['latest_time'], $time);
            
            if ($result['user_id'] == $user_id) {
                $stats['user']['total']++;
                $stats['user']['correct'] += $result['is_correct'];
                $stats['user']['sessions'][$result['session_id']] = true;
                $stats['user']['first_time'] = min($stats['user']['first_time'], $time);
                $stats['user']['last_time'] = max($stats['user']['last_time'], $time);
            } else {
                $stats['others']['total']++;
                $stats['others']['correct'] += $result['is_correct'];
                $stats['others']['sessions'][$result['session_id']] = true;
            }
        }
        
        // Calculate metrics
        $user_session_count = count($stats['user']['sessions']);
        $others_session_count = count($stats['others']['sessions']);
        
        $avg_others_correct_rate = $stats['others']['total'] > 0 
            ? $stats['others']['correct'] / $stats['others']['total'] 
            : 0;
        
        $avg_others_session_count = $stats['others']['total'] > 0 
            ? $others_session_count / $stats['others']['total'] 
            : 1;
        
        $user_correct_rate = $stats['user']['total'] > 0 
            ? $stats['user']['correct'] / $stats['user']['total'] 
            : 0;
        
        $relative_correct_rate = $avg_others_correct_rate > 0 
            ? $user_correct_rate / $avg_others_correct_rate 
            : 1;
        
        $session_efficiency = $user_session_count > 0 
            ? $stats['user']['correct'] / $user_session_count 
            : 0;
        
        $avg_other_session_efficiency = $avg_others_session_count > 0 
            ? $avg_others_correct_rate / $avg_others_session_count 
            : 0;
        
        $relative_session_efficiency = $avg_other_session_efficiency > 0 
            ? $session_efficiency / $avg_other_session_efficiency 
            : 1;
        
        // Retention
        $retention_span = max(1, ($stats['user']['last_time'] - $stats['user']['first_time']) / 86400);
        $retention_factor = min(1, log($retention_span + 1) / log(31));
        
        // Decay
        $time_since_last = max(0, ($current_time - $stats['latest_time']) / 86400);
        $decay_factor = exp(-0.1 * $time_since_last);
        
        // Final score
        $base_score = $relative_correct_rate * $relative_session_efficiency * 100;
        $percentage_score = $base_score * (0.7 + 0.3 * $retention_factor) * $decay_factor;
        
        // Session count adjustment
        $percentage_score *= ($user_session_count < $avg_others_session_count) ? 1.2 : 0.8;
        
        $scores[$question_id] = round(min(max($percentage_score, 0), 100), 2);
    }
    
    return $scores;
}


/**
 * Original single-question version (kept for backward compatibility)
 * Now internally uses the batch version for consistency
 */
function questionstatlooksfam($question_id, $user_id, $mode = "looksfam") {
    if ($mode === "accuracy") {
        // Accuracy mode still needs individual query
        global $wpdb;
        $table_name = $wpdb->prefix . 'exam_answers';
        
        $question_results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT is_correct 
                 FROM $table_name 
                 WHERE question_id = %d AND user_id = %d",
                $question_id,
                $user_id
            ),
            ARRAY_A
        );
        
        if (empty($question_results)) return 0;
        
        $user_correct = 0;
        $user_total = count($question_results);
        
        foreach ($question_results as $result) {
            $user_correct += $result['is_correct'];
        }
        
        return $user_total > 0 ? round($user_correct / $user_total * 100, 4) : 0;
    }
    
    // For looksfam mode, use batch function
    $scores = questionstatlooksfam_batch([$question_id], $user_id);
    return $scores[$question_id] ?? 0;
}


function questionoveralllooksfam($question_ids, $user_id, $mode = "looksfam") {
    global $wpdb;
    $table_name = $wpdb->prefix . 'exam_answers';

    $question_results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT DISTINCT user_id, session_id 
             FROM $table_name 
             WHERE question_id = %d",
            $question_ids
        ),
        ARRAY_A
    );

    if (empty($question_results)) {
        return 0;
    }

    $displaylook_total = 0;
    $unique_sessions = array();

    foreach ($question_results as $result) {
        $session_key = $result['user_id'] . '_' . $result['session_id'];

        if (!in_array($session_key, $unique_sessions)) {
            $displaylook = questionstatlooksfam($question_ids, $result['user_id'], $mode);
            $displaylook_total += $displaylook;
            $unique_sessions[] = $session_key;
        }
    }

    $unique_session_count = count($unique_sessions);
    $percentageScore = $unique_session_count > 0 ? $displaylook_total / $unique_session_count : 0;

    // Accuracy mode just returns average accuracy, not capped to 100
    if ($mode === "accuracy") {
        return round($percentageScore, 4);
    }

    // Clamp looksfam score
    $percentageScore = min(max($percentageScore, 0), 100);
    return round($percentageScore, 2);
}



//-------------------------------------- USER---------------------

// Add the user management functionality
function render_user_profiles_page() {
    // Check if the user is allowed to manage user profiles
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have permission to access this page.'));
    }

    echo '<div class="wrap">';
    echo '<h2>User Management</h2>';

    // Display a list of existing user profiles in a table
    $users = get_users();
    
    echo '<h3>Existing User Profiles</h3>';
    
    if (!empty($users)) {
        echo '<table class="wp-list-table widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th>User ID</th>';
        echo '<th>Username</th>';
        echo '<th>Email</th>';
        echo '<th>Role</th>';
        echo '<th>Actions</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($users as $user) {
            $user_roles = $user->roles;
            $role = !empty($user_roles) ? $user_roles[0] : 'No Role';

            echo '<tr>';
            echo '<td>' . $user->ID . '</td>';
            echo '<td>' . esc_html($user->user_login) . '</td>';
            echo '<td>' . esc_html($user->user_email) . '</td>';
            echo '<td>' . esc_html($role) . '</td>';
            echo '<td>';
            echo '<a href="admin.php?page=user-management&edit_user=' . $user->ID . '">Edit</a>'; // Edit link
            echo ' | ';
            echo '<a href="admin.php?page=user-management&delete_user=' . $user->ID . '">Delete</a>'; // Delete link
            echo '</td>';
            echo '</tr>';
        }
        echo '</tbody>';
        echo '</table>';
    } else {
        echo '<p>No user profiles available.</p>';
    }

    // Handle edit and delete actions
    if (isset($_GET['edit_user'])) {
        // Display user profile editing form
        $user_id = intval($_GET['edit_user']);
        $user = get_user_by('ID', $user_id);
        
        if ($user) {
            // Display user editing form with fields for username, email, role, etc.
            echo '<h3>Edit User Profile</h3>';
            echo '<form method="post" action="">';
            echo '<input type="hidden" name="user_id" value="' . $user_id . '">';
            echo '<label for="username">Username:</label>';
            echo '<input type="text" name="username" id="username" value="' . esc_attr($user->user_login) . '" required>';
            echo '<label for="email">Email:</label>';
            echo '<input type="email" name="email" id="email" value="' . esc_attr($user->user_email) . '" required>';
            
            // Add a role selection dropdown
            echo '<label for="role">Role:</label>';
            echo '<select name="role" id="role">';
            $roles = wp_roles()->get_names();
            foreach ($roles as $role_key => $role_name) {
                echo '<option value="' . esc_attr($role_key) . '" ' . selected($user->roles[0], $role_key, false) . '>' . esc_html($role_name) . '</option>';
            }
            echo '</select>';
            
            echo '<input type="submit" name="edit_user_profile" value="Save Changes">';
            echo '</form>';
        }
    } elseif (isset($_GET['delete_user'])) {
        // Handle user profile deletion
        $user_id = intval($_GET['delete_user']);
        if (wp_delete_user($user_id)) {
            echo '<p>User profile deleted successfully.</p>';
        } else {
            echo '<p>Failed to delete the user profile.</p>';
        }
    }

    // Display a form to add a new user
    echo '<h3>Add New User</h3>';
    echo '<form method="post" action="">';
    echo '<label for="new_username">Username:</label>';
    echo '<input type="text" name="new_username" id="new_username" required>';
    echo '<label for="new_email">Email:</label>';
    echo '<input type="email" name="new_email" id="new_email" required>';
    
    // Add a password field for the new user
    echo '<label for="new_password">Password:</label>';
    echo '<input type="password" name="new_password" id="new_password" required>';
    
    // Add a role selection dropdown for the new user
    echo '<label for="new_role">Role:</label>';
    echo '<select name="new_role" id="new_role">';
    foreach ($roles as $role_key => $role_name) {
        echo '<option value="' . esc_attr($role_key) . '">' . esc_html($role_name) . '</option>';
    }
    
    
    echo '</select>';
    
    echo '<input type="submit" name="add_new_user" value="Add New User">';
    echo '</form';

    // Handle form submission to add a new user
    if (isset($_POST['add_new_user'])) {
        $new_username = sanitize_user($_POST['new_username']);
        $new_email = sanitize_email($_POST['new_email']);
        $new_password = $_POST['new_password'];
        $new_role = sanitize_text_field($_POST['new_role']);

        $user_id = wp_create_user($new_username, $new_password, $new_email);
        if (is_wp_error($user_id)) {
            echo '<p>Failed to create a new user. Please check the input data.</p>';
        } else {
            // Set the role for the new user
            $new_user = get_user_by('ID', $user_id);
            $new_user->set_role($new_role);
            echo '<p>New user added successfully with ID: ' . $user_id . '</p>';
        }
    }

    echo '</div>';
}

// Add the user roles functionality
function render_user_roles_page() {
    if (!current_user_can('manage_options')) {
        wp_die(__('You do not have permission to access this page.'));
    }

    echo '<div class="wrap">';
    echo '<h2>User Roles</h2>';

    // Display a form to add a new role
    echo '<h3>Add New User Role</h3>';
    echo '<form method="post" action="">';
    echo '<label for="role">Role Name:</label>';
    echo '<input type="text" name="role" id="role" required>';
    echo '<input type="submit" name="add_role" value="Add Role">';
    echo '</form>';

    // Handle form submissions to add a new role
    if (isset($_POST['add_role'])) {
        $new_role_name = sanitize_text_field($_POST['role']);
        add_role($new_role_name, $new_role_name, array());
        echo '<p>New role added successfully!</p>';
    }

    // Display a list of existing roles
    echo '<h3>Existing User Roles</h3>';
    $roles = wp_roles()->get_names();
    echo '<ul>';
    foreach ($roles as $role => $label) {
        echo '<li>' . esc_html($role) . '</li>';
    }
    echo '</ul>';

    echo '</div>';
}

// Register admin menu items
function register_admin_menus() {
    add_menu_page('User Management', 'User Management', 'manage_options', 'user-management', 'render_user_profiles_page');
    add_submenu_page('user-management', 'User Roles', 'User Roles', 'manage_options', 'user-roles', 'render_user_roles_page');
}

// Hook into WordPress to add menu items
add_action('admin_menu', 'register_admin_menus');

// 1. Save login time + IP when user logs in
add_action('wp_login', 'bntm_save_login_history', 10, 2);
function bntm_save_login_history($user_login, $user) {
    $login_data = array(
        'time' => current_time('mysql'),
        'ip'   => $_SERVER['REMOTE_ADDR']
    );

    // Fetch old logins
    $logins = get_user_meta($user->ID, 'bntm_login_history', true);
    if (!is_array($logins)) {
        $logins = array();
    }

    // Add new login at the top
    array_unshift($logins, $login_data);

    // Limit to 10 recent logins
    $logins = array_slice($logins, 0, 10);

    update_user_meta($user->ID, 'bntm_login_history', $logins);
}

// 2. Add submenu under Users
add_action('admin_menu', 'bntm_last_logins_submenu');
function bntm_last_logins_submenu() {
    add_users_page(
        'Last Logins',           // Page title
        'Last Logins',           // Menu title
        'list_users',            // Capability
        'bntm-last-logins',      // Menu slug
        'bntm_render_last_logins_page' // Callback
    );
}

// 3. Render the page
function bntm_render_last_logins_page() {
    ?>
    <div class="wrap">
        <h1>Recent User Logins</h1>
        <table class="widefat striped">
            <thead>
                <tr>
                    <th>User</th>
                    <th>Email</th>
                    <th>Login Time</th>
                    <th>IP Address</th>
                </tr>
            </thead>
            <tbody>
            <?php
            $users = get_users();
            foreach ($users as $user) {
                $logins = get_user_meta($user->ID, 'bntm_login_history', true);
                if (!empty($logins)) {
                    foreach ($logins as $login) {
                        echo '<tr>';
                        echo '<td>' . esc_html($user->user_login) . '</td>';
                        echo '<td>' . esc_html($user->user_email) . '</td>';
                        echo '<td>' . esc_html($login['time']) . '</td>';
                        echo '<td>' . esc_html($login['ip']) . '</td>';
                        echo '</tr>';
                    }
                }
            }
            ?>
            </tbody>
        </table>
    </div>
    <?php
}

// Add submenu under Users for Last Logins
add_action('admin_menu', function() {
    add_users_page(
        'UM Last Logins',          // Page title
        'UM Last Logins',          // Menu title
        'list_users',           // Capability
        'last-logins',          // Menu slug
        'render_last_logins'    // Callback function
    );
});

// Render page content
function render_last_logins() {
    ?>
    <div class="wrap">
        <h1><?php esc_html_e('User Last Logins', 'ultimate-member'); ?></h1>
        <table class="wp-list-table widefat fixed striped users">
            <thead>
                <tr>
                    <th><?php esc_html_e('User', 'ultimate-member'); ?></th>
                    <th><?php esc_html_e('Email', 'ultimate-member'); ?></th>
                    <th><?php esc_html_e('Last Login', 'ultimate-member'); ?></th>
                </tr>
            </thead>
            <tbody>
                <?php
                $users = get_users();
                if ( !empty($users) ) {
                    foreach ( $users as $user ) {
                        $last_login = um_user_last_login_date( $user->ID );
                        ?>
                        <tr>
                            <td><?php echo esc_html($user->display_name); ?></td>
                            <td><?php echo esc_html($user->user_email); ?></td>
                            <td><?php echo $last_login ? esc_html($last_login) : 'Never logged in'; ?></td>
                        </tr>
                        <?php
                    }
                } else {
                    ?>
                    <tr>
                        <td colspan="3"><?php esc_html_e('No users found.', 'ultimate-member'); ?></td>
                    </tr>
                    <?php
                }
                ?>
            </tbody>
        </table>
    </div>
    <?php
}
// Track CPU usage at the start
add_action('init', function() {
    if (function_exists('getrusage')) {
        $GLOBALS['bntm_cpu_start'] = getrusage();
    }

    // Enable query saving if not already
    if (!defined('SAVEQUERIES')) {
        define('SAVEQUERIES', true);
    }
});

// Show performance stats in footer (only for admins)
add_action('wp_footer', function() {
    if (!current_user_can('manage_options')) return; // only admins

    global $wpdb;

    // CPU usage
    $cpu = 0;
    if (function_exists('getrusage') && isset($GLOBALS['bntm_cpu_start'])) {
        $end = getrusage();
        $start = $GLOBALS['bntm_cpu_start'];

        $utime = ($end["ru_utime.tv_sec"] * 1e6 + $end["ru_utime.tv_usec"]) 
               - ($start["ru_utime.tv_sec"] * 1e6 + $start["ru_utime.tv_usec"]);
        $stime = ($end["ru_stime.tv_sec"] * 1e6 + $end["ru_stime.tv_usec"]) 
               - ($start["ru_stime.tv_sec"] * 1e6 + $start["ru_stime.tv_usec"]);
        $cpu = ($utime + $stime) / 1e6; // seconds
    }

    // Memory usage
    $memory = round(memory_get_usage(true) / 1024 / 1024, 2); // MB
    $memory_peak = round(memory_get_peak_usage(true) / 1024 / 1024, 2); // MB

    // Database queries
    $queries = get_num_queries();
    $query_time = timer_stop(0); // total page load time

    // Output box
    echo '<div style="position:fixed;bottom:10px;right:10px;
                     background:#111;color:#0f0;padding:8px 12px;
                     font-size:12px;border-radius:6px;
                     font-family:monospace;z-index:99999;
                     max-height:30vh;overflow:auto;line-height:1.4;max-width:100%">
            <div>⚡ CPU: ' . round($cpu, 4) . ' sec</div>
            <div>💾 Memory: ' . $memory . ' MB (Peak: ' . $memory_peak . ' MB)</div>
            <div>📊 Queries: ' . $queries . ' in ' . $query_time . ' sec</div>';

    // Expand/collapse queries
    if (defined('SAVEQUERIES') && SAVEQUERIES && !empty($wpdb->queries)) {
        echo '<button onclick="var q=document.getElementById(\'query-list\'); 
                               q.style.display=q.style.display===\'none\'?\'block\':\'none\';" 
                style="margin-top:6px;background:#222;color:#0f0;border:1px solid #0f0;
                       padding:3px 6px;font-size:11px;cursor:pointer;border-radius:4px;">
                Show/Hide Queries
              </button>';

        echo '<div id="query-list" style="margin-top:8px;color:#fff;display:none;
                                          max-height:20vh;overflow:auto;">';
        foreach ($wpdb->queries as $q) {
            // $q[0] = SQL, $q[1] = time, $q[2] = stack trace
            echo '<div style="margin-bottom:6px;">
                    <span style="color:#0ff;">' . esc_html(round($q[1], 5)) . 's</span> 
                    <span style="color:#ff0;">' . esc_html($q[0]) . '</span>
                  </div>';
        }
        echo '</div>';
    }

    echo '</div>';
});




/**
 * Create custom table for storing exam answers
 */
function create_exam_answers_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'exam_answers';
    
    $charset_collate = $wpdb->get_charset_collate();
    
    $sql = "CREATE TABLE $table_name (
        id bigint(20) unsigned NOT NULL AUTO_INCREMENT,
        exam_id bigint(20) unsigned NOT NULL,
        class_id bigint(20) unsigned NOT NULL,
        exam_name varchar(255) NOT NULL,
        question_id bigint(20) unsigned NOT NULL,
        user_id bigint(20) unsigned NOT NULL,
        user_answer text NOT NULL,
        is_correct tinyint(1) NOT NULL DEFAULT 0,
        timestamp datetime DEFAULT CURRENT_TIMESTAMP,
        session_id varchar(10) NOT NULL,
        question_category bigint(20) unsigned DEFAULT NULL,
        created_at timestamp DEFAULT CURRENT_TIMESTAMP,
        updated_at timestamp DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY exam_id (exam_id),
        KEY class_id (class_id),
        KEY question_id (question_id),
        KEY user_id (user_id),
        KEY session_id (session_id),
        KEY timestamp (timestamp)
    ) $charset_collate;";
    
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
    
    // Check if table was created successfully
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name) {
        add_option('exam_answers_table_created', true);
        return true;
    }
    
    return false;
}

/**
 * Drop the exam answers table
 */
function drop_exam_answers_table() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'exam_answers';
    $wpdb->query("DROP TABLE IF EXISTS $table_name");
    
    delete_option('exam_answers_table_created');
    
    return true;
}

/**
 * Add admin submenu for table management
 */
function add_exam_answers_admin_menu() {
    add_submenu_page(
        'tools.php',
        'Exam Answers Table Manager',
        'Exam Answers DB',
        'manage_options',
        'exam-answers-table',
        'exam_answers_table_admin_page'
    );
}
add_action('admin_menu', 'add_exam_answers_admin_menu');

/**
 * Admin page for table management
 */
function exam_answers_table_admin_page() {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'exam_answers';
    $table_exists = ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") == $table_name);
    
    // Handle form submissions
    if (isset($_POST['action'])) {
        if ($_POST['action'] === 'create_table' && !$table_exists) {
            if (create_exam_answers_table()) {
                echo '<div class="notice notice-success"><p>Table created successfully!</p></div>';
                $table_exists = true;
            } else {
                echo '<div class="notice notice-error"><p>Failed to create table.</p></div>';
            }
        } elseif ($_POST['action'] === 'drop_table' && $table_exists) {
            if (drop_exam_answers_table()) {
                echo '<div class="notice notice-success"><p>Table dropped successfully!</p></div>';
                $table_exists = false;
            } else {
                echo '<div class="notice notice-error"><p>Failed to drop table.</p></div>';
            }
        }
    }
    
    // Get table stats if exists
    $record_count = 0;
    if ($table_exists) {
        $record_count = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
    }
    
    ?>
    <div class="wrap">
        <h1>Exam Answers Table Manager</h1>
        
        <div class="card">
            <h2>Table Status</h2>
            <p><strong>Table Name:</strong> <?php echo esc_html($table_name); ?></p>
            <p><strong>Status:</strong> 
                <?php if ($table_exists): ?>
                    <span style="color: green;">✓ Exists</span>
                <?php else: ?>
                    <span style="color: red;">✗ Does not exist</span>
                <?php endif; ?>
            </p>
            <?php if ($table_exists): ?>
                <p><strong>Records:</strong> <?php echo number_format($record_count); ?></p>
            <?php endif; ?>
        </div>
        
        <div class="card">
            <h2>Table Management</h2>
            <form method="post" style="margin-bottom: 20px;">
                <?php if (!$table_exists): ?>
                    <input type="hidden" name="action" value="create_table">
                    <button type="submit" class="button button-primary" onclick="return confirm('Are you sure you want to create the exam answers table?')">
                        Create Table
                    </button>
                    <p class="description">This will create the exam_answers table in your database.</p>
                <?php else: ?>
                    <input type="hidden" name="action" value="drop_table">
                    <button type="submit" class="button button-secondary" onclick="return confirm('WARNING: This will permanently delete the table and ALL exam answer data. Are you absolutely sure?')" style="background: #dc3545; border-color: #dc3545; color: white;">
                        Drop Table
                    </button>
                    <p class="description" style="color: #dc3545;">⚠️ <strong>Warning:</strong> This will permanently delete all exam answer data!</p>
                <?php endif; ?>
            </form>
        </div>
        
        <?php if ($table_exists): ?>
        <div class="card">
            <h2>Recent Submissions</h2>
            <?php
            $recent_submissions = $wpdb->get_results(
                "SELECT * FROM $table_name ORDER BY created_at DESC LIMIT 10",
                ARRAY_A
            );
            
            if ($recent_submissions): ?>
                <table class="wp-list-table widefat fixed striped">
                    <thead>
                        <tr>
                            <th>Session ID</th>
                            <th>User ID</th>
                            <th>Exam</th>
                            <th>Question ID</th>
                            <th>Correct</th>
                            <th>Date</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($recent_submissions as $submission): ?>
                        <tr>
                            <td><?php echo esc_html($submission['session_id']); ?></td>
                            <td><?php echo esc_html($submission['user_id']); ?></td>
                            <td><?php echo esc_html($submission['exam_name']); ?></td>
                            <td><?php echo esc_html($submission['question_id']); ?></td>
                            <td><?php echo $submission['is_correct'] ? '✓' : '✗'; ?></td>
                            <td><?php echo esc_html($submission['created_at']); ?></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php else: ?>
                <p>No submissions found.</p>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

/**
 * Efficient function to save exam answers to custom table
 */
function save_exam_answers_batch($answers_data) {
    global $wpdb;
    
    if (empty($answers_data)) {
        return false;
    }
    
    $table_name = $wpdb->prefix . 'exam_answers';
    
    // Check if table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return false;
    }
    
    // Prepare values for batch insert
    $values = array();
    $placeholders = array();
    
    foreach ($answers_data as $data) {
        $values[] = $data['exam_id'];
        $values[] = $data['class_id'];
        $values[] = $data['exam_name'];
        $values[] = $data['question_id'];
        $values[] = $data['user_id'];
        $values[] = $data['user_answer'];
        $values[] = $data['is_correct'];
        $values[] = $data['timestamp'];
        $values[] = $data['session_id'];
        $values[] = $data['question_category'];
        
        $placeholders[] = "(%d, %d, %s, %d, %d, %s, %d, %s, %s, %d)";
    }
    
    $sql = "INSERT INTO $table_name 
            (exam_id, class_id, exam_name, question_id, user_id, user_answer, is_correct, timestamp, session_id, question_category) 
            VALUES " . implode(', ', $placeholders);
    
    $result = $wpdb->query($wpdb->prepare($sql, $values));
    
    return $result !== false;
}

/**
 * Modified flashcard exam submission handler
 */

/**
 * Helper function to get exam answers by session ID
 */
function get_exam_answers_by_session($session_id) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'exam_answers';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE session_id = %s ORDER BY question_id ASC",
        $session_id
    ), ARRAY_A);
}

/**
 * Helper function to get user's exam history
 */
function get_user_exam_history($user_id, $limit = 50) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'exam_answers';
    
    return $wpdb->get_results($wpdb->prepare(
        "SELECT exam_id, exam_name, session_id, COUNT(*) as questions_answered, 
                SUM(is_correct) as correct_answers, MAX(created_at) as completed_at
         FROM $table_name 
         WHERE user_id = %d 
         GROUP BY session_id, exam_id 
         ORDER BY completed_at DESC 
         LIMIT %d",
        $user_id, $limit
    ), ARRAY_A);
}

/**
 * Helper function to get exam statistics
 */
function get_exam_statistics($exam_id = null) {
    global $wpdb;
    
    $table_name = $wpdb->prefix . 'exam_answers';
    
    $where_clause = $exam_id ? $wpdb->prepare("WHERE exam_id = %d", $exam_id) : "";
    
    return $wpdb->get_row(
        "SELECT COUNT(*) as total_answers,
                SUM(is_correct) as correct_answers,
                COUNT(DISTINCT user_id) as unique_users,
                COUNT(DISTINCT session_id) as total_sessions
         FROM $table_name $where_clause",
        ARRAY_A
    );
}

/**
 * Initialize table on plugin activation (optional)
 */
register_activation_hook(__FILE__, 'create_exam_answers_table');
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
        <h1>Generate Exam Answers</h1>
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
                    <p class="description">How many questions each student should answer (distributed evenly across categories)</p>
                </td>
            </tr>
        </table>

        <p class="submit">
            <button id="generate-fake-answers" class="button button-primary">Generate Exam Session</button>
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
function get_imported_questions_by_categories($cat_id, $num_questions) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'imported_questions';
    
    // Get ONLY immediate children of the selected category for DISPLAY
    $display_categories = get_terms([
        'taxonomy' => 'question_category',
        'parent' => $cat_id,
        'hide_empty' => false
    ]);
    
    // If no children, use the parent category itself
    if (empty($display_categories) || is_wp_error($display_categories)) {
        $main_term = get_term($cat_id);
        if ($main_term && !is_wp_error($main_term)) {
            $display_categories = [$main_term];
        } else {
            return ['questions' => [], 'distribution' => [], 'question_category_map' => []];
        }
    }
    
    $questions_by_category = [];
    $total_available = 0;
    
    // For each display category, get questions from it AND all its descendants
    foreach ($display_categories as $display_cat) {
        // Get all descendant IDs for this display category
        $all_ids = get_all_descendant_category_ids($display_cat->term_id);
        $all_ids[] = $display_cat->term_id; // Include the display category itself
        $all_ids = array_unique($all_ids);
        
        // Get all term info for querying
        $all_identifiers = [];
        foreach ($all_ids as $term_id) {
            $term = get_term($term_id);
            if ($term && !is_wp_error($term)) {
                $all_identifiers[] = $term->term_id;
                $all_identifiers[] = $term->slug;
                $all_identifiers[] = $term->name;
            }
        }
        
        $all_identifiers = array_unique($all_identifiers);
        
        if (empty($all_identifiers)) {
            continue;
        }
        
        // Query questions matching any of these identifiers
        $placeholders = implode(',', array_fill(0, count($all_identifiers), '%s'));
        $query = $wpdb->prepare(
            "SELECT * FROM $table_name WHERE category IN ($placeholders) ORDER BY RAND()",
            $all_identifiers
        );
        
        $results = $wpdb->get_results($query, ARRAY_A);
        
        if (!empty($results)) {
            // Convert to expected format
            $formatted_questions = [];
            foreach ($results as $row) {
                $formatted_questions[] = [
                    'ID' => $row['id'],
                    'post_id' => $row['post_id'],
                    'post_title' => $row['title'],
                    'title' => $row['title'],
                    'category' => $row['category'],
                    'option_a' => $row['option_a'],
                    'option_b' => $row['option_b'],
                    'option_c' => $row['option_c'],
                    'option_d' => $row['option_d'],
                    'correct_answer' => $row['correct_answer'],
                    'solution' => $row['solution'],
                    'image_url' => $row['image_url']
                ];
            }
            
            // Store under the DISPLAY category ID (not the actual question category)
            $questions_by_category[$display_cat->term_id] = $formatted_questions;
            $total_available += count($formatted_questions);
        }
    }
    
    if (empty($questions_by_category)) {
        return ['questions' => [], 'distribution' => [], 'question_category_map' => []];
    }
    
    // Rest of the function remains the same...
    // Calculate how many questions to take from each category
    $num_categories = count($questions_by_category);
    $base_per_category = floor($num_questions / $num_categories);
    $remainder = $num_questions % $num_categories;
    
    $final_questions = [];
    $distribution = [];
    $question_category_map = [];
    
    $category_index = 0;
    foreach ($questions_by_category as $category_id => $questions) {
        $questions_to_take = $base_per_category;
        
        if ($category_index < $remainder) {
            $questions_to_take++;
        }
        
        $questions_to_take = min($questions_to_take, count($questions));
        
        if ($questions_to_take > 0) {
            shuffle($questions);
            $selected = array_slice($questions, 0, $questions_to_take);
            $final_questions = array_merge($final_questions, $selected);
            
            foreach ($selected as $question) {
                $question_category_map[$question['ID']] = $category_id;
            }
            
            $category_name = get_term($category_id)->name ?? 'Category ' . $category_id;
            $distribution[] = [
                'category_id' => $category_id,
                'category_name' => $category_name,
                'count' => $questions_to_take,
                'available' => count($questions)
            ];
        }
        
        $category_index++;
    }
    
    shuffle($final_questions);
    
    return [
        'questions' => $final_questions,
        'distribution' => $distribution,
        'question_category_map' => $question_category_map
    ];
}

// Keep this helper function
function get_all_descendant_category_ids($cat_id) {
    $all_ids = [];
    
    $child_categories = get_terms([
        'taxonomy' => 'question_category',
        'parent' => $cat_id,
        'hide_empty' => false,
        'fields' => 'ids'
    ]);
    
    if (!empty($child_categories) && !is_wp_error($child_categories)) {
        foreach ($child_categories as $child_id) {
            $all_ids[] = $child_id;
            $descendants = get_all_descendant_category_ids($child_id);
            $all_ids = array_merge($all_ids, $descendants);
        }
    }
    
    return $all_ids;
}

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

    // Get questions from imported_questions table distributed across categories
    $question_data = get_imported_questions_by_categories($cat_id, $num_questions);
    $questions = $question_data['questions'];
    $distribution = $question_data['distribution'];
    $question_category_map = $question_data['question_category_map'];
    
    if (empty($questions)) {
        echo "<p style='color:red;'>No questions found in imported_questions table for this subject/topic.</p>";
        wp_die();
    }

    if (count($questions) < $num_questions) {
        echo "<p style='color:orange;'>Warning: Only " . count($questions) . " questions available, but you requested $num_questions. Using all available questions.</p>";
        $num_questions = count($questions);
    }

    // Randomly select students
    $selected_students = array_rand(array_flip($enrolled_students), $num_students);
    if (!is_array($selected_students)) {
        $selected_students = [$selected_students];
    }

    // Generate session ID for this exam
    $session_id = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 6);
    
    echo "<div style='margin-bottom: 20px; padding: 10px; background: #e8f4fd; border-left: 4px solid #0073aa;'>";
    echo "<h3>Exam Session: $session_id</h3>";
    echo "<p><strong>Class:</strong> " . esc_html(get_the_title($class_id)) . "</p>";
    echo "<p><strong>Students:</strong> $num_students | <strong>Questions:</strong> $num_questions</p>";
    echo "</div>";

    // Show question distribution by category
    if (!empty($distribution)) {
        echo "<div style='margin-bottom: 20px; padding: 10px; background: #fff3cd; border-left: 4px solid #ffc107;'>";
        echo "<h4>Question Distribution by Category:</h4>";
        echo "<table class='widefat' style='margin: 10px 0;'>";
        echo "<thead><tr><th>Category</th><th>Questions Used</th><th>Available</th><th>Percentage</th></tr></thead>";
        echo "<tbody>";
        
        foreach ($distribution as $dist) {
            $percentage = round(($dist['count'] / $num_questions) * 100, 1);
            echo "<tr>";
            echo "<td>" . esc_html($dist['category_name']) . "</td>";
            echo "<td><strong>" . $dist['count'] . "</strong></td>";
            echo "<td>" . $dist['available'] . "</td>";
            echo "<td>" . $percentage . "%</td>";
            echo "</tr>";
        }
        
        echo "</tbody></table>";
        echo "</div>";
    }

    $answers_batch = [];
    $choices = ['A','B','C','D'];
    $total_answers = 0;
    $correct_answers = 0;

    // Create a mapping of distribution category ID to category name for display
    $distribution_categories = [];
    foreach ($distribution as $dist) {
        $distribution_categories[$dist['category_id']] = $dist['category_name'];
    }

    echo "<table class='widefat striped'><thead><tr>
        <th>Student</th><th>Question #</th><th>Category</th><th>Question Title</th><th>Answer</th><th>Correct Answer</th><th>Result</th>
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
            $correct_answer = $question['correct_answer'];
            
            // Get the distribution category for this question
            $distribution_category_id = $question_category_map[$question['ID']] ?? null;
            $category_name = $distribution_categories[$distribution_category_id] ?? 'Unknown';
            
            // Determine if this answer should be correct based on student's target accuracy
            $remaining_questions = $num_questions - $q_num;
            $remaining_correct_needed = $student_correct_target - $student_correct_count;
            
            // Randomize answer selection per user per question
            if ($remaining_correct_needed > 0 && 
                ($remaining_correct_needed >= $remaining_questions || rand(1, 100) <= 60)) {
                // Give correct answer
                $fake_answer = $correct_answer;
                $is_correct = 1;
                $student_correct_count++;
                $correct_answers++;
            } else {
                // Give random wrong answer (different for each student)
                $wrong_choices = array_diff($choices, [$correct_answer]);
                $wrong_choices = array_values($wrong_choices); // Re-index array
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
                'question_category' => $distribution_category_id,
            ];

            $result_icon = $is_correct ? "<span style='color:green;'>✓</span>" : "<span style='color:red;'>✗</span>";
            
            echo "<tr>
                <td>" . esc_html($student_name) . "</td>
                <td>" . ($q_num + 1) . "</td>
                <td><small>" . esc_html($category_name) . "</small></td>
                <td>" . esc_html($question['title']) . "</td>
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
class DisableXMLRPCLogin {
    
    public function __construct() {
        $this->init();
    }
    
    public function init() {
        // Disable XML-RPC
        add_filter('xmlrpc_enabled', '__return_false');
        
        // Remove pingback headers
        add_filter('wp_headers', array($this, 'remove_pingback_header'));
        
        // Disable XML-RPC methods
        add_filter('xmlrpc_methods', array($this, 'disable_xmlrpc_methods'));
        
        // Block XML-RPC completely
        add_action('init', array($this, 'block_xmlrpc_requests'));
    }
    
    public function remove_pingback_header($headers) {
        unset($headers['X-Pingback']);
        return $headers;
    }
    
    public function disable_xmlrpc_methods($methods) {
        unset($methods['pingback.ping']);
        unset($methods['pingback.extensions.getPingbacks']);
        unset($methods['wp.getUsersBlogs']);
        unset($methods['system.multicall']);
        return $methods;
    }
    
    public function block_xmlrpc_requests() {
        if (defined('XMLRPC_REQUEST') && XMLRPC_REQUEST) {
            status_header(403);
            exit('XML-RPC is disabled on this site.');
        }
    }
}

new DisableXMLRPCLogin();

function lf_loading_screen() {
    ?>
    <div id="lf-loading-screen" style="
        position: fixed;
        top: 0;
        left: 0;
        width: 100vw;
        max-width: 100vw;
        height: 100vh;
            background: linear-gradient(135deg, var(--ast-global-color-4) 0%, var(--ast-global-color-5) 100%);
        display: none;
        justify-content: center;
        align-items: center;
        z-index: 9999;
        opacity: 0;
        transition: opacity 0.3s ease;
    ">
        <div style="text-align: center;">
            <img 
                src="<?php echo home_url('wp-content/uploads/2023/11/LOOk-2-1.png'); ?>" 
                alt="LooksFam Logo" 
                style="
                    max-width: 140px;
                    height: auto;
                    animation: lf-pulse 1.5s ease-in-out infinite;
                "
            >
            <p style="
                margin-top: 20px;
                color: #fff;
                font-size: 1.1em;
                font-weight: 500;
            ">Loading...</p>
        </div>
    </div>

    <style>
        @keyframes lf-pulse {
            0%, 100% {
                opacity: 0.5;
                transform: scale(1);
            }
            50% {
                opacity: 1;
                transform: scale(1.05);
            }
        }
        
        body.lf-loading-active {
            overflow: hidden;
        }
    </style>

    <script>
        (function() {
            // Show loading screen on initial page load
            const loadingScreen = document.getElementById('lf-loading-screen');
            loadingScreen.style.display = 'flex';
            loadingScreen.style.opacity = '1';

            // Hide loading screen when page is fully loaded
            window.addEventListener('load', function() {
                setTimeout(function() {
                    loadingScreen.style.opacity = '0';
                    setTimeout(function() {
                        loadingScreen.style.display = 'none';
                    }, 300);
                }, 2000);
                document.body.classList.remove('lf-loading-active');
            });

            // Show loading on link clicks that navigate away
            document.addEventListener('click', function(e) {
                const target = e.target.closest('a, button[onclick*="location"], button[data-href],input[type="button"]');
                
                if (target) {
                    let targetUrl = target.href || target.getAttribute('data-href');
                    
                    // Check if it's a button with onclick
                    // Check if it's a button/input with onclick
                    if (!targetUrl && target.onclick) {
                        const onclickStr = target.onclick.toString();
                        const urlMatch = onclickStr.match(/(?:location\s*=\s*|location\.href\s*=\s*|window\.location\s*=\s*|window\.location\.href\s*=\s*)['"]([^'"]+)['"]/);
                        if (urlMatch) {
                            targetUrl = urlMatch[1];
                        }
                    }
                     // Also check onclick attribute directly from HTML
                    if (!targetUrl && target.hasAttribute('onclick')) {
                        const onclickAttr = target.getAttribute('onclick');
                        const urlMatch = onclickAttr.match(/(?:location\s*=\s*|location\.href\s*=\s*|window\.location\s*=\s*|window\.location\.href\s*=\s*)['"]([^'"]+)['"]/);
                        if (urlMatch) {
                            targetUrl = urlMatch[1];
                        }
                    }    
                    // Only show loading for internal navigation
                    if (targetUrl && 
                        !target.hasAttribute('target') && 
                        !targetUrl.startsWith('#') && 
                        !targetUrl.startsWith('mailto:') && 
                        !targetUrl.startsWith('tel:') &&
                        !target.classList.contains('no-loading')) {
                        
                        loadingScreen.style.display = 'flex';
                        document.body.classList.add('lf-loading-active');
                        setTimeout(() => {
                            loadingScreen.style.opacity = '1';
                        }, 10);
                    }
                }
            });

            // Global function to show loading screen
            window.showLFLoading = function() {
                loadingScreen.style.display = 'flex';
                document.body.classList.add('lf-loading-active');
                setTimeout(() => {
                    loadingScreen.style.opacity = '1';
                }, 10);
            };
        })();
    </script>
    <?php
}

/**
 * LooksFam Board Exam Tool Intro Slides
 */
function lf_intro_slides() {
    ?>
    <div id="lf-intro-overlay" style="display: none;">
        <div class="lf-intro-container" style="opacity: 0;">
            <!-- Slide 1: Welcome -->
            <div class="lf-intro-slide active" data-slide="1">
                <div class="lf-slide-content">
                    <img src="<?php echo home_url('wp-content/uploads/2023/11/LOOk-2-1.png'); ?>" alt="LooksFam Logo" class="lf-intro-logo">
                    <h1>Board Exam Tool</h1>
                    <p class="lf-tagline">EASIER, SMARTER AND GAMIFIED</p>
                    <button class="lf-get-started-btn" data-redirect="<?php echo esc_url(home_url('/register')); ?>">START LOOKSFAM NOW!</button>
                </div>
            </div>

            <!-- Slide 2: EASIER -->
            <div class="lf-intro-slide" data-slide="2">
                <div class="lf-slide-content">
                    <div class="lf-feature-icon" style="background: linear-gradient(135deg, #4CAF50 0%, #45a049 100%);">
                        <i class="fa-solid fa-book-open"></i>
                    </div>
                    <h2>EASIER</h2>
                    <p class="lf-description">Review made simple—thousands of past board exam questions, anytime, anywhere.</p>
                </div>
            </div>

            <!-- Slide 3: SMARTER -->
            <div class="lf-intro-slide" data-slide="3">
                <div class="lf-slide-content">
                    <div class="lf-feature-icon" style="background: linear-gradient(135deg, #2196F3 0%, #1976D2 100%);">
                        <i class="fa-solid fa-brain"></i>
                    </div>
                    <h2>SMARTER</h2>
                    <p class="lf-description">Study with real-time national analytics that show you exactly where to focus.</p>
                </div>
            </div>

            <!-- Slide 4: GAMIFIED -->
            <div class="lf-intro-slide" data-slide="4">
                <div class="lf-slide-content">
                    <div class="lf-feature-icon" style="background: linear-gradient(135deg, #FF9800 0%, #F57C00 100%);">
                        <i class="fa-solid fa-gamepad"></i>
                    </div>
                    <h2>GAMIFIED</h2>
                    <p class="lf-description">Turn your review into a challenge—compete, track, and stay motivated.</p>
                </div>
            </div>

            <!-- Slide 5: About -->
            <div class="lf-intro-slide" data-slide="5">
                <div class="lf-slide-content">
                    <h2 style="color: #333; margin-bottom: 20px;">LOOKSFAM BOARD EXAM TOOL</h2>
                    <p class="lf-description">LooksFam is a smarter way to review—offering thousands of past board exam questions, real-time national analytics, and gamified tracking to help reviewees prepare with focus, confidence, and motivation.</p>
                </div>
            </div>

            <!-- Slide 6: Subjects Overview -->
            <div class="lf-intro-slide" data-slide="6">
                <div class="lf-slide-content">
                    <h2 style="color: #333;">Available Subjects</h2>
                    <div class="lf-subjects-grid">
                        <div class="lf-subject-card">
                            <i class="fa-solid fa-chalkboard-teacher"></i>
                            <h3>LET</h3>
                            <p>Licensure Examination for Teachers</p>
                        </div>
                        <div class="lf-subject-card">
                            <i class="fa-solid fa-landmark"></i>
                            <h3>CSE/CSC</h3>
                            <p>Civil Service Exam</p>
                        </div>
                        <div class="lf-subject-card">
                            <i class="fa-solid fa-microchip"></i>
                            <h3>Electronics</h3>
                            <p>Engineering Exam</p>
                        </div>
                        <div class="lf-subject-card">
                            <i class="fa-solid fa-calculator"></i>
                            <h3>Math</h3>
                            <p>Engineering Mathematics</p>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Slide 7: Features -->
            <div class="lf-intro-slide" data-slide="7">
                <div class="lf-slide-content">
                    <h2 style="color: #333; margin-bottom: 30px;">Key Features</h2>
                    <div class="lf-features-list">
                        <div class="lf-feature-item">
                            <i class="fa-solid fa-chart-line"></i>
                            <div>
                                <h3>Know Your Strengths</h3>
                                <p>Instantly discover which subjects you've mastered and which areas need focus.</p>
                            </div>
                        </div>
                        <div class="lf-feature-item">
                            <i class="fa-solid fa-target"></i>
                            <div>
                                <h3>Performance Tracking</h3>
                                <p>Detailed tracking on each question, showing accuracy, speed, and improvement.</p>
                            </div>
                        </div>
                        <div class="lf-feature-item">
                            <i class="fa-solid fa-clipboard-question"></i>
                            <div>
                                <h3>Thousands of Questions</h3>
                                <p>Practice with real questions from past board exams.</p>
                            </div>
                        </div>
                        <div class="lf-feature-item">
                            <i class="fa-solid fa-users"></i>
                            <div>
                                <h3>National Trends</h3>
                                <p>See how you're performing compared to reviewees across the Philippines.</p>
                            </div>
                        </div>
                    </div>
                </div>
            </div>

            <!-- Slide 8: Final CTA -->
            <div class="lf-intro-slide" data-slide="8">
                <div class="lf-slide-content">
                    <img src="<?php echo home_url('wp-content/uploads/2023/11/LOOk-2-1.png'); ?>" alt="LooksFam Logo" class="lf-intro-logo">
                    <h2 style="color: #333; margin: 20px 0;">Ready to Start?</h2>
                    <p class="lf-cta-message">Join thousands of reviewees preparing smarter with LooksFam</p>
                    <button class="lf-get-started-final" data-redirect="<?php echo esc_url(home_url('/register')); ?>">GET STARTED NOW</button>
                </div>
            </div>

            <!-- Navigation -->
            <div class="lf-intro-navigation">
                <button class="lf-intro-skip" data-redirect="<?php echo esc_url(home_url('/login')); ?>">Skip</button>
                <div class="lf-intro-dots">
                    <span class="lf-dot active" data-slide="1"></span>
                    <span class="lf-dot" data-slide="2"></span>
                    <span class="lf-dot" data-slide="3"></span>
                    <span class="lf-dot" data-slide="4"></span>
                    <span class="lf-dot" data-slide="5"></span>
                    <span class="lf-dot" data-slide="6"></span>
                    <span class="lf-dot" data-slide="7"></span>
                    <span class="lf-dot" data-slide="8"></span>
                </div>
                <button class="lf-intro-next">Next</button>
            </div>
        </div>
    </div>

    <style>
        #lf-intro-overlay {
            position: fixed;
            top: 0;
            left: 0;
            width: 100vw;
            width: 100dvw;
            height: 100vh;
            height: 100dvh;
            background: linear-gradient(135deg, var(--ast-global-color-4) 0%, var(--ast-global-color-5) 100%);
            z-index: 10000;
            display: flex;
            justify-content: center;
            align-items: center;
            opacity: 0;
            transition: opacity 0.5s ease;
            overscroll-behavior: none;
        }

        #lf-intro-overlay.show {
            opacity: 1;
        }

        .lf-intro-container {
            width: 90%;
            max-width: 500px;
            height: 85vh;
            height: 85dvh;
            max-height: 700px;
            background:#ffffff57;
            border-radius: 20px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
            position: relative;
            overflow: hidden;
            padding-bottom: env(safe-area-inset-bottom);
            transition: opacity 0.8s ease;
            transform: scale(0.95);
        }

        .lf-intro-container.visible {
            opacity: 1 !important;
            transform: scale(1);
            transition: all 0.8s cubic-bezier(0.34, 1.56, 0.64, 1);
        }

        .lf-intro-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: calc(100% - 80px);
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.5s ease;
            display: flex;
            align-items: center;
            justify-content: center;
            padding: 30px 20px;
            overflow-y: auto;
            -webkit-overflow-scrolling: touch;
        }

        .lf-intro-slide.active {
            opacity: 1;
            transform: translateX(0);
        }

        .lf-intro-slide.prev {
            transform: translateX(-100%);
        }

        .lf-slide-content {
            text-align: center;
            width: 100%;
        }

        .lf-intro-logo {
            max-width: 150px;
            height: auto;
            margin-bottom: 20px;
            animation: lf-fadeInScale 0.8s ease;
        }

        @keyframes lf-fadeInScale {
            from {
                opacity: 0;
                transform: scale(0.8);
            }
            to {
                opacity: 1;
                transform: scale(1);
            }
        }

        .lf-intro-slide h1 {
            font-size: 2.2em;
            color: var(--ast-global-color-1);
            margin: 10px 0;
            font-weight: 700;
        }

        .lf-intro-slide h2 {
            font-size: 1.8em;
            color: var(--ast-global-color-1);
            margin: 20px 0 15px;
            font-weight: 600;
        }

        .lf-tagline {
            font-size: 1em;
            color: var(--ast-global-color-1);
            font-weight: 600;
            margin: 15px 0 25px;
            letter-spacing: 1px;
        }

        .lf-description {
            color: var(--ast-global-color-1);
            font-size: 1.05em;
            line-height: 1.7;
            margin: 15px 0;
        }

        .lf-feature-icon {
            width: 100px;
            height: 100px;
            border-radius: 50%;
            display: flex;
            align-items: center;
            justify-content: center;
            margin: 0 auto 25px;
            font-size: 3em;
            color: var(--ast-global-color-1);
            box-shadow: 0 10px 30px rgba(0,0,0,0.2);
        }

        .lf-subjects-grid {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 20px 0;
        }

        .lf-subject-card {
            background: var(--ast-global-color-6);
            padding: 20px 15px;
            border-radius: 12px;
            text-align: center;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
        }

        .lf-subject-card:hover {
            transform: translateY(-5px);
            box-shadow: 0 8px 20px rgba(0,0,0,0.1);
        }

        .lf-subject-card i {
            font-size: 2.5em;
            color: var(--ast-global-color-0);
            margin-bottom: 10px;
        }

        .lf-subject-card h3 {
            font-size: 1.1em;
            color: var(--ast-global-color-7);
            margin: 10px 0 5px;
            font-weight: 600;
        }

        .lf-subject-card p {
            font-size: 0.85em;
            color: var(--ast-global-color-7);
            margin: 0;
        }

        .lf-features-list {
            text-align: left;
            max-width: 420px;
            margin: 0 auto;
        }

        .lf-feature-item {
            display: flex;
            align-items: flex-start;
            gap: 15px;
            margin-bottom: 25px;
        }

        .lf-feature-item i {
            font-size: 2em;
            color: var(--ast-global-color-0);
            min-width: 40px;
            margin-top: 5px;
        }

        .lf-feature-item h3 {
            font-size: 1.1em;
            color: var(--ast-global-color-7);
            margin: 0 0 5px;
            font-weight: 600;
        }

        .lf-feature-item p {
            font-size: 0.95em;
            color: var(--ast-global-color-7);
            margin: 0;
            line-height: 1.5;
        }

        .lf-cta-message {
            font-size: 1.1em;
            color: var(--ast-global-color-7);
            margin: 20px 0 30px;
            line-height: 1.6;
        }

        .lf-get-started-btn,
        .lf-get-started-final {
            background: linear-gradient(135deg, var(--ast-global-color-4) 0%, var(--ast-global-color-5) 100%);
            color: var(--ast-global-color-1);
            border: none;
            padding: 15px 40px;
            font-size: 1.1em;
            border-radius: 30px;
            cursor: pointer;
            font-weight: 600;
            transition: transform 0.3s ease, box-shadow 0.3s ease;
            box-shadow: 0 5px 20px rgba(24, 32, 44, 0.4);
        }

        .lf-get-started-btn:hover,
        .lf-get-started-btn:active,
        .lf-get-started-final:hover,
        .lf-get-started-final:active {
            transform: translateY(-2px);
            box-shadow: 0 10px 30px rgba(24, 32, 44, 0.6);
        }

        .lf-intro-navigation {
            position: absolute;
            bottom: 0;
            left: 0;
            right: 0;
            padding: 20px;
            padding-bottom: calc(20px + env(safe-area-inset-bottom));
            display: flex;
            justify-content: space-between;
            align-items: center;
            background: var(--ast-global-color-1);
            border-top: 1px solid var(--ast-global-color-6);
        }

        .lf-intro-skip,
        .lf-intro-next {
            background: transparent;
            border: none;
            color: var(--ast-global-color-0);
            font-size: 1em;
            cursor: pointer;
            font-weight: 600;
            padding: 10px 20px;
            transition: color 0.3s ease;
        }

        .lf-intro-skip:hover,
        .lf-intro-skip:active,
        .lf-intro-next:hover,
        .lf-intro-next:active {
            color: var(--ast-global-color-8);
        }

        .lf-intro-dots {
            display: flex;
            gap: 8px;
        }

        .lf-dot {
            width: 8px;
            height: 8px;
            border-radius: 50%;
            background: var(--ast-global-color-6);
            cursor: pointer;
            transition: all 0.3s ease;
        }

        .lf-dot.active {
            background: var(--ast-global-color-0);
            width: 24px;
            border-radius: 4px;
        }

        body.lf-intro-active {
            overflow: hidden;
            position: fixed;
            width: 100%;
            height: 100vh;
            height: 100dvh;
        }

        @media (max-width: 600px) {
            .lf-intro-container {
                width: 95%;
                height: 90vh;
                height: 90dvh;
            }

            .lf-intro-slide {
                padding: 20px 15px;
            }

            .lf-intro-slide h1 {
                font-size: 1.8em;
            }

            .lf-intro-slide h2 {
                font-size: 1.5em;
            }

            .lf-feature-icon {
                width: 80px;
                height: 80px;
                font-size: 2.5em;
            }

            .lf-subjects-grid {
                gap: 12px;
            }

            .lf-subject-card {
                padding: 15px 10px;
            }

            .lf-subject-card i {
                font-size: 2em;
            }

            .lf-feature-item i {
                font-size: 1.8em;
            }
        }

        /* Hide on desktop */
        @media (min-width: 769px) {
            #lf-intro-overlay {
                display: none !important;
            }
        }
    </style>
    <link rel="stylesheet" href="https://cdnjs.cloudflare.com/ajax/libs/font-awesome/6.5.1/css/all.min.css" integrity="sha512-DTOQO9RWCH3ppGqcWaEA1BIZOC6xxalwEsw9c2QQeAIftl+Vegovlnee1c9QX4TctnWMn13TZye+giMm8e2LwA==" crossorigin="anonymous" referrerpolicy="no-referrer" />
    <script>
        (function() {
            // Only show on mobile devices
            function isMobile() {
                return window.innerWidth <= 768 || /Android|webOS|iPhone|iPad|iPod|BlackBerry|IEMobile|Opera Mini/i.test(navigator.userAgent);
            }

            if (!isMobile()) {
                return; // Exit if not mobile
            }

            const overlay = document.getElementById('lf-intro-overlay');
            const container = document.querySelector('.lf-intro-container');
            const slides = document.querySelectorAll('.lf-intro-slide');
            const dots = document.querySelectorAll('.lf-dot');
            const nextBtn = document.querySelector('.lf-intro-next');
            const skipBtn = document.querySelector('.lf-intro-skip');
            const getStartedBtns = document.querySelectorAll('.lf-get-started-btn, .lf-get-started-final');
            
            let currentSlide = 1;
            const totalSlides = slides.length;

            // Show intro on page load
            function startIntro() {
                overlay.style.display = 'flex';
                document.body.classList.add('lf-intro-active');
                
                setTimeout(() => {
                    overlay.classList.add('show');
                    setTimeout(() => {
                        container.classList.add('visible');
                    }, 300);
                }, 100);
            }

            // Start intro after page load
            window.addEventListener('load', function() {
                setTimeout(startIntro, 500);
            });

            function goToSlide(slideNum) {
                slides.forEach(slide => {
                    slide.classList.remove('active', 'prev');
                });
                dots.forEach(dot => {
                    dot.classList.remove('active');
                });

                slides[slideNum - 1].classList.add('active');
                dots[slideNum - 1].classList.add('active');
                currentSlide = slideNum;

                if (currentSlide === totalSlides) {
                    nextBtn.style.display = 'none';
                } else {
                    nextBtn.style.display = 'block';
                }
            }

            function closeIntro(redirectUrl) {
                if (typeof window.showLFLoading === 'function') {
                    window.showLFLoading();
                }
                
                overlay.classList.remove('show');
                setTimeout(() => {
                    overlay.style.display = 'none';
                    document.body.classList.remove('lf-intro-active');
                }, 300);
                
                if (redirectUrl) {
                    setTimeout(() => {
                        window.location.href = redirectUrl;
                    }, 400);
                }
            }

            nextBtn.addEventListener('click', () => {
                if (currentSlide < totalSlides) {
                    goToSlide(currentSlide + 1);
                }
            });

            skipBtn.addEventListener('click', () => {
                const redirectUrl = skipBtn.getAttribute('data-redirect');
                closeIntro(redirectUrl);
            });

            getStartedBtns.forEach(btn => {
                btn.addEventListener('click', () => {
                    const redirectUrl = btn.getAttribute('data-redirect');
                    closeIntro(redirectUrl);
                });
            });

            dots.forEach(dot => {
                dot.addEventListener('click', () => {
                    const slideNum = parseInt(dot.getAttribute('data-slide'));
                    goToSlide(slideNum);
                });
            });

            // Swipe functionality
            let touchStartX = 0;
            let touchEndX = 0;

            overlay.addEventListener('touchstart', (e) => {
                touchStartX = e.changedTouches[0].screenX;
            }, { passive: true });

            overlay.addEventListener('touchend', (e) => {
                touchEndX = e.changedTouches[0].screenX;
                handleSwipe();
            }, { passive: true });

            function handleSwipe() {
                if (touchStartX - touchEndX > 50 && currentSlide < totalSlides) {
                    goToSlide(currentSlide + 1);
                } else if (touchEndX - touchStartX > 50 && currentSlide > 1) {
                    goToSlide(currentSlide - 1);
                }
            }
        })();
    </script>
    <?php
}

/**
 * Combined shortcode for intro and loading
 */
function display_lf_intro_shortcode() {
    ob_start();
    
    lf_intro_slides();
    lf_loading_screen();
    
    return ob_get_clean();
}
add_shortcode('lf_intro', 'display_lf_intro_shortcode');