<?php 
function add_exam_upload_submenu() {
    add_submenu_page(
        'edit.php?post_type=exam', // Parent menu (class post type)
        'Upload Exams',            // Page title
        'Upload Exams',            // Menu title
        'manage_options',          // Capability
        'upload-exams',            // Menu slug
        'render_exam_upload_page'  // Callback function to render the page
    );
}
add_action('admin_menu', 'add_exam_upload_submenu');

function render_exam_upload_page() {
    
    if (isset($_POST['upload_exam_btn'])) {
        // Nonce verification
        if (!isset($_POST['exam_upload_nonce']) || !wp_verify_nonce($_POST['exam_upload_nonce'], 'exam_upload_nonce')) {
            wp_die('Security check failed.');
        }

        // Handle file upload
        if (isset($_FILES['json_file']) && $_FILES['json_file']['error'] === UPLOAD_ERR_OK) {
            $file = $_FILES['json_file'];

            // Process the JSON file data
            $json_data = file_get_contents($file['tmp_name']);
            $exams = json_decode($json_data, true);

            if (!is_array($exams)) {
                echo 'Invalid JSON format.';
                return;
            }

            // Insert exam posts
            foreach ($exams as $exam_data) {
                $question_items = $exam_data['exam_items'];
                $exam_category = $exam_data['exam_category'];

                // Check if exam title exists
                if (empty($exam_data['exam_title'])) {
                    echo 'Exam title is required.';
                    continue;
                }

                // Check if an exam post with the same title already exists
                $existing_exam = get_page_by_title($exam_data['exam_title'], OBJECT, 'exam');
                if ($existing_exam) {
                    echo 'Exam with title ' . $exam_data['exam_title'] . ' already exists. Skipping.';
                    continue;
                }

                // Prepare exam post data
                $exam_post_data = array(
                    'post_title'    => $exam_data['exam_title'],
                    'post_status'   => 'publish',
                    'post_type'     => 'exam',
                );

                // Insert the exam post
                $exam_post_id = wp_insert_post($exam_post_data);

                if (!$exam_post_id) {
                    echo 'Failed to create exam post: ' . $exam_data['exam_title'];
                    continue;
                }

                // Query random question IDs
                $random_question_ids = get_random_question_ids($question_items, $exam_category);

                // Associate the questions with the exam
                update_post_meta($exam_post_id, 'selected_questions', $random_question_ids);

                // Optionally, add other meta or taxonomy data to the exam post

                // Notify success for this exam
                echo 'Exam ' . $exam_data['exam_title'] . ' imported successfully!<br>';
            }

            // Optionally, redirect after successful upload
            wp_redirect(admin_url('edit.php?post_type=exam&page=upload-exams'));
            exit;
        } else {
            echo 'Failed to upload JSON file.<br>';
        }
    }
    ?>
    <div class="wrap">
        <h2>Upload Exams</h2>

        <form method="post" enctype="multipart/form-data">
            <?php wp_nonce_field('exam_upload_nonce', 'exam_upload_nonce'); ?>
            <label for="json_file">Upload JSON File:</label>
            <input type="file" name="json_file" id="json_file" accept=".json">

            <input type="submit" name="upload_exam_btn" class="button-primary" value="Upload Exams">
        </form>
    </div>

    <?php
}



// Add a custom meta box to upload and manage questions
function add_question_upload_meta_box() {
    add_meta_box('question_upload_meta_box', 'Question Upload', 'display_question_upload_meta_box', 'exam', 'normal', 'high');
}

add_action('add_meta_boxes', 'add_question_upload_meta_box');



function display_question_upload_meta_box($post) {
     $exam_id = $post->ID; // Get the exam ID.
    ?>
    <p hidden>Upload a JSON file containing an array of questions in the specified format:</p>
    <pre hidden>
    [
        {
            "post_title": "Question title 1",
            "category": "Category 1",
            "option_a": "Option A 1",
            "option_b": "Option B 1",
            "option_c": "Option C 1",
            "option_d": "Option D 1",
            "correct_answer": "A"
        },
        {
            "post_title": "Question title 2",
            "category": "Category 2",
            "option_a": "Option A 2",
            "option_b": "Option B 2",
            "option_c": "Option C 2",
            "option_d": "Option D 2",
            "correct_answer": "B"
        },
        ...
    ]
    </pre>
    <form method="post" enctype="multipart/form-data" hidden>
        <input type="file" name="question_file" id="question_file" accept=".json" hidden>
        <input type="submit" name="upload_questions" value="Upload Questions" hidden>
    </form>

    <?php
     echo ' <a href="https://looksfam.technomad-ph.com/wp-admin/edit.php?post_type=question&page=json-import&exam=' . $exam_id . '">Click here to add questions</a>';
    if (isset($_POST['upload_questions'])) {
    
      if (isset($_FILES['question_file'])) {
             $uploaded_file = $_FILES['question_file'];
            $allowed_extensions = array('json');

           $uploaded_file = $_FILES['question_file'];
    if ($uploaded_file['error'] === 0 && $uploaded_file['type'] === 'application/json') {
    $json_question_data = file_get_contents($uploaded_file['tmp_name']);
    $questions = json_decode($json_question_data, true);

    if ($questions && is_array($questions)) {
        
        foreach ($questions as $question_data) {
            // Make sure required fields exist in the JSON data.
            if (isset($question_data['post_title'])) {
                $post_title = sanitize_text_field($question_data['post_title']);
                
                // Check if a question with the same title already exists
                $existing_question = get_page_by_title($post_title, OBJECT, 'question');
                
        if ($existing_question) {
            continue; // Skip adding duplicate questions
            
        }
                            // Create a question post.
                            $question_data['post_type'] = 'question';
                            $question_data['post_status'] = 'publish'; // Add this line
                            $question_id = wp_insert_post($question_data);

                            if ($question_id) {
                                // Assign category to the question (if it exists).
                                // Assign category to the question.
                                if (isset($question_data['category']) && !empty($question_data['category'])) {
                                    // Check if the category exists, and if not, set it to "Uncategorized."
                                    $category = term_exists($question_data['category'], 'question_category');
                                    if ($category) {
                                        $category_id = $category['term_id'];
                                    } else {
                                        // Set to "Uncategorized" if the category doesn't exist.
                                        $category_id = get_term_by('slug', 'uncategorized', 'question_category')->term_id;
                                    }
                                    wp_set_post_terms($question_id, $category_id, 'question_category');
                                }
                                // Add multiple-choice options and correct answer.
                                if (
                                    isset($question_data['option_a']) &&
                                    isset($question_data['option_b']) &&
                                    isset($question_data['option_c']) &&
                                    isset($question_data['option_d']) &&
                                    isset($question_data['correct_answer'])&&
                                    isset($question_data['solution'])
                                ) {
                                    $options = array(
                                        'A' => $question_data['option_a'],
                                        'B' => $question_data['option_b'],
                                        'C' => $question_data['option_c'],
                                        'D' => $question_data['option_d'],
                                    );
                                    $correct_answer = $question_data['correct_answer'];
                                    $solution = $question_data['solution'];
                                    update_post_meta($question_id, 'multiple_choice_options', $options);
                                    update_post_meta($question_id, 'correct_answer', $correct_answer);
                                     update_post_meta($question_id, 'solution', $solution);
                                     // Associate the question with the exam
                                     $selected_questions = get_post_meta($exam_id, 'selected_questions', true);
                                     $selected_questions[] = $question_id;
                                     update_post_meta($exam_id, 'selected_questions', $selected_questions);
                                    
                                    
                                }
                            }
                        }
                    }

                    echo 'Questions imported successfully!';
                     
                } else {
                    echo 'Invalid JSON data or file.';
                    
                }
            } else {
                echo 'Invalid file format. Please upload a JSON file.';
            }
        } 
    }
   

    display_exam_questions($exam_id); // Pass the $exam_id to the function.
}

// Display a list of exam questions with checkboxes in a styled table
function display_exam_questions($exam_id) {
    $selected_questions = get_post_meta($exam_id, 'selected_questions', true);
    if (!is_array($selected_questions)) {
        $selected_questions = array();
    }

    echo '<h3>Exam Questions:</h3>';
    echo '<form method="post">';
    echo '<table style="width: 100%; border-collapse: collapse; border: 1px solid #ddd;">';
    echo '<thead>';
    echo '<tr style="background-color: #f2f2f2;">';
    echo '<th style="border: 1px solid #ddd; padding: 8px;">Question</th>';
    echo '<th style="border: 1px solid #ddd; padding: 8px;">Sub Topic</th>'; // Added category column
    echo '<th style="border: 1px solid #ddd; padding: 8px;">Parent Category</th>'; // Added parent category column
    echo '<th style="border: 1px solid #ddd; padding: 8px;">Under Difficulty</th>'; // Added category under "Difficulty" column
    echo '<th style="border: 1px solid #ddd; padding: 8px;">Correct</th>';
    echo '<th style="border: 1px solid #ddd; padding: 8px;">Total</th>';
    echo '<th style="border: 1px solid #ddd; padding: 8px;">Percentage</th>';
    echo '<th style="border: 1px solid #ddd; padding: 8px;">Remove</th>';
    echo '</tr>';
    echo '</thead>';
    echo '<tbody>';
    
    // You can add the code to calculate correct, total, and percentage here
    
    foreach ($selected_questions as $question_id) {
        $question = get_post($question_id);
        $edit_post_url = get_edit_post_link($question_id); // Get the edit post URL
        $categories = wp_get_post_terms($question_id, 'question_category'); // Retrieve categories
        
        
        

        // Check if "difficulty" is in the parent chain
        $skipCategory = false;
        if (!empty($categories)) {
            foreach ($categories as $category) {
                $parent_category = get_term($category->parent, 'question_category');
                if ($parent_category && $parent_category->name === "Difficulty") {
                    $skipCategory = true;
                    $underDifficulty = $category->name;
                    break;
                }
            }
        }

        if ($skipCategory) {
            continue; // Skip this question
        }

        echo '<tr>';
        echo '<td style="border: 1px solid #ddd; padding: 8px;"><a href="' . esc_url($edit_post_url) . '">' . esc_html($question->post_title) . '</a></td>';
        echo '<td style="border: 1px solid #ddd; padding: 8px;">';
        if (!empty($categories)) {
            foreach ($categories as $category) {
                echo esc_html($category->name) . '<br>'; // Display the categories
            }
        } else {
            echo 'Uncategorized';
        }
        echo '</td>';
        
        // Display the parent category
        echo '<td style="border: 1px solid #ddd; padding: 8px;">';
        if (!empty($categories)) {
            $parent_category_id = $categories[0]->parent;
            $parent_category = get_term($parent_category_id, 'question_category');
            echo esc_html($parent_category->name);
        } else {
            echo 'N/A';
        }
        echo '</td>';
        
        // Display the category under "Difficulty"
        echo '<td style="border: 1px solid #ddd; padding: 8px;">';
        if (isset($underDifficulty)) {
            echo esc_html($underDifficulty);
        } else {
            echo 'N/A';
        }
        echo '</td>';
        
        // Calculate correct, total, and percentage
        $question_results = get_post_meta($question_id, 'question_results', true);
        $correct = 0;
        $total = 0;

        foreach ($question_results as $result) {
            if ($result['is_correct'] === "1" && $result['exam_id'] === $exam_id) {
                $correct++;
            }
            
            if (($result['is_correct'] === "1" || $result['is_correct'] === "0") && $result['exam_id'] === $exam_id) {
                $total++;
            }
        }

        $percentage = ($total > 0) ? ($correct / $total * 100) : 0;
        
        echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($correct) . '</td>';
        echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html($total) . '</td>';
        echo '<td style="border: 1px solid #ddd; padding: 8px;">' . esc_html(number_format($percentage, 2)) . '%</td>';
        
        echo '<td style="border: 1px solid #ddd; padding: 8px;"><input type="checkbox" name="remove_question[]" value="' . $question_id . '"></td>';
        echo '</tr>';
    }
    echo '</tbody>';
    echo '</table>';
    echo '<input type="submit" name="remove_selected" value="Remove Selected Questions">';
    echo '</form>';

    if (isset($_POST['remove_selected']) && isset($_POST['remove_question'])) {
        $questions_to_remove = $_POST['remove_question'];
        foreach ($questions_to_remove as $question_id) {
            $key = array_search($question_id, $selected_questions);
            if ($key !== false) {
                unset($selected_questions[$key]);
            }
        }
        update_post_meta($exam_id, 'selected_questions', $selected_questions);
    }
}
?>