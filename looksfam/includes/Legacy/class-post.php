<?php 
/**
 * Legacy Post Type Registration - DEPRECATED
 * 
 * This file is kept for backward compatibility during migration.
 * All post type registrations should be removed after data migration is complete.
 * 
 * @deprecated Use custom tables instead
 */

// KEEP taxonomy registration only - remove post types after migration
function register_question_category_taxonomy() {
    register_taxonomy('question_category', 'question', array(
        'labels' => array(
            'name' => 'Question Categories',
            'singular_name' => 'Question Category',
            'search_items' => 'Search Question Categories',
            'all_items' => 'All Question Categories',
            'parent_item' => 'Parent Question Category',
            'parent_item_colon' => 'Parent Question Category:',
            'edit_item' => 'Edit Question Category',
            'update_item' => 'Update Question Category',
            'add_new_item' => 'Add New Question Category',
            'new_item_name' => 'New Question Category Name',
        ),
        'hierarchical' => true,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'question-category'),
    ));
}
add_action('init', 'register_question_category_taxonomy');

// DEPRECATED: Post type registrations below - DO NOT USE FOR NEW DATA
// These will be removed after migration to custom tables is complete

function create_exam_post_type() {
    // DEPRECATED: Exams now use wp_looksfam_exams table
    // Migration required: Move data from wp_posts to wp_looksfam_exams
    if ( ! get_option( 'looksfam_migration_complete' ) ) {
        register_post_type('exam', array(
            'labels' => array(
                'name' => 'Exams',
                'singular_name' => 'Exam',
            ),
            'public' => false,
            'has_archive' => false,
            'rewrite' => array('slug' => 'exams'),
            'supports' => array('title'),
            'show_ui' => false,
            'taxonomies' => array('main_board', 'subject', 'topic', 'subtopic'),
            'show_in_nav_menus' => false,
        ));
    }
}
add_action('init', 'create_exam_post_type');

function create_question_post_type() {
    // DEPRECATED: Questions now use wp_looksfam_questions table
    // Migration required: Move data from wp_posts to wp_looksfam_questions
    if ( ! get_option( 'looksfam_migration_complete' ) ) {
        register_post_type('question', array(
            'labels' => array(
                'name' => 'Questions',
                'singular_name' => 'Question',
            ),
            'public' => true,
            'show_ui' => true,
            'supports' => array('title', 'thumbnail', 'editor'),
        ));
    }
}
add_action('init', 'create_question_post_type');

function register_class_post_type() {
    // DEPRECATED: Classes now use wp_looksfam_classes table
    // Migration required: Move data from wp_posts to wp_looksfam_classes
    if ( ! get_option( 'looksfam_migration_complete' ) ) {
        $args = array(
            'public' => false,
            'label'  => 'Classes',
            'show_ui' => true,
            'supports' => array('title', 'editor'),
            'has_archive' => false,
            'rewrite' => array('slug' => 'class')
        );
        register_post_type('class', $args);
    }
}
add_action('init', 'register_class_post_type');

function register_transaction_post_type() {
    // DEPRECATED: Transactions now use wp_looksfam_transactions table
    // Migration required: Move data from wp_posts to wp_looksfam_transactions
    if ( ! get_option( 'looksfam_migration_complete' ) ) {
        $args = array(
            'public' => false,
            'label'  => 'Transaction',
            'show_ui' => true,
            'supports' => array('title', 'editor'),
            'has_archive' => false
        );
        register_post_type('transaction', $args);
    }
}
add_action('init', 'register_transaction_post_type');
?>