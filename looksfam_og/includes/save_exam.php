
// Save selected category and questions
function save_exam_category_and_questions($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    $selected_category = isset($_POST['selected_category']) ? intval($_POST['selected_category']) : 0;
    $selected_exam_category = isset($_POST['selected_exam_category']) ? intval($_POST['selected_exam_category']) : 0;
    $selected_subject_category = isset($_POST['selected_subject_category']) ? intval($_POST['selected_subject_category']) : 0;
    $selected_topic_category = isset($_POST['selected_topic_category']) ? intval($_POST['selected_topic_category']) : 0;
    $selected_sub_topic_category = isset($_POST['selected_sub_topic_category']) ? intval($_POST['selected_sub_topic_category']) : 0;

    // Determine the relation based on the selected categories
    $tax_relation = 'OR';
   /* $tax_relation = 'OR';
    if ($selected_category && $selected_exam_category) {
        $tax_relation = 'AND';
    }*/
    
    
    if ($selected_category && $selected_exam_category) {
        $tax_relation = 'AND';
    }
    
    

    


    if ($selected_category || $selected_exam_category) {
        update_post_meta($post_id, 'selected_category', $selected_category);
        update_post_meta($post_id, 'selected_exam_category', $selected_exam_category);
        update_post_meta($post_id, 'selected_subject_category', $selected_subject_category);
        update_post_meta($post_id, 'selected_topic_category', $selected_topic_category);
        update_post_meta($post_id, 'selected_sub_topic_category', $selected_sub_topic_category);
        
       
    } else {
        // If no categories were selected, remove the selected categories
        delete_post_meta($post_id, 'selected_category');
        delete_post_meta($post_id, 'selected_exam_category');
        delete_post_meta($post_id, 'selected_subject_category');
        delete_post_meta($post_id, 'selected_topic_category');
        delete_post_meta($post_id, 'selected_sub_topic_category');
    }

    // Query questions based on the selected categories and the determined relation
    $tax_query = array(
        'relation' => $tax_relation,
        array(
            'taxonomy' => 'question_category',
            'field' => 'term_id',
            'terms' => $selected_category,
        ),
        array(
            'taxonomy' => 'question_category',
            'field' => 'term_id',
            'terms' => $selected_exam_category,
        ),
        array(
            'taxonomy' => 'question_category',
            'field' => 'term_id',
            'terms' => $selected_subject_category,
        ),
        array(
            'taxonomy' => 'question_category',
            'field' => 'term_id',
            'terms' => $selected_topic_category,
        ),
        array(
            'taxonomy' => 'question_category',
            'field' => 'term_id',
            'terms' => $selected_sub_topic_category,
        )
    );

   //-----------------------------------------------
        if (!($selected_category)) {
           
            $tax_relation = 'AND';
           
           // Query questions based on the selected categories and the determined relation
    $tax_query = array(
        'relation' => $tax_relation,
        array(
            'taxonomy' => 'question_category',
            'field' => 'term_id',
            'terms' => $selected_exam_category,
        ),
        array(
            'taxonomy' => 'question_category',
            'field' => 'term_id',
            'terms' => $selected_subject_category,
        ),
        array(
            'taxonomy' => 'question_category',
            'field' => 'term_id',
            'terms' => $selected_topic_category,
        ),
        array(
            'taxonomy' => 'question_category',
            'field' => 'term_id',
            'terms' => $selected_sub_topic_category,
        )
    );
    
    //-----------------------------------------------
        if (!($selected_sub_topic_category)) {
           
            $tax_relation = 'AND';
           
           // Query questions based on the selected categories and the determined relation
    $tax_query = array(
        'relation' => $tax_relation,
        array(
            'taxonomy' => 'question_category',
            'field' => 'term_id',
            'terms' => $selected_exam_category,
        ),
        array(
            'taxonomy' => 'question_category',
            'field' => 'term_id',
            'terms' => $selected_subject_category,
        ),
        array(
            'taxonomy' => 'question_category',
            'field' => 'term_id',
            'terms' => $selected_topic_category,
        )
    );
    }
    
    
   //-----------------------------------------------
        if (!($selected_topic_category)) {
           
            $tax_relation = 'AND';
           
           // Query questions based on the selected categories and the determined relation
    $tax_query = array(
        'relation' => $tax_relation,
        array(
            'taxonomy' => 'question_category',
            'field' => 'term_id',
            'terms' => $selected_exam_category,
        ),
        array(
            'taxonomy' => 'question_category',
            'field' => 'term_id',
            'terms' => $selected_subject_category,
        )
    );
    }
    
    
   //-----------------------------------------------
        if (!($selected_subject_category)) {
           
            $tax_relation = 'AND';
           
           // Query questions based on the selected categories and the determined relation
    $tax_query = array(
        'relation' => $tax_relation,
        array(
            'taxonomy' => 'question_category',
            'field' => 'term_id',
            'terms' => $selected_exam_category,
        )
    );
    
    }
}



//------------------- WITHOUT DIFFICULTY LOGIC----------------------------
        if (!($selected_sub_topic_category)) {
           
            $tax_relation = 'AND';
           
           // Query questions based on the selected categories and the determined relation
    $tax_query = array(
        'relation' => $tax_relation,
         array(
            'taxonomy' => 'question_category',
            'field' => 'term_id',
            'terms' => $selected_category,
        ),
        array(
            'taxonomy' => 'question_category',
            'field' => 'term_id',
            'terms' => $selected_exam_category,
        ),
        array(
            'taxonomy' => 'question_category',
            'field' => 'term_id',
            'terms' => $selected_subject_category,
        ),
        array(
            'taxonomy' => 'question_category',
            'field' => 'term_id',
            'terms' => $selected_topic_category,
        )
    );
    }
    
    
   //-----------------------------------------------
        if (!($selected_topic_category)) {
           
            $tax_relation = 'AND';
           
           // Query questions based on the selected categories and the determined relation
    $tax_query = array(
        'relation' => $tax_relation,
        array(
            'taxonomy' => 'question_category',
            'field' => 'term_id',
            'terms' => $selected_category,
        ),
        array(
            'taxonomy' => 'question_category',
            'field' => 'term_id',
            'terms' => $selected_exam_category,
        ),
        array(
            'taxonomy' => 'question_category',
            'field' => 'term_id',
            'terms' => $selected_subject_category,
        )
    );
    }
    
    
   //-----------------------------------------------
        if (!($selected_subject_category)) {
           
            $tax_relation = 'AND';
           
           // Query questions based on the selected categories and the determined relation
    $tax_query = array(
        'relation' => $tax_relation,
        array(
            'taxonomy' => 'question_category',
            'field' => 'term_id',
            'terms' => $selected_category,
        ),
        array(
            'taxonomy' => 'question_category',
            'field' => 'term_id',
            'terms' => $selected_exam_category,
        )
    );
    
    }
    
    
    $questions_query_args = array(
        'post_type' => 'question',
        'posts_per_page' => -1,
        'tax_query' => $tax_query,
    );

    $questions_query = new WP_Query($questions_query_args);

    // Store selected question IDs to avoid duplicates
    $selected_question_ids = array();

    // Get the selected questions based on the categories
    foreach ($questions_query->posts as $question_post) {
        $selected_question_ids[] = $question_post->ID;
    }

    // Update the selected questions
    update_post_meta($post_id, 'selected_questions', $selected_question_ids);
}