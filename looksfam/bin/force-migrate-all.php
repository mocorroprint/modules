<?php
/**
 * Force Migration Script
 * 
 * Pre-migrates all existing data from wp_posts/wp_postmeta to custom tables.
 * Run this ONCE before deleting the Legacy directory.
 * 
 * Usage: wp cli eval-file bin/force-migrate-all.php
 * OR: Access via browser (if logged in as admin): yoursite.com/wp-content/plugins/looksfam/bin/force-migrate-all.php
 */

// Prevent direct access unless WP CLI or Admin
if (!defined('ABSPATH')) {
    if (!defined('WP_CLI') || !WP_CLI) {
        // Check if admin is logged in for browser access
        require_once '../../../wp-load.php';
        if (!current_user_can('manage_options')) {
            die('Unauthorized access.');
        }
    } else {
        require_once '../../../wp-load.php';
    }
}

require_once __DIR__ . '/../includes/Core/Database.php';

use Looksfam\Core\Database;

$db = Database::instance();
$migrated = [
    'exams' => 0,
    'questions' => 0,
    'classes' => 0,
    'transactions' => 0,
    'enrollments' => 0
];

echo "Starting Force Migration...\n";
echo "===========================\n\n";

// 1. Migrate Exams
echo "Migrating Exams...\n";
$exams = get_posts([
    'post_type' => 'exam',
    'numberposts' => -1,
    'post_status' => 'any'
]);

foreach ($exams as $exam) {
    $existing = $db->get_exam($exam->ID);
    if (!$existing) {
        $selected_questions = get_post_meta($exam->ID, 'selected_questions', true);
        $time_limit = get_post_meta($exam->ID, 'time_limit', true);
        $randomize = get_post_meta($exam->ID, 'randomize_questions', true);
        
        $db->insert_exam([
            'id' => $exam->ID,
            'title' => $exam->post_title,
            'status' => $exam->post_status,
            'time_limit' => $time_limit ? intval($time_limit) : 0,
            'selected_questions' => is_array($selected_questions) ? json_encode($selected_questions) : '',
            'randomize_questions' => $randomize ? intval($randomize) : 0,
            'created_at' => $exam->post_date,
            'updated_at' => $exam->post_modified
        ]);
        $migrated['exams']++;
    }
}
echo "✓ Migrated {$migrated['exams']} exams.\n\n";

// 2. Migrate Questions
echo "Migrating Questions...\n";
$questions = get_posts([
    'post_type' => 'question',
    'numberposts' => -1,
    'post_status' => 'any'
]);

foreach ($questions as $question) {
    $existing = $db->get_question($question->ID);
    if (!$existing) {
        $options = get_post_meta($question->ID, 'multiple_choice_options', true);
        $correct = get_post_meta($question->ID, 'correct_answer', true);
        $solution = get_post_meta($question->ID, 'solution', true);
        $difficulty = get_post_meta($question->ID, 'difficulty_level', true);
        
        // Get taxonomy terms
        $terms = wp_get_post_terms($question->ID, 'question_category', ['fields' => 'ids']);
        $subject_id = !empty($terms) ? $terms[0] : 0;
        
        $db->insert_question([
            'id' => $question->ID,
            'title' => $question->post_title,
            'content' => $question->post_content,
            'question_type' => 'multiple_choice',
            'subject_id' => $subject_id,
            'multiple_choice_options' => is_array($options) ? json_encode($options) : '',
            'correct_answer' => maybe_serialize($correct),
            'solution' => $solution,
            'difficulty_level' => $difficulty ?: 'medium',
            'created_at' => $question->post_date,
            'updated_at' => $question->post_modified
        ]);
        $migrated['questions']++;
    }
}
echo "✓ Migrated {$migrated['questions']} questions.\n\n";

// 3. Migrate Classes
echo "Migrating Classes...\n";
$classes = get_posts([
    'post_type' => 'class',
    'numberposts' => -1,
    'post_status' => 'any'
]);

foreach ($classes as $class) {
    $existing = $db->get_class($class->ID);
    if (!$existing) {
        $subject = get_post_meta($class->ID, 'exam_subject', true);
        $topic = get_post_meta($class->ID, 'exam_topic', true);
        $price = get_post_meta($class->ID, 'class_price', true);
        $enrollment_key = get_post_meta($class->ID, 'enrollment_key', true);
        $enrolled_students = get_post_meta($class->ID, 'enrolled_students', true);
        
        $db->insert_class([
            'id' => $class->ID,
            'title' => $class->post_title,
            'description' => $class->post_content,
            'subject_id' => intval($subject),
            'topic_id' => intval($topic),
            'price' => floatval($price ?: 0),
            'enrollment_key' => $enrollment_key ?: '',
            'status' => $class->post_status === 'publish' ? 'active' : 'inactive',
            'created_at' => $class->post_date,
            'updated_at' => $class->post_modified
        ]);
        
        // Migrate enrollments
        if (is_array($enrolled_students) && !empty($enrolled_students)) {
            foreach ($enrolled_students as $student_id) {
                $db->insert_enrollment([
                    'class_id' => $class->ID,
                    'user_id' => intval($student_id),
                    'status' => 'active',
                    'payment_status' => 'paid',
                    'enrollment_date' => current_time('mysql')
                ]);
                $migrated['enrollments']++;
            }
        }
        $migrated['classes']++;
    }
}
echo "✓ Migrated {$migrated['classes']} classes.\n";
echo "✓ Migrated {$migrated['enrollments']} enrollments.\n\n";

// 4. Migrate Transactions
echo "Migrating Transactions...\n";
$transactions = get_posts([
    'post_type' => 'transaction',
    'numberposts' => -1,
    'post_status' => 'any'
]);

foreach ($transactions as $transaction) {
    $existing = $db->get_transaction($transaction->ID);
    if (!$existing) {
        $user_id = get_post_meta($transaction->ID, 'user_id', true);
        $class_id = get_post_meta($transaction->ID, 'class_id', true);
        $amount = get_post_meta($transaction->ID, 'amount', true);
        $payment_method = get_post_meta($transaction->ID, 'payment_method', true);
        $ref = get_post_meta($transaction->ID, 'transaction_reference', true);
        
        $db->insert_transaction([
            'id' => $transaction->ID,
            'user_id' => intval($user_id),
            'class_id' => intval($class_id),
            'amount' => floatval($amount),
            'payment_method' => $payment_method ?: 'manual',
            'payment_status' => $transaction->post_status === 'publish' ? 'completed' : $transaction->post_status,
            'transaction_reference' => $ref ?: '',
            'created_at' => $transaction->post_date,
            'updated_at' => $transaction->post_modified
        ]);
        $migrated['transactions']++;
    }
}
echo "✓ Migrated {$migrated['transactions']} transactions.\n\n";

echo "===========================\n";
echo "MIGRATION COMPLETE!\n";
echo "===========================\n";
echo "Summary:\n";
foreach ($migrated as $type => $count) {
    echo "- {$type}: {$count}\n";
}
echo "\nNext Steps:\n";
echo "1. Verify data integrity on the frontend.\n";
echo "2. Test creating new exams, questions, and classes.\n";
echo "3. If everything works, you can safely delete the includes/Legacy/ directory.\n";
