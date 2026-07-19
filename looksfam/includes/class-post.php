<?php 
function create_exam_post_type() {
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
add_action('init', 'create_exam_post_type');

// Function to create custom post type for questions
function create_question_post_type() {
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
add_action('init', 'create_question_post_type');

// Register the 'class' custom post type
function register_class_post_type() {
    $args = array(
        'public' => false,
        'label'  => 'Classes',
        'show_ui' => true,
        'supports' => array('title', 'editor'),
        'has_archive' => false,
        
        'rewrite' => array('slug' => 'class')
        // Add more args as needed
    );
    register_post_type('class', $args);
}
add_action('init', 'register_class_post_type');

// Register the 'class' custom post type
function register_transaction_post_type() {
    $args = array(
        'public' => false,
        'label'  => 'Transaction',
        'show_ui' => true,
        'supports' => array('title', 'editor'),
        'has_archive' => false
        // Add more args as needed
    );
    register_post_type('transaction', $args);
}
add_action('init', 'register_transaction_post_type');
?>