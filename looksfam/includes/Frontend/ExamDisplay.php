<?php
/**
 * Frontend Exam Display Handler
 * 
 * REFACTORED: Uses Looksfam\Core\Database instead of WP Posts
 */

namespace Looksfam\Frontend;

use Looksfam\Core\Database;

if (!defined('ABSPATH')) {
    exit;
}

class ExamDisplay {

    private $db;

    public function __construct() {
        $this->db = new Database();
        add_shortcode('looksfam_exam', array($this, 'render_exam'));
        add_action('wp_ajax_looksfam_start_exam', array($this, 'ajax_start_exam'));
        add_action('wp_ajax_looksfam_submit_exam', array($this, 'ajax_submit_exam'));
    }

    /**
     * Render exam shortcode
     */
    public function render_exam($atts) {
        $atts = shortcode_atts(array(
            'id' => 0
        ), $atts);

        $exam_id = intval($atts['id']);

        if (!$exam_id) {
            return '<p class="looksfam-error">Invalid exam ID provided.</p>';
        }

        // Get exam from custom table
        $exam = $this->db->get_exam($exam_id);

        if (!$exam) {
            return '<p class="looksfam-error">Exam not found.</p>';
        }

        // Check user enrollment/access
        if (!$this->user_can_access_exam($exam_id)) {
            return '<p class="looksfam-error">You do not have access to this exam.</p>';
        }

        ob_start();
        include LOOKSFAM_PLUGIN_DIR . 'frontend/views/exam-display.php';
        return ob_get_clean();
    }

    /**
     * Start exam session
     */
    public function ajax_start_exam() {
        check_ajax_referer('looksfam_exam_nonce', 'security');

        $exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0;
        $user_id = get_current_user_id();

        if (!$exam_id || !$user_id) {
            wp_send_json_error('Invalid request');
        }

        // Generate unique session ID
        $session_id = uniqid('exam_' . $user_id . '_');

        // Store session start
        set_transient('looksfam_exam_session_' . $session_id, array(
            'exam_id' => $exam_id,
            'user_id' => $user_id,
            'started_at' => current_time('mysql')
        ), HOUR_IN_SECONDS);

        wp_send_json_success(array(
            'session_id' => $session_id,
            'exam' => $this->db->get_exam($exam_id)
        ));
    }

    /**
     * Submit exam answers
     */
    public function ajax_submit_exam() {
        check_ajax_referer('looksfam_exam_nonce', 'security');

        $session_id = isset($_POST['session_id']) ? sanitize_text_field($_POST['session_id']) : '';
        $answers = isset($_POST['answers']) ? $_POST['answers'] : array();

        if (!$session_id || empty($answers)) {
            wp_send_json_error('Invalid submission');
        }

        // Get session data
        $session_data = get_transient('looksfam_exam_session_' . $session_id);

        if (!$session_data) {
            wp_send_json_error('Session expired or invalid');
        }

        global $wpdb;
        $results_table = $this->db->tables['exam_results'];
        $answers_table = $this->db->tables['exam_answers'];

        $correct_count = 0;
        $total_count = count($answers);

        // Save individual answers and calculate score
        foreach ($answers as $question_id => $user_answer) {
            // Get correct answer
            $question = $this->db->get_question($question_id);
            $is_correct = ($user_answer === $question['correct_answer']) ? 1 : 0;

            if ($is_correct) {
                $correct_count++;
            }

            // Insert into exam_answers table
            $wpdb->insert(
                $answers_table,
                array(
                    'exam_id' => $session_data['exam_id'],
                    'class_id' => 0, // Can be populated if needed
                    'question_id' => $question_id,
                    'user_id' => $session_data['user_id'],
                    'user_answer' => $user_answer,
                    'is_correct' => $is_correct,
                    'session_id' => $session_id,
                    'timestamp' => current_time('mysql')
                )
            );
        }

        // Calculate final score
        $score = $total_count > 0 ? ($correct_count / $total_count) * 100 : 0;

        // Insert exam result
        $wpdb->insert(
            $results_table,
            array(
                'exam_id' => $session_data['exam_id'],
                'user_id' => $session_data['user_id'],
                'score' => $score,
                'total_questions' => $total_count,
                'correct_answers' => $correct_count,
                'session_id' => $session_id,
                'started_at' => $session_data['started_at'],
                'completed_at' => current_time('mysql')
            )
        );

        // Clear session
        delete_transient('looksfam_exam_session_' . $session_id);

        wp_send_json_success(array(
            'score' => $score,
            'correct' => $correct_count,
            'total' => $total_count,
            'result_id' => $wpdb->insert_id
        ));
    }

    /**
     * Check if user can access exam
     */
    private function user_can_access_exam($exam_id) {
        $user_id = get_current_user_id();

        if (!$user_id) {
            return false;
        }

        // Admin can access all
        if (current_user_can('manage_options')) {
            return true;
        }

        // Check enrollment in associated classes
        $exam = $this->db->get_exam($exam_id);
        if (!$exam || empty($exam['associated_classes'])) {
            return true; // No restrictions
        }

        $classes = json_decode($exam['associated_classes'], true);

        if (empty($classes)) {
            return true;
        }

        global $wpdb;
        $enrollments_table = $this->db->tables['enrollments'];

        $placeholders = implode(',', array_fill(0, count($classes), '%d'));
        $query = $wpdb->prepare(
            "SELECT COUNT(*) FROM {$enrollments_table} WHERE user_id = %d AND class_id IN ($placeholders) AND status = 'active'",
            array_merge(array($user_id), $classes)
        );

        $enrolled = $wpdb->get_var($query);

        return $enrolled > 0;
    }
}
