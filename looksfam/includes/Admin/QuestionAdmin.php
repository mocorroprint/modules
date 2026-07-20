<?php
/**
 * Admin Question Handler
 * 
 * REFACTORED: Uses Looksfam\Core\Database instead of WP Posts
 */

namespace Looksfam\Admin;

use Looksfam\Core\Database;

if (!defined('ABSPATH')) {
    exit;
}

class QuestionAdmin {
    
    private $db;

    public function __construct() {
        $this->db = new Database();
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_looksfam_save_question', array($this, 'ajax_save_question'));
        add_action('wp_ajax_looksfam_get_question', array($this, 'ajax_get_question'));
        add_action('wp_ajax_looksfam_delete_question', array($this, 'ajax_delete_question'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'Questions',
            'Questions',
            'manage_options',
            'looksfam-questions',
            array($this, 'render_questions_page'),
            'dashicons-editor-help',
            7
        );
    }

    public function render_questions_page() {
        include LOOKSFAM_PLUGIN_DIR . 'admin/views/questions-list.php';
    }

    /**
     * Save Question - REFACTORED
     * Previously used wp_insert_post and update_post_meta
     */
    public function ajax_save_question() {
        check_ajax_referer('looksfam_question_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
        $data = array(
            'title' => sanitize_text_field($_POST['title']),
            'content' => wp_kses_post($_POST['content']),
            'question_type' => sanitize_text_field($_POST['question_type']),
            'subject_id' => intval($_POST['subject_id']),
            'topic_id' => intval($_POST['topic_id']),
            'subtopic_id' => intval($_POST['subtopic_id']),
            'multiple_choice_options' => json_encode($_POST['options'] ?? array()),
            'correct_answer' => sanitize_text_field($_POST['correct_answer']),
            'solution' => wp_kses_post($_POST['solution']),
            'difficulty_level' => sanitize_text_field($_POST['difficulty'])
        );

        try {
            if ($question_id > 0) {
                $result = $this->db->update_question($question_id, $data);
            } else {
                $result = $this->db->insert_question($data);
                $question_id = $result;
            }

            if ($result) {
                wp_send_json_success(array('question_id' => $question_id));
            } else {
                wp_send_json_error('Database operation failed');
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Get Question - REFACTORED
     */
    public function ajax_get_question() {
        check_ajax_referer('looksfam_question_nonce', 'security');

        $question_id = isset($_GET['question_id']) ? intval($_GET['question_id']) : 0;
        
        if (!$question_id) {
            wp_send_json_error('Invalid ID');
        }

        $question = $this->db->get_question($question_id);
        
        if ($question) {
            wp_send_json_success($question);
        } else {
            wp_send_json_error('Question not found');
        }
    }

    /**
     * Delete Question - REFACTORED
     */
    public function ajax_delete_question() {
        check_ajax_referer('looksfam_question_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $question_id = isset($_POST['question_id']) ? intval($_POST['question_id']) : 0;
        
        if (!$question_id) {
            wp_send_json_error('Invalid ID');
        }

        try {
            $result = $this->db->delete_question($question_id);
            
            if ($result) {
                wp_send_json_success('Question deleted');
            } else {
                wp_send_json_error('Failed to delete question');
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }
}
