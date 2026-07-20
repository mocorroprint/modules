<?php
/**
 * Admin Exam Handler
 * 
 * REFACTORED: Uses Looksfam\Core\Database instead of WP Posts
 */

namespace Looksfam\Admin;

use Looksfam\Core\Database;

if (!defined('ABSPATH')) {
    exit;
}

class ExamAdmin {
    
    private $db;

    public function __construct() {
        $this->db = new Database();
        add_action('admin_menu', array($this, 'add_admin_menu'));
        add_action('wp_ajax_looksfam_save_exam', array($this, 'ajax_save_exam'));
        add_action('wp_ajax_looksfam_get_exam', array($this, 'ajax_get_exam'));
    }

    public function add_admin_menu() {
        add_menu_page(
            'Exams',
            'Exams',
            'manage_options',
            'looksfam-exams',
            array($this, 'render_exams_page'),
            'dashicons-welcome-learn-more',
            6
        );
    }

    public function render_exams_page() {
        include LOOKSFAM_PLUGIN_DIR . 'admin/views/exams-list.php';
    }

    /**
     * Save Exam - REFACTORED
     * Previously used wp_insert_post and update_post_meta
     */
    public function ajax_save_exam() {
        check_ajax_referer('looksfam_exam_nonce', 'security');

        if (!current_user_can('manage_options')) {
            wp_send_json_error('Unauthorized');
        }

        $exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0;
        $data = array(
            'title' => sanitize_text_field($_POST['title']),
            'status' => sanitize_text_field($_POST['status']),
            'time_limit' => intval($_POST['time_limit']),
            'num_questions' => intval($_POST['num_questions']),
            'randomize_questions' => isset($_POST['randomize']) ? 1 : 0,
            'associated_classes' => json_encode($_POST['classes'] ?? array())
        );

        try {
            if ($exam_id > 0) {
                // Update existing
                $result = $this->db->update_exam($exam_id, $data);
            } else {
                // Insert new
                $result = $this->db->insert_exam($data);
                $exam_id = $result;
            }

            if ($result) {
                wp_send_json_success(array('exam_id' => $exam_id));
            } else {
                wp_send_json_error('Database operation failed');
            }
        } catch (\Exception $e) {
            wp_send_json_error($e->getMessage());
        }
    }

    /**
     * Get Exam - REFACTORED
     * Previously used get_post and get_post_meta
     */
    public function ajax_get_exam() {
        check_ajax_referer('looksfam_exam_nonce', 'security');

        $exam_id = isset($_GET['exam_id']) ? intval($_GET['exam_id']) : 0;
        
        if (!$exam_id) {
            wp_send_json_error('Invalid ID');
        }

        $exam = $this->db->get_exam($exam_id);
        
        if ($exam) {
            wp_send_json_success($exam);
        } else {
            wp_send_json_error('Exam not found');
        }
    }
}
