<?php 
// Function to display exam statistics
function exercise_content($atts) {
    $atts = shortcode_atts(array(
        'exam' => 0,
    ), $atts);

    $exam_id = intval($_GET['id']);
    $exam_title = get_the_title($exam_id);
    $class_id = intval($_GET['class_id']);
    $user_id = get_current_user_id();

    if (empty($exam_id) || get_post_type($exam_id) !== 'exam') {
        return 'Invalid or missing exam ID.';
    }

    if (empty(intval($_GET['take']))) {
        $exam_results = get_post_meta($exam_id, 'exam_results', true);
        $user_check = false;
        foreach ($exam_results as $result) {
            if($result['user_id'] == $user_id){
                $user_check = true;
                break;
            }
        }
        
        if ($user_check) {
            $type = 'exercises';
            return display_exam_statistics_ui($exam_id, $user_id, $class_id,$type);
        }
    }

    $selected_questions = get_post_meta($exam_id, 'selected_questions', true); // make this random


    if (!empty($selected_questions)) {
        return display_exam_ui($exam_id, $selected_questions, $exam_title, $class_id, 'exercises');
    } else {
        return 'This exam has no selected questions.';
    }

}
add_shortcode('exercise', 'exercise_content');


?>