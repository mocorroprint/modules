<?php
/**
 * Database Service Class
 * 
 * Centralized database access layer for Looksfam plugin.
 * Replaces all wp_posts/wp_postmeta operations with custom table queries.
 *
 * @package Looksfam\Core
 */

namespace Looksfam\Core;

use WP_Error;
use wpdb;

class Database {
    
    /**
     * Table names with prefix
     */
    public $tables = [];
    
    /**
     * Constructor - Initialize table names
     */
    public function __construct() {
        global $wpdb;
        
        $prefix = $wpdb->prefix . 'looksfam_';
        
        $this->tables = [
            'exams'        => $prefix . 'exams',
            'questions'    => $prefix . 'questions',
            'classes'      => $prefix . 'classes',
            'enrollments'  => $prefix . 'enrollments',
            'transactions' => $prefix . 'transactions',
            'exam_results' => $prefix . 'exam_results',
            'exam_answers' => $wpdb->prefix . 'exam_answers', // Legacy table
        ];
    }
    
    /**
     * Get table name by key
     * 
     * @param string $table_key
     * @return string
     */
    public function get_table( $table_key ) {
        return isset( $this->tables[ $table_key ] ) ? $this->tables[ $table_key ] : '';
    }
    
    /**
     * Create all custom tables
     * Called during plugin activation
     */
    public static function create_tables() {
        global $wpdb;
        $charset_collate = $wpdb->get_charset_collate();
        
        $sql = "
        -- Exams Table
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}looksfam_exams (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            status ENUM('draft', 'published', 'archived') DEFAULT 'draft',
            time_limit INT DEFAULT 0,
            num_questions INT DEFAULT 0,
            randomize_questions TINYINT(1) DEFAULT 0,
            associated_classes TEXT,
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_status (status),
            INDEX idx_created (created_at)
        ) $charset_collate;
        
        -- Questions Table
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}looksfam_questions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            content LONGTEXT,
            question_type ENUM('multiple_choice', 'true_false', 'essay') DEFAULT 'multiple_choice',
            subject_id BIGINT UNSIGNED,
            topic_id BIGINT UNSIGNED,
            subtopic_id BIGINT UNSIGNED,
            multiple_choice_options TEXT,
            correct_answer TEXT,
            solution LONGTEXT,
            difficulty_level ENUM('easy', 'medium', 'hard') DEFAULT 'medium',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_subject (subject_id),
            INDEX idx_topic (topic_id),
            INDEX idx_type (question_type)
        ) $charset_collate;
        
        -- Classes Table
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}looksfam_classes (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            title VARCHAR(255) NOT NULL,
            description LONGTEXT,
            subject_id BIGINT UNSIGNED,
            topic_id BIGINT UNSIGNED,
            price DECIMAL(10,2) DEFAULT 0.00,
            enrollment_key VARCHAR(50),
            status ENUM('active', 'inactive', 'archived') DEFAULT 'active',
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_subject (subject_id),
            INDEX idx_status (status)
        ) $charset_collate;
        
        -- Enrollments Table
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}looksfam_enrollments (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            class_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            enrollment_date TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            status ENUM('active', 'completed', 'cancelled') DEFAULT 'active',
            payment_status ENUM('pending', 'paid', 'refunded') DEFAULT 'pending',
            UNIQUE KEY unique_enrollment (class_id, user_id),
            INDEX idx_user (user_id),
            INDEX idx_class (class_id)
        ) $charset_collate;
        
        -- Transactions Table
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}looksfam_transactions (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL,
            class_id BIGINT UNSIGNED,
            amount DECIMAL(10,2) NOT NULL,
            payment_method VARCHAR(50),
            payment_status ENUM('pending', 'completed', 'failed', 'refunded') DEFAULT 'pending',
            transaction_reference VARCHAR(100),
            created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user (user_id),
            INDEX idx_status (payment_status)
        ) $charset_collate;
        
        -- Exam Results Table
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}looksfam_exam_results (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            exam_id BIGINT UNSIGNED NOT NULL,
            class_id BIGINT UNSIGNED,
            user_id BIGINT UNSIGNED NOT NULL,
            score DECIMAL(5,2),
            total_questions INT,
            correct_answers INT,
            time_taken INT,
            session_id VARCHAR(50),
            started_at TIMESTAMP,
            completed_at TIMESTAMP,
            INDEX idx_exam (exam_id),
            INDEX idx_user (user_id),
            INDEX idx_session (session_id)
        ) $charset_collate;
        
        -- Legacy Exam Answers Table (keep for backward compatibility)
        CREATE TABLE IF NOT EXISTS {$wpdb->prefix}exam_answers (
            id BIGINT UNSIGNED AUTO_INCREMENT PRIMARY KEY,
            exam_id BIGINT UNSIGNED NOT NULL,
            class_id BIGINT UNSIGNED,
            question_id BIGINT UNSIGNED NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL,
            user_answer TEXT,
            is_correct TINYINT(1) DEFAULT 0,
            session_id VARCHAR(50),
            question_category VARCHAR(255),
            timestamp TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_exam (exam_id),
            INDEX idx_user (user_id),
            INDEX idx_session (session_id)
        ) $charset_collate;
        ";
        
        require_once ABSPATH . 'wp-admin/includes/upgrade.php';
        dbDelta( $sql );
    }
    
    /**
     * Drop all custom tables
     * Called during plugin uninstall
     */
    public static function drop_tables() {
        global $wpdb;
        
        $tables = [
            'looksfam_exams',
            'looksfam_questions',
            'looksfam_classes',
            'looksfam_enrollments',
            'looksfam_transactions',
            'looksfam_exam_results',
            'exam_answers',
        ];
        
        foreach ( $tables as $table ) {
            $wpdb->query( "DROP TABLE IF EXISTS {$wpdb->prefix}{$table}" );
        }
    }
    
    // ==================== EXAM METHODS ====================
    
    /**
     * Get exam by ID
     * 
     * @param int $exam_id
     * @return array|WP_Error
     */
    public function get_exam( $exam_id ) {
        global $wpdb;
        
        $table = $this->tables['exams'];
        
        $result = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $exam_id ),
            ARRAY_A
        );
        
        if ( ! $result ) {
            return new WP_Error( 'exam_not_found', 'Exam not found' );
        }
        
        return $result;
    }
    
    /**
     * Get all exams
     * 
     * @param array $args
     * @return array
     */
    public function get_exams( $args = [] ) {
        global $wpdb;
        
        $table = $this->tables['exams'];
        $where = [];
        $values = [];
        
        if ( isset( $args['status'] ) ) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        
        $where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
        
        $sql = "SELECT * FROM {$table} {$where_clause} ORDER BY created_at DESC";
        
        if ( ! empty( $values ) ) {
            $sql = $wpdb->prepare( $sql, $values );
        }
        
        return $wpdb->get_results( $sql, ARRAY_A );
    }
    
    /**
     * Create or update exam
     * 
     * @param array $data
     * @return int|WP_Error Exam ID or error
     */
    public function save_exam( $data ) {
        global $wpdb;
        
        $table = $this->tables['exams'];
        
        $defaults = [
            'title'               => '',
            'status'              => 'draft',
            'time_limit'          => 0,
            'num_questions'       => 0,
            'randomize_questions' => 0,
            'associated_classes'  => '',
        ];
        
        $data = wp_parse_args( $data, $defaults );
        
        // Validate required fields
        if ( empty( $data['title'] ) ) {
            return new WP_Error( 'missing_title', 'Exam title is required' );
        }
        
        // Check if updating existing exam
        if ( ! empty( $data['id'] ) ) {
            $update_data = $data;
            unset( $update_data['id'] );
            $update_data['updated_at'] = current_time( 'mysql' );
            
            $wpdb->update(
                $table,
                $update_data,
                [ 'id' => $data['id'] ],
                [ '%s', '%s', '%d', '%d', '%d', '%s', '%s' ],
                [ '%d' ]
            );
            
            return $data['id'];
        } else {
            // Insert new exam
            $data['created_at'] = current_time( 'mysql' );
            $data['updated_at'] = current_time( 'mysql' );
            
            $wpdb->insert(
                $table,
                $data,
                [ '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s' ]
            );
            
            return $wpdb->insert_id;
        }
    }
    
    /**
     * Delete exam
     * 
     * @param int $exam_id
     * @return bool|WP_Error
     */
    public function delete_exam( $exam_id ) {
        global $wpdb;
        
        $table = $this->tables['exams'];
        
        $result = $wpdb->delete(
            $table,
            [ 'id' => $exam_id ],
            [ '%d' ]
        );
        
        return $result !== false;
    }
    
    // ==================== QUESTION METHODS ====================
    
    /**
     * Get question by ID
     * 
     * @param int $question_id
     * @return array|WP_Error
     */
    public function get_question( $question_id ) {
        global $wpdb;
        
        $table = $this->tables['questions'];
        
        $result = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $question_id ),
            ARRAY_A
        );
        
        if ( ! $result ) {
            return new WP_Error( 'question_not_found', 'Question not found' );
        }
        
        // Decode JSON fields
        if ( ! empty( $result['multiple_choice_options'] ) ) {
            $result['multiple_choice_options'] = json_decode( $result['multiple_choice_options'], true );
        }
        
        return $result;
    }
    
    /**
     * Get questions with filters
     * 
     * @param array $args
     * @return array
     */
    public function get_questions( $args = [] ) {
        global $wpdb;
        
        $table = $this->tables['questions'];
        $where = [];
        $values = [];
        
        if ( isset( $args['subject_id'] ) ) {
            $where[] = 'subject_id = %d';
            $values[] = $args['subject_id'];
        }
        
        if ( isset( $args['topic_id'] ) ) {
            $where[] = 'topic_id = %d';
            $values[] = $args['topic_id'];
        }
        
        if ( isset( $args['question_type'] ) ) {
            $where[] = 'question_type = %s';
            $values[] = $args['question_type'];
        }
        
        if ( isset( $args['difficulty_level'] ) ) {
            $where[] = 'difficulty_level = %s';
            $values[] = $args['difficulty_level'];
        }
        
        $where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
        
        $limit = isset( $args['limit'] ) ? 'LIMIT %d' : '';
        if ( $limit ) {
            $values[] = $args['limit'];
        }
        
        $sql = "SELECT * FROM {$table} {$where_clause} ORDER BY created_at DESC {$limit}";
        
        if ( ! empty( $values ) ) {
            $sql = $wpdb->prepare( $sql, $values );
        }
        
        $results = $wpdb->get_results( $sql, ARRAY_A );
        
        // Decode JSON fields
        foreach ( $results as &$row ) {
            if ( ! empty( $row['multiple_choice_options'] ) ) {
                $row['multiple_choice_options'] = json_decode( $row['multiple_choice_options'], true );
            }
        }
        
        return $results;
    }
    
    /**
     * Create or update question
     * 
     * @param array $data
     * @return int|WP_Error Question ID or error
     */
    public function save_question( $data ) {
        global $wpdb;
        
        $table = $this->tables['questions'];
        
        $defaults = [
            'title'                   => '',
            'content'                 => '',
            'question_type'           => 'multiple_choice',
            'subject_id'              => 0,
            'topic_id'                => 0,
            'subtopic_id'             => 0,
            'multiple_choice_options' => '',
            'correct_answer'          => '',
            'solution'                => '',
            'difficulty_level'        => 'medium',
        ];
        
        $data = wp_parse_args( $data, $defaults );
        
        // Validate required fields
        if ( empty( $data['title'] ) ) {
            return new WP_Error( 'missing_title', 'Question title is required' );
        }
        
        // Encode JSON fields
        if ( is_array( $data['multiple_choice_options'] ) ) {
            $data['multiple_choice_options'] = json_encode( $data['multiple_choice_options'] );
        }
        
        // Check if updating existing question
        if ( ! empty( $data['id'] ) ) {
            $update_data = $data;
            unset( $update_data['id'] );
            $update_data['updated_at'] = current_time( 'mysql' );
            
            $wpdb->update(
                $table,
                $update_data,
                [ 'id' => $data['id'] ],
                [ '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s' ],
                [ '%d' ]
            );
            
            return $data['id'];
        } else {
            // Insert new question
            $data['created_at'] = current_time( 'mysql' );
            $data['updated_at'] = current_time( 'mysql' );
            
            $wpdb->insert(
                $table,
                $data,
                [ '%s', '%s', '%s', '%d', '%d', '%d', '%s', '%s', '%s', '%s', '%s', '%s' ]
            );
            
            return $wpdb->insert_id;
        }
    }
    
    /**
     * Delete question
     * 
     * @param int $question_id
     * @return bool|WP_Error
     */
    public function delete_question( $question_id ) {
        global $wpdb;
        
        $table = $this->tables['questions'];
        
        $result = $wpdb->delete(
            $table,
            [ 'id' => $question_id ],
            [ '%d' ]
        );
        
        return $result !== false;
    }
    
    // ==================== CLASS METHODS ====================
    
    /**
     * Get class by ID
     * 
     * @param int $class_id
     * @return array|WP_Error
     */
    public function get_class( $class_id ) {
        global $wpdb;
        
        $table = $this->tables['classes'];
        
        $result = $wpdb->get_row(
            $wpdb->prepare( "SELECT * FROM {$table} WHERE id = %d", $class_id ),
            ARRAY_A
        );
        
        if ( ! $result ) {
            return new WP_Error( 'class_not_found', 'Class not found' );
        }
        
        return $result;
    }
    
    /**
     * Get classes with filters
     * 
     * @param array $args
     * @return array
     */
    public function get_classes( $args = [] ) {
        global $wpdb;
        
        $table = $this->tables['classes'];
        $where = [];
        $values = [];
        
        if ( isset( $args['status'] ) ) {
            $where[] = 'status = %s';
            $values[] = $args['status'];
        }
        
        if ( isset( $args['subject_id'] ) ) {
            $where[] = 'subject_id = %d';
            $values[] = $args['subject_id'];
        }
        
        $where_clause = ! empty( $where ) ? 'WHERE ' . implode( ' AND ', $where ) : '';
        
        $sql = "SELECT * FROM {$table} {$where_clause} ORDER BY created_at DESC";
        
        if ( ! empty( $values ) ) {
            $sql = $wpdb->prepare( $sql, $values );
        }
        
        return $wpdb->get_results( $sql, ARRAY_A );
    }
    
    /**
     * Create or update class
     * 
     * @param array $data
     * @return int|WP_Error Class ID or error
     */
    public function save_class( $data ) {
        global $wpdb;
        
        $table = $this->tables['classes'];
        
        $defaults = [
            'title'          => '',
            'description'    => '',
            'subject_id'     => 0,
            'topic_id'       => 0,
            'price'          => 0.00,
            'enrollment_key' => '',
            'status'         => 'active',
        ];
        
        $data = wp_parse_args( $data, $defaults );
        
        if ( empty( $data['title'] ) ) {
            return new WP_Error( 'missing_title', 'Class title is required' );
        }
        
        if ( ! empty( $data['id'] ) ) {
            $update_data = $data;
            unset( $update_data['id'] );
            $update_data['updated_at'] = current_time( 'mysql' );
            
            $wpdb->update(
                $table,
                $update_data,
                [ 'id' => $data['id'] ],
                [ '%s', '%s', '%d', '%d', '%f', '%s', '%s', '%s' ],
                [ '%d' ]
            );
            
            return $data['id'];
        } else {
            $data['created_at'] = current_time( 'mysql' );
            $data['updated_at'] = current_time( 'mysql' );
            
            $wpdb->insert(
                $table,
                $data,
                [ '%s', '%s', '%d', '%d', '%f', '%s', '%s', '%s', '%s' ]
            );
            
            return $wpdb->insert_id;
        }
    }
    
    /**
     * Delete class
     * 
     * @param int $class_id
     * @return bool|WP_Error
     */
    public function delete_class( $class_id ) {
        global $wpdb;
        
        $table = $this->tables['classes'];
        
        $result = $wpdb->delete(
            $table,
            [ 'id' => $class_id ],
            [ '%d' ]
        );
        
        return $result !== false;
    }
    
    // ==================== ENROLLMENT METHODS ====================
    
    /**
     * Enroll user in class
     * 
     * @param int $class_id
     * @param int $user_id
     * @param array $meta
     * @return int|WP_Error Enrollment ID or error
     */
    public function enroll_user( $class_id, $user_id, $meta = [] ) {
        global $wpdb;
        
        $table = $this->tables['enrollments'];
        
        // Check if already enrolled
        $exists = $wpdb->get_var(
            $wpdb->prepare(
                "SELECT id FROM {$table} WHERE class_id = %d AND user_id = %d",
                $class_id,
                $user_id
            )
        );
        
        if ( $exists ) {
            return new WP_Error( 'already_enrolled', 'User is already enrolled in this class' );
        }
        
        $data = [
            'class_id'       => $class_id,
            'user_id'        => $user_id,
            'status'         => isset( $meta['status'] ) ? $meta['status'] : 'active',
            'payment_status' => isset( $meta['payment_status'] ) ? $meta['payment_status'] : 'pending',
            'enrollment_date'=> current_time( 'mysql' ),
        ];
        
        $wpdb->insert(
            $table,
            $data,
            [ '%d', '%d', '%s', '%s', '%s' ]
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get user enrollments
     * 
     * @param int $user_id
     * @param string $status
     * @return array
     */
    public function get_user_enrollments( $user_id, $status = 'active' ) {
        global $wpdb;
        
        $table = $this->tables['enrollments'];
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT e.*, c.title as class_title, c.subject_id, c.topic_id
                 FROM {$table} e
                 INNER JOIN {$this->tables['classes']} c ON e.class_id = c.id
                 WHERE e.user_id = %d AND e.status = %s
                 ORDER BY e.enrollment_date DESC",
                $user_id,
                $status
            ),
            ARRAY_A
        );
        
        return $results;
    }
    
    /**
     * Update enrollment status
     * 
     * @param int $enrollment_id
     * @param string $status
     * @return bool
     */
    public function update_enrollment_status( $enrollment_id, $status ) {
        global $wpdb;
        
        $table = $this->tables['enrollments'];
        
        $result = $wpdb->update(
            $table,
            [ 'status' => $status ],
            [ 'id' => $enrollment_id ],
            [ '%s' ],
            [ '%d' ]
        );
        
        return $result !== false;
    }
    
    // ==================== TRANSACTION METHODS ====================
    
    /**
     * Create transaction
     * 
     * @param array $data
     * @return int|WP_Error Transaction ID or error
     */
    public function create_transaction( $data ) {
        global $wpdb;
        
        $table = $this->tables['transactions'];
        
        $defaults = [
            'user_id'             => 0,
            'class_id'            => 0,
            'amount'              => 0.00,
            'payment_method'      => '',
            'payment_status'      => 'pending',
            'transaction_reference' => '',
        ];
        
        $data = wp_parse_args( $data, $defaults );
        
        if ( empty( $data['user_id'] ) ) {
            return new WP_Error( 'missing_user_id', 'User ID is required' );
        }
        
        if ( empty( $data['amount'] ) ) {
            return new WP_Error( 'missing_amount', 'Amount is required' );
        }
        
        $data['created_at'] = current_time( 'mysql' );
        $data['updated_at'] = current_time( 'mysql' );
        
        $wpdb->insert(
            $table,
            $data,
            [ '%d', '%d', '%f', '%s', '%s', '%s', '%s', '%s' ]
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Update transaction status
     * 
     * @param int $transaction_id
     * @param string $status
     * @param string $reference
     * @return bool
     */
    public function update_transaction_status( $transaction_id, $status, $reference = '' ) {
        global $wpdb;
        
        $table = $this->tables['transactions'];
        
        $update_data = [
            'payment_status' => $status,
            'updated_at'     => current_time( 'mysql' ),
        ];
        
        if ( ! empty( $reference ) ) {
            $update_data['transaction_reference'] = $reference;
        }
        
        $result = $wpdb->update(
            $table,
            $update_data,
            [ 'id' => $transaction_id ],
            [ '%s', '%s', '%s' ],
            [ '%d' ]
        );
        
        return $result !== false;
    }
    
    /**
     * Get user transactions
     * 
     * @param int $user_id
     * @return array
     */
    public function get_user_transactions( $user_id ) {
        global $wpdb;
        
        $table = $this->tables['transactions'];
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT t.*, c.title as class_title
                 FROM {$table} t
                 LEFT JOIN {$this->tables['classes']} c ON t.class_id = c.id
                 WHERE t.user_id = %d
                 ORDER BY t.created_at DESC",
                $user_id
            ),
            ARRAY_A
        );
        
        return $results;
    }
    
    // ==================== EXAM RESULTS METHODS ====================
    
    /**
     * Save exam result
     * 
     * @param array $data
     * @return int|WP_Error Result ID or error
     */
    public function save_exam_result( $data ) {
        global $wpdb;
        
        $table = $this->tables['exam_results'];
        
        $defaults = [
            'exam_id'         => 0,
            'class_id'        => 0,
            'user_id'         => 0,
            'score'           => 0,
            'total_questions' => 0,
            'correct_answers' => 0,
            'time_taken'      => 0,
            'session_id'      => '',
        ];
        
        $data = wp_parse_args( $data, $defaults );
        
        if ( empty( $data['exam_id'] ) || empty( $data['user_id'] ) ) {
            return new WP_Error( 'missing_required', 'Exam ID and User ID are required' );
        }
        
        $data['started_at']   = isset( $data['started_at'] ) ? $data['started_at'] : current_time( 'mysql' );
        $data['completed_at'] = isset( $data['completed_at'] ) ? $data['completed_at'] : current_time( 'mysql' );
        
        $wpdb->insert(
            $table,
            $data,
            [ '%d', '%d', '%d', '%f', '%d', '%d', '%d', '%s', '%s', '%s' ]
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get user exam results
     * 
     * @param int $user_id
     * @param int $exam_id
     * @return array
     */
    public function get_user_exam_results( $user_id, $exam_id = null ) {
        global $wpdb;
        
        $table = $this->tables['exam_results'];
        
        $where = 'user_id = %d';
        $values = [ $user_id ];
        
        if ( $exam_id ) {
            $where .= ' AND exam_id = %d';
            $values[] = $exam_id;
        }
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE {$where} ORDER BY completed_at DESC",
                $values
            ),
            ARRAY_A
        );
        
        return $results;
    }
    
    /**
     * Save individual exam answer (legacy table support)
     * 
     * @param array $data
     * @return int|WP_Error
     */
    public function save_exam_answer( $data ) {
        global $wpdb;
        
        $table = $this->tables['exam_answers'];
        
        $defaults = [
            'exam_id'           => 0,
            'class_id'          => 0,
            'question_id'       => 0,
            'user_id'           => 0,
            'user_answer'       => '',
            'is_correct'        => 0,
            'session_id'        => '',
            'question_category' => '',
        ];
        
        $data = wp_parse_args( $data, $defaults );
        
        if ( empty( $data['exam_id'] ) || empty( $data['question_id'] ) || empty( $data['user_id'] ) ) {
            return new WP_Error( 'missing_required', 'Exam ID, Question ID, and User ID are required' );
        }
        
        $data['timestamp'] = current_time( 'mysql' );
        
        $wpdb->insert(
            $table,
            $data,
            [ '%d', '%d', '%d', '%d', '%s', '%d', '%s', '%s', '%s' ]
        );
        
        return $wpdb->insert_id;
    }
    
    /**
     * Get exam answers by session
     * 
     * @param string $session_id
     * @return array
     */
    public function get_exam_answers_by_session( $session_id ) {
        global $wpdb;
        
        $table = $this->tables['exam_answers'];
        
        $results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT * FROM {$table} WHERE session_id = %s ORDER BY timestamp ASC",
                $session_id
            ),
            ARRAY_A
        );
        
        return $results;
    }
}
