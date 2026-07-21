<?php 
function review_exam_content($atts) {
    $atts = shortcode_atts(array(
        'exam' => 0,
    ), $atts);

    $exam_id = intval($_GET['id']); // Get the exam_id from the URL parameter
    $class_id = intval($_GET['class_id']); 
    $user_id = get_current_user_id();
    $exam_title = get_the_title($exam_id);
    $category_l = intval($_GET['cat']);
    
    $has_free_trial = get_user_meta($user_id, 'free_trial_' . $class_id, true);
    
    // Get questions from database table instead of user meta
    global $wpdb;
    $table_name = $wpdb->prefix . 'exam_answers';
    
    $question_ids = $wpdb->get_col($wpdb->prepare(
        "SELECT DISTINCT question_id FROM $table_name 
         WHERE user_id = %d AND question_category = %d 
         ORDER BY question_id",
        $user_id, $category_l
    ));
    
    if (empty($question_ids)) {
        return 'No questions answered by the user.';
    }

    return display_flashcard_solution($user_id, $exam_id, $question_ids, $exam_title, $class_id, 'review');
}
add_shortcode('review_exam', 'review_exam_content');
function display_flashcard_solution($user_id, $exam_id, $selected_questions, $exam_title, $class_id, $type = 'exam') {
    $cat = intval($_GET['cat']); 
    $has_free_trial = get_user_meta($user_id, 'free_trial_' . $class_id, true);
    
    ob_start();
    ?>
    <div class="flashcard-solution-container">
        <?php wp_nonce_field('flashcard_solution_nonce', 'flashcard_solution_nonce'); ?>
        
        <!-- Image Modal -->
        <div class="image-modal" id="imageModal" style="display: none;">
            <div class="image-modal-overlay" onclick="closeImageModal()"></div>
            <div class="image-modal-content">
                <span class="image-modal-close" onclick="closeImageModal()">&times;</span>
                <img id="modalImage" src="" alt="Full Screen Image">
                <div class="image-modal-controls">
                    <button type="button" class="zoom-btn" onclick="zoomImage('in')">+</button>
                    <button type="button" class="zoom-btn" onclick="zoomImage('out')">-</button>
                    <button type="button" class="zoom-btn" onclick="resetZoom()">Reset</button>
                </div>
            </div>
        </div>
        
        <div class="button-container" style="flex-direction: row;">
            <input type="button" value="←" class="go-back-button" onclick="window.location.href='<?php echo home_url('/' . (isset($cat) && !empty($cat) ? 'activity' : 'profile') . '?class_id=' . $class_id . (isset($cat) && !empty($cat) ? '&cat=' . $cat : '') . (isset($exam_id) && !empty($exam_id) ? '&id=' . $exam_id : '')); ?>';" style="border-radius: 10px;">
            <?php echo dropdown_menu(); ?>
        </div>
        
        <h5><?php echo $exam_title; ?></h5>

        <div class="progress-container">
            <div class="progress-bar" id="progress-bar"></div>
        </div>

        <style>
            .flashcard-container {
                perspective: 1000px;
                width: 100%;
                min-height: 60vh;
                position: relative;
                margin-bottom: 20px;
                overflow: hidden;
            }
            .flashcard {
                width: 100%;
                height: 100%;
                position: absolute;
                transform-style: preserve-3d;
                transition: transform 0.6s, left 0.3s ease-in-out;
                left: 0;
            }
            .flashcard.flip {
                transform: rotateY(180deg);
            }
            .flashcard.slide-left {
                left: -100%;
            }
            .flashcard.slide-right {
                left: 100%;
            }
            .flashcard-front, .flashcard-back {
                position: absolute;
                width: 100%;
                height: 100%;
                backface-visibility: hidden;
                display: flex;
                flex-direction: column;
                justify-content: flex-start; /* Changed from center to flex-start */
                align-items: center;
                background-color: #fff;
                border-radius: 10px;
                padding: 20px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                color: black;
                overflow-y: auto;
            }
            
            /* Ensure question title stays visible at top */
            .flashcard-front h6,
            .flashcard-back h5 {
                flex-shrink: 0; /* Prevent title from shrinking */
                margin-top: 0;
                margin-bottom: 15px;
            }
            .flashcard-back {
                transform: rotateY(180deg);
            }
            
            /* Question Image Styles */
            .question-image-container {
                width: 100%;
                max-width: 500px;
                margin: 15px auto;
                text-align: center;
                position: relative;
                flex-shrink: 1; /* Allow image to shrink if needed */
            }
            
            .question-image {
                max-width: 100%;
                height: auto;
                max-height: 250px; /* Reduced from 300px to ensure question stays visible */
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.15);
                object-fit: contain;
                cursor: pointer;
                transition: transform 0.2s, box-shadow 0.2s;
            }
            
            .question-image:hover {
                transform: scale(1.02);
                box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            }
            
            .question-image-back {
                max-width: 100%;
                height: auto;
                max-height: 200px;
                border-radius: 8px;
                box-shadow: 0 2px 8px rgba(0,0,0,0.15);
                object-fit: contain;
                margin: 10px auto;
                cursor: pointer;
                transition: transform 0.2s, box-shadow 0.2s;
            }
            
            .question-image-back:hover {
                transform: scale(1.02);
                box-shadow: 0 4px 12px rgba(0,0,0,0.25);
            }
            
            .image-zoom-hint {
                position: absolute;
                bottom: 10px;
                right: 10px;
                background: rgba(0, 0, 0, 0.7);
                color: white;
                padding: 5px 10px;
                border-radius: 5px;
                font-size: 11px;
                pointer-events: none;
            }
            
            /* Image Modal Styles */
            .image-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 10000;
                display: flex;
                align-items: center;
                justify-content: center;
                animation: fadeIn 0.3s;
            }
            
            .image-modal-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.95);
                backdrop-filter: blur(5px);
                -webkit-backdrop-filter: blur(5px);
            }
            
            .image-modal-content {
                position: relative;
                max-width: 95%;
                max-height: 95%;
                display: flex;
                flex-direction: column;
                align-items: center;
                justify-content: center;
                z-index: 10001;
            }
            
            .image-modal-close {
                position: absolute;
                top: -40px;
                right: 0;
                color: white;
                font-size: 20px;
                font-weight: bold;
                cursor: pointer;
                background: rgba(255, 255, 255, 0.1);
                width: 50px;
                height: 50px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                transition: background 0.3s;
            }
            
            .image-modal-close:hover {
                background: rgba(255, 255, 255, 0.2);
            }
            
            #modalImage {
                max-width: 100%;
                max-height: 85vh;
                object-fit: contain;
                border-radius: 8px;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.5);
                transition: transform 0.3s ease;
                cursor: move;
            }
            
            .image-modal-controls {
                margin-top: 20px;
                display: flex;
                gap: 10px;
                z-index: 999;
            }
            
            .zoom-btn {
                background: rgba(255, 255, 255, 0.9);
                border: none;
                color: rgba(0, 0, 0, 0.3);
                padding: 10px 20px;
                border-radius: 25px;
                cursor: pointer;
                font-size: 16px;
                font-weight: bold;
                transition: all 0.3s;
                box-shadow: 0 2px 10px rgba(0, 0, 0, 0.3);
            }
            
            .zoom-btn:hover {
                background: white;
                transform: translateY(-2px);
                box-shadow: 0 4px 15px rgba(0, 0, 0, 0.4);
            }
            
            .zoom-btn:active {
                transform: translateY(0);
            }
            
            .options-grid {
                display: grid;
                grid-template-columns: repeat(1, 1fr);
                gap: 10px;
                width: 100%;
                flex-shrink: 0; /* Prevent options from shrinking */
                margin-top: auto; /* Push to bottom if space available */
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
            .button-container {
                display: flex;
                flex-direction: column;
                align-items: center;
                gap: 10px;
            }
            .nav-buttons {
                display: flex;
                justify-content: center;
                gap: 10px;
                width: 100%;
            }
            .nav-buttons input[type="button"] {
                flex: 1;
                max-width: 100%;
            }
            .show-solution {
                margin-top: 10px;
                text-decoration: underline;
                cursor: pointer;
            }
            .loading-spinner {
                border: 4px solid #f3f3f3;
                border-top: 4px solid #3498db;
                border-radius: 50%;
                width: 50px;
                height: 50px;
                animation: spin 2s linear infinite;
                margin: 20px auto;
            }
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            .upgrade-prompt {
                text-align: center;
                padding: 20px;
                background-color: #f8f9fa30;
                border-radius: 10px;
                margin: 20px 0;
                border: 2px solid #007cba;
            }
            .upgrade-button {
                background-color: #007cba;
                color: white;
                padding: 15px 30px;
                border: none;
                border-radius: 10px;
                font-size: 16px;
                cursor: pointer;
                text-decoration: none;
                display: inline-block;
                margin-top: 10px;
            }
            .upgrade-button:hover {
                background-color: #005a87;
            }
            .button-primary {
                min-height: 70px;
            }
            .progress-container {
                margin-bottom: 10px;
            }
            .progress-bar {
                border-radius: 3px;
                height: 10px;
                width: 0;
                background-color: #007cba;
                transition: width 0.5s;
            }
        </style>

        <div class="flashcard-container" id="flashcard-container">
            <div class="loading-spinner" id="loading-spinner"></div>
        </div>

        <?php if ($has_free_trial): ?>
        <div class="upgrade-prompt" id="upgrade-prompt" style="display: none;">
            <h4>Unlock More Questions!</h4>
            <p>You've completed the first 10 questions available in your free trial.</p>
            <p>Upgrade to access all questions and premium features!</p>
            <a href="<?php echo home_url('/checkout?class_id=' . $class_id); ?>" class="upgrade-button">
                Upgrade Now
            </a>
        </div>
        <?php endif; ?>

        <div class="button-container">
            <div class="nav-buttons">
                <input type="button" value="Previous" class="button-primary" id="prev-question" style="border-radius: 10px; display: none;">
                <input type="button" value="Next" class="button-primary" id="next-question" style="border-radius: 10px; display: none;">
            </div>
        </div>
    </div>

    <script>
    // Image modal variables
    var currentZoom = 1;
    var isDragging = false;
    var startX, startY, translateX = 0, translateY = 0;
    
    function openImageModal(imageUrl) {
        var modal = document.getElementById('imageModal');
        var modalImg = document.getElementById('modalImage');
        modal.style.display = 'flex';
        modalImg.src = imageUrl;
        resetZoom();
        document.body.style.overflow = 'hidden';
    }
    
    function closeImageModal() {
        var modal = document.getElementById('imageModal');
        modal.style.display = 'none';
        document.body.style.overflow = 'auto';
        resetZoom();
    }
    
    function zoomImage(direction) {
        var modalImg = document.getElementById('modalImage');
        if (direction === 'in') {
            currentZoom = Math.min(currentZoom + 0.25, 3);
        } else {
            currentZoom = Math.max(currentZoom - 0.25, 0.5);
        }
        modalImg.style.transform = 'scale(' + currentZoom + ') translate(' + translateX + 'px, ' + translateY + 'px)';
    }
    
    function resetZoom() {
        currentZoom = 1;
        translateX = 0;
        translateY = 0;
        var modalImg = document.getElementById('modalImage');
        modalImg.style.transform = 'scale(1) translate(0, 0)';
    }
    
    // Keyboard controls for modal
    document.addEventListener('keydown', function(e) {
        var modal = document.getElementById('imageModal');
        if (modal.style.display === 'flex') {
            if (e.key === 'Escape') {
                closeImageModal();
            } else if (e.key === '+' || e.key === '=') {
                zoomImage('in');
            } else if (e.key === '-' || e.key === '_') {
                zoomImage('out');
            } else if (e.key === '0') {
                resetZoom();
            }
        }
    });
    
    // Drag to pan when zoomed
    var modalImg = document.getElementById('modalImage');
    modalImg.addEventListener('mousedown', function(e) {
        if (currentZoom > 1) {
            isDragging = true;
            startX = e.clientX - translateX;
            startY = e.clientY - translateY;
            modalImg.style.cursor = 'grabbing';
        }
    });
    
    document.addEventListener('mousemove', function(e) {
        if (isDragging) {
            translateX = e.clientX - startX;
            translateY = e.clientY - startY;
            modalImg.style.transform = 'scale(' + currentZoom + ') translate(' + translateX + 'px, ' + translateY + 'px)';
        }
    });
    
    document.addEventListener('mouseup', function() {
        if (isDragging) {
            isDragging = false;
            modalImg.style.cursor = 'move';
        }
    });
    
    jQuery(document).ready(function($) {
        var type = '<?php echo $type; ?>';
        var examId = <?php echo $exam_id; ?>;
        var classId = <?php echo $class_id; ?>;
        var categoryId = <?php echo $cat; ?>;
        var userId = <?php echo $user_id; ?>;
        var hasFreeTrial = <?php echo $has_free_trial ? 'true' : 'false'; ?>;
        var allQuestionIds = <?php echo json_encode(array_values($selected_questions)); ?>;
        var totalQuestions = allQuestionIds.length;
        var currentIndex = 0;
        var currentBatch = 0;
        var questionsPerBatch = 10;
        var loadedQuestions = [];
        var flashcards = [];
        var loadingBatches = {};
        var loadedBatches = {};
        var nextButton = $('#next-question');
        var prevButton = $('#prev-question');
        var progressBar = $('#progress-bar');
        var container = $('#flashcard-container');
        var loadingSpinner = $('#loading-spinner');

        function loadQuestionBatch(batchIndex, callback) {
            if (loadingBatches[batchIndex] || loadedBatches[batchIndex]) {
                if (callback) callback(loadedBatches[batchIndex] ? true : false);
                return;
            }
            
            var startIndex = batchIndex * questionsPerBatch;
            var endIndex = Math.min(startIndex + questionsPerBatch, totalQuestions);
            var batchQuestionIds = allQuestionIds.slice(startIndex, endIndex);
            
            if (hasFreeTrial && batchIndex > 0) {
                if (callback) callback(false);
                return;
            }

            loadingBatches[batchIndex] = true;

            $.ajax({
                url: '<?php echo admin_url('admin-ajax.php'); ?>',
                type: 'POST',
                data: {
                    action: 'load_flashcard_questions',
                    question_ids: batchQuestionIds,
                    nonce: '<?php echo wp_create_nonce('load_flashcard_questions'); ?>'
                },
                success: function(response) {
                    delete loadingBatches[batchIndex];
                    
                    if (response.success) {
                        var questions = response.data;
                        for (var i = 0; i < questions.length; i++) {
                            loadedQuestions[startIndex + i] = questions[i];
                            createFlashcard(startIndex + i, questions[i]);
                        }
                        loadedBatches[batchIndex] = true;
                        if (callback) callback(true);
                    } else {
                        console.error('Failed to load questions');
                        if (callback) callback(false);
                    }
                },
                error: function() {
                    delete loadingBatches[batchIndex];
                    console.error('AJAX error loading questions');
                    if (callback) callback(false);
                }
            });
        }

        function createFlashcard(index, questionData) {
            var imageHtml = '';
            if (questionData.image_url) {
                imageHtml = `
                    <div class="question-image-container">
                        <img src="${questionData.image_url}" 
                             alt="Question Image" 
                             class="question-image"
                             onclick="openImageModal('${questionData.image_url}')">
                        <div class="image-zoom-hint">🔍</div>
                    </div>
                `;
            }
            
            var imageHtmlBack = '';
            if (questionData.image_url) {
                imageHtmlBack = `
                    <div class="question-image-container">
                        <img src="${questionData.image_url}" 
                             alt="Question Image" 
                             class="question-image-back"
                             onclick="openImageModal('${questionData.image_url}')">
                    </div>
                `;
            }
            
            var flashcardHtml = `
                <div class='flashcard' id='flashcard-${index}' style='display: none;'>
                    <div class='flashcard-front'>
                        <h5 style="color:#000;">${questionData.title}</h5>
                        ${imageHtml}
                        <div class="options-grid">
                            <div class='option-container ${questionData.correct_answer === 'A' ? 'correct-answer' : 'wrong-answer'}'>
                                <h5 style="color:#000;margin-bottom:0px;text-align:center;">
                                    ${questionData.options.A}
                                </h5>
                            </div>
                            <div class='option-container ${questionData.correct_answer === 'B' ? 'correct-answer' : 'wrong-answer'}'>
                                <h5 style="color:#000;margin-bottom:0px;text-align:center;">
                                    ${questionData.options.B}
                                </h5>
                            </div>
                            <div class='option-container ${questionData.correct_answer === 'C' ? 'correct-answer' : 'wrong-answer'}'>
                                <h5 style="color:#000;margin-bottom:0px;text-align:center;">
                                    ${questionData.options.C}
                                </h5>
                            </div>
                            <div class='option-container ${questionData.correct_answer === 'D' ? 'correct-answer' : 'wrong-answer'}'>
                                <h5 style="color:#000;margin-bottom:0px;text-align:center;">
                                    ${questionData.options.D}
                                </h5>
                            </div>
                        </div>
                        <div class="show-solution" onclick="flipCard(${index})">
                            ${hasFreeTrial ? 'Show Solution 🔒 (For Paid Users only)' : 'Show Solution'}
                        </div>
                    </div>
                    <div class='flashcard-back'>
                        <h5 style="color:#000;text-align: center;"><strong>Question:</strong></h5>
                        <h5 style="color:#000;text-align: center;">${questionData.title}</h5>
                        ${imageHtmlBack}
                        <h5 style="color:#000;text-align: center;"><strong>Answer:</strong></h5>
                        <h4 style="color:#000;text-align: center;">${questionData.options[questionData.correct_answer]}</h4>
                        ${questionData.solution ? `
                            <h5 style="color:#000;text-align: center;">Explanation:</h5>
                            <p style="color:#000;text-align: center;">${questionData.solution}</p>
                        ` : ''}
                    </div>
                </div>
            `;
            container.append(flashcardHtml);
            flashcards[index] = $('#flashcard-' + index);
        }

        function showFlashcard(index, direction) {
            if (!flashcards[index] && loadedQuestions[index]) {
                createFlashcard(index, loadedQuestions[index]);
            }
            
            if (!flashcards[index]) {
                container.find('.loading-spinner').show();
                
                var checkInterval = setInterval(function() {
                    if (flashcards[index]) {
                        clearInterval(checkInterval);
                        container.find('.loading-spinner').hide();
                        displayFlashcard(index);
                    }
                }, 100);
                
                return;
            }
            
            displayFlashcard(index);
        }
        
        function displayFlashcard(index) {
            flashcards.forEach(function(flashcard) {
                if (flashcard) {
                    flashcard.hide().removeClass('flip');
                }
            });
            
            if (flashcards[index]) {
                flashcards[index].show();
                currentIndex = index;
                updateNavigation();
                updateProgressBar();
                
                var currentBatchIndex = Math.floor(index / questionsPerBatch);
                var positionInBatch = index % questionsPerBatch;
                
                if (!hasFreeTrial && positionInBatch >= (questionsPerBatch - 2)) {
                    var nextBatchIndex = currentBatchIndex + 1;
                    if (nextBatchIndex < Math.ceil(totalQuestions / questionsPerBatch)) {
                        loadQuestionBatch(nextBatchIndex);
                    }
                }
            }
        }

        function updateNavigation() {
            prevButton.toggle(currentIndex > 0);
            
            if (hasFreeTrial) {
                if (currentIndex < 9 && currentIndex < totalQuestions - 1) {
                    nextButton.show();
                    $('#upgrade-prompt').hide();
                } else {
                    nextButton.hide();
                    $('#upgrade-prompt').show();
                }
            } else {
                nextButton.toggle(currentIndex < totalQuestions - 1);
            }
        }

        function updateProgressBar() {
            var maxQuestions = hasFreeTrial ? Math.min(10, totalQuestions) : totalQuestions;
            var progressWidth = (currentIndex + 1) / maxQuestions * 100;
            progressBar.css('width', progressWidth + '%');
        }

        function flipCard(index) {
            if (flashcards[index]) {
                flashcards[index].toggleClass('flip');
            }
        }

        window.flipCard = flipCard;

        nextButton.click(function() {
            if (currentIndex < totalQuestions - 1) {
                showFlashcard(currentIndex + 1, 'left');
            }
        });

        prevButton.click(function() {
            if (currentIndex > 0) {
                showFlashcard(currentIndex - 1, 'right');
            }
        });

        loadQuestionBatch(0, function(success) {
            loadingSpinner.hide();
            if (success && loadedQuestions.length > 0) {
                showFlashcard(0);
                
                if (!hasFreeTrial && totalQuestions > questionsPerBatch) {
                    loadQuestionBatch(1);
                }
            } else {
                container.html('<p>No questions available.</p>');
            }
        });
    });
    </script>
    <?php
    return ob_get_clean();
}

// AJAX handler for loading flashcard questions
function load_flashcard_questions() {
    if (!wp_verify_nonce($_POST['nonce'], 'load_flashcard_questions')) {
        wp_die('Security check failed');
    }
    
    $question_ids = array_map('intval', $_POST['question_ids']);
    
    if (empty($question_ids)) {
        wp_send_json_error('No question IDs provided');
    }
    
    $args = array(
        'post_type' => 'question',
        'post__in' => $question_ids,
        'orderby' => 'rand',
        'posts_per_page' => -1,
    );
    
    $questions = get_posts($args);
    $question_data = array();
    
    foreach ($questions as $question) {
        $question_id = $question->ID;
        $multiple_choice_options = get_post_meta($question_id, 'multiple_choice_options', true);
        $correct_answer = get_post_meta($question_id, 'correct_answer', true);
        $solution = get_post_meta($question_id, 'solution', true);
        
        $question_data[] = array(
            'id' => $question_id,
            'title' => esc_html($question->post_title),
            'options' => $multiple_choice_options,
            'correct_answer' => $correct_answer,
            'image_url' => get_the_post_thumbnail_url($question_id, 'large'),
            'solution' => $solution ? nl2br(esc_html($solution)) : ''
        );
    }
    
    wp_send_json_success($question_data);
}
add_action('wp_ajax_load_flashcard_questions', 'load_flashcard_questions');
add_action('wp_ajax_nopriv_load_flashcard_questions', 'load_flashcard_questions');

?>