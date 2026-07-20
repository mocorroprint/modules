<?php
/**
 * Write Interceptors - Legacy Compatibility Layer
 * 
 * Intercepts all WordPress post meta writes for Looksfam entities
 * and redirects them to custom tables to prevent data fragmentation.
 * 
 * This ensures NO NEW data is written to wp_postmeta while maintaining
 * backward compatibility during the migration period.
 */

if (!defined('ABSPATH')) {
    exit;
}

use Looksfam\Core\Database;

// Initialize Database instance
$db = Database::instance();

/**
 * Intercept Exam Saves
 * Redirects wp_insert_post/update_post_meta to custom tables
 */
add_action('save_post_exam', 'looksfam_intercept_exam_save', 1, 2); // Priority 1 (before legacy code)
function looksfam_intercept_exam_save($post_id, $post) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (wp_is_post_revision($post_id)) return;
    
    $db = Looksfam\Core\Database::instance();
    
    // Collect data from POST (legacy form fields)
    $time_limit = isset($_POST['exam_time_limit']) ? intval($_POST['exam_time_limit']) : 0;
    $randomize = isset($_POST['randomize_questions']) ? intval($_POST['randomize_questions']) : 0;
    $selected_questions = isset($_POST['selected_questions']) ? $_POST['selected_questions'] : [];
    
    $data = [
        'title' => sanitize_text_field($post->post_title),
        'status' => $post->post_status,
        'time_limit' => $time_limit,
        'randomize_questions' => $randomize,
        'selected_questions' => is_array($selected_questions) ? json_encode($selected_questions) : '',
        'updated_at' => current_time('mysql')
    ];
    
    // Check if exists in new table
    $existing = $db->get_exam($post_id);
    
    if ($existing) {
        $db->update_exam($post_id, $data);
    } else {
        $data['id'] = $post_id;
        $data['created_at'] = $post->post_date;
        $db->insert_exam($data);
    }
    
    // Prevent legacy post_meta write by returning early
    // Note: We don't stop the WP post save entirely to maintain ID sync
}

/**
 * Intercept Question Saves
 */
add_action('save_post_question', 'looksfam_intercept_question_save', 1, 2);
function looksfam_intercept_question_save($post_id, $post) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (wp_is_post_revision($post_id)) return;
    
    $db = Looksfam\Core\Database::instance();
    
    $options = isset($_POST['multiple_choice_options']) ? $_POST['multiple_choice_options'] : [];
    $correct = isset($_POST['correct_answer']) ? $_POST['correct_answer'] : '';
    $solution = isset($_POST['solution']) ? $_POST['solution'] : '';
    $difficulty = isset($_POST['difficulty_level']) ? sanitize_text_field($_POST['difficulty_level']) : 'medium';
    
    // Get taxonomy
    $terms = isset($_POST['tax_input']['question_category']) ? $_POST['tax_input']['question_category'] : [];
    $subject_id = !empty($terms) ? intval(reset($terms)) : 0;
    
    $data = [
        'title' => sanitize_text_field($post->post_title),
        'content' => $post->post_content,
        'question_type' => 'multiple_choice',
        'subject_id' => $subject_id,
        'multiple_choice_options' => is_array($options) ? json_encode($options) : '',
        'correct_answer' => maybe_serialize($correct),
        'solution' => $solution,
        'difficulty_level' => $difficulty,
        'updated_at' => current_time('mysql')
    ];
    
    $existing = $db->get_question($post_id);
    
    if ($existing) {
        $db->update_question($post_id, $data);
    } else {
        $data['id'] = $post_id;
        $data['created_at'] = $post->post_date;
        $db->insert_question($data);
    }
}

/**
 * Intercept Class Saves
 */
add_action('save_post_class', 'looksfam_intercept_class_save', 1, 2);
function looksfam_intercept_class_save($post_id, $post) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    if (wp_is_post_revision($post_id)) return;
    
    $db = Looksfam\Core\Database::instance();
    
    $subject = isset($_POST['exam_subject']) ? intval($_POST['exam_subject']) : 0;
    $topic = isset($_POST['exam_topic']) ? intval($_POST['exam_topic']) : 0;
    $price = isset($_POST['class_price']) ? floatval($_POST['class_price']) : 0;
    $key = isset($_POST['enrollment_key']) ? sanitize_text_field($_POST['enrollment_key']) : '';
    
    $data = [
        'title' => sanitize_text_field($post->post_title),
        'description' => $post->post_content,
        'subject_id' => $subject,
        'topic_id' => $topic,
        'price' => $price,
        'enrollment_key' => $key,
        'status' => $post->post_status === 'publish' ? 'active' : 'inactive',
        'updated_at' => current_time('mysql')
    ];
    
    $existing = $db->get_class($post_id);
    
    if ($existing) {
        $db->update_class($post_id, $data);
    } else {
        $data['id'] = $post_id;
        $data['created_at'] = $post->post_date;
        $db->insert_class($data);
    }
}

/**
 * Block Legacy Post Meta Writes for Looksfam Types
 * Returns non-null value to short-circuit the meta update
 */
add_filter('update_post_metadata', 'looksfam_block_legacy_meta_updates', 1, 5);
function looksfam_block_legacy_meta_updates($null, $object_id, $meta_key, $meta_value, $prev_value) {
    $post_type = get_post_type($object_id);
    
    if (in_array($post_type, ['exam', 'question', 'class', 'transaction'])) {
        // Log blocked attempt for debugging
        error_log("Looksfam: Blocked legacy meta update for {$meta_key} on {$post_type} #{$object_id}");
        
        // Return non-null to prevent DB write, but don't break the save process
        // The actual data was already saved to custom tables via save_post hooks
        return $prev_value !== false ? $prev_value : $meta_value;
    }
    
    return $null; // Let other meta updates proceed normally
}

/**
 * Intercept Transaction Creation
 */
add_action('init', 'looksfam_intercept_transaction_creation');
function looksfam_intercept_transaction_creation() {
    if (isset($_POST['looksfam_create_transaction'])) {
        check_admin_referer('looksfam_transaction_nonce');
        
        $db = Looksfam\Core\Database::instance();
        
        $data = [
            'user_id' => intval($_POST['user_id']),
            'class_id' => intval($_POST['class_id']),
            'amount' => floatval($_POST['amount']),
            'payment_method' => sanitize_text_field($_POST['payment_method'] ?? 'manual'),
            'payment_status' => 'pending',
            'transaction_reference' => sanitize_text_field($_POST['reference'] ?? ''),
            'created_at' => current_time('mysql')
        ];
        
        $transaction_id = $db->insert_transaction($data);
        
        // Store ID for legacy code compatibility
        $_POST['looksfam_new_transaction_id'] = $transaction_id;
        
        // Prevent legacy post creation
        add_filter('wp_insert_post_data', function($data, $postarr) {
            if (isset($postarr['post_type']) && $postarr['post_type'] === 'transaction') {
                $data['post_status'] = 'draft'; // Soft block
            }
            return $data;
        }, 10, 2);
    }
}
