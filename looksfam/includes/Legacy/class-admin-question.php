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
/*
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
//function display_multiple_choice_meta_box($post) {
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
*/

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
                        <input type="text" class="cell-input category-input" data-col="category" data-term-id="" autocomplete="off">
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
        'edit_posts',
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
        'edit_posts',
        'import-custom-questions',
        'render_custom_import_page'
    );
    
    // Manage Table submenu
    add_submenu_page(
        'custom-questions',
        'Manage Database',
        'Manage Database',
        'edit_posts',
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
    add_submenu_page(
    'custom-questions',
    'Import JSON',
    'Import JSON',
        'edit_posts',
    'import-json-questions',
    'render_json_import_page'
);
    add_submenu_page(
    'custom-questions',
    'Delete Post Meta',
    'Delete Post Meta',
    'manage_options',
    'delete-post-meta',
    'render_delete_post_meta_page'
);
    add_submenu_page(
        'custom-questions',
        'Fix Exam Answers',
        'Fix Exam Answers',
        'manage_options',
        'fix-exam-answers',
        'render_fix_exam_answers_page'
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
                            <td>
                                <?php 
                                // Display category name from ID
                                if (is_numeric($q->category)) {
                                    $term = get_term($q->category, 'question_category');
                                    echo $term && !is_wp_error($term) ? esc_html($term->name) : esc_html($q->category);
                                } else {
                                    echo esc_html($q->category);
                                }
                                ?>
                            </td>
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
                                        <option value="<?php echo esc_attr($cat['term_id']); ?>">
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
    
    // Validate category - now expecting term_id
    $category_id = intval($_POST['category']);
    $category_term = get_term($category_id, 'question_category');
    
    if (!$category_term || is_wp_error($category_term)) {
        wp_send_json_error('Invalid category');
        return;
    }
    
    // Update question
    $result = $wpdb->update(
        $table_name,
        array(
            'title' => sanitize_textarea_field($_POST['title']),
            'category' => $category_id, // Save term_id instead of name
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
        array('%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%s'), // Changed %s to %d for category
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
            <button id="add100Rows" class="button">Add 100 Rows</button>
            <button id="add1000Rows" class="button">Add 1000 Rows</button>
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
    var categoryMap = {};
    categories.forEach(c => categoryMap[c.name.toLowerCase()] = c);
    
    // Batch DOM operations for better performance
    var batchDOMUpdates = (function() {
        var pendingUpdates = [];
        var scheduled = false;
        
        return function(callback) {
            pendingUpdates.push(callback);
            if (!scheduled) {
                scheduled = true;
                requestAnimationFrame(function() {
                    pendingUpdates.forEach(cb => cb());
                    pendingUpdates = [];
                    scheduled = false;
                });
            }
        };
    })();

    // Initialize with 5 rows
    for (var i = 0; i < 5; i++) {
        addRow();
    }

    $('#addRowBtn').on('click', function() {
        addRow();
    });

    $('#addMultipleRows').on('click', function() {
        addMultipleRows(10);
    });

    $('#add100Rows').on('click', function() {
        if (confirm('Add 100 rows? This may take a moment.')) {
            $('#add100Rows').prop('disabled', true).text('Adding...');
            setTimeout(function() {
                addMultipleRows(100);
                $('#add100Rows').prop('disabled', false).text('Add 100 Rows');
            }, 10);
        }
    });

    $('#add1000Rows').on('click', function() {
        if (confirm('Add 1000 rows? This may take several seconds.')) {
            $('#add1000Rows').prop('disabled', true).text('Adding...');
            setTimeout(function() {
                addMultipleRows(1000);
                $('#add1000Rows').prop('disabled', false).text('Add 1000 Rows');
            }, 10);
        }
    });

    function addMultipleRows(count) {
        var fragment = document.createDocumentFragment();
        for (var i = 0; i < count; i++) {
            fragment.appendChild(createRowElement());
        }
        $('#spreadsheetBody')[0].appendChild(fragment);
        updateRowCount();
    }

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

    function createRowElement() {
        rowCounter++;
        var tr = document.createElement('tr');
        tr.setAttribute('data-row', rowCounter);
        tr.innerHTML = `
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
        `;
        return tr;
    }

    function addRow() {
        $('#spreadsheetBody').append(createRowElement());
        updateRowCount();
    }

    // Debounced input handler for better performance
    var autoResizeTimeout;
    $(document).on('input', 'textarea.cell-input', function() {
        var textarea = this;
        clearTimeout(autoResizeTimeout);
        autoResizeTimeout = setTimeout(function() {
            textarea.style.height = 'auto';
            textarea.style.height = (textarea.scrollHeight) + 'px';
        }, 50);
    });

    // Category validation without dropdown
    function validateCategoryQuietly(input) {
        var value = input.val().trim().toLowerCase();
        var parent = input.parent();
        
        if (value === '') {
            parent.removeClass('error-cell valid-cell');
        } else if (categoryNames.includes(value)) {
            parent.removeClass('error-cell').addClass('valid-cell');
        } else {
            parent.removeClass('valid-cell').addClass('error-cell');
        }
    }

    // Only show dropdown on explicit click
    $(document).on('click', '.category-input', function(e) {
        var input = $(this);
        var dropdown = input.siblings('.category-dropdown');
        
        // Toggle dropdown
        if (dropdown.hasClass('show')) {
            dropdown.removeClass('show');
        } else {
            // Hide all other dropdowns first
            $('.category-dropdown').removeClass('show');
            showCategoryDropdown(input, dropdown, input.val().toLowerCase());
        }
    });

    // Update search on input but don't auto-show dropdown
    $(document).on('input', '.category-input', function() {
        var input = $(this);
        var dropdown = input.siblings('.category-dropdown');
        
        // Only update dropdown if it's already visible
        if (dropdown.hasClass('show')) {
            var search = input.val().toLowerCase();
            showCategoryDropdown(input, dropdown, search);
        }
        
        // Always validate quietly
        validateCategoryQuietly(input);
    });

    // Close dropdown on blur
    $(document).on('blur', '.category-input', function() {
        var dropdown = $(this).siblings('.category-dropdown');
        setTimeout(function() {
            dropdown.removeClass('show');
        }, 200);
    });

    function showCategoryDropdown(input, dropdown, search) {
        var html = '';
        
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
            html += `<div class="category-option parent">${parent}</div>`;
            grouped[parent].forEach(function(cat) {
                var level = cat.path.split(' > ').length - 1;
                var className = level === 0 ? 'parent' : (level === 1 ? 'child' : 'grandchild');
                html += `<div class="category-option ${className}" data-name="${cat.name}" data-term-id="${cat.term_id}">${cat.path}</div>`;
            });
        });

        dropdown.html(html);
        dropdown.addClass('show');
    }

    $(document).on('click', '.category-option', function() {
        if (!$(this).hasClass('parent') || $(this).data('name')) {
            var name = $(this).data('name');
            var termId = $(this).data('term-id');
            var input = $(this).closest('.cell-category').find('.category-input');
            input.val(name);
            input.data('term-id', termId);
            $(this).closest('.category-dropdown').removeClass('show');
            validateCategoryQuietly(input);
        }
    });

    $(document).on('blur', '.correct-answer', function() {
        var value = $(this).val().toUpperCase().trim();
        $(this).val(value);
        var parent = $(this).parent();
        
        if (value === '') {
            parent.removeClass('error-cell valid-cell');
        } else if (['A', 'B', 'C', 'D'].includes(value)) {
            parent.removeClass('error-cell').addClass('valid-cell');
        } else {
            parent.removeClass('valid-cell').addClass('error-cell');
        }
    });

    // Optimized paste handler
    $(document).on('paste', '.cell-input', function(e) {
        e.preventDefault();
        var pastedData = e.originalEvent.clipboardData.getData('text');
        var rows = pastedData.split('\n').filter(r => r.trim() !== '');
        var currentCell = $(this);
        var currentRow = currentCell.closest('tr');
        var currentColIndex = currentCell.closest('td').index();
        var currentRowIndex = currentRow.index();
        
        // Show loading indicator for large pastes
        var isLargePaste = rows.length > 100;
        if (isLargePaste) {
            $('#importBtn').prop('disabled', true).text('Processing paste...');
        }
        
        // Calculate rows needed
        var rowsNeeded = rows.length;
        var existingRows = $('#spreadsheetBody tr').length;
        var rowsToAdd = Math.max(0, (currentRowIndex + rowsNeeded) - existingRows);
        
        // Add rows in batches using document fragment
        if (rowsToAdd > 0) {
            var fragment = document.createDocumentFragment();
            for (var i = 0; i < rowsToAdd; i++) {
                fragment.appendChild(createRowElement());
            }
            $('#spreadsheetBody')[0].appendChild(fragment);
        }
        
        // Process paste data
        setTimeout(function() {
            var tbody = $('#spreadsheetBody')[0];
            var allRows = tbody.querySelectorAll('tr');
            
            rows.forEach(function(rowData, rowIndex) {
                var cells = rowData.split('\t');
                var targetRow = allRows[currentRowIndex + rowIndex];
                if (!targetRow) return;
                
                var targetCells = targetRow.querySelectorAll('td');
                
                cells.forEach(function(cellData, colIndex) {
                    var targetTd = targetCells[currentColIndex + colIndex];
                    if (!targetTd) return;
                    
                    var targetInput = targetTd.querySelector('.cell-input');
                    if (targetInput) {
                        targetInput.value = cellData.trim();
                        
                        // Validate category quietly (no dropdown)
                        if (targetInput.classList.contains('category-input')) {
                            var $input = $(targetInput);
                            validateCategoryQuietly($input);
                        }
                        
                        // Validate answer
                        if (targetInput.classList.contains('correct-answer')) {
                            var val = targetInput.value.toUpperCase().trim();
                            targetInput.value = val;
                            var parent = targetInput.parentElement;
                            if (['A', 'B', 'C', 'D'].includes(val)) {
                                parent.classList.remove('error-cell');
                                parent.classList.add('valid-cell');
                            } else if (val !== '') {
                                parent.classList.remove('valid-cell');
                                parent.classList.add('error-cell');
                            }
                        }
                    }
                });
            });
            
            updateRowCount();
            
            if (isLargePaste) {
                $('#importBtn').prop('disabled', false).text('🚀 Import Questions');
            }
        }, 10);
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
            alert('❌ Found ' + errors.length + ' errors:\n\n' + errors.slice(0, 20).join('\n') + 
                  (errors.length > 20 ? '\n... and ' + (errors.length - 20) + ' more errors' : ''));
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
            alert('Please fix the following errors before importing:\n\n' + 
                  errors.slice(0, 20).join('\n') + 
                  (errors.length > 20 ? '\n... and ' + (errors.length - 20) + ' more errors' : ''));
            return;
        }

        var questions = [];
        
        $('#spreadsheetBody tr').each(function() {
            var row = $(this);
            var title = row.find('.question-title').val().trim();
            
            if (!title) return;
            
            var categoryInput = row.find('.category-input');
            var categoryName = categoryInput.val().trim();
            var categoryObj = categoryMap[categoryName.toLowerCase()];
            
            if (!categoryObj) return;
            
            questions.push({
                title: title,
                category: categoryName,
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
            
            // Validate category exists in question_category taxonomy and get term_id
            $category_term = get_term_by('name', $question_data['category'], 'question_category');
            if (!$category_term || is_wp_error($category_term)) {
                $errors[] = "Question " . ($index + 1) . ": Category '{$question_data['category']}' not found in question_category taxonomy";
                $failed++;
                continue;
            }
            
            // Generate unique post_id (5-digit random integer)
            $post_id = generate_unique_post_id($wpdb, $table_name);
            if ($post_id === false) {
                $errors[] = "Question " . ($index + 1) . ": Failed to generate unique post_id after multiple attempts";
                $failed++;
                continue;
            }
            
            // Insert into custom table with term_id instead of name
            $insert_result = $wpdb->insert(
                $table_name,
                array(
                    'post_id' => $post_id,
                    'title' => $question_data['title'],
                    'category' => $category_term->term_id,
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
                    '%d', // post_id
                    '%s', // title
                    '%d', // category
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
        
        // Get category ID instead of name
        $terms = wp_get_post_terms($post->ID, 'question_category');
        $category_id = !empty($terms) && !is_wp_error($terms) ? $terms[0]->term_id : 0;
        
        // Get featured image URL
        $image_url = '';
        if (has_post_thumbnail($post->ID)) {
            $image_url = get_the_post_thumbnail_url($post->ID, 'full');
        }
        
        // Validate required fields
        if (empty($post->post_title) || empty($category_id) || empty($options) || empty($correct_answer)) {
            $missing = [];
            
            foreach (['post_title' => $post->post_title, 
                      'category_id' => $category_id, 
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
        
        // Prepare data - save category_id instead of category name
        $data = array(
            'post_id' => $post->ID,
            'title' => $post->post_title,
            'category' => $category_id,  // Changed to store ID
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
        
        $format = array('%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s');  // Changed %s to %d for category
        
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


// Render JSON import page
function render_json_import_page() {
    ?>
    <div class="wrap">
        <h1>Import Questions from JSON File</h1>
        
        <div class="import-instructions">
            <h3>📋 JSON Import Instructions:</h3>
            <ul>
                <li><strong>File Format:</strong> JSON array of question objects</li>
                <li><strong>Required fields:</strong> post_title, category, option_a, option_b, option_c, option_d, correct_answer</li>
                <li><strong>Optional fields:</strong> solution, image_url</li>
                <li><strong>Category:</strong> Should match existing category names in your system</li>
                <li><strong>Correct Answer:</strong> Must be A, B, C, or D</li>
                <li><strong>File Size:</strong> Maximum 10MB</li>
            </ul>
            
            <h4>Example JSON Structure:</h4>
            <pre style="background: #f5f5f5; padding: 15px; border: 1px solid #ddd; border-radius: 4px; overflow: auto;">
[
    {
        "post_title": "Which of the following statements is TRUE about the function f(x) = |x|?",
        "category": "Differential Calculus",
        "option_a": "It is differentiable at x = 0.",
        "option_b": "It is continuous but not differentiable at x = 0.",
        "option_c": "It is neither continuous nor differentiable at x = 0.",
        "option_d": "It has a removable discontinuity at x = 0.",
        "correct_answer": "B",
        "solution": "V-shape has a sharp corner at 0, so no derivative."
    },
    {
        "post_title": "A spherical balloon is being inflated. If the radius of the balloon is increasing at a rate of 2 cm/s, how fast is the volume increasing when the radius is 10 cm?",
        "category": "Differential Calculus",
        "option_a": "80π cm³/s",
        "option_b": "400π cm³/s",
        "option_c": "800π cm³/s",
        "option_d": "1600π cm³/s",
        "correct_answer": "C",
        "solution": "dV/dt = 4πr² dr/dt = 4π(100)(2) = 800π"
    }
]
            </pre>
        </div>

        <div class="upload-section" style="background: #fff; padding: 20px; border: 1px solid #ccc; margin: 20px 0;">
            <h3>Upload JSON File</h3>
            <form id="jsonUploadForm" enctype="multipart/form-data">
                <?php wp_nonce_field('json_import_nonce', 'json_import_nonce'); ?>
                <table class="form-table">
                    <tr>
                        <th scope="row"><label for="json_file">Select JSON File</label></th>
                        <td>
                            <input type="file" id="json_file" name="json_file" accept=".json" required>
                            <p class="description">Select a .json file with questions data</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="exam_id">Exam ID (Optional)</label></th>
                        <td>
                            <input type="number" id="exam_id" name="exam_id" value="0" min="0">
                            <p class="description">Associate questions with an exam ID</p>
                        </td>
                    </tr>
                    <tr>
                        <th scope="row"><label for="skip_duplicates">Skip Duplicates</label></th>
                        <td>
                            <input type="checkbox" id="skip_duplicates" name="skip_duplicates" value="1" checked>
                            <label for="skip_duplicates">Skip questions that already exist (based on title)</label>
                        </td>
                    </tr>
                </table>
                
                <button type="submit" id="uploadJsonBtn" class="button button-primary button-large">📤 Upload and Import JSON</button>
            </form>
        </div>

        <div id="jsonProgressContainer" style="display:none; margin-top: 20px;">
            <h3>Import Progress</h3>
            <div class="progress-bar-container">
                <div id="jsonProgressBar" class="progress-bar"></div>
            </div>
            <div id="jsonProgressStatus" style="margin-top: 15px;">
                <p>Total Questions: <span id="jsonTotalCount">0</span></p>
                <p>Processed: <span id="jsonProcessedCount">0</span></p>
                <p>Success: <span id="jsonSuccessCount">0</span></p>
                <p>Duplicates: <span id="jsonSkippedCount">0</span></p>
                <p>Failed: <span id="jsonFailedCount">0</span></p>
            </div>
            <div id="jsonErrorLog" style="margin-top: 15px; max-height: 300px; overflow-y: auto;"></div>
        </div>

        <div id="jsonPreview" style="display:none; margin-top: 20px;">
            <h3>Data Preview</h3>
            <div id="previewContent" style="background: #f9f9f9; padding: 15px; border: 1px solid #ddd; max-height: 400px; overflow-y: auto;"></div>
        </div>
    </div>

    <style>
    .progress-bar-container {
        width: 100%;
        height: 30px;
        background: #f0f0f0;
        border-radius: 5px;
        overflow: hidden;
        margin: 10px 0;
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
        font-size: 14px;
    }
    
    .import-instructions {
        background: #f0f6fc;
        border-left: 4px solid #0073aa;
        padding: 20px;
        margin: 20px 0;
    }
    
    .import-instructions ul {
        margin: 10px 0 0 20px;
    }
    
    .import-instructions pre {
        font-size: 12px;
        line-height: 1.4;
    }
    
    .question-preview {
        background: white;
        border: 1px solid #ddd;
        margin: 10px 0;
        padding: 15px;
        border-radius: 4px;
    }
    
    .question-preview h4 {
        margin-top: 0;
        color: #0073aa;
    }
    
    .question-preview .options {
        margin: 10px 0;
    }
    
    .question-preview .correct {
        color: green;
        font-weight: bold;
    }
    
    .error-message {
        background: #ffebee;
        border-left: 4px solid #f44336;
        padding: 10px;
        margin: 5px 0;
    }
    
    .success-message {
        background: #e8f5e9;
        border-left: 4px solid #4caf50;
        padding: 10px;
        margin: 5px 0;
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        
        $('#jsonUploadForm').on('submit', function(e) {
            e.preventDefault();
            
            var formData = new FormData(this);
            formData.append('action', 'handle_json_upload');
            
            $('#uploadJsonBtn').prop('disabled', true).text('Processing...');
            $('#jsonProgressContainer').show();
            $('#jsonErrorLog').empty();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                processData: false,
                contentType: false,
                success: function(response) {
                    if (response.success) {
                        var data = response.data;
                        $('#jsonTotalCount').text(data.total);
                        $('#jsonProcessedCount').text(data.processed);
                        $('#jsonSuccessCount').text(data.success);
                        $('#jsonSkippedCount').text(data.skipped);
                        $('#jsonFailedCount').text(data.failed);
                        
                        // Show preview
                        if (data.preview && data.preview.length > 0) {
                            showPreview(data.preview);
                        }
                        
                        // Process import if preview is good
                        if (confirm('Found ' + data.total + ' questions. ' + data.errors.length + ' validation errors.\n\nStart import?')) {
                            processJsonImport(data.questions, 0, data.total);
                        } else {
                            $('#uploadJsonBtn').prop('disabled', false).text('📤 Upload and Import JSON');
                        }
                        
                        // Show errors
                        if (data.errors.length > 0) {
                            data.errors.forEach(function(error) {
                                $('#jsonErrorLog').append('<div class="error-message">' + error + '</div>');
                            });
                        }
                    } else {
                        alert('Error: ' + response.data);
                        $('#uploadJsonBtn').prop('disabled', false).text('📤 Upload and Import JSON');
                    }
                },
                error: function(xhr, status, error) {
                    alert('Upload failed: ' + error);
                    $('#uploadJsonBtn').prop('disabled', false).text('📤 Upload and Import JSON');
                }
            });
        });
        
        function showPreview(questions) {
            var previewHtml = '<p>Showing first ' + questions.length + ' questions:</p>';
            
            questions.forEach(function(question, index) {
                previewHtml += `
                    <div class="question-preview">
                        <h4>Question ${index + 1}: ${question.post_title}</h4>
                        <div class="options">
                            <p><strong>A:</strong> ${question.option_a}</p>
                            <p><strong>B:</strong> ${question.option_b}</p>
                            <p><strong>C:</strong> ${question.option_c}</p>
                            <p><strong>D:</strong> ${question.option_d}</p>
                            <p class="correct"><strong>Correct Answer:</strong> ${question.correct_answer}</p>
                            <p><strong>Category:</strong> ${question.category}</p>
                            ${question.solution ? '<p><strong>Solution:</strong> ' + question.solution + '</p>' : ''}
                        </div>
                    </div>
                `;
            });
            
            $('#previewContent').html(previewHtml);
            $('#jsonPreview').show();
        }
        
        function processJsonImport(questions, start, total) {
            var batchSize = 10;
            var batch = questions.slice(start, start + batchSize);
            
            if (batch.length === 0) {
                $('#uploadJsonBtn').prop('disabled', false).text('📤 Upload and Import JSON');
                alert('Import completed!');
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'import_json_questions_batch',
                    questions: JSON.stringify(batch),
                    exam_id: $('#exam_id').val(),
                    skip_duplicates: $('#skip_duplicates').is(':checked') ? 1 : 0
                },
                success: function(response) {
                    if (response.success) {
                        var processed = start + batch.length;
                        var progress = (processed / total) * 100;
                        
                        $('#jsonProgressBar').css('width', progress + '%').text(Math.round(progress) + '%');
                        $('#jsonProcessedCount').text(processed);
                        $('#jsonSuccessCount').text(response.data.success);
                        $('#jsonSkippedCount').text(response.data.skipped);
                        $('#jsonFailedCount').text(response.data.failed);
                        
                        if (response.data.errors && response.data.errors.length > 0) {
                            response.data.errors.forEach(function(error) {
                                $('#jsonErrorLog').append('<div class="error-message">' + error + '</div>');
                            });
                        }
                        
                        // Continue with next batch
                        setTimeout(function() {
                            processJsonImport(questions, processed, total);
                        }, 500);
                    } else {
                        alert('Import error: ' + response.data);
                        $('#uploadJsonBtn').prop('disabled', false).text('📤 Upload and Import JSON');
                    }
                },
                error: function() {
                    alert('Import failed');
                    $('#uploadJsonBtn').prop('disabled', false).text('📤 Upload and Import JSON');
                }
            });
        }
    });
    </script>
    <?php
}

// AJAX handler for JSON file upload and validation
add_action('wp_ajax_handle_json_upload', 'handle_json_upload_handler');
function handle_json_upload_handler() {
    global $wpdb;
    
    try {
        // Verify nonce
        if (!wp_verify_nonce($_POST['json_import_nonce'], 'json_import_nonce')) {
            wp_send_json_error('Security verification failed');
            return;
        }
        
        // Check file upload
        if (!isset($_FILES['json_file']) || $_FILES['json_file']['error'] !== UPLOAD_ERR_OK) {
            wp_send_json_error('File upload failed');
            return;
        }
        
        // Check file type
        $file_type = $_FILES['json_file']['type'];
        $file_size = $_FILES['json_file']['size'];
        
        if ($file_size > 10 * 1024 * 1024) { // 10MB limit
            wp_send_json_error('File size too large. Maximum 10MB allowed.');
            return;
        }
        
        // Read and parse JSON file
        $json_content = file_get_contents($_FILES['json_file']['tmp_name']);
        $data = json_decode($json_content, true);
        
        if (json_last_error() !== JSON_ERROR_NONE) {
            wp_send_json_error('Invalid JSON format: ' . json_last_error_msg());
            return;
        }
        
        // Validate structure - now expecting direct array
        if (!is_array($data)) {
            wp_send_json_error('JSON must contain an array of question objects');
            return;
        }
        
        $questions = $data;
        $valid_questions = [];
        $errors = [];
        
        // Get existing categories for validation
        $categories = get_question_categories_list();
        $category_names = array_map('strtolower', array_column($categories, 'name'));
        
        // Validate each question
        foreach ($questions as $index => $question) {
            $error_messages = [];
            
            // Check required fields
            $required_fields = ['post_title', 'category', 'option_a', 'option_b', 'option_c', 'option_d', 'correct_answer'];
            foreach ($required_fields as $field) {
                if (empty($question[$field])) {
                    $error_messages[] = "Missing required field: $field";
                }
            }
            
            // Validate category exists
            if (!empty($question['category']) && !in_array(strtolower($question['category']), $category_names)) {
                $error_messages[] = "Category '{$question['category']}' not found in system";
            }
            
            // Validate correct answer
            if (!empty($question['correct_answer']) && !in_array(strtoupper($question['correct_answer']), ['A', 'B', 'C', 'D'])) {
                $error_messages[] = "Correct answer must be A, B, C, or D";
            }
            
            if (empty($error_messages)) {
                // Add to valid questions
                $valid_questions[] = [
                    'post_title' => sanitize_text_field($question['post_title']),
                    'category' => sanitize_text_field($question['category']),
                    'option_a' => sanitize_textarea_field($question['option_a']),
                    'option_b' => sanitize_textarea_field($question['option_b']),
                    'option_c' => sanitize_textarea_field($question['option_c']),
                    'option_d' => sanitize_textarea_field($question['option_d']),
                    'correct_answer' => strtoupper(sanitize_text_field($question['correct_answer'])),
                    'solution' => isset($question['solution']) ? sanitize_textarea_field($question['solution']) : '',
                    'image_url' => isset($question['image_url']) ? esc_url_raw($question['image_url']) : ''
                ];
            } else {
                $errors[] = "Question " . ($index + 1) . ": " . implode(', ', $error_messages);
            }
        }
        
        // Return validation results
        wp_send_json_success([
            'total' => count($questions),
            'valid' => count($valid_questions),
            'invalid' => count($errors),
            'questions' => $valid_questions,
            'errors' => $errors,
            'preview' => array_slice($valid_questions, 0, 5) // Preview first 5 questions
        ]);
        
    } catch (Exception $e) {
        wp_send_json_error('Processing error: ' . $e->getMessage());
    }
    
    wp_die();
}

// AJAX handler for batch JSON import
add_action('wp_ajax_import_json_questions_batch', 'import_json_questions_batch_handler');
function import_json_questions_batch_handler() {
    global $wpdb;
    
    try {
        if (!isset($_POST['questions'])) {
            wp_send_json_error('No questions data provided');
            return;
        }
        
        $questions = json_decode(stripslashes($_POST['questions']), true);
        $exam_id = isset($_POST['exam_id']) ? intval($_POST['exam_id']) : 0;
        $skip_duplicates = isset($_POST['skip_duplicates']) ? boolval($_POST['skip_duplicates']) : true;
        $table_name = $wpdb->prefix . 'imported_questions';
        
        $success = 0;
        $skipped = 0;
        $failed = 0;
        $errors = [];
        
        foreach ($questions as $index => $question_data) {
            // Check for duplicates if enabled
            if ($skip_duplicates) {
                $existing = $wpdb->get_var($wpdb->prepare(
                    "SELECT id FROM $table_name WHERE title = %s",
                    $question_data['post_title']
                ));
                
                if ($existing) {
                    $skipped++;
                    continue;
                }
            }
            
            // Validate category exists and get term_id
            $category_term = get_term_by('name', $question_data['category'], 'question_category');
            if (!$category_term || is_wp_error($category_term)) {
                $errors[] = "Question " . ($index + 1) . ": Category '{$question_data['category']}' not found";
                $failed++;
                continue;
            }
            
            // Generate unique post_id
            $post_id = generate_unique_post_id($wpdb, $table_name);
            if ($post_id === false) {
                $errors[] = "Question " . ($index + 1) . ": Failed to generate unique post_id";
                $failed++;
                continue;
            }
            
            // Insert into custom table
            $insert_result = $wpdb->insert(
                $table_name,
                array(
                    'post_id' => $post_id,
                    'title' => $question_data['post_title'],
                    'category' => $category_term->term_id,
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
                    '%d', '%s', '%d', '%s', '%s', '%s', '%s', '%s', '%s', '%s', '%d', '%s', '%s'
                )
            );
            
            if ($insert_result === false) {
                $errors[] = "Question " . ($index + 1) . ": Database insert failed - " . $wpdb->last_error;
                $failed++;
            } else {
                $success++;
            }
        }
        
        wp_send_json_success([
            'success' => $success,
            'skipped' => $skipped,
            'failed' => $failed,
            'errors' => $errors,
            'batch_size' => count($questions)
        ]);
        
    } catch (Exception $e) {
        wp_send_json_error('Import error: ' . $e->getMessage());
    }
    
    wp_die();
}

// Render delete post meta page
function render_delete_post_meta_page() {
    ?>
    <div class="wrap">
        <h1>Delete Question Post Meta</h1>
        <p>This will delete all meta data from question posts (options, answers, solutions). The posts themselves will remain.</p>
        <p style="color: red;"><strong>Warning: This action cannot be undone!</strong></p>
        
        <button id="startDeleteBtn" class="button button-primary">Start Deleting Post Meta</button>
        
        <div id="progressContainer" style="display:none; margin-top: 20px;">
            <div style="background: #f0f0f0; height: 30px; border-radius: 5px; overflow: hidden;">
                <div id="progressBar" style="background: #dc3232; height: 100%; width: 0%; text-align: center; color: white; line-height: 30px;"></div>
            </div>
            <p>Total: <span id="totalCount">0</span></p>
            <p>Processed: <span id="processedCount">0</span></p>
            <p>Success: <span id="successCount">0</span></p>
            <p>Failed: <span id="failedCount">0</span></p>
            <div id="errorLog" style="margin-top: 10px; max-height: 200px; overflow-y: auto; background: #fff; border: 1px solid #ddd; padding: 10px;"></div>
        </div>
    </div>

    <script>
    jQuery(document).ready(function($) {
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        
        $('#startDeleteBtn').on('click', function() {
            if (!confirm('Are you sure you want to delete all post meta from question posts? This cannot be undone!')) {
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
                        startDelete(0, response.data);
                    }
                }
            });
        });
        
        function startDelete(offset, total) {
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'delete_post_meta_batch',
                    offset: offset
                },
                success: function(response) {
                    if (response.success) {
                        var processed = offset + response.data.batch_size;
                        var progress = (processed / total) * 100;
                        
                        $('#progressBar').css('width', progress + '%').text(Math.round(progress) + '%');
                        $('#processedCount').text(processed);
                        $('#successCount').text(response.data.success);
                        $('#failedCount').text(response.data.failed);
                        
                        if (response.data.errors.length > 0) {
                            response.data.errors.forEach(function(error) {
                                $('#errorLog').append('<div style="color: red; margin: 2px 0;">' + error + '</div>');
                            });
                        }
                        
                        if (processed < total) {
                            startDelete(processed, total);
                        } else {
                            $('#startDeleteBtn').prop('disabled', false);
                            alert('Delete complete!\nSuccess: ' + response.data.success + '\nFailed: ' + response.data.failed);
                        }
                    }
                },
                error: function() {
                    alert('Delete failed');
                    $('#startDeleteBtn').prop('disabled', false);
                }
            });
        }
    });
    </script>
    <?php
}
// AJAX: Delete post meta batch - FAST SQL METHOD
add_action('wp_ajax_delete_post_meta_batch', 'delete_post_meta_batch_handler');
function delete_post_meta_batch_handler() {
    global $wpdb;
    
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    $batch_size = 50;
    
    // Get questions
    $args = array(
        'post_type' => 'question',
        'post_status' => 'publish',
        'posts_per_page' => $batch_size,
        'offset' => $offset,
        'orderby' => 'ID',
        'order' => 'ASC',
        'fields' => 'ids' // Only get IDs for speed
    );
    
    $question_ids = get_posts($args);
    
    $success = 0;
    $failed = 0;
    $errors = array();
    
    foreach ($question_ids as $post_id) {
        // Direct SQL delete - deletes ALL meta for this post
        $result = $wpdb->delete(
            $wpdb->postmeta,
            array('post_id' => $post_id),
            array('%d')
        );
        
        if ($result !== false) {
            $success++;
        } else {
            $failed++;
            $errors[] = "Question ID {$post_id}: SQL delete failed - " . $wpdb->last_error;
        }
    }
    
    // Store cumulative counts in transient
    $stored_success = get_transient('delete_meta_success_count') ?: 0;
    $stored_failed = get_transient('delete_meta_failed_count') ?: 0;
    
    $total_success = $stored_success + $success;
    $total_failed = $stored_failed + $failed;
    
    set_transient('delete_meta_success_count', $total_success, 3600);
    set_transient('delete_meta_failed_count', $total_failed, 3600);
    
    wp_send_json_success(array(
        'batch_size' => count($question_ids),
        'success' => $total_success,
        'failed' => $total_failed,
        'errors' => $errors
    ));
    
    wp_die();
}
// Add submenu under Custom Questions
add_action('admin_menu', 'add_category_manager_menu', 20);
function add_category_manager_menu() {
    add_submenu_page(
        'custom-questions',
        'Organize Categories',
        'Organize Categories',
        'edit_posts',
        'organize-categories',
        'render_category_manager_page'
    );
}

// Enqueue scripts and styles
add_action('admin_enqueue_scripts', 'enqueue_category_manager_assets');
function enqueue_category_manager_assets($hook) {
    if ($hook !== 'custom-questions_page_organize-categories') {
        return;
    }
    
    wp_enqueue_script('jquery');
    wp_enqueue_script('jquery-ui-sortable');
}

// Get category tree with question counts
function get_category_tree_with_counts() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'imported_questions';
    
    $terms = get_terms(array(
        'taxonomy' => 'question_category',
        'hide_empty' => false,
        'orderby' => 'name',
        'order' => 'ASC'
    ));
    
    if (is_wp_error($terms)) {
        return array();
    }
    
    // Get question counts from custom table only
    $count_query = "SELECT category, COUNT(*) as count FROM $table_name GROUP BY category";
    $counts = $wpdb->get_results($count_query, OBJECT_K);
    
    // Build tree structure
    $tree = array();
    $terms_by_id = array();
    
    foreach ($terms as $term) {
        $custom_count = isset($counts[$term->term_id]) ? $counts[$term->term_id]->count : 0;
        
        $terms_by_id[$term->term_id] = array(
            'term' => $term,
            'children' => array(),
            'custom_count' => $custom_count,
            'total_count' => $custom_count
        );
    }
    
    // Organize into tree
    foreach ($terms_by_id as $id => $data) {
        if ($data['term']->parent == 0) {
            $tree[$id] = &$terms_by_id[$id];
        } else {
            if (isset($terms_by_id[$data['term']->parent])) {
                $terms_by_id[$data['term']->parent]['children'][$id] = &$terms_by_id[$id];
            }
        }
    }
    
    // Calculate total counts including all descendants
    function calculate_total_counts(&$node) {
        $total = $node['custom_count'];
        
        if (!empty($node['children'])) {
            foreach ($node['children'] as &$child) {
                $total += calculate_total_counts($child);
            }
        }
        
        $node['total_count'] = $total;
        return $total;
    }
    
    foreach ($tree as &$root) {
        calculate_total_counts($root);
    }
    
    return $tree;
}

// Generate unique post_id for questions
function generate_unique_post_id($wpdb, $table_name, $max_attempts = 100) {
    for ($i = 0; $i < $max_attempts; $i++) {
        $post_id = rand(100000, 999999);
        
        $exists = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $table_name WHERE post_id = %d",
            $post_id
        ));
        
        if ($exists == 0) {
            return $post_id;
        }
    }
    
    return false;
}

// Count all questions in category and its children
function count_category_questions_recursive($category_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'imported_questions';
    
    // Count direct questions
    $count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE category = %d",
        $category_id
    ));
    
    if ($wpdb->last_error) {
        error_log("WPDB Error counting questions for category $category_id: " . $wpdb->last_error);
    }
    
    $total = intval($count);
    error_log("Category $category_id has $total direct questions");
    
    // Get children and count their questions recursively
    $children = get_terms(array(
        'taxonomy' => 'question_category',
        'parent' => $category_id,
        'hide_empty' => false
    ));
    
    if (!is_wp_error($children) && !empty($children)) {
        error_log("Category $category_id has " . count($children) . " children");
        foreach ($children as $child) {
            $child_count = count_category_questions_recursive($child->term_id);
            error_log("Child {$child->term_id} ({$child->name}) contributed $child_count questions");
            $total += $child_count;
        }
    } else {
        error_log("Category $category_id has no children");
    }
    
    error_log("Total questions for category $category_id (including children): $total");
    return $total;
}

// Count child categories recursively
function count_child_categories_recursive($category_id) {
    $children = get_terms(array(
        'taxonomy' => 'question_category',
        'parent' => $category_id,
        'hide_empty' => false
    ));
    
    if (is_wp_error($children) || empty($children)) {
        error_log("Category $category_id has 0 child categories");
        return 0;
    }
    
    $count = count($children);
    error_log("Category $category_id has $count direct children");
    
    foreach ($children as $child) {
        $child_count = count_child_categories_recursive($child->term_id);
        $count += $child_count;
    }
    
    error_log("Total child categories for $category_id (including descendants): $count");
    return $count;
}

// Render the category manager page
function render_category_manager_page() {
    $category_tree = get_category_tree_with_counts();
    ?>
    <div class="wrap category-manager-wrapper">
        <h1>Organize Question Categories</h1>
        
        <div class="manager-container">
            <!-- Left Panel: Category Tree -->
            <div class="tree-panel">
                <div class="panel-header">
                    <h2>📁 Category Tree</h2>
                    <button id="addRootCategoryBtn" class="button button-primary">➕ Add Root Category</button>
                </div>
                
                <div class="tree-search">
                    <input type="text" id="categorySearch" placeholder="🔍 Search categories..." style="width: 100%; padding: 8px; margin-bottom: 10px;">
                    <div style="display: flex; gap: 5px; margin-bottom: 15px;">
                        <button id="expandAllBtn" class="button button-small" style="flex: 1;">➕ Expand All</button>
                        <button id="collapseAllBtn" class="button button-small" style="flex: 1;">➖ Collapse All</button>
                    </div>
                </div>
                
                <div id="categoryTree" class="category-tree">
                    <?php render_category_tree_html($category_tree); ?>
                </div>
            </div>
            
            <!-- Right Panel: Category Details & Actions -->
            <div class="details-panel">
                <div id="noSelection" class="no-selection">
                    <p>👈 Select a category from the tree to view details and perform actions</p>
                </div>
                
                <div id="categoryDetails" class="category-details" style="display: none;">
                    <div class="panel-header">
                        <h2 id="detailsTitle">Category Details</h2>
                    </div>
                    
                    <!-- Category Info -->
                    <div class="info-section">
                        <table class="form-table">
                            <tr>
                                <th>Category Name:</th>
                                <td>
                                    <input type="text" id="categoryName" class="regular-text" readonly>
                                    <button id="editNameBtn" class="button button-small">✏️ Edit</button>
                                    <button id="saveNameBtn" class="button button-small button-primary" style="display: none;">💾 Save</button>
                                    <button id="cancelNameBtn" class="button button-small" style="display: none;">❌ Cancel</button>
                                </td>
                            </tr>
                            <tr>
                                <th>Slug:</th>
                                <td><code id="categorySlug"></code></td>
                            </tr>
                            <tr>
                                <th>Term ID:</th>
                                <td><code id="categoryId"></code></td>
                            </tr>
                            <tr>
                                <th>Parent Category:</th>
                                <td>
                                    <select id="categoryParent" class="regular-text" disabled>
                                        <option value="0">None (Root Category)</option>
                                        <?php
                                        $all_categories = get_terms(array(
                                            'taxonomy' => 'question_category',
                                            'hide_empty' => false
                                        ));
                                        foreach ($all_categories as $cat) {
                                            echo '<option value="' . $cat->term_id . '">' . esc_html($cat->name) . '</option>';
                                        }
                                        ?>
                                    </select>
                                    <button id="editParentBtn" class="button button-small">✏️ Change Parent</button>
                                    <button id="saveParentBtn" class="button button-small button-primary" style="display: none;">💾 Save</button>
                                    <button id="cancelParentBtn" class="button button-small" style="display: none;">❌ Cancel</button>
                                </td>
                            </tr>
                            <tr>
                                <th>Total Questions:</th>
                                <td><strong id="totalCount" style="color: #0073aa; font-size: 16px;">0</strong></td>
                            </tr>
                        </table>
                    </div>
                    
                    <!-- Quick Actions -->
                    <div class="actions-section">
                        <h3>⚡ Quick Actions</h3>
                        <div class="action-buttons">
                            <button id="addChildBtn" class="button">➕ Add Child Category</button>
                            <button id="uploadQuestionsBtn" class="button">📤 Upload JSON Questions</button>
                            <button id="duplicateCategoryBtn" class="button">📑 Duplicate Category (with children & questions)</button>
                            <button id="copyQuestionsBtn" class="button">📋 Copy Questions to Another Category</button>
                            <button id="moveQuestionsBtn" class="button">🔄 Move Questions to Another Category</button>
                            <button id="viewQuestionsBtn" class="button">👁️ View All Questions</button>
                            <button id="deleteCategoryBtn" class="button button-danger" style="background: #dc3232; color: white;">🗑️ Delete Category</button>
                        </div>
                    </div>
                </div>
            </div>
        </div>
    </div>

    <!-- Add/Edit Category Modal -->
    <div id="categoryModal" class="category-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle">Add Category</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="categoryForm">
                    <input type="hidden" id="modal_action" value="add">
                    <input type="hidden" id="modal_category_id" value="0">
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="modal_category_name">Category Name *</label></th>
                            <td>
                                <input type="text" id="modal_category_name" class="regular-text" required>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="modal_category_parent">Parent Category</label></th>
                            <td>
                                <select id="modal_category_parent" class="regular-text">
                                    <option value="0">None (Root Category)</option>
                                    <?php foreach ($all_categories as $cat): ?>
                                        <option value="<?php echo $cat->term_id; ?>"><?php echo esc_html($cat->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="modal-footer">
                        <button type="submit" class="button button-primary">💾 Save Category</button>
                        <button type="button" class="button close-modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Upload Questions JSON Modal -->
    <div id="uploadQuestionsModal" class="category-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>📤 Upload Questions (JSON)</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="uploadQuestionsForm" enctype="multipart/form-data">
                    <input type="hidden" id="upload_category_id" value="0">
                    
                    <div class="upload-info">
                        <p><strong>Upload to Category:</strong> <span id="upload_category_name"></span></p>
                        <p><strong>Current Questions:</strong> <span id="upload_current_count">0</span></p>
                    </div>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="questions_json_file">JSON File *</label></th>
                            <td>
                                <input type="file" id="questions_json_file" accept=".json" required>
                                <p class="description">Select a JSON file containing questions</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Upload Options</label></th>
                            <td>
                                <label>
                                    <input type="radio" name="upload_mode" value="append" checked>
                                    Append to existing questions
                                </label><br>
                                <label>
                                    <input type="radio" name="upload_mode" value="replace">
                                    Replace all existing questions
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="json-format-info" style="background: #f0f6fc; padding: 15px; border-left: 4px solid #0073aa; margin: 20px 0;">
                        <h4 style="margin-top: 0;">Expected JSON Format:</h4>
                        <pre style="background: #fff; padding: 10px; overflow-x: auto; font-size: 12px;">[
  {
    "title": "Question text here?",
    "option_a": "First option",
    "option_b": "Second option",
    "option_c": "Third option",
    "option_d": "Fourth option",
    "correct_answer": "A",
    "solution": "Explanation here (optional)"
  }
]</pre>
                    </div>
                    
                    <div id="uploadProgress" style="display: none; margin-top: 15px;">
                        <div class="progress-bar-container">
                            <div id="uploadProgressBar" class="progress-bar"></div>
                        </div>
                        <p id="uploadStatus"></p>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="submit" id="uploadSubmitBtn" class="button button-primary">📤 Upload Questions</button>
                        <button type="button" class="button close-modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Duplicate Category Modal -->
    <div id="duplicateCategoryModal" class="category-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2>Duplicate Category with Children & Questions</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="duplicateCategoryForm">
                    <input type="hidden" id="duplicate_source_category" value="0">
                    
                    <div class="copy-info">
                        <p><strong>Source Category:</strong> <span id="duplicate_from_name"></span></p>
                        <p><strong>Total Questions (including children):</strong> <span id="duplicate_question_count">0</span></p>
                        <p><strong>Child Categories:</strong> <span id="duplicate_children_count">0</span></p>
                    </div>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="duplicate_new_name">New Category Name *</label></th>
                            <td>
                                <input type="text" id="duplicate_new_name" class="regular-text" required>
                                <p class="description">Name for the duplicated category</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label for="duplicate_parent_category">Place Under</label></th>
                            <td>
                                <select id="duplicate_parent_category" class="regular-text">
                                    <option value="0">Root (Top Level)</option>
                                    <?php
                                    $all_categories = get_terms(array(
                                        'taxonomy' => 'question_category',
                                        'hide_empty' => false
                                    ));
                                    foreach ($all_categories as $cat) {
                                        echo '<option value="' . $cat->term_id . '">' . esc_html($cat->name) . '</option>';
                                    }
                                    ?>
                                </select>
                                <p class="description">Choose where to place the duplicated category</p>
                            </td>
                        </tr>
                        <tr>
                            <th><label>Options</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="duplicate_include_children" checked>
                                    Include all child categories
                                </label><br>
                                <label>
                                    <input type="checkbox" id="duplicate_include_questions" checked>
                                    Copy all questions (from this category and all children)
                                </label>
                            </td>
                        </tr>
                    </table>
                    
                    <div id="duplicateProgress" style="display: none; margin-top: 15px;">
                        <div class="progress-bar-container">
                            <div id="duplicateProgressBar" class="progress-bar"></div>
                        </div>
                        <p id="duplicateStatus"></p>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="submit" id="duplicateSubmitBtn" class="button button-primary">📑 Start Duplication</button>
                        <button type="button" class="button close-modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- Copy/Move Questions Modal -->
    <div id="copyQuestionsModal" class="category-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="copyModalTitle">Copy Questions</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="copyQuestionsForm">
                    <input type="hidden" id="copy_source_category" value="0">
                    <input type="hidden" id="copy_action_type" value="copy">
                    
                    <div class="copy-info">
                        <p><strong>From Category:</strong> <span id="copy_from_name"></span></p>
                        <p><strong>Total Questions:</strong> <span id="copy_question_count">0</span></p>
                    </div>
                    
                    <table class="form-table">
                        <tr>
                            <th><label for="copy_target_category">Target Category *</label></th>
                            <td>
                                <select id="copy_target_category" class="regular-text" required>
                                    <option value="">Select target category</option>
                                    <?php foreach ($all_categories as $cat): ?>
                                        <option value="<?php echo $cat->term_id; ?>"><?php echo esc_html($cat->name); ?></option>
                                    <?php endforeach; ?>
                                </select>
                            </td>
                        </tr>
                    </table>
                    
                    <div id="copyProgress" style="display: none; margin-top: 15px;">
                        <div class="progress-bar-container">
                            <div id="copyProgressBar" class="progress-bar"></div>
                        </div>
                        <p id="copyStatus"></p>
                    </div>
                    
                    <div class="modal-footer">
                        <button type="submit" id="copySubmitBtn" class="button button-primary">📋 Start Copy</button>
                        <button type="button" class="button close-modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <!-- View Questions Modal -->
    <div id="viewQuestionsModal" class="category-modal" style="display: none;">
        <div class="modal-content" style="max-width: 90%; height: 90vh;">
            <div class="modal-header">
                <h2 id="viewModalTitle">Questions in Category</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body" style="overflow-y: auto; max-height: calc(90vh - 120px);">
                <div id="questionsListContainer">
                    <p>Loading questions...</p>
                </div>
            </div>
        </div>
    </div>

    <!-- Delete Category Modal -->
    <div id="deleteCategoryModal" class="category-modal" style="display: none;">
        <div class="modal-content">
            <div class="modal-header" style="background: #dc3232;">
                <h2>🗑️ Delete Category</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div class="modal-body">
                <form id="deleteCategoryForm">
                    <input type="hidden" id="delete_category_id" value="0">
                    
                    <div class="copy-info" style="background: #fff3cd; border-left-color: #ffc107;">
                        <p><strong>⚠️ Category to Delete:</strong> <span id="delete_category_name"></span></p>
                        <p><strong>Questions in this category:</strong> <span id="delete_direct_questions">0</span></p>
                        <p><strong>Child categories:</strong> <span id="delete_children_count">0</span></p>
                    </div>
                    
                    <div style="background: #f8d7da; border-left: 4px solid #dc3232; padding: 15px; margin: 20px 0;">
                        <h4 style="margin-top: 0; color: #721c24;">⚠️ Warning</h4>
                        <p style="color: #721c24; margin: 0;">This action cannot be undone. Please review your options carefully.</p>
                    </div>
                    
                    <table class="form-table">
                        <tr>
                            <th><label>Delete Options</label></th>
                            <td>
                                <label>
                                    <input type="checkbox" id="delete_questions_checkbox">
                                    <strong>Also delete all questions in this category</strong>
                                </label>
                                <p class="description" style="color: #dc3232; margin-top: 5px;">
                                    ⚠️ If unchecked, questions will remain in the database but lose their category assignment.
                                </p>
                            </td>
                        </tr>
                    </table>
                    
                    <div class="modal-footer" style="border-top: 2px solid #dc3232;">
                        <button type="submit" id="deleteSubmitBtn" class="button button-danger" style="background: #dc3232; color: white; border-color: #dc3232;">
                            🗑️ Confirm Delete
                        </button>
                        <button type="button" class="button close-modal">Cancel</button>
                    </div>
                </form>
            </div>
        </div>
    </div>

    <style>
    .category-manager-wrapper {
        margin: 20px 20px 20px 0;
    }
    
    .manager-container {
        display: grid;
        grid-template-columns: 400px 1fr;
        gap: 20px;
        margin-top: 20px;
    }
    
    .tree-panel, .details-panel {
        background: #fff;
        border: 1px solid #ccc;
        border-radius: 8px;
        padding: 20px;
    }
    
    .tree-panel {
        max-height: calc(100vh - 150px);
        overflow-y: auto;
    }
    
    .panel-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 15px;
        border-bottom: 2px solid #0073aa;
    }
    
    .panel-header h2 {
        margin: 0;
        color: #0073aa;
    }
    
    .category-tree {
        margin-top: 15px;
    }
    
    .tree-item {
        margin: 5px 0;
        padding: 10px;
        border: 1px solid #ddd;
        border-radius: 4px;
        cursor: pointer;
        transition: all 0.2s;
        background: #f9f9f9;
    }
    
    .tree-item:hover {
        background: #e3f2fd;
        border-color: #0073aa;
    }
    
    .tree-item.selected {
        background: #0073aa;
        color: white;
        border-color: #005a87;
    }
    
    .tree-item.selected .item-counts {
        color: #e3f2fd;
    }
    
    .tree-item-content {
        display: flex;
        align-items: center;
        justify-content: space-between;
    }
    
    .item-name {
        font-weight: 600;
        flex: 1;
    }
    
    .item-counts {
        font-size: 12px;
        color: #666;
        margin-left: 10px;
    }
    
    .tree-children {
        margin-left: 25px;
        margin-top: 5px;
        display: none;
    }
    
    .tree-children.expanded {
        display: block;
    }
    
    .toggle-icon {
        display: inline-block;
        width: 20px;
        cursor: pointer;
        font-weight: bold;
        user-select: none;
        margin-right: 5px;
    }
    
    .tree-item.parent > .tree-item-content .item-name::before {
        content: '📁 ';
    }
    
    .tree-item.child > .tree-item-content .item-name::before {
        content: '📄 ';
    }
    
    .no-selection {
        text-align: center;
        padding: 60px 20px;
        color: #999;
        font-size: 16px;
    }
    
    .category-details {
        max-height: calc(100vh - 150px);
        overflow-y: auto;
    }
    
    .info-section {
        margin-bottom: 30px;
        padding-bottom: 20px;
        border-bottom: 1px solid #ddd;
    }
    
    .actions-section h3 {
        margin-bottom: 15px;
        color: #0073aa;
    }
    
    .action-buttons {
        display: grid;
        grid-template-columns: repeat(2, 1fr);
        gap: 10px;
    }
    
    .action-buttons .button {
        justify-content: center;
        text-align: center;
        padding: 10px;
    }
    
    .button-danger:hover {
        background: #a00 !important;
        border-color: #a00 !important;
    }
    
    .category-modal {
        position: fixed;
        z-index: 100000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        overflow: auto;
        background-color: rgba(0,0,0,0.7);
    }
    
    .modal-content {
        background-color: #fefefe;
        margin: 5% auto;
        padding: 0;
        border: 1px solid #888;
        width: 600px;
        max-width: 90%;
        border-radius: 8px;
        box-shadow: 0 4px 20px rgba(0,0,0,0.3);
    }
    
    .modal-header {
        padding: 20px;
        background: #0073aa;
        color: white;
        border-radius: 8px 8px 0 0;
        display: flex;
        justify-content: space-between;
        align-items: center;
    }
    
    .modal-header h2 {
        margin: 0;
        color: white;
    }
    
    .close-modal {
        cursor: pointer;
        font-size: 28px;
        font-weight: bold;
        color: white;
    }
    
    .close-modal:hover {
        color: #ccc;
    }
    
    .modal-body {
        padding: 30px;
    }
    
    .modal-footer {
        margin-top: 20px;
        padding-top: 20px;
        border-top: 1px solid #ddd;
    }
    
    .copy-info, .upload-info {
        background: #f0f6fc;
        padding: 15px;
        border-left: 4px solid #0073aa;
        margin-bottom: 20px;
    }
    
    .copy-info p, .upload-info p {
        margin: 5px 0;
    }
    
    .progress-bar-container {
        width: 100%;
        height: 30px;
        background: #f0f0f0;
        border-radius: 5px;
        overflow: hidden;
        margin: 10px 0;
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
    
    #questionsListContainer {
        max-height: 600px;
        overflow-y: auto;
    }
    
    .question-item {
        background: #f9f9f9;
        border: 1px solid #ddd;
        padding: 15px;
        margin: 10px 0;
        border-radius: 4px;
    }
    
    .question-item h4 {
        margin: 0 0 10px 0;
        color: #0073aa;
    }
    
    .question-item .options {
        font-size: 13px;
        color: #666;
    }
    </style>

    <script>
    jQuery(document).ready(function($) {
        var ajaxurl = '<?php echo admin_url('admin-ajax.php'); ?>';
        var selectedCategoryId = null;
        var selectedCategoryData = null;
        
        // Function to refresh the category tree
        function refreshCategoryTree() {
            console.log('Refreshing category tree...');
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_category_tree'
                },
                success: function(response) {
                    console.log('Refresh category tree response:', response);
                    if (response.success) {
                        $('#categoryTree').html(response.data.html);
                        if (selectedCategoryId) {
                            loadCategoryDetails(selectedCategoryId);
                        }
                    } else {
                        console.error('Failed to refresh tree:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error refreshing tree:', error);
                    console.error('Response:', xhr.responseText);
                }
            });
        }
        
        // Category selection
        $(document).on('click', '.tree-item', function(e) {
            e.stopPropagation();
            
            if ($(e.target).hasClass('toggle-icon')) {
                return;
            }
            
            $('.tree-item').removeClass('selected');
            $(this).addClass('selected');
            
            selectedCategoryId = $(this).data('term-id');
            loadCategoryDetails(selectedCategoryId);
        });
        
        // Toggle tree items
        $(document).on('click', '.toggle-icon', function(e) {
            e.stopPropagation();
            
            var treeItem = $(this).closest('.tree-item');
            var children = treeItem.children('.tree-children');
            
            if (children.length > 0) {
                children.toggleClass('expanded');
                
                if (children.hasClass('expanded')) {
                    $(this).text('▼');
                } else {
                    $(this).text('▶');
                }
            }
        });
        
        // Expand all
        $('#expandAllBtn').on('click', function() {
            $('.tree-children').addClass('expanded');
            $('.toggle-icon').text('▼');
        });
        
        // Collapse all
        $('#collapseAllBtn').on('click', function() {
            $('.tree-children').removeClass('expanded');
            $('.toggle-icon').text('▶');
        });
        
        // Auto-expand selected item's parents
        function expandToSelected(termId) {
            var selectedItem = $('.tree-item[data-term-id="' + termId + '"]');
            selectedItem.parents('.tree-children').addClass('expanded').each(function() {
                $(this).siblings('.tree-item-content').find('.toggle-icon').text('▼');
            });
        }
        
        // Search categories
        $('#categorySearch').on('input', function() {
            var search = $(this).val().toLowerCase();
            $('.tree-item').each(function() {
                var name = $(this).find('.item-name').text().toLowerCase();
                if (name.includes(search)) {
                    $(this).show();
                } else {
                    $(this).hide();
                }
            });
        });
        
        // Load category details
        function loadCategoryDetails(termId) {
            expandToSelected(termId);
            
            console.log('Loading category details for term ID:', termId);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_category_details',
                    term_id: termId
                },
                success: function(response) {
                    console.log('Get category details response:', response);
                    if (response.success) {
                        selectedCategoryData = response.data;
                        displayCategoryDetails(response.data);
                    } else {
                        console.error('Failed to get category details:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error getting category details:', error);
                }
            });
        }
        
        function displayCategoryDetails(data) {
            $('#noSelection').hide();
            $('#categoryDetails').show();
            
            $('#detailsTitle').text(data.name);
            $('#categoryName').val(data.name);
            $('#categorySlug').text(data.slug);
            $('#categoryId').text(data.term_id);
            $('#categoryParent').val(data.parent);
            $('#totalCount').text(data.total_count);
            
            $('#categoryParent option').prop('disabled', false);
            $('#categoryParent option[value="' + data.term_id + '"]').prop('disabled', true);
        }
        
        // Edit category name
        $('#editNameBtn').on('click', function() {
            $('#categoryName').prop('readonly', false).focus();
            $('#editNameBtn').hide();
            $('#saveNameBtn, #cancelNameBtn').show();
        });
        
        $('#saveNameBtn').on('click', function() {
            var newName = $('#categoryName').val().trim();
            if (!newName) {
                alert('Category name cannot be empty');
                return;
            }
            
            updateCategory(selectedCategoryId, { name: newName });
        });
        
        $('#cancelNameBtn').on('click', function() {
            $('#categoryName').val(selectedCategoryData.name).prop('readonly', true);
            $('#editNameBtn').show();
            $('#saveNameBtn, #cancelNameBtn').hide();
        });
        
        // Edit parent
        $('#editParentBtn').on('click', function() {
            $('#categoryParent').prop('disabled', false).focus();
            $('#editParentBtn').hide();
            $('#saveParentBtn, #cancelParentBtn').show();
        });
        
        $('#saveParentBtn').on('click', function() {
            var newParent = $('#categoryParent').val();
            updateCategory(selectedCategoryId, { parent: newParent });
        });
        
        $('#cancelParentBtn').on('click', function() {
            $('#categoryParent').val(selectedCategoryData.parent).prop('disabled', true);
            $('#editParentBtn').show();
            $('#saveParentBtn, #cancelParentBtn').hide();
        });
        
        // Update category
        function updateCategory(termId, updates) {
            console.log('Updating category:', termId, 'Updates:', updates);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'update_category',
                    term_id: termId,
                    updates: JSON.stringify(updates)
                },
                success: function(response) {
                    console.log('Update category response:', response);
                    if (response.success) {
                        alert('✅ Category updated successfully!');
                        $('#categoryName').prop('readonly', true);
                        $('#categoryParent').prop('disabled', true);
                        $('#editNameBtn, #editParentBtn').show();
                        $('#saveNameBtn, #cancelNameBtn, #saveParentBtn, #cancelParentBtn').hide();
                        refreshCategoryTree();
                    } else {
                        console.error('Update failed:', response.data);
                        alert('❌ Error: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error updating category:', error);
                }
            });
        }
        
        // Add root category
        $('#addRootCategoryBtn').on('click', function() {
            $('#modalTitle').text('Add Root Category');
            $('#modal_action').val('add');
            $('#modal_category_id').val(0);
            $('#modal_category_name').val('');
            $('#modal_category_parent').val(0);
            $('#categoryModal').show();
        });
        
        // Add child category
        $('#addChildBtn').on('click', function() {
            if (!selectedCategoryId) return;
            
            $('#modalTitle').text('Add Child Category');
            $('#modal_action').val('add');
            $('#modal_category_id').val(0);
            $('#modal_category_name').val('');
            $('#modal_category_parent').val(selectedCategoryId);
            $('#categoryModal').show();
        });
        
        // Save category
        $('#categoryForm').on('submit', function(e) {
            e.preventDefault();
            
            var formData = {
                action: 'save_category',
                category_id: $('#modal_category_id').val(),
                name: $('#modal_category_name').val(),
                parent: $('#modal_category_parent').val()
            };
            
            console.log('Saving category with data:', formData);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: formData,
                success: function(response) {
                    console.log('Save category response:', response);
                    if (response.success) {
                        alert('✅ Category saved successfully!');
                        $('#categoryModal').hide();
                        // Refresh the tree without page reload
                        refreshCategoryTree();
                    } else {
                        console.error('Save failed:', response.data);
                        alert('❌ Error: ' + response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error saving category:', error);
                }
            });
        });
        
        // Upload questions
        $('#uploadQuestionsBtn').on('click', function() {
            if (!selectedCategoryId) return;
            
            $('#upload_category_id').val(selectedCategoryId);
            $('#upload_category_name').text(selectedCategoryData.name);
            $('#upload_current_count').text(selectedCategoryData.total_count);
            $('#questions_json_file').val('');
            $('#uploadProgress').hide();
            $('#uploadQuestionsModal').show();
        });
        
        // Process upload
        $('#uploadQuestionsForm').on('submit', function(e) {
            e.preventDefault();
            
            var fileInput = $('#questions_json_file')[0];
            if (!fileInput.files.length) {
                alert('Please select a JSON file');
                return;
            }
            
            var file = fileInput.files[0];
            var reader = new FileReader();
            
            reader.onload = function(e) {
                try {
                    var questions = JSON.parse(e.target.result);
                    
                    console.log('Parsed JSON questions:', questions);
                    
                    if (!Array.isArray(questions)) {
                        alert('Invalid JSON format. Expected an array of questions.');
                        return;
                    }
                    
                    var categoryId = $('#upload_category_id').val();
                    var uploadMode = $('input[name="upload_mode"]:checked').val();
                    
                    console.log('Uploading', questions.length, 'questions to category', categoryId, 'mode:', uploadMode);
                    
                    $('#uploadSubmitBtn').prop('disabled', true);
                    $('#uploadProgress').show();
                    $('#uploadStatus').text('Uploading questions...');
                    
                    $.ajax({
                        url: ajaxurl,
                        type: 'POST',
                        data: {
                            action: 'upload_questions_json',
                            category_id: categoryId,
                            questions: JSON.stringify(questions),
                            mode: uploadMode
                        },
                        success: function(response) {
                            console.log('Upload questions response:', response);
                            if (response.success) {
                                $('#uploadProgressBar').css('width', '100%').text('100%');
                                $('#uploadStatus').html('✅ Success! Imported: ' + response.data.imported + ', Failed: ' + response.data.failed);
                                
                                setTimeout(function() {
                                    $('#uploadQuestionsModal').hide();
                                    $('#uploadProgress').hide();
                                    $('#uploadSubmitBtn').prop('disabled', false);
                                    refreshCategoryTree();
                                }, 2000);
                            } else {
                                console.error('Upload failed:', response.data);
                                alert('❌ Error: ' + response.data);
                                $('#uploadSubmitBtn').prop('disabled', false);
                            }
                        },
                        error: function(xhr, status, error) {
                            console.error('AJAX error uploading questions:', error);
                            console.error('Response:', xhr.responseText);
                        }
                    });
                    
                } catch (error) {
                    console.error('Error parsing JSON:', error);
                    alert('Error parsing JSON file: ' + error.message);
                }
            };
            
            reader.readAsText(file);
        });
        
        // Copy questions
        $('#copyQuestionsBtn').on('click', function() {
            if (!selectedCategoryId) return;
            
            $('#copyModalTitle').text('Copy Questions');
            $('#copy_source_category').val(selectedCategoryId);
            $('#copy_action_type').val('copy');
            $('#copy_from_name').text(selectedCategoryData.name);
            $('#copy_question_count').text(selectedCategoryData.total_count);
            $('#copy_target_category').val('');
            $('#copyProgress').hide();
            $('#copyQuestionsModal').show();
        });
        
        // Move questions
        $('#moveQuestionsBtn').on('click', function() {
            if (!selectedCategoryId) return;
            
            $('#copyModalTitle').text('Move Questions');
            $('#copy_source_category').val(selectedCategoryId);
            $('#copy_action_type').val('move');
            $('#copy_from_name').text(selectedCategoryData.name);
            $('#copy_question_count').text(selectedCategoryData.total_count);
            $('#copy_target_category').val('');
            $('#copyProgress').hide();
            $('#copyQuestionsModal').show();
        });
        
        // Process copy/move
        $('#copyQuestionsForm').on('submit', function(e) {
            e.preventDefault();
            
            var sourceId = $('#copy_source_category').val();
            var targetId = $('#copy_target_category').val();
            var actionType = $('#copy_action_type').val();
            
            console.log('Copy/Move operation:', {
                action: actionType,
                source: sourceId,
                target: targetId
            });
            
            if (!targetId) {
                alert('Please select a target category');
                return;
            }
            
            if (sourceId === targetId) {
                alert('Source and target categories cannot be the same');
                return;
            }
            
            if (!confirm('Are you sure you want to ' + actionType + ' all questions?')) {
                return;
            }
            
            $('#copySubmitBtn').prop('disabled', true);
            $('#copyProgress').show();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'copy_category_questions',
                    source_id: sourceId,
                    target_id: targetId,
                    action_type: actionType
                },
                success: function(response) {
                    console.log('Copy/Move response:', response);
                    if (response.success) {
                        $('#copyProgressBar').css('width', '100%').text('100%');
                        $('#copyStatus').html('✅ Success! Copied: ' + response.data.copied + ', Failed: ' + response.data.failed);
                        setTimeout(function() {
                            $('#copyQuestionsModal').hide();
                            $('#copyProgress').hide();
                            $('#copySubmitBtn').prop('disabled', false);
                            refreshCategoryTree();
                        }, 2000);
                    } else {
                        console.error('Copy/Move failed:', response.data);
                        alert('❌ Error: ' + response.data);
                        $('#copySubmitBtn').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error copying/moving questions:', error);
                    console.error('Response:', xhr.responseText);
                }
            });
        });
        
        // View questions
        $('#viewQuestionsBtn').on('click', function() {
            if (!selectedCategoryId) return;
            
            $('#viewModalTitle').text('Questions in: ' + selectedCategoryData.name);
            $('#questionsListContainer').html('<p>Loading questions...</p>');
            $('#viewQuestionsModal').show();
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_category_questions',
                    category_id: selectedCategoryId
                },
                success: function(response) {
                    if (response.success) {
                        displayQuestionsList(response.data);
                    } else {
                        $('#questionsListContainer').html('<p>Error loading questions</p>');
                    }
                }
            });
        });
        
        function displayQuestionsList(questions) {
            if (questions.length === 0) {
                $('#questionsListContainer').html('<p>No questions found in this category</p>');
                return;
            }
            
            var html = '<h3>Total: ' + questions.length + ' questions</h3>';
            questions.forEach(function(q, index) {
                html += `
                    <div class="question-item">
                        <h4>${index + 1}. ${q.title}</h4>
                        <div class="options">
                            <p><strong>A:</strong> ${q.option_a}</p>
                            <p><strong>B:</strong> ${q.option_b}</p>
                            <p><strong>C:</strong> ${q.option_c}</p>
                            <p><strong>D:</strong> ${q.option_d}</p>
                            <p style="color: green;"><strong>Correct Answer:</strong> ${q.correct_answer}</p>
                        </div>
                    </div>
                `;
            });
            
            $('#questionsListContainer').html(html);
        }
        
        // Delete category
        $('#deleteCategoryBtn').on('click', function() {
            if (!selectedCategoryId) return;
            
            console.log('Opening delete modal for category:', selectedCategoryId);
            
            // Get counts for display
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_delete_category_info',
                    term_id: selectedCategoryId
                },
                success: function(response) {
                    console.log('Get delete category info response:', response);
                    if (response.success) {
                        $('#delete_category_id').val(selectedCategoryId);
                        $('#delete_category_name').text(response.data.name);
                        $('#delete_direct_questions').text(response.data.direct_questions);
                        $('#delete_children_count').text(response.data.children_count);
                        $('#delete_questions_checkbox').prop('checked', false);
                        $('#deleteCategoryModal').show();
                    } else {
                        console.error('Failed to get delete info:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error getting delete info:', error);
                    console.error('Response:', xhr.responseText);
                }
            });
        });
        
        // Process delete
        $('#deleteCategoryForm').on('submit', function(e) {
            e.preventDefault();
            
            var categoryId = $('#delete_category_id').val();
            var deleteQuestions = $('#delete_questions_checkbox').is(':checked');
            
            console.log('Deleting category:', categoryId, 'Delete questions:', deleteQuestions);
            
            var confirmMsg = '⚠️ Are you sure you want to delete this category?';
            if (deleteQuestions) {
                confirmMsg += '\n\n🗑️ ALL QUESTIONS in this category will also be PERMANENTLY DELETED!';
            } else {
                confirmMsg += '\n\nQuestions will be kept but will lose their category assignment.';
            }
            
            if (!confirm(confirmMsg)) {
                return;
            }
            
            $('#deleteSubmitBtn').prop('disabled', true);
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'delete_category',
                    term_id: categoryId,
                    delete_questions: deleteQuestions ? 1 : 0
                },
                success: function(response) {
                    console.log('Delete category response:', response);
                    if (response.success) {
                        alert('✅ Category deleted successfully!' + 
                              (deleteQuestions ? '\n🗑️ ' + response.data.questions_deleted + ' questions were deleted.' : ''));
                        $('#deleteCategoryModal').hide();
                        selectedCategoryId = null;
                        selectedCategoryData = null;
                        $('#categoryDetails').hide();
                        $('#noSelection').show();
                        refreshCategoryTree();
                    } else {
                        console.error('Delete failed:', response.data);
                        alert('❌ Error: ' + response.data);
                        $('#deleteSubmitBtn').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error deleting category:', error);
                    console.error('Response:', xhr.responseText);
                }
            });
        });
        
        // Duplicate category
        $('#duplicateCategoryBtn').on('click', function() {
            if (!selectedCategoryId) return;
            
            console.log('Opening duplicate modal for category:', selectedCategoryId);
            
            // Get accurate counts via AJAX
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'get_duplicate_category_info',
                    term_id: selectedCategoryId
                },
                success: function(response) {
                    console.log('Get duplicate category info response:', response);
                    if (response.success) {
                        $('#duplicate_source_category').val(selectedCategoryId);
                        $('#duplicate_from_name').text(response.data.name);
                        $('#duplicate_question_count').text(response.data.total_questions);
                        $('#duplicate_children_count').text(response.data.children_count);
                        $('#duplicate_new_name').val(response.data.name + ' (Copy)');
                        $('#duplicate_parent_category').val(response.data.parent);
                        $('#duplicateProgress').hide();
                        $('#duplicateCategoryModal').show();
                    } else {
                        console.error('Failed to get duplicate info:', response.data);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error getting duplicate info:', error);
                    console.error('Response:', xhr.responseText);
                }
            });
        });
        
        // Process duplication
        $('#duplicateCategoryForm').on('submit', function(e) {
            e.preventDefault();
            
            var sourceId = $('#duplicate_source_category').val();
            var newName = $('#duplicate_new_name').val();
            var parentId = $('#duplicate_parent_category').val();
            var includeChildren = $('#duplicate_include_children').is(':checked');
            var includeQuestions = $('#duplicate_include_questions').is(':checked');
            
            console.log('Starting duplicate operation:', {
                sourceId: sourceId,
                newName: newName,
                parentId: parentId,
                includeChildren: includeChildren,
                includeQuestions: includeQuestions
            });
            
            if (!newName) {
                alert('Please enter a name for the new category');
                return;
            }
            
            var childText = $('#duplicate_children_count').text();
            var questionText = $('#duplicate_question_count').text();
            
            if (!confirm('This will create a copy of "' + selectedCategoryData.name + '" including:\n\n' +
                '- ' + (includeChildren ? childText + ' child categories' : 'No child categories') + '\n' +
                '- ' + (includeQuestions ? questionText + ' questions (including from children)' : 'No questions') + '\n\n' +
                'Continue?')) {
                return;
            }
            
            $('#duplicateSubmitBtn').prop('disabled', true);
            $('#duplicateProgress').show();
            $('#duplicateStatus').text('Starting duplication...');
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'duplicate_category_with_children',
                    source_id: sourceId,
                    new_name: newName,
                    parent_id: parentId,
                    include_children: includeChildren ? 1 : 0,
                    include_questions: includeQuestions ? 1 : 0
                },
                success: function(response) {
                    console.log('Duplicate category response:', response);
                    
                    if (response.success) {
                        console.log('Duplication successful:', {
                            categoriesCreated: response.data.categories_created,
                            questionsCopied: response.data.questions_copied,
                            newCategoryId: response.data.new_category_id
                        });
                        
                        $('#duplicateProgressBar').css('width', '100%').text('100%');
                        
                        var statusHtml = '✅ Success!<br>' +
                            'Categories created: ' + response.data.categories_created + '<br>' +
                            'Questions copied: ' + response.data.questions_copied;
                        
                        if (response.data.warnings && response.data.warnings.length > 0) {
                            console.warn('Duplication warnings:', response.data.warnings);
                            statusHtml += '<br><br>⚠️ <strong>Warnings (' + response.data.warning_count + '):</strong><br>' +
                                '<div style="max-height: 150px; overflow-y: auto; background: #fff3cd; padding: 10px; border-left: 3px solid #ffc107; margin-top: 10px;">' +
                                response.data.warnings.slice(0, 5).join('<br>');
                            
                            if (response.data.warnings.length > 5) {
                                statusHtml += '<br>... and ' + (response.data.warnings.length - 5) + ' more warnings';
                            }
                            
                            statusHtml += '</div>';
                        }
                        
                        $('#duplicateStatus').html(statusHtml);
                        
                        setTimeout(function() {
                            $('#duplicateCategoryModal').hide();
                            $('#duplicateProgress').hide();
                            $('#duplicateSubmitBtn').prop('disabled', false);
                            refreshCategoryTree();
                        }, 3000);
                    } else {
                        console.error('Duplication failed:', response.data);
                        $('#duplicateStatus').html('❌ <strong>Error:</strong><br>' + response.data);
                        $('#duplicateSubmitBtn').prop('disabled', false);
                    }
                },
                error: function(xhr, status, error) {
                    console.error('AJAX error duplicating category:', error);
                    console.error('Response:', xhr.responseText);
                    $('#duplicateStatus').html('❌ <strong>AJAX Error:</strong><br>' + error);
                    $('#duplicateSubmitBtn').prop('disabled', false);
                }
            });
        });
        
        // Close modals
        $('.close-modal').on('click', function() {
            $(this).closest('.category-modal').hide();
        });
        
        $(window).on('click', function(event) {
            if ($(event.target).hasClass('category-modal')) {
                $(event.target).hide();
            }
        });
    });
    </script>
    <?php
}

// Render category tree HTML
function render_category_tree_html($tree, $level = 0) {
    foreach ($tree as $item) {
        $term = $item['term'];
        $has_children = !empty($item['children']);
        $class = $has_children ? 'parent' : 'child';
        ?>
        <div class="tree-item <?php echo $class; ?>" data-term-id="<?php echo $term->term_id; ?>">
            <div class="tree-item-content">
                <?php if ($has_children): ?>
                    <span class="toggle-icon">▶</span>
                <?php else: ?>
                    <span class="toggle-icon" style="visibility: hidden;">▶</span>
                <?php endif; ?>
                <span class="item-name"><?php echo esc_html($term->name); ?></span>
                <span class="item-counts">
                    <?php echo $item['total_count']; ?> total
                    <?php if ($item['custom_count'] > 0): ?>
                        (<?php echo $item['custom_count']; ?> direct)
                    <?php endif; ?>
                </span>
            </div>
            <?php if ($has_children): ?>
                <div class="tree-children">
                    <?php render_category_tree_html($item['children'], $level + 1); ?>
                </div>
            <?php endif; ?>
        </div>
        <?php
    }
}

// AJAX: Get category details
add_action('wp_ajax_get_category_details', 'get_category_details_handler');
function get_category_details_handler() {
    global $wpdb;
    
    if (!isset($_POST['term_id'])) {
        wp_send_json_error('No term ID provided');
        return;
    }
    
    $term_id = intval($_POST['term_id']);
    $term = get_term($term_id, 'question_category');
    
    if (is_wp_error($term)) {
        wp_send_json_error('Category not found');
        return;
    }
    
    $table_name = $wpdb->prefix . 'imported_questions';
    
    $custom_count = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE category = %d",
        $term_id
    ));
    
    wp_send_json_success(array(
        'term_id' => $term->term_id,
        'name' => $term->name,
        'slug' => $term->slug,
        'parent' => $term->parent,
        'total_count' => intval($custom_count)
    ));
    
    wp_die();
}

// AJAX: Get duplicate category info
add_action('wp_ajax_get_duplicate_category_info', 'get_duplicate_category_info_handler');
function get_duplicate_category_info_handler() {
    if (!isset($_POST['term_id'])) {
        wp_send_json_error('No term ID provided');
        return;
    }
    
    $term_id = intval($_POST['term_id']);
    error_log("=== GET DUPLICATE INFO FOR CATEGORY $term_id ===");
    
    $term = get_term($term_id, 'question_category');
    
    if (is_wp_error($term)) {
        error_log("Error getting term: " . $term->get_error_message());
        wp_send_json_error('Category not found');
        return;
    }
    
    error_log("Category name: {$term->name}");
    
    // Count all questions including children
    $total_questions = count_category_questions_recursive($term_id);
    error_log("Total questions (including children): $total_questions");
    
    // Count child categories
    $children_count = count_child_categories_recursive($term_id);
    error_log("Total child categories: $children_count");
    
    $response_data = array(
        'name' => $term->name,
        'parent' => $term->parent,
        'total_questions' => $total_questions,
        'children_count' => $children_count
    );
    
    error_log("Response data: " . json_encode($response_data));
    
    wp_send_json_success($response_data);
    
    wp_die();
}

// AJAX: Get delete category info
add_action('wp_ajax_get_delete_category_info', 'get_delete_category_info_handler');
function get_delete_category_info_handler() {
    global $wpdb;
    
    if (!isset($_POST['term_id'])) {
        wp_send_json_error('No term ID provided');
        return;
    }
    
    $term_id = intval($_POST['term_id']);
    $term = get_term($term_id, 'question_category');
    
    if (is_wp_error($term)) {
        wp_send_json_error('Category not found');
        return;
    }
    
    $table_name = $wpdb->prefix . 'imported_questions';
    
    // Count direct questions in this category
    $direct_questions = $wpdb->get_var($wpdb->prepare(
        "SELECT COUNT(*) FROM $table_name WHERE category = %d",
        $term_id
    ));
    
    // Count child categories
    $children = get_terms(array(
        'taxonomy' => 'question_category',
        'parent' => $term_id,
        'hide_empty' => false
    ));
    
    $children_count = (!is_wp_error($children) && !empty($children)) ? count($children) : 0;
    
    wp_send_json_success(array(
        'name' => $term->name,
        'direct_questions' => intval($direct_questions),
        'children_count' => $children_count
    ));
    
    wp_die();
}

// AJAX: Update category
add_action('wp_ajax_update_category', 'update_category_handler');
function update_category_handler() {
    if (!isset($_POST['term_id']) || !isset($_POST['updates'])) {
        wp_send_json_error('Missing required data');
        return;
    }
    
    $term_id = intval($_POST['term_id']);
    $updates = json_decode(stripslashes($_POST['updates']), true);
    
    error_log("Update category: ID=$term_id, Updates=" . json_encode($updates));
    
    $args = array();
    if (isset($updates['name'])) {
        $args['name'] = sanitize_text_field($updates['name']);
    }
    if (isset($updates['parent'])) {
        $args['parent'] = intval($updates['parent']);
    }
    
    $result = wp_update_term($term_id, 'question_category', $args);
    
    if (is_wp_error($result)) {
        error_log("Update category error: " . $result->get_error_message());
        wp_send_json_error($result->get_error_message());
    } else {
        error_log("Category updated successfully: ID=$term_id");
        wp_send_json_success('Category updated');
    }
    
    wp_die();
}

// AJAX: Save category
add_action('wp_ajax_save_category', 'save_category_handler');
function save_category_handler() {
    if (!isset($_POST['name'])) {
        wp_send_json_error('Category name required');
        return;
    }
    
    $name = sanitize_text_field($_POST['name']);
    $parent = isset($_POST['parent']) ? intval($_POST['parent']) : 0;
    
    error_log("Creating new category: Name=$name, Parent=$parent");
    
    $args = array(
        'parent' => $parent
    );
    
    $result = wp_insert_term($name, 'question_category', $args);
    
    if (is_wp_error($result)) {
        error_log("Create category error: " . $result->get_error_message());
        wp_send_json_error($result->get_error_message());
    } else {
        error_log("Category created successfully: ID=" . $result['term_id'] . ", Name=$name");
        wp_send_json_success('Category created');
    }
    
    wp_die();
}

// AJAX: Upload questions from JSON
add_action('wp_ajax_upload_questions_json', 'upload_questions_json_handler');
function upload_questions_json_handler() {
    global $wpdb;
    
    if (!isset($_POST['category_id']) || !isset($_POST['questions'])) {
        wp_send_json_error('Missing required data');
        return;
    }
    
    $category_id = intval($_POST['category_id']);
    $questions = json_decode(stripslashes($_POST['questions']), true);
    $mode = isset($_POST['mode']) ? $_POST['mode'] : 'append';
    
    $table_name = $wpdb->prefix . 'imported_questions';
    
    // Replace mode: delete existing questions
    if ($mode === 'replace') {
        $result = $wpdb->delete($table_name, array('category' => $category_id), array('%d'));
        if ($wpdb->last_error) {
            error_log('WPDB Delete Error (Replace mode): ' . $wpdb->last_error);
            error_log('SQL Query: ' . $wpdb->last_query);
        } else {
            error_log('Replace mode: Deleted ' . $result . ' existing questions');
        }
    }
    
    $imported = 0;
    $failed = 0;
    
    foreach ($questions as $index => $question) {
        // Validate required fields
        if (!isset($question['title']) || !isset($question['option_a']) || 
            !isset($question['option_b']) || !isset($question['option_c']) || 
            !isset($question['option_d']) || !isset($question['correct_answer'])) {
            $failed++;
            error_log("Question validation failed at index $index: Missing required fields");
            continue;
        }
        
        $post_id = generate_unique_post_id($wpdb, $table_name);
        
        if ($post_id === false) {
            $failed++;
            error_log("Failed to generate unique post_id for question at index $index");
            continue;
        }
        
        $data = array(
            'post_id' => $post_id,
            'category' => $category_id,
            'title' => sanitize_text_field($question['title']),
            'option_a' => sanitize_text_field($question['option_a']),
            'option_b' => sanitize_text_field($question['option_b']),
            'option_c' => sanitize_text_field($question['option_c']),
            'option_d' => sanitize_text_field($question['option_d']),
            'correct_answer' => sanitize_text_field($question['correct_answer']),
            'solution' => isset($question['solution']) ? sanitize_textarea_field($question['solution']) : '',
            'created_at' => current_time('mysql'),
            'updated_at' => current_time('mysql')
        );
        
        $result = $wpdb->insert($table_name, $data);
        
        if ($result) {
            $imported++;
        } else {
            $failed++;
            if ($wpdb->last_error) {
                error_log("WPDB Insert Error at index $index: " . $wpdb->last_error);
                error_log('SQL Query: ' . $wpdb->last_query);
            }
        }
    }
    
    error_log("Upload complete: Imported=$imported, Failed=$failed");
    
    wp_send_json_success(array(
        'imported' => $imported,
        'failed' => $failed
    ));
    
    wp_die();
}

// AJAX: Copy/Move category questions
add_action('wp_ajax_copy_category_questions', 'copy_category_questions_handler');
function copy_category_questions_handler() {
    global $wpdb;
    
    if (!isset($_POST['source_id']) || !isset($_POST['target_id'])) {
        wp_send_json_error('Missing required data');
        return;
    }
    
    $source_id = intval($_POST['source_id']);
    $target_id = intval($_POST['target_id']);
    $action_type = isset($_POST['action_type']) ? $_POST['action_type'] : 'copy';
    
    error_log("Copy/Move operation started: Action=$action_type, Source=$source_id, Target=$target_id");
    
    $table_name = $wpdb->prefix . 'imported_questions';
    $copied = 0;
    $failed = 0;
    
    $questions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE category = %d",
        $source_id
    ), ARRAY_A);
    
    if ($wpdb->last_error) {
        error_log('WPDB Select Error: ' . $wpdb->last_error);
        error_log('SQL Query: ' . $wpdb->last_query);
    }
    
    error_log("Found " . count($questions) . " questions to process");
    
    foreach ($questions as $question) {
        if ($action_type === 'copy') {
            // Duplicate the question with new category
            $original_id = $question['id'];
            unset($question['id']);
            $question['category'] = $target_id;
            $question['created_at'] = current_time('mysql');
            $question['updated_at'] = current_time('mysql');
            
            // Generate new unique post_id
            $question['post_id'] = generate_unique_post_id($wpdb, $table_name);
            
            if ($question['post_id'] === false) {
                $failed++;
                error_log("Failed to generate unique post_id for question ID: $original_id");
                continue;
            }
            
            $result = $wpdb->insert($table_name, $question);
            
            if ($wpdb->last_error) {
                error_log("WPDB Insert Error for question ID $original_id: " . $wpdb->last_error);
                error_log('SQL Query: ' . $wpdb->last_query);
            }
        } else {
            // Move: just update category
            $result = $wpdb->update(
                $table_name,
                array('category' => $target_id),
                array('id' => $question['id']),
                array('%d'),
                array('%d')
            );
            
            if ($wpdb->last_error) {
                error_log("WPDB Update Error for question ID {$question['id']}: " . $wpdb->last_error);
                error_log('SQL Query: ' . $wpdb->last_query);
            }
        }
        
        if ($result) {
            $copied++;
        } else {
            $failed++;
        }
    }
    
    error_log("Copy/Move complete: Success=$copied, Failed=$failed");
    
    wp_send_json_success(array(
        'copied' => $copied,
        'failed' => $failed
    ));
    
    wp_die();
}

// AJAX: Get category questions
add_action('wp_ajax_get_category_questions', 'get_category_questions_handler');
function get_category_questions_handler() {
    global $wpdb;
    
    if (!isset($_POST['category_id'])) {
        wp_send_json_error('No category ID provided');
        return;
    }
    
    $category_id = intval($_POST['category_id']);
    $table_name = $wpdb->prefix . 'imported_questions';
    
    $questions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name WHERE category = %d ORDER BY created_at DESC",
        $category_id
    ), ARRAY_A);
    
    wp_send_json_success($questions);
    wp_die();
}

// AJAX: Duplicate category with children and questions
add_action('wp_ajax_duplicate_category_with_children', 'duplicate_category_with_children_handler');
function duplicate_category_with_children_handler() {
    global $wpdb;
    
    if (!isset($_POST['source_id']) || !isset($_POST['new_name'])) {
        wp_send_json_error('Missing required data: source_id or new_name');
        return;
    }
    
    $source_id = intval($_POST['source_id']);
    $new_name = sanitize_text_field($_POST['new_name']);
    $parent_id = isset($_POST['parent_id']) ? intval($_POST['parent_id']) : 0;
    $include_children = isset($_POST['include_children']) ? boolval($_POST['include_children']) : true;
    $include_questions = isset($_POST['include_questions']) ? boolval($_POST['include_questions']) : true;
    
    error_log("=== DUPLICATE CATEGORY OPERATION STARTED ===");
    error_log("Source ID: $source_id, New Name: $new_name, Parent ID: $parent_id");
    error_log("Include Children: " . ($include_children ? 'Yes' : 'No') . ", Include Questions: " . ($include_questions ? 'Yes' : 'No'));
    
    $table_name = $wpdb->prefix . 'imported_questions';
    $categories_created = 0;
    $questions_copied = 0;
    $category_map = array();
    $errors = array();
    
    // Verify source category exists
    $source_term = get_term($source_id, 'question_category');
    if (is_wp_error($source_term) || !$source_term) {
        error_log("Source category not found: ID $source_id");
        wp_send_json_error('Source category not found');
        return;
    }
    
    error_log("Source category verified: {$source_term->name}");
    
    // Verify parent category exists if specified
    if ($parent_id > 0) {
        $parent_term = get_term($parent_id, 'question_category');
        if (is_wp_error($parent_term) || !$parent_term) {
            error_log("Parent category not found: ID $parent_id");
            wp_send_json_error('Parent category not found');
            return;
        }
        error_log("Parent category verified: {$parent_term->name}");
    }
    
    // Function to recursively duplicate categories
    function duplicate_category_recursive($source_term_id, $new_parent_id, $new_name = null, $is_root = true) {
        global $wpdb, $table_name, $categories_created, $questions_copied, $category_map, $include_children, $include_questions, $errors;
        
        $source_term = get_term($source_term_id, 'question_category');
        if (is_wp_error($source_term)) {
            $errors[] = "Failed to get source term (ID: $source_term_id)";
            error_log("Failed to get source term (ID: $source_term_id)");
            return false;
        }
        
        error_log("Processing category: {$source_term->name} (ID: $source_term_id)");
        
        // Create new category
        $category_name = $is_root && $new_name ? $new_name : $source_term->name;
        
        // Get parent name for appending if needed
        $parent_name = '';
        if ($new_parent_id > 0) {
            $parent_term = get_term($new_parent_id, 'question_category');
            if (!is_wp_error($parent_term) && $parent_term) {
                $parent_name = $parent_term->name;
            }
        }
        
        // Try to create the category
        $new_term = wp_insert_term(
            $category_name,
            'question_category',
            array('parent' => $new_parent_id)
        );
        
        // If term already exists (duplicate slug/name), append parent name
        if (is_wp_error($new_term) && $new_term->get_error_code() === 'term_exists') {
            error_log("Term exists, trying with parent name: $category_name");
            // Try with parent name appended
            if ($parent_name) {
                $category_name_with_parent = $category_name . ' (' . $parent_name . ')';
                $new_term = wp_insert_term(
                    $category_name_with_parent,
                    'question_category',
                    array('parent' => $new_parent_id)
                );
                error_log("Attempted with parent name: $category_name_with_parent");
            }
            
            // If still fails, try with timestamp
            if (is_wp_error($new_term) && $new_term->get_error_code() === 'term_exists') {
                $category_name_with_timestamp = $category_name . ($parent_name ? ' (' . $parent_name . ')' : '') . ' - ' . date('YmdHis');
                $new_term = wp_insert_term(
                    $category_name_with_timestamp,
                    'question_category',
                    array('parent' => $new_parent_id)
                );
                error_log("Attempted with timestamp: $category_name_with_timestamp");
            }
        }
        
        if (is_wp_error($new_term)) {
            $error_msg = "Failed to create category '$category_name': " . $new_term->get_error_message();
            $errors[] = $error_msg;
            error_log($error_msg);
            return false;
        }
        
        $new_term_id = $new_term['term_id'];
        $category_map[$source_term_id] = $new_term_id;
        $categories_created++;
        
        error_log("Created category with ID: $new_term_id");
        
        // Copy questions if enabled
        if ($include_questions) {
            $questions = $wpdb->get_results($wpdb->prepare(
                "SELECT * FROM $table_name WHERE category = %d",
                $source_term_id
            ), ARRAY_A);
            
            if ($wpdb->last_error) {
                error_log("WPDB Select Error for category $source_term_id: " . $wpdb->last_error);
                error_log('SQL Query: ' . $wpdb->last_query);
            }
            
            $question_count = is_array($questions) ? count($questions) : 0;
            error_log("Found $question_count questions in category $source_term_id");
            
            if ($questions && is_array($questions) && count($questions) > 0) {
                foreach ($questions as $question) {
                    $original_id = $question['id'];
                    unset($question['id']);
                    $question['category'] = $new_term_id;
                    $question['created_at'] = current_time('mysql');
                    $question['updated_at'] = current_time('mysql');
                    
                    // Generate unique post_id
                    $new_post_id = generate_unique_post_id($wpdb, $table_name);
                    
                    if ($new_post_id === false) {
                        $errors[] = "Failed to generate unique post_id for question (original ID: $original_id)";
                        error_log("Failed to generate unique post_id for question (original ID: $original_id)");
                        continue;
                    }
                    
                    $question['post_id'] = $new_post_id;
                    
                    $result = $wpdb->insert($table_name, $question);
                    
                    if ($result === false) {
                        $error_msg = "Failed to insert question (original ID: $original_id): " . $wpdb->last_error;
                        $errors[] = $error_msg;
                        error_log($error_msg);
                        error_log('SQL Query: ' . $wpdb->last_query);
                    } else {
                        $questions_copied++;
                        error_log("Copied question ID $original_id -> new post_id $new_post_id");
                    }
                }
            }
        }
        
        // Duplicate children if enabled
        if ($include_children) {
            $children = get_terms(array(
                'taxonomy' => 'question_category',
                'parent' => $source_term_id,
                'hide_empty' => false
            ));
            
            $child_count = (is_array($children) && !is_wp_error($children)) ? count($children) : 0;
            error_log("Found $child_count children for category $source_term_id");
            
            if (!is_wp_error($children) && !empty($children)) {
                foreach ($children as $child) {
                    error_log("Duplicating child: {$child->name}");
                    duplicate_category_recursive($child->term_id, $new_term_id, null, false);
                }
            }
        }
        
        return $new_term_id;
    }
    
    // Start duplication
    $result = duplicate_category_recursive($source_id, $parent_id, $new_name, true);
    
    if ($result) {
        error_log("=== DUPLICATE OPERATION COMPLETED ===");
        error_log("Categories created: $categories_created, Questions copied: $questions_copied");
        error_log("Errors: " . count($errors));
        
        $response_data = array(
            'categories_created' => $categories_created,
            'questions_copied' => $questions_copied,
            'new_category_id' => $result
        );
        
        if (!empty($errors)) {
            $response_data['warnings'] = $errors;
            $response_data['warning_count'] = count($errors);
            error_log("Warnings found: " . implode(', ', $errors));
        }
        
        wp_send_json_success($response_data);
    } else {
        error_log("=== DUPLICATE OPERATION FAILED ===");
        error_log("Errors: " . implode(', ', $errors));
        
        $error_message = 'Failed to duplicate category';
        if (!empty($errors)) {
            $error_message .= ':<br><br>' . implode('<br>', array_slice($errors, 0, 10));
        }
        wp_send_json_error($error_message);
    }
    
    wp_die();
}

// AJAX: Delete category
add_action('wp_ajax_delete_category', 'delete_category_handler');
function delete_category_handler() {
    global $wpdb;
    
    if (!isset($_POST['term_id'])) {
        wp_send_json_error('No term ID provided');
        return;
    }
    
    $term_id = intval($_POST['term_id']);
    $delete_questions = isset($_POST['delete_questions']) ? intval($_POST['delete_questions']) : 0;
    
    $table_name = $wpdb->prefix . 'imported_questions';
    $questions_deleted = 0;
    
    // Delete questions if requested
    if ($delete_questions) {
        $result = $wpdb->delete($table_name, array('category' => $term_id), array('%d'));
        $questions_deleted = $result !== false ? $result : 0;
        
        // Log database errors
        if ($wpdb->last_error) {
            error_log('WPDB Delete Questions Error: ' . $wpdb->last_error);
        }
    }
    
    // Delete the category
    $result = wp_delete_term($term_id, 'question_category');
    
    if (is_wp_error($result)) {
        error_log('WP Delete Term Error: ' . $result->get_error_message());
        wp_send_json_error($result->get_error_message());
    } else {
        wp_send_json_success(array(
            'message' => 'Category deleted',
            'questions_deleted' => $questions_deleted
        ));
    }
    
    wp_die();
}

// AJAX: Get category tree HTML
add_action('wp_ajax_get_category_tree', 'get_category_tree_handler');
function get_category_tree_handler() {
    $category_tree = get_category_tree_with_counts();
    
    ob_start();
    render_category_tree_html($category_tree);
    $html = ob_get_clean();
    
    wp_send_json_success(array(
        'html' => $html
    ));
    
    wp_die();
}

function render_fix_exam_answers_page() {
    ?>
    <div class="wrap">
        <h1>Fix Exam Answers - Verify Correct Answers</h1>
        <p>This tool will check all exam answers and update the <code>is_correct</code> field based on the actual correct answer from the questions table.</p>
        
        <div id="fix-progress-container" style="display: none; margin: 20px 0;">
            <div style="background: #f0f0f0; border-radius: 5px; height: 30px; position: relative; overflow: hidden;">
                <div id="fix-progress-bar" style="background: linear-gradient(90deg, #4CAF50, #45a049); height: 100%; width: 0%; transition: width 0.3s; display: flex; align-items: center; justify-content: center; color: white; font-weight: bold;">
                    <span id="fix-progress-text">0%</span>
                </div>
            </div>
            <p id="fix-progress-info" style="margin-top: 10px; font-size: 14px; color: #666;">Processing...</p>
        </div>
        
        <div id="fix-results" style="margin: 20px 0; padding: 15px; background: #fff; border: 1px solid #ddd; border-radius: 5px; display: none;">
            <h3>Results:</h3>
            <ul id="fix-results-list" style="list-style: none; padding: 0;">
            </ul>
        </div>
        
        <button id="start-fix-btn" class="button button-primary button-large" style="margin-top: 20px;">
            <span class="dashicons dashicons-admin-tools" style="margin-top: 3px;"></span> Start Verification & Fix
        </button>
        
        <button id="cancel-fix-btn" class="button button-secondary" style="margin-top: 20px; display: none;">
            Cancel
        </button>
    </div>
    
    <style>
        #fix-results-list li {
            padding: 8px;
            margin: 5px 0;
            background: #f9f9f9;
            border-left: 4px solid #4CAF50;
            border-radius: 3px;
        }
        
        #fix-results-list li.error {
            border-left-color: #f44336;
            background: #ffebee;
        }
        
        #fix-results-list li.warning {
            border-left-color: #ff9800;
            background: #fff3e0;
        }
        
        .fix-stats {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
            margin: 20px 0;
        }
        
        .fix-stat-card {
            background: white;
            padding: 20px;
            border-radius: 8px;
            box-shadow: 0 2px 5px rgba(0,0,0,0.1);
            text-align: center;
        }
        
        .fix-stat-card h3 {
            margin: 0 0 10px 0;
            font-size: 14px;
            color: #666;
            text-transform: uppercase;
        }
        
        .fix-stat-card .value {
            font-size: 32px;
            font-weight: bold;
            color: #4CAF50;
        }
    </style>
    
    <script>
    jQuery(document).ready(function($) {
        let isProcessing = false;
        let shouldCancel = false;
        
        $('#start-fix-btn').on('click', function() {
            if (isProcessing) return;
            
            if (!confirm('This will check and update incorrect answer records. Do you want to continue?')) {
                return;
            }
            
            isProcessing = true;
            shouldCancel = false;
            
            $('#start-fix-btn').prop('disabled', true);
            $('#cancel-fix-btn').show();
            $('#fix-progress-container').show();
            $('#fix-results').hide();
            $('#fix-results-list').empty();
            
            processFixExamAnswers(0);
        });
        
        $('#cancel-fix-btn').on('click', function() {
            shouldCancel = true;
            $(this).prop('disabled', true).text('Cancelling...');
        });
        
        function processFixExamAnswers(offset) {
            if (shouldCancel) {
                finishProcessing(true);
                return;
            }
            
            $.ajax({
                url: ajaxurl,
                type: 'POST',
                data: {
                    action: 'fix_exam_answers_batch',
                    offset: offset,
                    nonce: '<?php echo wp_create_nonce('fix_exam_answers_nonce'); ?>'
                },
                success: function(response) {
                    if (response.success) {
                        const data = response.data;
                        
                        // Update progress bar
                        const percentage = Math.round((data.processed / data.total) * 100);
                        $('#fix-progress-bar').css('width', percentage + '%');
                        $('#fix-progress-text').text(percentage + '%');
                        $('#fix-progress-info').html(
                            'Processed: <strong>' + data.processed + '</strong> / <strong>' + data.total + '</strong><br>' +
                            'Fixed: <strong>' + data.total_fixed + '</strong> records'
                        );
                        
                        // Add batch results
                        if (data.fixed_count > 0) {
                            $('#fix-results-list').append(
                                '<li>Batch ' + (Math.floor(offset / data.batch_size) + 1) + ': Fixed ' + 
                                data.fixed_count + ' records</li>'
                            );
                            $('#fix-results').show();
                        }
                        
                        // Continue if not done
                        if (!data.done) {
                            processFixExamAnswers(data.next_offset);
                        } else {
                            finishProcessing(false, data);
                        }
                    } else {
                        $('#fix-results-list').append(
                            '<li class="error">Error: ' + response.data.message + '</li>'
                        );
                        $('#fix-results').show();
                        finishProcessing(false);
                    }
                },
                error: function(xhr, status, error) {
                    $('#fix-results-list').append(
                        '<li class="error">AJAX Error: ' + error + '</li>'
                    );
                    $('#fix-results').show();
                    finishProcessing(false);
                }
            });
        }
        
        function finishProcessing(cancelled, finalData) {
            isProcessing = false;
            $('#start-fix-btn').prop('disabled', false);
            $('#cancel-fix-btn').hide().prop('disabled', false).text('Cancel');
            
            if (cancelled) {
                $('#fix-results-list').prepend(
                    '<li class="warning">Process cancelled by user</li>'
                );
                $('#fix-results').show();
            } else if (finalData) {
                // Show final stats
                const statsHtml = '<div class="fix-stats">' +
                    '<div class="fix-stat-card"><h3>Total Checked</h3><div class="value">' + finalData.total + '</div></div>' +
                    '<div class="fix-stat-card"><h3>Total Fixed</h3><div class="value">' + finalData.total_fixed + '</div></div>' +
                    '<div class="fix-stat-card"><h3>Already Correct</h3><div class="value">' + (finalData.total - finalData.total_fixed) + '</div></div>' +
                    '</div>';
                
                $('#fix-results-list').prepend(
                    '<li style="background: #e8f5e9; border-left-color: #4CAF50; font-weight: bold;">' +
                    '✓ Process completed successfully!</li>'
                );
                $('#fix-results').prepend(statsHtml);
                $('#fix-results').show();
            }
        }
    });
    </script>
    <?php
}

add_action('wp_ajax_fix_exam_answers_batch', 'handle_fix_exam_answers_batch');

function handle_fix_exam_answers_batch() {
    // Security check
    check_ajax_referer('fix_exam_answers_nonce', 'nonce');
    
    if (!current_user_can('manage_options')) {
        wp_send_json_error(array('message' => 'Unauthorized access'));
        return;
    }
    
    global $wpdb;
    $answers_table = $wpdb->prefix . 'exam_answers';
    $questions_table = $wpdb->prefix . 'imported_questions';
    
    $batch_size = 100; // Process 100 records at a time
    $offset = isset($_POST['offset']) ? intval($_POST['offset']) : 0;
    
    // Get total count (only on first batch)
    if ($offset === 0) {
        $total = $wpdb->get_var("SELECT COUNT(*) FROM $answers_table");
        set_transient('fix_exam_answers_total', $total, 3600); // Store for 1 hour
        set_transient('fix_exam_answers_fixed', 0, 3600);
    } else {
        $total = get_transient('fix_exam_answers_total');
    }
    
    // Get batch of exam answers
    $exam_answers = $wpdb->get_results($wpdb->prepare(
        "SELECT ea.id, ea.question_id, ea.user_answer, ea.is_correct
         FROM $answers_table ea
         ORDER BY ea.id ASC
         LIMIT %d OFFSET %d",
        $batch_size,
        $offset
    ), ARRAY_A);
    
    if (empty($exam_answers)) {
        // Clean up transients
        delete_transient('fix_exam_answers_total');
        delete_transient('fix_exam_answers_fixed');
        
        wp_send_json_success(array(
            'done' => true,
            'processed' => $total,
            'total' => $total,
            'total_fixed' => 0,
            'fixed_count' => 0,
            'batch_size' => $batch_size
        ));
        return;
    }
    
    // Get all question IDs in this batch
    $question_ids = array_unique(array_column($exam_answers, 'question_id'));
    $placeholders = implode(',', array_fill(0, count($question_ids), '%d'));
    
    // Get correct answers for all questions in one query
    $correct_answers = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, correct_answer FROM $questions_table WHERE ID IN ($placeholders)",
        $question_ids
    ), OBJECT_K);
    
    $fixed_count = 0;
    $updates = array();
    
    // Check each exam answer
    foreach ($exam_answers as $answer) {
        $question_id = $answer['question_id'];
        $user_answer = $answer['user_answer'];
        $current_is_correct = $answer['is_correct'];
        
        // Skip if question not found
        if (!isset($correct_answers[$question_id])) {
            continue;
        }
        
        $correct_answer = $correct_answers[$question_id]->correct_answer;
        $should_be_correct = ($user_answer === $correct_answer) ? '1' : '0';
        
        // Only update if incorrect
        if ($current_is_correct !== $should_be_correct) {
            $updates[] = $answer['id'];
            $wpdb->update(
                $answers_table,
                array('is_correct' => $should_be_correct),
                array('id' => $answer['id']),
                array('%s'),
                array('%d')
            );
            $fixed_count++;
        }
    }
    
    // Update running total
    $total_fixed = get_transient('fix_exam_answers_fixed') + $fixed_count;
    set_transient('fix_exam_answers_fixed', $total_fixed, 3600);
    
    $processed = $offset + count($exam_answers);
    $done = $processed >= $total;
    
    wp_send_json_success(array(
        'done' => $done,
        'processed' => $processed,
        'total' => $total,
        'fixed_count' => $fixed_count,
        'total_fixed' => $total_fixed,
        'next_offset' => $offset + $batch_size,
        'batch_size' => $batch_size
    ));
}
?>