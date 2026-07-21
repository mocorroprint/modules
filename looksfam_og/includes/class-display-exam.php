<?php 
// Main function to display the exam content
function display_exam_content($atts) {
    $atts = shortcode_atts(array(
        'exam' => 0,
    ), $atts);

    $exam_id = intval($_GET['id']); // Get the exam_id from the URL parameter
    $exam_title = get_the_title($exam_id);
    $user_id = get_current_user_id();
    $class_id = intval($_GET['class_id']);

    if (empty($exam_id) || get_post_type($exam_id) !== 'exam') {
        return 'Invalid or missing exam ID.';
    }

    // Check if the user has already answered this exam
    if (empty(intval($_GET['take']))) {
         $exam_results = get_post_meta($exam_id, 'exam_results', true);
        foreach ($exam_results as $result) {
            if($result['user_id']== $user_id){
                $user_id_check = $result['user_id'];
                $user_check = $user_id_check == $user_id? true : false;
                
            }
        }

        if ($user_check) { // Check if the result is for this question
            $type = 'exam';
            return display_exam_statistics_ui($exam_id, $user_id, $class_id,$type);
        }
    }

    $selected_questions = get_post_meta($exam_id, 'selected_questions', true);

    if (!empty($selected_questions)) {
        
        return display_exam_ui($exam_id, $selected_questions, $exam_title, $class_id, 'exam');
    } else {
        return 'This exam has no selected questions.';
    }
}
add_shortcode('display_exam', 'display_exam_content');
// Function to display exam statistics
?>