<?php 
function get_total_is_correct($exam_id, $session_id) {
    $total_is_correct = 0;
    $session = $session_id;

    $exam_results = get_post_meta($exam_id, 'exam_results', true);

    // Loop through the exam results
    foreach ($exam_results as $exam_result) {
        // Check if the session_id matches
        $result_session_id = $exam_result['session_id'];
        if ($result_session_id == $session_id) {
            
            if($exam_result['is_correct'] == 1){
              
            $total_is_correct++  ;
            }
        }
    }

    return $total_is_correct;
}



function confirmation_url_shortcode($atts) {
    $atts = shortcode_atts(array(
        'exam' => 0,
    ), $atts);

    $exam_id = intval($_GET['id']);
    $done = intval($_GET['done']);
    $session_id = $_GET['session_id'];
    $class_id = intval($_GET['class_id']);
    $cat = intval($_GET['cat']);

   if (empty($exam_id)) {
        $confirmation_url = home_url('/activity?id=' . $exam_id . '&cat=' . $cat . '&class_id=' . $class_id);
        wp_redirect($confirmation_url);
        exit;
    }

    ob_start();

    if (isset($done)) {
        
        $total_is_correct = get_total_is_correct($exam_id, $session_id);  
        $selected_questions = get_post_meta($exam_id, 'selected_questions', true);
        $total_question = count($selected_questions);
        $exam_average = ($total_is_correct / $total_question) * 100;
        $examres = $exam_average >= 60 ? true : false;

        if (!$examres) {
            ?>
            <h3 style="text-align: center; margin-top: 20px;">You have not passed the exam!<br></h3>
            <h5 style="text-align: center; margin-top: 20px;">You need to have 60% in order to proceed to the next exercise!<br></h5>
            
            <div>
                <div style="text-align: center; margin-top: 40px;">
                    <h3 style="display: inline-block; vertical-align: middle;"><?php echo displayStarRating($exam_average); ?></h3>
                </div>
                <div style="text-align: center; margin-top: 10px;">
                    <h2 style="display: inline-block; vertical-align: middle; margin-left: 10px;"><?php echo $total_is_correct . ' / ' . $total_question; ?></h2>
                </div>

                <div class="button-container">
                    <button onclick="window.location.href='<?php echo home_url('/solutions?id=' . $exam_id . '&class_id=' . $class_id); ?>';" style="border-radius: 10px; width: 49%; margin-right: 1%;">Show Solution</button>
                    <button onclick="window.location.href='<?php echo home_url('/profile?class_id=' . $class_id); ?>';" style="border-radius: 10px; width: 49%;">Continue</button>
                </div>
            </div>
            <?php
        } else {
            ?>
            <h3 style="text-align: center; margin-top: 20px;">Congrats! You passed the exam<br></h3>
            <h5 style="text-align: center; margin-top: 20px;">You may now proceed to the next exercise!<br></h5>
            
            <div>
                <div style="text-align: center; margin-top: 20px;">
                    <h3 style="display: inline-block; vertical-align: middle;"><?php echo displayStarRating($exam_average); ?></h3>
                </div>
                <div style="text-align: center; margin-top: 10px;">
                    <h2 style="display: inline-block; vertical-align: middle; margin-left: 10px;"><?php echo $total_is_correct . ' / ' . $total_question; ?></h2>
                </div>

                <div class="button-container">
                    <button onclick="window.location.href='<?php echo home_url('/solutions?id=' . $exam_id . '&class_id=' . $class_id); ?>';" style="border-radius: 10px; width: 49%; margin-right: 1%;">Show Solution</button>
                    <button onclick="window.location.href='<?php echo home_url('/profile?class_id=' . $class_id); ?>';" style="border-radius: 10px; width: 49%;">Continue</button>
                </div>
            </div>
            <?php
        }
    } else {
        ?>
        <h3>You have already taken the exam. Do you want to take it again?<br></h3>
        <button onclick="window.location.href='<?php echo home_url('/exam?id=' . $exam_id); ?>';" style="border-radius: 10px; width: 100%;">Continue</button>
        <?php
    }

    return ob_get_clean();
}
add_shortcode('confirmation_url', 'confirmation_url_shortcode');


?>