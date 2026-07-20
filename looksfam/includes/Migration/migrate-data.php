<?php
/**
 * Data Migration Script
 * Migrates data from WP Posts/PostMeta to Custom Tables
 * 
 * Usage: Run once via WP-CLI or temporary admin page
 * WP-CLI: wp eval-file includes/Migration/migrate-data.php
 */

namespace Looksfam\Migration;

use Looksfam\Core\Database;

if (!defined('ABSPATH')) {
    die('Direct access not allowed');
}

class DataMigrator {
    
    private $db;
    private $migrated_counts = array(
        'exams' => 0,
        'questions' => 0,
        'classes' => 0,
        'enrollments' => 0,
        'transactions' => 0,
        'exam_results' => 0
    );
    
    public function __construct() {
        $this->db = new Database();
    }
    
    /**
     * Run all migrations
     */
    public function run_all_migrations() {
        global $wpdb;
        
        echo "Starting Data Migration...\n\n";
        
        // Check if already migrated
        if (get_option('looksfam_migration_completed')) {
            echo "WARNING: Migration already completed. Running again may create duplicates.\n";
            echo "To force re-migration, delete option: looksfam_migration_completed\n\n";
        }
        
        $wpdb->query("SET autocommit=0");
        $wpdb->query("START TRANSACTION");
        
        try {
            $this->migrate_exams();
            $this->migrate_questions();
            $this->migrate_classes();
            $this->migrate_enrollments();
            $this->migrate_transactions();
            $this->migrate_exam_results();
            
            $wpdb->query("COMMIT");
            update_option('looksfam_migration_completed', true);
            update_option('looksfam_migration_date', current_time('mysql'));
            
            echo "\n✅ Migration completed successfully!\n";
            echo "Summary:\n";
            foreach ($this->migrated_counts as $type => $count) {
                echo "  - {$type}: {$count} records\n";
            }
            
        } catch (\Exception $e) {
            $wpdb->query("ROLLBACK");
            echo "\n❌ Migration failed: " . $e->getMessage() . "\n";
            echo "Transaction rolled back. No changes made.\n";
        }
    }
    
    /**
     * Migrate Exams from wp_posts to wp_looksfam_exams
     */
    private function migrate_exams() {
        global $wpdb;
        
        echo "Migrating exams...\n";
        
        $exams = get_posts(array(
            'post_type' => 'exam',
            'numberposts' => -1,
            'post_status' => 'any'
        ));
        
        foreach ($exams as $exam) {
            // Check if already migrated
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->db->tables['exams']} WHERE id = %d",
                $exam->ID
            ));
            
            if ($exists) {
                continue;
            }
            
            $data = array(
                'id' => $exam->ID,
                'title' => $exam->post_title,
                'status' => $exam->post_status,
                'time_limit' => intval(get_post_meta($exam->ID, 'time_limit', true)),
                'num_questions' => intval(get_post_meta($exam->ID, 'num_questions', true)),
                'randomize_questions' => intval(get_post_meta($exam->ID, 'randomize_questions', true)),
                'associated_classes' => json_encode(get_post_meta($exam->ID, 'associated_classes', true) ?: array()),
                'created_at' => $exam->post_date,
                'updated_at' => $exam->post_modified
            );
            
            $wpdb->insert($this->db->tables['exams'], $data);
            $this->migrated_counts['exams']++;
        }
        
        echo "  ✓ Migrated {$this->migrated_counts['exams']} exams\n";
    }
    
    /**
     * Migrate Questions from wp_posts to wp_looksfam_questions
     */
    private function migrate_questions() {
        global $wpdb;
        
        echo "Migrating questions...\n";
        
        $questions = get_posts(array(
            'post_type' => 'question',
            'numberposts' => -1,
            'post_status' => 'any'
        ));
        
        foreach ($questions as $question) {
            // Check if already migrated
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->db->tables['questions']} WHERE id = %d",
                $question->ID
            ));
            
            if ($exists) {
                continue;
            }
            
            // Get taxonomy terms
            $terms = wp_get_post_terms($question->ID, 'question_category', array('fields' => 'ids'));
            $subject_id = !empty($terms) ? $terms[0] : null;
            $topic_id = !empty($terms[1]) ? $terms[1] : null;
            $subtopic_id = !empty($terms[2]) ? $terms[2] : null;
            
            $data = array(
                'id' => $question->ID,
                'title' => $question->post_title,
                'content' => $question->post_content,
                'question_type' => 'multiple_choice', // Default, can be enhanced
                'subject_id' => $subject_id,
                'topic_id' => $topic_id,
                'subtopic_id' => $subtopic_id,
                'multiple_choice_options' => json_encode(get_post_meta($question->ID, 'multiple_choice_options', true)),
                'correct_answer' => get_post_meta($question->ID, 'correct_answer', true),
                'solution' => get_post_meta($question->ID, 'solution', true),
                'difficulty_level' => get_post_meta($question->ID, 'difficulty_level', true) ?: 'medium',
                'created_at' => $question->post_date,
                'updated_at' => $question->post_modified
            );
            
            $wpdb->insert($this->db->tables['questions'], $data);
            $this->migrated_counts['questions']++;
        }
        
        echo "  ✓ Migrated {$this->migrated_counts['questions']} questions\n";
    }
    
    /**
     * Migrate Classes from wp_posts to wp_looksfam_classes
     */
    private function migrate_classes() {
        global $wpdb;
        
        echo "Migrating classes...\n";
        
        $classes = get_posts(array(
            'post_type' => 'class',
            'numberposts' => -1,
            'post_status' => 'any'
        ));
        
        foreach ($classes as $class) {
            // Check if already migrated
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->db->tables['classes']} WHERE id = %d",
                $class->ID
            ));
            
            if ($exists) {
                continue;
            }
            
            $data = array(
                'id' => $class->ID,
                'title' => $class->post_title,
                'description' => $class->post_content,
                'subject_id' => intval(get_post_meta($class->ID, 'exam_subject', true)),
                'topic_id' => intval(get_post_meta($class->ID, 'exam_topic', true)),
                'price' => floatval(get_post_meta($class->ID, 'price', true)),
                'enrollment_key' => get_post_meta($class->ID, 'enrollment_key', true),
                'status' => 'active',
                'created_at' => $class->post_date,
                'updated_at' => $class->post_modified
            );
            
            $wpdb->insert($this->db->tables['classes'], $data);
            $this->migrated_counts['classes']++;
        }
        
        echo "  ✓ Migrated {$this->migrated_counts['classes']} classes\n";
    }
    
    /**
     * Migrate Enrollments from post_meta to wp_looksfam_enrollments
     */
    private function migrate_enrollments() {
        global $wpdb;
        
        echo "Migrating enrollments...\n";
        
        $classes = get_posts(array(
            'post_type' => 'class',
            'numberposts' => -1,
            'fields' => 'ids'
        ));
        
        foreach ($classes as $class_id) {
            $enrolled_students = get_post_meta($class_id, 'enrolled_students', true);
            
            if (!is_array($enrolled_students)) {
                continue;
            }
            
            foreach ($enrolled_students as $user_id) {
                // Check for duplicate
                $exists = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM {$this->db->tables['enrollments']} WHERE class_id = %d AND user_id = %d",
                    $class_id,
                    $user_id
                ));
                
                if ($exists) {
                    continue;
                }
                
                $data = array(
                    'class_id' => $class_id,
                    'user_id' => $user_id,
                    'enrollment_date' => current_time('mysql'),
                    'status' => 'active',
                    'payment_status' => 'paid' // Assume paid if already enrolled
                );
                
                $wpdb->insert($this->db->tables['enrollments'], $data);
                $this->migrated_counts['enrollments']++;
            }
        }
        
        echo "  ✓ Migrated {$this->migrated_counts['enrollments']} enrollments\n";
    }
    
    /**
     * Migrate Transactions from wp_posts to wp_looksfam_transactions
     */
    private function migrate_transactions() {
        global $wpdb;
        
        echo "Migrating transactions...\n";
        
        $transactions = get_posts(array(
            'post_type' => 'transaction',
            'numberposts' => -1,
            'post_status' => 'any'
        ));
        
        foreach ($transactions as $transaction) {
            // Check if already migrated
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->db->tables['transactions']} WHERE id = %d",
                $transaction->ID
            ));
            
            if ($exists) {
                continue;
            }
            
            $data = array(
                'id' => $transaction->ID,
                'user_id' => intval(get_post_meta($transaction->ID, 'user_id', true)),
                'class_id' => intval(get_post_meta($transaction->ID, 'class_id', true)),
                'amount' => floatval(get_post_meta($transaction->ID, 'amount', true)),
                'payment_method' => get_post_meta($transaction->ID, 'payment_method', true),
                'payment_status' => $transaction->post_status,
                'transaction_reference' => get_post_meta($transaction->ID, 'transaction_reference', true),
                'created_at' => $transaction->post_date,
                'updated_at' => $transaction->post_modified
            );
            
            $wpdb->insert($this->db->tables['transactions'], $data);
            $this->migrated_counts['transactions']++;
        }
        
        echo "  ✓ Migrated {$this->migrated_counts['transactions']} transactions\n";
    }
    
    /**
     * Migrate Exam Results from wp_exam_answers to wp_looksfam_exam_results
     */
    private function migrate_exam_results() {
        global $wpdb;
        
        echo "Migrating exam results...\n";
        
        $old_table = $wpdb->prefix . 'exam_answers';
        
        // Check if old table exists
        if ($wpdb->get_var("SHOW TABLES LIKE '$old_table'") !== $old_table) {
            echo "  ⊘ Old table {$old_table} does not exist. Skipping.\n";
            return;
        }
        
        $results = $wpdb->get_results("SELECT * FROM {$old_table}", ARRAY_A);
        
        // Group by session_id to aggregate results
        $sessions = array();
        foreach ($results as $row) {
            $session_id = $row['session_id'];
            if (!isset($sessions[$session_id])) {
                $sessions[$session_id] = array(
                    'exam_id' => $row['exam_id'],
                    'class_id' => $row['class_id'],
                    'user_id' => $row['user_id'],
                    'correct_answers' => 0,
                    'total_questions' => 0,
                    'started_at' => $row['timestamp'],
                    'completed_at' => $row['timestamp']
                );
            }
            
            $sessions[$session_id]['total_questions']++;
            if ($row['is_correct']) {
                $sessions[$session_id]['correct_answers']++;
            }
            
            // Update timestamps
            if ($row['timestamp'] < $sessions[$session_id]['started_at']) {
                $sessions[$session_id]['started_at'] = $row['timestamp'];
            }
            if ($row['timestamp'] > $sessions[$session_id]['completed_at']) {
                $sessions[$session_id]['completed_at'] = $row['timestamp'];
            }
        }
        
        // Insert aggregated results
        foreach ($sessions as $session_id => $data) {
            // Check if already migrated
            $exists = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM {$this->db->tables['exam_results']} WHERE session_id = %s",
                $session_id
            ));
            
            if ($exists) {
                continue;
            }
            
            $time_taken = strtotime($data['completed_at']) - strtotime($data['started_at']);
            $score = $data['total_questions'] > 0 
                ? ($data['correct_answers'] / $data['total_questions']) * 100 
                : 0;
            
            $insert_data = array(
                'exam_id' => $data['exam_id'],
                'class_id' => $data['class_id'],
                'user_id' => $data['user_id'],
                'score' => $score,
                'total_questions' => $data['total_questions'],
                'correct_answers' => $data['correct_answers'],
                'time_taken' => $time_taken,
                'session_id' => $session_id,
                'started_at' => $data['started_at'],
                'completed_at' => $data['completed_at']
            );
            
            $wpdb->insert($this->db->tables['exam_results'], $insert_data);
            $this->migrated_counts['exam_results']++;
        }
        
        echo "  ✓ Migrated {$this->migrated_counts['exam_results']} exam results\n";
    }
    
    /**
     * Rollback migration (for testing)
     */
    public function rollback() {
        global $wpdb;
        
        echo "Rolling back migration...\n";
        
        $wpdb->query("START TRANSACTION");
        
        try {
            // Truncate custom tables (keeps structure, removes data)
            foreach ($this->db->tables as $table_name) {
                $wpdb->query("TRUNCATE TABLE {$table_name}");
            }
            
            delete_option('looksfam_migration_completed');
            delete_option('looksfam_migration_date');
            
            $wpdb->query("COMMIT");
            
            echo "✅ Rollback completed successfully!\n";
            
        } catch (\Exception $e) {
            $wpdb->query("ROLLBACK");
            echo "❌ Rollback failed: " . $e->getMessage() . "\n";
        }
    }
}

// CLI Command Registration
if (defined('WP_CLI') && WP_CLI) {
    \WP_CLI::add_command('looksfam migrate', function($args, $assoc_args) {
        $migrator = new DataMigrator();
        
        if (isset($assoc_args['rollback'])) {
            $migrator->rollback();
        } else {
            $migrator->run_all_migrations();
        }
    });
}
