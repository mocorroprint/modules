<?php 
function activity_content($atts) {
    $atts = shortcode_atts(array(
        'exam' => 0,
    ), $atts);

    $exam_id = intval($_GET['id']);
    $class_id = intval($_GET['class_id']);
    $category = get_term_by('id', intval($_GET['cat']), 'question_category');
    
    // Validate category
    if (!$category) {
        return '<div class="no-questions-message" style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 10px; margin: 20px 0;">
                    <h3 style="color: #dc3545; margin-bottom: 15px;">⚠️ Invalid Category</h3>
                    <p style="color: #6c757d;">The category you are trying to access does not exist.</p>
                    <a href="' . home_url('/profile?class_id=' . $class_id) . '" class="button-primary" style="margin-top: 20px; display: inline-block;">← Go Back</a>
                </div>';
    }
    
    $user_id = get_current_user_id();
    $exam_title = $category->name;
    
    if (empty(intval($_GET['take']))) {
        $type = 'activity';
        return display_activity_statistics_ui($category, $user_id, $class_id, $type);
    }
    
    // Get number of questions from class meta
    $number_of_questions = get_post_meta($class_id, '_exam_num_questions', true);
    $number_of_questions = $number_of_questions ? intval($number_of_questions) : 10;

    $selected_questions = get_questions($category->term_id, $number_of_questions);

    // Validate if questions were returned
    if (empty($selected_questions)) {
        return '<div class="no-questions-message" style="text-align: center; padding: 40px; background: #f8f9fa; border-radius: 10px; margin: 20px 0;">
                    <h3 style="color: #dc3545; margin-bottom: 15px;">📝 No Questions Available</h3>
                    <p style="color: #6c757d; margin-bottom: 10px;">There are currently no questions available for <strong>' . esc_html($exam_title) . '</strong>.</p>
                    <p style="color: #6c757d;">Please check back later or contact your instructor.</p>
                    <a href="' . home_url('/profile?class_id=' . $class_id . '&cat=' . $category->term_id) . '" class="button-primary" style="margin-top: 20px; display: inline-block; border-radius: 10px; padding: 15px 30px;">← Go Back</a>
                </div>';
    }
    
    // Extract IDs for the display function
    $selected_question_ids = wp_list_pluck($selected_questions, 'ID');
    return display_flashcard_exam_ui($exam_id, $selected_questions, $exam_title, $class_id, 'exercises');
}
add_shortcode('activity', 'activity_content');
//----
function display_flashcard_exam_ui($exam_id, $selected_questions, $exam_title, $class_id, $type = 'exam') {
    $cat = intval($_GET['cat']); 

    // No need to query posts anymore - we have the data directly
    $random_questions = $selected_questions;
    
    // Shuffle if needed
    shuffle($random_questions);
    
    $timer = count($selected_questions);
    ob_start();
    lf_loading_screen();
    ?>
    <form method="post" action="" id="flashcard-exam-form">
        <?php wp_nonce_field('flashcard_exam_submission_nonce', 'flashcard_exam_submission_nonce'); ?>
     
        
        <!-- Exam Overlay -->
        <div class="exam-overlay" id="exam-overlay" style="display: none;">
            <div class="overlay-content">
                <div class="overlay-spinner"></div>
                <h3 class="overlay-message" id="overlay-message">Submitting answers...</h3>
            </div>
        </div>
        
        <!-- Success Modal -->
        <div class="success-modal" id="success-modal" style="display: none;">
            <div class="success-modal-overlay"></div>
            <div class="success-modal-content">
                <div class="success-icon">✓</div>
                <h3 class="success-title">Exam Submitted Successfully!</h3>
                <p class="success-message">Your answers have been saved.</p>
                
                <!-- Results Table -->
                <div class="results-table-container" id="results-container">
                    <h4 style="color: #333; margin: 20px 0 10px 0;">Your Results</h4>
                    <div class="results-stats" id="results-stats"></div>
                    <div class="results-table-wrapper">
                        <table class="results-table" id="results-table">
                            <thead>
                                <tr>
                                    <th>Question</th>
                                    <th>Your Answer</th>
                                    <th>Correct Answer</th>
                                    <th>Status</th>
                                </tr>
                            </thead>
                            <tbody id="results-tbody">
                            </tbody>
                        </table>
                    </div>
                </div>
                
                <button type="button" class="success-btn" id="back-btn">Back to Profile</button>
            </div>
        </div>
        
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
            <a href="<?php echo home_url('/' . (isset($cat) && !empty($cat) ? 'activity' : 'profile') . '?class_id=' . $class_id . (isset($cat) && !empty($cat) ? '&cat=' . $cat : '') . (isset($exam_id) && !empty($exam_id) ? '&id=' . $exam_id : '')); ?>" 
               style="border-radius: 10px; display: inline-block;  text-align: center;">
                ← Go Back
            </a>
        </div>
        <?php if ($type === 'exercises' || $type === 'exam'): ?>
            <div class="timer-container">
                <div class="countdown" id="countdown" style="text-align:center;">0:00</div>
                <div class="timer-bar" id="timer-progress-bar"></div>
            </div>
        <?php endif; ?>
        
        <h4 style="margin-bottom:0px"><?php echo $exam_title; ?></h4>

        <div class="progress-container" >
            <div class="progress-bar" id="progress-bar"></div>
        </div>

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
                transition: transform 0.6s, left 0.3s, opacity 0.3s ease-in-out;
                left: 0;
                opacity: 1;
            }
            
            .fade-in {
                opacity: 0;
                animation: fadeIn 0.3s forwards;
            }
            
            .fade-out {
                opacity: 1;
                animation: fadeOut 0.3s forwards;
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
                justify-content: center;
                align-items: center;
                background-color: #fff;
                border-radius: 10px;
                padding: 10px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                color: black;
                overflow-y: auto;
            }
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
                z-index:999;
            }
            
            .zoom-btn {
                background: rgba(255, 255, 255, 0.9);
                border: none;
                color:rgba(0, 0, 0, 0.3);
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
               /* margin-top: auto;  Push to bottom if space available */
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
            #submit-flashcard-exam {
                width: 100%;
                max-width: 100%;
            }
            .show-solution {
                margin-top: 10px;
                text-decoration: underline;
                cursor: pointer;
            }
            
            .progress-container {
                margin-bottom: 10px;
            }
            .progress-bar {
                border-radius: 3px;
                height: 10px;
                width: 0;
                background-color: #ccc;
                transition: width 0.5s;
            }

            .timer-container {
                margin-bottom: 0px;
            }

            .timer-bar {
                height: 10px;
                background-color: #ccc;
                transition: width 1s;
                width: 100%;
                border-radius: 3px;
            }

            .countdown {
                text-align: right;
                font-size: 18px;
            }
            .button-primary{
                min-height:70px;
            }
                
            /* Overlay Styles */
            .exam-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.7);
                backdrop-filter: blur(5px);
                -webkit-backdrop-filter: blur(5px);
                z-index: 9999;
                display: flex;
                justify-content: center;
                align-items: center;
                transition: opacity 0.3s ease-in-out;
            }
            
            .overlay-content {
                background-color: white;
                padding: 40px;
                border-radius: 15px;
                text-align: center;
                box-shadow: 0 10px 25px rgba(0, 0, 0, 0.3);
                max-width: 400px;
                width: 90%;
            }
            
            .overlay-message {
                color: #333;
                margin: 20px 0 0 0;
                font-size: 20px;
                font-weight: bold;
            }
            
            .overlay-spinner {
                width: 50px;
                height: 50px;
                border: 4px solid #f3f3f3;
                border-top: 4px solid #3498db;
                border-radius: 50%;
                animation: spin 1s linear infinite;
                margin: 0 auto 20px;
            }
            
            /* Success Modal Styles */
            .success-modal {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                z-index: 10000;
                display: flex;
                justify-content: center;
                align-items: center;
                animation: fadeIn 0.3s;
            }
            
            .success-modal-overlay {
                position: absolute;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background-color: rgba(0, 0, 0, 0.8);
                backdrop-filter: blur(5px);
                -webkit-backdrop-filter: blur(5px);
            }
            
            .success-modal-content {
                position: relative;
                background-color: white;
                padding: 30px;
                border-radius: 20px;
                text-align: center;
                box-shadow: 0 10px 40px rgba(0, 0, 0, 0.3);
                max-width: 900px; /* Increased from 450px */
                width: 90%;
                z-index: 10001;
                animation: slideUp 0.5s ease-out;
                max-height: 90vh;
                overflow-y: auto;
            }
            
            .results-table-container {
                margin: 20px 0;
                text-align: left;
            }
            
            .results-stats {
                background: linear-gradient(135deg, #4CAF50, #45a049);
                color: white;
                padding: 15px;
                border-radius: 10px;
                margin-bottom: 15px;
                font-size: 16px;
                font-weight: bold;
            }
            
            .results-table-wrapper {
                max-height: 400px;
                overflow-y: auto;
                border-radius: 10px;
                box-shadow: 0 2px 10px rgba(0,0,0,0.1);
            }
            
            .results-table {
                width: 100%;
                border-collapse: collapse;
                background: white;
            }
            
            .results-table thead {
                position: sticky;
                top: 0;
                background: linear-gradient(135deg, #58c2f7, #4facfe);
                z-index: 10;
            }
            
            .results-table th {
                padding: 12px 8px;
                text-align: left;
                color: white;
                font-weight: 600;
                font-size: 14px;
                border-bottom: 2px solid #58c2f7;
            }
            
            .results-table td {
                padding: 12px 8px;
                border-bottom: 1px solid #e0e0e0;
                color: #333;
                font-size: 13px;
            }
            
            .results-table tbody tr:hover {
                background-color: #f5f5f5;
            }
            
            .results-table tbody tr:last-child td {
                border-bottom: none;
            }
            
            .status-correct {
                color: #4CAF50;
                font-weight: bold;
            }
            
            .status-wrong {
                color: #f44336;
                font-weight: bold;
            }
            
            .question-text {
                max-width: 300px;
                overflow: hidden;
                text-overflow: ellipsis;
                white-space: nowrap;
            }
            
            @media (max-width: 768px) {
                .success-modal-content {
                    padding: 20px;
                    max-width: 95%;
                }
                
                .results-table th,
                .results-table td {
                    padding: 8px 4px;
                    font-size: 12px;
                }
                
                .question-text {
                    max-width: 150px;
                }
                
                .results-table-wrapper {
                    max-height: 300px;
                }
            }
            
            .success-icon {
                width: 80px;
                height: 80px;
                background: linear-gradient(135deg, #4CAF50, #45a049);
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                margin: 0 auto 25px;
                font-size: 48px;
                color: white;
                font-weight: bold;
                animation: scaleIn 0.5s ease-out 0.2s both;
                box-shadow: 0 5px 20px rgba(76, 175, 80, 0.4);
            }
            
            .success-title {
                color: #333;
                margin: 0 0 15px 0;
                font-size: 24px;
                font-weight: bold;
            }
            
            .success-message {
                color: #666;
                margin: 0 0 30px 0;
                font-size: 16px;
                line-height: 1.5;
            }
            
            .success-btn {
                background: linear-gradient(135deg, #4CAF50, #45a049);
                color: white;
                border: none;
                padding: 15px 40px;
                border-radius: 25px;
                font-size: 16px;
                font-weight: bold;
                cursor: pointer;
                transition: all 0.3s;
                box-shadow: 0 4px 15px rgba(76, 175, 80, 0.3);
            }
            
            .success-btn:hover {
                transform: translateY(-2px);
                box-shadow: 0 6px 20px rgba(76, 175, 80, 0.4);
            }
            
            .success-btn:active {
                transform: translateY(0);
            }
            
            @keyframes spin {
                0% { transform: rotate(0deg); }
                100% { transform: rotate(360deg); }
            }
            
            @keyframes fadeIn {
                from { opacity: 0; }
                to { opacity: 1; }
            }
            
            @keyframes fadeOut {
                from { opacity: 1; }
                to { opacity: 0; }
            }
            
            @keyframes slideUp {
                from { 
                    transform: translateY(50px);
                    opacity: 0;
                }
                to { 
                    transform: translateY(0);
                    opacity: 1;
                }
            }
            
            @keyframes scaleIn {
                from { 
                    transform: scale(0);
                }
                to { 
                    transform: scale(1);
                }
            }
        </style>

         <div class="flashcard-container">
            <?php foreach ($random_questions as $index => $question): 
                $question_id = $question['ID'];
                $question_title = esc_html($question['title']);
                
                // Build options array
                $multiple_choice_options = array(
                    'A' => $question['option_a'],
                    'B' => $question['option_b'],
                    'C' => $question['option_c'],
                    'D' => $question['option_d']
                );
                
                $correct_answer = $question['correct_answer'];
                $question_solution = ($type === 'exercises' || $type === 'review') ? $question['solution'] : '';
                $image_url = $question['image_url'];
            ?>
                <div class='flashcard' id='flashcard-<?php echo $index; ?>'>
                    <div class='flashcard-front'>
                        <h6 style="color:#000;"> <?php echo $question_title; ?></h6>
                        
                        <?php if (!empty($image_url)): ?>
                            <div class="question-image-container">
                                <img src="<?php echo esc_url($image_url); ?>" 
                                     alt="Question Image" 
                                     class="question-image"
                                     onclick="openImageModal('<?php echo esc_url($image_url); ?>')">
                                <div class="image-zoom-hint">🔍</div>
                            </div>
                        <?php endif; ?>
                        
                        <div class="options-grid">
                            <?php 
                            $shuffled_letters = ['A', 'B', 'C', 'D'];
                            shuffle($shuffled_letters);
                            foreach ($shuffled_letters as $option):
                                $optionId = "option-$index-$option";
                                $isCorrect = ($option === $correct_answer);
                            ?>
                                <div class='option-container' 
                                     onclick="selectOption('<?php echo $optionId; ?>', '<?php echo $correct_answer; ?>', <?php echo $index; ?>)" 
                                     id='option-container-<?php echo $optionId; ?>'>
                                    <input hidden type='radio' name='user_answers[<?php echo $question_id; ?>]' value='<?php echo $option; ?>' id='<?php echo $optionId; ?>'>
                                    <div style="color:#000;margin-bottom:0px;text-align:center;"for='<?php echo $optionId; ?>'>
                                        <?php echo $multiple_choice_options[$option]; ?>
                                    </div>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div class="show-solution" id="show-solution-<?php echo $index; ?>" onclick="flipCard(<?php echo $index; ?>)">Show Solution</div>
                    </div>
                    <div class='flashcard-back'>
                        <h5 style="color:#000;text-align: center;"><strong>Question:</strong></h5>
                        <h5 style="color:#000;text-align: center;"> <?php echo $question_title; ?></h5>
                        
                        <?php if (!empty($image_url)): ?>
                            <div class="question-image-container">
                                <img src="<?php echo esc_url($image_url); ?>" 
                                     alt="Question Image" 
                                     class="question-image-back"
                                     onclick="openImageModal('<?php echo esc_url($image_url); ?>')">
                            </div>
                        <?php endif; ?>
                        
                        <h5 style="color:#000;text-align: center;"><strong>Answer:</strong></h5>
                        <h4 style="color:#000;text-align: center;"> <?php echo $multiple_choice_options[$correct_answer]; ?> </h4>
                        <?php 
                        if (!empty($question_solution)) {
                        ?>
                        <h5 style="color:#000;text-align: center;">Explanation:</h5>
                        <p style="color:#000;text-align: center;"><?php echo nl2br(esc_html($question_solution)); ?></p>
                        <?php
                        }
                        ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="button-container">
            <div class="nav-buttons">
                <input type="button" value="Previous" class="button-primary" id="prev-question" style="border-radius: 10px; display:none;">
                <input type="button" value="Next" class="button-primary" id="next-question" style="border-radius: 10px;">
            </div>
            <?php if ($type !== 'review'): ?>
                <input type="button" name="submit_flashcard_exam" value="Submit" class="button-primary" id="submit-flashcard-exam" style="border-radius: 10px;display:none;">
            <?php else: ?>
                <input type="button" value="Go Back" class="button-primary" onclick="window.location.href='<?php echo home_url('/profile?class_id=' . $class_id); ?>';" style="border-radius: 10px;" id="go-back-button">
            <?php endif; ?>
        </div>
        <div id="message" style="margin-top: 10px; display:none; text-align:center;">Please select an answer!</div>
    </form>

    <script>
var type = '<?php echo $type; ?>';
var totalQuestions = <?php echo count($random_questions); ?>;
var currentIndex = 0;
var flashcards = document.querySelectorAll('.flashcard');
var solution = document.querySelectorAll('show-solution');
var nextButton = document.getElementById('next-question');
var prevButton = document.getElementById('prev-question');
var submitButton = document.getElementById('submit-flashcard-exam');
var goBackButton = document.getElementById('go-back-button');
var progressBar = document.getElementById('progress-bar');
var answeredQuestions = new Array(totalQuestions).fill(false);
var overlay = document.getElementById('exam-overlay');
var overlayMessage = document.getElementById('overlay-message');
var isAutoAdvancing = false; // Flag to prevent double navigation

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

function showOverlay(message) {
    overlayMessage.textContent = message || 'Submitting answers...';
    overlay.style.display = 'flex';
    overlay.style.opacity = '0';
    setTimeout(function() {
        overlay.style.opacity = '1';
    }, 10);
    
    // Disable all interactions
    document.body.style.pointerEvents = 'none';
    overlay.style.pointerEvents = 'auto';
}

function hideOverlay() {
    overlay.style.opacity = '0';
    setTimeout(function() {
        overlay.style.display = 'none';
        document.body.style.pointerEvents = 'auto';
    }, 300);
}

function showSuccessModal(results) {
    var successModal = document.getElementById('success-modal');
    successModal.style.display = 'flex';
    document.body.style.overflow = 'hidden';
    
    // Populate results table
    var tbody = document.getElementById('results-tbody');
    tbody.innerHTML = '';
    
    var correctCount = 0;
    var totalCount = results.length;
    
    results.forEach(function(result, index) {
        if (result.is_correct) correctCount++;
        
        var row = document.createElement('tr');
        row.innerHTML = 
            '<td class="question-text" title="' + result.question + '">' + (index + 1) + '. ' + result.question + '</td>' +
            '<td>' + result.user_answer + '</td>' +
            '<td>' + result.correct_answer + '</td>' +
            '<td class="' + (result.is_correct ? 'status-correct' : 'status-wrong') + '">' + 
                (result.is_correct ? '✓ Correct' : '✗ Wrong') + 
            '</td>';
        tbody.appendChild(row);
    });
    
    // Update stats
    var statsDiv = document.getElementById('results-stats');
    var percentage = ((correctCount / totalCount) * 100).toFixed(1);
    statsDiv.innerHTML = 'Score: ' + correctCount + '/' + totalCount + ' (' + percentage + '%)';
    
    // Setup back button
    var backBtn = document.getElementById('back-btn');
    backBtn.onclick = function() {
        var classId = <?php echo $class_id; ?>;
        var cat = <?php echo isset($cat) ? $cat : 0; ?>;
        
        var url = '<?php echo home_url('/profile'); ?>' + '?class_id=' + classId;
        
        if (cat) {
            url = '<?php echo home_url('/activity'); ?>' + '?class_id=' + classId + '&cat=' + cat;
        }
        
        window.location.href = url;
    };
}

function showFlashcard(index, direction) {
    flashcards.forEach(function(flashcard) {
        flashcard.style.display = 'none';
        flashcard.classList.remove('flip');
    });
    
    var currentFlashcard = flashcards[currentIndex];
    var targetFlashcard = flashcards[index];
    
    if (direction === 'left') {
        currentFlashcard.classList.add('slide-left');
        targetFlashcard.classList.add('slide-right');
        
        targetFlashcard.classList.add('fade-out');
        currentFlashcard.classList.add('fade-in');
        setTimeout(function() {
            targetFlashcard.classList.remove('fade-out');
            currentFlashcard.classList.remove('fade-in');
        }, 500);
    } else if (direction === 'right') {
        currentFlashcard.classList.add('slide-right');
        targetFlashcard.classList.add('slide-left');
        
        targetFlashcard.classList.add('fade-in');
        currentFlashcard.classList.add('fade-out');
        setTimeout(function() {
            targetFlashcard.classList.remove('fade-in');
            currentFlashcard.classList.remove('fade-out');
        }, 500);
    }
    
    currentFlashcard.style.display = 'block';
    targetFlashcard.style.display = 'block';
    
    setTimeout(function() {
        currentFlashcard.classList.remove('slide-left', 'slide-right');
        targetFlashcard.classList.remove('slide-left', 'slide-right');
        currentFlashcard.style.display = 'none';
        targetFlashcard.style.display = 'block';
        currentIndex = index;
        updateNavigation();
        isAutoAdvancing = false; // Reset flag after transition completes
    }, 300);

    var progressWidth = (index + 1) / totalQuestions * 100;
    progressBar.style.width = progressWidth + '%';
    
    if (answeredQuestions[index]) {
        showAnswerStatus(index);
    }
}

function updateNavigation() {
    prevButton.style.display = currentIndex > 0 ? 'inline-block' : 'none';
    nextButton.style.display = currentIndex < totalQuestions - 1 ? 'inline-block' : 'none';
    submitButton.style.display = currentIndex < totalQuestions - 1 ? 'none' : 'inline-block';
    if (type === 'review') {
        goBackButton.style.display = currentIndex === totalQuestions - 1 ? 'inline-block' : 'none';
    }
}

var autoAdvanceTimeout = null; // Add this variable at the top with other variables

// Update selectOption function to store the timeout:
function selectOption(optionId, correctAnswer, flashcardIndex) {
    var radioElement = document.getElementById(optionId);
    if (radioElement && !answeredQuestions[flashcardIndex]) {
        radioElement.checked = true;
        answeredQuestions[flashcardIndex] = true;
        showAnswerStatus(flashcardIndex, correctAnswer);
        //updateShowSolutionButton(flashcardIndex);
        
        // Set flag to prevent manual navigation during auto-advance
        isAutoAdvancing = true;
        
        // Store the timeout so it can be cleared if needed
        autoAdvanceTimeout = setTimeout(function() {
            if (currentIndex < totalQuestions - 1 && isAutoAdvancing) {
                showFlashcard(currentIndex + 1, 'left');
            } else {
                isAutoAdvancing = false; // Reset if we're on last question
            }
        }, 800);
    }
}

function showAnswerStatus(flashcardIndex, correctAnswer) {
    var flashcard = document.getElementById('flashcard-' + flashcardIndex);
    var allOptions = flashcard.querySelectorAll('.option-container');
    var selectedOption = flashcard.querySelector('input[type="radio"]:checked');
    
    if (selectedOption) {
        var isCorrect = selectedOption.value === correctAnswer;
        
        allOptions.forEach(function(option) {
            option.classList.remove('selected', 'correct-answer', 'wrong-answer');
            if (option.querySelector('input') === selectedOption) {
                option.classList.add('selected');
                option.classList.add(isCorrect ? 'correct-answer' : 'wrong-answer');
            } else if (option.querySelector('input').value === correctAnswer) {
                option.classList.add('correct-answer');
            }
            option.style.pointerEvents = 'none';
        });
    }
}

function flipCard(index) {
    // Check if solution is available before flipping
    var solution = flashcards[index].querySelector('.flashcard-back').textContent.trim();
    if (solution !== 'No Solution available') {
        flashcards[index].classList.toggle('flip');
    }
}

// Update nextButton listener to clear the timeout:
nextButton.addEventListener('click', function() {
    // Cancel auto-advance timeout and navigate manually
    if (autoAdvanceTimeout) {
        clearTimeout(autoAdvanceTimeout);
        autoAdvanceTimeout = null;
    }
    isAutoAdvancing = false;
    showFlashcard(currentIndex + 1, 'left');
});

// Update prevButton listener to clear the timeout:
prevButton.addEventListener('click', function() {
    // Cancel auto-advance timeout when going back
    if (autoAdvanceTimeout) {
        clearTimeout(autoAdvanceTimeout);
        autoAdvanceTimeout = null;
    }
    isAutoAdvancing = false;
    showFlashcard(currentIndex - 1, 'right');
});

function updateShowSolutionButton(index) {
    var showSolutionButton = document.getElementById('show-solution-' + index);
    if (showSolutionButton) {
        showSolutionButton.style.display = answeredQuestions[index] ? 'block' : 'none';
    }
}

// Initially hide all "Show Solution" buttons
flashcards.forEach(function(flashcard, index) {
    var showSolutionButton = document.getElementById('show-solution-' + index);
    if (showSolutionButton) {
        showSolutionButton.style.display = 'none';
    }
});

// Update AJAX Submission Handler
// Update AJAX Submission Handler
submitButton.addEventListener('click', function(e) {
    e.preventDefault();
    
    showOverlay('Submitting answers...');
    
    var form = document.getElementById('flashcard-exam-form');
    var formData = new FormData(form);
    formData.append('action', 'submit_flashcard_exam_ajax');
    formData.append('exam_id', <?php echo $exam_id; ?>);
    formData.append('class_id', <?php echo $class_id; ?>);
    formData.append('cat', <?php echo isset($cat) ? $cat : 0; ?>);
    
    // Collect all question IDs from the exam
    var allQuestionIds = [];
    flashcards.forEach(function(flashcard) {
        var radioInputs = flashcard.querySelectorAll('input[type="radio"]');
        if (radioInputs.length > 0) {
            var name = radioInputs[0].name;
            var questionId = name.match(/\[(\d+)\]/);
            if (questionId) {
                allQuestionIds.push(questionId[1]);
            }
        }
    });
    formData.append('all_question_ids', JSON.stringify(allQuestionIds));
    
    // Use fetch API for AJAX request
    fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
        method: 'POST',
        body: formData
    })
    .then(response => response.json())
    .then(data => {
        hideOverlay();
        
        if (data.success) {
            showSuccessModal(data.data.results);
        } else {
            alert('Error: ' + (data.data.message || 'Failed to submit exam'));
        }
    })
    .catch(error => {
        hideOverlay();
        console.error('Error:', error);
        alert('An error occurred while submitting the exam. Please try again.');
    });
});

<?php if ($type === 'exercises' || $type === 'exam'): ?>
// Timer functionality
var timerDuration = (type === 'exercises' ? 30 : 30) * totalQuestions;
var timeLeft = timerDuration;
var timerBar = document.getElementById('timer-progress-bar');
var countdownElement = document.getElementById('countdown');

function updateTimer() {
    var percentage = (timeLeft / timerDuration) * 100;
    timerBar.style.width = percentage + '%';
    countdownElement.textContent = formatTime(timeLeft);
    timeLeft--;
    if (timeLeft < 0) {
        clearInterval(timerInterval);
        if (type !== 'review') {
            showOverlay('Time\'s up! Submitting answers...');
            submitButton.click();
        }
    }
}

function formatTime(seconds) {
    var minutes = Math.floor(seconds / 60);
    var remainingSeconds = seconds % 60;
    return minutes + ':' + (remainingSeconds < 10 ? '0' : '') + remainingSeconds;
}

var timerInterval = setInterval(updateTimer, 1000);      
<?php endif; ?>

// Initialize
showFlashcard(currentIndex);
</script>
    <?php
    return ob_get_clean();
}

// AJAX Handler for Flashcard Exam Submission
add_action('wp_ajax_submit_flashcard_exam_ajax', 'handle_flashcard_exam_ajax_submission');
add_action('wp_ajax_nopriv_submit_flashcard_exam_ajax', 'handle_flashcard_exam_ajax_submission');

function handle_flashcard_exam_ajax_submission() {
    // Verify nonce
    if (!isset($_POST['flashcard_exam_submission_nonce']) || 
        !wp_verify_nonce($_POST['flashcard_exam_submission_nonce'], 'flashcard_exam_submission_nonce')) {
        wp_send_json_error(array('message' => 'Security verification failed.'));
        return;
    }
    
    // Check if user is logged in
    if (!is_user_logged_in()) {
        wp_send_json_error(array('message' => 'You must be logged in to submit.'));
        return;
    }
    
    // Generate a random 5-character session ID
    $session_id = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 5);
    $user_id = get_current_user_id();
    $user_answers = isset($_POST['user_answers']) ? $_POST['user_answers'] : array();
    $exam_id = intval($_POST['exam_id']);
    $class_id = intval($_POST['class_id']);
    $cat = intval($_POST['cat']);
    $exam_name = get_the_title($exam_id);
    
    // Get all question IDs from the exam
    $all_question_ids = isset($_POST['all_question_ids']) ? json_decode(stripslashes($_POST['all_question_ids']), true) : array();
    
    // If no question IDs provided, try to get from user_answers
    if (empty($all_question_ids)) {
        $all_question_ids = array_keys($user_answers);
    }
    
    if (empty($all_question_ids)) {
        wp_send_json_error(array('message' => 'No questions found in exam.'));
        return;
    }
    
    // Prepare batch data and results array
    $answers_batch = array();
    $results = array();
    
    global $wpdb;
    $questions_table = $wpdb->prefix . 'imported_questions';
    
    // Get all question details in one query
    $placeholders = implode(',', array_fill(0, count($all_question_ids), '%d'));
    $all_questions = $wpdb->get_results($wpdb->prepare(
        "SELECT ID, title, correct_answer, option_a, option_b, option_c, option_d 
         FROM $questions_table WHERE ID IN ($placeholders)",
        $all_question_ids
    ), ARRAY_A);
    
    // Process each question
    foreach ($all_questions as $question_data) {
        $question_id = $question_data['ID'];
        $correct_answer = $question_data['correct_answer'];
        
        // Get option texts
        $options = array(
            'A' => $question_data['option_a'],
            'B' => $question_data['option_b'],
            'C' => $question_data['option_c'],
            'D' => $question_data['option_d']
        );
        
        // Check if question was answered
        $user_answer = isset($user_answers[$question_id]) && !empty($user_answers[$question_id]) 
            ? $user_answers[$question_id] 
            : null;
        
        // Determine if correct
        if ($user_answer === null || $user_answer === '') {
            // Unanswered = automatically incorrect
            $is_correct = '0';
            $user_answer_text = '';
            $user_answer_display = 'Unanswered';
        } else {
            $is_correct = ($user_answer === $correct_answer) ? '1' : '0';
            $user_answer_text = sanitize_text_field($user_answer);
            $user_answer_display = $user_answer . '. ' . $options[$user_answer];
        }
        
        // Prepare data for batch insert
        $answers_batch[] = array(
            'exam_id' => $exam_id,
            'class_id' => $class_id,
            'exam_name' => $exam_name,
            'question_id' => intval($question_id),
            'user_id' => $user_id,
            'user_answer' => $user_answer_text,
            'is_correct' => $is_correct,
            'timestamp' => current_time('mysql'),
            'session_id' => $session_id,
            'question_category' => $cat,
        );
        
        // Prepare results for display
        $results[] = array(
            'question' => $question_data['title'],
            'user_answer' => $user_answer_display,
            'correct_answer' => $correct_answer . '. ' . $options[$correct_answer],
            'is_correct' => ($is_correct === '1')
        );
    }
    
    // Save all answers in a single batch operation
    if (!empty($answers_batch)) {
        $save_result = save_exam_answers_batch($answers_batch);
        
        if (!$save_result) {
            // Fallback to individual saves if batch fails
            $table_name = $wpdb->prefix . 'exam_answers';
            
            foreach ($answers_batch as $answer_data) {
                $wpdb->insert($table_name, $answer_data);
            }
        }
    }
    
    // Send success response with results
    wp_send_json_success(array(
        'message' => 'Exam submitted successfully!',
        'session_id' => $session_id,
        'results' => $results
    ));
}
//add_action('template_redirect', 'handle_flashcard_exam_submission');
function get_questions($category_id, $limit = 10) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'imported_questions';
    $user_id = get_current_user_id();
    
    // Validate category_id
    if (empty($category_id)) {
        return array();
    }
    
    // Get all subcategories under the main category
    $subcategories = get_term_children($category_id, 'question_category');
    $all_categories = array_merge([$category_id], $subcategories);
    $all_categories = array_filter($all_categories);
    
    if (empty($all_categories)) {
        return array();
    }
    
    // Get category identifiers (ID, slug, name) for matching
    $category_identifiers = array();
    foreach ($all_categories as $cat_id) {
        $term = get_term($cat_id, 'question_category');
        if ($term && !is_wp_error($term)) {
            $category_identifiers[] = $cat_id;
            $category_identifiers[] = $term->slug;
            $category_identifiers[] = $term->name;
        }
    }
    
    $category_identifiers = array_unique($category_identifiers);
    
    if (empty($category_identifiers)) {
        return array();
    }
    
    // QUERY 1: Fetch questions from database
    $placeholders = implode(',', array_fill(0, count($category_identifiers), '%s'));
    $query = $wpdb->prepare(
        "SELECT * FROM $table_name WHERE category IN ($placeholders) ORDER BY RAND()",
        $category_identifiers
    );
    
    $results = $wpdb->get_results($query, ARRAY_A);
    
    if (empty($results)) {
        return array();
    }
    
    // Extract all question IDs for batch processing
    $question_ids = array_column($results, 'id');
    
    // QUERY 2: Batch calculate looksfam scores for ALL questions at once
    $algo_scores = questionstatlooksfam_batch($question_ids, $user_id);
    
    // Categorize questions based on displaylook scores
    $questions_0 = array();
    $questions_1_50 = array();
    $questions_50_plus = array();
    
    foreach ($results as $row) {
        $displaylook = $algo_scores[$row['id']] ?? 0;
        
        $question = array(
            'ID' => $row['id'],
            'post_id' => $row['post_id'],
            'title' => $row['title'],
            'category' => $row['category'],
            'option_a' => $row['option_a'],
            'option_b' => $row['option_b'],
            'option_c' => $row['option_c'],
            'option_d' => $row['option_d'],
            'correct_answer' => $row['correct_answer'],
            'solution' => $row['solution'],
            'image_url' => $row['image_url'],
            'displaylook' => $displaylook
        );
        
        if ($displaylook == 0) {
            $questions_0[] = $question;
        } elseif ($displaylook >= 1 && $displaylook <= 50) {
            $questions_1_50[] = $question;
        } else {
            $questions_50_plus[] = $question;
        }
    }
    
    // Calculate distribution: 70% from questions_0, 30% from questions_1_50
    $limit_0 = min(ceil($limit * 0.7), count($questions_0));
    $limit_1_50 = min(ceil($limit * 0.3), count($questions_1_50));
    $limit_50_plus = max(0, $limit - $limit_0 - $limit_1_50);
    
    // Select questions from each group
    $selected_questions = array_merge(
        array_slice($questions_0, 0, $limit_0),
        array_slice($questions_1_50, 0, $limit_1_50),
        array_slice($questions_50_plus, 0, $limit_50_plus)
    );
    
    // Backfill if we don't have enough questions
    $remaining = $limit - count($selected_questions);
    if ($remaining > 0) {
        // Remove already selected questions from pools
        $questions_0 = array_slice($questions_0, $limit_0);
        $questions_1_50 = array_slice($questions_1_50, $limit_1_50);
        $questions_50_plus = array_slice($questions_50_plus, $limit_50_plus);
        
        // Backfill in priority order
        $backfill_pool = array_merge($questions_0, $questions_1_50, $questions_50_plus);
        $selected_questions = array_merge(
            $selected_questions,
            array_slice($backfill_pool, 0, $remaining)
        );
    }
    
    // Sort by displaylook (ascending - prioritize lower scores)
    usort($selected_questions, function ($a, $b) {
        return $a['displaylook'] <=> $b['displaylook'];
    });
    
    return array_slice($selected_questions, 0, $limit);
}

function display_activity_statistics_ui($cat, $user_id, $class_id, $type) {
    ob_start();
    echo getClassCategoryStatus($class_id);
    hover_looks();
    ?>
    <style>
    .stat-con {
        width: unset!important;
    }
    .button-container-menu {
        margin: 5px 0px;
        display: flex;
        gap: 10px;
        flex-wrap: wrap;
    }
    
    .custom-transparent-button {
        height:70px;
        border: 2px solid #58c2f7;
        background: transparent;
        color: #58c2f7;
        border-radius: 10px;
        flex: 1;
        cursor: pointer;
        transition: background 0.3s ease;
        padding: 10px;
        text-align: center;
    }
    
    .custom-button {
        height:70px;
        border-radius: 10px;
        flex: 1;
        cursor: pointer;
        transition: background 0.3s ease;
        padding: 10px;
        text-align: center;
        background: linear-gradient(135deg, #58c2f7, #4facfe);
    }
    
    .custom-transparent-button:hover {
        background: #ffffff;
    }
    
    .custom-transparent-button:disabled {
        opacity: 0.5;
        cursor: not-allowed;
        background-color: #e9ecef;
    }
    .go-back-button {
        margin-top: 20px;
        margin-bottom: 20px; 
        height: 50px;
    }

    /* Modal Styles */
    .stats-modal {
        display: none;
        position: fixed!important;
        z-index: 1000;
        left: 0;
        top: 0;
        width: 100%;
        height: 100%;
        background-color: rgba(0,0,0,0.8);
        backdrop-filter: blur(5px);
    }

    .modal-content {
        background: linear-gradient(135deg, rgba(88,194,247,0.1), rgba(88,194,247,0.05));
        margin: 5% auto;
        padding: 20px;
        border-radius: 15px;
        width: 90%;
        max-width: 1200px;
        max-height: 80%;
        overflow-y: auto;
        border: 2px solid #58c2f7;
        box-shadow: 0 10px 30px rgba(0,0,0,0.5);
        color: white;
    }

    .modal-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 20px;
        padding-bottom: 10px;
        border-bottom: 2px solid #58c2f7;
    }

    .modal-title {
        font-size: 24px;
        font-weight: bold;
        color: #58c2f7;
        margin: 0;
    }

    .close-modal {
        color: #58c2f7;
        font-size: 28px;
        font-weight: bold;
        cursor: pointer;
        padding: 5px 10px;
        border-radius: 5px;
        transition: background 0.3s ease;
    }

    .close-modal:hover {
        background: rgba(88,194,247,0.2);
    }

    /* Statistics Button Styles */
    .stats-buttons {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 15px;
        margin: 20px 0;
    }

    .stats-load-button {
        background: linear-gradient(135deg, #58c2f7, #4facfe);
        color: white;
        border: none;
        padding: 15px 20px;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 600;
        cursor: pointer;
        transition: all 0.3s ease;
        box-shadow: 0 4px 15px rgba(88,194,247,0.3);
        height:70px;
        width:100%;
    }

    .stats-load-button:hover {
        transform: translateY(-2px);
        box-shadow: 0 6px 20px rgba(88,194,247,0.4);
    }

    .stats-load-button:disabled {
        background: #666;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .loading-spinner {
        display: none;
        text-align: center;
        padding: 20px;
    }

    .spinner {
        border: 4px solid #f3f3f3;
        border-top: 4px solid #58c2f7;
        border-radius: 50%;
        width: 40px;
        height: 40px;
        animation: spin 2s linear infinite;
        margin: 0 auto;
    }

    @keyframes spin {
        0% { transform: rotate(0deg); }
        100% { transform: rotate(360deg); }
    }

    /* Existing styles for stats display */
    .stats-line {
        margin-top: 5px; 
        display: flex; 
        align-items: center;    
        border-bottom: 1px solid rgba(255, 255, 255, 0.1);
        padding: 12px 8px;
        transition: background 0.2s ease;
        border-radius: 6px;
    }
    
    .stats-line:hover {
        background: rgba(88, 194, 247, 0.1);
    }
    
    .stat-con {
        width: 20%;
        font-size: 14px;
        line-height: 1.4;
        padding: 0 5px;
    }
    
    .stats-section .stats-line .stat-con:first-child {
        width: 40%;
    }
    
    .stats-section .stats-line .stat-con:not(:first-child) {
        width: 15%;
    }
    
    .stats-section:not(.all-questions) .stats-line .stat-con {
        width: 50%;
    }
    
    .stats-header-line, .stats-header {
        background: rgba(88, 194, 247, 0.2);
        font-weight: bold;
        color: #58c2f7;
        border-bottom: 2px solid #58c2f7;
    }
    
    .stats-header-line:hover {
        background: rgba(88, 194, 247, 0.2);
    }

    .mobile-label {
        display: none;
        font-weight: bold;
        color: #58c2f7;
        margin-right: 5px;
        font-size:12px;
    }

    .desktop-label {
        display: inline;
    }

    /* Mobile responsive */
    @media (max-width: 768px) {
        .stats-buttons {
            grid-template-columns: 1fr;
            gap: 20px;
        }

        .modal-content {
            margin: 2% auto;
            width: 95%;
            max-height: 90%;
            padding: 15px;
        }

        .modal-title {
            font-size: 20px;
        }

        .mobile-label {
            display: inline;
        }
        
        .desktop-label {
            display: none;
        }
        
        .stats-line {
            flex-direction: row !important;
            flex-wrap: wrap !important;
            text-align: left !important;
            padding: 15px 10px;
            gap: 8px;
        }
        
        .stat-con {
            width: 100% !important;
            text-align: left !important;
            margin: 3px 0;
            font-size: 13px;
            display: flex;
            justify-content: space-between;
            align-items: center;
            border-bottom: 1px solid rgba(255, 255, 255, 0.05);
            padding: 5px 0;
        }
        
         .stat-con {
            font-size: 14px;
            font-weight: bold;
            justify-content: center;
            border-bottom: none;
        }
        
        .stats-header-line{
            display:none;
        }
        .question-title {
            justify-content: flex-start !important;
            text-align: left !important;
            font-weight: 600;
            color: #ffffff;
            border-bottom: 1px solid rgba(255, 255, 255, 0.2) !important;
            margin-bottom: 5px;
        }
        
        .rating-value {
            justify-content: space-between !important;
        }
    }

    /* Dashboard card styles remain the same */
    .dashboard-container {
        display: grid;
        grid-template-columns: 1fr 1fr;
        gap: 20px;
        margin: 20px 0;
    }
    
    .dashboard-card {
        background: white;
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 8px 25px rgba(0,0,0,0.15);
        color: #58c2f7;
        transition: transform 0.3s ease, box-shadow 0.3s ease;
        position: relative;
        overflow: hidden;
        display:flex;
        flex-direction: column;
        justify-content: space-between;
    }
    
    .dashboard-card:hover {
        transform: translateY(-5px);
        box-shadow: 0 15px 35px rgba(0,0,0,0.2);
    }
    
    .dashboard-card::before {
        content: '';
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        height: 4px;
        background: linear-gradient(90deg, #58c2f7, #4facfe);
    }
    
    .card-header {
        display: flex;
        justify-content: space-between;
        align-items: center;
        margin-bottom: 15px;
    }
    
    .card-title {
        font-size: 16px;
        font-weight: 600;
        margin: 0;
        color: #58c2f7;
    }
    
    .card-value {
        font-size: 40px;
        font-weight: bold;
        color: #58c2f7;
        text-shadow: 0 2px 4px rgba(0,0,0,0.2);
    }
    
    .info-icon {
        cursor: pointer;
        margin-left: 8px;
        color: #58c2f7;
        font-weight: bold;
        background: rgba(88,194,247,0.2);
        transition: background 0.3s ease;
    }
    
    .info-icon:hover {
        background: rgba(88,194,247,0.4);
    }
    
    .description-box {
        position: absolute;
        background: rgba(0,0,0,0.9);
        color: white;
        padding: 10px;
        border-radius: 8px;
        font-size: 12px;
        width: 200px;
        z-index: 1000;
        border: 1px solid #58c2f7;
        box-shadow: 0 4px 15px rgba(0,0,0,0.3);
        left: 20%;
        margin-top: 5px;
    }

    .question-title {
        font-weight: 500;
        color: #ffffff;
        overflow: hidden;
        text-overflow: ellipsis;
        display: -webkit-box;
        line-height: 1.3;
    }
    
    .rating-value {
        font-weight: bold;
        color: #58c2f7;
        font-size: 16px;
    }

    @media (max-width: 768px) {
        .dashboard-container {
            grid-template-columns: 1fr 1fr;
            gap: 15px;
            margin: 15px 0;
        }
        
        .dashboard-card {
            padding: 20px;
        }
        
        .card-value {
            font-size: 35px;
        }
        
        .description-box {
            width: 180px;
            font-size: 11px;
            left: 50%;
        }
    }
    
    @media (max-width: 480px) {
        .dashboard-card {
            padding: 15px;
        }
        
        .card-title {
            font-size: 14px;
        }
        
        .card-value {
            font-size: 32px;
        }

        .question-title {
            font-size: 12px;
            -webkit-line-clamp: 3;
        }
        
        .rating-value {
            font-size: 14px;
        }
    }
    .bb {
    position: relative;
    width: 100%;
    background: #00000012;
    border-radius: 10px;
    overflow: hidden;
    min-height: 200px; /* Ensure minimum height for proper display */
}

.bb::before {
    content: '';
    position: absolute;
    top: 0;
    left: 0;
    width: 100%;
    height: 100%;
    backdrop-filter: blur(15px);
    -webkit-backdrop-filter: blur(15px);
    background: rgba(0, 0, 0, 0.6); /* Darker overlay for better blur effect */
    pointer-events: none;
    z-index: 1;
}

.bb > * {
    position: relative;
    z-index: 2;
    filter: blur(3px); /* Additional blur for content underneath */
    opacity: 0.3; /* Make underlying content more faded */
}

.stat-forbid {
    position: absolute !important;
    top: 50% !important;
    left: 50% !important;
    transform: translate(-50%, -50%) !important;
    z-index: 10 !important;
    color: #58c2f7 !important;
    font-size: 18px !important;
    font-weight: bold !important;
    text-align: center !important;
    text-shadow: 0 2px 4px rgba(0,0,0,0.5) !important;
    background: rgba(0, 0, 0, 0.8) !important;
    padding: 20px 30px !important;
    border-radius: 12px !important;
    border: 2px solid #58c2f7 !important;
    box-shadow: 0 8px 25px rgba(0,0,0,0.4) !important;
    backdrop-filter: blur(5px) !important;
    -webkit-backdrop-filter: blur(5px) !important;
    max-width: 90% !important;
    line-height: 1.4 !important;
    pointer-events: auto !important;
    filter: none !important; /* Remove blur from the message itself */
    opacity: 1 !important; /* Full opacity for the message */
}
    </style>

    <!-- Modal for statistics -->
    <div id="statsModal" class="stats-modal">
        <div class="modal-content">
            <div class="modal-header">
                <h2 id="modalTitle" class="modal-title">Statistics</h2>
                <span class="close-modal">&times;</span>
            </div>
            <div id="modalBody">
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <p>Loading statistics...</p>
                </div>
            </div>
        </div>
    </div>

    <div class="button-container">
        <input type="button" value="←" class="go-back-button" onclick="window.location.href='<?php echo home_url('/profile?class_id=' . $class_id); ?>';" style="border-radius: 10px;">
        <?php echo dropdown_menu(); ?>
    </div>

    <div class="button-container" style="display: flex;">
        <div style="width: 100%;">
            <h4 style="margin-bottom:0px">Progress<br></h4>
            
  <?php
// Get category ID
$category_l = intval($_GET['cat']);

// Get all subcategories in a single query
$subcategories = get_term_children($category_l, 'question_category');
$all_categories = array_merge([$category_l], $subcategories);
$all_categories = array_filter($all_categories);

// Initialize database
global $wpdb;
$questions_table = $wpdb->prefix . 'imported_questions';
$table_name = $wpdb->prefix . 'exam_answers';

// Build placeholders for categories
$category_placeholders = implode(',', array_fill(0, count($all_categories), '%d'));

// QUERY 1: Get total questions count
$total_questions = $wpdb->get_var($wpdb->prepare(
    "SELECT COUNT(*) FROM $questions_table WHERE category IN ($category_placeholders)",
    $all_categories
));
$total_questions = intval($total_questions);

// QUERY 2: Get user's exam answers
$results = $wpdb->get_results($wpdb->prepare(
    "SELECT question_id, is_correct, session_id
     FROM $table_name
     WHERE user_id = %d AND question_category = %d
     ORDER BY timestamp ASC",
    $user_id,
    $category_l
), ARRAY_A);

// Initialize counters
$unique_sessions = [];
$unique_questions = [];
$total_correct_answers = 0;
$total_answered = count($results);
$question_ids_for_algo = [];

// Single pass through results - O(n) complexity
foreach ($results as $row) {
    $question_id = $row['question_id'];
    $session_id = $row['session_id'];
    
    // Track unique sessions
    $unique_sessions[$session_id] = true;
    
    // Track unique questions (only count first answer)
    if (!isset($unique_questions[$question_id])) {
        $unique_questions[$question_id] = true;
        $question_ids_for_algo[] = $question_id;
        
        if ($row['is_correct'] == '1') {
            $total_correct_answers++;
        }
    }
}

// QUERY 3: Batch calculate all looksfam scores at once (instead of N queries)
$algo_scores = !empty($question_ids_for_algo) 
    ? questionstatlooksfam_batch($question_ids_for_algo, $user_id) 
    : [];

// Calculate displaylook sum using pre-fetched scores
$displaylook_sum = 0;
foreach ($results as $row) {
    $displaylook_sum += $algo_scores[$row['question_id']] ?? 0;
}

// Calculate metrics
$exams_taken = count($unique_sessions);
$questions_answered = count($unique_questions);
$overall_accuracy = $questions_answered > 0 
    ? ($total_correct_answers / $questions_answered) * 100 
    : 0;
$looksfamacc_x_accuracy_display = $total_answered > 0 
    ? ($displaylook_sum / ($total_answered * 100)) * 100 
    : 0;

// Tier requirements
$tier2_required = 10;
$tier3_required = 20;
$tier4_required = 50;
$tier5_required = 70;

// Check user capabilities once
$is_admin = current_user_can('manage_options');
$has_free_trial = get_user_meta($user_id, 'free_trial_' . $class_id, true);

// Calculate tiers
$tier1 = $exams_taken >= 5 ? '1' : null;
$tier2 = ($exams_taken >= 10 && $total_correct_answers >= $tier2_required) || $is_admin ? '1' : null;
$tier3 = ($exams_taken >= 20 && $total_correct_answers >= $tier3_required) || $is_admin ? '1' : null;
$tier4 = ($exams_taken >= 50 && $total_correct_answers >= $tier4_required) || $is_admin ? '1' : null;
$tier5 = ($exams_taken >= 70 && $total_correct_answers >= $tier5_required) || $is_admin ? '1' : null;
$tier6 = $has_free_trial || !$is_admin ? null : '1';
// QUERY 4: Get this week's data
$week_start = date('Y-m-d 00:00:00', strtotime('monday this week'));
$week_results = $wpdb->get_results($wpdb->prepare(
    "SELECT question_id, is_correct, session_id
     FROM $table_name
     WHERE user_id = %d 
     AND question_category = %d 
     AND timestamp >= %s
     ORDER BY timestamp ASC",
    $user_id,
    $category_l,
    $week_start
), ARRAY_A);

// Calculate weekly metrics
$weekly_unique_questions = [];
$weekly_correct_answers = 0;
$weekly_unique_sessions = [];

foreach ($week_results as $row) {
    $question_id = $row['question_id'];
    $session_id = $row['session_id'];
    
    // Track weekly unique sessions (exams)
    $weekly_unique_sessions[$session_id] = true;
    
    // Track weekly unique questions
    if (!isset($weekly_unique_questions[$question_id])) {
        $weekly_unique_questions[$question_id] = true;
        
        if ($row['is_correct'] == '1') {
            $weekly_correct_answers++;
        }
    }
}

$weekly_questions_answered = count($weekly_unique_questions);
$weekly_exams_taken = count($weekly_unique_sessions);
$weekly_accuracy = $weekly_questions_answered > 0 
    ? ($weekly_correct_answers / $weekly_questions_answered) * 100 
    : 0;
    
?>
        </div>
    </div>

    <!-- Dashboard cards -->
    <div class="dashboard-container">
        <div id="info-description" class="description-box" style="display: none;">
            This rating is an AI algorithm percentage based on your familiarity and retention on this question.
        </div>
        
        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">
                    Looksfam Accuracy
                    <span class="info-icon" onclick="toggleDescription()">&#8505;</span>
                </h3>
            </div>
            <div class="card-value"><?php echo round($looksfamacc_x_accuracy_display, 2).'%'; ?></div>
            <small>Retention Rating</small>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">Overall Question Accuracy</h3><br>
            </div>
            <div class="card-value"><?php echo round($overall_accuracy, 2).'%'; ?></div>
            <small>(Correct Answers / Total Unique Questions)</small>
        </div>

        
    </div>
    
    <div class="button-container-menu">
        <button class="custom-button" onclick="window.location.href='<?php echo home_url('/'.$type.'?exam_id=8080&cat=' . $category_l . '&take=1&class_id=' . $class_id); ?>';">Start</button>
        <button class="custom-transparent-button"<?php if(!isset($tier1)){echo "disabled";}?> onclick="window.location.href='<?php echo home_url('/solutions?id=' . $exam_id . '&cat=' . $category_l . '&class_id=' . $class_id); ?>';">
        Review Questions<br><?php 
        if(!isset($tier1)){ 
            $exam_needed = 5 - $exams_taken;
            if ($exam_needed > 0) {
                echo "Answer $exam_needed more exam" . ($exam_needed > 1 ? "s" : "") . " to unlock!";
            }
        }
        ?></button>
    </div>
        <h3 style="margin-bottom:0px;text-align:center"> Other Statistics<br></h3>
    <div class="dashboard-container">
        
        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">Weekly Accuracy</h3>
            </div>
            <div class="card-value"><?php echo round($weekly_accuracy, 2).'%'; ?></div>
            <small>This Week's Performance</small>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">Weekly Correct Answers</h3>
            </div>
            <div class="card-value"><?php echo $weekly_correct_answers; ?></div>
            <small>Since <?php echo date('M d', strtotime($week_start)); ?></small>
        </div>
        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">Questions This Week</h3>
            </div>
            <div class="card-value"><?php echo $weekly_questions_answered; ?></div>
            <small>Since <?php echo date('M d', strtotime($week_start)); ?></small>
        </div>
        
        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">Exams This Week</h3>
            </div>
            <div class="card-value"><?php echo $weekly_exams_taken; ?></div>
            <small>Since <?php echo date('M d', strtotime($week_start)); ?></small>
        </div>
        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">Total Questions Answered</h3>
            </div>
            <div class="card-value"><?php echo $questions_answered; ?></div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">Total Exams Taken</h3>
            </div>
            <div class="card-value"><?php echo $exams_taken; ?></div>
        </div>
    </div>

    <script>
    function toggleDescription() {
        const desc = document.getElementById('info-description');
        desc.style.display = desc.style.display === 'none' ? 'block' : 'none';
    }
    </script>

    
    <p style="margin-bottom:0px">Unli Questions Mode Coming Soon.</p>
    
    <div style="margin-top:0px;display:none">
        <h3 style="margin-bottom:0px;text-align:center">Statistics<br></h3>
        
        <!-- Statistics buttons instead of direct display -->
        <div class="stats-buttons">
            <button class="stats-load-button" onclick="loadStats('best-worst', '<?php echo $user_id; ?>', '<?php echo $category_l; ?>', '<?php echo $class_id; ?>')">
                Your Best & Weakest Questions
            </button>
            <button class="stats-load-button" onclick="loadStats('overall', '<?php echo $user_id; ?>', '<?php echo $category_l; ?>', '<?php echo $class_id; ?>')">
                Overall Top & Worst Questions
            </button>
            
        </div>
        <div>
            <button class="stats-load-button" style="width:100%" onclick="loadStats('all-questions', '<?php echo $user_id; ?>', '<?php echo $category_l; ?>', '<?php echo $class_id; ?>')">
                All Question Statistics
            </button>
        </div>
        <?php if (empty($results)): ?>
            <div style="text-align: center; color: #58c2f7; margin-top: 20px;">
                <p>No questions answered yet in this category.</p>
            </div>
        <?php endif; ?>
    </div>

    <script>
    // Modal functionality
    const modal = document.getElementById('statsModal');
    const closeBtn = document.querySelector('.close-modal');
    const modalTitle = document.getElementById('modalTitle');
    const modalBody = document.getElementById('modalBody');

    closeBtn.onclick = function() {
        modal.style.display = 'none';
    }

    window.onclick = function(event) {
        if (event.target == modal) {
            modal.style.display = 'none';
        }
    }

    function loadStats(type, userId, categoryId, classId) {
        modal.style.display = 'block';
        modalBody.innerHTML = '<div class="loading-spinner" style="display: block;"><div class="spinner"></div><p>Loading statistics...</p></div>';
        
        // Set modal title based on type
        const titles = {
            'best-worst': 'Your Best & Weakest Questions',
            'overall': 'Overall Top & Worst Questions', 
            'all-questions': 'All Question Statistics'
        };
        modalTitle.textContent = titles[type] || 'Statistics';

        // AJAX call to load statistics
        fetch('<?php echo admin_url('admin-ajax.php'); ?>', {
            method: 'POST',
            headers: {
                'Content-Type': 'application/x-www-form-urlencoded',
            },
            body: `action=load_question_stats&type=${type}&user_id=${userId}&category_id=${categoryId}&class_id=${classId}&nonce=<?php echo wp_create_nonce('load_stats_nonce'); ?>`
        })
        .then(response => response.text())
        .then(data => {
            modalBody.innerHTML = data;
        })
        .catch(error => {
            console.error('Error:', error);
            modalBody.innerHTML = '<p style="color: #ff6b6b; text-align: center;">Error loading statistics. Please try again.</p>';
        });
    }
    </script>

    <?php
    return ob_get_clean();
}

// AJAX handler for loading statistics
function load_question_stats() {
    // Security check
    if (!wp_verify_nonce($_POST['nonce'], 'load_stats_nonce')) {
        wp_die('Security check failed');
    }
    
    $type = sanitize_text_field($_POST['type']);
    $user_id = intval($_POST['user_id']);
    $category_id = intval($_POST['category_id']);
    $class_id = intval($_POST['class_id']);
    
    global $wpdb;
    $table_name = $wpdb->prefix . 'exam_answers';
    $questions_table = $wpdb->prefix . 'imported_questions';
    
    // QUERY 1: Get user's exam answers
    $user_questions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT question_id, is_correct, session_id, question_category, timestamp 
             FROM $table_name 
             WHERE user_id = %d AND question_category = %d 
             ORDER BY timestamp ASC",
            $user_id,
            $category_id
        ),
        ARRAY_A
    );
    
    // Calculate tiers using optimized tracking
    $unique_sessions = [];
    $unique_questions = [];
    $all_correct_answers = 0;
    
    if (!empty($user_questions)) {
        foreach ($user_questions as $question) {
            $question_id = $question['question_id'];
            $session_id = $question['session_id'];
            
            // Track unique questions
            if (!isset($unique_questions[$question_id])) {
                $unique_questions[$question_id] = true;
                
                if ($question['is_correct'] == '1') {
                    $all_correct_answers++;
                }
            }
            
            // Track unique sessions
            $unique_sessions[$session_id] = true;
        }
    }
    
    $exams_taken = count($unique_sessions);
    
    // Tier requirements
    $tier2_required = 10;
    $tier3_required = 20;
    $tier4_required = 50;
    $tier5_required = 70;
    
    $is_admin = current_user_can('manage_options');
    $has_free_trial = get_user_meta($user_id, 'free_trial_' . $class_id, true);
    
    $tier2 = ($exams_taken >= 10 && $all_correct_answers >= $tier2_required) || $is_admin ? '1' : null;
    $tier3 = ($exams_taken >= 20 && $all_correct_answers >= $tier3_required) || $is_admin ? '1' : null;
    $tier4 = ($exams_taken >= 50 && $all_correct_answers >= $tier4_required) || $is_admin ? '1' : null;
    $tier5 = ($exams_taken >= 70 && $all_correct_answers >= $tier5_required) || $is_admin ? '1' : null;
    $tier6 = $has_free_trial || !$is_admin ? null : '1';
    
    // Build questions arrays
    $questions_to_display = [];
    
    if (!empty($user_questions)) {
        // Get unique question IDs (post_ids)
        $question_ids = array_keys($unique_questions);
        
        if (empty($question_ids)) {
            echo '';
            wp_die();
        }
        
        // QUERY 2: Fetch all question details at once
        $placeholders = implode(',', array_fill(0, count($question_ids), '%d'));
        $questions_data = $wpdb->get_results(
            $wpdb->prepare(
                "SELECT id, post_id, title, category, option_a, option_b, option_c, option_d, 
                        correct_answer, solution, image_url 
                 FROM $questions_table 
                 WHERE post_id IN ($placeholders)",
                $question_ids
            ),
            ARRAY_A
        );
        
        // Index by post_id for quick lookup
        $questions_lookup = [];
        foreach ($questions_data as $q) {
            $questions_lookup[$q['post_id']] = $q;
        }
        
        // QUERY 3: Batch calculate looksfam scores
        $looksfam_scores = questionstatlooksfam_batch($question_ids, $user_id, $class_id);
        
        // QUERY 4: Batch calculate overall looksfam scores
        $overall_scores = questionstatlooksfam_batch($question_ids, $user_id, $class_id);
        
        // QUERY 5: Batch calculate accuracy scores
        $accuracy_scores = questionstatlooksfam_batch($question_ids, $user_id, $class_id, 'accuracy');
        
        // QUERY 6: Batch calculate overall accuracy scores
        $overall_accuracy_scores = questionstatlooksfam_batch($question_ids, $user_id, $class_id, 'accuracy');
        
        // Build display array with pre-calculated scores
        foreach ($question_ids as $question_id) {
            // Skip if question data not found
            if (!isset($questions_lookup[$question_id])) {
                continue;
            }
            
            $question_data = $questions_lookup[$question_id];
            
            // Get pre-calculated scores
            $displaylook_rating = $looksfam_scores[$question_id] ?? 0;
            $displayoveralllook_rating = $overall_scores[$question_id] ?? 0;
            $displaylook_rating_acc = $accuracy_scores[$question_id] ?? 0;
            $displayoveralllook_rating_acc = $overall_accuracy_scores[$question_id] ?? 0;
            
            // Apply variations
            $question_hash = crc32($question_id . $user_id);
            $user_variation = ($question_hash % 30) - 15;
            $overall_variation = ($question_hash % 20) - 10;
            
            $displaylook_rating = max(0, min(100, $displaylook_rating + $user_variation));
            $displayoveralllook_rating = max(0, min(100, $displayoveralllook_rating + $overall_variation));
            
            $questions_to_display[] = [
                'question_id' => $question_id,
                'title' => $question_data['title'],
                'displaylook' => $displaylook_rating,
                'displayoveralllook' => $displayoveralllook_rating,
                'displaylook_acc' => $displaylook_rating_acc,
                'displayoveralllook_acc' => $displayoveralllook_rating_acc
            ];
        }
        
        // Sort for best-worst view
        usort($questions_to_display, function($a, $b) {
            return $b['displaylook'] <=> $a['displaylook'];
        });
        
        // Create overall questions array (sorted differently)
        $overall_questions = $questions_to_display;
        usort($overall_questions, function($a, $b) {
            return $b['displayoveralllook'] <=> $a['displayoveralllook'];
        });
    }
    
    // Generate HTML based on type
    ob_start();
    
    if ($type === 'best-worst') {
        render_best_worst_stats(
            $questions_to_display, 
            $tier2, 
            $tier3, 
            $exams_taken, 
            $all_correct_answers, 
            $tier2_required, 
            $tier3_required
        );
    } elseif ($type === 'overall') {
        render_overall_stats(
            $overall_questions, 
            $tier4, 
            $tier5, 
            $exams_taken, 
            $all_correct_answers, 
            $tier4_required, 
            $tier5_required
        );
    } elseif ($type === 'all-questions') {
        render_all_questions_stats($questions_to_display, $tier6);
    }
    
    echo ob_get_clean();
    wp_die();
}

add_action('wp_ajax_load_question_stats', 'load_question_stats');
add_action('wp_ajax_nopriv_load_question_stats', 'load_question_stats');
function render_best_worst_stats($questions_to_display, $tier2, $tier3, $exams_taken, $all_correct_answers, $tier2_required, $tier3_required) {
    ?>
    <div class="stats-row">
        <!-- Top 3 Category Questions -->
        <div class="stats-column">
            <div class="stats-section">
                <h4 class="stats-header">Your Best 3 Questions</h4>
                <div class="stats-line stats-header-line">
                    <div class="stat-con" style="flex-grow: 1; text-align: left;">Question</div>
                    <div class="stat-con" style="text-align: center;">
                        <span class="desktop-label">Looksfam</span>
                        <span class="mobile-label">Looksfam</span>
                    </div>
                    <div class="stat-con" style="text-align: center;">
                        <span class="desktop-label">Accuracy</span>
                        <span class="mobile-label">Accuracy</span>
                    </div>
                    <div class="stat-con" style="text-align: center;">
                        <span class="desktop-label">Overall Looksfam</span>
                        <span class="mobile-label">Overall Looksfam</span>
                    </div>
                    <div class="stat-con" style="text-align: right;">
                        <span class="desktop-label">Overall Accuracy</span>
                        <span class="mobile-label">Overall Accuracy</span>
                    </div>
                </div>
                
                <?php if (isset($tier2)): ?>
                    <div class="stats">
                        <?php 
                        $top_3 = array_slice($questions_to_display, 0, 3);
                        foreach ($top_3 as $question): ?>
                            <div class="stats-line" data-id="<?php echo $question['question_id']?>">
                                <div class="stat-con question-title" style="flex-grow: 1; text-align: left;"><?php echo $question['title']; ?></div>
                                <div class="stat-con rating-value" style="text-align: center;color: #58c2f7;">
                                    <span class="mobile-label">Looksfam: </span><?php echo $question['displaylook']; ?>%
                                </div>
                                <div class="stat-con rating-value" style="text-align: center;color: #58c2f7;">
                                    <span class="mobile-label">Accuracy: </span><?php echo $question['displaylook_acc']; ?>%
                                </div>
                                <div class="stat-con rating-value" style="text-align: center;color: #58c2f7;">
                                    <span class="mobile-label">Overall Looksfam: </span><?php echo $question['displayoveralllook']; ?>%
                                </div>
                                <div class="stat-con rating-value" style="text-align: right;color: #58c2f7;">
                                    <span class="mobile-label">Overall Accuracy: </span><?php echo $question['displayoveralllook_acc']; ?>%
                                </div>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else: ?>
                    <div class="stats bb">
                        <?php render_locked_placeholder(); ?>
                        <div class="stat-forbid">
                            <?php 
                            $exam_needed = 10 - $exams_taken;
                            $correct_needed = $tier2_required - $all_correct_answers;
                            $message_parts = [];
                            
                            if ($exam_needed > 0) {
                                $message_parts[] = "Answer $exam_needed more exam" . ($exam_needed > 1 ? "s" : "");
                            }
                            
                            if ($correct_needed > 0) {
                                $message_parts[] = "Get $correct_needed more correct answer" . ($correct_needed > 1 ? "s" : "");
                            }
                            
                            if (!empty($message_parts)) {
                                echo implode(" and ", $message_parts) . " to unlock!";
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bottom 3 Category Questions -->
        <div class="stats-column">
            <div class="stats-section">
                <h4 class="stats-header">Your Weakest 3 Questions</h4>
                <div class="stats-line stats-header-line">
                    <div class="stat-con" style="flex-grow: 1; text-align: left;">Question</div>
                    <div class="stat-con" style="text-align: center;">
                        <span class="desktop-label">Looksfam</span>
                        <span class="mobile-label">Looksfam</span>
                    </div>
                    <div class="stat-con" style="text-align: center;">
                        <span class="desktop-label">Accuracy</span>
                        <span class="mobile-label">Accuracy</span>
                    </div>
                    <div class="stat-con" style="text-align: center;">
                        <span class="desktop-label">Overall Looksfam</span>
                        <span class="mobile-label">Overall Looksfam</span>
                    </div>
                    <div class="stat-con" style="text-align: right;">
                        <span class="desktop-label">Overall Accuracy</span>
                        <span class="mobile-label">Overall Accuracy</span>
                    </div>
                </div>
                
                <?php if (isset($tier3)): ?>
                    <div class="stats">
                        <?php 
                        if (!empty($questions_to_display)) {
                            $bottom_3 = array_slice($questions_to_display, -3);
                            foreach ($bottom_3 as $question): ?>
                                <div class="stats-line" data-id="<?php echo $question['question_id']?>">
                                    <div class="stat-con question-title" style="flex-grow: 1; text-align: left;"><?php echo $question['title']; ?></div>
                                    <div class="stat-con rating-value" style="text-align: center;color: #58c2f7;">
                                        <span class="mobile-label">Looksfam: </span><?php echo $question['displaylook']; ?>%
                                    </div>
                                    <div class="stat-con rating-value" style="text-align: center;color: #58c2f7;">
                                        <span class="mobile-label">Accuracy: </span><?php echo $question['displaylook_acc']; ?>%
                                    </div>
                                    <div class="stat-con rating-value" style="text-align: center;color: #58c2f7;">
                                        <span class="mobile-label">Overall Looksfam: </span><?php echo $question['displayoveralllook']; ?>%
                                    </div>
                                    <div class="stat-con rating-value" style="text-align: right;color: #58c2f7;">
                                        <span class="mobile-label">Overall Accuracy: </span><?php echo $question['displayoveralllook_acc']; ?>%
                                    </div>
                                </div>
                            <?php endforeach; 
                        } ?>
                    </div>
                <?php else: ?>
                    <div class="stats bb">
                        <?php render_locked_placeholder(); ?>
                        <div class="stat-forbid">
                            <?php 
                            $exam_needed = 20 - $exams_taken;
                            $correct_needed = $tier3_required - $all_correct_answers;
                            $message_parts = [];
                            
                            if ($exam_needed > 0) {
                                $message_parts[] = "Answer $exam_needed more exam" . ($exam_needed > 1 ? "s" : "");
                            }
                            
                            if ($correct_needed > 0) {
                                $message_parts[] = "Get $correct_needed more correct answer" . ($correct_needed > 1 ? "s" : "");
                            }
                            
                            if (!empty($message_parts)) {
                                echo implode(" and ", $message_parts) . " to unlock!";
                            }
                            ?>   
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

function render_overall_stats($overall_questions, $tier4, $tier5, $exams_taken, $all_correct_answers, $tier4_required, $tier5_required) {
    ?>
    <div class="stats-row">
        <!-- Top 3 Overall Questions -->
        <div class="stats-column">
            <div class="stats-section">
                <h4 class="stats-header">Overall Top Questions</h4>
                <div class="stats-line stats-header-line">
                    <div class="stat-con" style="flex-grow: 1; text-align: left;">Question</div>
                    <div class="stat-con" style="text-align: center;">
                        <span class="desktop-label">Looksfam</span>
                        <span class="mobile-label">Looksfam</span>
                    </div>
                    <div class="stat-con" style="text-align: center;">
                        <span class="desktop-label">Accuracy</span>
                        <span class="mobile-label">Accuracy</span>
                    </div>
                    <div class="stat-con" style="text-align: center;">
                        <span class="desktop-label">Overall Looksfam</span>
                        <span class="mobile-label">Overall Looksfam</span>
                    </div>
                    <div class="stat-con" style="text-align: right;">
                        <span class="desktop-label">Overall Accuracy</span>
                        <span class="mobile-label">Overall Accuracy</span>
                    </div>
                </div>
                
                <?php if (isset($tier4)): ?>
                    <div class="stats">
                        <?php 
                        if (!empty($overall_questions)) {
                            $top_3_overall = array_slice($overall_questions, 0, 3);
                            foreach ($top_3_overall as $question): ?>
                                <div class="stats-line" data-id="<?php echo $question['question_id']?>">
                                    <div class="stat-con question-title" style="flex-grow: 1; text-align: left;"><?php echo $question['title']; ?></div>
                                    <div class="stat-con rating-value" style="text-align: center;color: #58c2f7;">
                                        <span class="mobile-label">Looksfam: </span><?php echo $question['displaylook']; ?>%
                                    </div>
                                    <div class="stat-con rating-value" style="text-align: center;color: #58c2f7;">
                                        <span class="mobile-label">Accuracy: </span><?php echo $question['displaylook_acc']; ?>%
                                    </div>
                                    <div class="stat-con rating-value" style="text-align: center;color: #58c2f7;">
                                        <span class="mobile-label">Overall Looksfam: </span><?php echo $question['displayoveralllook']; ?>%
                                    </div>
                                    <div class="stat-con rating-value" style="text-align: right;color: #58c2f7;">
                                        <span class="mobile-label">Overall Accuracy: </span><?php echo $question['displayoveralllook_acc']; ?>%
                                    </div>
                                </div>
                            <?php endforeach; 
                        } ?>
                    </div>
                <?php else: ?>
                    <div class="stats bb">
                        <?php render_locked_placeholder(); ?>
                        <div class="stat-forbid">
                            <?php 
                            $exam_needed = 50 - $exams_taken;
                            $correct_needed = $tier4_required - $all_correct_answers;
                            $message_parts = [];
                            
                            if ($exam_needed > 0) {
                                $message_parts[] = "Answer $exam_needed more exam" . ($exam_needed > 1 ? "s" : "");
                            }
                            
                            if ($correct_needed > 0) {
                                $message_parts[] = "Get $correct_needed more correct answer" . ($correct_needed > 1 ? "s" : "");
                            }
                            
                            if (!empty($message_parts)) {
                                echo implode(" and ", $message_parts) . " to unlock!";
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>

        <!-- Bottom 3 Overall Questions -->
        <div class="stats-column">
            <div class="stats-section">
                <h4 class="stats-header">Overall Worst Questions</h4>
                <div class="stats-line stats-header-line">
                    <div class="stat-con" style="flex-grow: 1; text-align: left;">Question</div>
                    <div class="stat-con" style="text-align: center;">
                        <span class="desktop-label">Looksfam</span>
                        <span class="mobile-label">Looksfam</span>
                    </div>
                    <div class="stat-con" style="text-align: center;">
                        <span class="desktop-label">Accuracy</span>
                        <span class="mobile-label">Accuracy</span>
                    </div>
                    <div class="stat-con" style="text-align: center;">
                        <span class="desktop-label">Overall Looksfam</span>
                        <span class="mobile-label">Overall Looksfam</span>
                    </div>
                    <div class="stat-con" style="text-align: right;">
                        <span class="desktop-label">Overall Accuracy</span>
                        <span class="mobile-label">Overall Accuracy</span>
                    </div>
                </div>
                
                <?php if (isset($tier5)): ?>
                    <div class="stats">
                        <?php 
                        if (!empty($overall_questions)) {
                            $bottom_3_overall = array_slice($overall_questions, -3);
                            foreach ($bottom_3_overall as $question): ?>
                                <div class="stats-line" data-id="<?php echo $question['question_id']?>">
                                    <div class="stat-con question-title" style="flex-grow: 1; text-align: left;"><?php echo $question['title']; ?></div>
                                    <div class="stat-con rating-value" style="text-align: center;color: #58c2f7;">
                                        <span class="mobile-label">Looksfam: </span><?php echo $question['displaylook']; ?>%
                                    </div>
                                    <div class="stat-con rating-value" style="text-align: center;color: #58c2f7;">
                                        <span class="mobile-label">Accuracy: </span><?php echo $question['displaylook_acc']; ?>%
                                    </div>
                                    <div class="stat-con rating-value" style="text-align: center;color: #58c2f7;">
                                        <span class="mobile-label">Overall Looksfam: </span><?php echo $question['displayoveralllook']; ?>%
                                    </div>
                                    <div class="stat-con rating-value" style="text-align: right;color: #58c2f7;">
                                        <span class="mobile-label">Overall Accuracy: </span><?php echo $question['displayoveralllook_acc']; ?>%
                                    </div>
                                </div>
                            <?php endforeach; 
                        } ?>
                    </div>
                <?php else: ?>
                    <div class="stats bb">
                        <?php render_locked_placeholder(); ?>
                        <div class="stat-forbid">
                            <?php 
                            $exam_needed = 70 - $exams_taken;
                            $correct_needed = $tier5_required - $all_correct_answers;
                            $message_parts = [];
                            
                            if ($exam_needed > 0) {
                                $message_parts[] = "Answer $exam_needed more exam" . ($exam_needed > 1 ? "s" : "");
                            }
                            
                            if ($correct_needed > 0) {
                                $message_parts[] = "Get $correct_needed more correct answer" . ($correct_needed > 1 ? "s" : "");
                            }
                            
                            if (!empty($message_parts)) {
                                echo implode(" and ", $message_parts) . " to unlock!";
                            }
                            ?>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
    <?php
}

function render_all_questions_stats($questions_to_display, $tier6) {
    ?>
    <div class="stats-section">
        <div class="stats-line stats-header-line">
            <div class="stat-con" style="flex-grow: 1; text-align: left;">Question</div>
            <div class="stat-con" style="text-align: center;">
                <span class="desktop-label">Looksfam</span>
                <span class="mobile-label">Looksfam</span>
            </div>
            <div class="stat-con" style="text-align: center;">
                <span class="desktop-label">Accuracy</span>
                <span class="mobile-label">Accuracy</span>
            </div>
            <div class="stat-con" style="text-align: center;">
                <span class="desktop-label">Overall Looksfam</span>
                <span class="mobile-label">Overall Looksfam</span>
            </div>
            <div class="stat-con" style="text-align: right;">
                <span class="desktop-label">Overall Accuracy</span>
                <span class="mobile-label">Overall Accuracy</span>
            </div>
        </div>
        
        <?php if ($tier6): ?>
            <div class="stats">
                <?php
                if (!empty($questions_to_display)) {
                    foreach ($questions_to_display as $question) {
                        ?>
                        <div class="stats-line" data-id="<?php echo $question['question_id']?>">
                            <div class="stat-con question-title" style="flex-grow: 1; text-align: left;"><?php echo $question['title']; ?></div>
                            <div class="stat-con rating-value" style="text-align: center;color: #58c2f7;">
                                <span class="mobile-label">Looksfam: </span><?php echo $question['displaylook']; ?>%
                            </div>
                            <div class="stat-con rating-value" style="text-align: center;color: #58c2f7;">
                                <span class="mobile-label">Accuracy: </span><?php echo $question['displaylook_acc']; ?>%
                            </div>
                            <div class="stat-con rating-value" style="text-align: center;color: #58c2f7;">
                                <span class="mobile-label">Overall Looksfam: </span><?php echo $question['displayoveralllook']; ?>%
                            </div>
                            <div class="stat-con rating-value" style="text-align: right;color: #58c2f7;">
                                <span class="mobile-label">Overall Accuracy: </span><?php echo $question['displayoveralllook_acc']; ?>%
                            </div>
                        </div>
                        <?php
                    }
                }
                ?>
            </div>
        <?php else: ?>
            <div class="stats bb">
                <?php render_locked_placeholder(); ?>
                <div class="stat-forbid">
                    🔒<br>For Paid Users osnly!
                </div>
            </div>
        <?php endif; ?>
    </div>
    <?php
}

function render_locked_placeholder() {
    ?>
    <div class="stats-line">
        <div class="stat-con" style="flex-grow: 1; text-align: left;">This is a random question.</div>
        <div class="stat-con" style="text-align: center;color: #58c2f7;">0%</div>
        <div class="stat-con" style="text-align: center;color: #58c2f7;">0%</div>
        <div class="stat-con" style="text-align: center;color: #58c2f7;">0%</div>
        <div class="stat-con" style="text-align: right;color: #58c2f7;">0%</div>
    </div>
    <div class="stats-line">
        <div class="stat-con" style="flex-grow: 1; text-align: left;">Unlocked it. You have to beat the goal!</div>
        <div class="stat-con" style="text-align: center;color: #58c2f7;">0%</div>
        <div class="stat-con" style="text-align: center;color: #58c2f7;">0%</div>
        <div class="stat-con" style="text-align: center;color: #58c2f7;">0%</div>
        <div class="stat-con" style="text-align: right;color: #58c2f7;">0%</div>
    </div>
    <div class="stats-line">
        <div class="stat-con" style="flex-grow: 1; text-align: left;">No Cheating! I repeat no cheating!</div>
        <div class="stat-con" style="text-align: center;color: #58c2f7;">0%</div>
        <div class="stat-con" style="text-align: center;color: #58c2f7;">0%</div>
        <div class="stat-con" style="text-align: center;color: #58c2f7;">0%</div>
        <div class="stat-con" style="text-align: right;color: #58c2f7;">0%</div>
    </div>
    <?php
}
// Modified activity_content function with class_id parameter
function activity_content_trial($atts) {
    $atts = shortcode_atts(array(
        'class_id' => 0,
    ), $atts);
    
    $class_id = intval($atts['class_id']);
    
    if (!$class_id) {
        return '<div class="error-message">Class ID is required.</div>';
    }
    
    $user_id = get_current_user_id();
    if (!$user_id) {
        $user_id = 0; // For non-logged in users
    }
    
    // Get the subject/topic for this class
    $exam_topic_id = get_post_meta($class_id, 'exam_topic', true);
    $exam_subject_id = get_post_meta($class_id, 'exam_subject', true);
    $parent_term_id = $exam_topic_id ?: $exam_subject_id;
    
    if (!$parent_term_id) {
        return '<div class="error-message">No categories found for this class.</div>';
    }
    
    // Get subcategories
    $subcategories = get_terms([
        'taxonomy' => 'question_category',
        'parent' => $parent_term_id,
        'hide_empty' => false,
    ]);
    
    if (empty($subcategories) || is_wp_error($subcategories)) {
        return '<div class="error-message">No subcategories found.</div>';
    }
    
    // Check if showing results via AJAX
    if (isset($_GET['show_results']) && isset($_GET['selected_cat'])) {
        return display_trial_results($class_id, intval($_GET['selected_cat']), $subcategories);
    }
    
    // Display category selection and exam container
    return display_category_selection_inline($class_id, $subcategories);
}
add_shortcode('activity_trial', 'activity_content_trial');

// Helper function to get all category IDs including children
function get_category_with_children($category_id) {
    $category_ids = [$category_id];
    
    // Get child categories
    $child_categories = get_terms([
        'taxonomy' => 'question_category',
        'parent' => $category_id,
        'hide_empty' => false,
    ]);
    
    if (!empty($child_categories) && !is_wp_error($child_categories)) {
        foreach ($child_categories as $child) {
            // Recursively get children of children
            $category_ids = array_merge($category_ids, get_category_with_children($child->term_id));
        }
    }
    
    return $category_ids;
}

// Display category selection inline (no overlay)
function display_category_selection_inline($class_id, $subcategories) {
    global $wpdb;
    $questions_table = $wpdb->prefix . 'imported_questions';
    
    // Get current page title
    $page_title = get_the_title();
    
    // Filter out categories with 0 questions (including child categories)
    $categories_with_questions = [];
    foreach ($subcategories as $category) {
        // Get all category IDs including children
        $all_category_ids = get_category_with_children($category->term_id);
        
        // Count questions in parent and all child categories
        $placeholders = implode(',', array_fill(0, count($all_category_ids), '%d'));
        $count = $wpdb->get_var($wpdb->prepare(
            "SELECT COUNT(*) FROM $questions_table WHERE category IN ($placeholders)",
            ...$all_category_ids
        ));
        
        if ($count > 0) {
            $category->question_count = $count;
            $categories_with_questions[] = $category;
        }
    }
    
    // If no categories have questions, show error
    if (empty($categories_with_questions)) {
        return '<div class="error-message">No categories with questions available.</div>';
    }
    
    ob_start();
    lf_loading_screen();
    ?>
    <div class="trial-activity-container" data-class-id="<?php echo $class_id; ?>">
        <div class="category-selection-section" id="category-selection">
            <div class="page-title-section">
                <h1 class="page-title"><?php echo esc_html($page_title); ?></h1>
            </div>
            
            <h2>Choose a Category</h2>
            <p class="subtitle">Select one category to practice questions</p>
            
            <div class="categories-list">
                <?php foreach ($categories_with_questions as $category): ?>
                    <div class="category-card" data-category-id="<?php echo $category->term_id; ?>" data-category-name="<?php echo esc_attr($category->name); ?>">
                        <h3><?php echo esc_html($category->name); ?></h3>
                        <div class="start-button">Start Practice →</div>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        
        <!-- Exam container (initially hidden) -->
        <div class="exam-section" id="exam-section" style="display: none;">
            <!-- Exam content will be loaded here via AJAX -->
        </div>
    </div>
    
    <script>
    jQuery(document).ready(function($) {
        // Handle category card click
        $('.category-card').on('click', function() {
            var categoryId = $(this).data('category-id');
            var categoryName = $(this).data('category-name');
            var classId = $('.trial-activity-container').data('class-id');
            
            // Hide category selection
            $('#category-selection').fadeOut(300, function() {
                // Show loading state
                $('#exam-section').html('<div class="loading-exam"><p>Loading questions...</p></div>').fadeIn(300);
                
                // Load exam via AJAX
                $.ajax({
                    url: '<?php echo admin_url('admin-ajax.php'); ?>',
                    type: 'POST',
                    data: {
                        action: 'load_trial_exam',
                        class_id: classId,
                        category_id: categoryId,
                        category_name: categoryName
                    },
                    success: function(response) {
                        if (response.success) {
                            $('#exam-section').html(response.data.html);
                            initializeExam();
                        } else {
                            $('#exam-section').html('<div class="error-message">' + response.data.message + '</div>');
                        }
                    },
                    error: function() {
                        $('#exam-section').html('<div class="error-message">Error loading exam. Please try again.</div>');
                    }
                });
            });
        });
        
        // Back to category selection
        $(document).on('click', '#back-to-categories', function() {
            $('#exam-section').fadeOut(300, function() {
                $(this).empty();
                $('#category-selection').fadeIn(300);
            });
        });
    });
    </script>
    
    <style>
        .trial-activity-container {
            padding: 40px 20px;
            max-width: 1200px;
            margin: 0 auto;
        }
        
        .category-selection-section {
            text-align: center;
        }
        
        .page-title-section {
            margin-bottom: 30px;
        }
        
        .page-title {
            font-size: 36px;
            color: var(--ast-global-color-2, #2d3748);
            margin-bottom: 10px;
            font-weight: 700;
        }
        
        .category-selection-section h2 {
            font-size: 28px;
            margin-bottom: 10px;
            font-weight: 700;
            color: var(--ast-global-color-2, #2d3748);
        }
        
        .subtitle {
            color: var(--ast-global-color-3, #718096);
            font-size: 16px;
            margin-bottom: 40px;
        }
        
        .categories-list {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(280px, 1fr));
            gap: 25px;
            margin-top: 30px;
        }
        
        .category-card {
            background: var(--ast-global-color-5, white);
            border-radius: 20px;
            padding: 40px 30px;
            text-decoration: none;
            transition: all 0.3s ease;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.1);
            cursor: pointer;
            position: relative;
            overflow: hidden;
            border: 2px solid var(--ast-global-color-4, #e2e8f0);
        }
        
        .category-card::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            height: 5px;
            background: linear-gradient(90deg, var(--ast-global-color-0, #667eea), var(--ast-global-color-1, #764ba2));
            transform: scaleX(0);
            transition: transform 0.3s ease;
        }
        
        .category-card:hover::before {
            transform: scaleX(1);
        }
        
        .category-card:hover {
            transform: translateY(-10px);
            box-shadow: 0 15px 40px rgba(0, 0, 0, 0.15);
        }
        
        .category-card h3 {
            color: var(--ast-global-color-2, #2d3748);
            font-size: 22px;
            margin-bottom: 15px;
            font-weight: 600;
        }
        
        .start-button {
            display: inline-block;
            background: linear-gradient(90deg, var(--ast-global-color-0, #667eea), var(--ast-global-color-0, #764ba2));
            color: var(--ast-global-color-5, white);
            padding: 12px 30px;
            border-radius: 10px;
            font-weight: 600;
            font-size: 14px;
            transition: all 0.3s ease;
        }
        
        .category-card:hover .start-button {
            transform: scale(1.05);
            box-shadow: 0 5px 15px rgba(102, 126, 234, 0.4);
        }
        
        .loading-exam {
            text-align: center;
            padding: 60px 20px;
            font-size: 18px;
            color: var(--ast-global-color-3, #718096);
        }
        
        .error-message {
            background: #fee;
            color: #c33;
            padding: 20px;
            border-radius: 10px;
            text-align: center;
            margin: 20px 0;
        }
        
        @media (max-width: 768px) {
            .trial-activity-container {
                padding: 20px 10px;
            }
            
            .page-title {
                font-size: 28px;
            }
            
            .category-selection-section h2 {
                font-size: 24px;
            }
            
            .subtitle {
                font-size: 14px;
            }
            
            .categories-list {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .category-card {
                padding: 30px 20px;
            }
        }
    </style>
    <?php
    return ob_get_clean();
}

// AJAX handler to load trial exam
add_action('wp_ajax_load_trial_exam', 'ajax_load_trial_exam');
add_action('wp_ajax_nopriv_load_trial_exam', 'ajax_load_trial_exam');

function ajax_load_trial_exam() {
    global $wpdb;
    $questions_table = $wpdb->prefix . 'imported_questions';
    
    $class_id = intval($_POST['class_id']);
    $cat_id = intval($_POST['category_id']);
    $category_name = sanitize_text_field($_POST['category_name']);
    
    if (!$class_id || !$cat_id) {
        wp_send_json_error(['message' => 'Invalid parameters']);
        return;
    }
    
    // Get all questions from selected category and its subcategories
    $category_ids = [$cat_id];
    
    // Get child categories
    $child_categories = get_terms([
        'taxonomy' => 'question_category',
        'parent' => $cat_id,
        'hide_empty' => false,
    ]);
    
    if (!empty($child_categories) && !is_wp_error($child_categories)) {
        $category_ids = array_merge($category_ids, wp_list_pluck($child_categories, 'term_id'));
    }
    
    // Get 15 random questions from these categories
    $placeholders = implode(',', array_fill(0, count($category_ids), '%d'));
    $selected_questions = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $questions_table 
         WHERE category IN ($placeholders) 
         ORDER BY RAND() 
         LIMIT 15",
        ...$category_ids
    ), ARRAY_A);
    
    if (empty($selected_questions)) {
        wp_send_json_error(['message' => 'No questions available for this category.']);
        return;
    }
    
    $exam_title = $category_name . ' - Trial Practice';
    $html = generate_flashcard_exam_html($class_id, $selected_questions, $exam_title, $cat_id);
    
    wp_send_json_success(['html' => $html]);
}

// Generate flashcard exam HTML
function generate_flashcard_exam_html($class_id, $selected_questions, $exam_title, $selected_cat_id) {
    $user_id = get_current_user_id();
    if (!$user_id) {
        $user_id = 0;
    }
    
    // Generate session ID based on timestamp and user
    $session_id = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 7);
    
    $random_questions = $selected_questions;
    shuffle($random_questions);

    ob_start();
    ?>
    <div class="trial-exam-content">
        <div class="exam-header">
            <button type="button" id="back-to-categories" class="back-button">← Back to Categories</button>
            <h4 class="exam-title"><?php echo esc_html($exam_title); ?></h4>
        </div>
        
        <form method="post" action="" id="flashcard-exam-form">
            <div class="progress-container">
                <div class="progress-bar" id="progress-bar"></div>
            </div>

            <style>
                .trial-exam-content {
                    max-width: 800px;
                    margin: 0 auto;
                    padding: 20px;
                }
                
                .exam-header {
                    display: flex;
                    align-items: center;
                    gap: 20px;
                    margin-bottom: 30px;
                }
                
                .back-button {
                    background: var(--ast-global-color-5, white);
                    border: 2px solid var(--ast-global-color-4, #e2e8f0);
                    color: var(--ast-global-color-2, #2d3748);
                    padding: 10px 20px;
                    border-radius: 8px;
                    cursor: pointer;
                    font-weight: 600;
                    transition: all 0.3s ease;
                }
                
                .back-button:hover {
                    background: var(--ast-global-color-4, #f7fafc);
                    transform: translateX(-5px);
                }
                
                .exam-title {
                    margin: 0;
                    color: var(--ast-global-color-2, #2d3748);
                    font-size: 24px;
                    font-weight: 700;
                }
                
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
                    transition: transform 0.6s, left 0.3s, opacity 0.3s ease-in-out;
                    left: 0;
                    opacity: 1;
                }
                
                .fade-in {
                    opacity: 0;
                    animation: fadeIn 0.3s forwards;
                }
                
                .fade-out {
                    opacity: 1;
                    animation: fadeOut 0.3s forwards;
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
                    justify-content: flex-start;
                    align-items: center;
                    border-radius: 10px;
                    padding: 20px;
                    box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                    background: var(--ast-global-color-5, white);
                    color: var(--ast-global-color-2, black);
                    overflow-y: auto;
                }
                
                .flashcard-back {
                    transform: rotateY(180deg);
                }
                
                .question-image-container {
                    width: 100%;
                    max-width: 500px;
                    margin: 15px auto;
                    text-align: center;
                    position: relative;
                }
                
                .question-image {
                    max-width: 100%;
                    height: auto;
                    max-height: 250px;
                    border-radius: 8px;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.15);
                    object-fit: contain;
                    cursor: pointer;
                }
                
                .options-grid {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 10px;
                    width: 100%;
                    height: 100%;
                }
                
                .option-container {
                    background-color: var(--ast-global-color-5, #fff);
                    border: 2px solid var(--ast-global-color-4, #ddd);
                    border-radius: 10px;
                    padding: 15px;
                    cursor: pointer;
                    transition: all 0.3s;
                    color: var(--ast-global-color-2, black);
                    display: flex;
                    align-items: center;
                    height: 100%;
                    flex-wrap: nowrap;
                    justify-content: center;
                    box-shadow: 0 2px 8px rgba(0,0,0,0.08);
                }
                
                .option-container:hover {
                    background-color: var(--ast-global-color-4, #f5f5f5);
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(0,0,0,0.12);
                }
                
                .option-container.selected {
                    background-color: #e0e0e0;
                }
                
                .option-container.correct-answer {
                    background-color: #c1e2b3;
                    border-color: #10b981;
                }
                
                .option-container.wrong-answer {
                    background-color: #ffcccc;
                    border-color: #ef4444;
                }
                
                .button-container {
                    display: flex;
                    flex-direction: column;
                    align-items: center;
                    gap: 10px;
                    margin-top: 20px;
                }
                
                .nav-buttons {
                    display: flex;
                    justify-content: center;
                    gap: 10px;
                    width: 100%;
                }
                
                .nav-buttons input[type="button"] {
                    flex: 1;
                    max-width: 200px;
                }
                
                .show-solution {
                    margin-top: 15px;
                    padding: 8px 20px;
                    background: var(--ast-global-color-0, #667eea);
                    color: white;
                    border-radius: 8px;
                    cursor: pointer;
                    display: none;
                    font-weight: 600;
                    transition: all 0.3s ease;
                }
                
                .show-solution:hover {
                    background: var(--ast-global-color-1, #764ba2);
                    transform: translateY(-2px);
                }
                
                .progress-container {
                    margin-bottom: 20px;
                    background: var(--ast-global-color-4, #e2e8f0);
                    border-radius: 10px;
                    overflow: hidden;
                }
                
                .progress-bar {
                    height: 12px;
                    width: 0;
                    background: linear-gradient(90deg, var(--ast-global-color-0, #667eea), var(--ast-global-color-1, #764ba2));
                    transition: width 0.5s ease;
                    border-radius: 10px;
                }
                
                .button-primary {
                    min-height: 50px;
                    padding: 12px 30px;
                    border-radius: 10px;
                    font-weight: 600;
                    border: none;
                    cursor: pointer;
                    transition: all 0.3s ease;
                }
                
                .button-primary:hover {
                    transform: translateY(-2px);
                    box-shadow: 0 4px 12px rgba(0,0,0,0.15);
                }
                
                @keyframes fadeIn {
                    from { opacity: 0; }
                    to { opacity: 1; }
                }
                
                @keyframes fadeOut {
                    from { opacity: 1; }
                    to { opacity: 0; }
                }
                
                @media (max-width: 768px) {
                    .trial-exam-content {
                        padding: 10px;
                    }
                    
                    .exam-header {
                        flex-direction: column;
                        align-items: flex-start;
                        gap: 10px;
                    }
                    
                    .exam-title {
                        font-size: 20px;
                    }
                    
                    .options-grid {
                        grid-template-columns: 1fr;
                    }
                }
            </style>

            <div class="flashcard-container">
                <?php foreach ($random_questions as $index => $question): 
                    $question_id = $question['post_id'];
                    $question_title = esc_html($question['title']);
                    
                    $multiple_choice_options = array(
                        'A' => $question['option_a'],
                        'B' => $question['option_b'],
                        'C' => $question['option_c'],
                        'D' => $question['option_d']
                    );
                    
                    $correct_answer = $question['correct_answer'];
                    $question_solution = $question['solution'];
                    $image_url = $question['image_url'];
                ?>
                    <div class='flashcard' id='flashcard-<?php echo $index; ?>'>
                        <div class='flashcard-front'>
                            <h6 style="color: var(--ast-global-color-2, #000); text-align: center;"> <?php echo $question_title; ?></h6>
                            
                            <?php if (!empty($image_url)): ?>
                                <div class="question-image-container">
                                    <img src="<?php echo esc_url($image_url); ?>" 
                                         alt="Question Image" 
                                         class="question-image">
                                </div>
                            <?php endif; ?>
                            
                            <div class="options-grid">
                                <?php 
                                $shuffled_letters = ['A', 'B', 'C', 'D'];
                                shuffle($shuffled_letters);
                                foreach ($shuffled_letters as $option):
                                    $optionId = "option-$index-$option";
                                    $isCorrect = ($option === $correct_answer);
                                ?>
                                    <div class='option-container' 
                                         onclick="selectOption('<?php echo $optionId; ?>', '<?php echo $correct_answer; ?>', <?php echo $index; ?>)" 
                                         id='option-container-<?php echo $optionId; ?>'>
                                        <input hidden type='radio' name='user_answers[<?php echo $question_id; ?>]' value='<?php echo $option; ?>' id='<?php echo $optionId; ?>'>
                                        <div style="color: var(--ast-global-color-2, #000); margin-bottom:0px; text-align:center;" for='<?php echo $optionId; ?>'>
                                            <?php echo esc_html($multiple_choice_options[$option]); ?>
                                        </div>
                                    </div>
                                <?php endforeach; ?>
                            </div>
                            <div class="show-solution" id="show-solution-<?php echo $index; ?>" onclick="flipCard(<?php echo $index; ?>)">Show Solution</div>
                        </div>
                        <div class='flashcard-back'>
                            <h5 style="color: var(--ast-global-color-2, #000); text-align: center;"><strong>Question:</strong></h5>
                            <h5 style="color: var(--ast-global-color-2, #000); text-align: center;"> <?php echo $question_title; ?></h5>
                            
                            <?php if (!empty($image_url)): ?>
                                <div class="question-image-container">
                                    <img src="<?php echo esc_url($image_url); ?>" 
                                         alt="Question Image" 
                                         class="question-image">
                                </div>
                            <?php endif; ?>
                            
                            <h5 style="color: var(--ast-global-color-2, #000); text-align: center;"><strong>Answer:</strong></h5>
                            <h4 style="color: var(--ast-global-color-2, #000); text-align: center;"> <?php echo esc_html($multiple_choice_options[$correct_answer]); ?> </h4>
                            <?php 
                            if (!empty($question_solution)) {
                            ?>
                            <h5 style="color: var(--ast-global-color-2, #000); text-align: center;">Explanation:</h5>
                            <p style="color: var(--ast-global-color-2, #000); text-align: center;"><?php echo nl2br(esc_html($question_solution)); ?></p>
                            <?php
                            }
                            ?>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="button-container">
                <div class="nav-buttons">
                    <input type="button" value="Previous" class="button-primary" id="prev-question" style="display:none;">
                    <input type="button" value="Next" class="button-primary" id="next-question">
                </div>
                <input type="button" value="Finish Practice" class="button-primary" id="finish-practice" style="display:none; background: linear-gradient(90deg, #10b981, #059669); border: none;">
            </div>
        </form>
    </div>

    <script>
    function initializeExam() {
        var totalQuestions = <?php echo count($random_questions); ?>;
        var currentIndex = 0;
        var flashcards = document.querySelectorAll('.flashcard');
        var nextButton = document.getElementById('next-question');
        var prevButton = document.getElementById('prev-question');
        var finishButton = document.getElementById('finish-practice');
        var progressBar = document.getElementById('progress-bar');
        var answeredQuestions = new Array(totalQuestions).fill(false);
        var userAnswers = [];
        var sessionId = '<?php echo $session_id; ?>';

        function showFlashcard(index, direction) {
            flashcards.forEach(function(flashcard) {
                flashcard.style.display = 'none';
                flashcard.classList.remove('flip');
            });
            
            var currentFlashcard = flashcards[currentIndex];
            var targetFlashcard = flashcards[index];
            
            if (direction === 'left') {
                currentFlashcard.classList.add('slide-left');
                targetFlashcard.classList.add('slide-right');
                
                targetFlashcard.classList.add('fade-out');
                currentFlashcard.classList.add('fade-in');
                setTimeout(function() {
                    targetFlashcard.classList.remove('fade-out');
                    currentFlashcard.classList.remove('fade-in');
                }, 500);
            } else if (direction === 'right') {
                currentFlashcard.classList.add('slide-right');
                targetFlashcard.classList.add('slide-left');
                
                targetFlashcard.classList.add('fade-in');
                currentFlashcard.classList.add('fade-out');
                setTimeout(function() {
                    targetFlashcard.classList.remove('fade-in');
                    currentFlashcard.classList.remove('fade-out');
                }, 500);
            }
            
            currentFlashcard.style.display = 'block';
            targetFlashcard.style.display = 'block';
            
            setTimeout(function() {
                currentFlashcard.classList.remove('slide-left', 'slide-right');
                targetFlashcard.classList.remove('slide-left', 'slide-right');
                currentFlashcard.style.display = 'none';
                targetFlashcard.style.display = 'block';
                currentIndex = index;
                updateNavigation();
            }, 300);

            var progressWidth = (index + 1) / totalQuestions * 100;
            progressBar.style.width = progressWidth + '%';
            
            if (answeredQuestions[index]) {
                showAnswerStatus(index);
            }
        }

        function updateNavigation() {
            prevButton.style.display = currentIndex > 0 ? 'inline-block' : 'none';
            
            if (currentIndex === totalQuestions - 1) {
                nextButton.style.display = 'none';
                finishButton.style.display = 'inline-block';
            } else {
                nextButton.style.display = 'inline-block';
                finishButton.style.display = 'none';
            }
        }

        window.selectOption = function(optionId, correctAnswer, flashcardIndex) {
            var radioElement = document.getElementById(optionId);
            if (radioElement && !answeredQuestions[flashcardIndex]) {
                radioElement.checked = true;
                answeredQuestions[flashcardIndex] = true;
                
                var questionId = radioElement.name.match(/\d+/)[0];
                var userAnswer = radioElement.value;
                
                userAnswers.push({
                    question_id: questionId,
                    user_answer: userAnswer,
                    correct_answer: correctAnswer,
                    is_correct: userAnswer === correctAnswer
                });
                
                showAnswerStatus(flashcardIndex, correctAnswer);

                jQuery.ajax({
                    url: "<?php echo admin_url('admin-ajax.php'); ?>",
                    type: "POST",
                    data: {
                        action: "save_trial_answer",
                        class_id: <?php echo $class_id; ?>,
                        category_id: <?php echo $selected_cat_id; ?>,
                        question_id: questionId,
                        user_answer: userAnswer,
                        session_id: sessionId,
                        user_id: <?php echo $user_id; ?>
                    },
                    success: function (response) {
                        console.log("Saved:", response);

                        setTimeout(function () {
                            if (currentIndex < totalQuestions - 1) {
                                showFlashcard(currentIndex + 1, "left");
                            }
                        }, 800);
                    },
                    error: function (xhr) {
                        console.error("Error saving:", xhr.responseText);
                    }
                });

                setTimeout(function () {
                    updateShowSolutionButton(flashcardIndex);
                }, 1200);
            }
        }

        function showAnswerStatus(flashcardIndex, correctAnswer) {
            var flashcard = document.getElementById('flashcard-' + flashcardIndex);
            var allOptions = flashcard.querySelectorAll('.option-container');
            var selectedOption = flashcard.querySelector('input[type="radio"]:checked');
            
            if (selectedOption) {
                var isCorrect = selectedOption.value === correctAnswer;
                
                allOptions.forEach(function(option) {
                    option.classList.remove('selected', 'correct-answer', 'wrong-answer');
                    if (option.querySelector('input') === selectedOption) {
                        option.classList.add('selected');
                        option.classList.add(isCorrect ? 'correct-answer' : 'wrong-answer');
                    } else if (option.querySelector('input').value === correctAnswer) {
                        option.classList.add('correct-answer');
                    }
                    option.style.pointerEvents = 'none';
                });
            }
        }

        window.flipCard = function(index) {
            flashcards[index].classList.toggle('flip');
        }

        nextButton.addEventListener('click', function() {
            showFlashcard(currentIndex + 1, 'left');
        });

        prevButton.addEventListener('click', function() {
            showFlashcard(currentIndex - 1, 'right');
        });
        
        finishButton.addEventListener('click', function() {
            showResults();
        });
        
        function updateShowSolutionButton(index) {
            var showSolutionButton = document.getElementById('show-solution-' + index);
            if (showSolutionButton) {
                showSolutionButton.style.display = answeredQuestions[index] ? 'block' : 'none';
            }
        }
        
        function showResults() {
            window.location.href = '?class_id=<?php echo $class_id; ?>&selected_cat=<?php echo $selected_cat_id; ?>&show_results=1&session_id=' + sessionId;
        }

        showFlashcard(currentIndex);
    }
    </script>
    <?php
    return ob_get_clean();
}

// Keep the existing save_trial_answer and display_trial_results functions as they are
add_action('wp_ajax_save_trial_answer', 'save_trial_answer');
add_action('wp_ajax_nopriv_save_trial_answer', 'save_trial_answer');

function save_trial_answer() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'exam_answers';
    $questions_table = $wpdb->prefix . 'imported_questions';

    $user_id = isset($_POST['user_id']) ? intval($_POST['user_id']) : 0;
    $class_id = intval($_POST['class_id']);
    $category_id = intval($_POST['category_id']);
    $question_id = intval($_POST['question_id']);
    $user_answer = sanitize_text_field($_POST['user_answer']);
    $session_id = sanitize_text_field($_POST['session_id']);

    $question_data = $wpdb->get_row(
        $wpdb->prepare(
            "SELECT correct_answer, category FROM $questions_table WHERE post_id = %d",
            $question_id
        ),
        ARRAY_A
    );
    
    if (!$question_data) {
        wp_send_json_error(['message' => 'Question not found']);
        return;
    }
    
    $correct_answer = $question_data['correct_answer'];
    $is_correct = ($user_answer === $correct_answer) ? '1' : '0';

    $inserted = $wpdb->insert(
        $table_name,
        [
            'exam_id' => 0,
            'class_id' => $class_id,
            'exam_name' => 'Trial',
            'question_id' => $question_id,
            'user_id' => $user_id,
            'user_answer' => $user_answer,
            'is_correct' => $is_correct,
            'timestamp' => current_time('mysql'),
            'session_id' => $session_id,
            'question_category' => $question_data['category']
        ]
    );

    if ($inserted === false) {
        wp_send_json_error(['message' => 'Database insert failed']);
    } else {
        wp_send_json_success("Answer saved");
    }
}


function display_trial_results($class_id, $selected_cat_id, $all_subcategories) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'exam_answers';
    $user_id = get_current_user_id();
    if (!$user_id) {
        $user_id = 0;
    }
    
    // Get session_id from URL if available
    $session_id = isset($_GET['session_id']) ? sanitize_text_field($_GET['session_id']) : '';
    
    if (empty($session_id)) {
        // Fallback to latest session for this user
        $session_id = $wpdb->get_var($wpdb->prepare(
            "SELECT session_id FROM $table_name 
             WHERE user_id = %d AND class_id = %d AND exam_name = 'Trial'
             ORDER BY timestamp DESC LIMIT 1",
            $user_id, $class_id
        ));
    }
    
    // Get user's results for the session
    $user_results = $wpdb->get_results($wpdb->prepare(
        "SELECT * FROM $table_name 
         WHERE session_id = %s",
        $session_id
    ), ARRAY_A);
    
    $total_questions = count($user_results);
    $correct_answers = array_filter($user_results, function($r) { return $r['is_correct'] == '1'; });
    $user_score = $total_questions > 0 ? round((count($correct_answers) / $total_questions) * 100, 2) : 0;
    
    // Get all results for this class to calculate overall average (similar to display_analytics_graph)
    $all_results = $wpdb->get_results($wpdb->prepare(
        "SELECT question_id, user_id, is_correct, session_id, question_category 
         FROM $table_name 
         WHERE class_id = %d AND exam_name = 'Trial'
         ORDER BY timestamp ASC",
        $class_id
    ), ARRAY_A);
    
    // Get all unique users who participated in this category
    $all_users = [];
    foreach ($all_results as $result) {
        if ($result['question_category'] == $selected_cat_id) {
            $all_users[] = $result['user_id'];
        }
    }
    $all_users = array_unique($all_users);
    
    // Calculate scores for all users in this category using the same method as analytics
    $user_scores = [];
    foreach ($all_users as $uid) {
        if (function_exists('calculate_category_score_from_db')) {
            $score = calculate_category_score_from_db($all_results, $uid, $selected_cat_id, "overall");
            if ($score > 0) {
                $user_scores[] = $score;
            }
        }
    }
    
    // Calculate average of all users who attempted questions in this category
    $average_score = !empty($user_scores) ? round(array_sum($user_scores) / count($user_scores), 2) : 0;
    
    // Calculate overall class accuracy (all categories)
    $exam_topic = get_post_meta($class_id, 'exam_topic', true);
    $exam_subject = get_post_meta($class_id, 'exam_subject', true);
    $parent_term = $exam_topic ?: $exam_subject;
    
    // Get all questions in this subject/topic tree
    $subject_question_ids = [];
    if ($parent_term && function_exists('get_questions_in_category_tree')) {
        $subject_question_ids = get_questions_in_category_tree($parent_term);
    }
    
    // Calculate class-wide accuracy
    $class_results = $wpdb->get_results($wpdb->prepare(
        "SELECT is_correct FROM $table_name WHERE class_id = %d AND exam_name = 'Trial'",
        $class_id
    ), ARRAY_A);
    
    $class_total = count($class_results);
    $class_correct = count(array_filter($class_results, fn($r) => $r['is_correct'] == '1'));
    $class_accuracy = $class_total > 0 ? round(($class_correct / $class_total) * 100, 1) : 0;

    ob_start();
    ?>
    <div class="trial-results-wrapper">
        <div class="results-container">
            <div class="results-header">
                <h2>🎉 Practice Complete!</h2>
                <p>Here's how you performed</p>
            </div>
            
            <div class="class-overview-stats">
                <div class="overview-stat-card">
                    <div class="stat-icon">📊</div>
                    <div class="stat-content">
                        <div class="stat-label">National Accuracy</div>
                        <div class="stat-value"><?php echo $class_accuracy; ?>%</div>
                        <div class="stat-detail">Across all trial practice sessions</div>
                    </div>
                </div>
            </div>
            
            <div class="legend">
                <div class="legend-item">
                    <div class="legend-color user-color"></div>
                    <span>Your Performance</span>
                </div>
                <div class="legend-item">
                    <div class="legend-color average-color"></div>
                    <span>Overall Accuracy</span>
                </div>
            </div>
            
            <div class="categories-grid">
                <?php 
                $selected_category = get_term_by('id', $selected_cat_id, 'question_category');
                
                // Display selected category analytics
                ?>
                <div class="category-chart-wrapper unlocked-chart">
                    <h4 class="category-title"><?php echo esc_html($selected_category->name); ?></h4>
                    
                    <div class="single-chart-container">
                        <div class="chart-wrapper">
                            <div class="y-axis">
                                <div class="y-label">100%</div>
                                <div class="y-label">75%</div>
                                <div class="y-label">50%</div>
                                <div class="y-label">25%</div>
                                <div class="y-label">0%</div>
                            </div>
                            
                            <div class="chart-content">
                                <div class="category-group">
                                    <div class="category-bars">
                                        <div class="bar-pair">
                                            <div class="bar user-bar" 
                                                 data-score="<?php echo $user_score; ?>" 
                                                 style="height: <?php echo $user_score == 0 ? '2px' : max(($user_score / 100) * 100, 2) . '%'; ?>;">
                                                <div class="bar-value"><?php echo $user_score; ?>%</div>
                                            </div>
                                            <div class="bar average-bar" 
                                                 data-score="<?php echo $average_score; ?>" 
                                                 style="height: <?php echo $average_score == 0 ? '2px' : max(($average_score / 100) * 100, 2) . '%'; ?>;">
                                                <div class="bar-value"><?php echo $average_score; ?>%</div>
                                            </div>
                                        </div>
                                        <div class="bar-labels">
                                            <div class="label user-label">You</div>
                                            <div class="label avg-label">Avg</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="category-stats">
                        <div class="stat-item">
                            <span class="stat-label">Your Accuracy:</span>
                            <span class="stat-value user-color"><?php echo $user_score . '%'; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">National Accuracy:</span>
                            <span class="stat-value average-color"><?php echo $average_score . '%'; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Questions Answered:</span>
                            <span class="stat-value"><?php echo count($correct_answers) . ' / ' . $total_questions; ?> correct</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Difference:</span>
                            <span class="stat-value <?php echo $user_score >= $average_score ? 'positive' : 'negative'; ?>">
                                <?php 
                                echo $user_score >= $average_score ? '+' : '';
                                echo ($user_score - $average_score);
                                ?>%
                            </span>
                        </div>
                    </div>
                </div>
                
                <?php 
                // Display other categories as locked with blurred graphs
                foreach ($all_subcategories as $category):
                    if ($category->term_id == $selected_cat_id) continue;
                    $random_user_score = rand(60, 85);
                    $random_avg_score = rand(60, 85); // Fixed number for overall average
                ?>
                <div class="category-chart-wrapper locked-chart">
                    <div class="locked-overlay">
                        <div class="lock-content">
                            <div class="lock-icon">🔒</div>
                            <h4>Unlock This Category</h4>
                            <p>Sign up to access detailed analytics</p>
                            <a href="<?php echo home_url('/checkout?class=' . $class_id); ?>" class="unlock-btn">
                                Unlock Now
                            </a>
                        </div>
                    </div>
                    
                    <h4 class="category-title"><?php echo esc_html($category->name); ?></h4>
                    
                    <div class="single-chart-container blurred-content">
                        <div class="chart-wrapper">
                            <div class="y-axis">
                                <div class="y-label">100%</div>
                                <div class="y-label">75%</div>
                                <div class="y-label">50%</div>
                                <div class="y-label">25%</div>
                                <div class="y-label">0%</div>
                            </div>
                            
                            <div class="chart-content">
                                <div class="category-group">
                                    <div class="category-bars">
                                        <div class="bar-pair">
                                            <div class="bar user-bar" 
                                                 style="height: <?php echo max(($random_user_score / 100) * 100, 2) . '%'; ?>;">
                                                <div class="bar-value"><?php echo $random_user_score; ?>%</div>
                                            </div>
                                            <div class="bar average-bar" 
                                                 style="height: <?php echo max(($random_avg_score / 100) * 100, 2) . '%'; ?>;">
                                                <div class="bar-value"><?php echo $random_avg_score; ?>%</div>
                                            </div>
                                        </div>
                                        <div class="bar-labels">
                                            <div class="label user-label">You</div>
                                            <div class="label avg-label">Avg</div>
                                        </div>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>
                    
                    <div class="category-stats blurred-content">
                        <div class="stat-item">
                            <span class="stat-label">Your Accuracy:</span>
                            <span class="stat-value user-color"><?php echo $random_user_score; ?>%</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Overall Accuracy:</span>
                            <span class="stat-value average-color"><?php echo $random_avg_score; ?>%</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Questions Answered:</span>
                            <span class="stat-value">-- / --</span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Not Real Data(Sign Up to reveal):</span>
                            <span class="stat-value">-- / --</span>
                        </div>
                    </div>
                </div>
                <?php endforeach; ?>
            </div>
            
            <div class="results-actions">
                <a href="<?php echo home_url('/checkout?class=' . $class_id); ?>" class="signup-button">
                    🚀 Sign Up to Unlock Full Access
                </a>
                <a href="?class_id=<?php echo $class_id; ?>" class="secondary-button">
                    Try Another Category
                </a>
            </div>
        </div>
    </div>
    <style>
    .trial-results-wrapper {
        padding: 40px 20px;
        background: var(--ast-global-color-4, #f7fafc);
        min-height: 100vh;
    }
    
    .results-container {
        max-width: 1200px;
        margin: 0 auto;
        background: var(--ast-global-color-5, white);
        border-radius: 20px;
        padding: 40px;
        box-shadow: 0 20px 60px rgba(0, 0, 0, 0.1);
    }
    
    .results-header {
        text-align: center;
        margin-bottom: 30px;
    }
    
    .results-header h2 {
        font-size: 36px;
        color: var(--ast-global-color-2, #2d3748);
        margin-bottom: 10px;
        font-weight: 700;
    }
    
    .results-header p {
        color: var(--ast-global-color-3, #718096);
        font-size: 18px;
    }
    
    .class-overview-stats {
        display: flex;
        justify-content: center;
        margin-bottom: 30px;
    }
    
    .overview-stat-card {
        background: var(--ast-global-color-0, #667eea);
        border-radius: 15px;
        padding: 25px 40px;
        display: flex;
        align-items: center;
        gap: 20px;
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.3);
        max-width: 500px;
        width: 100%;
    }
    
    .stat-icon {
        font-size: 48px;
        filter: drop-shadow(0 2px 4px rgba(0, 0, 0, 0.1));
    }
    
    .stat-content {
        flex: 1;
    }
    
    .overview-stat-card .stat-label {
        color: rgba(255, 255, 255, 0.9);
        font-size: 14px;
        font-weight: 600;
        text-transform: uppercase;
        letter-spacing: 1px;
        margin-bottom: 8px;
    }
    
    .overview-stat-card .stat-value {
        color: white;
        font-size: 42px;
        font-weight: 800;
        line-height: 1;
        margin-bottom: 5px;
    }
    
    .overview-stat-card .stat-detail {
        color: rgba(255, 255, 255, 0.8);
        font-size: 13px;
    }
    
    .legend {
        display: flex;
        justify-content: center;
        gap: 30px;
        margin-bottom: 30px;
        flex-wrap: wrap;
    }
    
    .legend-item {
        display: flex;
        align-items: center;
        gap: 8px;
    }
    
    .legend-color {
        width: 20px;
        height: 20px;
        border-radius: 4px;
    }
    
    .legend-color.user-color {
        background: linear-gradient(135deg, #ef4444, #dc2626);
    }
    
    .legend-color.average-color {
        background: linear-gradient(135deg, #3b82f6, #2563eb);
    }
    
    .categories-grid {
        display: grid;
        grid-template-columns: repeat(auto-fit, minmax(320px, 1fr));
        gap: 30px;
        margin-bottom: 40px;
    }
    
    .category-chart-wrapper {
        background: var(--ast-global-color-5, white);
        border-radius: 15px;
        padding: 25px;
        box-shadow: 0 4px 15px rgba(0, 0, 0, 0.08);
        border: 2px solid var(--ast-global-color-4, #e2e8f0);
        position: relative;
        transition: all 0.3s ease;
    }
    
    .category-chart-wrapper.unlocked-chart {
        border-color: #10b981;
    }
    
    .category-chart-wrapper.unlocked-chart:hover {
        transform: translateY(-5px);
        box-shadow: 0 8px 25px rgba(0, 0, 0, 0.12);
    }
    
    .category-chart-wrapper.locked-chart {
        overflow: hidden;
    }
    
    .locked-overlay {
        position: absolute;
        top: 0;
        left: 0;
        right: 0;
        bottom: 0;
        background: rgba(255, 255, 255, 0.1);
        display: flex;
        align-items: center;
        justify-content: center;
        z-index: 10;
        border-radius: 15px;
    }
    
    .lock-content {
        text-align: center;
        padding: 20px;
    }
    
    .lock-icon {
        font-size: 48px;
        margin-bottom: 15px;
        opacity: 0.7;
    }
    
    .lock-content h4 {
        color: var(--ast-global-color-2, #2d3748);
        font-size: 18px;
        margin-bottom: 10px;
        font-weight: 600;
    }
    
    .lock-content p {
        color: var(--ast-global-color-3, #718096);
        font-size: 14px;
        margin-bottom: 15px;
    }
    
    .blurred-content {
        filter: blur(6px);
        opacity: 0.4;
    }
    
    .category-title {
        font-size: 20px;
        font-weight: 700;
        color: var(--ast-global-color-2, #2d3748);
        margin-bottom: 20px;
        text-align: center;
        padding-bottom: 15px;
        border-bottom: 2px solid var(--ast-global-color-4, #e2e8f0);
    }
    
    .single-chart-container {
        margin-bottom: 25px;
    }
    
    .chart-wrapper {
        display: flex;
        gap: 15px;
        align-items: stretch;
        min-height: 250px;
    }
    
    .y-axis {
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 10px 0;
    }
    
    .y-label {
        font-size: 12px;
        color: var(--ast-global-color-3, #718096);
        font-weight: 600;
    }
    
    .chart-content {
        flex: 1;
        display: flex;
        align-items: flex-end;
        border-left: 2px solid var(--ast-global-color-4, #e2e8f0);
        border-bottom: 2px solid var(--ast-global-color-4, #e2e8f0);
        padding: 10px 20px;
        position: relative;
    }
    
    .category-group {
        flex: 1;
        display: flex;
        justify-content: center;
    }
    
    .category-bars {
        width: 100%;
        max-width: 150px;
    }
    
    .bar-pair {
        display: flex;
        gap: 15px;
        height: 100%;
        min-height: 200px;
        align-items: flex-end;
        justify-content: center;
        margin-bottom: 10px;
    }
    
    .bar {
        flex: 1;
        max-width: 50px;
        border-radius: 8px 8px 0 0;
        position: relative;
        transition: height 0.8s ease;
        display: flex;
        align-items: flex-start;
        justify-content: center;
        padding-top: 8px;
        box-shadow: 0 -2px 8px rgba(0, 0, 0, 0.1);
    }
    
    .bar.user-bar {
        background: linear-gradient(to top, #dc2626, #ef4444);
    }
    
    .bar.average-bar {
        background: linear-gradient(to top, #2563eb, #3b82f6);
    }
    
    .bar-value {
        color: var(--ast-global-color-5, white);
        font-size: 13px;
        font-weight: 700;
        text-shadow: 0 1px 2px rgba(0, 0, 0, 0.2);
    }
    
    .bar-labels {
        display: flex;
        gap: 15px;
        justify-content: center;
        margin-top: 8px;
    }
    
    .label {
        flex: 1;
        max-width: 50px;
        text-align: center;
        font-size: 12px;
        font-weight: 600;
        color: var(--ast-global-color-3, #64748b);
    }
    
    .category-stats {
        background: var(--ast-global-color-4, #f8fafc);
        border-radius: 10px;
        padding: 20px;
    }
    
    .stat-item {
        display: flex;
        justify-content: space-between;
        align-items: center;
        padding: 8px 0;
        border-bottom: 1px solid var(--ast-global-color-4, #e2e8f0);
    }
    
    .stat-item:last-child {
        border-bottom: none;
    }
    
    .stat-label {
        font-size: 13px;
        color: var(--ast-global-color-3, #64748b);
        font-weight: 600;
    }
    
    .stat-value {
        font-size: 15px;
        font-weight: 700;
        color: var(--ast-global-color-2, #2d3748);
    }
    
    .stat-value.user-color {
        color: #ef4444;
    }
    
    .stat-value.average-color {
        color: #3b82f6;
    }
    
    .stat-value.positive {
        color: #10b981;
    }
    
    .stat-value.negative {
        color: #ef4444;
    }
    
    .results-actions {
        display: flex;
        flex-direction: column;
        gap: 15px;
        align-items: center;
        margin-top: 40px;
    }
    
    /* Unified button styles */
    .signup-button,
    .unlock-btn,
    .secondary-button {
        display: inline-block;
        text-decoration: none;
        padding: 15px 35px;
        border-radius: 10px;
        font-size: 16px;
        font-weight: 600;
        transition: all 0.3s ease;
        text-align: center;
    }
    
    /* Primary button - filled */
    .signup-button,
    .unlock-btn {
        background: var(--ast-global-color-0, #667eea);
        color: var(--ast-global-color-5, white);
        border: 2px solid var(--ast-global-color-0, #667eea);
    }
    
    .signup-button:hover,
    .unlock-btn:hover {
        transform: translateY(-2px);
        box-shadow: 0 8px 20px rgba(102, 126, 234, 0.4);
        color: var(--ast-global-color-5, white);
        text-decoration: none;
    }
    
    /* Secondary button - outline only */
    .secondary-button {
        background: transparent;
        color: var(--ast-global-color-0, #667eea);
        border: 2px solid var(--ast-global-color-0, #667eea);
    }
    
    .secondary-button:hover {
        background: var(--ast-global-color-0, #667eea);
        color: var(--ast-global-color-5, white);
        text-decoration: none;
    }
    
    /* Small button variant */
    .unlock-btn {
        padding: 10px 25px;
        font-size: 14px;
    }
    
    @media (max-width: 768px) {
        .trial-results-wrapper {
            padding: 20px 10px;
        }
        
        .results-container {
            padding: 25px 15px;
        }
        
        .results-header h2 {
            font-size: 28px;
        }
        
        .overview-stat-card {
            flex-direction: column;
            text-align: center;
            padding: 20px;
        }
        
        .overview-stat-card .stat-value {
            font-size: 36px;
        }
        
        .legend {
            gap: 15px;
        }
        
        .categories-grid {
            grid-template-columns: 1fr;
            gap: 20px;
        }
        
        .category-chart-wrapper {
            padding: 20px 15px;
        }
        
        .bar-pair {
            min-height: 180px;
        }
        
        .signup-button,
        .secondary-button {
            width: 100%;
        }
    }
</style>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Animate bars on load
            const bars = document.querySelectorAll('.bar');
            bars.forEach((bar, index) => {
                const height = bar.style.height;
                bar.style.height = '2px';
                setTimeout(() => {
                    bar.style.height = height;
                }, 200 + (index * 100));
            });
        });
    </script>
    <?php
    return ob_get_clean();
}   
?>