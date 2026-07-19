<?php 
function activity_content($atts) {
    $atts = shortcode_atts(array(
        'exam' => 0,
    ), $atts);

    $exam_id = intval($_GET['id']);
    $class_id = intval($_GET['class_id']);
    $category = get_term_by('id', intval($_GET['cat']), 'question_category');
    
    $user_id = get_current_user_id();
    $exam_title = $category->name;
    
    if (empty(intval($_GET['take']))) {
        $type = 'activity';
        return display_activity_statistics_ui( $category, $user_id, $class_id, $type);
    }
    // Define the number of questions to be retrieved
    $number_of_questions = 10; // You can change this value as needed
   if($class_id == '15611'){
       $number_of_questions = 50; 
   }

    // Get random questions from the category and all its subcategories
    $selected_questions = get_questions($category->term_id, $number_of_questions);

    if (!empty($selected_questions)) {
        $selected_question_ids = wp_list_pluck($selected_questions, 'ID');
        //return display_exam_ui($exam_id, $selected_question_ids, $exam_title, $class_id, 'exercises'); 
        return display_flashcard_exam_ui($exam_id, $selected_question_ids, $exam_title, $class_id, 'exercises'); 
        
    } 
}
add_shortcode('activity', 'activity_content');
//----

function display_flashcard_exam_ui($exam_id, $selected_questions, $exam_title, $class_id, $type = 'exam') {
    $cat = intval($_GET['cat']); 

    $args = array(
        'post_type' => 'question',
        'post__in' => $selected_questions,
        'orderby' => 'rand',
        'posts_per_page' => -1,
    );
    $random_questions = get_posts($args);
    $timer = count($selected_questions);

    ob_start();
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
                padding: 20px;
                box-shadow: 0 4px 8px rgba(0,0,0,0.1);
                color: black;
                overflow-y: auto;
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
            }
            
            .question-image {
                max-width: 100%;
                height: auto;
                max-height: 300px;
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
                background: rgba(0, 0, 0, 0.3);
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
                width: 20px;
                height: 20px;
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
                color: rgba(0, 0, 0, 0.5);
                border: none;
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
        </style>

        <div class="flashcard-container">
            <?php foreach ($random_questions as $index => $question): 
                $question_id = $question->ID;
                $question_title = esc_html($question->post_title);
                $multiple_choice_options = get_post_meta($question_id, 'multiple_choice_options', true);
                $correct_answer = get_post_meta($question_id, 'correct_answer', true);
                $question_solution = ($type === 'exercises' || $type === 'review') ? get_post_meta($question_id, 'solution', true) : '';
                
                // Get featured image if available
                $thumbnail_id = get_post_thumbnail_id($question_id);
                $image_url = $thumbnail_id ? wp_get_attachment_image_url($thumbnail_id, 'large') : '';
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
                <input type="submit" name="submit_flashcard_exam" value="Submit" onclick="showOverlay()" class="button-primary" id="submit-flashcard-exam" style="border-radius: 10px;display:none;">
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
        overlayMessage.textContent = message;
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
        nextButton.style.display = currentIndex < totalQuestions - 1 ? 'inline-block' : 'none';
        submitButton.style.display = currentIndex < totalQuestions - 1 ? 'none' : 'inline-block';
        if (type === 'review') {
            goBackButton.style.display = currentIndex === totalQuestions - 1 ? 'inline-block' : 'none';
        }
    }

    function selectOption(optionId, correctAnswer, flashcardIndex) {
        var radioElement = document.getElementById(optionId);
        if (radioElement && !answeredQuestions[flashcardIndex]) {
            radioElement.checked = true;
            answeredQuestions[flashcardIndex] = true;
            showAnswerStatus(flashcardIndex, correctAnswer);

            setTimeout(function() {
                //flipCard(flashcardIndex);
                setTimeout(function() {
                    if (currentIndex < totalQuestions - 1) {
                        //showFlashcard(currentIndex + 1, 'left');
                    }
                }, 1000);
            }, 0);
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

    nextButton.addEventListener('click', function() {
        showFlashcard(currentIndex + 1, 'left');
    });

    prevButton.addEventListener('click', function() {
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
        nextButton.style.display = currentIndex < totalQuestions - 1 ? 'inline-block' : 'none';
        submitButton.style.display = currentIndex < totalQuestions - 1 ? 'none' : 'inline-block';
        if (type === 'review') {
            goBackButton.style.display = currentIndex === totalQuestions - 1 ? 'inline-block' : 'none';
        }
    }

    function selectOption(optionId, correctAnswer, flashcardIndex) {
        var radioElement = document.getElementById(optionId);
        if (radioElement && !answeredQuestions[flashcardIndex]) {
            radioElement.checked = true;
            answeredQuestions[flashcardIndex] = true;
            showAnswerStatus(flashcardIndex, correctAnswer);

            setTimeout(function() {
                //flipCard(flashcardIndex);
                setTimeout(function() {
                    if (currentIndex < totalQuestions - 1) {
                        //showFlashcard(currentIndex + 1, 'left');
                    }
                }, 1000);
            }, 0);
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

    nextButton.addEventListener('click', function() {
        showFlashcard(currentIndex + 1, 'left');
    });

    prevButton.addEventListener('click', function() {
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

    <?php if ($type === 'exercises' || $type === 'exam'): ?>
    // Timer functionality
    var timerDuration = (type === 'exercises' ? 30 : 30) * totalQuestions; // 10 seconds per question for exercise, 30 for exam
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

function handle_flashcard_exam_submission() {
    if (isset($_POST['submit_flashcard_exam']) && isset($_POST['user_answers'])) {
        // Generate a random 5-character session ID
        $session_id = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 5);
        $class_id = intval($_GET['class_id']);
        $cat = intval($_GET['cat']);
        $category = get_term_by('id', $cat, 'question_category');

        // Verify the nonce for security
        if (isset($_POST['flashcard_exam_submission_nonce']) && wp_verify_nonce($_POST['flashcard_exam_submission_nonce'], 'flashcard_exam_submission_nonce')) {
            $user_id = get_current_user_id();
            $user_answers = $_POST['user_answers'];
            $exam_id = intval($_GET['id']); // Get the exam ID from the URL parameter
            $exam_name = get_the_title($exam_id); // Get the exam name
        
            
            // Check if 'cat' is present in the URL
            $is_cat_present = isset($_GET['cat']);

            
            // Prepare batch data for efficient database insertion
            $answers_batch = array();
            
            // Check each question's answer
            foreach ($user_answers as $question_id => $user_answer) {
                // Skip questions that weren't answered
                if (empty($user_answer)) {
                    continue;
                }

                $correct_answer = get_post_meta($question_id, 'correct_answer', true);
                $is_correct = ($user_answer === $correct_answer) ? '1' : '0';
                
                // Get the category of the question
                $question_categories = wp_get_post_terms($question_id, 'question_category', array("fields" => "names"));
                $question_category = !empty($question_categories) ? $question_categories[0] : 'Uncategorized';

                
                // Prepare data for batch insert
                $answers_batch[] = array(
                    'exam_id' => $exam_id,
                    'class_id' => $class_id,
                    'exam_name' => $exam_name,
                    'question_id' => intval($question_id),
                    'user_id' => $user_id,
                    'user_answer' => sanitize_text_field($user_answer),
                    'is_correct' => $is_correct,
                    'timestamp' => current_time('mysql'),
                    'session_id' => $session_id,
                    'question_category' => $cat,
                );
            }
            // Save all answers in a single batch operation
            if (!empty($answers_batch)) {
                $save_result = save_exam_answers_batch($answers_batch);
                
                if (!$save_result) {
                    // Fallback to individual saves if batch fails
                    global $wpdb;
                    $table_name = $wpdb->prefix . 'exam_answers';
                    
                    foreach ($answers_batch as $answer_data) {
                        $wpdb->insert($table_name, $answer_data);
                    }
                }
            }
            if ($is_cat_present) {
                // Save the questions to the user's meta data
                $confirmation_url = home_url('/confirm?id=' . $exam_id . '&done=1&class_id=' . $class_id . '&cat=' . $cat . '&session_id=' . $session_id); 
            } else {
                // Save exam results to the database
                $confirmation_url = home_url('/confirm?id=' . $exam_id . '&done=1&class_id=' . $class_id . '&session_id=' . $session_id); 
            }

            // Redirect to confirmation page after successful submission
            wp_redirect($confirmation_url);
            exit;
        }
    }
}
add_action('template_redirect', 'handle_flashcard_exam_submission');

// Modified activity_content function with type and cat options
function activity_content_trial($atts) {
    $atts = shortcode_atts(array(
        'exam' => 0,
        'type' => '', // New type attribute
        'cat' => '',  // New cat attribute
    ), $atts);
    
    $exam_id = intval($_GET['id']);
    $class_id = intval($_GET['class_id']);
    
    // Use cat from shortcode if provided, otherwise use URL parameter
    $cat_id = !empty($atts['cat']) ? intval($atts['cat']) : intval($_GET['cat']);
    $category = get_term_by('id', $cat_id, 'question_category');
    
    $user_id = get_current_user_id();
    $exam_title = $category->name;

    $number_of_questions = 30;
    $selected_questions = get_questions($category->term_id, $number_of_questions);
    
    if (!empty($selected_questions)) {
        $selected_question_ids = wp_list_pluck($selected_questions, 'ID');
        return display_flashcard_exam_trial($exam_id, $selected_question_ids, $exam_title, $class_id, 'trial'); 
    } 
}
add_shortcode('activity_trial', 'activity_content_trial');
// Modified display_flashcard_exam_ui function to handle 'trial' type
function display_flashcard_exam_trial($exam_id, $selected_questions, $exam_title, $class_id, $type = 'exam') {
    $cat = intval($_GET['cat']); 

    $args = array(
        'post_type' => 'question',
        'post__in' => $selected_questions,
        'orderby' => 'rand',
        'posts_per_page' => -1,
    );
    $random_questions = get_posts($args);
    $timer = count($selected_questions);

    ob_start();
    ?>
    <form method="post" action="" id="flashcard-exam-form" style="max-width:700px">
       
        
        
        <h4 style="margin-bottom:0px"><?php echo $exam_title; ?></h4>

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
        </style>

        <div class="flashcard-container">
            <?php foreach ($random_questions as $index => $question): 
                $question_id = $question->ID;
                $question_title = esc_html($question->post_title);
                $multiple_choice_options = get_post_meta($question_id, 'multiple_choice_options', true);
                $correct_answer = get_post_meta($question_id, 'correct_answer', true);
                $question_solution = ($type === 'exercises' || $type === 'review' || $type === 'trial') ? get_post_meta($question_id, 'solution', true) : '';
            ?>
                <div class='flashcard' id='flashcard-<?php echo $index; ?>'>
                    <div class='flashcard-front'>
                        <h6 style="color:#000;"> <?php echo $question_title; ?></h6>
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
                                    <div style="color:#000;margin-bottom:0px;text-align:center;" for='<?php echo $optionId; ?>'>
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
        nextButton.style.display = currentIndex < totalQuestions - 1 ? 'inline-block' : 'none';
        
        // For trial type, show Go Back button instead of Submit
        if (type === 'trial') {
            submitButton.style.display = 'none';
            goBackButton.style.display = currentIndex === totalQuestions - 1 ? 'inline-block' : 'none';
        } else {
            submitButton.style.display = currentIndex < totalQuestions - 1 ? 'none' : 'inline-block';
            if (type === 'review') {
                goBackButton.style.display = currentIndex === totalQuestions - 1 ? 'inline-block' : 'none';
            }
        }
    }

    function selectOption(optionId, correctAnswer, flashcardIndex) {
    var radioElement = document.getElementById(optionId);
    if (radioElement && !answeredQuestions[flashcardIndex]) {
        radioElement.checked = true;
        answeredQuestions[flashcardIndex] = true;
        showAnswerStatus(flashcardIndex, correctAnswer);

        // Prepare data for AJAX
        var questionId = radioElement.name.match(/\d+/)[0]; // extract numeric ID
        var userAnswer = radioElement.value;

        jQuery.ajax({
            url: "<?php echo admin_url('admin-ajax.php'); ?>",
            type: "POST",
            data: {
                action: "save_flashcard_answer",
                exam_name: "Trial",
                question_id: questionId,
                user_answer: userAnswer
            },
            success: function (response) {
                console.log("Saved:", response);

                // Enable moving to next only after success
                setTimeout(function () {
                    if (currentIndex < totalQuestions - 1) {
                        showFlashcard(currentIndex + 1, "left");
                    }
                }, 800);
            },
            error: function (xhr) {
                console.error("Error saving:", xhr.responseText);
                alert("Failed to save answer. Please try again.");
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

    function flipCard(index) {
        var solution = flashcards[index].querySelector('.flashcard-back').textContent.trim();
        if (solution !== 'No Solution available') {
            flashcards[index].classList.toggle('flip');
        }
    }

    nextButton.addEventListener('click', function() {
        showFlashcard(currentIndex + 1, 'left');
    });

    prevButton.addEventListener('click', function() {
        showFlashcard(currentIndex - 1, 'right');
    });
    
    function updateShowSolutionButton(index) {
        var showSolutionButton = document.getElementById('show-solution-' + index);
        if (showSolutionButton) {
            // For trial type, always show the solution button after answering
            if (type === 'trial') {
                showSolutionButton.style.display = answeredQuestions[index] ? 'block' : 'none';
            } else {
                showSolutionButton.style.display = answeredQuestions[index] ? 'block' : 'none';
            }
        }
    }
    
    // Initially hide all "Show Solution" buttons
    flashcards.forEach(function(flashcard, index) {
        var showSolutionButton = document.getElementById('show-solution-' + index);
        if (showSolutionButton) {
            // For trial type, show solution buttons immediately
            if (type === 'trial') {
                showSolutionButton.style.display = 'block';
            } else {
                showSolutionButton.style.display = 'none';
            }
        }
    });

    // Initialize
    showFlashcard(currentIndex);
    </script>
    <?php
    return ob_get_clean();
}
add_action('wp_ajax_save_flashcard_answer', 'save_flashcard_answer');
add_action('wp_ajax_nopriv_save_flashcard_answer', 'save_flashcard_answer');

function save_flashcard_answer() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'exam_answers';

    $user_id     = get_current_user_id();
    $exam_name   = sanitize_text_field($_POST['exam_name']); // "Trial"
    $question_id = intval($_POST['question_id']);
    $user_answer = sanitize_text_field($_POST['user_answer']);

    // Get correct answer
    $correct_answer = get_post_meta($question_id, 'correct_answer', true);
    $is_correct     = ($user_answer === $correct_answer) ? '1' : '0';

    $inserted = $wpdb->insert(
        $table_name,
        [
            'exam_id'           => 0, // since it's Trial
            'class_id'          => 0,
            'exam_name'         => $exam_name,
            'question_id'       => $question_id,
            'user_id'           => $user_id,
            'user_answer'       => $user_answer,
            'is_correct'        => $is_correct,
            'timestamp'         => current_time('mysql'),
            'session_id'        => substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 5),
            'question_category' => ''
        ]
    );

    if ($inserted === false) {
        // Return error message to AJAX → will show up in console.error
        wp_send_json_error([
            'message' => 'Database insert failed',
            'sql_error' => $wpdb->last_error
        ]);
    } else {
        wp_send_json_success("Answer saved");
    }
}

    
function get_random_questions_from_category_and_subcategories($category_id, $limit = 10) {
    // Get all subcategories
    $subcategories = get_term_children($category_id, 'question_category');
    
    // Add the main category to the list
    $all_categories = array_merge([$category_id], $subcategories);

    $args = array(
        'post_type' => 'question',
        'posts_per_page' => $limit,
        'orderby' => 'rand',
        'tax_query' => array(
            array(
                'taxonomy' => 'question_category',
                'field' => 'term_id',
                'terms' => $all_categories,
                'include_children' => true,
            ),
        ),
    );

    $query = new WP_Query($args);
    
    $questions = array();
    if ($query->have_posts()) {
        while ($query->have_posts()) {
            $query->the_post();
            $questions[] = array(
                'ID' => get_the_ID(),
                'post_title' => get_the_title(),
                'post_content' => get_the_content(),
                // Add any other question data you need
            );
        }
        wp_reset_postdata();
    }
    
    
    
 $displaylook = questionstatlooksfam($question['ID'], $user_id);
    return $questions;
}

function get_questions($category_id, $limit = 10, $batch_size = 500) {
    // Get all subcategories
    $subcategories = get_term_children($category_id, 'question_category');

    // Add the main category to the list
    $all_categories = array_merge([$category_id], $subcategories);

    $paged = 1;
    $found_posts = true;

    $questions_0 = array();
    $questions_1_50 = array();
    $questions_50_plus = array();

    // Batch fetch questions
    while ($found_posts) {
        $args = array(
            'post_type'      => 'question',
            'posts_per_page' => $batch_size,
            'paged'          => $paged,
            'orderby'        => 'rand',
            'tax_query'      => array(
                array(
                    'taxonomy'         => 'question_category',
                    'field'            => 'term_id',
                    'terms'            => $all_categories,
                    'include_children' => true,
                ),
            ),
            'fields' => 'ids', // Only get IDs for speed
        );

        $query = new WP_Query($args);

        if ($query->have_posts()) {
            foreach ($query->posts as $question_id) {
                $user_id = get_current_user_id();
                $displaylook = questionstatlooksfam($question_id, $user_id);

                $question = array(
                    'ID'           => $question_id,
                    'post_title'   => get_the_title($question_id),
                    'post_content' => get_post_field('post_content', $question_id),
                    'displaylook'  => $displaylook
                );

                if ($displaylook == 0) {
                    $questions_0[] = $question;
                } elseif ($displaylook >= 1 && $displaylook <= 50) {
                    $questions_1_50[] = $question;
                } else {
                    $questions_50_plus[] = $question;
                }

                // Early break if we already have enough
                if (
                    count($questions_0) + count($questions_1_50) + count($questions_50_plus) >= ($limit * 3)
                ) {
                    $found_posts = false;
                    break;
                }
            }
            $paged++;
        } else {
            $found_posts = false;
        }
        wp_reset_postdata();
    }

    // Calculate the number of questions to select from each group
    $limit_0      = min(ceil($limit * 0.7), count($questions_0));
    $limit_1_50   = min(ceil($limit * 0.3), count($questions_1_50));
    $limit_50_plus = $limit - $limit_0 - $limit_1_50;

    $selected_questions = array_merge(
        array_slice($questions_0, 0, $limit_0),
        array_slice($questions_1_50, 0, $limit_1_50),
        array_slice($questions_50_plus, 0, $limit_50_plus)
    );

    // If we don't have enough, backfill
    while (count($selected_questions) < $limit) {
        if (!empty($questions_0) && count($selected_questions) < $limit) {
            $selected_questions[] = array_shift($questions_0);
        } elseif (!empty($questions_1_50) && count($selected_questions) < $limit) {
            $selected_questions[] = array_shift($questions_1_50);
        } elseif (!empty($questions_50_plus) && count($selected_questions) < $limit) {
            $selected_questions[] = array_shift($questions_50_plus);
        } else {
            break;
        }
    }

    // Sort the final list by displaylook
    usort($selected_questions, function ($a, $b) {
        return $a['displaylook'] - $b['displaylook'];
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
            // Your existing PHP calculation code here...
            $category_l = intval($_GET['cat']);
            
            $questions_in_category = array();
            $per_page = 100;
            $paged = 1;
            
            do {
                $args = array(
                    'post_type'      => 'question',
                    'tax_query'      => array(
                        array(
                            'taxonomy'         => 'question_category',
                            'field'            => 'term_id',
                            'terms'            => $category_l,
                            'include_children' => true,
                        ),
                    ),
                    'posts_per_page' => $per_page,
                    'paged'          => $paged,
                );
                $batch = get_posts($args);
                $questions_in_category = array_merge($questions_in_category, $batch);
                $paged++;
            } while (count($batch) === $per_page);
            
            $total_questions = count($questions_in_category);
            
            global $wpdb;
            $table_name = $wpdb->prefix . 'exam_answers';
            
            $user_questions = $wpdb->get_results(
                $wpdb->prepare(
                    "SELECT question_id, user_id, is_correct, session_id, question_category, timestamp 
                     FROM $table_name 
                     WHERE user_id = %d AND question_category = %d 
                     ORDER BY timestamp ASC",
                    $user_id,
                    $category_l
                ),
                ARRAY_A
            );
            
            $correct_answers = 0;
            $all_correct_answers = 0;
            $sessions = 0;
            $q_sessions = 0;
            $displaylook = 0;
            $displayed_session = array();
            $displayed_questions = array();
            
            if (!empty($user_questions) && is_array($user_questions)) {
                foreach ($user_questions as $question) {
                    if (in_array($question['question_id'], $displayed_questions)) {
                        continue;
                    }
                    $displayed_questions[] = $question['question_id'];
                    if ($question['is_correct'] == '1') {
                        $correct_answers++;
                    }
                    if (!in_array($question['session_id'], $displayed_session)) {
                        $displayed_session[] = $question['session_id'];
                        $sessions++;
                    }
                    $q_sessions++;
                    $displaylook += questionstatlooksfam($question['question_id'], $user_id);
                }
            }
            
            $all_correct_answers = $correct_answers;
            $looksfamacc = ($total_questions > 0) ? ($correct_answers / $total_questions) * 100 : 0;
            $looksfamacc_x_accuracy_display = ($sessions > 0) ? ($displaylook / ($q_sessions * 100)) * 100 : 0;
            
            $tier2_required = 10;
            $tier3_required = 20;
            $tier4_required = 50;
            $tier5_required = 70;
            
            $has_free_trial = get_user_meta($user_id, 'free_trial_' . $class_id, true);
            
            $tier1 = ($sessions >= 5) ? '1' : null;
            $tier2 = ((($sessions >= 10) && ($all_correct_answers >= $tier2_required))|| current_user_can('manage_options')) ? '1' : null;
            $tier3 = ((($sessions >= 20) && ($all_correct_answers >= $tier3_required))|| current_user_can('manage_options')) ? '1' : null;
            $tier4 = ((($sessions >= 50) &&  ($all_correct_answers >= $tier4_required))|| current_user_can('manage_options')) ? '1' : null;
            $tier5 = ((($sessions >= 70) &&  ($all_correct_answers >= $tier5_required))|| current_user_can('manage_options')) ? '1' : null;
            $tier6 = $has_free_trial|| !current_user_can('manage_options')?null : '1';
            ?>
        </div>
    </div>

    <!-- Dashboard cards remain the same -->
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
            <div class="card-value"><?php echo round($looksfamacc, 2).'%'; ?></div>
            <small>(Correct Answers/ Total Answers)</small>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">Total Questions Answered</h3>
            </div>
            <div class="card-value"><?php echo $q_sessions; ?></div>
        </div>

        <div class="dashboard-card">
            <div class="card-header">
                <h3 class="card-title">Exams Taken</h3>
            </div>
            <div class="card-value"><?php echo $sessions; ?></div>
        </div>
    </div>

    <script>
    function toggleDescription() {
        const desc = document.getElementById('info-description');
        desc.style.display = desc.style.display === 'none' ? 'block' : 'none';
    }
    </script>

    <div class="button-container-menu">
        <button class="custom-button" onclick="window.location.href='<?php echo home_url('/'.$type.'?exam_id=8080&cat=' . $category_l . '&take=1&class_id=' . $class_id); ?>';">Start</button>
        <button class="custom-transparent-button"<?php if(!isset($tier1)){echo "disabled";}?> onclick="window.location.href='<?php echo home_url('/solutions?id=' . $exam_id . '&cat=' . $category_l . '&class_id=' . $class_id); ?>';">
        Review Questions<br><?php 
        if(!isset($tier1)){ 
            $exam_needed = 5 - $sessions;
            if ($exam_needed > 0) {
                echo "Answer $exam_needed more exam" . ($exam_needed > 1 ? "s" : "") . " to unlock!";
            }
        }
        ?></button>
    </div>
    
    <p style="margin-bottom:0px">Unli Questions Mode Coming Soon.</p>
    
    <div style="margin-top:0px;">
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
        <?php if (empty($displayed_questions)): ?>
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
    
    // Get user's questions and build data arrays (same logic as original)
    global $wpdb;
    $table_name = $wpdb->prefix . 'exam_answers';
    
    $user_questions = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT question_id, user_id, is_correct, session_id, question_category, timestamp 
             FROM $table_name 
             WHERE user_id = %d AND question_category = %d 
             ORDER BY timestamp ASC",
            $user_id,
            $category_id
        ),
        ARRAY_A
    );
    
    // Calculate tiers and permissions
    $sessions = 0;
    $all_correct_answers = 0;
    $displayed_session = array();
    $displayed_questions = array();
    
    if (!empty($user_questions) && is_array($user_questions)) {
        foreach ($user_questions as $question) {
            if (in_array($question['question_id'], $displayed_questions)) {
                continue;
            }
            $displayed_questions[] = $question['question_id'];
            if ($question['is_correct'] == '1') {
                $all_correct_answers++;
            }
            if (!in_array($question['session_id'], $displayed_session)) {
                $displayed_session[] = $question['session_id'];
                $sessions++;
            }
        }
    }
    
    $tier2_required = 10;
    $tier3_required = 20;
    $tier4_required = 50;
    $tier5_required = 70;
    
    $has_free_trial = get_user_meta($user_id, 'free_trial_' . $class_id, true);
    
    $tier2 = ((($sessions >= 10) && ($all_correct_answers >= $tier2_required))|| current_user_can('manage_options')) ? '1' : null;
    $tier3 = ((($sessions >= 20) && ($all_correct_answers >= $tier3_required))|| current_user_can('manage_options')) ? '1' : null;
    $tier4 = ((($sessions >= 50) &&  ($all_correct_answers >= $tier4_required))|| current_user_can('manage_options')) ? '1' : null;
    $tier5 = ((($sessions >= 70) &&  ($all_correct_answers >= $tier5_required))|| current_user_can('manage_options')) ? '1' : null;
    $tier6 = $has_free_trial|| !current_user_can('manage_options')?null : '1';
    
    // Build questions arrays
    $questions_to_display = [];
    $overall_questions = [];
    
    if (!empty($user_questions) && is_array($user_questions)) {
        $processed_questions = array();
        
        foreach ($user_questions as $question) {
            if (in_array($question['question_id'], $processed_questions)) {
                continue;
            }
            if ($category_id != $question['question_category']) {
                continue;
            }
            $processed_questions[] = $question['question_id'];
            
            $question_post = get_post($question['question_id']);
            if (!$question_post || $question_post->post_type !== 'question') {
                continue;
            }
            
            $displaylook_rating = questionstatlooksfam($question['question_id'], $user_id);
            $displayoveralllook_rating = questionoveralllooksfam($question['question_id'], $user_id);
            $displaylook_rating_acc = questionstatlooksfam($question['question_id'], $user_id,"accuracy");
            $displayoveralllook_rating_acc = questionoveralllooksfam($question['question_id'], $user_id,"accuracy");
            
            $question_hash = crc32($question['question_id'] . $user_id);
            $user_variation = ($question_hash % 30) - 15;
            $overall_variation = ($question_hash % 20) - 10;
            
            $displaylook_rating = max(0, min(100, $displaylook_rating + $user_variation));
            $displayoveralllook_rating = max(0, min(100, $displayoveralllook_rating + $overall_variation));
            
            $questions_to_display[] = [
                'question_id' => $question['question_id'],
                'title' => get_the_title($question['question_id']),
                'displaylook' => $displaylook_rating,
                'displayoveralllook' => $displayoveralllook_rating,
                'displaylook_acc' => $displaylook_rating_acc,
                'displayoveralllook_acc' => $displayoveralllook_rating_acc
            ];
        }
        
        usort($questions_to_display, function($a, $b) {
            return $b['displaylook'] - $a['displaylook'];
        });
        
        $overall_questions = $questions_to_display;
        usort($overall_questions, function($a, $b) {
            return $b['displayoveralllook'] - $a['displayoveralllook'];
        });
    }
    
    // Generate HTML based on type
    ob_start();
    
    if ($type === 'best-worst') {
        render_best_worst_stats($questions_to_display, $tier2, $tier3, $sessions, $all_correct_answers, $tier2_required, $tier3_required);
    } elseif ($type === 'overall') {
        render_overall_stats($overall_questions, $tier4, $tier5, $sessions, $all_correct_answers, $tier4_required, $tier5_required);
    } elseif ($type === 'all-questions') {
        render_all_questions_stats($questions_to_display, $tier6);
    }
    
    echo ob_get_clean();
    wp_die();
}
add_action('wp_ajax_load_question_stats', 'load_question_stats');
add_action('wp_ajax_nopriv_load_question_stats', 'load_question_stats');
function render_best_worst_stats($questions_to_display, $tier2, $tier3, $sessions, $all_correct_answers, $tier2_required, $tier3_required) {
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
                            $exam_needed = 10 - $sessions;
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
                            $exam_needed = 20 - $sessions;
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

function render_overall_stats($overall_questions, $tier4, $tier5, $sessions, $all_correct_answers, $tier4_required, $tier5_required) {
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
                            $exam_needed = 50 - $sessions;
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
                            $exam_needed = 70 - $sessions;
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
        
        <?php if (!$tier6): ?>
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
                    🔒<br>For Paid Users only!
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
?>