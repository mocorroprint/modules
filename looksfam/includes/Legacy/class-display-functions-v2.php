<?php 



function display_exam_statistics_ui($exam_id, $user_id, $class_id,$type) {
    ob_start();
    echo getClassCategoryStatus($class_id);
    ?>
    <style>
        .custom-transparent-button {
            border: 2px solid #58c2f7;
            background: transparent;
            color: #58c2f7;
            border-radius: 10px;
            width: 49%;
            cursor: pointer;
            transition: background 0.3s ease;
        }

        .custom-transparent-button:hover {
            background: #ffffff;
        }
        .go-back-button {
            margin-top: 20px;
            margin-bottom: 20px; 
            height: 50px;
        }
        .stats-line {
            margin-top: 20px; 
            display: flex; 
            align-items: center;    
            border-bottom: 1px solid #fff;
        }
        .stat-con {
            width: 33.33%;
        }
    </style>
    <div class="button-container">
        <input type="button" value="←" class="go-back-button" onclick="window.location.href='<?php echo home_url('/profile?class_id=' . $class_id); ?>';" style="border-radius: 10px;">
    </div>
    <div class="button-container" style="display: flex;">
        <div style="width: 50%;">
            <h4>Looksfam Rating<br></h4>
            <?php
            $looksfamacc = calculateLooksfamacc($exam_id, $user_id);
            displayStarRating($looksfamacc);
            echo '<h2>'.$looksfamacc.'%</h2>';
            ?>
        </div>
    </div>
    <div class="stats-line" style="border-bottom: 0px solid #fff;">
        <?php
        $percentageScore = calculatePercentageScore($exam_id, $user_id);
        ?>
        <div class="stat-con" style="flex-grow: 1; text-align: left;">
            <strong>Your Highest Percentage</strong>
        </div>
        <div class="stat-con" style="text-align: center;">
            <?php displayStarRating($percentageScore); ?>
        </div>
        <div class="stat-con" style="flex-grow: 1; text-align: right;">
            <?php echo $percentageScore.'%'; ?>
        </div>
    </div>
    <div class="stats-line" style="margin-top: 5px;">
        <?php
        $averagePercentageScore = calculate_average_percentage_score($exam_id);
        ?>
        <div class="stat-con" style="flex-grow: 1; text-align: left;">
            Overall Average Exam Percentage
        </div>
        <div class="stat-con" style="text-align: center;">
            <?php displayStarRating($averagePercentageScore); ?>
        </div>
        <div class="stat-con" style="flex-grow: 1; text-align: right;">
            <?php echo $averagePercentageScore.'%'; ?>
        </div>
    </div>
    <div style="margin-top: 20px;">
        <button class="ast-button" onclick="window.location.href='<?php echo home_url('/'.$type.'?id=' . $exam_id . '&take=1&class_id=' . $class_id); ?>';" style="margin-right: 1%; border-radius: 10px; width: 49%; height: 100%;">Take Exam Again</button>
        <button class="custom-transparent-button" onclick="window.location.href='<?php echo home_url('/solutions?id=' . $exam_id . '&class_id=' . $class_id); ?>';" style="height: 100%;">Show Solution</button>
    </div>
    <div style="margin-top: 20px;">
        <h3>Statistics<br></h3>
        <div class="stats">
            <?php echo displayquestionstat($exam_id, $user_id); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
function display_exam_ui($exam_id, $selected_questions, $exam_title, $class_id, $type = 'exam') {
    if ($type === 'exercise' || $type === 'review') {
        $associated_classes = get_post_meta($exam_id, 'associated_classes', true);
        if (empty($associated_classes)) {
            return '<p>' . ucfirst($type) . ' is not associated with any class. It can be viewed by everyone.</p>';
        } elseif (!is_user_enrolled_in_any_class($associated_classes)) {
            return '<p>You are not enrolled in any class associated with this ' . $type . '.</p>';
        }
    }

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
    <form method="post" action="" id="exam-form">
        <?php wp_nonce_field('exam_submission_nonce', 'exam_submission_nonce'); ?>
        
        <div class="button-container">
            <input type="button" value="←" class="go-back-button" onclick="window.location.href='<?php echo home_url('/profile?class_id=' . $class_id); ?>';" style="border-radius: 10px;">
        </div>
        <?php if ($type === 'exercise' || $type === 'exam'): ?>
            <div class="timer-container">
                <div class="countdown" id="countdown" style="text-align:center;"></div>
                <div class="timer-bar" id="timer-progress-bar"></div>
            </div>
        <?php endif; ?>
        
        <h3><?php echo $exam_title; ?></h3>

        <div class="progress-container">
            <div class="progress-bar" id="progress-bar"></div>
        </div>

        <style>
            /* Include your CSS styles here */
            .options-grid {
                display: grid;
                grid-template-columns: repeat(2, 1fr);
                gap: 10px;
                margin-top: 10px;
            }
            .option-container {
                border: 1px solid #ccc;
                border-radius: 10px;
                min-height: 200px;
                padding: 10px;
                cursor: pointer;
                display: flex;
                align-items: center;
                justify-content: center;
                text-align: center;
            }
           /* .option-container.disabled {
                cursor: not-allowed;
                background-color: #e9ecef;
            } */
            .option-container label {
                width: 100%;
                height: 100%;
                display: flex;
                align-items: center;
                justify-content: center;
            }
            .option-container h4 {
                margin: 0;
            }
            .correct-answer {
                background-color: #c1e2b3;
            }
            .incorrect-answer {
                background-color: #ffcccc;
            }
            .button-container {
                display: flex;
                justify-content: space-between;
                gap: 10px;
            }
            .button-primary {
                flex: 1;
            }
            .button-container {
                    display: flex;
                    justify-content: space-between;
                    margin-top: 10px;
                }

                .button-primary {
                    width: calc(33.33% - 10px); /* Adjust the width as needed */
                    height:100px;
                    margin: 5px;
                    font-size: 20px;
                    font-weight: 700 !important;
                }  

                .go-back-button {
                    margin-top: 20px;
                    margin-bottom: 20px;
                    height: 50px;
                }

                .progress-container {
                margin-bottom: 20px;
            }
            .progress-bar {
                border-radius: 3px;
                height: 10px;
                width: 0;
                background-color: #ccc;
                transition: width 0.5s;
            }

                .timer-container {
                    margin-bottom: 20px;
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
                    margin-top: 10px;
                    font-size: 18px;
                }
            /* Add more styles as needed */
        </style>

        <div class="question-container">
            <?php foreach ($random_questions as $index => $question): 
                $question_id = $question->ID;
                $question_title = esc_html($question->post_title);
                $multiple_choice_options = get_post_meta($question_id, 'multiple_choice_options', true);
                $correct_answer = get_post_meta($question_id, 'correct_answer', true);
                $question_solution = ($type === 'exercise' || $type === 'review') ? get_post_meta($question_id, 'solution', true) : '';
                if (($type === 'exercise' || $type === 'review') && empty($question_solution)) {
                    $question_solution = 'No Solution available';
                }
            ?>
                <div class='question' id='question-<?php echo $index; ?>'>
                    <p><strong>Question:</strong> <?php echo $question_title; ?></p>
                    <div class="options-grid">
                        <?php 
                        $shuffled_letters = ['A', 'B', 'C', 'D'];
                        shuffle($shuffled_letters);
                        foreach ($shuffled_letters as $option):
                            $optionId = "option-$index-$option";
                            $isCorrect = ($option === $correct_answer);
                        ?>
                            <div class='option-container <?php echo $type === 'review' ? ($isCorrect ? 'correct-answer' : '') : ''; ?>' 
                                 <?php echo $type !== 'review' ? 'onclick="selectOption(\'' . $optionId . '\', \'' . $correct_answer . '\')"' : ''; ?> 
                                 id='option-container-<?php echo $optionId; ?>'>
                                <input hidden type='radio' name='user_answers[<?php echo $question_id; ?>]' value='<?php echo $option; ?>' id='<?php echo $optionId; ?>' <?php echo $type === 'review' ? 'disabled' : ''; ?>>
                                <label for='<?php echo $optionId; ?>'>
                                    <h4><?php echo $multiple_choice_options[$option]; ?></h4>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    <div>
                        Any concerns or suggestions for this question? <a href='contact?id=<?php echo $question_id; ?>' target='_blank'>Click here</a><br>
                        <div id="message-<?php echo $index; ?>" style="margin-top: 10px; display:none; text-align:center;">Please select an answer!</div>
                        <?php if ($type === 'exercise' || $type === 'review'): ?>
                            <div class="solution" id="solution-<?php echo $index; ?>" style="margin-top: 10px; <?php echo $type === 'review' ? '' : 'display:none;'; ?>">
                                <h4>Solution:</h4>
                                <?php echo nl2br(esc_html($question_solution)); ?>
                            </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>

        <div class="button-container">
            <input type="button" value="Previous Question" class="button-primary" id="prev-question" style="border-radius: 10px; display:none;">
            <input type="button" value="Next Question" class="button-primary" id="next-question" style="border-radius: 10px;">
            <?php if ($type !== 'review'): ?>
                <input type="submit" name="submit_exam" value="Submit <?php echo ucfirst($type); ?>" class="button-primary" id="back" style="border-radius: 10px; display:none;">
            <?php else: ?>
                <input type="button" value="Go Back" class="button-primary" onclick="window.location.href='<?php echo home_url('/profile?class_id=' . $class_id); ?>';" style="border-radius: 10px; display:none;" id="go-back-button">
            <?php endif; ?>
        </div>
    </form>

    <script>
    var type = '<?php echo $type; ?>';
    var totalQuestions = <?php echo count($random_questions); ?>;
    var currentIndex = 0;
    var questions = document.querySelectorAll('.question');
    var nextButton = document.getElementById('next-question');
    var prevButton = document.getElementById('prev-question');
    var submitButton = document.getElementById('back');
    var goBackButton = document.getElementById('go-back-button');
    var progressBar = document.getElementById('progress-bar');
    var answeredQuestions = 0;

    function showQuestion(index) {
        questions.forEach(function(question) {
            question.style.display = 'none';
        });
        questions[index].style.display = 'block';
        var progressWidth = (index + 1) / totalQuestions * 100;
        progressBar.style.width = progressWidth + '%';
        prevButton.style.display = index > 0 ? 'inline-block' : 'none';
        nextButton.style.display = index < totalQuestions - 1 ? 'inline-block' : 'none';
        if (type !== 'review') {
            submitButton.style.display = index === totalQuestions - 1 ? 'inline-block' : 'none';
        } else {
            goBackButton.style.display = index === totalQuestions - 1 ? 'inline-block' : 'none';
        }
    }

    function selectOption(optionId, correctAnswer) {
        var radioElement = document.getElementById(optionId);
        if (radioElement) {
            radioElement.checked = true;
            var optionContainer = document.getElementById('option-container-' + optionId);
            
            if (type === 'exam' || type === 'exercise') {
                var allOptions = optionContainer.parentNode.querySelectorAll('.option-container');
                allOptions.forEach(function(option) {
                    option.style.backgroundColor = '';
                });
                optionContainer.style.backgroundColor = '#ccc';
                
                if (type === 'exercise') {
                    var isCorrect = radioElement.value === correctAnswer;
                    optionContainer.style.backgroundColor = isCorrect ? '#c1e2b3' : '#ffcccc';
                    
                    allOptions.forEach(function(option) {
                        option.classList.add('disabled');
                        option.onclick = null;
                    });

                    if (!isCorrect) {
                        var correctOptionContainer = document.getElementById('option-container-option-' + currentIndex + '-' + correctAnswer);
                        if (correctOptionContainer) {
                            correctOptionContainer.style.backgroundColor = '#c1e2b3';
                        }
                    }

                    var solutionDiv = document.getElementById('solution-' + currentIndex);
                    if (solutionDiv) {
                        solutionDiv.style.display = 'block';
                    }
                }
                
                answeredQuestions++;
                
                if (answeredQuestions === totalQuestions) {
                    submitButton.disabled = false;
                    submitButton.classList.remove('dis-button');
                }
            }
        }
    }

    nextButton.addEventListener('click', function() {
        currentIndex++;
        showQuestion(currentIndex);
    });

    prevButton.addEventListener('click', function() {
        currentIndex--;
        showQuestion(currentIndex);
    });
    <?php if ($type === 'exercise' || $type === 'exam'): ?>
      // Timer functionality
        var timerDuration = (type === 'exercise' ? 15 : 30) * totalQuestions; // 15 seconds per question for exercise, 30 for exam
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
    showQuestion(currentIndex);
    </script>
    <?php
    return ob_get_clean();
}



// Handle exam submission
function handle_exam_submission() {
    if (isset($_POST['submit_exam']) && isset($_POST['user_answers'])) {
        // Generate a random 5-character session ID
        $session_id = substr(str_shuffle('ABCDEFGHIJKLMNOPQRSTUVWXYZ0123456789'), 0, 5);
        $class_id = intval($_GET['class_id']);

        // Verify the nonce for security
        if (isset($_POST['exam_submission_nonce']) && wp_verify_nonce($_POST['exam_submission_nonce'], 'exam_submission_nonce')) {
           $user_id = get_current_user_id();
            $user_answers = $_POST['user_answers'];
            $exam_id = intval($_GET['id']); // Get the exam ID from the URL parameter
            $exam_name = get_the_title($exam_id); // Get the exam name
            $exam_results = get_post_meta($exam_id, 'exam_results', true);

            if (!is_array($exam_results)) {
                $exam_results = array();
            }

            // Check each question's answer
            foreach ($user_answers as $question_id => $user_answer) {
                $correct_answer = get_post_meta($question_id, 'correct_answer', true);
                $is_correct = ($user_answer === $correct_answer) ? '1' : '0';
                
                // Get the category of the question
                $question_categories = wp_get_post_terms($question_id, 'question_category', array("fields" => "names"));
                $question_category = !empty($question_categories) ? $question_categories[0] : 'Uncategorized';
                
                $exam_results[] = array(
                    'exam_id' => $exam_id,
                    'exam_name' => $exam_name,
                    'question_id' => $question_id,
                    'user_id' => $user_id,
                    'user_answer' => $user_answer,
                    'is_correct' => $is_correct,
                    'timestamp' => current_time('mysql'),
                    'session_id' => $session_id,
                    'question_category' => $question_category, // Include the question category
                );

                // Save question results to the respective question
                $question_results = get_post_meta($question_id, 'question_results', true);
                $question_results = is_array($question_results) ? $question_results : array();
                $question_results[] = array(
                    'exam_id' => $exam_id,
                    'exam_name' => $exam_name,
                    'question_id' => $question_id,
                    'user_id' => $user_id,
                    'user_answer' => $user_answer,
                    'is_correct' => $is_correct,
                    'timestamp' => current_time('mysql'),
                    'session_id' => $session_id,
                    'question_category' => $question_category, // Include the question category
                );
                update_post_meta($question_id, 'question_results', $question_results);
            }

            // Save exam results to the database
            update_post_meta($exam_id, 'exam_results', $exam_results);
            
            // Redirect to '/confirm?done=1' after successful submission
            $confirmation_url = home_url('/confirm?id='.$exam_id.'&done=1&class_id='.$class_id.'&session_id='.$session_id); 
            wp_redirect($confirmation_url);
            exit;
        }
    }
}
add_action('template_redirect', 'handle_exam_submission');


?>