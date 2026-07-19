<?php 
// Create a custom taxonomy for classes
function register_class_taxonomy() {
    $args = array(
        'label'        => 'Class Categories',
        'hierarchical' => true,
    );
    register_taxonomy('class', array('class'), $args); // Change 'question' to 'class'
}
add_action('init', 'register_class_taxonomy');
// Add a custom metabox for displaying questions and student answers
function add_questions_answers_metabox() {
    add_meta_box(
        'questions-answers-metabox',
        'Questions and Student Answers',
        'render_questions_answers_metabox',
        'class', // Replace with the slug of your class custom post type
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'add_questions_answers_metabox');
// Render the content of the questions and answers metabox
function render_questions_answers_metabox($post) {
    $class_id = $post->ID;
    $exam_subject = get_post_meta($class_id, 'exam_subject', true);
    $exam_topic = get_post_meta($class_id, 'exam_topic', true);

    // Get questions based on exam_topic if present, otherwise use exam_subject
    $category_id = $exam_topic ? $exam_topic : $exam_subject;

    /*$questions = get_posts(array(
        'post_type' => 'question',
        'numberposts' => -1,
        'tax_query' => array(
            array(
                'taxonomy' => 'question_category',
                'field' => 'term_id',
                'terms' => $category_id,
            ),
        ),
    ));*/
    
    $questions_in_category = get_posts($questions);

    $enrolled_students = get_post_meta($class_id, 'enrolled_students', true);

    if (empty($questions)) {
        echo '<p>No questions found for this class.</p>';
        return;
    }

    if (empty($enrolled_students)) {
        echo '<p>No students enrolled in this class.</p>';
        return;
    }
    

    // Display student statistics
    echo '<h3>Student Statistics</h3>';
    echo '<table class="widefat">';
    echo '<thead><tr><th>Student</th><th>Total Correct Answers</th><th>Unique Exams Taken</th><th>Looksfam Accuracy</th></tr></thead><tbody>';

    $student_stats = [];

    foreach ($enrolled_students as $student_id) {
        $last_name = get_user_meta($student_id, 'last_name', true);
        $first_name = get_user_meta($student_id, 'first_name', true);
        $user_questions = get_user_meta($student_id, 'questions', true);
        $total_questions = count($questions_in_category);
        $correct_answers = 0;
        $sessions = 0;
        $q_sessions = 0;
        $displaylook = 0;
        $displayed_session = array();

        if (!empty($user_questions) && is_array($user_questions)) {
            $displayed_questions = array();

            foreach ($user_questions as $question) {
                if (in_array($question['question_id'], $displayed_questions)) {
                    continue;
                }

                $displayed_questions[] = $question['question_id'];

                if ($class_id != $question['class_id']) {
                    continue;
                }

                if ($question['is_correct']) {
                    $correct_answers++;
                }

                if (!in_array($question['session_id'], $displayed_session)) {
                    $displayed_session[] = $question['session_id'];
                    $sessions++;
                }
                $q_sessions++;
                $displaylook += questionstatlooksfam($question['question_id'], $student_id);
            }
        }

        $looksfamacc_x_accuracy_display = ($total_questions > 0) ? ($displaylook / ($q_sessions * 100)) * 100 : 0;

        $student_stats[] = array(
            'name' => "$last_name, $first_name",
            'correct_answers' => $correct_answers,
            'sessions' => $sessions,
            'looksfam_accuracy' => round($looksfamacc_x_accuracy_display, 2)
        );
    }

    // Sort the student stats by Looksfam accuracy in descending order
    usort($student_stats, function($a, $b) {
        return $b['looksfam_accuracy'] <=> $a['looksfam_accuracy'];
    });

    foreach ($student_stats as $stat) {
        echo '<tr>';
        echo '<td>' . esc_html($stat['name']) . '</td>';
        echo '<td>' . $stat['correct_answers'] . '</td>';
        echo '<td>' . $stat['sessions'] . '</td>';
        echo '<td>' . $stat['looksfam_accuracy'] . '%</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';

    echo '<table class="widefat">';
    echo '<thead><tr><th>Question</th><th>Unique Sessions</th><th>Total Correct</th><th>Looksfam Rating</th></tr></thead><tbody>';

    $total_exams_taken = array();
    $question_stats = [];

    foreach ($questions as $question) {
        $unique_sessions = array();
        $total_correct = 0;
        $displayoveralllook = 0;

        foreach ($enrolled_students as $student_id) {
            $user_questions = get_user_meta($student_id, 'questions', true);
            $correct_answer = 'N/A';

            if (is_array($user_questions)) {
                foreach ($user_questions as $user_question) {
                    if (isset($user_question['class_id']) && $user_question['class_id'] == $class_id &&
                        isset($user_question['question_id']) && $user_question['question_id'] == $question->ID) {
                        $displayoveralllook = questionoveralllooksfam($user_question['question_id'], $student_id);
                        $correct_answer = isset($user_question['is_correct']) && $user_question['is_correct'] ? 'Correct' : 'Incorrect';
                        if ($correct_answer === 'Correct') {
                            $total_correct++;
                        }
                        if (isset($user_question['session_id'])) {
                            $unique_sessions[$user_question['session_id']] = true;
                            $total_exams_taken[$user_question['session_id']] = true;
                        }
                        break;
                    }
                }
            }
        }

        $question_stats[] = array(
            'title' => esc_html($question->post_title),
            'unique_sessions' => count($unique_sessions),
            'total_correct' => $total_correct,
            'looksfam_rating' => $displayoveralllook
        );
    }

    // Sort the question stats by Looksfam rating in descending order
    usort($question_stats, function($a, $b) {
        return $b['looksfam_rating'] <=> $a['looksfam_rating'];
    });

    foreach ($question_stats as $stat) {
        echo '<tr>';
        echo '<td>' . $stat['title'] . '</td>';
        echo '<td>' . $stat['unique_sessions'] . '</td>';
        echo '<td>' . $stat['total_correct'] . '</td>';
        echo '<td>' . $stat['looksfam_rating'] . '</td>';
        echo '</tr>';
    }

    echo '</tbody></table>';
}
// Add meta box to class post type
function add_exam_meta_box() {
    add_meta_box(
        'exam_subject_topic',
        'Subject and Topic',
        'render_exam_meta_box',
        'class',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'add_exam_meta_box');

// Render meta box content
function render_exam_meta_box($post) {
    wp_nonce_field('exam_subject_topic', 'exam_subject_topic_nonce');
    $subject_def = get_post_meta($post->ID, 'exam_subject', true);
    $topic_def = get_post_meta($post->ID, 'exam_topic', true);
    
    // Get the "Exam" category
    $exam_cat = get_term_by('name', 'Exam', 'question_category');
 
    if ($exam_cat) {
        // Get all categories under "Exam" (subjects)
        $subjects = get_terms(array(
            'taxonomy' => 'question_category',
            'parent' => $exam_cat->term_id,
            'hide_empty' => false,
        ));

        // Get topics based on the selected subject
        $topics = array();
        if ($subject_def) {
            $topics = get_terms(array(
                'taxonomy' => 'question_category',
                'parent' => $subject_def,
                'hide_empty' => false,
            ));
        }
        ?>
        <p>
            <label for="exam_subject">Subject:</label>
            <select name="exam_subject" id="exam_subject">
                <option value="">Select a subject</option>
                <?php foreach ($subjects as $subj) : ?>
                    <option value="<?php echo esc_attr($subj->term_id); ?>" <?php selected($subject_def, $subj->term_id); ?>>
                        <?php echo esc_html($subj->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <p>
            <label for="exam_topic">Topic:</label>
            <select name="exam_topic" id="exam_topic">
                <option value="">Select a topic</option>
                <?php foreach ($topics as $top) : ?>
                    <option value="<?php echo esc_attr($top->term_id); ?>" <?php selected($topic_def, $top->term_id); ?>>
                        <?php echo esc_html($top->name); ?>
                    </option>
                <?php endforeach; ?>
            </select>
        </p>
        <script>
        jQuery(document).ready(function($) {
            $('#exam_subject').change(function() {
                var subject = $(this).val();
                var data = {
                    'action': 'get_topics',
                    'subject': subject
                };
                $.post(ajaxurl, data, function(response) {
                    $('#exam_topic').html(response);
                });
            });
        });
        </script>
        <?php
    } else {
        echo '<p>Error: "Exam" category not found in question_category taxonomy.</p>';
    }
}

// Save meta box data
function save_exam_meta_box($post_id) {
    if (!isset($_POST['exam_subject_topic_nonce']) || !wp_verify_nonce($_POST['exam_subject_topic_nonce'], 'exam_subject_topic')) {
        return;
    }
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    if (isset($_POST['exam_subject'])) {
        update_post_meta($post_id, 'exam_subject', sanitize_text_field($_POST['exam_subject']));
    }
    if (isset($_POST['exam_topic'])) {
        update_post_meta($post_id, 'exam_topic', sanitize_text_field($_POST['exam_topic']));
    }
}
add_action('save_post_class', 'save_exam_meta_box');

// AJAX handler to get topics based on selected subject
function get_topics_ajax() {
    $subject = isset($_POST['subject']) ? intval($_POST['subject']) : 0;
    $topics = get_terms(array(
        'taxonomy' => 'question_category',
        'parent' => $subject,
        'hide_empty' => false,
    ));

    $output = '<option value="">Select a topic</option>';
    foreach ($topics as $topic) {
        $output .= sprintf('<option value="%s">%s</option>', esc_attr($topic->term_id), esc_html($topic->name));
    }
    echo $output;
    wp_die();
}
add_action('wp_ajax_get_topics', 'get_topics_ajax');





// Add a custom metabox for adding students to a class
function add_students_to_class_metabox() {
  add_meta_box(
    'students-to-class-metabox',
    'Add Students to Class',
    'render_students_to_class_metabox',
    'class', // Replace with the slug of your class custom post type
    'normal',
    'default'
  );

  add_meta_box(
    'existing-students-metabox',
    'Existing Students',
    'render_existing_students_metabox',
    'class', // Replace with the slug of your class custom post type
    'normal',
    'default'
  );
}
add_action('add_meta_boxes', 'add_students_to_class_metabox');

// Render the content of the students to class metabox
function render_students_to_class_metabox($post) {
  // Display a list of existing users to select as students
 $users = get_users();
  echo '<label for="students">Select Students:</label>';
  echo '<select name="students[]" id="students" multiple>';

  foreach ($users as $user) {
    // Check if the user is already enrolled in the class
    $enrolled_students = get_post_meta($post->ID, 'enrolled_students', true);
    if (!in_array($user->ID, $enrolled_students)) {
      echo '<option value="' . $user->ID . '">' . esc_html($user->user_login) . '</option>';
    }
  }

  echo '</select>';
  echo '<button id="add-students-button" class="button-primary">Add Students</button>';

}


// Render the content of the existing students metabox
function render_existing_students_metabox($post) {
  // Display a list of the currently enrolled students
  $enrolled_students = get_post_meta($post->ID, 'enrolled_students', true);
  if ($enrolled_students) {
    echo '<h3>Currently Enrolled Students</h3>';
    echo '<h5>Total:'.class_enrollees($post->ID).'</h5>';
    
    echo '<style>';
    echo 'table { width: 100%; border-collapse: collapse; }';
    echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }';
    echo '</style>';
    echo '<table>';
    echo '<thead>';
    echo '<tr>';
    echo '<th>User ID</th>';
    echo '<th>Username</th>';
    echo '<th>Remove</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';

    foreach ($enrolled_students as $student_id) {
      $user = get_user_by('ID', $student_id);
      echo '<tr>';
      echo '<td>' . esc_html($user->ID) . '</td>';
      echo '<td>' . esc_html($user->user_login) . '</td>';
      echo '<td><input type="checkbox" name="remove_students[]" value="' . $user->ID . '"></td>';
      echo '</tr>';
    }

    echo '</tbody>';
    echo '</table>';
    echo '<button id="remove-students-button" class="button-secondary">Remove Selected Students</button>';

   
  }
}


// Save the selected students when the class post is saved and handle student removal
function save_students_to_class($post_id) {
  if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
    return $post_id;
  }

  if (isset($_POST['students'])) {
    $students_to_add = $_POST['students'];
    $enrolled_students = get_post_meta($post_id, 'enrolled_students', true);
     $class_keys = get_term_meta(305, 'class_keys', true) ?: array();
     $is_valid_key = false;
     
     
     

    // Add the class post ID to the user meta of each selected student
    foreach ($students_to_add as $student_id) {
      $classes_enrolled = get_user_meta($student_id, 'classes_enrolled', true);
      
       $used_key = $is_valid_key ? $class_key : generate_unique_key($class_keys);
        $class_keys[$used_key] = array(
                    'class' => $post_id,
                    'status' => 'Used',
                    'user' => $student_id,
                    'used_timestamp' => current_time('mysql'),
                    'duration' => '6'
        );
        update_term_meta(305, 'class_keys', $class_keys);


      if (is_array($classes_enrolled)) {
        $classes_enrolled[] = $post_id;
      } else {
        $classes_enrolled = array($post_id);
      }

      update_user_meta($student_id, 'classes_enrolled', $classes_enrolled);
    }

    if (is_array($enrolled_students)) {
      $students_to_add = array_merge($enrolled_students, $students_to_add);
    }

    update_post_meta($post_id, 'enrolled_students', $students_to_add);
  }

  // Handle student removal
  if (isset($_POST['remove_students'])) {
    $students_to_remove = $_POST['remove_students'];
    $enrolled_students = get_post_meta($post_id, 'enrolled_students', true);

    // Remove the class post ID from the user meta of each removed student
    foreach ($students_to_remove as $student_id) {
      $classes_enrolled = get_user_meta($student_id, 'classes_enrolled', true);

      if (is_array($classes_enrolled)) {
        $classes_enrolled = array_diff($classes_enrolled, array($post_id));
        update_user_meta($student_id, 'classes_enrolled', $classes_enrolled);
      }
    }

    if (is_array($enrolled_students)) {
      $enrolled_students = array_diff($enrolled_students, $students_to_remove);
      update_post_meta($post_id, 'enrolled_students', $enrolled_students);
    }
  }
}
add_action('save_post', 'save_students_to_class');

// Add a custom metabox for adding and removing exams from classes
function add_exams_to_class_metabox() {
    add_meta_box(
        'exams-to-class-metabox',
        'Add/Remove Exams to Class',
        'render_exams_to_class_metabox',
        'class', // Replace with the slug of your class custom post type
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'add_exams_to_class_metabox');

// Remove selected exams from a class
function remove_exams_from_class($post_id, $exams_to_remove, $exam_type) {
    $associated_exams = get_post_meta($post_id, $exam_type, true);

    if (is_array($associated_exams) && !empty($exams_to_remove)) {
        foreach ($exams_to_remove as $exam_id) {
            $key = array_search($exam_id, $associated_exams);
            if ($key !== false) {
                unset($associated_exams[$key]);

                // Remove the class from the exam's 'associated_classes'
                $associated_classes = get_post_meta($exam_id, 'associated_classes', true);
                if (is_array($associated_classes)) {
                    $class_key = array_search($post_id, $associated_classes);
                    if ($class_key !== false) {
                        unset($associated_classes[$class_key]);
                        update_post_meta($exam_id, 'associated_classes', $associated_classes);
                    }
                }
            }
        }

        update_post_meta($post_id, $exam_type, $associated_exams);
    }
}

// Render the content of the exams to class metabox
function render_exams_to_class_metabox($post) {
    // Get the list of all available exams
    //$exams = get_posts(array('post_type' => 'exam', 'numberposts' => -1));

    // Get the list of exams associated with this class
    $associated_exams = get_post_meta($post->ID, 'associated_exams', true);

    // Get the list of exams associated with this class
    $pre_exams = get_post_meta($post->ID, 'associated_pre_exam', true);

    // Get the list of exams associated with this class
    $post_exams = get_post_meta($post->ID, 'associated_post_exam', true);

    echo '<form method="post">';

    // Pre-Assessment
    echo '<label for="exam_pre">Select Pre-Assessment:</label>';
    echo '<select name="pre_exams[]" id="exam_pre" multiple>';

    foreach ($exams as $exam) {
        if (
            !in_array($exam->ID, $associated_exams)
            && !in_array($exam->ID, $post_exams)
            && !in_array($exam->ID, $pre_exams)
            && !is_exam_associated_with_other_classes($exam->ID, $post->ID)
        ) {
            echo '<option value="' . $exam->ID . '">' . $exam->post_title . '</option>';
        }
    }

    echo '</select>';
    echo '<button id="add-exam-button-pre" class="button-primary">Add Pre-Assessment</button>';

    // Display Pre-Assessment exams
    display_exam_table($pre_exams, 'remove_exam_pre');

    // Exercises
    echo '<label for="exam">Select Exercises (multiple):</label>';
    echo '<select name="exams[]" id="exam" multiple>';

    foreach ($exams as $exam) {
        if (
            !in_array($exam->ID, $associated_exams)
            && !in_array($exam->ID, $post_exams)
            && !in_array($exam->ID, $pre_exams)
            && !is_exam_associated_with_other_classes($exam->ID, $post->ID)
        ) {
            echo '<option value="' . $exam->ID . '">' . $exam->post_title . '</option>';
        }
    }

    echo '</select>';
    echo '<button id="add-exam-button" class="button-primary">Add Exercise</button>';

    // Display Exercise exams
    display_exam_table($associated_exams, 'remove_exam');

    // Post-Assessment
    echo '<label for="exam_post">Select Post-Assessment:</label>';
    echo '<select name="post_exams[]" id="exam_post" multiple>';

    foreach ($exams as $exam) {
        if (
            !in_array($exam->ID, $associated_exams)
            && !in_array($exam->ID, $post_exams)
            && !in_array($exam->ID, $pre_exams)
            && !is_exam_associated_with_other_classes($exam->ID, $post->ID)
        ) {
            echo '<option value="' . $exam->ID . '">' . $exam->post_title . '</option>';
        }
    }

    echo '</select>';
    echo '<button id="add-exam-button-post" class="button-primary">Add Post-Assessment</button>';

    // Display Post-Assessment exams
    display_exam_table($post_exams, 'remove_exam_post');
    echo '<br>';
    echo '<input type="submit" name="remove_selected" class="button-primary" value="Remove Selected Exams">';
    echo '</form><br>';
    echo '<label">Deletes pre first then exam then post</label>';
}

function is_exam_associated_with_other_classes($exam_id, $current_class_id) {
    $classes_with_exam = get_post_meta($exam_id, 'associated_classes', true);

    return $classes_with_exam && count($classes_with_exam) > 1 && !in_array($current_class_id, $classes_with_exam);
}


// Helper function to display exam tables
function display_exam_table($exams, $remove_name) {
    if (is_array($exams) && !empty($exams)) {
        echo '<style>';
        echo 'table { width: 100%; border-collapse: collapse; }';
        echo 'th, td { border: 1px solid #ddd; padding: 8px; text-align: center; }';
        echo '</style>';
        echo '<table>';
        echo '<thead>';
        echo '<tr>';
        echo '<th>Exam Name</th>';
        echo '<th>Exam Link</th>';
        echo '<th>Edit Exam</th>';
        echo '<th>Remove</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        foreach ($exams as $exam_id) {
            $exam = get_post($exam_id);
            $exam_edit_url = get_edit_post_link($exam_id);
            $exam_link = get_permalink($exam_id);

            echo '<tr>';
            echo '<td>' . esc_html($exam->post_title) . '</td>';
            echo '<td><a href="' . esc_url($exam_link) . '">View Exam</a></td>';
            echo '<td><a href="' . esc_url($exam_edit_url) . '">Edit Exam</a></td>';
            echo '<td><input type="checkbox" name="' . $remove_name . '[]" value="' . $exam->ID . '"></td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';

    } else {
        echo '<p>No exams associated with this class.</p>';
    }
}

// Save the selected exams and remove selected exams when the class post is saved
function save_exams_to_class($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $post_id;
    }

    if (!empty($_POST['remove_exam_pre'])) {
        $exams_to_remove = $_POST['remove_exam_pre'];
        remove_exams_from_class($post_id, $exams_to_remove, 'associated_pre_exam');
    }

    if (!empty($_POST['remove_exam_post'])) {
        $exams_to_remove = $_POST['remove_exam_post'];
        remove_exams_from_class($post_id, $exams_to_remove, 'associated_post_exam');
    }

    if (!empty($_POST['remove_exam'])) {
        $exams_to_remove = $_POST['remove_exam'];
        remove_exams_from_class($post_id, $exams_to_remove, 'associated_exams');
    }

    if (isset($_POST['pre_exams']) && !empty($_POST['pre_exams'])) {
        $pre_exams = $_POST['pre_exams'];
        update_post_meta($post_id, 'associated_pre_exam', $pre_exams);

        // Add the class to the associated classes of each selected pre_exam
        foreach ($pre_exams as $exam_id) {
            add_class_to_exam($post_id, $exam_id);
        }
    }

       if (isset($_POST['exams']) && !empty($_POST['exams'])) {
        $selected_exams = $_POST['exams'];
        $associated_exams = get_post_meta($post_id, 'associated_exams', true);

        if (!is_array($associated_exams)) {
            $associated_exams = array();
        }

        foreach ($selected_exams as $exam_id) {
            if (!in_array($exam_id, $associated_exams)) {
                $associated_exams[] = $exam_id;
                update_post_meta($post_id, 'associated_exams', $associated_exams);

                // Update the class information in the exam's 'associated_classes'
                $associated_classes = get_post_meta($exam_id, 'associated_classes', true);

                if (!is_array($associated_classes)) {
                    $associated_classes = array();
                }

                if (!in_array($post_id, $associated_classes)) {
                    $associated_classes[] = $post_id;
                    update_post_meta($exam_id, 'associated_classes', $associated_classes);
                }
            }
        }
    }


    if (isset($_POST['post_exams']) && !empty($_POST['post_exams'])) {
        $post_exams = $_POST['post_exams'];
        update_post_meta($post_id, 'associated_post_exam', $post_exams);

        // Add the class to the associated classes of each selected post_exam
        foreach ($post_exams as $exam_id) {
            add_class_to_exam($post_id, $exam_id);
        }
    }
}

// Helper function to add class to the associated classes of an exam
function add_class_to_exam($class_id, $exam_id) {
    $associated_classes = get_post_meta($exam_id, 'associated_classes', true);

    if (!is_array($associated_classes)) {
        $associated_classes = array();
    }

    if (!in_array($class_id, $associated_classes)) {
        $associated_classes[] = $class_id;
        update_post_meta($exam_id, 'associated_classes', $associated_classes);
    }
}

add_action('save_post', 'save_exams_to_class');


// Add meta box for class keys
function add_class_keys_meta_box() {
    add_meta_box(
        'class_keys_meta_box',
        'Class Keys',
        'render_class_keys_meta_box',
        'class', // Change 'class' to your actual post type
        'normal',
        'default'
    );
}

add_action('add_meta_boxes', 'add_class_keys_meta_box');
// Render the content of the class keys meta box
function render_class_keys_meta_box($post) {
    // Retrieve existing class keys and their status
    $class_keys = get_term_meta(305, 'class_keys', true) ?: array();

    // Display existing keys and their status in a table
   
    echo '<table class="widefat">';
    echo '<thead><tr><th>Select</th><th>Key</th><th>Status</th><th>User</th><th>Class</th></tr></thead>';
    echo '<tbody>';

    // Get the current class ID
    $post_id = $post->ID;

    foreach ($class_keys as $key => $data) {
        if ($data['class'] == $post_id) {
            echo '<tr>';
            echo '<td><input type="checkbox" name="delete_keys[]" value="' . esc_attr($key) . '"></td>';
            echo '<td>' . esc_html($key) . '</td>';
            echo '<td>' . esc_html($data['status']) . '</td>';
            echo '<td>' . esc_html($data['user']) . '</td>';
            echo '<td>' . esc_html($data['class']) . '</td>';
            echo '</tr>';
        }
    }

    echo '</tbody>';
    echo '</table>';

    // Add a button to generate a new key
    echo '<input type="text" name="generate_key" >';
    wp_nonce_field('class_keys_nonce', 'class_keys_nonce');
    echo '<button class="button-primary" name="generate_key_button">Generate Class Key</button>';
    
    // Add a button to delete selected keys
    echo '<button class="button" name="delete_keys_button">Delete Selected Keys</button>';
    
}

// Save class keys when the class post is saved
function save_class_keys($post_id) {
    // Verify nonce
    if (!isset($_POST['class_keys_nonce']) || !wp_verify_nonce($_POST['class_keys_nonce'], 'class_keys_nonce')) {
        return $post_id;
    }

    // Check if this is an autosave
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return $post_id;
    }

    // Generate and save a new key when the "Generate Class Key" button is clicked
    if ( isset($_POST['generate_key'])) {
        $keys_to_generate = intval($_POST['generate_key']);

        for ($i = 0; $i < $keys_to_generate; $i++) {
            $new_key = strtoupper(substr(md5(uniqid()), 0, 5)); // Generate a 5-digit hex key

            $class_keys = get_term_meta(305, 'class_keys', true) ?: array();
            $class_keys[$new_key] = array('key' => $new_key, 'status' => 'Unused', 'class' => $post_id, 'user' => '');

            update_term_meta(305, 'class_keys', $class_keys);
        }
    }

    // Delete selected keys when the "Delete Selected Keys" button is clicked
    if ( isset($_POST['delete_keys'])) {
        $keys_to_delete = $_POST['delete_keys'];

        $class_keys = get_term_meta(305, 'class_keys', true) ?: array();

        foreach ($keys_to_delete as $key) {
            unset($class_keys[$key]);
        }

        update_term_meta(305, 'class_keys', $class_keys);
    }
}

add_action('save_post', 'save_class_keys');


// Add meta box to class post type
function add_class_price_meta_box() {
    add_meta_box(
        'class_price_duration',
        'Class Price and Duration',
        'class_price_duration_callback',
        'class',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_class_price_meta_box');

// Meta box callback function
function class_price_duration_callback($post) {
    wp_nonce_field(basename(__FILE__), 'class_price_duration_nonce');
    $prices = get_post_meta($post->ID, 'price', true) ?: array();

    ?>
    <table id="class-price-duration-table" style="width: 100%;">
        <thead>
            <tr>
                <th>Duration (months)</th>
                <th>Price ($)</th>
                <th>Action</th>
            </tr>
        </thead>
        <tbody>
            <?php
            if (!empty($prices)) {
                foreach ($prices as $duration => $price) {
                    echo '<tr>';
                    echo '<td><input type="number" name="duration[]" value="' . esc_attr($duration) . '" min="1" required></td>';
                    echo '<td><input type="number" name="price[]" value="' . esc_attr($price) . '" min="0" step="0.01" required></td>';
                    echo '<td><button type="button" class="remove-row button">Remove</button></td>';
                    echo '</tr>';
                }
            }
            ?>
        </tbody>
    </table>
    <button type="button" id="add-row" class="button">Add New Price</button>

    <script>
    jQuery(document).ready(function($) {
        $('#add-row').on('click', function() {
            var row = '<tr>' +
                '<td><input type="number" name="duration[]" value="" min="1" required></td>' +
                '<td><input type="number" name="price[]" value="" min="0" step="0.01" required></td>' +
                '<td><button type="button" class="remove-row button">Remove</button></td>' +
                '</tr>';
            $('#class-price-duration-table tbody').append(row);
        });

        $('#class-price-duration-table').on('click', '.remove-row', function() {
            $(this).closest('tr').remove();
        });
    });
    </script>
    <?php
}

// Save meta box data
function save_class_price_duration_meta($post_id) {
    if (!isset($_POST['class_price_duration_nonce']) || !wp_verify_nonce($_POST['class_price_duration_nonce'], basename(__FILE__))) {
        return;
    }

    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }

    if (!current_user_can('edit_post', $post_id)) {
        return;
    }

    if (isset($_POST['duration']) && isset($_POST['price'])) {
        $durations = array_map('intval', $_POST['duration']);
        $prices = array_map('floatval', $_POST['price']);

        $price_data = array();
        for ($i = 0; $i < count($durations); $i++) {
            if (!empty($durations[$i]) && isset($prices[$i])) {
                $price_data[$durations[$i]] = $prices[$i];
            }
        }

        update_post_meta($post_id, 'price', $price_data);
    }
}
add_action('save_post_class', 'save_class_price_duration_meta');
// Add metabox to class post type
function add_class_analytics_metabox() {
    add_meta_box(
        'class_analytics_metabox',
        'Class Analytics',
        'render_class_analytics_metabox',
        'class',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_class_analytics_metabox');

// Helper function to get all question IDs under a category (including all subcategories)
function get_questions_in_category_tree($category_id) {
    $all_question_ids = [];
    
    // Get questions directly in this category
    $direct_questions = get_posts([
        'post_type' => 'question',
        'posts_per_page' => -1,
        'fields' => 'ids',
        'tax_query' => [
            [
                'taxonomy' => 'question_category',
                'field' => 'term_id',
                'terms' => $category_id,
                'include_children' => false
            ]
        ]
    ]);
    $all_question_ids = array_merge($all_question_ids, $direct_questions);
    
    // Get all subcategories recursively
    $subcats = get_terms([
        'taxonomy' => 'question_category',
        'parent' => $category_id,
        'hide_empty' => false,
    ]);
    
    foreach($subcats as $subcat) {
        // Get questions in subcategory
        $subcat_questions = get_posts([
            'post_type' => 'question',
            'posts_per_page' => -1,
            'fields' => 'ids',
            'tax_query' => [
                [
                    'taxonomy' => 'question_category',
                    'field' => 'term_id',
                    'terms' => $subcat->term_id,
                    'include_children' => false
                ]
            ]
        ]);
        $all_question_ids = array_merge($all_question_ids, $subcat_questions);
        
        // Recursively get questions from deeper subcategories
        $deeper_questions = get_questions_in_category_tree($subcat->term_id);
        $all_question_ids = array_merge($all_question_ids, $deeper_questions);
    }
    
    return array_unique($all_question_ids);
}

// Helper function to check if category has questions (in itself or subcategories)
function category_has_questions($category_id) {
    $questions = get_questions_in_category_tree($category_id);
    return count($questions) > 0;
}

// Render metabox content
function render_class_analytics_metabox($post) {
    $class_id = $post->ID;
    $exam_subject = get_post_meta($class_id, 'exam_subject', true);
    $exam_topic = get_post_meta($class_id, 'exam_topic', true);
    $category_id = $exam_topic ? $exam_topic : $exam_subject;
    
    // Get all questions under this category tree
    $all_question_ids = get_questions_in_category_tree($category_id);
    $total_questions_in_class = count($all_question_ids);
    ?>
    <div id="class-analytics-container">
        <div class="analytics-tabs">
            <button class="tab-btn active" data-tab="overview">Overview</button>
            <button class="tab-btn" data-tab="students">Students</button>
        </div>
        
        <div id="overview-tab" class="tab-content active">
            <h3>Class Performance Overview</h3>
            <div style="background: #f0f6fc; padding: 15px; border-radius: 8px; margin-bottom: 20px; border-left: 4px solid #2271b1;">
                <strong>Total Questions in Class:</strong> <?php echo $total_questions_in_class; ?> questions
            </div>
            <div id="class-overview-stats"></div>
            <div id="class-comparison-graph"></div>
        </div>
        
        <div id="students-tab" class="tab-content">
            <h3>Student Performance</h3>
            <div id="students-list"></div>
            <div class="pagination-controls">
                <button id="prev-page" disabled>← Previous</button>
                <span id="page-info"></span>
                <button id="next-page">Next →</button>
            </div>
        </div>
    </div>
    
    <!-- Subject Detail Modal -->
    <div id="subject-modal" class="subject-modal">
        <div class="modal-content">
            <span class="close-subject-modal">&times;</span>
            <div id="subject-detail"></div>
        </div>
    </div>
    
    <!-- Student Detail Modal -->
    <div id="student-modal" class="student-modal">
        <div class="modal-content">
            <span class="close-modal">&times;</span>
            <div id="student-detail"></div>
        </div>
    </div>
    
    <style>
    .analytics-tabs {
        display: flex;
        gap: 10px;
        margin-bottom: 20px;
        border-bottom: 2px solid #ddd;
    }
    .tab-btn {
        padding: 10px 20px;
        border: none;
        background: transparent;
        cursor: pointer;
        font-weight: 600;
        color: #666;
        border-bottom: 3px solid transparent;
        transition: all 0.3s;
    }
    .tab-btn:hover { color: #2271b1; }
    .tab-btn.active {
        color: #2271b1;
        border-bottom-color: #2271b1;
    }
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    .stat-card {
        display: inline-block;
        background: #f9f9f9;
        padding: 15px;
        margin: 10px;
        border-radius: 8px;
        min-width: 150px;
        text-align: center;
    }
    .stat-card h4 {
        margin: 0 0 5px;
        color: #666;
        font-size: 14px;
    }
    .stat-card .value {
        font-size: 28px;
        font-weight: bold;
        color: #2271b1;
    }
    .comparison-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
        gap: 20px;
        margin: 20px 0;
    }
    .comparison-card {
        background: white;
        border-radius: 10px;
        padding: 20px;
        box-shadow: 0 2px 10px rgba(0,0,0,0.1);
        cursor: pointer;
        transition: transform 0.2s;
        position: relative;
    }
    .comparison-card:hover {
        transform: translateY(-2px);
        box-shadow: 0 4px 15px rgba(0,0,0,0.15);
    }
    .comparison-card h4 {
        margin: 0 0 15px;
        color: #333;
        text-align: center;
        font-size: 15px;
    }
    .question-count-badge {
        position: absolute;
        top: 10px;
        right: 10px;
        background: #2271b1;
        color: white;
        padding: 4px 8px;
        border-radius: 12px;
        font-size: 11px;
        font-weight: bold;
    }
    .bar-chart {
        display: flex;
        flex-direction: column;
        gap: 15px;
    }
    .bar-item {
        display: flex;
        align-items: center;
        gap: 10px;
    }
    .bar-label {
        width: 80px;
        font-size: 13px;
        font-weight: 600;
    }
    .bar-wrapper {
        flex: 1;
        height: 30px;
        background: #f0f0f0;
        border-radius: 5px;
        position: relative;
        overflow: hidden;
    }
    .bar-fill {
        height: 100%;
        border-radius: 5px;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding-right: 8px;
        color: white;
        font-size: 12px;
        font-weight: bold;
        transition: width 0.8s ease;
    }
    .bar-fill.class { background: linear-gradient(90deg, #2271b1, #4a9fd8); }
    .bar-fill.national { background: linear-gradient(90deg, #666, #888); }
    .student-row {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 12px;
        border-bottom: 1px solid #eee;
        cursor: pointer;
        transition: background 0.2s;
    }
    .student-row:hover { background: #f9f9f9; }
    .student-info { flex: 1; }
    .student-stats {
        display: flex;
        gap: 20px;
        font-size: 14px;
        color: #666;
    }
    .pagination-controls {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 20px;
        margin-top: 20px;
    }
    .pagination-controls button {
        padding: 8px 16px;
        border: 1px solid #2271b1;
        background: white;
        color: #2271b1;
        cursor: pointer;
        border-radius: 4px;
    }
    .pagination-controls button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    .subject-modal, .student-modal {
        display: none;
        position: fixed;
        z-index: 100000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.7);
    }
    .subject-modal .modal-content, .student-modal .modal-content {
        background: white;
        margin: 3% auto;
        padding: 20px;
        width: 90%;
        max-width: 1200px;
        max-height: 85vh;
        overflow-y: auto;
        border-radius: 8px;
        position: relative;
    }
    .close-subject-modal, .close-modal {
        position: absolute;
        right: 15px;
        top: 15px;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        color: #666;
    }
    .close-subject-modal:hover, .close-modal:hover { color: #000; }
    .detail-section { margin: 20px 0; }
    .detail-section h3 {
        margin: 15px 0 10px;
        padding-bottom: 10px;
        border-bottom: 2px solid #2271b1;
    }
    .category-grid {
        display: grid;
        grid-template-columns: repeat(auto-fill, minmax(200px, 1fr));
        gap: 15px;
        margin: 15px 0;
    }
    .category-card {
        background: #f9f9f9;
        padding: 15px;
        border-radius: 8px;
        text-align: center;
    }
    .category-card h4 {
        margin: 0 0 10px;
        font-size: 14px;
        color: #666;
    }
    .category-card .score {
        font-size: 24px;
        font-weight: bold;
        color: #2271b1;
    }
    .questions-table {
        width: 100%;
        border-collapse: collapse;
        margin-top: 15px;
    }
    .questions-table th,
    .questions-table td {
        padding: 10px;
        text-align: left;
        border-bottom: 1px solid #eee;
        font-size: 13px;
    }
    .questions-table th {
        background: #f9f9f9;
        font-weight: 600;
        position: sticky;
        top: 0;
    }
    .questions-pagination {
        display: flex;
        justify-content: center;
        align-items: center;
        gap: 15px;
        margin-top: 15px;
    }
    .questions-pagination button {
        padding: 6px 12px;
        border: 1px solid #2271b1;
        background: white;
        color: #2271b1;
        cursor: pointer;
        border-radius: 4px;
        font-size: 12px;
    }
    .questions-pagination button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
    }
    .category-filter {
        margin: 15px 0;
    }
    .category-filter select {
        padding: 8px;
        border: 1px solid #ddd;
        border-radius: 4px;
        font-size: 14px;
    }
    .loading {
        text-align: center;
        padding: 20px;
        color: #666;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        let currentPage = 1;
        const perPage = 10;
        const classId = <?php echo $class_id; ?>;
        let overviewLoaded = false;
        let studentsLoaded = false;
        
        // Tab switching
        $('.tab-btn').click(function(e) {
            e.preventDefault();
            
            $('.tab-btn').removeClass('active');
            $('.tab-content').removeClass('active');
            $(this).addClass('active');
            $('#' + $(this).data('tab') + '-tab').addClass('active');
            
            if($(this).data('tab') === 'overview' && !overviewLoaded) {
                loadOverview();
                overviewLoaded = true;
            } else if($(this).data('tab') === 'students' && !studentsLoaded) {
                loadStudents(1);
                studentsLoaded = true;
            }
        });
        
        // Load initial overview
        loadOverview();
        overviewLoaded = true;
        
        // Pagination
        $('#prev-page').click(function(e) {
            e.preventDefault();
            if(currentPage > 1) {
                currentPage--;
                loadStudents(currentPage);
            }
        });
        
        $('#next-page').click(function(e) {
            e.preventDefault();
            currentPage++;
            loadStudents(currentPage);
        });
        
        // Close modals
        $('.close-subject-modal').click(function(e) {
            e.preventDefault();
            $('#subject-modal').hide();
        });
        
        $('.close-modal').click(function(e) {
            e.preventDefault();
            $('#student-modal').hide();
        });
        
        $(window).click(function(e) {
            if(e.target.id === 'subject-modal') {
                $('#subject-modal').hide();
            }
            if(e.target.id === 'student-modal') {
                $('#student-modal').hide();
            }
        });
        
        function loadOverview() {
            $('#class-overview-stats').html('<div class="loading">Loading...</div>');
            $('#class-comparison-graph').html('');
            
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'get_class_overview',
                    class_id: classId
                },
                success: function(response) {
                    const data = JSON.parse(response);
                    $('#class-overview-stats').html(data.stats);
                    $('#class-comparison-graph').html(data.graphs);
                    
                    // Attach click handlers for subject cards
                    $('.comparison-card').click(function(e) {
                        e.preventDefault();
                        const catId = $(this).data('category-id');
                        if(catId) {
                            loadSubjectDetail(catId);
                        }
                    });
                }
            });
        }
        
        function loadSubjectDetail(categoryId) {
            $('#subject-modal').show();
            $('#subject-detail').html('<div class="loading">Loading subject details...</div>');
            
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'get_subject_detail',
                    class_id: classId,
                    category_id: categoryId
                },
                success: function(response) {
                    $('#subject-detail').html(response);
                }
            });
        }
        
        function loadStudents(page) {
            $('#students-list').html('<div class="loading">Loading students...</div>');
            
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'get_students_list',
                    class_id: classId,
                    page: page,
                    per_page: perPage
                },
                success: function(response) {
                    const data = JSON.parse(response);
                    $('#students-list').html(data.html);
                    $('#page-info').text('Page ' + page + ' of ' + data.total_pages);
                    $('#prev-page').prop('disabled', page === 1);
                    $('#next-page').prop('disabled', page >= data.total_pages);
                    currentPage = page;
                    
                    // Attach click handlers
                    $('.student-row').click(function(e) {
                        e.preventDefault();
                        loadStudentDetail($(this).data('user-id'));
                    });
                }
            });
        }
        
        function loadStudentDetail(userId) {
            $('#student-modal').show();
            $('#student-detail').html('<div class="loading">Loading student details...</div>');
            
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'get_student_detail',
                    class_id: classId,
                    user_id: userId
                },
                success: function(response) {
                    $('#student-detail').html(response);
                    
                    // Attach pagination handlers
                    attachQuestionsPagination(userId);
                    
                    // Attach category filter handler
                    $('#question-category-filter').change(function() {
                        loadStudentQuestions(userId, 1, $(this).val());
                    });
                }
            });
        }
        
        function attachQuestionsPagination(userId) {
            $(document).off('click', '#prev-questions');
            $(document).off('click', '#next-questions');
            
            $(document).on('click', '#prev-questions', function(e) {
                e.preventDefault();
                const page = parseInt($(this).data('page'));
                const categoryId = $('#question-category-filter').val();
                if(page > 1) {
                    loadStudentQuestions(userId, page - 1, categoryId);
                }
            });
            
            $(document).on('click', '#next-questions', function(e) {
                e.preventDefault();
                const page = parseInt($(this).data('page'));
                const categoryId = $('#question-category-filter').val();
                loadStudentQuestions(userId, page + 1, categoryId);
            });
        }
        
        function loadStudentQuestions(userId, page, categoryId = '') {
            $('#questions-table-container').html('<div class="loading">Loading questions...</div>');
            
            $.ajax({
                url: ajaxurl,
                method: 'POST',
                data: {
                    action: 'get_student_questions',
                    class_id: classId,
                    user_id: userId,
                    page: page,
                    category_id: categoryId
                },
                success: function(response) {
                    $('#questions-table-container').html(response);
                    attachQuestionsPagination(userId);
                }
            });
        }
    });
    </script>
    <?php
}
// AJAX: Get class overview with graphs (only tier-2 subjects with questions)
function ajax_get_class_overview() {
    $class_id = intval($_POST['class_id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'exam_answers';
    
    $exam_topic_id = get_post_meta($class_id, 'exam_topic', true);
    $exam_subject_id = get_post_meta($class_id, 'exam_subject', true);
    $parent_term_id = $exam_topic_id ?: $exam_subject_id;
    
    // Get tier-1 subcategories (e.g., Electronics, Math, GEAS) - DISPLAY THESE
    $tier1_categories = get_terms([
        'taxonomy' => 'question_category',
        'parent' => $parent_term_id,
        'hide_empty' => false,
    ]);
    
    // Filter tier-1 categories that have questions (checking all subcategories)
    $tier1_with_questions = [];
    foreach($tier1_categories as $tier1) {
        if(category_has_questions($tier1->term_id)) {
            $tier1_with_questions[] = $tier1;
        }
    }
    
    // Get activities
    $class_activities = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id, is_correct, session_id, question_id FROM $table_name WHERE class_id = %d",
        $class_id
    ), ARRAY_A);
    
    $national_activities = $wpdb->get_results(
        "SELECT is_correct, question_id FROM $table_name",
        ARRAY_A
    );
    
    $total_students = count(array_unique(wp_list_pluck($class_activities, 'user_id')));
    $total_sessions = count(array_unique(wp_list_pluck($class_activities, 'session_id')));
    $total_answers = count($class_activities);
    $correct_answers = count(array_filter($class_activities, function($a) { return $a['is_correct'] == '1'; }));
    $avg_accuracy = $total_answers > 0 ? round(($correct_answers / $total_answers) * 100, 1) : 0;
    
    $national_total = count($national_activities);
    $national_correct = count(array_filter($national_activities, function($a) { return $a['is_correct'] == '1'; }));
    $national_accuracy = $national_total > 0 ? round(($national_correct / $national_total) * 100, 1) : 0;
    
    // Stats HTML
    ob_start();
    ?>
    <div>
        <div class="stat-card">
            <h4>Total Students</h4>
            <div class="value"><?php echo $total_students; ?></div>
        </div>
        <div class="stat-card">
            <h4>Total Exams</h4>
            <div class="value"><?php echo $total_sessions; ?></div>
        </div>
        <div class="stat-card">
            <h4>Total Answers</h4>
            <div class="value"><?php echo $total_answers; ?></div>
        </div>
        <div class="stat-card">
            <h4>Class Accuracy</h4>
            <div class="value"><?php echo $avg_accuracy; ?>%</div>
        </div>
        <div class="stat-card">
            <h4>National Accuracy</h4>
            <div class="value"><?php echo $national_accuracy; ?>%</div>
        </div>
    </div>
    <?php
    $stats_html = ob_get_clean();
    
    // Graphs HTML - showing tier-1 subjects (Electronics, Math, GEAS)
    ob_start();
    ?>
    <div class="comparison-grid">
        <div class="comparison-card">
            <h4>Overall Accuracy Comparison</h4>
            <div class="bar-chart">
                <div class="bar-item">
                    <div class="bar-label">This Class</div>
                    <div class="bar-wrapper">
                        <div class="bar-fill class" style="width: <?php echo $avg_accuracy; ?>%">
                            <?php echo $avg_accuracy; ?>%
                        </div>
                    </div>
                </div>
                <div class="bar-item">
                    <div class="bar-label">National</div>
                    <div class="bar-wrapper">
                        <div class="bar-fill national" style="width: <?php echo $national_accuracy; ?>%">
                            <?php echo $national_accuracy; ?>%
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <?php foreach($tier1_with_questions as $cat): 
            $question_count = count(get_questions_in_category_tree($cat->term_id));
            $question_ids_in_cat = get_questions_in_category_tree($cat->term_id);
            
            $cat_class_activities = array_filter($class_activities, function($a) use ($question_ids_in_cat) {
                return in_array($a['question_id'], $question_ids_in_cat);
            });
            
            $cat_class_total = count($cat_class_activities);
            $cat_class_correct = count(array_filter($cat_class_activities, function($a) {
                return $a['is_correct'] == '1';
            }));
            $cat_class_accuracy = $cat_class_total > 0 ? round(($cat_class_correct / $cat_class_total) * 100, 1) : 0;
            
            $cat_national_activities = array_filter($national_activities, function($a) use ($question_ids_in_cat) {
                return in_array($a['question_id'], $question_ids_in_cat);
            });
            
            $cat_national_total = count($cat_national_activities);
            $cat_national_correct = count(array_filter($cat_national_activities, function($a) {
                return $a['is_correct'] == '1';
            }));
            $cat_national_accuracy = $cat_national_total > 0 ? round(($cat_national_correct / $cat_national_total) * 100, 1) : 0;
        ?>
        <div class="comparison-card" data-category-id="<?php echo $cat->term_id; ?>">
            <span class="question-count-badge"><?php echo $question_count; ?> Q's</span>
            <h4><?php echo esc_html($cat->name); ?></h4>
            <div class="bar-chart">
                <div class="bar-item">
                    <div class="bar-label">This Class</div>
                    <div class="bar-wrapper">
                        <div class="bar-fill class" style="width: <?php echo $cat_class_accuracy; ?>%">
                            <?php echo $cat_class_accuracy; ?>%
                        </div>
                    </div>
                </div>
                <div class="bar-item">
                    <div class="bar-label">National</div>
                    <div class="bar-wrapper">
                        <div class="bar-fill national" style="width: <?php echo $cat_national_accuracy; ?>%">
                            <?php echo $cat_national_accuracy; ?>%
                        </div>
                    </div>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
    $graphs_html = ob_get_clean();
    
    echo json_encode([
        'stats' => $stats_html,
        'graphs' => $graphs_html
    ]);
    wp_die();
}
add_action('wp_ajax_get_class_overview', 'ajax_get_class_overview');

// AJAX: Get subject detail (shows all subcategories under clicked subject)
function ajax_get_subject_detail() {
    $class_id = intval($_POST['class_id']);
    $category_id = intval($_POST['category_id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'exam_answers';
    
    $subject = get_term($category_id);
    
    // Get all subcategories under this subject
    $all_subcategories = get_terms([
        'taxonomy' => 'question_category',
        'parent' => $category_id,
        'hide_empty' => false,
    ]);
    
    $class_activities = $wpdb->get_results($wpdb->prepare(
        "SELECT is_correct, question_id FROM $table_name WHERE class_id = %d",
        $class_id
    ), ARRAY_A);
    
    $national_activities = $wpdb->get_results(
        "SELECT is_correct, question_id FROM $table_name",
        ARRAY_A
    );
    
    // Calculate subject overall stats
    $subject_question_ids = get_questions_in_category_tree($category_id);
    $subject_class_activities = array_filter($class_activities, function($a) use ($subject_question_ids) {
        return in_array($a['question_id'], $subject_question_ids);
    });
    
    $subject_total = count($subject_class_activities);
    $subject_correct = count(array_filter($subject_class_activities, function($a) {
        return $a['is_correct'] == '1';
    }));
    $subject_accuracy = $subject_total > 0 ? round(($subject_correct / $subject_total) * 100, 1) : 0;
    
    ?>
    <h2><?php echo esc_html($subject->name); ?> - Detailed Performance</h2>
    
    <div class="detail-section">
        <h3>Overall Performance</h3>
        <div class="stat-card">
            <h4>Total Questions</h4>
            <div class="value"><?php echo count($subject_question_ids); ?></div>
        </div>
        <div class="stat-card">
            <h4>Total Answers</h4>
            <div class="value"><?php echo $subject_total; ?></div>
        </div>
        <div class="stat-card">
            <h4>Accuracy</h4>
            <div class="value"><?php echo $subject_accuracy; ?>%</div>
        </div>
    </div>
    
    <div class="detail-section">
        <h3>Performance by Subcategory</h3>
        <div class="comparison-grid">
            <?php foreach($all_subcategories as $subcat): 
                if(!category_has_questions($subcat->term_id)) continue;
                
                $subcat_question_ids = get_questions_in_category_tree($subcat->term_id);
                $question_count = count($subcat_question_ids);
                
                $subcat_class_activities = array_filter($class_activities, function($a) use ($subcat_question_ids) {
                    return in_array($a['question_id'], $subcat_question_ids);
                });
                
                $subcat_class_total = count($subcat_class_activities);
                $subcat_class_correct = count(array_filter($subcat_class_activities, function($a) {
                    return $a['is_correct'] == '1';
                }));
                $subcat_class_accuracy = $subcat_class_total > 0 ? round(($subcat_class_correct / $subcat_class_total) * 100, 1) : 0;
                
                $subcat_national_activities = array_filter($national_activities, function($a) use ($subcat_question_ids) {
                    return in_array($a['question_id'], $subcat_question_ids);
                });
                
                $subcat_national_total = count($subcat_national_activities);
                $subcat_national_correct = count(array_filter($subcat_national_activities, function($a) {
                    return $a['is_correct'] == '1';
                }));
                $subcat_national_accuracy = $subcat_national_total > 0 ? round(($subcat_national_correct / $subcat_national_total) * 100, 1) : 0;
            ?>
            <div class="comparison-card">
                <span class="question-count-badge"><?php echo $question_count; ?> Q's</span>
                <h4><?php echo esc_html($subcat->name); ?></h4>
                <div class="bar-chart">
                    <div class="bar-item">
                        <div class="bar-label">This Class</div>
                        <div class="bar-wrapper">
                            <div class="bar-fill class" style="width: <?php echo $subcat_class_accuracy; ?>%">
                                <?php echo $subcat_class_accuracy; ?>%
                            </div>
                        </div>
                    </div>
                    <div class="bar-item">
                        <div class="bar-label">National</div>
                        <div class="bar-wrapper">
                            <div class="bar-fill national" style="width: <?php echo $subcat_national_accuracy; ?>%">
                                <?php echo $subcat_national_accuracy; ?>%
                            </div>
                        </div>
                    </div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    <?php
    wp_die();
}
add_action('wp_ajax_get_subject_detail', 'ajax_get_subject_detail');

// AJAX: Get students list
function ajax_get_students_list() {
    $class_id = intval($_POST['class_id']);
    $page = intval($_POST['page']);
    $per_page = intval($_POST['per_page']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'exam_answers';
    
    $all_students = $wpdb->get_results($wpdb->prepare(
        "SELECT DISTINCT user_id FROM $table_name WHERE class_id = %d",
        $class_id
    ), ARRAY_A);
    
    $student_stats = [];
    foreach($all_students as $student) {
        $user_id = $student['user_id'];
        $user = get_userdata($user_id);
        
        $activities = $wpdb->get_results($wpdb->prepare(
            "SELECT is_correct, session_id FROM $table_name WHERE class_id = %d AND user_id = %d",
            $class_id, $user_id
        ), ARRAY_A);
        
        $total = count($activities);
        $correct = count(array_filter($activities, function($a) { return $a['is_correct'] == '1'; }));
        $sessions = count(array_unique(wp_list_pluck($activities, 'session_id')));
        $accuracy = $total > 0 ? round(($correct / $total) * 100, 1): 0;
        
        $student_stats[] = [
            'user_id' => $user_id,
            'name' => $user ? $user->display_name : 'Unknown',
            'total' => $total,
            'correct' => $correct,
            'sessions' => $sessions,
            'accuracy' => $accuracy
        ];
    }
    
    usort($student_stats, function($a, $b) {
        return $b['accuracy'] - $a['accuracy'];
    });
    
    $total_students = count($student_stats);
    $total_pages = ceil($total_students / $per_page);
    $offset = ($page - 1) * $per_page;
    $paginated_students = array_slice($student_stats, $offset, $per_page);
    
    ob_start();
    foreach($paginated_students as $index => $student) {
        $rank = $offset + $index + 1;
        ?>
        <div class="student-row" data-user-id="<?php echo $student['user_id']; ?>">
            <div class="student-info">
                <strong>#<?php echo $rank; ?> - <?php echo esc_html($student['name']); ?></strong>
            </div>
            <div class="student-stats">
                <span>Sessions: <strong><?php echo $student['sessions']; ?></strong></span>
                <span>Answers: <strong><?php echo $student['total']; ?></strong></span>
                <span>Accuracy: <strong><?php echo $student['accuracy']; ?>%</strong></span>
            </div>
        </div>
        <?php
    }
    $html = ob_get_clean();
    
    echo json_encode([
        'html' => $html,
        'total_pages' => $total_pages
    ]);
    wp_die();
}
add_action('wp_ajax_get_students_list', 'ajax_get_students_list');

// AJAX: Get student detail
function ajax_get_student_detail() {
    $class_id = intval($_POST['class_id']);
    $user_id = intval($_POST['user_id']);
    
    $user = get_userdata($user_id);
    global $wpdb;
    $table_name = $wpdb->prefix . 'exam_answers';
    
    $exam_topic_id = get_post_meta($class_id, 'exam_topic', true);
    $exam_subject_id = get_post_meta($class_id, 'exam_subject', true);
    $parent_term_id = $exam_topic_id ?: $exam_subject_id;
    
    // Get tier-1 subcategories (e.g., Electronics, Math, GEAS) - DISPLAY THESE
    $tier1_categories = get_terms([
        'taxonomy' => 'question_category',
        'parent' => $parent_term_id,
        'hide_empty' => false,
    ]);
    
    // Filter tier-1 categories that have questions (checking all subcategories)
    $tier1_with_questions = [];
    foreach($tier1_categories as $tier1) {
        if(category_has_questions($tier1->term_id)) {
            $tier1_with_questions[] = $tier1;
        }
    }
    
    
    // Get all tier-2 subcategories that have questions
    $tier2_with_questions = [];
    foreach($tier1_categories as $tier1) {
        $tier2_subs = get_terms([
            'taxonomy' => 'question_category',
            'parent' => $tier1->term_id,
            'hide_empty' => false,
        ]);
        
        foreach($tier2_subs as $tier2) {
            if(category_has_questions($tier2->term_id)) {
                $tier2_with_questions[] = $tier2;
            }
        }
    }
    
    $activities = $wpdb->get_results($wpdb->prepare(
        "SELECT question_id, is_correct FROM $table_name 
         WHERE class_id = %d AND user_id = %d",
        $class_id, $user_id
    ), ARRAY_A);
    
    $total = count($activities);
    $correct = count(array_filter($activities, function($a) { return $a['is_correct'] == '1'; }));
    $accuracy = $total > 0 ? round(($correct / $total) * 100, 1) : 0;
    
    ?>
    <h2><?php echo esc_html($user->display_name); ?> - Detailed Analytics</h2>
    
    <div class="detail-section">
        <h3>Overall Performance</h3>
        <div class="stat-card">
            <h4>Total Answers</h4>
            <div class="value"><?php echo $total; ?></div>
        </div>
        <div class="stat-card">
            <h4>Correct Answers</h4>
            <div class="value"><?php echo $correct; ?></div>
        </div>
        <div class="stat-card">
            <h4>Accuracy</h4>
            <div class="value"><?php echo $accuracy; ?>%</div>
        </div>
    </div>
    
    <div class="detail-section">
        <h3>Performance by Category</h3>
        <div class="category-grid">
            <?php foreach($tier1_with_questions as $cat): 
                $question_ids_in_cat = get_questions_in_category_tree($cat->term_id);
                $question_count = count($question_ids_in_cat);
                
                $cat_activities = array_filter($activities, function($a) use ($question_ids_in_cat) {
                    return in_array($a['question_id'], $question_ids_in_cat);
                });
                
                $cat_total = count($cat_activities);
                $cat_correct = count(array_filter($cat_activities, function($a) { 
                    return $a['is_correct'] == '1'; 
                }));
                $cat_accuracy = $cat_total > 0 ? round(($cat_correct / $cat_total) * 100, 1) : 0;
            ?>
            <div class="category-card">
                <h4><?php echo esc_html($cat->name); ?></h4>
                <div class="score"><?php echo $cat_accuracy; ?>%</div>
                <small><?php echo $cat_correct; ?>/<?php echo $cat_total; ?> correct</small>
                <div style="margin-top: 8px; font-size: 11px; color: #2271b1; font-weight: 600;">
                    <?php echo $question_count; ?> questions total
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
    
    <div class="detail-section">
        <h3>Question Details</h3>
        
        <div class="category-filter">
            <label>Filter by Category: </label>
            <select id="question-category-filter">
                <option value="">All Categories</option>
                <?php foreach($tier1_with_questions as $cat): 
                    $cat_question_count = count(get_questions_in_category_tree($cat->term_id));
                ?>
                    <option value="<?php echo $cat->term_id; ?>">
                        <?php echo esc_html($cat->name); ?> (<?php echo $cat_question_count; ?> questions)
                    </option>
                <?php endforeach; ?>
            </select>
        </div>
        
        <div id="questions-table-container">
            <?php echo render_student_questions_table($class_id, $user_id, 1, ''); ?>
        </div>
    </div>
    <?php
    wp_die();
}
add_action('wp_ajax_get_student_detail', 'ajax_get_student_detail');

// AJAX: Get student questions with pagination
function ajax_get_student_questions() {
    $class_id = intval($_POST['class_id']);
    $user_id = intval($_POST['user_id']);
    $page = intval($_POST['page']);
    $category_id = isset($_POST['category_id']) && $_POST['category_id'] !== '' ? intval($_POST['category_id']) : '';
    
    echo render_student_questions_table($class_id, $user_id, $page, $category_id);
    wp_die();
}
add_action('wp_ajax_get_student_questions', 'ajax_get_student_questions');

// Helper function to render questions table with pagination
function render_student_questions_table($class_id, $user_id, $page = 1, $category_id = '') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'exam_answers';
    $per_page = 50;
    
    // Build query
    $where = $wpdb->prepare("class_id = %d AND user_id = %d", $class_id, $user_id);
    if($category_id !== '') {
        $question_ids_in_tree = get_questions_in_category_tree($category_id);
        
        if(!empty($question_ids_in_tree)) {
            $ids_placeholder = implode(',', array_map('intval', $question_ids_in_tree));
            $where .= " AND question_id IN ($ids_placeholder)";
        } else {
            $where .= " AND 1=0";
        }
    }
    
    // Get total count
    $total = $wpdb->get_var("SELECT COUNT(DISTINCT question_id) FROM $table_name WHERE $where");
    $total_pages = ceil($total / $per_page);
    $offset = ($page - 1) * $per_page;
    
    // Get paginated questions
    $question_ids = $wpdb->get_col(
        "SELECT DISTINCT question_id FROM $table_name WHERE $where LIMIT $per_page OFFSET $offset"
    );
    
    // Get all activities for these questions
    $activities = [];
    if(!empty($question_ids)) {
        $ids_placeholder = implode(',', array_fill(0, count($question_ids), '%d'));
        $query = $wpdb->prepare(
            "SELECT question_id, is_correct FROM $table_name 
             WHERE class_id = %d AND user_id = %d AND question_id IN ($ids_placeholder)",
            array_merge([$class_id, $user_id], $question_ids)
        );
        $activities = $wpdb->get_results($query, ARRAY_A);
    }
    
    // Build question stats
    $question_stats = [];
    foreach($activities as $activity) {
        $qid = $activity['question_id'];
        if(!isset($question_stats[$qid])) {
            $question_stats[$qid] = [
                'total' => 0,
                'correct' => 0
            ];
        }
        $question_stats[$qid]['total']++;
        if($activity['is_correct'] == '1') {
            $question_stats[$qid]['correct']++;
        }
    }
    
    ob_start();
    ?>
    <table class="questions-table">
        <thead>
            <tr>
                <th>Question</th>
                <th>Category</th>
                <th>Sub-Category</th>
                <th>Times Answered</th>
                <th>Times Correct</th>
                <th>Accuracy</th>
            </tr>
        </thead>
        <tbody>
            <?php if(empty($question_stats)): ?>
                <tr>
                    <td colspan="6" style="text-align: center; padding: 20px;">No questions found.</td>
                </tr>
            <?php else: ?>
                <?php foreach($question_stats as $qid => $stats): 
                    $q_accuracy = $stats['total'] > 0 ? round(($stats['correct'] / $stats['total']) * 100, 1) : 0;
                    $question_post = get_post($qid);
                    
                    if(!$question_post) continue;
                    
                    $question_categories = wp_get_post_terms($qid, 'question_category', array('fields' => 'all'));
                    
                    $main_cat = null;
                    $question_cat = null;
                    
                    if(!empty($question_categories) && !is_wp_error($question_categories)) {
                        foreach($question_categories as $cat) {
                            if($cat->parent == 0) {
                                $main_cat = $cat;
                            } else {
                                $question_cat = $cat;
                                if(!$main_cat && $cat->parent) {
                                    $main_cat = get_term($cat->parent, 'question_category');
                                }
                            }
                        }
                        
                        if(count($question_categories) == 1) {
                            $single_cat = $question_categories[0];
                            if($single_cat->parent == 0) {
                                $main_cat = $single_cat;
                                $question_cat = null;
                            } else {
                                $question_cat = $single_cat;
                                $main_cat = get_term($single_cat->parent, 'question_category');
                            }
                        }
                    }
                ?>
                <tr>
                    <td><?php echo esc_html(get_the_title($qid)); ?></td>
                    <td><?php echo $main_cat ? esc_html($main_cat->name) : 'N/A'; ?></td>
                    <td><?php echo $question_cat ? esc_html($question_cat->name) : '-'; ?></td>
                    <td><?php echo $stats['total']; ?></td>
                    <td><?php echo $stats['correct']; ?></td>
                    <td><strong><?php echo $q_accuracy; ?>%</strong></td>
                </tr>
                <?php endforeach; ?>
            <?php endif; ?>
        </tbody>
    </table>
    
    <?php if($total_pages > 1): ?>
    <div class="questions-pagination">
        <button id="prev-questions" data-page="<?php echo $page; ?>" <?php echo $page <= 1 ? 'disabled' : ''; ?>>
            ← Previous
        </button>
        <span>Page <?php echo $page; ?> of <?php echo $total_pages; ?> (Total: <?php echo $total; ?> questions)</span>
        <button id="next-questions" data-page="<?php echo $page; ?>" <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>
            Next →
        </button>
    </div>
    <?php endif; ?>
    <?php
    return ob_get_clean();
}
?>