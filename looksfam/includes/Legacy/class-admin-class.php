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

function add_class_exam_settings_meta_box() {
    add_meta_box(
        'class_exam_settings',
        'Exam Settings',
        'render_class_exam_settings_meta_box',
        'class',
        'side',
        'default'
    );
}
add_action('add_meta_boxes', 'add_class_exam_settings_meta_box');

function render_class_exam_settings_meta_box($post) {
    wp_nonce_field('class_exam_settings_nonce', 'class_exam_settings_nonce_field');
    
    $num_questions = get_post_meta($post->ID, '_exam_num_questions', true);
    $time_per_question = get_post_meta($post->ID, '_exam_time_per_question', true);
    
    $num_questions = $num_questions ? $num_questions : 10;
    $time_per_question = $time_per_question ? $time_per_question : 30;
    ?>
    <p>
        <label for="exam_num_questions">Number of Questions:</label><br>
        <input type="number" id="exam_num_questions" name="exam_num_questions" 
               value="<?php echo esc_attr($num_questions); ?>" min="1" style="width:100%;">
    </p>
    <p>
        <label for="exam_time_per_question">Time per Question (seconds):</label><br>
        <input type="number" id="exam_time_per_question" name="exam_time_per_question" 
               value="<?php echo esc_attr($time_per_question); ?>" min="1" style="width:100%;">
    </p>
    <?php
}
function save_class_exam_settings_meta_box($post_id) {
    if (!isset($_POST['class_exam_settings_nonce_field']) || 
        !wp_verify_nonce($_POST['class_exam_settings_nonce_field'], 'class_exam_settings_nonce')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) {
        return;
    }
    
    if (!current_user_can('edit_post', $post_id)) {
        return;
    }
    
    if (isset($_POST['exam_num_questions'])) {
        update_post_meta($post_id, '_exam_num_questions', intval($_POST['exam_num_questions']));
    }
    
    if (isset($_POST['exam_time_per_question'])) {
        update_post_meta($post_id, '_exam_time_per_question', intval($_POST['exam_time_per_question']));
    }
}
add_action('save_post_class', 'save_class_exam_settings_meta_box');
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


function is_exam_associated_with_other_classes($exam_id, $current_class_id) {
    $classes_with_exam = get_post_meta($exam_id, 'associated_classes', true);

    return $classes_with_exam && count($classes_with_exam) > 1 && !in_array($current_class_id, $classes_with_exam);
}


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
// Optimized helper function - single query for all questions in category tree
function get_questions_in_category_tree($category_id) {
    global $wpdb;
    $questions_table = $wpdb->prefix . 'imported_questions';
    
    // Get all subcategories
    $subcats = get_term_children($category_id, 'question_category');
    $all_categories = array_merge([$category_id], $subcats);
    
    // Get category identifiers
    $category_identifiers = [];
    foreach ($all_categories as $cat_id) {
        $term = get_term($cat_id, 'question_category');
        if ($term && !is_wp_error($term)) {
            $category_identifiers[] = $cat_id;
            $category_identifiers[] = $term->slug;
            $category_identifiers[] = $term->name;
        }
    }
    
    if (empty($category_identifiers)) return [];
    
    $placeholders = implode(',', array_fill(0, count($category_identifiers), '%s'));
    $questions = $wpdb->get_results($wpdb->prepare(
        "SELECT id, post_id FROM $questions_table WHERE category IN ($placeholders)",
        $category_identifiers
    ), ARRAY_A);

    $question_ids = [];
    foreach ($questions as $question) {
        if (!empty($question['id'])) {
            $question_ids[] = (int) $question['id'];
        }
        if (!empty($question['post_id'])) {
            $question_ids[] = (int) $question['post_id'];
        }
    }
    
    return array_unique($question_ids);
}

// Render metabox content
function render_class_analytics_metabox($post) {
    $class_id = $post->ID;
    ?>
    <div id="class-analytics-container">
        <!-- Date Filter -->
        <div class="analytics-header">
            <div class="date-filter">
                <button class="filter-btn active" data-period="all">All Time</button>
                <button class="filter-btn" data-period="week">Last 7 Days</button>
                <button class="filter-btn" data-period="month">Last 30 Days</button>
                <button class="filter-btn" data-period="year">Last Year</button>
                <button class="filter-btn" data-period="custom">Custom Range</button>
            </div>
            <div class="custom-date-range" style="display: none;">
                <input type="date" id="date-from" />
                <span>to</span>
                <input type="date" id="date-to" />
                <button id="apply-custom-date">Apply</button>
            </div>
        </div>
        
        <!-- Tabs -->
        <div class="analytics-tabs">
            <button class="tab-btn active" data-tab="overview">Overview</button>
            <button class="tab-btn" data-tab="categories">Categories</button>
            <button class="tab-btn" data-tab="students">Students</button>
            <button class="tab-btn" data-tab="questions">Questions</button>
            <button class="tab-btn" data-tab="trends">Trends</button>
        </div>
        
        <!-- Overview Tab -->
        <div id="overview-tab" class="tab-content active">
            <div id="overview-stats" class="loading">Loading...</div>
        </div>
        
        <!-- Categories Tab -->
        <div id="categories-tab" class="tab-content">
            <div id="categories-stats" class="loading">Loading...</div>
        </div>
        
        <!-- Students Tab -->
        <div id="students-tab" class="tab-content">
            <div id="students-list" class="loading">Loading...</div>
        </div>
        
        <!-- Questions Tab -->
        <div id="questions-tab" class="tab-content">
            <div class="filter-section" style="margin-bottom: 20px; background: #f8f9fa; padding: 15px; border-radius: 8px;">
                <label for="category-filter" style="font-weight: 600; margin-right: 10px;">Filter by Category:</label>
                <select id="category-filter" style="padding: 8px 12px; border: 1px solid #ddd; border-radius: 4px; min-width: 200px;">
                    <option value="">All Categories</option>
                </select>
            </div>
            <div id="questions-list" class="loading">Loading...</div>
        </div>
        
        <!-- Trends Tab -->
        <div id="trends-tab" class="tab-content">
            <div id="trends-chart" class="loading">Loading...</div>
        </div>
        
        <!-- Student Detail Modal -->
        <div id="student-modal" class="modal">
            <div class="modal-content">
                <span class="close" data-modal="student">&times;</span>
                <div id="student-detail"></div>
            </div>
        </div>
    </div>
    
    <style>
    #class-analytics-container { font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif; }
    
    .analytics-header {
        background: #f8f9fa;
        padding: 20px;
        border-radius: 8px;
        margin-bottom: 20px;
    }
    
    .date-filter {
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .filter-btn {
        padding: 8px 16px;
        border: 2px solid #e0e0e0;
        background: white;
        border-radius: 6px;
        cursor: pointer;
        font-size: 14px;
        transition: all 0.2s;
    }
    
    .filter-btn:hover { border-color: #2271b1; color: #2271b1; }
    .filter-btn.active { background: #2271b1; color: white; border-color: #2271b1; }
    
    .custom-date-range {
        margin-top: 15px;
        display: flex;
        align-items: center;
        gap: 10px;
    }
    
    .custom-date-range input { padding: 8px; border: 1px solid #ddd; border-radius: 4px; }
    #apply-custom-date { padding: 8px 16px; background: #2271b1; color: white; border: none; border-radius: 4px; cursor: pointer; }
    
    .analytics-tabs {
        display: flex;
        gap: 5px;
        border-bottom: 2px solid #e0e0e0;
        margin-bottom: 25px;
    }
    
    .tab-btn {
        padding: 12px 24px;
        border: none;
        background: transparent;
        cursor: pointer;
        font-weight: 600;
        color: #666;
        border-bottom: 3px solid transparent;
        transition: all 0.2s;
    }
    
    .tab-btn:hover { color: #2271b1; }
    .tab-btn.active { color: #2271b1; border-bottom-color: #2271b1; }
    
    .tab-content { display: none; }
    .tab-content.active { display: block; }
    
    .loading {
        text-align: center;
        padding: 40px;
        color: #999;
        font-size: 16px;
    }
    
    .stats-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .stat-card {
        background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
        color: white;
        padding: 25px;
        border-radius: 12px;
        box-shadow: 0 4px 15px rgba(0,0,0,0.1);
        position: relative;
        overflow: hidden;
    }
    
    .stat-card:nth-child(2) { background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%); }
    .stat-card:nth-child(3) { background: linear-gradient(135deg, #4facfe 0%, #00f2fe 100%); }
    .stat-card:nth-child(4) { background: linear-gradient(135deg, #43e97b 0%, #38f9d7 100%); }
    .stat-card:nth-child(5) { background: linear-gradient(135deg, #fa709a 0%, #fee140 100%); }
    .stat-card:nth-child(6) { background: linear-gradient(135deg, #30cfd0 0%, #330867 100%); }
    
    .stat-card h4 {
        margin: 0 0 10px;
        font-size: 14px;
        opacity: 0.9;
        font-weight: 500;
    }
    
    .stat-card .value {
        font-size: 36px;
        font-weight: 700;
        margin: 0;
    }
    
    .stat-card .subtext {
        font-size: 12px;
        opacity: 0.8;
        margin-top: 5px;
    }
    
    .comparison-section {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 25px;
        margin-bottom: 30px;
    }
    
    .comparison-section h3 {
        margin: 0 0 20px;
        color: #333;
    }
    
    .comparison-bars {
        display: flex;
        flex-direction: column;
        gap: 20px;
    }
    
    .comparison-item {
        display: flex;
        align-items: center;
        gap: 15px;
    }
    
    .comparison-label {
        width: 120px;
        font-weight: 600;
        color: #333;
    }
    
    .comparison-bar {
        flex: 1;
        height: 40px;
        background: #f5f5f5;
        border-radius: 8px;
        position: relative;
        overflow: hidden;
    }
    
    .comparison-fill {
        height: 100%;
        display: flex;
        align-items: center;
        justify-content: flex-end;
        padding-right: 12px;
        color: white;
        font-weight: 700;
        font-size: 14px;
        transition: width 0.8s ease;
    }
    
    .comparison-fill.class-bar {
        background: linear-gradient(90deg, #2271b1, #4a9fd8);
    }
    
    .comparison-fill.national-bar {
        background: linear-gradient(90deg, #666, #999);
    }
    
    .categories-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
        gap: 20px;
    }
    
    .category-card {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 20px;
        transition: all 0.2s;
        cursor: pointer;
        position: relative;
    }
    
    .category-card:hover {
        box-shadow: 0 4px 20px rgba(0,0,0,0.1);
        transform: translateY(-2px);
    }
    
    .category-card.expanded {
        border-color: #2271b1;
        box-shadow: 0 4px 15px rgba(34, 113, 177, 0.2);
    }
    
    .category-card h4 {
        margin: 0 0 15px;
        color: #333;
        font-size: 16px;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .expand-icon {
        font-size: 20px;
        transition: transform 0.3s;
    }
    
    .expand-icon.rotated {
        transform: rotate(180deg);
    }
    
    .subcategories-container {
        display: none;
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #e0e0e0;
    }
    
    .subcategories-container.show {
        display: block;
    }
    
    .subcategories-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 15px;
        margin-top: 15px;
    }
    
    .subcat-card {
        background: #f8f9fa;
        padding: 15px;
        border-radius: 8px;
        border: 1px solid #e0e0e0;
    }
    
    .subcat-card h5 {
        margin: 0 0 10px;
        font-size: 14px;
        color: #555;
    }
    
    .progress-bar {
        height: 8px;
        background: #f0f0f0;
        border-radius: 4px;
        overflow: hidden;
        margin: 10px 0;
    }
    
    .progress-fill {
        height: 100%;
        background: linear-gradient(90deg, #2271b1, #4a9fd8);
        border-radius: 4px;
        transition: width 0.8s ease;
    }
    
    .stats-row {
        display: flex;
        justify-content: space-between;
        font-size: 13px;
        color: #666;
        margin-top: 10px;
    }
    
    .student-item, .question-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 15px;
        border: 1px solid #e0e0e0;
        border-radius: 8px;
        margin-bottom: 10px;
        cursor: pointer;
        transition: all 0.2s;
    }
    
    .student-item:hover, .question-item:hover {
        background: #f8f9fa;
        border-color: #2271b1;
    }
    
    .student-name, .question-title { font-weight: 600; color: #333; flex: 1; }
    .student-stats, .question-stats { display: flex; gap: 20px; font-size: 14px; color: #666; }
    
    .difficulty-badge {
        display: inline-block;
        padding: 4px 12px;
        border-radius: 12px;
        font-size: 12px;
        font-weight: 600;
    }
    
    .difficulty-easy { background: #d4edda; color: #155724; }
    .difficulty-medium { background: #fff3cd; color: #856404; }
    .difficulty-hard { background: #f8d7da; color: #721c24; }
    
    .pagination-controls button, .pagination-btn {
        padding: 8px 16px;
        border: 1px solid #2271b1;
        background: white;
        color: #2271b1;
        cursor: pointer;
        border-radius: 4px;
        font-size: 14px;
    }
    
    .pagination-controls button:disabled, .pagination-btn:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        border-color: #ddd;
        color: #999;
    }
    
    .pagination-controls button:not(:disabled):hover, .pagination-btn:not(:disabled):hover {
        background: #2271b1;
        color: white;
    }
    
    .modal {
        display: none;
        position: fixed;
        z-index: 100000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background: rgba(0,0,0,0.6);
    }
    
    .modal-content {
        background: white;
        margin: 3% auto;
        padding: 30px;
        width: 90%;
        max-width: 1000px;
        max-height: 85vh;
        overflow-y: auto;
        border-radius: 12px;
        position: relative;
    }
    
    .close {
        position: absolute;
        right: 20px;
        top: 20px;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        color: #999;
    }
    
    .close:hover { color: #333; }
    
    
    .chart-container {
        background: white;
        border: 1px solid #e0e0e0;
        border-radius: 10px;
        padding: 20px;
        margin-top: 20px;
    }
    
    .insights-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(250px, 1fr));
        gap: 20px;
        margin-bottom: 30px;
    }
    
    .insight-card {
        background: #f8f9fa;
        border-left: 4px solid #2271b1;
        padding: 20px;
        border-radius: 8px;
    }
    
    .insight-card h4 {
        margin: 0 0 10px;
        color: #2271b1;
        font-size: 14px;
    }
    
    .insight-card p {
        margin: 0;
        color: #666;
        font-size: 13px;
        line-height: 1.6;
    }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        const classId = <?php echo $class_id; ?>;
        let currentPeriod = 'all';
        let dateFrom = '';
        let dateTo = '';
        let tabsLoaded = {
            overview: false,
            categories: false,
            students: false,
            questions: false,
            trends: false
        };
        
        // Date filter buttons
        $('.filter-btn').click(function(e) {
            e.preventDefault();
            $('.filter-btn').removeClass('active');
            $(this).addClass('active');
            currentPeriod = $(this).data('period');
            
            if (currentPeriod === 'custom') {
                $('.custom-date-range').slideDown();
            } else {
                $('.custom-date-range').slideUp();
                dateFrom = '';
                dateTo = '';
                resetAndLoadData();
            }
        });
        
        $('#apply-custom-date').click(function(e) {
            e.preventDefault();
            dateFrom = $('#date-from').val();
            dateTo = $('#date-to').val();
            if (dateFrom && dateTo) {
                resetAndLoadData();
            }
        });
        
        // Tab switching - lazy load
        $('.tab-btn').click(function(e) {
            e.preventDefault();
            
            const tabName = $(this).data('tab');
            
            $('.tab-btn').removeClass('active');
            $('.tab-content').removeClass('active');
            $(this).addClass('active');
            $('#' + tabName + '-tab').addClass('active');
            
            // Only load if not already loaded
            if (!tabsLoaded[tabName]) {
                loadTab(tabName);
                tabsLoaded[tabName] = true;
            }
        });
        
        // Load initial overview tab only
        loadOverview();
        tabsLoaded.overview = true;
        
        function resetAndLoadData() {
            // Reset all tabs as not loaded
            tabsLoaded = {
                overview: false,
                categories: false,
                students: false,
                questions: false,
                trends: false
            };
            
            // Load current active tab
            const activeTab = $('.tab-btn.active').data('tab');
            loadTab(activeTab);
            tabsLoaded[activeTab] = true;
        }
        
        function loadTab(tabName) {
            switch(tabName) {
                case 'overview':
                    loadOverview();
                    break;
                case 'categories':
                    loadCategories();
                    break;
                case 'students':
                    loadStudents();
                    break;
                case 'questions':
                    loadQuestions();
                    break;
                case 'trends':
                    loadTrends();
                    break;
            }
        }
        
        function loadOverview() {
            $('#overview-stats').html('<div class="loading">Loading...</div>');
            
            $.post(ajaxurl, {
                action: 'get_analytics_overview',
                class_id: classId,
                period: currentPeriod,
                date_from: dateFrom,
                date_to: dateTo
            }, function(response) {
                $('#overview-stats').html(response);
            });
        }
        
        function loadCategories() {
            $('#categories-stats').html('<div class="loading">Loading...</div>');
            
            $.post(ajaxurl, {
                action: 'get_analytics_categories',
                class_id: classId,
                period: currentPeriod,
                date_from: dateFrom,
                date_to: dateTo
            }, function(response) {
                $('#categories-stats').html(response);
                
                // Attach click handlers for category expansion
                $('.category-card').click(function(e) {
                    e.preventDefault();
                    const $card = $(this);
                    const categoryId = $card.data('category-id');
                    const $container = $card.find('.subcategories-container');
                    const $icon = $card.find('.expand-icon');
                    
                    if ($container.hasClass('show')) {
                        // Collapse
                        $container.removeClass('show').slideUp(300);
                        $card.removeClass('expanded');
                        $icon.removeClass('rotated');
                    } else {
                        // Check if already loaded
                        if ($container.data('loaded')) {
                            $container.addClass('show').slideDown(300);
                            $card.addClass('expanded');
                            $icon.addClass('rotated');
                        } else {
                            // Load subcategories
                            $container.html('<div class="loading">Loading subcategories...</div>');
                            $container.addClass('show').slideDown(300);
                            $card.addClass('expanded');
                            $icon.addClass('rotated');
                            
                            $.post(ajaxurl, {
                                action: 'get_category_subcategories',
                                class_id: classId,
                                category_id: categoryId,
                                period: currentPeriod,
                                date_from: dateFrom,
                                date_to: dateTo
                            }, function(subcatResponse) {
                                $container.html(subcatResponse);
                                $container.data('loaded', true);
                            });
                        }
                    }
                });
            });
        }
        
        function loadStudents() {
            $('#students-list').html('<div class="loading">Loading...</div>');
            
            $.post(ajaxurl, {
                action: 'get_analytics_students',
                class_id: classId,
                period: currentPeriod,
                date_from: dateFrom,
                date_to: dateTo
            }, function(response) {
                $('#students-list').html(response);
                
                $('.student-item').click(function(e) {
                    e.preventDefault();
                    loadStudentDetail($(this).data('user-id'));
                });
            });
        }
        
        function loadQuestions() {
            // Load category filter options
            $.post(ajaxurl, {
                action: 'get_category_filter_options',
                class_id: classId
            }, function(response) {
                const categories = JSON.parse(response);
                const $filter = $('#category-filter');
                $filter.find('option:not(:first)').remove();
                
                categories.forEach(function(cat) {
                    $filter.append($('<option>', {
                        value: cat.id,
                        text: cat.name
                    }));
                });
            });
            
            loadQuestionsPage(1);
        }
        
        function loadQuestionsPage(page) {
            $('#questions-list').html('<div class="loading">Loading...</div>');
            
            const categoryFilter = $('#category-filter').val();
            
            $.post(ajaxurl, {
                action: 'get_analytics_questions',
                class_id: classId,
                period: currentPeriod,
                date_from: dateFrom,
                date_to: dateTo,
                page: page,
                category_filter: categoryFilter
            }, function(response) {
                $('#questions-list').html(response);
                
                // Attach pagination handlers
                $('.pagination-btn').click(function(e) {
                    e.preventDefault();
                    if (!$(this).prop('disabled')) {
                        loadQuestionsPage($(this).data('page'));
                    }
                });
            });
        }
        
        // Category filter change handler
        $(document).on('change', '#category-filter', function() {
            loadQuestionsPage(1);
        });
        
        function loadTrends() {
            $('#trends-chart').html('<div class="loading">Loading...</div>');
            
            $.post(ajaxurl, {
                action: 'get_analytics_trends',
                class_id: classId,
                period: currentPeriod,
                date_from: dateFrom,
                date_to: dateTo
            }, function(response) {
                $('#trends-chart').html(response);
            });
        }
        
        function loadStudentDetail(userId) {
            $('#student-modal').show();
            $('#student-detail').html('<div class="loading">Loading...</div>');
            
            $.post(ajaxurl, {
                action: 'get_student_analytics',
                class_id: classId,
                user_id: userId,
                period: currentPeriod,
                date_from: dateFrom,
                date_to: dateTo
            }, function(response) {
                $('#student-detail').html(response);
                
                // Attach click handlers for category expansion in modal
                $('.student-category-card').click(function(e) {
                    e.preventDefault();
                    const $card = $(this);
                    const categoryId = $card.data('category-id');
                    const $container = $card.find('.subcategories-container');
                    const $icon = $card.find('.expand-icon');
                    
                    if ($container.hasClass('show')) {
                        // Collapse
                        $container.removeClass('show').slideUp(300);
                        $card.removeClass('expanded');
                        $icon.removeClass('rotated');
                    } else {
                        // Check if already loaded
                        if ($container.data('loaded')) {
                            $container.addClass('show').slideDown(300);
                            $card.addClass('expanded');
                            $icon.addClass('rotated');
                        } else {
                            // Load subcategories for student
                            $container.html('<div class="loading">Loading breakdown...</div>');
                            $container.addClass('show').slideDown(300);
                            $card.addClass('expanded');
                            $icon.addClass('rotated');
                            
                            $.post(ajaxurl, {
                                action: 'get_student_category_breakdown',
                                class_id: classId,
                                user_id: userId,
                                category_id: categoryId,
                                period: currentPeriod,
                                date_from: dateFrom,
                                date_to: dateTo
                            }, function(subcatResponse) {
                                $container.html(subcatResponse);
                                $container.data('loaded', true);
                            });
                        }
                    }
                });
            });
        }
        
        function loadCategoryDetail(categoryId) {
            $('#category-modal').show();
            $('#category-detail').html('<div class="loading">Loading...</div>');
            
            $.post(ajaxurl, {
                action: 'get_category_analytics',
                class_id: classId,
                category_id: categoryId,
                period: currentPeriod,
                date_from: dateFrom,
                date_to: dateTo
            }, function(response) {
                $('#category-detail').html(response);
            });
        }
        
        $('.close').click(function(e) {
            e.preventDefault();
            const modal = $(this).data('modal');
            $('#' + modal + '-modal').hide();
        });
        
        $(window).click(function(e) {
            if (e.target.id === 'student-modal') {
                $('#student-modal').hide();
            }
        });
    });
    </script>
    <?php
}

function ajax_get_analytics_overview() {
    global $wpdb;
    $class_id = intval($_POST['class_id']);
    $period = sanitize_text_field($_POST['period']);
    $date_from = sanitize_text_field($_POST['date_from']);
    $date_to = sanitize_text_field($_POST['date_to']);
    
    $table_name = $wpdb->prefix . 'exam_answers';
    $where = $wpdb->prepare("class_id = %d", $class_id);
    $date_filter = get_date_filter($period, $date_from, $date_to);
    $where .= $date_filter;
    
    // Get class exam topic/subject
    $exam_topic = get_post_meta($class_id, 'exam_topic', true);
    $exam_subject = get_post_meta($class_id, 'exam_subject', true);
    $parent_term = $exam_topic ?: $exam_subject;
    
    // Get all questions in this subject/topic tree
    $subject_question_ids = [];
    if ($parent_term) {
        $subject_question_ids = get_questions_in_category_tree($parent_term);
    }
    
    // Class data
    $class_results = $wpdb->get_results(
        "SELECT user_id, is_correct, session_id, question_id, question_category 
         FROM $table_name WHERE $where",
        ARRAY_A
    );
    
    // National data - filtered by same subject questions
    if (!empty($subject_question_ids)) {
        $placeholders = implode(',', array_fill(0, count($subject_question_ids), '%d'));
        $national_results = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT is_correct, question_id FROM $table_name WHERE question_id IN ($placeholders)",
                $subject_question_ids
            ),
            ARRAY_A
        );
    } else {
        // Fallback to all questions if no subject defined
        $national_results = $wpdb->get_results(
            "SELECT is_correct, question_id FROM $table_name",
            ARRAY_A
        );
    }
    
    // Calculate class stats
    $unique_students = array_unique(wp_list_pluck($class_results, 'user_id'));
    $unique_sessions = array_unique(wp_list_pluck($class_results, 'session_id'));
    $unique_questions = array_unique(wp_list_pluck($class_results, 'question_id'));
    
    $class_total = count($class_results);
    $class_correct = count(array_filter($class_results, fn($r) => $r['is_correct'] == '1'));
    $class_accuracy = $class_total > 0 ? round(($class_correct / $class_total) * 100, 1) : 0;
    
    // Calculate national stats (same subject only)
    $national_total = count($national_results);
    $national_correct = count(array_filter($national_results, fn($r) => $r['is_correct'] == '1'));
    $national_accuracy = $national_total > 0 ? round(($national_correct / $national_total) * 100, 1) : 0;
    
    // Calculate average attempts per question
    $avg_attempts = count($unique_questions) > 0 ? round($class_total / count($unique_questions), 1) : 0;
    
    // Get performance difference
    $performance_diff = $class_accuracy - $national_accuracy;
    
    ob_start();
    ?>
    <div class="stats-grid">
        <div class="stat-card">
            <h4>Active Students</h4>
            <div class="value"><?php echo count($unique_students); ?></div>
            <div class="subtext">Participating in class</div>
        </div>
        <div class="stat-card">
            <h4>Total Sessions</h4>
            <div class="value"><?php echo count($unique_sessions); ?></div>
            <div class="subtext"><?php echo count($unique_students) > 0 ? round(count($unique_sessions) / count($unique_students), 1) : 0; ?> per student</div>
        </div>
        <div class="stat-card">
            <h4>Total Answers</h4>
            <div class="value"><?php echo number_format($class_total); ?></div>
            <div class="subtext"><?php echo count($unique_questions); ?> unique questions</div>
        </div>
        <div class="stat-card">
            <h4>Class Accuracy</h4>
            <div class="value"><?php echo $class_accuracy; ?>%</div>
            <div class="subtext"><?php echo $class_correct; ?>/<?php echo $class_total; ?> correct</div>
        </div>
        <div class="stat-card">
            <h4>Avg Attempts/Question</h4>
            <div class="value"><?php echo $avg_attempts; ?></div>
            <div class="subtext">Practice frequency</div>
        </div>
        <div class="stat-card">
            <h4>vs National</h4>
            <div class="value"><?php echo $performance_diff > 0 ? '+' : ''; ?><?php echo number_format($performance_diff, 1); ?>%</div>
            <div class="subtext"><?php echo $performance_diff > 0 ? 'Above' : 'Below'; ?> average</div>
        </div>
    </div>
    
    <div class="comparison-section">
        <h3>📊 Class vs National Performance</h3>
        <div class="comparison-bars">
            <div class="comparison-item">
                <div class="comparison-label">This Class</div>
                <div class="comparison-bar">
                    <div class="comparison-fill class-bar" style="width: <?php echo $class_accuracy; ?>%">
                        <?php echo $class_accuracy; ?>% (<?php echo number_format($class_correct); ?>/<?php echo number_format($class_total); ?>)
                    </div>
                </div>
            </div>
            <div class="comparison-item">
                <div class="comparison-label">National Average</div>
                <div class="comparison-bar">
                    <div class="comparison-fill national-bar" style="width: <?php echo $national_accuracy; ?>%">
                        <?php echo $national_accuracy; ?>% (<?php echo number_format($national_correct); ?>/<?php echo number_format($national_total); ?>)
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <div class="insights-grid">
        <?php if ($performance_diff > 5): ?>
        <div class="insight-card">
            <h4>🎉 Excellent Performance</h4>
            <p>Your class is performing <?php echo number_format($performance_diff, 1); ?>% above the national average. Keep up the great work!</p>
        </div>
        <?php elseif ($performance_diff < -5): ?>
        <div class="insight-card">
            <h4>💡 Improvement Opportunity</h4>
            <p>Your class is <?php echo number_format(abs($performance_diff), 1); ?>% below the national average. Consider focusing on challenging topics.</p>
        </div>
        <?php else: ?>
        <div class="insight-card">
            <h4>📈 On Track</h4>
            <p>Your class is performing at the national average level. Consistent practice can boost results.</p>
        </div>
        <?php endif; ?>
        
        <?php if (count($unique_students) > 0 && count($unique_sessions) / count($unique_students) < 3): ?>
        <div class="insight-card">
            <h4>🎯 Engagement Tip</h4>
            <p>Average sessions per student is low. Encourage more regular practice for better retention.</p>
        </div>
        <?php endif; ?>
        
        <?php if ($avg_attempts > 3): ?>
        <div class="insight-card">
            <h4>✅ Strong Practice Habits</h4>
            <p>Students are averaging <?php echo $avg_attempts; ?> attempts per question, showing good practice consistency.</p>
        </div>
        <?php endif; ?>
    </div>
    <?php
    echo ob_get_clean();
    wp_die();
}
add_action('wp_ajax_get_analytics_overview', 'ajax_get_analytics_overview');

// AJAX: Get categories breakdown (main categories only, expandable)
function ajax_get_analytics_categories() {
    global $wpdb;
    $class_id = intval($_POST['class_id']);
    $period = sanitize_text_field($_POST['period']);
    $date_from = sanitize_text_field($_POST['date_from']);
    $date_to = sanitize_text_field($_POST['date_to']);
    
    $table_name = $wpdb->prefix . 'exam_answers';
    $where = $wpdb->prepare("class_id = %d", $class_id);
    $date_filter = get_date_filter($period, $date_from, $date_to);
    $where .= $date_filter;
    
    $class_results = $wpdb->get_results(
        "SELECT question_id, is_correct FROM $table_name WHERE $where",
        ARRAY_A
    );
    
    $national_results = $wpdb->get_results(
        "SELECT question_id, is_correct FROM $table_name ",
        ARRAY_A
    );
    
    $exam_topic = get_post_meta($class_id, 'exam_topic', true);
    $exam_subject = get_post_meta($class_id, 'exam_subject', true);
    $parent_term = $exam_topic ?: $exam_subject;
    
    $categories = get_terms([
        'taxonomy' => 'question_category',
        'parent' => $parent_term,
        'hide_empty' => false,
    ]);
    
    ob_start();
    ?>
    <h3 style="margin-bottom: 20px;">Category Performance Analysis</h3>
    <p style="color: #666; margin-bottom: 25px;">Click on a category to view subcategory breakdown</p>
    
    <div class="categories-grid">
        <?php foreach($categories as $cat):
            $cat_questions = get_questions_in_category_tree($cat->term_id);
            if (empty($cat_questions)) continue;
            
            $cat_class_results = array_filter($class_results, fn($r) => in_array($r['question_id'], $cat_questions));
            $cat_class_total = count($cat_class_results);
            $cat_class_correct = count(array_filter($cat_class_results, fn($r) => $r['is_correct'] == '1'));
            $cat_class_accuracy = $cat_class_total > 0 ? round(($cat_class_correct / $cat_class_total) * 100, 1) : 0;
            
            $cat_national_results = array_filter($national_results, fn($r) => in_array($r['question_id'], $cat_questions));
            $cat_national_total = count($cat_national_results);
            $cat_national_correct = count(array_filter($cat_national_results, fn($r) => $r['is_correct'] == '1'));
            $cat_national_accuracy = $cat_national_total > 0 ? round(($cat_national_correct / $cat_national_total) * 100, 1) : 0;
            
            $diff = $cat_class_accuracy - $cat_national_accuracy;
        ?>
        <div class="category-card" data-category-id="<?php echo $cat->term_id; ?>">
            <h4>
                <?php echo esc_html($cat->name); ?>
                <span class="expand-icon">▼</span>
            </h4>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $cat_class_accuracy; ?>%"></div>
            </div>
            <div class="stats-row">
                <span><strong><?php echo $cat_class_accuracy; ?>%</strong> Class</span>
                <span><?php echo $cat_class_correct; ?>/<?php echo $cat_class_total; ?></span>
            </div>
            <div class="stats-row">
                <span><?php echo $cat_national_accuracy; ?>% National</span>
                <span style="color: <?php echo $diff > 0 ? '#28a745' : '#dc3545'; ?>">
                    <?php echo $diff > 0 ? '+' : ''; ?><?php echo number_format($diff, 1); ?>%
                </span>
            </div>
            
            <!-- Subcategories container (loaded on click) -->
            <div class="subcategories-container"></div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
    echo ob_get_clean();
    wp_die();
}
add_action('wp_ajax_get_analytics_categories', 'ajax_get_analytics_categories');

// AJAX: Get subcategories for a specific category
function ajax_get_category_subcategories() {
    global $wpdb;
    $class_id = intval($_POST['class_id']);
    $category_id = intval($_POST['category_id']);
    $period = sanitize_text_field($_POST['period']);
    $date_from = sanitize_text_field($_POST['date_from']);
    $date_to = sanitize_text_field($_POST['date_to']);
    
    $table_name = $wpdb->prefix . 'exam_answers';
    $where = $wpdb->prepare("class_id = %d", $class_id);
    $date_filter = get_date_filter($period, $date_from, $date_to);
    $where .= $date_filter;
    
    $class_results = $wpdb->get_results(
        "SELECT question_id, is_correct FROM $table_name WHERE $where",
        ARRAY_A
    );
    
    $national_results = $wpdb->get_results(
        "SELECT question_id, is_correct FROM $table_name ",
        ARRAY_A
    );
    
    $subcategories = get_terms([
        'taxonomy' => 'question_category',
        'parent' => $category_id,
        'hide_empty' => false,
    ]);
    
    if (empty($subcategories)) {
        echo '<p style="color: #999; font-style: italic; padding: 10px 0;">No subcategories available</p>';
        wp_die();
    }
    
    ob_start();
    ?>
    <h5 style="margin: 15px 0 10px; color: #666; font-size: 13px; font-weight: 600;">SUBCATEGORIES:</h5>
    <div class="subcategories-grid">
        <?php foreach($subcategories as $subcat):
            $subcat_questions = get_questions_in_category_tree($subcat->term_id);
            if (empty($subcat_questions)) continue;
            
            $subcat_class_results = array_filter($class_results, fn($r) => in_array($r['question_id'], $subcat_questions));
            $subcat_class_total = count($subcat_class_results);
            $subcat_class_correct = count(array_filter($subcat_class_results, fn($r) => $r['is_correct'] == '1'));
            $subcat_class_accuracy = $subcat_class_total > 0 ? round(($subcat_class_correct / $subcat_class_total) * 100, 1) : 0;
            
            $subcat_national_results = array_filter($national_results, fn($r) => in_array($r['question_id'], $subcat_questions));
            $subcat_national_total = count($subcat_national_results);
            $subcat_national_correct = count(array_filter($subcat_national_results, fn($r) => $r['is_correct'] == '1'));
            $subcat_national_accuracy = $subcat_national_total > 0 ? round(($subcat_national_correct / $subcat_national_total) * 100, 1) : 0;
            
            $subcat_diff = $subcat_class_accuracy - $subcat_national_accuracy;
        ?>
        <div class="subcat-card">
            <h5><?php echo esc_html($subcat->name); ?></h5>
            <div class="progress-bar" style="height: 6px;">
                <div class="progress-fill" style="width: <?php echo $subcat_class_accuracy; ?>%"></div>
            </div>
            <div class="stats-row" style="font-size: 12px; margin-top: 8px;">
                <span><strong><?php echo $subcat_class_accuracy; ?>%</strong></span>
                <span><?php echo $subcat_class_correct; ?>/<?php echo $subcat_class_total; ?></span>
            </div>
            <div class="stats-row" style="font-size: 11px;">
                <span style="color: #999;"><?php echo $subcat_national_accuracy; ?>% National</span>
                <span style="color: <?php echo $subcat_diff > 0 ? '#28a745' : '#dc3545'; ?>">
                    <?php echo $subcat_diff > 0 ? '+' : ''; ?><?php echo number_format($subcat_diff, 1); ?>%
                </span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
    echo ob_get_clean();
    wp_die();
}
add_action('wp_ajax_get_category_subcategories', 'ajax_get_category_subcategories');

// AJAX: Get students list with rankings (sorted by attempts)
function ajax_get_analytics_students() {
    global $wpdb;
    $class_id = intval($_POST['class_id']);
    $period = sanitize_text_field($_POST['period']);
    $date_from = sanitize_text_field($_POST['date_from']);
    $date_to = sanitize_text_field($_POST['date_to']);
    
    $table_name = $wpdb->prefix . 'exam_answers';
    $where = $wpdb->prepare("class_id = %d", $class_id);
    $where .= get_date_filter($period, $date_from, $date_to);
    
    $results = $wpdb->get_results(
        "SELECT user_id, is_correct, session_id, question_id FROM $table_name WHERE $where",
        ARRAY_A
    );
    
    $student_stats = [];
    foreach($results as $r) {
        if (!isset($student_stats[$r['user_id']])) {
            $student_stats[$r['user_id']] = [
                'total' => 0, 
                'correct' => 0, 
                'sessions' => [],
                'questions' => []
            ];
        }
        $student_stats[$r['user_id']]['total']++;
        if ($r['is_correct'] == '1') $student_stats[$r['user_id']]['correct']++;
        $student_stats[$r['user_id']]['sessions'][$r['session_id']] = true;
        $student_stats[$r['user_id']]['questions'][$r['question_id']] = true;
    }
    
    $students = [];
    foreach($student_stats as $user_id => $stats) {
        $user = get_userdata($user_id);
        $accuracy = $stats['total'] > 0 ? round(($stats['correct'] / $stats['total']) * 100, 1) : 0;
        $students[] = [
            'id' => $user_id,
            'name' => $user ? $user->display_name : 'Unknown',
            'accuracy' => $accuracy,
            'total' => $stats['total'],
            'correct' => $stats['correct'],
            'sessions' => count($stats['sessions']),
            'questions' => count($stats['questions'])
        ];
    }
    
    // Sort by total attempts (most active first)
    usort($students, fn($a, $b) => $b['total'] <=> $a['total']);
    
    ob_start();
    ?>
    <h3 style="margin-bottom: 20px;">Student Rankings (by Activity)</h3>
    <?php 
    $rank = 1;
    foreach($students as $student):
        $medal = '';
        if ($rank == 1) $medal = '🥇';
        elseif ($rank == 2) $medal = '🥈';
        elseif ($rank == 3) $medal = '🥉';
    ?>
    <div class="student-item" data-user-id="<?php echo $student['id']; ?>">
        <div class="student-name">
            <?php echo $medal; ?> #<?php echo $rank; ?> - <?php echo esc_html($student['name']); ?>
        </div>
        <div class="student-stats">
            <span>Attempts: <strong><?php echo $student['total']; ?></strong></span>
            <span>Accuracy: <strong><?php echo $student['accuracy']; ?>%</strong></span>
            <span>Correct: <strong><?php echo $student['correct']; ?></strong></span>
            <span>Sessions: <strong><?php echo $student['sessions']; ?></strong></span>
            <span>Questions: <strong><?php echo $student['questions']; ?></strong></span>
        </div>
    </div>
    <?php 
    $rank++;
    endforeach; ?>
    <?php
    echo ob_get_clean();
    wp_die();
}
add_action('wp_ajax_get_analytics_students', 'ajax_get_analytics_students');

// AJAX: Get category filter options for questions
function ajax_get_category_filter_options() {
    $class_id = intval($_POST['class_id']);
    
    $exam_topic = get_post_meta($class_id, 'exam_topic', true);
    $exam_subject = get_post_meta($class_id, 'exam_subject', true);
    $parent_term = $exam_topic ?: $exam_subject;
    
    $categories = get_terms([
        'taxonomy' => 'question_category',
        'parent' => $parent_term,
        'hide_empty' => false,
    ]);
    
    $options = [];
    foreach($categories as $cat) {
        $options[] = [
            'id' => $cat->term_id,
            'name' => $cat->name
        ];
    }
    
    echo json_encode($options);
    wp_die();
}
add_action('wp_ajax_get_category_filter_options', 'ajax_get_category_filter_options');
// AJAX: Get questions analytics with pagination and category filter
function ajax_get_analytics_questions() {
    global $wpdb;
    $class_id = intval($_POST['class_id']);
    $period = sanitize_text_field($_POST['period']);
    $date_from = sanitize_text_field($_POST['date_from']);
    $date_to = sanitize_text_field($_POST['date_to']);
    $page = isset($_POST['page']) ? intval($_POST['page']) : 1;
    $category_filter = isset($_POST['category_filter']) && $_POST['category_filter'] !== '' ? intval($_POST['category_filter']) : 0;
    $performance_filter = isset($_POST['performance_filter']) ? sanitize_text_field($_POST['performance_filter']) : 'worst'; // 'worst' or 'best'
    $per_page = 20;
    
    $table_name = $wpdb->prefix . 'exam_answers';
    $questions_table = $wpdb->prefix . 'imported_questions';
    $where = $wpdb->prepare("class_id = %d", $class_id);
    $where .= get_date_filter($period, $date_from, $date_to);
    
    $results = $wpdb->get_results(
        "SELECT question_id, is_correct FROM $table_name WHERE $where",
        ARRAY_A
    );
    
    $question_stats = [];
    foreach($results as $r) {
        if (!isset($question_stats[$r['question_id']])) {
            $question_stats[$r['question_id']] = ['total' => 0, 'correct' => 0];
        }
        $question_stats[$r['question_id']]['total']++;
        if ($r['is_correct'] == '1') $question_stats[$r['question_id']]['correct']++;
    }
    
    // Get question details
    $question_ids = array_keys($question_stats);
    $questions_data = [];
    
    if (!empty($question_ids)) {
        $placeholders = implode(',', array_fill(0, count($question_ids), '%d'));
        $questions_data = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, post_id, title, category FROM $questions_table 
                 WHERE id IN ($placeholders) OR post_id IN ($placeholders)",
                array_merge($question_ids, $question_ids)
            ),
            ARRAY_A
        );
    }
    
    // Filter by category if selected
    $filtered_question_ids = [];
    if ($category_filter > 0) {
        $cat_questions = get_questions_in_category_tree($category_filter);
        $filtered_question_ids = $cat_questions;
    }
    
    $questions = [];
    foreach($questions_data as $q) {
        // Apply category filter
        $imported_id = (int) $q['id'];
        $post_id = (int) $q['post_id'];

        if ($category_filter > 0 && !in_array($imported_id, $filtered_question_ids) && !in_array($post_id, $filtered_question_ids)) {
            continue;
        }
        
        $stats = ['total' => 0, 'correct' => 0];
        foreach ([$imported_id, $post_id] as $possible_question_id) {
            if ($possible_question_id && isset($question_stats[$possible_question_id])) {
                $stats['total'] += $question_stats[$possible_question_id]['total'];
                $stats['correct'] += $question_stats[$possible_question_id]['correct'];
            }
        }

        if ($stats['total'] === 0) {
            continue;
        }

        $accuracy = $stats['total'] > 0 ? round(($stats['correct'] / $stats['total']) * 100, 1) : 0;
        
        $difficulty = 'easy';
        if ($accuracy < 40) $difficulty = 'hard';
        elseif ($accuracy < 70) $difficulty = 'medium';
        
        // Get category name
        $category_name = 'N/A';
        $category_value = $q['category'];
        
        if (is_numeric($category_value)) {
            $term = get_term($category_value, 'question_category');
            if ($term && !is_wp_error($term)) {
                $category_name = $term->name;
            }
        } else {
            $term = get_term_by('slug', $category_value, 'question_category');
            if (!$term) {
                $term = get_term_by('name', $category_value, 'question_category');
            }
            if ($term && !is_wp_error($term)) {
                $category_name = $term->name;
            }
        }
        
        $questions[] = [
            'id' => $post_id ?: $imported_id,
            'title' => $q['title'],
            'category' => $category_name,
            'accuracy' => $accuracy,
            'total' => $stats['total'],
            'correct' => $stats['correct'],
            'difficulty' => $difficulty
        ];
    }
    
    // Sort based on performance filter
    if ($performance_filter === 'worst') {
        // Worst: High attempts + Low accuracy
        usort($questions, function($a, $b) {
            // First compare by accuracy (lower is worse)
            $accuracy_diff = $a['accuracy'] - $b['accuracy'];
            if (abs($accuracy_diff) > 5) { // If accuracy difference is significant
                return $accuracy_diff;
            }
            // If accuracy is similar, prioritize higher attempts
            return $b['total'] - $a['total'];
        });
    } else {
        // Best: High attempts + High accuracy
        usort($questions, function($a, $b) {
            // First compare by accuracy (higher is better)
            $accuracy_diff = $b['accuracy'] - $a['accuracy'];
            if (abs($accuracy_diff) > 5) { // If accuracy difference is significant
                return $accuracy_diff;
            }
            // If accuracy is similar, prioritize higher attempts
            return $b['total'] - $a['total'];
        });
    }
    
    // Pagination
    $total_questions = count($questions);
    $total_pages = ceil($total_questions / $per_page);
    $offset = ($page - 1) * $per_page;
    $paginated_questions = array_slice($questions, $offset, $per_page);
    
    ob_start();
    ?>
    <div style="display: flex; justify-content: space-between; align-items: center; margin-bottom: 20px;">
        <div>
            <h3 style="margin: 0 0 10px 0;">Question Performance Analysis</h3>
            <p style="color: #666; margin: 0;">
                Showing <?php echo $performance_filter === 'worst' ? 'worst' : 'best'; ?> performing questions - Total: <?php echo $total_questions; ?>
            </p>
        </div>
        <div style="display: flex; gap: 10px;">
            <button class="performance-filter-btn <?php echo $performance_filter === 'worst' ? 'active' : ''; ?>" data-filter="worst">
                Worst Performing
            </button>
            <button class="performance-filter-btn <?php echo $performance_filter === 'best' ? 'active' : ''; ?>" data-filter="best">
                Best Performing
            </button>
        </div>
    </div>
    
    <?php if (empty($paginated_questions)): ?>
        <p style="text-align: center; padding: 40px; color: #999;">No questions found for the selected filter.</p>
    <?php else: ?>
        <?php foreach($paginated_questions as $q): ?>
        <div class="question-item">
            <div class="question-title">
                <?php echo esc_html($q['title']); ?>
                <span class="difficulty-badge difficulty-<?php echo $q['difficulty']; ?>">
                    <?php echo ucfirst($q['difficulty']); ?>
                </span>
            </div>
            <div class="question-stats">
                <span>Category: <strong><?php echo esc_html($q['category']); ?></strong></span>
                <span>Accuracy: <strong><?php echo $q['accuracy']; ?>%</strong></span>
                <span>Attempts: <strong><?php echo $q['total']; ?></strong></span>
                <span>Correct: <strong><?php echo $q['correct']; ?></strong></span>
            </div>
        </div>
        <?php endforeach; ?>
        
        <?php if ($total_pages > 1): ?>
        <div class="pagination-controls" style="display: flex; justify-content: center; align-items: center; gap: 15px; margin-top: 20px;">
            <button class="pagination-btn" data-page="<?php echo max(1, $page - 1); ?>" <?php echo $page <= 1 ? 'disabled' : ''; ?>>
                ← Previous
            </button>
            <span>Page <?php echo $page; ?> of <?php echo $total_pages; ?></span>
            <button class="pagination-btn" data-page="<?php echo min($total_pages, $page + 1); ?>" <?php echo $page >= $total_pages ? 'disabled' : ''; ?>>
                Next →
            </button>
        </div>
        <?php endif; ?>
    <?php endif; ?>
    
    <style>
        .performance-filter-btn {
            padding: 8px 16px;
            border: 1px solid #ddd;
            background: white;
            border-radius: 4px;
            cursor: pointer;
            transition: all 0.3s;
        }
        .performance-filter-btn:hover {
            border-color: #999;
        }
        .performance-filter-btn.active {
            background: var(--ast-global-color-0, #007bff);
            color: white;
            border-color: var(--ast-global-color-0, #007bff);
        }
    </style>
    <?php
    echo ob_get_clean();
    wp_die();
}
add_action('wp_ajax_get_analytics_questions', 'ajax_get_analytics_questions');

// AJAX: Get trends with enhanced visualizations
function ajax_get_analytics_trends() {
    global $wpdb;
    $class_id = intval($_POST['class_id']);
    $period = sanitize_text_field($_POST['period']);
    $date_from = sanitize_text_field($_POST['date_from']);
    $date_to = sanitize_text_field($_POST['date_to']);
    
    $table_name = $wpdb->prefix . 'exam_answers';
    $where = $wpdb->prepare("class_id = %d", $class_id);
    $where .= get_date_filter($period, $date_from, $date_to);
    
    $results = $wpdb->get_results(
        "SELECT DATE(timestamp) as date, is_correct, user_id FROM $table_name WHERE $where ORDER BY timestamp ASC",
        ARRAY_A
    );
    
    $daily_stats = [];
    foreach($results as $r) {
        $date = $r['date'];
        if (!isset($daily_stats[$date])) {
            $daily_stats[$date] = ['total' => 0, 'correct' => 0, 'students' => []];
        }
        $daily_stats[$date]['total']++;
        if ($r['is_correct'] == '1') $daily_stats[$date]['correct']++;
        $daily_stats[$date]['students'][$r['user_id']] = true;
    }
    
    ob_start();
    ?>
    <div class="chart-container">
        <h3>📈 Daily Activity & Performance Trends</h3>
        <canvas id="trends-canvas" width="800" height="400"></canvas>
    </div>
    
    <div class="stats-grid" style="margin-top: 30px;">
        <?php 
        $total_days = count($daily_stats);
        $avg_daily_answers = $total_days > 0 ? round(array_sum(array_column($daily_stats, 'total')) / $total_days, 1) : 0;
        $peak_day = !empty($daily_stats) ? array_keys($daily_stats, max($daily_stats))[0] : 'N/A';
        ?>
        <div class="stat-card">
            <h4>Active Days</h4>
            <div class="value"><?php echo $total_days; ?></div>
            <div class="subtext">Days with activity</div>
        </div>
        <div class="stat-card">
            <h4>Avg Daily Answers</h4>
            <div class="value"><?php echo $avg_daily_answers; ?></div>
            <div class="subtext">Per active day</div>
        </div>
        <div class="stat-card">
            <h4>Peak Activity</h4>
            <div class="value"><?php echo max(array_column($daily_stats, 'total')); ?></div>
            <div class="subtext"><?php echo $peak_day; ?></div>
        </div>
    </div>
    
    <script>
    (function() {
        const ctx = document.getElementById('trends-canvas').getContext('2d');
        const data = <?php echo json_encode($daily_stats); ?>;
        const dates = Object.keys(data);
        
        if (dates.length === 0) {
            ctx.font = '16px sans-serif';
            ctx.fillStyle = '#999';
            ctx.textAlign = 'center';
            ctx.fillText('No data available for this period', 400, 200);
            return;
        }
        
        const totals = dates.map(d => data[d].total);
        const accuracies = dates.map(d => data[d].total > 0 ? (data[d].correct / data[d].total * 100) : 0);
        
        const padding = 60;
        const width = 800;
        const height = 400;
        const chartWidth = width - padding * 2;
        const chartHeight = height - padding * 2;
        
        ctx.clearRect(0, 0, width, height);
        
        // Draw grid
        ctx.strokeStyle = '#f0f0f0';
        ctx.lineWidth = 1;
        for (let i = 0; i <= 5; i++) {
            const y = padding + (chartHeight / 5) * i;
            ctx.beginPath();
            ctx.moveTo(padding, y);
            ctx.lineTo(width - padding, y);
            ctx.stroke();
        }
        
        // Draw axes
        ctx.strokeStyle = '#333';
        ctx.lineWidth = 2;
        ctx.beginPath();
        ctx.moveTo(padding, padding);
        ctx.lineTo(padding, height - padding);
        ctx.lineTo(width - padding, height - padding);
        ctx.stroke();
        
        // Draw bars and accuracy line
        const barWidth = Math.max(10, (chartWidth / dates.length) - 5);
        const maxTotal = Math.max(...totals);
        const points = [];
        
        dates.forEach((date, i) => {
            const x = padding + (chartWidth / dates.length) * i + (chartWidth / dates.length - barWidth) / 2;
            const barHeight = (totals[i] / maxTotal) * (chartHeight - 40);
            const y = height - padding - barHeight;
            
            // Draw bar
            ctx.fillStyle = '#2271b1';
            ctx.fillRect(x, y, barWidth, barHeight);
            
            // Plot accuracy point
            const accY = height - padding - (accuracies[i] / 100) * (chartHeight - 40);
            points.push({ x: x + barWidth / 2, y: accY });
            
            // Date label (every nth date)
            if (i % Math.ceil(dates.length / 10) === 0) {
                ctx.fillStyle = '#666';
                ctx.font = '10px sans-serif';
                ctx.save();
                ctx.translate(x + barWidth / 2, height - padding + 15);
                ctx.rotate(-Math.PI / 4);
                ctx.fillText(date.substring(5), 0, 0);
                ctx.restore();
            }
        });
        
        // Draw accuracy line
        ctx.strokeStyle = '#f5576c';
        ctx.lineWidth = 3;
        ctx.beginPath();
        points.forEach((p, i) => {
            if (i === 0) ctx.moveTo(p.x, p.y);
            else ctx.lineTo(p.x, p.y);
        });
        ctx.stroke();
        
        // Draw accuracy points
        ctx.fillStyle = '#f5576c';
        points.forEach(p => {
            ctx.beginPath();
            ctx.arc(p.x, p.y, 4, 0, Math.PI * 2);
            ctx.fill();
        });
        
        // Legend
        ctx.fillStyle = '#2271b1';
        ctx.fillRect(width - 150, 20, 20, 15);
        ctx.fillStyle = '#333';
        ctx.font = '12px sans-serif';
        ctx.fillText('Daily Answers', width - 125, 32);
        
        ctx.strokeStyle = '#f5576c';
        ctx.lineWidth = 3;
        ctx.beginPath();
        ctx.moveTo(width - 150, 50);
        ctx.lineTo(width - 130, 50);
        ctx.stroke();
        ctx.fillStyle = '#333';
        ctx.fillText('Accuracy %', width - 125, 53);
    })();
    </script>
    <?php
    echo ob_get_clean();
    wp_die();
}
add_action('wp_ajax_get_analytics_trends', 'ajax_get_analytics_trends');

// AJAX: Get student detailed analytics with category breakdown
function ajax_get_student_analytics() {
    global $wpdb;
    $class_id = intval($_POST['class_id']);
    $user_id = intval($_POST['user_id']);
    $period = sanitize_text_field($_POST['period']);
    $date_from = sanitize_text_field($_POST['date_from']);
    $date_to = sanitize_text_field($_POST['date_to']);
    
    $user = get_userdata($user_id);
    $table_name = $wpdb->prefix . 'exam_answers';
    $where = $wpdb->prepare("class_id = %d AND user_id = %d", $class_id, $user_id);
    $where .= get_date_filter($period, $date_from, $date_to);
    
    $results = $wpdb->get_results(
        "SELECT is_correct, question_id, question_category, session_id FROM $table_name WHERE $where",
        ARRAY_A
    );
    
    $total = count($results);
    $correct = count(array_filter($results, fn($r) => $r['is_correct'] == '1'));
    $accuracy = $total > 0 ? round(($correct / $total) * 100, 1) : 0;
    $sessions = count(array_unique(wp_list_pluck($results, 'session_id')));
    $questions = count(array_unique(wp_list_pluck($results, 'question_id')));
    
    // Get category breakdown
    $exam_topic = get_post_meta($class_id, 'exam_topic', true);
    $exam_subject = get_post_meta($class_id, 'exam_subject', true);
    $parent_term = $exam_topic ?: $exam_subject;
    
    $categories = get_terms([
        'taxonomy' => 'question_category',
        'parent' => $parent_term,
        'hide_empty' => false,
    ]);
    
    ob_start();
    ?>
    <h2><?php echo esc_html($user->display_name); ?> - Detailed Performance</h2>
    
    <div class="stats-grid">
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
        <div class="stat-card">
            <h4>Sessions</h4>
            <div class="value"><?php echo $sessions; ?></div>
        </div>
        <div class="stat-card">
            <h4>Questions Attempted</h4>
            <div class="value"><?php echo $questions; ?></div>
        </div>
    </div>
    
    <h3 style="margin: 30px 0 20px;">Performance by Category</h3>
    
    <?php foreach($categories as $cat):
        $cat_questions = get_questions_in_category_tree($cat->term_id);
        if (empty($cat_questions)) continue;
        
        $cat_results = array_filter($results, fn($r) => in_array($r['question_id'], $cat_questions));
        $cat_total = count($cat_results);
        $cat_correct = count(array_filter($cat_results, fn($r) => $r['is_correct'] == '1'));
        $cat_accuracy = $cat_total > 0 ? round(($cat_correct / $cat_total) * 100, 1) : 0;
        
        if ($cat_total == 0) continue;
        
        // Get subcategories
        $subcategories = get_terms([
            'taxonomy' => 'question_category',
            'parent' => $cat->term_id,
            'hide_empty' => false,
        ]);
    ?>
    <div style="margin-bottom: 40px; background: #f8f9fa; border-radius: 10px; padding: 25px;">
        <h4 style="margin: 0 0 20px; font-size: 18px;"><?php echo esc_html($cat->name); ?></h4>
        
        <!-- Main Category Stats -->
        <div style="background: white; padding: 20px; border-radius: 8px; margin-bottom: 20px;">
            <div class="progress-bar" style="height: 12px; margin-bottom: 15px;">
                <div class="progress-fill" style="width: <?php echo $cat_accuracy; ?>%"></div>
            </div>
            <div class="stats-row">
                <span><strong><?php echo $cat_accuracy; ?>%</strong> Accuracy</span>
                <span><strong><?php echo $cat_correct; ?>/<?php echo $cat_total; ?></strong> Correct</span>
            </div>
        </div>
        
        <?php if (!empty($subcategories)): ?>
        <!-- Subcategories -->
        <h5 style="margin: 20px 0 15px; color: #666; font-size: 14px;">Breakdown by Subcategory:</h5>
        <div class="categories-grid">
            <?php foreach($subcategories as $subcat):
                $subcat_questions = get_questions_in_category_tree($subcat->term_id);
                if (empty($subcat_questions)) continue;
                
                $subcat_results = array_filter($cat_results, fn($r) => in_array($r['question_id'], $subcat_questions));
                $subcat_total = count($subcat_results);
                $subcat_correct = count(array_filter($subcat_results, fn($r) => $r['is_correct'] == '1'));
                $subcat_accuracy = $subcat_total > 0 ? round(($subcat_correct / $subcat_total) * 100, 1) : 0;
                
                if ($subcat_total == 0) continue;
            ?>
            <div class="category-card" style="background: white;">
                <h4><?php echo esc_html($subcat->name); ?></h4>
                <div class="progress-bar">
                    <div class="progress-fill" style="width: <?php echo $subcat_accuracy; ?>%"></div>
                </div>
                <div class="stats-row">
                    <span><?php echo $subcat_accuracy; ?>%</span>
                    <span><?php echo $subcat_correct; ?>/<?php echo $subcat_total; ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <?php endif; ?>
    </div>
    <?php endforeach; ?>
    <?php
    echo ob_get_clean();
    wp_die();
}
add_action('wp_ajax_get_student_analytics', 'ajax_get_student_analytics');

// AJAX: Get category detailed analytics
function ajax_get_category_analytics() {
    global $wpdb;
    $class_id = intval($_POST['class_id']);
    $category_id = intval($_POST['category_id']);
    $period = sanitize_text_field($_POST['period']);
    $date_from = sanitize_text_field($_POST['date_from']);
    $date_to = sanitize_text_field($_POST['date_to']);
    
    $category = get_term($category_id);
    $table_name = $wpdb->prefix . 'exam_answers';
    $where = $wpdb->prepare("class_id = %d", $class_id);
    $date_filter = get_date_filter($period, $date_from, $date_to);
    $where .= $date_filter;
    
    $cat_questions = get_questions_in_category_tree($category_id);
    
    $class_results = $wpdb->get_results(
        "SELECT question_id, is_correct, user_id FROM $table_name WHERE $where",
        ARRAY_A
    );
    
    $national_results = $wpdb->get_results(
        "SELECT question_id, is_correct FROM $table_name ",
        ARRAY_A
    );
    
    $cat_class_results = array_filter($class_results, fn($r) => in_array($r['question_id'], $cat_questions));
    $cat_national_results = array_filter($national_results, fn($r) => in_array($r['question_id'], $cat_questions));
    
    $class_total = count($cat_class_results);
    $class_correct = count(array_filter($cat_class_results, fn($r) => $r['is_correct'] == '1'));
    $class_accuracy = $class_total > 0 ? round(($class_correct / $class_total) * 100, 1) : 0;
    
    $national_total = count($cat_national_results);
    $national_correct = count(array_filter($cat_national_results, fn($r) => $r['is_correct'] == '1'));
    $national_accuracy = $national_total > 0 ? round(($national_correct / $national_total) * 100, 1) : 0;
    
    // Get subcategories
    $subcategories = get_terms([
        'taxonomy' => 'question_category',
        'parent' => $category_id,
        'hide_empty' => false,
    ]);
    
    ob_start();
    ?>
    <h2><?php echo esc_html($category->name); ?> - Category Analysis</h2>
    
    <div class="comparison-section">
        <h3>Performance Comparison</h3>
        <div class="comparison-bars">
            <div class="comparison-item">
                <div class="comparison-label">This Class</div>
                <div class="comparison-bar">
                    <div class="comparison-fill class-bar" style="width: <?php echo $class_accuracy; ?>%">
                        <?php echo $class_accuracy; ?>% (<?php echo $class_correct; ?>/<?php echo $class_total; ?>)
                    </div>
                </div>
            </div>
            <div class="comparison-item">
                <div class="comparison-label">National</div>
                <div class="comparison-bar">
                    <div class="comparison-fill national-bar" style="width: <?php echo $national_accuracy; ?>%">
                        <?php echo $national_accuracy; ?>%
                    </div>
                </div>
            </div>
        </div>
    </div>
    
    <?php if (!empty($subcategories)): ?>
    <h3 style="margin: 30px 0 20px;">Subcategory Breakdown</h3>
    <div class="categories-grid">
        <?php foreach($subcategories as $subcat):
            $subcat_questions = get_questions_in_category_tree($subcat->term_id);
            if (empty($subcat_questions)) continue;
            
            $subcat_results = array_filter($cat_class_results, fn($r) => in_array($r['question_id'], $subcat_questions));
            $subcat_total = count($subcat_results);
            $subcat_correct = count(array_filter($subcat_results, fn($r) => $r['is_correct'] == '1'));
            $subcat_accuracy = $subcat_total > 0 ? round(($subcat_correct / $subcat_total) * 100, 1) : 0;
        ?>
        <div class="category-card">
            <h4><?php echo esc_html($subcat->name); ?></h4>
            <div class="progress-bar">
                <div class="progress-fill" style="width: <?php echo $subcat_accuracy; ?>%"></div>
            </div>
            <div class="stats-row">
                <span><?php echo $subcat_accuracy; ?>%</span>
                <span><?php echo $subcat_correct; ?>/<?php echo $subcat_total; ?></span>
            </div>
        </div>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>
    <?php
    echo ob_get_clean();
    wp_die();
}
add_action('wp_ajax_get_category_analytics', 'ajax_get_category_analytics');

// Helper function to build date filter
function get_date_filter($period, $date_from = '', $date_to = '') {
    if ($period === 'custom' && $date_from && $date_to) {
        global $wpdb;
        return $wpdb->prepare(" AND timestamp BETWEEN %s AND %s", $date_from, $date_to . ' 23:59:59');
    }
    
    $days = [
        'week' => 7,
        'month' => 30,
        'year' => 365
    ];
    
    if (isset($days[$period])) {
        return " AND timestamp >= DATE_SUB(NOW(), INTERVAL {$days[$period]} DAY)";
    }
    
    return '';
}
?>