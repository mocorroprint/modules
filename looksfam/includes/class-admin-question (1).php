<?php 

// Function to get random question IDs
function get_random_question_ids($question_count, $exam_category) {
    $args = array(
        'post_type'      => 'question',
        'posts_per_page' => $question_count,
        'orderby'        => 'rand',
        'tax_query'      => array(
            array(
                'taxonomy' => 'question_category',
                'field'    => 'name',
                'terms'    => $exam_category,
            ),
        ),
        'fields'         => 'ids', // Retrieve only post IDs
    );

    $random_question_ids = get_posts($args);

    return $random_question_ids;
}





function json_import_menu() {
    add_submenu_page(
        'edit.php?post_type=question', // Parent menu slug
        'JSON Import',
        'JSON Import',
        'manage_options',
        'json-import',
        'json_import_page'
    );
     add_submenu_page(
        'edit.php?post_type=question', // Parent menu slug
        'JSON Update',
        'JSON Update',
        'manage_options',
        'json-update',
        'json_update_questions'
    );
      add_submenu_page(
        'edit.php?post_type=question',
        'Import Question Categories',
        'Import Categories',
        'manage_options',
        'category-import',
        'question_category_import_page'
    );
    
    add_submenu_page(
        'edit.php?post_type=question',  // Parent slug (points to the question post type)
        'All Questions',                // Page title
        'All Questions',                // Menu title
        'manage_options',               // Capability
        'list-all-questions',           // Menu slug
        'list_all_questions_callback'   // Callback function
    );
    
      add_submenu_page(
        'edit.php?post_type=question',
        'Update All Questions',
        'Update All',
        'edit_posts',
        'update-all-questions',
        'update_all_questions_page'
    );
}
add_action('admin_menu', 'json_import_menu');
function update_all_questions_page() {
    // Enqueue necessary scripts
    wp_enqueue_script('jquery');
    ?>
    <div class="wrap">
        <h1>Update All Questions</h1>
        <div id="progress-container" style="display: none;">
            <div class="progress-bar" style="width: 100%; height: 20px; background: #f0f0f0; margin: 20px 0;">
                <div id="progress" style="width: 0%; height: 100%; background: #0073aa; transition: width 0.3s;"></div>
            </div>
            <p id="progress-status">Processing questions...</p>
            <p id="progress-count">Processed: <span id="processed-count">0</span></p>
        </div>
        <div id="start-container">
            <p>Click the button below to update all question posts to contain "[content_question]"</p>
            <button type="button" id="start-update" class="button button-primary">Update All Questions</button>
        </div>
        <div id="completion-message" style="display: none;"></div>
    </div>

    <script type="text/javascript">
    jQuery(document).ready(function($) {
        var offset = 0;
        var totalProcessed = 0;
        var isProcessing = false;

        function updateQuestionBatch() {
            if (isProcessing) {
                return;
            }

            isProcessing = true;
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'process_question_batch',
                    offset: offset,
                    nonce: '<?php echo wp_create_nonce("update_questions_nonce"); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        totalProcessed += response.data.processed;
                        $('#processed-count').text(totalProcessed);
                        
                        if (response.data.completed) {
                            $('#progress').css('width', '100%');
                            $('#progress-status').text('Update completed!');
                            $('#completion-message').html('<div class="updated"><p>' + totalProcessed + ' question(s) updated successfully!</p></div>').show();
                            $('#progress-container').hide();
                        } else {
                            offset += 50; // Move to next batch
                            isProcessing = false;
                            updateQuestionBatch(); // Process next batch
                        }
                    } else {
                        $('#completion-message').html('<div class="error"><p>Error: ' + response.data.message + '</p></div>').show();
                        $('#progress-container').hide();
                    }
                },
                error: function() {
                    $('#completion-message').html('<div class="error"><p>An error occurred during the update process.</p></div>').show();
                    $('#progress-container').hide();
                }
            });
        }

        $('#start-update').click(function() {
            $(this).prop('disabled', true);
            $('#progress-container').show();
            $('#start-container').hide();
            $('#completion-message').hide();
            offset = 0;
            totalProcessed = 0;
            updateQuestionBatch();
        });
    });
    </script>
    <?php
}

function process_question_batch() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'update_questions_nonce')) {
        wp_send_json_error(array('message' => 'Invalid security token.'));
    }

    $batch_size = 50;
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    
    $args = array(
        'post_type' => 'question',
        'posts_per_page' => $batch_size,
        'offset' => $offset,
        'post_status' => 'any',
        'meta_query' => array(
            'relation' => 'OR',
            array(
                'key' => 'post_content',
                'compare' => 'NOT EXISTS',
            ),
            array(
                'key' => 'post_content',
                'value' => '',
                'compare' => '=',
            ),
        ),
        'fields' => 'ids', // Only get post IDs for better performance
    );

    $question_ids = get_posts($args);
    $processed = 0;

    if (!empty($question_ids)) {
        foreach ($question_ids as $question_id) {
            wp_update_post(array(
                'ID' => $question_id,
                'post_content' => '[content_question]'
            ));
            $processed++;
        }
    }

    // Check if this was the last batch
    $completed = count($question_ids) < $batch_size;

    wp_send_json_success(array(
        'processed' => $processed,
        'completed' => $completed
    ));
}

// Add AJAX action hooks
add_action('wp_ajax_process_question_batch', 'process_question_batch');
function list_all_questions_callback() {
    // Get current page number
    $current_page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $questions_per_page = 50;
    $offset = ($current_page - 1) * $questions_per_page;

    // Get total number of questions for pagination
    $total_questions = wp_count_posts('question')->publish;
    $total_pages = ceil($total_questions / $questions_per_page);

    // Fetch questions with pagination
    $questions = get_posts(array(
        'post_type'      => 'question',
        'numberposts'    => $questions_per_page,
        'offset'         => $offset,
        'orderby'        => 'ID',
        'order'          => 'ASC'
    ));

    echo '<div class="wrap">';
    echo '<h1>All Questions</h1>';

    // Add pagination controls at the top
    display_pagination_controls($current_page, $total_pages, $total_questions);

    if (!empty($questions)) {
        echo '<table class="widefat fixed striped">';
        echo '<thead>';
        echo '<tr>';
        echo '<th width="5%">ID</th>';
        echo '<th width="20%">Title</th>';
        echo '<th width="30%">Options (A, B, C, D)</th>';
        echo '<th width="10%">Correct Answer</th>';
        echo '<th width="20%">Solution</th>';
        echo '<th width="15%">Content</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($questions as $question) {
            $options = get_post_meta($question->ID, 'multiple_choice_options', true);
            $correct_answer = get_post_meta($question->ID, 'correct_answer', true);
            $solution = get_post_meta($question->ID, 'solution', true);
            
            echo '<tr>';
            echo '<td>' . esc_html($question->ID) . '</td>';
            echo '<td>' . esc_html($question->post_title) . '</td>';
            echo '<td>';
            if (!empty($options)) {
                echo '<div style="max-height: 150px; overflow-y: auto;">';
                echo 'A: ' . esc_html($options['A'] ?? '') . '<br>';
                echo 'B: ' . esc_html($options['B'] ?? '') . '<br>';
                echo 'C: ' . esc_html($options['C'] ?? '') . '<br>';
                echo 'D: ' . esc_html($options['D'] ?? '');
                echo '</div>';
            }
            echo '</td>';
            echo '<td>' . esc_html($correct_answer) . '</td>';
            echo '<td>' . '<div style="max-height: 150px; overflow-y: auto;">' . esc_html($solution) . '</div>' . '</td>';
            echo '<td>' . '<div style="max-height: 150px; overflow-y: auto;">' . get_the_content(null, false, $question) . '</div>' . '</td>';
            echo '</tr>';
        }
        
        echo '</tbody>';
        echo '</table>';

        // Add pagination controls at the bottom
        display_pagination_controls($current_page, $total_pages, $total_questions);
    } else {
        echo '<p>No questions found.</p>';
    }
    
    echo '</div>';

    // Add some CSS for better display
    echo '<style>
        .tablenav-pages a, .tablenav-pages span {
            padding: 0 10px;
            margin: 0 2px;
            text-decoration: none;
        }
        .tablenav-pages .current {
            background: #0073aa;
            color: white;
            border-radius: 2px;
        }
        .tablenav {
            margin: 15px 0;
        }
    </style>';
}

// Helper function to display pagination controls
function display_pagination_controls($current_page, $total_pages, $total_questions) {
    $page_links = array();
    
    echo '<div class="tablenav">';
    echo '<div class="tablenav-pages">';
    echo '<span class="displaying-num">' . $total_questions . ' items</span>';
    
    if ($total_pages > 1) {
        // Previous page
        if ($current_page > 1) {
            $prev_page = $current_page - 1;
            echo '<a class="prev-page" href="' . add_query_arg('paged', $prev_page) . '">&laquo;</a>';
        }

        // Page numbers
        for ($i = 1; $i <= $total_pages; $i++) {
            if ($i == $current_page) {
                echo '<span class="tablenav-pages-navspan current">' . $i . '</span>';
            } else {
                if ($i == 1 || $i == $total_pages || abs($i - $current_page) <= 2) {
                    echo '<a class="page-numbers" href="' . add_query_arg('paged', $i) . '">' . $i . '</a>';
                } elseif (abs($i - $current_page) == 3) {
                    echo '<span class="page-numbers dots">...</span>';
                }
            }
        }

        // Next page
        if ($current_page < $total_pages) {
            $next_page = $current_page + 1;
            echo '<a class="next-page" href="' . add_query_arg('paged', $next_page) . '">&raquo;</a>';
        }
    }
    
    echo '</div>';
    echo '</div>';
}
function question_category_import_page() {
    if (isset($_POST['submit'])) {
        if (isset($_FILES['json_file'])) {
            $file = $_FILES['json_file'];
            $allowed_extensions = array('json');

            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);

            if (in_array(strtolower($file_extension), $allowed_extensions)) {
                $json_data = file_get_contents($file['tmp_name']);
                $categories = json_decode($json_data, true);

                if (is_array($categories)) {
                    foreach ($categories as $category_data) {
                        if (isset($category_data['category_name'])) {
                            $category_name = $category_data['category_name'];
                            $parent_category_name = isset($category_data['parent']) ? $category_data['parent'] : '';

                            $parent_category_id = 0;

                            if (!empty($parent_category_name)) {
                                $parent_category = get_term_by('name', $parent_category_name, 'question_category');
                                if ($parent_category) {
                                    $parent_category_id = $parent_category->term_id;
                                }
                            }

                            $existing_category = term_exists($category_name, 'question_category', $parent_category_id);

                            if (!$existing_category) {
                                $result = wp_insert_term($category_name, 'question_category', array('parent' => $parent_category_id));
                                if (!is_wp_error($result)) {
                                    echo "Category '$category_name' added successfully.<br>";
                                } else {
                                    echo "Failed to add category '$category_name'. Error: " . $result->get_error_message() . "<br>";
                                }
                            } else {
                                echo "Category '$category_name' already exists, skipping.<br>";
                            }
                        }
                    }
                } else {
                    echo 'Invalid JSON data or file.<br>';
                }
            } else {
                echo 'Invalid file format. Please upload a JSON file.<br>';
            }
        }
    }

    ?>
    <div class="wrap">
        <h2>Import Question Categories from JSON</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="json_file" accept=".json" />
            <input type="submit" name="submit" value="Import Categories" />
        </form>
    </div>
    <?php
}
// Add this at the beginning of your file
add_action('wp_ajax_process_question_batch_2', 'process_question_batch_2');
add_action('wp_ajax_upload_json_file', 'handle_json_upload');
function handle_json_upload() {
    check_ajax_referer('my_ajax_nonce', 'nonce');

    if (!isset($_FILES['json_file'])) {
        error_log('JSON Import: No file uploaded');
        wp_send_json_error('No file uploaded');
        return;
    }

    $file = $_FILES['json_file'];
    $allowed_extensions = array('json');
    $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
    
    if (!in_array(strtolower($file_extension), $allowed_extensions)) {
        error_log('JSON Import: Invalid file format - ' . $file_extension);
        wp_send_json_error('Invalid file format');
        return;
    }

    if ($file['error'] !== UPLOAD_ERR_OK) {
        error_log('JSON Import: Upload error - ' . $file['error']);
        wp_send_json_error('Upload error: ' . $file['error']);
        return;
    }

    $json_data = file_get_contents($file['tmp_name']);
    $questions = json_decode($json_data, true);
    
    if (json_last_error() !== JSON_ERROR_NONE) {
        error_log('JSON Import: JSON decode error - ' . json_last_error_msg());
        wp_send_json_error('Invalid JSON data: ' . json_last_error_msg());
        return;
    }

    if (!is_array($questions)) {
        error_log('JSON Import: Questions data is not an array');
        wp_send_json_error('Invalid JSON data structure');
        return;
    }

    wp_send_json_success([
        'total' => count($questions),
        'questions' => $questions
    ]);
}

function process_question_batch_2() {
    try {
        if (!isset($_POST['questions']) || !isset($_POST['start'])) {
            error_log('Question Processing: Missing required POST data');
            wp_send_json_error('Missing required data');
            return;
        }

        $raw_questions = stripslashes($_POST['questions']);
        $questions = json_decode($raw_questions, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            error_log('Question Processing: JSON decode error - ' . json_last_error_msg());
            error_log('Raw questions data: ' . substr($raw_questions, 0, 255)); // Log first 255 chars
            wp_send_json_error('Invalid questions data: ' . json_last_error_msg());
            return;
        }

        $start = intval($_POST['start']);
        $batch_size = 20;
        $batch = array_slice($questions, $start, $batch_size);
        $question_ids = array();
        $processed = 0;
        $skipped = 0;
        $failed = 0;

        foreach ($batch as $question_data) {
            if (!isset($question_data['post_title'])) {
                error_log('Question Processing: Missing post title in question data');
                $failed++;
                continue;
            }

            $existing_question = get_page_by_title($question_data['post_title'], OBJECT, 'question');

            if ($existing_question) {
                $question_ids[] = $existing_question->ID;
                $skipped++;
                continue;
            }

            $question_data['post_type'] = 'question';
            $question_data['post_status'] = 'publish';
            $question_data['post_content'] = '[content_question]';
            
            $question_id = wp_insert_post($question_data);

            if (!$question_id || is_wp_error($question_id)) {
                error_log('Question Processing: Failed to insert post - ' . 
                    (is_wp_error($question_id) ? $question_id->get_error_message() : 'Unknown error'));
                $failed++;
                continue;
            }

            // Process category
            if (isset($question_data['category']) && !empty($question_data['category'])) {
                $category = term_exists($question_data['category'], 'question_category');
                if ($category) {
                    $category_id = $category['term_id'];
                } else {
                    $uncategorized = get_term_by('slug', 'uncategorized', 'question_category');
                    $category_id = $uncategorized ? $uncategorized->term_id : null;
                }
                if ($category_id) {
                    wp_set_post_terms($question_id, $category_id, 'question_category');
                }
            }

            // Check required fields
            $required_fields = array('option_a', 'option_b', 'option_c', 'option_d', 'correct_answer');
            $missing_fields = array_filter($required_fields, function($field) use ($question_data) {
                return !isset($question_data[$field]) || empty($question_data[$field]);
            });

            if (!empty($missing_fields)) {
                error_log('Question Processing: Missing required fields - ' . implode(', ', $missing_fields));
                wp_delete_post($question_id, true);
                $failed++;
                continue;
            }

            // Save question data
            $options = array(
                'A' => $question_data['option_a'],
                'B' => $question_data['option_b'],
                'C' => $question_data['option_c'],
                'D' => $question_data['option_d'],
            );
            update_post_meta($question_id, 'multiple_choice_options', $options);
            update_post_meta($question_id, 'correct_answer', $question_data['correct_answer']);
            
            // Save solution if exists
            if (isset($question_data['solution'])) {
                update_post_meta($question_id, 'solution', $question_data['solution']);
            }
            
            $question_ids[] = $question_id;
            $processed++;
        }

        // Process exam relationship
        if (isset($_POST['exam_id']) && !empty($question_ids)) {
            $exam = $_POST['exam_id'];
            $selected_question_ids = get_post_meta($exam, 'selected_questions', true);
            if (!is_array($selected_question_ids)) {
                $selected_question_ids = array();
            }
            $selected_question_ids = array_merge($selected_question_ids, $question_ids);
            update_post_meta($exam, 'selected_questions', $selected_question_ids);
        }

        wp_send_json_success([
            'processed' => $processed,
            'skipped' => $skipped,
            'failed' => $failed,
            'next_start' => $start + $batch_size,
            'question_ids' => $question_ids
        ]);

    } catch (Exception $e) {
        error_log('Question Processing: Exception - ' . $e->getMessage());
        wp_send_json_error('Processing error: ' . $e->getMessage());
    }
    wp_die();
}

function json_import_page() {
     // Enqueue necessary scripts
    wp_enqueue_script('jquery');
    
    // Add the admin ajax URL to your page
    ?>
    <script type="text/javascript">
        var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
    </script>
    <div class="wrap">
        <h2>Import Questions from JSON</h2>
        <h5>Comment out Question Display shortcode. Display Flashcard cause errors</h5>
        <form method="post" enctype="multipart/form-data" id="uploadForm">
            <?php wp_nonce_field('my_ajax_nonce', 'nonce'); // Add nonce field if using nonce ?>
            <input type="file" name="json_file" accept=".json" />
            <input type="submit" name="submit" value="Import Questions" />
        </form>
        <div id="progressContainer" style="display:none;">
            <div style="margin: 20px 0;">
                <div id="progressBar" style="width:0%; height:20px; background-color:#0073aa; transition: width 0.3s;"></div>
            </div>
            <div id="progressStatus">
                Processed: <span id="processedCount">0</span> questions
            </div>
            <div id="processingStatus"></div>
        </div>
    </div>

     <script>
    jQuery(document).ready(function($) {
        $('#uploadForm').on('submit', function(e) {
            e.preventDefault();
            var formData = new FormData(this);
            formData.append('action', 'upload_json_file');

            $('#progressContainer').show();
            $('#processingStatus').html('Uploading file...');
            $('#errorDetails').html('');

            // First AJAX call to upload file
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    console.log('Upload response:', response); // Debug log
                    if (response.success) {
                        $('#processingStatus').html('Processing questions...');
                        processBatch(response.data.questions, 0, response.data.total);
                    } else {
                        $('#processingStatus').html('Error: ' + response.data);
                        $('#errorDetails').html('Upload failed: ' + JSON.stringify(response));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Upload error:', {xhr: xhr, status: status, error: error}); // Debug log
                    $('#processingStatus').html('Upload failed. Please try again.');
                    $('#errorDetails').html('Error details: ' + error + '<br>Status: ' + status + '<br>Response: ' + xhr.responseText);
                }
            });
        });

        function processBatch(questions, start, total) {
            // Debug log
            console.log('Processing batch:', {
                start: start,
                total: total,
                batchSize: Math.min(20, total - start)
            });

            var data = {
                action: 'process_question_batch_2',
                questions: JSON.stringify(questions),
                start: start,
                exam_id: <?php echo isset($_GET['exam']) ? $_GET['exam'] : 'null'; ?>
            };

            // Debug log
            console.log('Sending batch data:', data);

            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: data,
                success: function(response) {
                    console.log('Batch processing response:', response); // Debug log
                    
                    if (response.success) {
                        var progress = Math.min(100, ((start + response.data.processed) / total) * 100);
                        $('#progressBar').css('width', progress + '%');
                        $('#processedCount').text(start + response.data.processed);
                        
                        $('#processingStatus').html(
                            'Successfully added: ' + response.data.processed + '<br>' +
                            'Skipped (duplicates): ' + response.data.skipped + '<br>' +
                            'Failed to upload: ' + response.data.failed
                        );
                        
                        if (response.data.next_start < total) {
                            processBatch(questions, response.data.next_start, total);
                        } else {
                            $('#processingStatus').append('<br><br>Complete! <a href="<?php echo admin_url(); ?>/post.php?post=<?php echo isset($_GET['exam']) ? $_GET['exam'] : ''; ?>&action=edit">CLICK HERE TO GO BACK</a>');
                        }
                    } else {
                        console.error('Batch processing error:', response); // Debug log
                        $('#processingStatus').html('Error processing batch. Please try again.');
                        $('#errorDetails').html('Processing error details: ' + JSON.stringify(response));
                    }
                },
                error: function(xhr, status, error) {
                    console.error('Batch processing error:', {xhr: xhr, status: status, error: error}); // Debug log
                    $('#processingStatus').html('Batch processing failed. Please try again.');
                    $('#errorDetails').html(
                        'Error processing batch:<br>' +
                        'Status: ' + status + '<br>' +
                        'Error: ' + error + '<br>' +
                        'Response: ' + xhr.responseText
                    );
                }
            });
        }
    });
    </script>
    <?php
}
function json_update_questions() {
    // Check if the form was submitted.
    if (isset($_POST['submit'])) {
        if (isset($_FILES['json_file'])) {
            $file = $_FILES['json_file'];
            $allowed_extensions = array('json');

            $file_extension = pathinfo($file['name'], PATHINFO_EXTENSION);
            if (in_array(strtolower($file_extension), $allowed_extensions)) {
                $json_data = file_get_contents($file['tmp_name']);
                $questions = json_decode($json_data, true);

                if (is_array($questions)) {
                    $updated_count = 0;
                    $skipped_count = 0;

                    foreach ($questions as $question_data) {
                        $existing_question = null;

                        // Check if question exists by ID
                        if (isset($question_data['ID'])) {
                            $existing_question = get_post($question_data['ID']);
                        }

                        // If not found by ID, check by post title
                        if (!$existing_question && isset($question_data['post_title'])) {
                            $existing_question = get_page_by_title($question_data['post_title'], OBJECT, 'question');
                        }

                        if ($existing_question) {
                            // Update existing question
                            $question_data['ID'] = $existing_question->ID;
                            $question_data['post_type'] = 'question';
                            $question_data['post_status'] = 'publish';
                            $question_data['post_content'] = '[content_question]';
                            $question_id = wp_update_post($question_data);

                            if ($question_id) {
                                // Update category if provided
                                if (isset($question_data['category']) && !empty($question_data['category'])) {
                                    $category = term_exists($question_data['category'], 'question_category');
                                    if ($category) {
                                        $category_id = $category['term_id'];
                                    } else {
                                        $category_id = get_term_by('slug', 'uncategorized', 'question_category')->term_id;
                                    }
                                    wp_set_post_terms($question_id, $category_id, 'question_category');
                                }

                                // Update options and correct answer if provided
                                if (
                                    isset($question_data['option_a']) &&
                                    isset($question_data['option_b']) &&
                                    isset($question_data['option_c']) &&
                                    isset($question_data['option_d']) &&
                                    isset($question_data['correct_answer'])
                                ) {
                                    $options = array(
                                        'A' => $question_data['option_a'],
                                        'B' => $question_data['option_b'],
                                        'C' => $question_data['option_c'],
                                        'D' => $question_data['option_d'],
                                    );
                                    $correct_answer = $question_data['correct_answer'];
                                    $solution = $question_data['solution'];
                                    update_post_meta($question_id, 'solution', $solution);
                                    update_post_meta($question_id, 'multiple_choice_options', $options);
                                    update_post_meta($question_id, 'correct_answer', $correct_answer);
                                }

                                $updated_count++;
                            }
                        } else {
                            $skipped_count++;
                        }
                    }

                    echo "Update completed. $updated_count questions updated. $skipped_count questions skipped (not found).";
                } else {
                    echo 'Invalid JSON data or file.';
                }
            } else {
                echo 'Invalid file format. Please upload a JSON file.';
            }
        }
    }

    // Display the update form.
    ?>
    <div class="wrap">
        <h2>Update Questions from JSON</h2>
        <form method="post" enctype="multipart/form-data">
            <input type="file" name="json_file" accept=".json" />
            <input type="submit" name="submit" value="Update Questions" />
        </form>
    </div>
    <?php
}

// Register custom taxonomy for question categories
function register_question_categories_taxonomy() {
    $labels = array(
        'name' => 'Question Categories',
        'singular_name' => 'Question Category',
        'search_items' => 'Search Question Categories',
        'all_items' => 'All Question Categories',
        'edit_item' => 'Edit Question Category',
        'update_item' => 'Update Question Category',
        'add_new_item' => 'Add New Question Category',
        'new_item_name' => 'New Question Category Name',
        'menu_name' => 'Question Categories',
    );

    $args = array(
        'hierarchical' => true,
        'labels' => $labels,
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => array('slug' => 'question-category'),
    );

    register_taxonomy('question_category', array('question'), $args);
}

add_action('init', 'register_question_categories_taxonomy');

// Add a meta box for multiple choice options and correct answer
function add_multiple_choice_meta_box() {
    add_meta_box(
        'multiple_choice_meta_box',
        'Multiple Choice Options and Correct Answer',
        'display_multiple_choice_meta_box',
        'question', // Custom post type
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_multiple_choice_meta_box');


// Display the multiple choice options and correct answer in the meta box
function display_multiple_choice_meta_box($post) {
    // Retrieve the current values of the options
    $multiple_choice_options = get_post_meta($post->ID, 'multiple_choice_options', true);
    $correct_answer = get_post_meta($post->ID, 'correct_answer', true);
    $solution = get_post_meta($post->ID, 'solution', true);
    
    // Get featured image if available
    $thumbnail_id = get_post_thumbnail_id($post->ID);
    $image_url = $thumbnail_id ? wp_get_attachment_image_url($thumbnail_id, 'medium') : '';
    
    ?>
    <style>
        .question-meta-box {
            padding: 15px;
        }
        .meta-box-section {
            margin-bottom: 20px;
        }
        .meta-box-label {
            font-weight: 600;
            margin-bottom: 5px;
            display: block;
        }
        .meta-box-input {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .meta-box-textarea {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
            resize: vertical;
        }
        .meta-box-select {
            width: 100%;
            padding: 8px;
            border: 1px solid #ddd;
            border-radius: 4px;
            font-size: 14px;
        }
        .question-image-preview {
            margin-top: 10px;
            padding: 15px;
            background: #f9f9f9;
            border: 1px solid #ddd;
            border-radius: 4px;
            text-align: center;
        }
        .question-image-preview img {
            max-width: 100%;
            height: auto;
            max-height: 300px;
            border-radius: 4px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .question-image-preview p {
            margin: 10px 0 0 0;
            font-size: 13px;
            color: #666;
        }
        .no-image-notice {
            padding: 15px;
            background: #fff9e6;
            border-left: 4px solid #ffb900;
            margin-top: 10px;
            font-size: 13px;
            color: #666;
        }
        .option-row {
            margin-bottom: 12px;
        }
    </style>
    
    <div class="question-meta-box">
        <!-- Featured Image Display -->
        <?php if (!empty($image_url)): ?>
            <div class="meta-box-section">
                <label class="meta-box-label">📷 Question Image:</label>
                <div class="question-image-preview">
                    <img src="<?php echo esc_url($image_url); ?>" alt="Question Image">
                    <p><em>This image will be displayed with the question in exams and flashcards</em></p>
                </div>
            </div>
        <?php else: ?>
            <div class="no-image-notice">
                <strong>ℹ️ No image attached</strong><br>
                To add an image to this question, set a Featured Image using the panel on the right →
            </div>
        <?php endif; ?>
        
        <!-- Multiple Choice Options -->
        <div class="meta-box-section">
            <label class="meta-box-label">Multiple Choice Options:</label>
            
            <div class="option-row">
                <label><strong>Option A:</strong></label>
                <input type="text" 
                       name="multiple_choice_options[A]" 
                       value="<?php echo esc_attr($multiple_choice_options['A']); ?>" 
                       class="meta-box-input"
                       placeholder="Enter option A">
            </div>
            
            <div class="option-row">
                <label><strong>Option B:</strong></label>
                <input type="text" 
                       name="multiple_choice_options[B]" 
                       value="<?php echo esc_attr($multiple_choice_options['B']); ?>" 
                       class="meta-box-input"
                       placeholder="Enter option B">
            </div>
            
            <div class="option-row">
                <label><strong>Option C:</strong></label>
                <input type="text" 
                       name="multiple_choice_options[C]" 
                       value="<?php echo esc_attr($multiple_choice_options['C']); ?>" 
                       class="meta-box-input"
                       placeholder="Enter option C">
            </div>
            
            <div class="option-row">
                <label><strong>Option D:</strong></label>
                <input type="text" 
                       name="multiple_choice_options[D]" 
                       value="<?php echo esc_attr($multiple_choice_options['D']); ?>" 
                       class="meta-box-input"
                       placeholder="Enter option D">
            </div>
        </div>
        
        <!-- Correct Answer -->
        <div class="meta-box-section">
            <label class="meta-box-label">✓ Correct Answer:</label>
            <select name="correct_answer" class="meta-box-select">
                <option value="">-- Select Correct Answer --</option>
                <option value="A" <?php selected($correct_answer, 'A'); ?>>Option A</option>
                <option value="B" <?php selected($correct_answer, 'B'); ?>>Option B</option>
                <option value="C" <?php selected($correct_answer, 'C'); ?>>Option C</option>
                <option value="D" <?php selected($correct_answer, 'D'); ?>>Option D</option>
            </select>
        </div>
        
        <!-- Solution/Explanation -->
        <div class="meta-box-section">
            <label class="meta-box-label">💡 Solution/Explanation (Optional):</label>
            <textarea name="solution" 
                      rows="6" 
                      class="meta-box-textarea"
                      placeholder="Enter detailed explanation for the correct answer (optional)"><?php echo esc_textarea($solution); ?></textarea>
            <p style="margin: 5px 0 0 0; font-size: 12px; color: #666;">
                <em>This explanation will be shown in the flashcard solution view</em>
            </p>
        </div>
    </div>
    <?php
}

// Save the multiple choice options and correct answer when the question is saved
function save_multiple_choice_options($post_id) {
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;

    if (isset($_POST['multiple_choice_options'])) {
        $multiple_choice_options = $_POST['multiple_choice_options'];
        update_post_meta($post_id, 'multiple_choice_options', $multiple_choice_options);
    }

    if (isset($_POST['correct_answer'])) {
        $correct_answer = sanitize_text_field($_POST['correct_answer']);
        update_post_meta($post_id, 'correct_answer', $correct_answer);
    }
    
    if (isset($_POST['solution'])) {
        $solution = sanitize_textarea_field($_POST['solution']);
        update_post_meta($post_id, 'solution', $solution);
    }
}
add_action('save_post', 'save_multiple_choice_options');



// Add a custom meta box for displaying exam results on the exam edit page
function add_exam_results_metabox() {
    add_meta_box('exam_results_metabox', 'Exam Results', 'display_exam_results_metabox', 'exam', 'normal', 'high');
}
add_action('add_meta_boxes', 'add_exam_results_metabox');
// Function to delete an exam result session
function delete_exam_result_session($post_id, $session_id) {
    $exam_results = get_post_meta($post_id, 'exam_results', true);

    if (is_array($exam_results)) {
        $updated_exam_results = array();

        foreach ($exam_results as $result) {
            if ($result['session_id'] !== $session_id) {
                $updated_exam_results[] = $result;
            }
        }

        update_post_meta($post_id, 'exam_results', $updated_exam_results);
    }
}

// Display exam results in the custom meta box
function display_exam_results_metabox($post) {
    if (isset($_POST['remove_selected'])) {
        if (isset($_POST['remove_session']) && is_array($_POST['remove_session'])) {
            $sessions_to_remove = $_POST['remove_session'];
            foreach ($sessions_to_remove as $session_id) {
                delete_exam_result_session($post->ID, $session_id);
            }
        }
    }

    $exam_results = get_post_meta($post->ID, 'exam_results', true);
    
    $selected_questions = get_post_meta($post->ID, 'selected_questions', true);

    echo '<h3>Exam Results:</h3>';

    if (is_array($exam_results) && !empty($exam_results)) {
        $exam_sessions = array(); // Create an array to store unique exam sessions

        // Create an array to store results for each session
        $session_results = array();

        foreach ($exam_results as $result) {
            $session_id = $result['session_id'];
            $user_id = $result['user_id'];
            $is_correct = $result['is_correct'];

            // Initialize the session if it doesn't exist
            if (!isset($session_results[$session_id])) {
                $session_results[$session_id] = array(
                    'session_id' => $session_id,
                    'user_id' => $user_id,
                    'correct_count' => 0,
                    'total_count' => 0,
                );
            }

            // Update the correct count and total count
            if ($is_correct == "1") {
                $session_results[$session_id]['correct_count']++;
            }
            $session_results[$session_id]['total_count']++;
        }

        // Display results in a table
        echo '<form method="post">';
        echo '<table style="width:100%; border-collapse:collapse; border: 1px solid #ddd;">';
        echo '<thead>';
        echo '<tr>';
        echo '<th style="border: 1px solid #ddd; padding: 8px;">Session ID</th>';
        echo '<th style="border: 1px solid #ddd; padding: 8px;">User ID</th>';
        echo '<th style="border: 1px solid #ddd; padding: 8px;">Correct</th>';
        echo '<th style="border: 1px solid #ddd; padding: 8px;">Total</th>';
        echo '<th style="border: 1px solid #ddd; padding: 8px;">Percentage Score</th>';
        echo '<th style="border: 1px solid #ddd; padding: 8px;">Timestamp</th>';
        echo '<th style="border: 1px solid #ddd; padding: 8px;">Remove</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';

        // Loop through each unique session and display results in the table
        foreach ($session_results as $session) {
            $session_id = $session['session_id'];
            $user_id = $session['user_id'];
            $correct_count = $session['correct_count'];
            $total_count = $session['total_count'];
            $timestamp = $session['timestamp'];
            $percentage_score = ( count($selected_questions) > 0) ? round(($correct_count /  count($selected_questions)) * 100, 2) : 0;

            echo '<tr>';
            echo '<td style="border: 1px solid #ddd; padding: 8px;">' . $session_id . '</td>';
            echo '<td style="border: 1px solid #ddd; padding: 8px;">' . $user_id . '</td>';
            echo '<td style="border: 1px solid #ddd; padding: 8px;">' . $correct_count . '</td>';
            echo '<td style="border: 1px solid #ddd; padding: 8px;">' .  count($selected_questions) . '</td>';
            echo '<td style="border: 1px solid #ddd; padding: 8px;">' . $percentage_score . '%</td>';
            echo '<td style="border: 1px solid #ddd; padding: 8px;">' . $timestamp . '</td>';
            echo '<td style="border: 1px solid #ddd; padding: 8px;"><input type="checkbox" name="remove_session[]" value="' . $session_id . '"></td>';
            echo '</tr>';
        }

        echo '</tbody>';
        echo '</table>';
        echo '<input type="submit" name="remove_selected" value="Remove Selected Sessions">';
        echo '</form>';
    } else {
        echo '<p>No exam results available.</p>';
    }
}



// Add a custom meta box for displaying question results on the question edit page
function add_question_results_metabox() {
    add_meta_box('question_results_metabox', 'Question Results', 'display_question_results_metabox', 'question', 'normal', 'high');
}
add_action('add_meta_boxes', 'add_question_results_metabox');


// Function to delete exam result sessions
function delete_question_result_sessions($post_id, $session_ids) {
    $question_results = get_post_meta($post_id, 'question_results', true);

    if (!empty($question_results)) {
        $updated_question_results = array();

        foreach ($question_results as $result) {
            if (!in_array($result['session_id'], $session_ids)) {
                $updated_question_results[] = $result;
            }
        }

        update_post_meta($post_id, 'question_results', $updated_question_results);
    }
}

// Display question results in the custom meta box
function display_question_results_metabox($post) {
    $question_id = $post->ID;
    
    $existing_results = get_post_meta($question_id, 'question_results', true);
    $results_to_remove = array();

    echo '<h3>Question Results:</h3>';
    
    if (!empty($existing_results)) {
        echo '<form method="post">'; // Added a form element
        echo '<table style="width:100%; border-collapse:collapse; border: 1px solid #ddd;">';
        echo '<thead>';
        echo '<tr>';
        echo '<th style="border: 1px solid #ddd; padding: 8px;"></th>'; // Add a checkbox column
        echo '<th style="border: 1px solid #ddd; padding: 8px;">Exam Name</th>';
        echo '<th style="border: 1px solid #ddd; padding: 8px;">Session</th>';
        echo '<th style="border: 1px solid #ddd; padding: 8px;">Class ID</th>';
        echo '<th style="border: 1px solid #ddd; padding: 8px;">Exam ID</th>';
        echo '<th style="border: 1px solid #ddd; padding: 8px;">User ID</th>';
        echo '<th style="border: 1px solid #ddd; padding: 8px;">User Answer</th>';
        echo '<th style="border: 1px solid #ddd; padding: 8px;">Result</th>';
        echo '<th style="border: 1px solid #ddd; padding: 8px;">Timestamp</th>';
        echo '</tr>';
        echo '</thead>';
        echo '<tbody>';
        
        foreach ($existing_results as $result) {
            if ($result['question_id'] == $question_id) { // Check if the result is for this question
                echo '<tr>';
                echo '<td style="border: 1px solid #ddd; padding: 8px;"><input type="checkbox" name="remove_result[]" value="' . $result['session_id'] . '"></td>';
                echo '<td style="border: 1px solid #ddd; padding: 8px;">' . $result['exam_name'] . '</td>';
                echo '<td style="border: 1px solid #ddd; padding: 8px;">' . $result['session_id'] . '</td>';
                echo '<td style="border: 1px solid #ddd; padding: 8px;">' . $result['class_id'] . '</td>';
                echo '<td style="border: 1px solid #ddd; padding: 8px;">' . $result['exam_id'] . '</td>';
                echo '<td style="border: 1px solid #ddd; padding: 8px;">' . $result['user_id'] . '</td>';
                echo '<td style="border: 1px solid #ddd; padding: 8px;">' . $result['user_answer'] . '</td>';
                echo '<td style="border: 1px solid #ddd; padding: 8px;">' . $result['is_correct'] . '</td>';
                echo '<td style="border: 1px solid #ddd; padding: 8px;">' . $result['timestamp'] . '</td>';
                echo '</tr>';
            }
        }
        
        echo '</tbody>';
        echo '</table>';
        echo '<input type="submit" name="remove_selected" value="Remove">';
        echo '</form>'; // Closed the form element
    } else {
        echo '<p>No question results available for this question.</p>';
    }

    // Handle removing selected results
    if (isset($_POST['remove_result']) ) {
        $results_to_remove = $_POST['remove_result'];
        // Debug information: print_r($results_to_remove);
        delete_question_result_sessions($question_id, $results_to_remove);
    }
}


function display_question_shortcode($atts) {
    global $post;
    
    // Check if we're inside a 'question' post
    if ($post->post_type !== 'question') {
        return 'This shortcode can only be used within a question post.';
    }
    
    $question_id = $post->ID;
     // Get categories
        $categories = get_the_terms($question_id, 'question_category');
        $parent_category = '';
        $child_category = '';
        
        if ($categories && !is_wp_error($categories)) {
            foreach ($categories as $category) {
                if ($category->parent == 0) {
                    $parent_category = $category->name;
                } else {
                    $child_category = $category->name;
                }
            }
        }
    
    ob_start();
    ?>
    <div class="question-display">
        <?php echo display_single_flashcard($question_id); ?>
         <div style="display:none">
        <h2><?php echo get_the_title($question_id); ?></h2>
        <?php if ($child_category): ?>
                <p>Category: <?php echo esc_html($child_category); ?></p>
            <?php endif; ?>
            <?php if ($parent_category): ?>
                <p>Main Category: <?php echo esc_html($parent_category); ?></p>
            <?php endif; ?>
        
        <?php
        $options = get_post_meta($question_id, 'multiple_choice_options', true);
        $correct_answer = get_post_meta($question_id, 'correct_answer', true);
        
        if (!empty($options)) {
            echo '<ul class="question-options">';
            foreach (['A', 'B', 'C', 'D'] as $option) {
                if (isset($options[$option])) {
                    echo '<li>' . esc_html($option) . ': ' . esc_html($options[$option]) . '</li>';
                }
            }
            echo '</ul>';
        }
        ?>
        
        <h3><strong>Correct Answer:</strong> <?php echo esc_html($correct_answer); ?></h3>
        
        <?php
       
        ?>
         </div>
        <div class="cta-section" style="padding-bottom:20px;">
            <h4>You can access thousands more PAST BOARD and Updated questions in a Flashcards like this! Study 
            <?php if ($child_category): ?>
                <?php echo esc_html($child_category); ?>
            <?php endif; ?>
            <?php if ($parent_category): ?>
                <?php echo esc_html($parent_category); ?>
            <?php endif; ?>
            
            and start LOOKSFAM now!</h4>
            
    
            <a href="/register" style="width:100%" class="button">Start Looksfam Now!</a>
        </div>
    </div>
    <br>
    <hr>
            <h4>Mastering the Board Exams in the <a href="https://www.bentamo.site">Philippines</a>: A Comprehensive Guide</h4>
            <p>The journey to becoming a licensed professional in the Philippines begins with passing the board exams, a rigorous assessment conducted by the <a href="https://www.prc.gov.ph/">Professional Regulation Commission (PRC)</a>. For many, this is a daunting process that requires thorough preparation and a deep understanding of the material. In this post, we’ll explore everything you need to know about the board exams in the Philippines, from registration to preparation, and how <a href="https://www.looksfam.co/">Looksfam</a> can be your ultimate companion in mastering these exams.</p>
            
            <h5>Understanding the PRC and the Board Exams</h5>
            <p>The Professional Regulation Commission (PRC) is the government agency responsible for regulating and supervising the practice of professionals in the Philippines. The PRC administers board exams for various professions, ensuring that only qualified individuals enter the workforce.</p>
            
            <h4>Courses that Require PRC Board Exams:</h4>
            <ul>
                <li>Engineering (Civil, Electrical, Mechanical, etc.)</li>
                <li>Nursing</li>
                <li>Accountancy</li>
                <li>Architecture</li>
                <li>Teaching (LET)</li>
                <li>Medicine</li>
                <li>Law (Bar Exam)</li>
                <li>Pharmacy</li>
                <li>Psychology</li>
                <li>And many more...</li>
            </ul>
            <p>Each course has its specific board exam, which tests the knowledge and skills essential to the profession. Passing the board exam is a crucial step in obtaining your professional license and practicing your chosen career legally.</p>
           
            <h4>How to Register and Apply for the PRC Board Exam</h4>
            <p>Registering for the board exam requires several steps, which are crucial to ensuring your application is processed smoothly:</p>
            
            <h5>Online Registration:</h4>
            <ol>
                <li>Visit the official <a href="https://www.prc.gov.ph/">PRC website</a> and create an account using your personal details.</li>
                <li>Fill out the application form, ensuring all information is accurate.</li>
            </ol>
            
            <h5>Document Submission:</h4>
            <p>Submit the necessary documents, including your Transcript of Records, Certificate of Good Moral Character, and other required documents specific to your profession.</p>
        
            <h5>Payment of Fees:</h4>
            <p>Pay the examination fee through the available payment channels. Ensure you keep the receipt as proof of payment.</p>
        
            <h5>Scheduling and Appointment:</h4>
            <p>Once your documents are verified, schedule your appointment for the exam. You will receive a Notice of Admission (NOA) which you need to bring on the exam day.</p>
        
            <h4>Preparation Tips for the PRC Board Exam</h4>
            <p>Preparing for the board exam requires dedication and a strategic approach. Here are some tips to help you succeed:</p>
            <ul>
                <li><strong>Understand the Exam Format:</strong> Familiarize yourself with the exam structure, types of questions, and time allocation.</li>
                <li><strong>Create a Study Plan:</strong> Develop a study schedule that covers all topics and allows for regular review sessions.</li>
                <li><strong>Use Quality Review Materials:</strong> Invest in reputable review books and online resources. Looksfam offers comprehensive review materials tailored to your specific exam.</li>
                <li><strong>Join Review Centers:</strong> Consider enrolling in a review center for guided instruction and additional resources.</li>
                <li><strong>Practice with Mock Exams:</strong> Take practice tests to assess your knowledge and improve your time management skills.</li>
                <li><strong>Stay Healthy:</strong> Maintain a balanced diet, get enough sleep, and exercise regularly to keep your mind and body in top condition.</li>
            </ul>
        
            <h4>On the Day of the Exam</h4>
            <p>Here are some tips to ensure you are ready on the day of the exam:</p>
            <ul>
                <li>Arrive at the exam venue early to avoid any last-minute stress.</li>
                <li>Bring all necessary documents, including your NOA, valid ID, and examination materials.</li>
                <li>Stay calm and focused during the exam. Read each question carefully and manage your time effectively.</li>
            </ul>
        
            <p>By following these steps and utilizing the resources provided by Looksfam, you can confidently navigate the board exam process and achieve your goal of becoming a licensed professional in the Philippines.</p>
    
    
    
   
    
     <h4>Why Looksfam is Your Best Ally in Board Exam Preparation</h4>
    <p>Board exam preparation can be overwhelming, but Looksfam is designed to make this journey easier, smarter, and more enjoyable. Here’s how:</p>
    
    <h5>EASIER: Simplified Question Mastery</h5>
    <p>Looksfam takes the stress out of studying by breaking down complex topics into manageable lessons. The platform’s adaptive learning pathways help you focus on areas that need the most improvement, making your study sessions more productive. With Looksfam, mastering board exam questions becomes a streamlined process, allowing you to track your progress and stay on top of your preparation.</p>
    
    <h5>SMARTER: Assess Your Mastery and Familiarity</h5>
    <p>Understanding your strengths and weaknesses is key to effective preparation. Looksfam offers real-time assessments that allow you to gauge your familiarity with the exam material. The platform’s analytics provide detailed insights into your performance, highlighting areas that need more focus. This smarter approach ensures that your study time is spent effectively, leading to better results.</p>
    
    <h5>GAMIFIED: Make Studying Fun and Competitive</h5>
    <p>Studying doesn’t have to be boring. With Looksfam’s gamified features, you can compete with peers on leaderboards, earn rewards, and track your achievements. This competitive element makes the learning process enjoyable, motivating you to stay consistent and push yourself further. The result? A more engaging and effective study experience.</p>
    
    <h5>Maximize Your Board Exam Success with Looksfam</h5>
    <p>Passing the board exam is not just about hard work; it’s about working smart. Looksfam provides the tools you need to streamline your preparation, track your progress, and stay motivated throughout your journey. Whether you’re studying for the engineering board exam, nursing, or any other licensure exam, Looksfam has got you covered.</p>
       <h5>By using Looksfam, you can:</h5>
    <ul>
        <li>Reduce exam anxiety by becoming familiar with the exam format and question types.</li>
        <li>Optimize your study sessions with personalized learning paths and progress tracking.</li>
        <li>Enjoy a competitive, gamified learning experience that keeps you motivated.</li>
    </ul> 
     <iframe src="https://www.facebook.com/plugins/post.php?href=https%3A%2F%2Fwww.facebook.com%2Flooksfam%2Fposts%2Fpfbid0DCshNWm26LfSPSpPCqQCHm5cgf9rfj1D1nRhaMdU88fCdcYKDdgTsk6mvmfjtcFSl&show_text=true&width=900" width="900" height="900" style="border:none;overflow:hidden" scrolling="no" frameborder="0" allowfullscreen="true" allow="autoplay; clipboard-write; encrypted-media; picture-in-picture; web-share"></iframe>
 
    
     <div class="cta-section">
             <h4>You can access thousands more PAST BOARD and Updated questions in a Flashcards like this! Study 
            <?php if ($child_category): ?>
                <?php echo esc_html($child_category); ?>
            <?php endif; ?>
            <?php if ($parent_category): ?>
                <?php echo esc_html($parent_category); ?>
            <?php endif; ?>
            
            and start LOOKSFAM now!</h4>
            
            <a href="#" class="button">Start Looksfam Now!</a>
        </div>
        
        <div style="margin-top:20px">
            <a href="https://www.bentamo.site">Bentamo - BNTM Technologies Inc.</a>, the creator of Looksfam, offers comprehensive business consultation and specializes in developing business automation solutions to meet diverse business needs. Their services are designed to integrate technology into operations, improving efficiency and reducing costs.
            </div>
            
        <?php  echo do_shortcode('[ads]'); ?>
    <?php
    return ob_get_clean();
}

//add_shortcode('content_question', 'display_question_shortcode');

function display_single_flashcard($post_id) {
    // Get question data
    $question = get_post($post_id);
    $question_title = esc_html($question->post_title);
    $multiple_choice_options = get_post_meta($post_id, 'multiple_choice_options', true);
    $correct_answer = get_post_meta($post_id, 'correct_answer', true);
    $question_solution = get_post_meta($post_id, 'solution', true);

    ob_start();
    ?>
    <style>
        .flashcard-container {
            perspective: 1000px;
            width: 100%;
            min-height: 60vh;
            position: relative;
            margin-bottom: 20px;
        }
        .flashcard {
            width: 100%;
            height: 100%;
            position: absolute;
            transform-style: preserve-3d;
            transition: transform 0.6s;
            left: 0;
            opacity: 1;
        }
        .flashcard.flip {
            transform: rotateY(180deg);
        }
        .flashcard-front, .flashcard-back {
            position: absolute;
            width: 100%;
            height: 100%;
            backface-visibility: hidden;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            background-color: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
            color: black;
            overflow-y: auto;
        }
        .flashcard-back {
            transform: rotateY(180deg);
        }
        .options-grid {
            display: grid;
            grid-template-columns: repeat(2, 1fr);
            gap: 10px;
            width: 100%;
            height: 100%;
        }
        .option-container {
            background-color: #fff;
            border: 1px solid #ddd;
            border-radius: 5px;
            padding: 10px;
            cursor: pointer;
            transition: background-color 0.3s;
            color: black;
            display: flex;
            align-items: center;
            height: 100%;
            flex-wrap: nowrap;
            justify-content: center;
            box-shadow: 0 4px 8px rgba(0,0,0,0.1);
        }
        .option-container:hover {
            background-color: #f5f5f5;
        }
        .option-container.selected {
            background-color: #e0e0e0;
        }
        .option-container.correct-answer {
            background-color: #c1e2b3;
        }
        .option-container.wrong-answer {
            background-color: #ffcccc;
        }
        .show-solution {
            margin-top: 10px;
            text-decoration: underline;
            cursor: pointer;
            display: none;
        }
        .return-to-question {
            margin-top: 20px;
            text-decoration: underline;
            cursor: pointer;
            color: #666;
        }
    </style>

    <div class="flashcard-container">
        <div class="flashcard" id="flashcard">
            <div class="flashcard-front">
                <h4 style="color:#000;"><strong></strong> <?php echo $question_title; ?></h4>
                <div class="options-grid">
                    <?php 
                    $shuffled_letters = ['A', 'B', 'C', 'D'];
                    shuffle($shuffled_letters);
                    foreach ($shuffled_letters as $option):
                        $optionId = "option-{$option}";
                        $isCorrect = ($option === $correct_answer);
                    ?>
                        <div class="option-container" 
                             onclick="selectOption('<?php echo $optionId; ?>', '<?php echo $correct_answer; ?>')" 
                             id="option-container-<?php echo $optionId; ?>">
                            <h5 style="color:#000;margin-bottom:0px;text-align:center;">
                                <?php echo $multiple_choice_options[$option]; ?>
                            </h5>
                        </div>
                    <?php endforeach; ?>
                </div>
                <div class="show-solution" onclick="flipCard()">Show Solution</div>
            </div>
            <div class="flashcard-back">
                <h5 style="color:#000;text-align: center;"><strong>Question:</strong></h5>
                <h5 style="color:#000;text-align: center;"><?php echo $question_title; ?></h5>
                <h5 style="color:#000;text-align: center;"><strong>Answer:</strong></h5>
                <h4 style="color:#000;text-align: center;"><?php echo $multiple_choice_options[$correct_answer]; ?></h4>
                <?php if (!empty($question_solution)): ?>
                    <h5 style="color:#000;text-align: center;">Explanation:</h5>
                    <p style="color:#000;text-align: center;"><?php echo nl2br(esc_html($question_solution)); ?></p>
                <?php endif; ?>
                <div class="return-to-question" onclick="flipCard()">← Return to Question</div>
            </div>
        </div>
    </div>

    <script>
    function selectOption(optionId, correctAnswer) {
        var allOptions = document.querySelectorAll('.option-container');
        var selectedOption = document.getElementById('option-container-' + optionId);
        var showSolutionButton = document.querySelector('.show-solution');
        
        allOptions.forEach(function(option) {
            option.classList.remove('selected', 'correct-answer', 'wrong-answer');
            var optionLetter = option.id.split('-').pop();
            
            if (option === selectedOption) {
                option.classList.add('selected');
                option.classList.add(optionLetter === correctAnswer ? 'correct-answer' : 'wrong-answer');
            } else if (optionLetter === correctAnswer) {
                option.classList.add('correct-answer');
            }
            option.style.pointerEvents = 'none';
        });
        
        showSolutionButton.style.display = 'block';
    }

    function flipCard() {
        var flashcard = document.getElementById('flashcard');
        flashcard.classList.toggle('flip');
    }
    </script>
    <?php
    return ob_get_clean();
}
/**
 * Question Import System with Spreadsheet Interface
 * Add this to your theme's functions.php or as a plugin
 */

// Add admin menu
add_action('admin_menu', 'question_import_admin_menu');
function question_import_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=question',
        'Import Questions with Images',
        'Import Questions',
        'manage_options',
        'import-questions-images',
        'render_question_import_page'
    );
}

// Enqueue scripts and styles
add_action('admin_enqueue_scripts', 'enqueue_question_import_assets');
function enqueue_question_import_assets($hook) {
    if ($hook !== 'question_page_import-questions-images') {
        return;
    }
    
    wp_enqueue_media();
    wp_enqueue_script('jquery');
}

// Get hierarchical categories
function get_hierarchical_categories() {
    $categories = get_terms(array(
        'taxonomy' => 'question_category',
        'hide_empty' => false,
    ));
    
    $hierarchy = array();
    
    foreach ($categories as $category) {
        $path = array();
        $current = $category;
        
        // Build path from child to parent
        while ($current) {
            array_unshift($path, $current->name);
            if ($current->parent) {
                $current = get_term($current->parent, 'question_category');
            } else {
                break;
            }
        }
        
        $hierarchy[] = array(
            'name' => $category->name,
            'path' => implode(' > ', $path),
            'term_id' => $category->term_id,
            'parent' => $category->parent
        );
    }
    
    return $hierarchy;
}

// Render the import page
function render_question_import_page() {
    $categories = get_hierarchical_categories();
    $exam_id = isset($_GET['exam']) ? intval($_GET['exam']) : 0;
    ?>
    <div class="wrap question-import-wrapper">
        <h1>Import Questions with Spreadsheet Interface</h1>
        
        <div class="import-instructions">
            <h3>📋 Instructions:</h3>
            <ul>
                <li><strong>Copy-Paste from Excel:</strong> Select cells in Excel and paste directly into the spreadsheet</li>
                <li><strong>Add rows:</strong> Click "Add Row" or "Add 10 Rows" buttons</li>
                <li><strong>Required fields (*):</strong> Question Title, Category, Options A-D, Correct Answer</li>
                <li><strong>Optional fields:</strong> Solution and Image</li>
                <li><strong>Category format:</strong> Type category name or select from dropdown (validates against existing categories)</li>
                <li><strong>Answer format:</strong> Type A, B, C, or D</li>
                <li><strong>Images:</strong> Click image cell to upload after pasting data</li>
                <li><strong>Invalid data will be highlighted in red</strong></li>
            </ul>
        </div>

        <div class="import-controls">
            <button id="addRowBtn" class="button button-primary">➕ Add Row</button>
            <button id="addMultipleRows" class="button">Add 10 Rows</button>
            <button id="clearAllBtn" class="button">Clear All</button>
            <button id="validateBtn" class="button">🔍 Validate Data</button>
            <button id="importBtn" class="button button-large button-primary">🚀 Import Questions</button>
            <span id="rowCount">Rows: 0</span>
        </div>

        <div class="spreadsheet-container">
            <table id="spreadsheet" class="spreadsheet-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th style="width: 300px;">Question Title *</th>
                        <th style="width: 200px;">Category *</th>
                        <th style="width: 150px;">Option A *</th>
                        <th style="width: 150px;">Option B *</th>
                        <th style="width: 150px;">Option C *</th>
                        <th style="width: 150px;">Option D *</th>
                        <th style="width: 80px;">Answer *</th>
                        <th style="width: 250px;">Solution (Optional)</th>
                        <th style="width: 100px;">Image (Optional)</th>
                        <th style="width: 50px;">Del</th>
                    </tr>
                </thead>
                <tbody id="spreadsheetBody">
                </tbody>
            </table>
        </div>

        <div id="progressContainer" style="display:none; margin-top: 20px;">
            <h3>Import Progress</h3>
            <div class="progress-bar-container">
                <div id="progressBar" class="progress-bar"></div>
            </div>
            <div id="progressStatus">
                <p>Processed: <span id="processedCount">0</span></p>
                <p>Success: <span id="successCount">0</span></p>
                <p>Skipped: <span id="skippedCount">0</span></p>
                <p>Failed: <span id="failedCount">0</span></p>
            </div>
            <div id="errorLog"></div>
        </div>
    </div>

    <style>
    .question-import-wrapper {
        max-width: 100%;
        padding: 20px;
    }
    
    .import-instructions {
        background: #f0f6fc;
        border-left: 4px solid #0073aa;
        padding: 15px;
        margin: 20px 0;
    }
    
    .import-instructions ul {
        margin: 10px 0 0 20px;
    }
    
    .import-instructions strong {
        color: #0073aa;
    }
    
    .import-controls {
        margin: 20px 0;
        padding: 15px;
        background: #fff;
        border: 1px solid #ccc;
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    #rowCount {
        margin-left: auto;
        font-weight: bold;
        color: #0073aa;
    }
    
    .spreadsheet-container {
        overflow: auto;
        margin: 20px 0;
        border: 2px solid #0073aa;
        max-height: 600px;
        background: #fff;
    }
    
    .spreadsheet-table {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
        font-family: 'Segoe UI', Arial, sans-serif;
        font-size: 13px;
    }
    
    .spreadsheet-table thead {
        position: sticky;
        top: 0;
        z-index: 100;
        background: #f8f9fa;
    }
    
    .spreadsheet-table th {
        background: #2c3e50;
        color: white;
        padding: 10px 5px;
        font-weight: 600;
        border: 1px solid #34495e;
        text-align: left;
        font-size: 12px;
    }
    
    .spreadsheet-table td {
        border: 1px solid #ddd;
        padding: 0;
        background: #fff;
        position: relative;
    }
    
    .spreadsheet-table tr:hover td {
        background: #f8f9fa;
    }
    
    .spreadsheet-table td.row-number {
        background: #ecf0f1;
        text-align: center;
        font-weight: bold;
        color: #7f8c8d;
        padding: 8px 5px;
    }
    
    .cell-input {
        width: 100%;
        border: none;
        padding: 8px;
        font-family: inherit;
        font-size: inherit;
        background: transparent;
        outline: none;
        box-sizing: border-box;
        height:100%;
    }
    
    .cell-input:focus {
        background: #fff9e6;
        outline: 2px solid #0073aa;
        outline-offset: -2px;
    }
    
    textarea.cell-input {
        resize: none;
        min-height: 40px;
        overflow: hidden;
    }
    
    .cell-category {
        position: relative;
    }
    
    .category-dropdown {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        max-height: 300px;
        overflow-y: auto;
        background: white;
        border: 2px solid #0073aa;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        z-index: 1000;
    }
    
    .category-dropdown.show {
        display: block;
    }
    
    .category-option {
        padding: 8px 12px;
        cursor: pointer;
        border-bottom: 1px solid #eee;
    }
    
    .category-option:hover {
        background: #e3f2fd;
    }
    
    .category-option.parent {
        font-weight: bold;
        background: #f5f5f5;
        color: #0073aa;
    }
    
    .category-option.child {
        padding-left: 24px;
        font-size: 12px;
    }
    
    .category-option.grandchild {
        padding-left: 36px;
        font-size: 11px;
        color: #666;
    }
    
    .error-cell {
        background: #ffebee !important;
        border: 2px solid #f44336 !important;
    }
    
    .valid-cell {
        background: #e8f5e9 !important;
    }
    
    .image-upload-cell {
        text-align: center;
        cursor: pointer;
        min-height: 50px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 5px;
    }
    
    .image-upload-cell:hover {
        background: #e3f2fd !important;
    }
    
    .image-preview {
        max-width: 60px;
        max-height: 60px;
        margin: 2px auto;
    }
    
    .image-upload-text {
        font-size: 10px;
        color: #999;
    }
    
    .remove-image {
        color: red;
        font-size: 10px;
        cursor: pointer;
        text-decoration: underline;
        margin-top: 2px;
    }
    
    .delete-row {
        color: #dc3232;
        cursor: pointer;
        font-size: 16px;
        text-decoration: none;
        display: block;
        text-align: center;
        padding: 8px;
    }
    
    .delete-row:hover {
        background: #ffebee;
    }
    
    .progress-bar-container {
        width: 100%;
        height: 30px;
        background: #f0f0f0;
        border-radius: 5px;
        overflow: hidden;
    }
    
    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #0073aa, #00a0d2);
        width: 0%;
        transition: width 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
    }
    
    #progressStatus {
        margin-top: 15px;
        padding: 15px;
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    #progressStatus p {
        margin: 5px 0;
    }
    
    #errorLog {
        margin-top: 15px;
        padding: 10px;
        background: #fff;
        border: 1px solid #ddd;
        max-height: 200px;
        overflow-y: auto;
    }
    
    .error-message {
        color: #dc3232;
        padding: 5px;
        margin: 2px 0;
        background: #fdd;
        border-left: 3px solid #dc3232;
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        var rowCounter = 0;
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var examId = <?php echo $exam_id; ?>;
        
        // Category data
        var categories = <?php echo json_encode($categories); ?>;
        var categoryNames = categories.map(c => c.name.toLowerCase());

        // Initialize with 5 rows
        for (var i = 0; i < 5; i++) {
            addRow();
        }

        // Add single row
        $('#addRowBtn').on('click', function() {
            addRow();
        });

        // Add multiple rows
        $('#addMultipleRows').on('click', function() {
            for (var i = 0; i < 10; i++) {
                addRow();
            }
        });

        // Clear all
        $('#clearAllBtn').on('click', function() {
            if (confirm('Are you sure you want to clear all rows?')) {
                $('#spreadsheetBody').empty();
                rowCounter = 0;
                updateRowCount();
                for (var i = 0; i < 5; i++) {
                    addRow();
                }
            }
        });

        function addRow() {
            rowCounter++;
            var row = $(`
                <tr data-row="${rowCounter}">
                    <td class="row-number">${rowCounter}</td>
                    <td><textarea class="cell-input question-title" data-col="title" rows="2"></textarea></td>
                    <td class="cell-category">
                        <textarea class="cell-input category-input" data-col="category" autocomplete="off"></textarea>
                        <div class="category-dropdown"></div>
                    </td>
                    <td><textarea class="cell-input option-a" data-col="option_a"></textarea></td>
                    <td><textarea class="cell-input option-b" data-col="option_b"></textarea></td>
                    <td><textarea class="cell-input option-c" data-col="option_c"></textarea></td>
                    <td><textarea class="cell-input option-d" data-col="option_d"></textarea></td>
                    <td><textarea class="cell-input correct-answer" data-col="answer" maxlength="1"></textarea></td>
                    <td><textarea class="cell-input solution" data-col="solution" rows="2"></textarea></td>
                    <td>
                        <div class="image-upload-cell" data-row="${rowCounter}">
                            <span class="image-upload-text">📷</span>
                            <input type="hidden" class="image-id" value="">
                        </div>
                    </td>
                    <td><a href="#" class="delete-row" data-row="${rowCounter}">🗑️</a></td>
                </tr>
            `);
            $('#spreadsheetBody').append(row);
            updateRowCount();
        }

        // Auto-resize textarea
        $(document).on('input', 'textarea.cell-input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        // Category autocomplete
        $(document).on('focus', '.category-input', function() {
            var input = $(this);
            var dropdown = input.siblings('.category-dropdown');
            showCategoryDropdown(input, dropdown, '');
        });

        $(document).on('input', '.category-input', function() {
            var input = $(this);
            var dropdown = input.siblings('.category-dropdown');
            var search = input.val().toLowerCase();
            showCategoryDropdown(input, dropdown, search);
            validateCategory(input);
        });

        $(document).on('blur', '.category-input', function() {
            var input = $(this);
            var dropdown = input.siblings('.category-dropdown');
            setTimeout(function() {
                dropdown.removeClass('show');
            }, 200);
            validateCategory(input);
        });

        function showCategoryDropdown(input, dropdown, search) {
            dropdown.empty();
            
            var filtered = categories.filter(function(cat) {
                return search === '' || cat.path.toLowerCase().includes(search);
            });

            // Group by parent
            var grouped = {};
            filtered.forEach(function(cat) {
                var parts = cat.path.split(' > ');
                var parent = parts.length > 0 ? parts[0] : 'Root';
                if (!grouped[parent]) {
                    grouped[parent] = [];
                }
                grouped[parent].push(cat);
            });

            Object.keys(grouped).forEach(function(parent) {
                dropdown.append(`<div class="category-option parent">${parent}</div>`);
                grouped[parent].forEach(function(cat) {
                    var level = cat.path.split(' > ').length - 1;
                    var className = level === 0 ? 'parent' : (level === 1 ? 'child' : 'grandchild');
                    dropdown.append(`<div class="category-option ${className}" data-name="${cat.name}">${cat.path}</div>`);
                });
            });

            dropdown.addClass('show');
        }

        $(document).on('click', '.category-option', function() {
            if (!$(this).hasClass('parent') || $(this).data('name')) {
                var name = $(this).data('name');
                var input = $(this).closest('.cell-category').find('.category-input');
                input.val(name);
                $(this).closest('.category-dropdown').removeClass('show');
                validateCategory(input);
            }
        });

        function validateCategory(input) {
            var value = input.val().trim().toLowerCase();
            if (value === '') {
                input.parent().removeClass('error-cell valid-cell');
            } else if (categoryNames.includes(value)) {
                input.parent().removeClass('error-cell').addClass('valid-cell');
            } else {
                input.parent().removeClass('valid-cell').addClass('error-cell');
            }
        }

        // Answer validation
        $(document).on('blur', '.correct-answer', function() {
            var value = $(this).val().toUpperCase().trim();
            $(this).val(value);
            if (value === '') {
                $(this).parent().removeClass('error-cell valid-cell');
            } else if (['A', 'B', 'C', 'D'].includes(value)) {
                $(this).parent().removeClass('error-cell').addClass('valid-cell');
            } else {
                $(this).parent().removeClass('valid-cell').addClass('error-cell');
            }
        });

        // Handle paste from Excel
        $(document).on('paste', '.cell-input', function(e) {
            e.preventDefault();
            var pastedData = e.originalEvent.clipboardData.getData('text');
            var rows = pastedData.split('\n');
            var currentCell = $(this);
            var currentRow = currentCell.closest('tr');
            var currentColIndex = currentCell.closest('td').index();
            
            // Ensure enough rows exist
            var rowsNeeded = rows.length;
            var existingRows = $('#spreadsheetBody tr').length;
            var currentRowIndex = currentRow.index();
            var rowsToAdd = Math.max(0, (currentRowIndex + rowsNeeded) - existingRows);
            
            for (var i = 0; i < rowsToAdd; i++) {
                addRow();
            }
            
            // Paste data
            rows.forEach(function(rowData, rowIndex) {
                if (rowData.trim() === '') return;
                
                var cells = rowData.split('\t');
                var targetRow = $('#spreadsheetBody tr').eq(currentRowIndex + rowIndex);
                
                cells.forEach(function(cellData, colIndex) {
                    var targetCell = targetRow.find('td').eq(currentColIndex + colIndex).find('.cell-input');
                    if (targetCell.length) {
                        targetCell.val(cellData.trim());
                        if (targetCell.is('textarea')) {
                            targetCell.trigger('input');
                        }
                        if (targetCell.hasClass('category-input')) {
                            validateCategory(targetCell);
                        }
                        if (targetCell.hasClass('correct-answer')) {
                            targetCell.blur();
                        }
                    }
                });
            });
        });

        // Delete row
        $(document).on('click', '.delete-row', function(e) {
            e.preventDefault();
            $(this).closest('tr').remove();
            updateRowCount();
        });

        // Image upload
        $(document).on('click', '.image-upload-cell', function() {
            var cell = $(this);
            var frame = wp.media({
                title: 'Select or Upload Image',
                button: { text: 'Use this image' },
                multiple: false
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                cell.html(`
                    <img src="${attachment.url}" class="image-preview">
                    <span class="remove-image">Remove</span>
                    <input type="hidden" class="image-id" value="${attachment.id}">
                `);
            });

            frame.open();
        });

        $(document).on('click', '.remove-image', function(e) {
            e.stopPropagation();
            var cell = $(this).closest('.image-upload-cell');
            cell.html('<span class="image-upload-text">📷</span><input type="hidden" class="image-id" value="">');
        });

        function updateRowCount() {
            var count = $('#spreadsheetBody tr').length;
            $('#rowCount').text('Rows: ' + count);
        }

        // Validate all data
        $('#validateBtn').on('click', function() {
            var errors = validateAllData();
            if (errors.length === 0) {
                alert('✅ All data is valid! Ready to import.');
            } else {
                alert('❌ Found ' + errors.length + ' errors:\n\n' + errors.join('\n'));
            }
        });

        function validateAllData() {
            var errors = [];
            
            $('#spreadsheetBody tr').each(function(index) {
                var row = $(this);
                var rowNum = index + 1;
                
                var title = row.find('.question-title').val().trim();
                var category = row.find('.category-input').val().trim();
                var optionA = row.find('.option-a').val().trim();
                var optionB = row.find('.option-b').val().trim();
                var optionC = row.find('.option-c').val().trim();
                var optionD = row.find('.option-d').val().trim();
                var answer = row.find('.correct-answer').val().trim().toUpperCase();
                
                // Skip completely empty rows
                if (!title && !category && !optionA && !optionB && !optionC && !optionD && !answer) {
                    return;
                }
                
                if (!title) errors.push(`Row ${rowNum}: Missing question title`);
                if (!category) {
                    errors.push(`Row ${rowNum}: Missing category`);
                } else if (!categoryNames.includes(category.toLowerCase())) {
                    errors.push(`Row ${rowNum}: Invalid category "${category}"`);
                }
                if (!optionA) errors.push(`Row ${rowNum}: Missing Option A`);
                if (!optionB) errors.push(`Row ${rowNum}: Missing Option B`);
                if (!optionC) errors.push(`Row ${rowNum}: Missing Option C`);
                if (!optionD) errors.push(`Row ${rowNum}: Missing Option D`);
                if (!answer) {
                    errors.push(`Row ${rowNum}: Missing correct answer`);
                } else if (!['A', 'B', 'C', 'D'].includes(answer)) {
                    errors.push(`Row ${rowNum}: Invalid answer "${answer}" (must be A, B, C, or D)`);
                }
            });
            
            return errors;
        }

        // Import questions
        $('#importBtn').on('click', function() {
            var errors = validateAllData();
            
            if (errors.length > 0) {
                alert('Please fix the following errors before importing:\n\n' + errors.join('\n'));
                return;
            }

            var questions = [];
            
            $('#spreadsheetBody tr').each(function() {
                var row = $(this);
                var title = row.find('.question-title').val().trim();
                
                // Skip empty rows
                if (!title) return;
                
                questions.push({
                    title: title,
                    category: row.find('.category-input').val().trim(),
                    option_a: row.find('.option-a').val().trim(),
                    option_b: row.find('.option-b').val().trim(),
                    option_c: row.find('.option-c').val().trim(),
                    option_d: row.find('.option-d').val().trim(),
                    correct_answer: row.find('.correct-answer').val().trim().toUpperCase(),
                    solution: row.find('.solution').val().trim(),
                    image_id: row.find('.image-id').val()
                });
            });

            if (questions.length === 0) {
                alert('No valid questions to import. Please add at least one question.');
                return;
            }

            if (!confirm('Import ' + questions.length + ' questions?')) {
                return;
            }

            // Start import
            $('#progressContainer').show();
            $('#importBtn').prop('disabled', true);
            processBatch(questions, 0);
        });

        function processBatch(questions, start) {
            var batchSize = 10;
            var batch = questions.slice(start, start + batchSize);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'import_questions_with_images',
                    questions: JSON.stringify(batch),
                    exam_id: examId
                },
                success: function(response) {
                    if (response.success) {
                        var processed = start + batch.length;
                        var total = questions.length;
                        var progress = (processed / total) * 100;
                        
                        $('#progressBar').css('width', progress + '%').text(Math.round(progress) + '%');
                        $('#processedCount').text(processed);
                        $('#successCount').text(response.data.success);
                        $('#skippedCount').text(response.data.skipped);
                        $('#failedCount').text(response.data.failed);

                        if (response.data.errors && response.data.errors.length > 0) {
                            response.data.errors.forEach(function(error) {
                                $('#errorLog').append('<div class="error-message">' + error + '</div>');
                            });
                        }

                        if (processed < total) {
                            processBatch(questions, processed);
                        } else {
                            $('#importBtn').prop('disabled', false);
                            alert('Import complete!\n\nSuccess: ' + response.data.success + '\nSkipped: ' + response.data.skipped + '\nFailed: ' + response.data.failed);
                            <?php if ($exam_id): ?>
                            if (confirm('Import complete! Go back to exam edit page?')) {
                                window.location.href = '<?php echo admin_url("post.php?post=$exam_id&action=edit"); ?>';
                            }
                            <?php endif; ?>
                        }
                    } else {
                        alert('Error: ' + response.data);
                        $('#importBtn').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Import failed: ' + error);
                    $('#importBtn').prop('disabled', false);
                }
            });
        }
    });
    </script>
    <?php
}

// AJAX handler for importing questions
add_action('wp_ajax_import_questions_with_images', 'import_questions_with_images_handler');
function import_questions_with_images_handler() {
    try {
        if (!isset($_POST['questions'])) {
            wp_send_json_error('No questions data provided');
            return;
        }

        $questions = json_decode(stripslashes($_POST['questions']), true);
        $exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0;
        
        $success = 0;
        $skipped = 0;
        $failed = 0;
        $errors = array();
        $question_ids = array();

        foreach ($questions as $index => $question_data) {
            // Check if question already exists
            $existing = get_page_by_title($question_data['title'], OBJECT, 'question');
            
            if ($existing) {
                $question_ids[] = $existing->ID;
                $skipped++;
                continue;
            }

            // Validate category
            $category_term = get_term_by('name', $question_data['category'], 'question_category');
            if (!$category_term) {
                $errors[] = "Question " . ($index + 1) . ": Category '{$question_data['category']}' not found";
                $failed++;
                continue;
            }

            // Create question post
            $post_data = array(
                'post_title' => $question_data['title'],
                'post_type' => 'question',
                'post_status' => 'publish',
                'post_content' => '[content_question]'
            );

            $question_id = wp_insert_post($post_data);

            if (is_wp_error($question_id)) {
                $errors[] = "Question " . ($index + 1) . ": " . $question_id->get_error_message();
                $failed++;
                continue;
            }

            // Set featured image if provided
            if (!empty($question_data['image_id'])) {
                set_post_thumbnail($question_id, intval($question_data['image_id']));
            }

            // Set category
            wp_set_post_terms($question_id, array($category_term->term_id), 'question_category');

            // Save question metadata
            $options = array(
                'A' => $question_data['option_a'],
                'B' => $question_data['option_b'],
                'C' => $question_data['option_c'],
                'D' => $question_data['option_d']
            );
            
            update_post_meta($question_id, 'multiple_choice_options', $options);
            update_post_meta($question_id, 'correct_answer', $question_data['correct_answer']);
            
            if (!empty($question_data['solution'])) {
                update_post_meta($question_id, 'solution', $question_data['solution']);
            }

            $question_ids[] = $question_id;
            $success++;
        }

        // Link questions to exam if exam_id provided
        if ($exam_id && !empty($question_ids)) {
            $selected_questions = get_post_meta($exam_id, 'selected_questions', true);
            if (!is_array($selected_questions)) {
                $selected_questions = array();
            }
            $selected_questions = array_merge($selected_questions, $question_ids);
            update_post_meta($exam_id, 'selected_questions', $selected_questions);
        }

        wp_send_json_success(array(
            'success' => $success,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors,
            'question_ids' => $question_ids
        ));

    } catch (Exception $e) {
        wp_send_json_error('Processing error: ' . $e->getMessage());
    }
    
    wp_die();
}

/**
 * Question Import System with Custom Database Table
 * Creates a custom table and imports questions to it
 * Add this to your theme's functions.php or as a plugin
 */

// Create custom table on activation
register_activation_hook(__FILE__, 'create_questions_table');
function create_questions_table() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'imported_questions';
    $charset_collate = $wpdb->get_charset_collate();

    $sql = "CREATE TABLE IF NOT EXISTS $table_name (
        id bigint(20) NOT NULL AUTO_INCREMENT,
        post_id bigint(20),
        title text NOT NULL,
        category varchar(255) NOT NULL,
        option_a text NOT NULL,
        option_b text NOT NULL,
        option_c text NOT NULL,
        option_d text NOT NULL,
        correct_answer varchar(1) NOT NULL,
        solution text,
        image_url varchar(255),
        exam_id bigint(20),
        created_at datetime DEFAULT CURRENT_TIMESTAMP,
        updated_at datetime DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
        PRIMARY KEY (id),
        KEY post_id (post_id),
        KEY category (category),
        KEY exam_id (exam_id)
    ) $charset_collate;";

    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    dbDelta($sql);
}
// Add admin menu with submenu items
add_action('admin_menu', 'custom_questions_admin_menu');
function custom_questions_admin_menu() {
    // Main menu
    add_menu_page(
        'Custom Questions',
        'Custom Questions',
        'manage_options',
        'custom-questions',
        'render_questions_list_page',
        'dashicons-list-view',
        25
    );
    
    // Import submenu
    add_submenu_page(
        'custom-questions',
        'Import Questions',
        'Import Questions',
        'manage_options',
        'import-custom-questions',
        'render_custom_import_page'
    );
    
    // Manage Table submenu
    add_submenu_page(
        'custom-questions',
        'Manage Database',
        'Manage Database',
        'manage_options',
        'manage-questions-table',
        'render_manage_table_page'
    );
    
    // Add submenu for importing from posts
    add_submenu_page(
        'custom-questions',
        'Import from Posts',
        'Import from Posts',
        'manage_options',
        'import-from-posts',
        'render_import_from_posts_page'
    );
}

// Enqueue scripts and styles
add_action('admin_enqueue_scripts', 'enqueue_custom_import_assets');
function enqueue_custom_import_assets($hook) {
    if (strpos($hook, 'custom-questions') === false) {
        return;
    }
    
    wp_enqueue_media();
    wp_enqueue_script('jquery');
}

// Get categories (from taxonomy or custom source)
function get_question_categories_list() {
    $categories = get_terms(array(
        'taxonomy' => 'question_category',
        'hide_empty' => false,
    ));
    
    $hierarchy = array();
    
    foreach ($categories as $category) {
        $path = array();
        $current = $category;
        
        while ($current) {
            array_unshift($path, $current->name);
            if ($current->parent) {
                $current = get_term($current->parent, 'question_category');
            } else {
                break;
            }
        }
        
        $hierarchy[] = array(
            'name' => $category->name,
            'path' => implode(' > ', $path),
            'term_id' => $category->term_id,
            'parent' => $category->parent
        );
    }
    
    return $hierarchy;
}
// Update the render_questions_list_page function with these changes:

function render_questions_list_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'imported_questions';
    
    // Handle delete
    if (isset($_GET['delete_id'])) {
        $delete_id = intval($_GET['delete_id']);
        $wpdb->delete($table_name, array('id' => $delete_id), array('%d'));
        echo '<div class="notice notice-success"><p>Question deleted successfully!</p></div>';
    }
    
    // Pagination
    $per_page = 20;
    $page = isset($_GET['paged']) ? max(1, intval($_GET['paged'])) : 1;
    $offset = ($page - 1) * $per_page;
    
    // Search
    $search = isset($_GET['s']) ? sanitize_text_field($_GET['s']) : '';
    $where = '';
    if ($search) {
        $where = $wpdb->prepare(" WHERE title LIKE %s OR category LIKE %s OR option_a LIKE %s OR option_b LIKE %s OR option_c LIKE %s OR option_d LIKE %s OR solution LIKE %s", 
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%',
            '%' . $wpdb->esc_like($search) . '%'
        );
    }
    
    $total = $wpdb->get_var("SELECT COUNT(*) FROM $table_name" . $where);
    $questions = $wpdb->get_results("SELECT * FROM $table_name" . $where . " ORDER BY created_at DESC LIMIT $offset, $per_page");
    $total_pages = ceil($total / $per_page);
    
    // Get categories for dropdown
    $categories = get_question_categories_list();
    ?>
    <div class="wrap">
        <h1>Custom Questions Database 
            <span class="subtitle" style="font-size: 14px; color: #666;">(Total: <?php echo number_format($total); ?> questions)</span>
        </h1>
        
        <div style="margin: 20px 0; background: #fff; padding: 15px; border: 1px solid #ccc;">
            <form method="get" style="display: flex; gap: 10px; align-items: center;">
                <input type="hidden" name="page" value="custom-questions">
                <input type="text" name="s" value="<?php echo esc_attr($search); ?>" placeholder="Search in questions, options, category, solution..." style="width: 400px; padding: 8px;">
                <button type="submit" class="button button-primary">🔍 Search</button>
                <?php if ($search): ?>
                    <a href="?page=custom-questions" class="button">Clear Search</a>
                    <span style="color: #666;">Found <?php echo number_format($total); ?> result(s)</span>
                <?php endif; ?>
            </form>
        </div>
        
        <table class="wp-list-table widefat fixed striped">
            <thead>
                <tr>
                    <th style="width: 50px;">ID</th>
                    <th style="width: 70px;">Post ID</th>
                    <th>Question</th>
                    <th style="width: 150px;">Category</th>
                    <th style="width: 80px;">Answer</th>
                    <th style="width: 100px;">Image</th>
                    <th style="width: 150px;">Created</th>
                    <th style="width: 150px;">Actions</th>
                </tr>
            </thead>
            <tbody>
                <?php if ($questions): ?>
                    <?php foreach ($questions as $q): ?>
                        <tr>
                            <td><?php echo $q->id; ?></td>
                            <td><?php echo $q->post_id ? $q->post_id : '—'; ?></td>
                            <td>
                                <strong><?php echo esc_html($q->title); ?></strong>
                                <div style="font-size: 12px; color: #666; margin-top: 5px;">
                                    A: <?php echo esc_html(substr($q->option_a, 0, 30)); ?>...<br>
                                    B: <?php echo esc_html(substr($q->option_b, 0, 30)); ?>...
                                </div>
                            </td>
                            <td><?php echo esc_html($q->category); ?></td>
                            <td><strong style="color: #0073aa;"><?php echo esc_html($q->correct_answer); ?></strong></td>
                            <td>
                                <?php if ($q->image_url): ?>
                                    <img src="<?php echo esc_url($q->image_url); ?>" style="max-width: 50px; max-height: 50px;">
                                <?php else: ?>
                                    —
                                <?php endif; ?>
                            </td>
                            <td><?php echo date('Y-m-d H:i', strtotime($q->created_at)); ?></td>
                            <td>
                                <button class="button button-small edit-question-btn" data-id="<?php echo $q->id; ?>">✏️ Edit</button>
                                <a href="?page=custom-questions&delete_id=<?php echo $q->id; ?><?php echo $search ? '&s=' . urlencode($search) : ''; ?>&paged=<?php echo $page; ?>" 
                                   onclick="return confirm('Delete this question?')" 
                                   class="button button-small" style="color: #dc3232;">🗑️ Delete</a>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                <?php else: ?>
                    <tr>
                        <td colspan="7" style="text-align: center; padding: 40px;">
                            <?php if ($search): ?>
                                No questions found matching "<?php echo esc_html($search); ?>". <a href="?page=custom-questions">Clear search</a>
                            <?php else: ?>
                                No questions found. <a href="?page=import-custom-questions">Import some questions</a>
                            <?php endif; ?>
                        </td>
                    </tr>
                <?php endif; ?>
            </tbody>
        </table>
        
        <?php if ($total_pages > 1): ?>
            <div class="tablenav">
                <div class="tablenav-pages">
                    <?php
                    $base_url = add_query_arg('paged', '%#%');
                    if ($search) {
                        $base_url = add_query_arg('s', urlencode($search), $base_url);
                    }
                    echo paginate_links(array(
                        'base' => $base_url,
                        'format' => '',
                        'prev_text' => '&laquo;',
                        'next_text' => '&raquo;',
                        'total' => $total_pages,
                        'current' => $page
                    ));
                    ?>
                </div>
            </div>
        <?php endif; ?>
    </div>

    <!-- Edit Question Modal -->
    <div id="editQuestionModal" style="display: none; position: fixed; z-index: 100000; left: 0; top: 0; width: 100%; height: 100%; overflow: auto; background-color: rgba(0,0,0,0.7);">
        <div style="background-color: #fefefe; margin: 2% auto; padding: 0; border: 1px solid #888; width: 90%; max-width: 900px; border-radius: 8px; max-height: 90vh; overflow-y: auto;">
            <div style="padding: 20px; background: #0073aa; color: white; border-radius: 8px 8px 0 0; display: flex; justify-content: space-between; align-items: center;">
                <h2 style="margin: 0; color: white;">Edit Question</h2>
                <span class="close-modal" style="cursor: pointer; font-size: 28px; font-weight: bold; color: white;">&times;</span>
            </div>
            
            <div style="padding: 30px;">
                <form id="editQuestionForm">
                    <input type="hidden" id="edit_question_id" name="question_id">
                    
                    <table class="form-table">
                        <tr>
                            <th style="width: 150px;"><label for="edit_title">Question Title *</label></th>
                            <td>
                                <textarea id="edit_title" name="title" rows="3" style="width: 100%;" required></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="edit_category">Category *</label></th>
                            <td>
                                <select id="edit_category" name="category" style="width: 100%;" required>
                                    <option value="">Select Category</option>
                                    <?php foreach ($categories as $cat): ?>
                                        <option value="<?php echo esc_attr($cat['name']); ?>">
                                            <?php echo esc_html($cat['path']); ?>
                                        </option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="edit_option_a">Option A *</label></th>
                            <td>
                                <textarea id="edit_option_a" name="option_a" rows="2" style="width: 100%;" required></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="edit_option_b">Option B *</label></th>
                            <td>
                                <textarea id="edit_option_b" name="option_b" rows="2" style="width: 100%;" required></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="edit_option_c">Option C *</label></th>
                            <td>
                                <textarea id="edit_option_c" name="option_c" rows="2" style="width: 100%;" required></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="edit_option_d">Option D *</label></th>
                            <td>
                                <textarea id="edit_option_d" name="option_d" rows="2" style="width: 100%;" required></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="edit_correct_answer">Correct Answer *</label></th>
                            <td>
                                <select id="edit_correct_answer" name="correct_answer" required>
                                    <option value="">Select Answer</option>
                                    <option value="A">A</option>
                                    <option value="B">B</option>
                                    <option value="C">C</option>
                                    <option value="D">D</option>
                                </select>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="edit_solution">Solution</label></th>
                            <td>
                                <textarea id="edit_solution" name="solution" rows="4" style="width: 100%;"></textarea>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Current Image</label></th>
                            <td>
                                <div id="current_image_preview"></div>
                                <button type="button" class="button" id="change_image_btn">Change Image</button>
                                <button type="button" class="button" id="remove_image_btn" style="display: none;">Remove Image</button>
                                <input type="hidden" id="edit_image_url" name="image_url">
                            </td>
                        </tr>
                    </table>
                    
                    <div style="margin-top: 20px; padding-top: 20px; border-top: 1px solid #ddd;">
                        <button type="submit" class="button button-primary button-large">💾 Update Question</button>
                        <button type="button" class="button button-large close-modal" style="margin-left: 10px;">Cancel</button>
                        <span id="update_status" style="margin-left: 15px;"></span>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
    .edit-question-btn {
        background: #0073aa;
        color: white;
        border-color: #0073aa;
    }
    .edit-question-btn:hover {
        background: #005a87;
        border-color: #005a87;
        color: white;
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        var modal = $('#editQuestionModal');
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';

        // Open modal
        $('.edit-question-btn').on('click', function() {
            var questionId = $(this).data('id');
            loadQuestionData(questionId);
        });

        // Close modal
        $('.close-modal').on('click', function() {
            modal.hide();
        });

        // Close on outside click
        $(window).on('click', function(event) {
            if (event.target.id === 'editQuestionModal') {
                modal.hide();
            }
        });

        // Load question data via AJAX
        function loadQuestionData(questionId) {
            $('#update_status').html('<span style="color: #0073aa;">Loading...</span>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_custom_question',
                    question_id: questionId
                },
                success: function(response) {
                    if (response.success) {
                        var q = response.data;
                        
                        $('#edit_question_id').val(q.id);
                        $('#edit_title').val(q.title);
                        $('#edit_category').val(q.category);
                        $('#edit_option_a').val(q.option_a);
                        $('#edit_option_b').val(q.option_b);
                        $('#edit_option_c').val(q.option_c);
                        $('#edit_option_d').val(q.option_d);
                        $('#edit_correct_answer').val(q.correct_answer);
                        $('#edit_solution').val(q.solution);
                        $('#edit_image_url').val(q.image_url);
                        
                        // Update image preview
                        if (q.image_url) {
                            $('#current_image_preview').html('<img src="' + q.image_url + '" style="max-width: 200px; max-height: 200px; display: block; margin-bottom: 10px;">');
                            $('#remove_image_btn').show();
                        } else {
                            $('#current_image_preview').html('<p style="color: #666;">No image</p>');
                            $('#remove_image_btn').hide();
                        }
                        
                        $('#update_status').html('');
                        modal.show();
                    } else {
                        alert('Error loading question: ' + response.data);
                    }
                },
                error: function() {
                    alert('Failed to load question data');
                }
            });
        }

        // Change image
        $('#change_image_btn').on('click', function() {
            var frame = wp.media({
                title: 'Select or Upload Image',
                button: { text: 'Use this image' },
                multiple: false
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                $('#current_image_preview').html('<img src="' + attachment.url + '" style="max-width: 200px; max-height: 200px; display: block; margin-bottom: 10px;">');
                $('#edit_image_url').val(attachment.url);
                $('#remove_image_btn').show();
            });

            frame.open();
        });

        // Remove image
        $('#remove_image_btn').on('click', function() {
            $('#current_image_preview').html('<p style="color: #666;">No image</p>');
            $('#edit_image_url').val('');
            $(this).hide();
        });

        // Submit form
        $('#editQuestionForm').on('submit', function(e) {
            e.preventDefault();
            
            $('#update_status').html('<span style="color: #0073aa;">⏳ Updating...</span>');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'update_custom_question',
                    question_id: $('#edit_question_id').val(),
                    title: $('#edit_title').val(),
                    category: $('#edit_category').val(),
                    option_a: $('#edit_option_a').val(),
                    option_b: $('#edit_option_b').val(),
                    option_c: $('#edit_option_c').val(),
                    option_d: $('#edit_option_d').val(),
                    correct_answer: $('#edit_correct_answer').val(),
                    solution: $('#edit_solution').val(),
                    image_url: $('#edit_image_url').val()
                },
                success: function(response) {
                    if (response.success) {
                        $('#update_status').html('<span style="color: green;">✅ Updated successfully!</span>');
                        setTimeout(function() {
                            location.reload();
                        }, 1000);
                    } else {
                        $('#update_status').html('<span style="color: red;">❌ Error: ' + response.data + '</span>');
                    }
                },
                error: function() {
                    $('#update_status').html('<span style="color: red;">❌ Update failed</span>');
                }
            });
        });
    });
    </script>
    <?php
}

// AJAX handler to get question data
add_action('wp_ajax_get_custom_question', 'get_custom_question_handler');
function get_custom_question_handler() {
    global $wpdb;
    
    if (!isset($_POST['question_id'])) {
        wp_send_json_error('No question ID provided');
        return;
    }
    
    $question_id = intval($_POST['question_id']);
    $table_name = $wpdb->prefix . 'imported_questions';
    
    $question = $wpdb->get_row($wpdb->prepare(
        "SELECT * FROM $table_name WHERE id = %d",
        $question_id
    ), ARRAY_A);
    
    if ($question) {
        wp_send_json_success($question);
    } else {
        wp_send_json_error('Question not found');
    }
    
    wp_die();
}

// AJAX handler to update question
add_action('wp_ajax_update_custom_question', 'update_custom_question_handler');
function update_custom_question_handler() {
    global $wpdb;
    
    if (!isset($_POST['question_id'])) {
        wp_send_json_error('No question ID provided');
        return;
    }
    
    $question_id = intval($_POST['question_id']);
    $table_name = $wpdb->prefix . 'imported_questions';
    
    // Validate category
    $category = sanitize_text_field($_POST['category']);
    $category_term = get_term_by('name', $category, 'question_category');
    if (!$category_term) {
        wp_send_json_error('Invalid category');
        return;
    }
    
    // Update question
    $result = $wpdb->update(
        $table_name,
        array(
            'title' => sanitize_textarea_field($_POST['title']),
            'category' => $category,
            'option_a' => sanitize_textarea_field($_POST['option_a']),
            'option_b' => sanitize_textarea_field($_POST['option_b']),
            'option_c' => sanitize_textarea_field($_POST['option_c']),
            'option_d' => sanitize_textarea_field($_POST['option_d']),
            'correct_answer' => strtoupper(sanitize_text_field($_POST['correct_answer'])),
            'solution' => sanitize_textarea_field($_POST['solution']),
            'image_url' => esc_url_raw($_POST['image_url']),
            'updated_at' => current_time('mysql')
        ),
        array('id' => $question_id),
        array('%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'),
        array('%d')
    );
    
    if ($result !== false) {
        wp_send_json_success('Question updated successfully');
    } else {
        wp_send_json_error('Failed to update question');
    }
    
    wp_die();
}

// Render import page
function render_custom_import_page() {
    $categories = get_question_categories_list();
    $exam_id = isset($_GET['exam']) ? intval($_GET['exam']) : 0;
    ?>
    <div class="wrap question-import-wrapper">
        <h1>Import Questions to Custom Database</h1>
        
        <div class="import-instructions">
            <h3>📋 Instructions:</h3>
            <ul>
                <li><strong>Copy-Paste from Excel:</strong> Select cells in Excel and paste directly into the spreadsheet</li>
                <li><strong>Add rows:</strong> Click "Add Row" or "Add 10 Rows" buttons</li>
                <li><strong>Required fields (*):</strong> Question Title, Category, Options A-D, Correct Answer</li>
                <li><strong>Optional fields:</strong> Solution and Image</li>
                <li><strong>Category format:</strong> Type category name or select from dropdown</li>
                <li><strong>Answer format:</strong> Type A, B, C, or D</li>
                <li><strong>Images:</strong> Click image cell to upload after pasting data</li>
                <li><strong>Data is saved to custom database table</strong></li>
            </ul>
        </div>

        <div class="import-controls">
            <button id="addRowBtn" class="button button-primary">➕ Add Row</button>
            <button id="addMultipleRows" class="button">Add 10 Rows</button>
            <button id="clearAllBtn" class="button">Clear All</button>
            <button id="validateBtn" class="button">🔍 Validate Data</button>
            <button id="importBtn" class="button button-large button-primary">🚀 Import Questions</button>
            <span id="rowCount">Rows: 0</span>
        </div>

        <div class="spreadsheet-container">
            <table id="spreadsheet" class="spreadsheet-table">
                <thead>
                    <tr>
                        <th style="width: 40px;">#</th>
                        <th style="width: 300px;">Question Title *</th>
                        <th style="width: 200px;">Category *</th>
                        <th style="width: 150px;">Option A *</th>
                        <th style="width: 150px;">Option B *</th>
                        <th style="width: 150px;">Option C *</th>
                        <th style="width: 150px;">Option D *</th>
                        <th style="width: 80px;">Answer *</th>
                        <th style="width: 250px;">Solution (Optional)</th>
                        <th style="width: 100px;">Image (Optional)</th>
                        <th style="width: 50px;">Del</th>
                    </tr>
                </thead>
                <tbody id="spreadsheetBody">
                </tbody>
            </table>
        </div>

        <div id="progressContainer" style="display:none; margin-top: 20px;">
            <h3>Import Progress</h3>
            <div class="progress-bar-container">
                <div id="progressBar" class="progress-bar"></div>
            </div>
            <div id="progressStatus">
                <p>Processed: <span id="processedCount">0</span></p>
                <p>Success: <span id="successCount">0</span></p>
                <p>Duplicates: <span id="skippedCount">0</span></p>
                <p>Failed: <span id="failedCount">0</span></p>
            </div>
            <div id="errorLog"></div>
        </div>
    </div>

    <style>
    .question-import-wrapper {
        max-width: 100%;
        padding: 20px;
    }
    
    .import-instructions {
        background: #f0f6fc;
        border-left: 4px solid #0073aa;
        padding: 15px;
        margin: 20px 0;
    }
    
    .import-instructions ul {
        margin: 10px 0 0 20px;
    }
    
    .import-instructions strong {
        color: #0073aa;
    }
    
    .import-controls {
        margin: 20px 0;
        padding: 15px;
        background: #fff;
        border: 1px solid #ccc;
        display: flex;
        gap: 10px;
        align-items: center;
    }
    
    #rowCount {
        margin-left: auto;
        font-weight: bold;
        color: #0073aa;
    }
    
    .spreadsheet-container {
        overflow: auto;
        margin: 20px 0;
        border: 2px solid #0073aa;
        max-height: 600px;
        background: #fff;
    }
    
    .spreadsheet-table {
        width: 100%;
        border-collapse: collapse;
        background: #fff;
        font-family: 'Segoe UI', Arial, sans-serif;
        font-size: 13px;
    }
    
    .spreadsheet-table thead {
        position: sticky;
        top: 0;
        z-index: 100;
        background: #f8f9fa;
    }
    
    .spreadsheet-table th {
        background: #2c3e50;
        color: white;
        padding: 10px 5px;
        font-weight: 600;
        border: 1px solid #34495e;
        text-align: left;
        font-size: 12px;
    }
    
    .spreadsheet-table td {
        border: 1px solid #ddd;
        padding: 0;
        background: #fff;
        position: relative;
    }
    
    .spreadsheet-table tr:hover td {
        background: #f8f9fa;
    }
    
    .spreadsheet-table td.row-number {
        background: #ecf0f1;
        text-align: center;
        font-weight: bold;
        color: #7f8c8d;
        padding: 8px 5px;
    }
    
    .cell-input {
        width: 100%;
        border: none;
        padding: 8px;
        font-family: inherit;
        font-size: inherit;
        background: transparent;
        outline: none;
        box-sizing: border-box;
        height:100%;
    }
    
    .cell-input:focus {
        background: #fff9e6;
        outline: 2px solid #0073aa;
        outline-offset: -2px;
    }
    
    textarea.cell-input {
        resize: none;
        min-height: 40px;
        overflow: hidden;
    }
    
    .cell-category {
        position: relative;
    }
    
    .category-dropdown {
        display: none;
        position: absolute;
        top: 100%;
        left: 0;
        right: 0;
        max-height: 300px;
        overflow-y: auto;
        background: white;
        border: 2px solid #0073aa;
        box-shadow: 0 4px 6px rgba(0,0,0,0.1);
        z-index: 1000;
    }
    
    .category-dropdown.show {
        display: block;
    }
    
    .category-option {
        padding: 8px 12px;
        cursor: pointer;
        border-bottom: 1px solid #eee;
    }
    
    .category-option:hover {
        background: #e3f2fd;
    }
    
    .category-option.parent {
        font-weight: bold;
        background: #f5f5f5;
        color: #0073aa;
    }
    
    .category-option.child {
        padding-left: 24px;
        font-size: 12px;
    }
    
    .category-option.grandchild {
        padding-left: 36px;
        font-size: 11px;
        color: #666;
    }
    
    .error-cell {
        background: #ffebee !important;
        border: 2px solid #f44336 !important;
    }
    
    .valid-cell {
        background: #e8f5e9 !important;
    }
    
    .image-upload-cell {
        text-align: center;
        cursor: pointer;
        min-height: 50px;
        display: flex;
        flex-direction: column;
        align-items: center;
        justify-content: center;
        padding: 5px;
    }
    
    .image-upload-cell:hover {
        background: #e3f2fd !important;
    }
    
    .image-preview {
        max-width: 60px;
        max-height: 60px;
        margin: 2px auto;
    }
    
    .image-upload-text {
        font-size: 10px;
        color: #999;
    }
    
    .remove-image {
        color: red;
        font-size: 10px;
        cursor: pointer;
        text-decoration: underline;
        margin-top: 2px;
    }
    
    .delete-row {
        color: #dc3232;
        cursor: pointer;
        font-size: 16px;
        text-decoration: none;
        display: block;
        text-align: center;
        padding: 8px;
    }
    
    .delete-row:hover {
        background: #ffebee;
    }
    
    .progress-bar-container {
        width: 100%;
        height: 30px;
        background: #f0f0f0;
        border-radius: 5px;
        overflow: hidden;
    }
    
    .progress-bar {
        height: 100%;
        background: linear-gradient(90deg, #0073aa, #00a0d2);
        width: 0%;
        transition: width 0.3s;
        display: flex;
        align-items: center;
        justify-content: center;
        color: white;
        font-weight: bold;
    }
    
    #progressStatus {
        margin-top: 15px;
        padding: 15px;
        background: #f9f9f9;
        border: 1px solid #ddd;
        border-radius: 4px;
    }
    
    #progressStatus p {
        margin: 5px 0;
    }
    
    #errorLog {
        margin-top: 15px;
        padding: 10px;
        background: #fff;
        border: 1px solid #ddd;
        max-height: 200px;
        overflow-y: auto;
    }
    
    .error-message {
        color: #dc3232;
        padding: 5px;
        margin: 2px 0;
        background: #fdd;
        border-left: 3px solid #dc3232;
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        var rowCounter = 0;
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var examId = <?php echo $exam_id; ?>;
        
        var categories = <?php echo json_encode($categories); ?>;
        var categoryNames = categories.map(c => c.name.toLowerCase());

        // Initialize with 5 rows
        for (var i = 0; i < 5; i++) {
            addRow();
        }

        $('#addRowBtn').on('click', function() {
            addRow();
        });

        $('#addMultipleRows').on('click', function() {
            for (var i = 0; i < 10; i++) {
                addRow();
            }
        });

        $('#clearAllBtn').on('click', function() {
            if (confirm('Are you sure you want to clear all rows?')) {
                $('#spreadsheetBody').empty();
                rowCounter = 0;
                updateRowCount();
                for (var i = 0; i < 5; i++) {
                    addRow();
                }
            }
        });

        function addRow() {
            rowCounter++;
            var row = $(`
                <tr data-row="${rowCounter}">
                    <td class="row-number">${rowCounter}</td>
                    <td><textarea class="cell-input question-title" data-col="title" rows="2"></textarea></td>
                    <td class="cell-category">
                        <textarea class="cell-input category-input" data-col="category" autocomplete="off"></textarea>
                        <div class="category-dropdown"></div>
                    </td>
                    <td><textarea class="cell-input option-a" data-col="option_a"></textarea></td>
                    <td><textarea class="cell-input option-b" data-col="option_b"></textarea></td>
                    <td><textarea class="cell-input option-c" data-col="option_c"></textarea></td>
                    <td><textarea class="cell-input option-d" data-col="option_d"></textarea></td>
                    <td><textarea class="cell-input correct-answer" data-col="answer" maxlength="1"></textarea></td>
                    <td><textarea class="cell-input solution" data-col="solution" rows="2"></textarea></td>
                    <td>
                        <div class="image-upload-cell" data-row="${rowCounter}">
                            <span class="image-upload-text">📷</span>
                            <input type="hidden" class="image-url" value="">
                        </div>
                    </td>
                    <td><a href="#" class="delete-row" data-row="${rowCounter}">🗑️</a></td>
                </tr>
            `);
            $('#spreadsheetBody').append(row);
            updateRowCount();
        }

        $(document).on('input', 'textarea.cell-input', function() {
            this.style.height = 'auto';
            this.style.height = (this.scrollHeight) + 'px';
        });

        $(document).on('focus', '.category-input', function() {
            var input = $(this);
            var dropdown = input.siblings('.category-dropdown');
            showCategoryDropdown(input, dropdown, '');
        });

        $(document).on('input', '.category-input', function() {
            var input = $(this);
            var dropdown = input.siblings('.category-dropdown');
            var search = input.val().toLowerCase();
            showCategoryDropdown(input, dropdown, search);
            validateCategory(input);
        });

        $(document).on('blur', '.category-input', function() {
            var input = $(this);
            var dropdown = input.siblings('.category-dropdown');
            setTimeout(function() {
                dropdown.removeClass('show');
            }, 200);
            validateCategory(input);
        });

        function showCategoryDropdown(input, dropdown, search) {
            dropdown.empty();
            
            var filtered = categories.filter(function(cat) {
                return search === '' || cat.path.toLowerCase().includes(search);
            });

            var grouped = {};
            filtered.forEach(function(cat) {
                var parts = cat.path.split(' > ');
                var parent = parts.length > 0 ? parts[0] : 'Root';
                if (!grouped[parent]) {
                    grouped[parent] = [];
                }
                grouped[parent].push(cat);
            });

            Object.keys(grouped).forEach(function(parent) {
                dropdown.append(`<div class="category-option parent">${parent}</div>`);
                grouped[parent].forEach(function(cat) {
                    var level = cat.path.split(' > ').length - 1;
                    var className = level === 0 ? 'parent' : (level === 1 ? 'child' : 'grandchild');
                    dropdown.append(`<div class="category-option ${className}" data-name="${cat.name}">${cat.path}</div>`);
                });
            });

            dropdown.addClass('show');
        }

        $(document).on('click', '.category-option', function() {
            if (!$(this).hasClass('parent') || $(this).data('name')) {
                var name = $(this).data('name');
                var input = $(this).closest('.cell-category').find('.category-input');
                input.val(name);
                $(this).closest('.category-dropdown').removeClass('show');
                validateCategory(input);
            }
        });

        function validateCategory(input) {
            var value = input.val().trim().toLowerCase();
            if (value === '') {
                input.parent().removeClass('error-cell valid-cell');
            } else if (categoryNames.includes(value)) {
                input.parent().removeClass('error-cell').addClass('valid-cell');
            } else {
                input.parent().removeClass('valid-cell').addClass('error-cell');
            }
        }

        $(document).on('blur', '.correct-answer', function() {
            var value = $(this).val().toUpperCase().trim();
            $(this).val(value);
            if (value === '') {
                $(this).parent().removeClass('error-cell valid-cell');
            } else if (['A', 'B', 'C', 'D'].includes(value)) {
                $(this).parent().removeClass('error-cell').addClass('valid-cell');
            } else {
                $(this).parent().removeClass('valid-cell').addClass('error-cell');
            }
        });

        $(document).on('paste', '.cell-input', function(e) {
            e.preventDefault();
            var pastedData = e.originalEvent.clipboardData.getData('text');
            var rows = pastedData.split('\n');
            var currentCell = $(this);
            var currentRow = currentCell.closest('tr');
            var currentColIndex = currentCell.closest('td').index();
            
            var rowsNeeded = rows.length;
            var existingRows = $('#spreadsheetBody tr').length;
            var currentRowIndex = currentRow.index();
            var rowsToAdd = Math.max(0, (currentRowIndex + rowsNeeded) - existingRows);
            
            for (var i = 0; i < rowsToAdd; i++) {
                addRow();
            }
            
            rows.forEach(function(rowData, rowIndex) {
                if (rowData.trim() === '') return;
                
                var cells = rowData.split('\t');
                var targetRow = $('#spreadsheetBody tr').eq(currentRowIndex + rowIndex);
                
                cells.forEach(function(cellData, colIndex) {
                    var targetCell = targetRow.find('td').eq(currentColIndex + colIndex).find('.cell-input');
                    if (targetCell.length) {
                        targetCell.val(cellData.trim());
                        if (targetCell.is('textarea')) {
                            targetCell.trigger('input');
                        }
                        if (targetCell.hasClass('category-input')) {
                            validateCategory(targetCell);
                        }
                        if (targetCell.hasClass('correct-answer')) {
                            targetCell.blur();
                        }
                    }
                });
            });
        });

        $(document).on('click', '.delete-row', function(e) {
            e.preventDefault();
            $(this).closest('tr').remove();
            updateRowCount();
        });

        $(document).on('click', '.image-upload-cell', function() {
            var cell = $(this);
            var frame = wp.media({
                title: 'Select or Upload Image',
                button: { text: 'Use this image' },
                multiple: false
            });

            frame.on('select', function() {
                var attachment = frame.state().get('selection').first().toJSON();
                cell.html(`
                    <img src="${attachment.url}" class="image-preview">
                    <span class="remove-image">Remove</span>
                    <input type="hidden" class="image-url" value="${attachment.url}">
                `);
            });

            frame.open();
        });

        $(document).on('click', '.remove-image', function(e) {
            e.stopPropagation();
            var cell = $(this).closest('.image-upload-cell');
            cell.html('<span class="image-upload-text">📷</span><input type="hidden" class="image-url" value="">');
        });

        function updateRowCount() {
            var count = $('#spreadsheetBody tr').length;
            $('#rowCount').text('Rows: ' + count);
        }

        $('#validateBtn').on('click', function() {
            var errors = validateAllData();
            if (errors.length === 0) {
                alert('✅ All data is valid! Ready to import.');
            } else {
                alert('❌ Found ' + errors.length + ' errors:\n\n' + errors.join('\n'));
            }
        });

        function validateAllData() {
            var errors = [];
            
            $('#spreadsheetBody tr').each(function(index) {
                var row = $(this);
                var rowNum = index + 1;
                
                var title = row.find('.question-title').val().trim();
                var category = row.find('.category-input').val().trim();
                var optionA = row.find('.option-a').val().trim();
                var optionB = row.find('.option-b').val().trim();
                var optionC = row.find('.option-c').val().trim();
                var optionD = row.find('.option-d').val().trim();
                var answer = row.find('.correct-answer').val().trim().toUpperCase();
                
                if (!title && !category && !optionA && !optionB && !optionC && !optionD && !answer) {
                    return;
                }
                
                if (!title) errors.push(`Row ${rowNum}: Missing question title`);
                if (!category) {
                    errors.push(`Row ${rowNum}: Missing category`);
                } else if (!categoryNames.includes(category.toLowerCase())) {
                    errors.push(`Row ${rowNum}: Invalid category "${category}"`);
                }
                if (!optionA) errors.push(`Row ${rowNum}: Missing Option A`);
                if (!optionB) errors.push(`Row ${rowNum}: Missing Option B`);
                if (!optionC) errors.push(`Row ${rowNum}: Missing Option C`);
                if (!optionD) errors.push(`Row ${rowNum}: Missing Option D`);
                if (!answer) {
                    errors.push(`Row ${rowNum}: Missing correct answer`);
                } else if (!['A', 'B', 'C', 'D'].includes(answer)) {
                    errors.push(`Row ${rowNum}: Invalid answer "${answer}" (must be A, B, C, or D)`);
                }
            });
            
            return errors;
        }

        $('#importBtn').on('click', function() {
            var errors = validateAllData();
            
            if (errors.length > 0) {
                alert('Please fix the following errors before importing:\n\n' + errors.join('\n'));
                return;
            }

            var questions = [];
            
            $('#spreadsheetBody tr').each(function() {
                var row = $(this);
                var title = row.find('.question-title').val().trim();
                
                if (!title) return;
                
                questions.push({
                    title: title,
                    category: row.find('.category-input').val().trim(),
                    option_a: row.find('.option-a').val().trim(),
                    option_b: row.find('.option-b').val().trim(),
                    option_c: row.find('.option-c').val().trim(),
                    option_d: row.find('.option-d').val().trim(),
                    correct_answer: row.find('.correct-answer').val().trim().toUpperCase(),
                    solution: row.find('.solution').val().trim(),
                    image_url: row.find('.image-url').val()
                });
            });

            if (questions.length === 0) {
                alert('No valid questions to import. Please add at least one question.');
                return;
            }

            if (!confirm('Import ' + questions.length + ' questions to custom database?')) {
                return;
            }

            $('#progressContainer').show();
            $('#importBtn').prop('disabled', true);
            processBatch(questions, 0);
        });

        function processBatch(questions, start) {
            var batchSize = 10;
            var batch = questions.slice(start, start + batchSize);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'import_questions_to_custom_table',
                    questions: JSON.stringify(batch),
                    exam_id: examId
                },
                success: function(response) {
                    if (response.success) {
                        var processed = start + batch.length;
                        var total = questions.length;
                        var progress = (processed / total) * 100;
                        
                        $('#progressBar').css('width', progress + '%').text(Math.round(progress) + '%');
                        $('#processedCount').text(processed);
                        $('#successCount').text(response.data.success);
                        $('#skippedCount').text(response.data.skipped);
                        $('#failedCount').text(response.data.failed);

                        if (response.data.errors && response.data.errors.length > 0) {
                            response.data.errors.forEach(function(error) {
                                $('#errorLog').append('<div class="error-message">' + error + '</div>');
                            });
                        }

                        if (processed < total) {
                            processBatch(questions, processed);
                        } else {
                            $('#importBtn').prop('disabled', false);
                            alert('Import complete!\n\nSuccess: ' + response.data.success + '\nDuplicates: ' + response.data.skipped + '\nFailed: ' + response.data.failed);
                            if (confirm('View imported questions?')) {
                                window.location.href = '<?php echo admin_url("admin.php?page=custom-questions"); ?>';
                            }
                        }
                    } else {
                        alert('Error: ' + response.data);
                        $('#importBtn').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    alert('Import failed: ' + error);
                    $('#importBtn').prop('disabled', false);
                }
            });
        }
    });
    </script>
    <?php
}

// Render manage table page
function render_manage_table_page() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'imported_questions';
    
    // Handle actions
    if (isset($_POST['create_table'])) {
        create_questions_table();
        echo '<div class="notice notice-success"><p>Table created successfully!</p></div>';
    }
    
    if (isset($_POST['truncate_table'])) {
        $wpdb->query("TRUNCATE TABLE $table_name");
        echo '<div class="notice notice-success"><p>Table cleared successfully!</p></div>';
    }
    
    if (isset($_POST['drop_table'])) {
        $wpdb->query("DROP TABLE IF EXISTS $table_name");
        echo '<div class="notice notice-success"><p>Table dropped successfully!</p></div>';
    }
    
    // Check if table exists
    $table_exists = $wpdb->get_var("SHOW TABLES LIKE '$table_name'") === $table_name;
    
    // Get table stats
    $total_questions = 0;
    $table_size = 0;
    if ($table_exists) {
        $total_questions = $wpdb->get_var("SELECT COUNT(*) FROM $table_name");
        $table_size = $wpdb->get_var("SELECT ROUND((DATA_LENGTH + INDEX_LENGTH) / 1024 / 1024, 2) FROM information_schema.TABLES WHERE TABLE_SCHEMA = DATABASE() AND TABLE_NAME = '$table_name'");
    }
    
    ?>
    <div class="wrap">
        <h1>Manage Questions Database</h1>
        
        <div class="card" style="max-width: 800px;">
            <h2>Database Information</h2>
            <table class="form-table">
                <tr>
                    <th>Table Name:</th>
                    <td><code><?php echo $table_name; ?></code></td>
                </tr>
                <tr>
                    <th>Status:</th>
                    <td>
                        <?php if ($table_exists): ?>
                            <span style="color: green;">✓ Table exists</span>
                        <?php else: ?>
                            <span style="color: red;">✗ Table does not exist</span>
                        <?php endif; ?>
                    </td>
                </tr>
                <?php if ($table_exists): ?>
                <tr>
                    <th>Total Questions:</th>
                    <td><strong><?php echo number_format($total_questions); ?></strong></td>
                </tr>
                <tr>
                    <th>Table Size:</th>
                    <td><?php echo $table_size ? $table_size . ' MB' : 'N/A'; ?></td>
                </tr>
                <?php endif; ?>
            </table>
        </div>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Table Structure</h2>
            <p>The custom questions table includes the following columns:</p>
            <ul style="list-style: disc; margin-left: 20px;">
                <li><strong>id</strong> - Auto-increment primary key</li>
                <li><strong>title</strong> - Question title (text)</li>
                <li><strong>category</strong> - Question category from taxonomy (varchar 255)</li>
                <li><strong>option_a, option_b, option_c, option_d</strong> - Answer options (text)</li>
                <li><strong>correct_answer</strong> - Correct answer A/B/C/D (varchar 1)</li>
                <li><strong>solution</strong> - Solution explanation (text, optional)</li>
                <li><strong>image_url</strong> - Image URL (varchar 255, optional)</li>
                <li><strong>exam_id</strong> - Associated exam ID (bigint, optional)</li>
                <li><strong>created_at</strong> - Creation timestamp</li>
                <li><strong>updated_at</strong> - Last update timestamp</li>
            </ul>
        </div>
        
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Database Actions</h2>
            
            <?php if (!$table_exists): ?>
                <form method="post" style="margin: 10px 0;">
                    <button type="submit" name="create_table" class="button button-primary" onclick="return confirm('Create the questions table?')">
                        Create Table
                    </button>
                    <p class="description">Create the custom questions database table.</p>
                </form>
            <?php else: ?>
                <form method="post" style="margin: 10px 0;">
                    <button type="submit" name="truncate_table" class="button button-secondary" onclick="return confirm('This will delete ALL questions from the table. Are you sure?')">
                        Clear All Data
                    </button>
                    <p class="description">Remove all questions but keep the table structure.</p>
                </form>
                
                <form method="post" style="margin: 10px 0;">
                    <button type="submit" name="drop_table" class="button button-danger" style="background: #dc3232; color: white; border-color: #dc3232;" onclick="return confirm('This will permanently delete the table and ALL data. This cannot be undone! Are you sure?')">
                        Drop Table
                    </button>
                    <p class="description" style="color: #dc3232;">⚠️ Permanently delete the table and all data.</p>
                </form>
            <?php endif; ?>
        </div>
        
        <?php if ($table_exists): ?>
        <div class="card" style="max-width: 800px; margin-top: 20px;">
            <h2>Export Data</h2>
            <a href="<?php echo admin_url('admin-ajax.php?action=export_questions_csv'); ?>" class="button button-secondary">
                Export to CSV
            </a>
            <p class="description">Download all questions as a CSV file.</p>
        </div>
        <?php endif; ?>
    </div>
    <?php
}

// AJAX handler for importing questions to custom table
add_action('wp_ajax_import_questions_to_custom_table', 'import_questions_to_custom_table_handler');
function import_questions_to_custom_table_handler() {
    global $wpdb;
    
    try {
        if (!isset($_POST['questions'])) {
            wp_send_json_error('No questions data provided');
            return;
        }

        $questions = json_decode(stripslashes($_POST['questions']), true);
        $exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0;
        $table_name = $wpdb->prefix . 'imported_questions';
        
        $success = 0;
        $skipped = 0;
        $failed = 0;
        $errors = array();

        foreach ($questions as $index => $question_data) {
            // Check if question already exists (by title)
            $existing = $wpdb->get_var($wpdb->prepare(
                "SELECT id FROM $table_name WHERE title = %s",
                $question_data['title']
            ));
            
            if ($existing) {
                $skipped++;
                continue;
            }

            // Validate category exists in question_category taxonomy
            $category_term = get_term_by('name', $question_data['category'], 'question_category');
            if (!$category_term) {
                $errors[] = "Question " . ($index + 1) . ": Category '{$question_data['category']}' not found in question_category taxonomy";
                $failed++;
                continue;
            }

            // Insert into custom table
            $insert_result = $wpdb->insert(
                $table_name,
                array(
                    'title' => $question_data['title'],
                    'category' => $question_data['category'],
                    'option_a' => $question_data['option_a'],
                    'option_b' => $question_data['option_b'],
                    'option_c' => $question_data['option_c'],
                    'option_d' => $question_data['option_d'],
                    'correct_answer' => $question_data['correct_answer'],
                    'solution' => $question_data['solution'],
                    'image_url' => $question_data['image_url'],
                    'exam_id' => $exam_id,
                    'created_at' => current_time('mysql'),
                    'updated_at' => current_time('mysql')
                ),
                array(
                    '%s', // title
                    '%s', // category
                    '%s', // option_a
                    '%s', // option_b
                    '%s', // option_c
                    '%s', // option_d
                    '%s', // correct_answer
                    '%s', // solution
                    '%s', // image_url
                    '%d', // exam_id
                    '%s', // created_at
                    '%s'  // updated_at
                )
            );

            if ($insert_result === false) {
                $errors[] = "Question " . ($index + 1) . ": Database insert failed - " . $wpdb->last_error;
                $failed++;
            } else {
                $success++;
            }
        }

        wp_send_json_success(array(
            'success' => $success,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors
        ));

    } catch (Exception $e) {
        wp_send_json_error('Processing error: ' . $e->getMessage());
    }
    
    wp_die();
}

// Export to CSV
add_action('wp_ajax_export_questions_csv', 'export_questions_csv_handler');
function export_questions_csv_handler() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'imported_questions';
    
    $questions = $wpdb->get_results("SELECT * FROM $table_name ORDER BY created_at DESC", ARRAY_A);
    
    if (empty($questions)) {
        wp_die('No questions to export');
    }
    
    header('Content-Type: text/csv; charset=utf-8');
    header('Content-Disposition: attachment; filename=questions_export_' . date('Y-m-d') . '.csv');
    
    $output = fopen('php://output', 'w');
    
    // Headers
    fputcsv($output, array('ID', 'Title', 'Category', 'Option A', 'Option B', 'Option C', 'Option D', 'Correct Answer', 'Solution', 'Image URL', 'Exam ID', 'Created At'));
    
    // Data
    foreach ($questions as $question) {
        fputcsv($output, $question);
    }
    
    fclose($output);
    exit;
}


// Render import page
function render_import_from_posts_page() {
    ?>
    <div class="wrap">
        <h1>Import Questions from Posts</h1>
        <p>Import all questions from the 'question' post type to the custom database table.</p>
        
        <button id="startImportBtn" class="button button-primary">Start Import</button>
        
        <div id="progressContainer" style="display:none; margin-top: 20px;">
            <div style="background: #f0f0f0; height: 30px; border-radius: 5px; overflow: hidden;">
                <div id="progressBar" style="background: #0073aa; height: 100%; width: 0%; text-align: center; color: white; line-height: 30px;"></div>
            </div>
            <p>Total: <span id="totalCount">0</span></p>
            <p>Processed: <span id="processedCount">0</span></p>
            <p>Success: <span id="successCount">0</span></p>
            <p>Skipped: <span id="skippedCount">0</span></p>
            <p>Failed: <span id="failedCount">0</span></p>
            <div id="errorLog" style="margin-top: 10px; max-height: 200px; overflow-y: auto; background: #fff; border: 1px solid #ddd; padding: 10px;"></div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        
        $('#startImportBtn').on('click', function() {
            if (!confirm('Start importing questions from posts?')) {
                return;
            }
            
            $(this).prop('disabled', true);
            $('#progressContainer').show();
            
            // Get total count first
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_post_questions_count'
                },
                success: function(response) {
                    if (response.success) {
                        $('#totalCount').text(response.data);
                        startImport(0, response.data);
                    }
                }
            });
        });
        
        function startImport(offset, total) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'import_post_questions_batch',
                    offset: offset
                },
                success: function(response) {
                    if (response.success) {
                        var processed = offset + response.data.batch_size;
                        var progress = (processed / total) * 100;
                        
                        $('#progressBar').css('width', progress + '%').text(Math.round(progress) + '%');
                        $('#processedCount').text(processed);
                        $('#successCount').text(response.data.success);
                        $('#skippedCount').text(response.data.skipped);
                        $('#failedCount').text(response.data.failed);
                        
                        if (response.data.errors.length > 0) {
                            response.data.errors.forEach(function(error) {
                                $('#errorLog').append('<div style="color: red; margin: 2px 0;">' + error + '</div>');
                            });
                        }
                        
                        if (processed < total) {
                            startImport(processed, total);
                        } else {
                            $('#startImportBtn').prop('disabled', false);
                            alert('Import complete!\nSuccess: ' + response.data.success + '\nSkipped: ' + response.data.skipped + '\nFailed: ' + response.data.failed);
                        }
                    }
                },
                error: function() {
                    alert('Import failed');
                    $('#startImportBtn').prop('disabled', false);
                }
            });
        }
    });
    </script>
    <?php
}

// AJAX: Get total count of questions
add_action('wp_ajax_get_post_questions_count', 'get_post_questions_count_handler');
function get_post_questions_count_handler() {
    $count = wp_count_posts('question');
    $total = $count->publish;
    wp_send_json_success($total);
    wp_die();
}

// AJAX: Import batch of questions with retry logic
add_action('wp_ajax_import_post_questions_batch', 'import_post_questions_batch_handler');
function import_post_questions_batch_handler() {
    global $wpdb;
    
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $batch_size = 50;
    $table_name = $wpdb->prefix . 'imported_questions';
    
    // Get questions
    $args = array(
        'post_type' => 'question',
        'post_status' => 'publish',
        'posts_per_page' => $batch_size,
        'offset' => $offset,
        'orderby' => 'ID',
        'order' => 'ASC'
    );
    
    $questions = get_posts($args);
    
    $success = 0;
    $skipped = 0;
    $failed = 0;
    $errors = array();
    
    foreach ($questions as $post) {
        // Check if already imported (by post_id instead of title)
        $existing = $wpdb->get_var($wpdb->prepare(
            "SELECT id FROM $table_name WHERE post_id = %d",
            $post->ID
        ));
        
        if ($existing) {
            $skipped++;
            continue;
        }
        
        // Get post meta
        $options = get_post_meta($post->ID, 'multiple_choice_options', true);
        $correct_answer = get_post_meta($post->ID, 'correct_answer', true);
        $solution = get_post_meta($post->ID, 'solution', true);
        
        // Get category
        $terms = wp_get_post_terms($post->ID, 'question_category');
        $category = !empty($terms) && !is_wp_error($terms) ? $terms[0]->name : '';
        
        // Get featured image URL
        $image_url = '';
        if (has_post_thumbnail($post->ID)) {
            $image_url = get_the_post_thumbnail_url($post->ID, 'full');
        }
        
        // Validate required fields
        if (empty($post->post_title) || empty($category) || empty($options) || empty($correct_answer)) {
            // Validate required fields
            $missing = [];
            
            foreach (['post_title' => $post->post_title, 
                      'category' => $category, 
                      'options' => $options, 
                      'correct_answer' => $correct_answer] as $field => $value) {
                if (empty($value)) {
                    $missing[] = $field;
                }
            }
            
            if ($missing) {
                $errors[] = "Question ID {$post->ID}: Missing (" . implode(', ', $missing) . ")";
                $failed++;
                continue;
            }

        }
        
        // Prepare data
        $data = array(
            'post_id' => $post->ID,
            'title' => $post->post_title,
            'category' => $category,
            'option_a' => isset($options['A']) ? $options['A'] : '',
            'option_b' => isset($options['B']) ? $options['B'] : '',
            'option_c' => isset($options['C']) ? $options['C'] : '',
            'option_d' => isset($options['D']) ? $options['D'] : '',
            'correct_answer' => $correct_answer,
            'solution' => $solution ? $solution : '',
            'image_url' => $image_url,
            'exam_id' => 0,
            'created_at' => $post->post_date,
            'updated_at' => current_time('mysql')
        );
        
        $format = array('%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s');
        
        // Retry logic: Try up to 3 times
        $max_retries = 3;
        $retry_count = 0;
        $insert_success = false;
        
        while ($retry_count < $max_retries && !$insert_success) {
            $result = $wpdb->insert($table_name, $data, $format);
            
            if ($result !== false) {
                $insert_success = true;
                $success++;
            } else {
                $retry_count++;
                if ($retry_count < $max_retries) {
                    // Wait a bit before retrying (100ms, 200ms, 300ms)
                    usleep(100000 * $retry_count);
                }
            }
        }
        
        // If still failed after retries
        if (!$insert_success) {
            $errors[] = "Question ID {$post->ID}: Failed after {$max_retries} retries - " . $wpdb->last_error;
            $failed++;
        }
    }
    
    // Store cumulative counts in transient
    $stored_success = get_transient('import_success_count') ?: 0;
    $stored_skipped = get_transient('import_skipped_count') ?: 0;
    $stored_failed = get_transient('import_failed_count') ?: 0;
    
    $total_success = $stored_success + $success;
    $total_skipped = $stored_skipped + $skipped;
    $total_failed = $stored_failed + $failed;
    
    set_transient('import_success_count', $total_success, 3600);
    set_transient('import_skipped_count', $total_skipped, 3600);
    set_transient('import_failed_count', $total_failed, 3600);
    
    wp_send_json_success(array(
        'batch_size' => count($questions),
        'success' => $total_success,
        'skipped' => $total_skipped,
        'failed' => $total_failed,
        'errors' => $errors
    ));
    
    wp_die();
}
?>