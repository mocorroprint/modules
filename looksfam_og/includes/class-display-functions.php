<?php 
    // Add this new function to calculate category-specific success rate
    function calculate_category_question_success_rate($class_id, $question_id, $category) {
        $activity_results = get_post_meta($class_id, 'activity_results', true);
        if (empty($activity_results)) {
            return 0;
        }
        
        $total_attempts = 0;
        $correct_attempts = 0;
        
        foreach ($activity_results as $result) {
            if ($result['question_id'] == $question_id && $result['question_category'] == $category) {
                $total_attempts++;
                if ($result['is_correct']) {
                    $correct_attempts++;
                }
            }
        }
        
        return $total_attempts > 0 ? ($correct_attempts / $total_attempts) * 100 : 0;
    }

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
        <button disabled class="custom-transparent-button" onclick="window.location.href='<?php echo home_url('/solutions?id=' . $exam_id . '&class_id=' . $class_id); ?>';" style="height: 100%;">Show Solution</button>
    </div>
    <div style="margin-top: 20px;">
        <h3>Statistics<br></h3>
        <div class="stats">
            <?php echo displayquestionstat($exam_id, $user_id); // make this user questions?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

function display_exam_ui($exam_id, $selected_questions, $exam_title, $class_id, $type = 'exam') {
    /*if ($type === 'exercise' || $type === 'review') {
        $associated_classes = get_post_meta($exam_id, 'associated_classes', true);
        if (empty($associated_classes)) {
            return '<p>' . ucfirst($type) . ' is not associated with any class. It can be viewed by everyone.</p>';
        } elseif (!is_user_enrolled_in_any_class($associated_classes)) {
            return '<p>You are not enrolled in any class associated with this ' . $type . '.</p>';
        }
    }*/
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
    echo get_styles();
    ?>
    <form method="post" action="" id="exam-form">
        <?php wp_nonce_field('exam_submission_nonce', 'exam_submission_nonce'); ?>
        
        <div class="button-container" style="display:none">
            <input type="button" value="←" class="go-back-button" onclick="window.location.href='<?php echo home_url('/' . (isset($cat) && !empty($cat) ? 'activity' : 'profile') . '?class_id=' . $class_id . (isset($cat) && !empty($cat) ? '&cat=' . $cat : '') . (isset($exam_id) && !empty($exam_id) ? '&id=' . $exam_id : '')); ?>';" style="border-radius: 10px;">
        </div>
        <?php if ($type === 'exercises' || $type === 'exam'): ?>
            <div class="timer-container">
                <div class="countdown" id="countdown" style="text-align:center;"></div>
                <div class="timer-bar" id="timer-progress-bar"></div>
            </div>
        <?php endif; ?>
        
        <h4><?php echo $exam_title; ?></h4>

        <div class="progress-container">
            <div class="progress-bar" id="progress-bar"></div>
        </div>

        <style>
            
            /* Add more styles as needed */
        </style>

        <div class="question-container">
            <?php foreach ($random_questions as $index => $question): 
                $question_id = $question->ID;
                $question_title = esc_html($question->post_title);
                $multiple_choice_options = get_post_meta($question_id, 'multiple_choice_options', true);
                $correct_answer = get_post_meta($question_id, 'correct_answer', true);
                $question_solution = ($type === 'exercises' || $type === 'review') ? get_post_meta($question_id, 'solution', true) : '';
                if (($type === 'exercises' || $type === 'review') && empty($question_solution)) {
                    $question_solution = 'No Solution available';
                }
            ?>
                <div class='question' id='question-<?php echo $index; ?>'>
                    <h6><strong>Question:</strong> <?php echo $question_title; ?></h6>
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
                                    <h5><?php echo $multiple_choice_options[$option]; ?></h5>
                                </label>
                            </div>
                        <?php endforeach; ?>
                    </div>
                    
                </div>
            <?php endforeach; ?>
        </div>

        <div class="button-container">
            <input type="button" value="Previous" class="button-primary" id="prev-question" style="border-radius: 10px; display:none;">
            <input type="button" value="Next" class="button-primary" id="next-question" style="border-radius: 10px;">
            <?php if ($type !== 'review'): ?>
                <input type="submit" name="submit_exam" value="Submit <?php echo ucfirst($type); ?>" class="button-primary" id="back" style="border-radius: 10px; display:none;">
            <?php else: ?>
                <input type="button" value="Go Back" class="button-primary" onclick="window.location.href='<?php echo home_url('/profile?class_id=' . $class_id); ?>';" style="border-radius: 10px; display:none;" id="go-back-button">
            <?php endif; ?>
        </div>
                    <div>
                        Any concerns or suggestions for this question? <a href='contact?id=<?php echo $question_id; ?>' target='_blank'>Click here</a><br>
                        <div id="message-<?php echo $index; ?>" style="margin-top: 10px; display:none; text-align:center;">Please select an answer!</div>
                        <?php if ($type === 'exercises' || $type === 'review'): ?>
                            <div class="solution" id="solution-<?php echo $index; ?>" style="margin-top: 10px; <?php echo $type === 'review' ? '' : 'display:none;'; ?>">
                                <h4>Solution:</h4>
                                <?php echo nl2br(esc_html($question_solution)); ?>
                            </div>
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
            
            if (type === 'exam' || type === 'exercises') {
                var allOptions = optionContainer.parentNode.querySelectorAll('.option-container');
                allOptions.forEach(function(option) {
                    option.style.backgroundColor = '';
                });
                optionContainer.style.backgroundColor = '#ccc';
                
                if (type === 'exercises') {
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
    <?php if ($type === 'exercises' || $type === 'exam'): ?>
      // Timer functionality
        var timerDuration = (type === 'exercises' ? 10 : 30) * totalQuestions; // 15 seconds per question for exercise, 30 for exam
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
        $cat = intval($_GET['cat']);
        $category = get_term_by('id', intval($_GET['cat']), 'question_category');

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

            // Check if 'cat' is present in the URL
            $is_cat_present = isset($_GET['cat']);

            // Initialize the questions array if it doesn't exist in the user meta
            if ($is_cat_present) {
                $user_questions = get_user_meta($user_id, 'questions', true);
                if (!is_array($user_questions)) {
                    $user_questions = array();
                }
            }

            // Check each question's answer
            foreach ($user_answers as $question_id => $user_answer) {
                $correct_answer = get_post_meta($question_id, 'correct_answer', true);
                $is_correct = ($user_answer === $correct_answer) ? '1' : '0';
                
                // Get the category of the question
                $question_categories = wp_get_post_terms($question_id, 'question_category', array("fields" => "names"));
                $question_category = !empty($question_categories) ? $question_categories[0] : 'Uncategorized';

                if ($is_cat_present) {
                    // Add the question to the user's meta data
                    $user_questions[] = array(
                        'exam_id' => $exam_id,
                        'class_id' => $class_id,
                        'exam_name' => $exam_name,
                        'question_id' => $question_id,
                        'user_answer' => $user_answer,
                        'is_correct' => $is_correct,
                        'timestamp' => current_time('mysql'),
                        'session_id' => $session_id,
                        'question_category' => $cat,
                    );
                } else {
                    // Add to the exam results
                    $exam_results[] = array(
                        'exam_id' => $exam_id,
                        'class_id' => $class_id,
                        'exam_name' => $exam_name,
                        'question_id' => $question_id,
                        'user_id' => $user_id,
                        'user_answer' => $user_answer,
                        'is_correct' => $is_correct,
                        'timestamp' => current_time('mysql'),
                        'session_id' => $session_id,
                        'question_category' => $cat, // Include the question category
                    );

                   
                }
                 // Save question results to the respective question
                    $question_results = get_post_meta($question_id, 'question_results', true);
                    $question_results = is_array($question_results) ? $question_results : array();
                    $question_results[] = array(
                        'class_id' => $class_id,
                        'exam_id' => $exam_id,
                        'exam_name' => $exam_name,
                        'question_id' => $question_id,
                        'user_id' => $user_id,
                        'user_answer' => $user_answer,
                        'is_correct' => $is_correct,
                        'timestamp' => current_time('mysql'),
                        'session_id' => $session_id,
                        'question_category' => $cat, // Include the question category
                    );
                    update_post_meta($question_id, 'question_results', $question_results);
            }

            if ($is_cat_present) {
                // Save the questions to the user's meta data
                update_user_meta($user_id, 'questions', $user_questions);
                $confirmation_url = home_url('/confirm?id='.$exam_id.'&done=1&class_id='.$class_id.'&cat='.$cat.'&session_id='.$session_id); 
                wp_redirect($confirmation_url);
                exit;
            } else {
                // Save exam results to the database
                update_post_meta($exam_id, 'exam_results', $exam_results); 
                $confirmation_url = home_url('/confirm?id='.$exam_id.'&done=1&class_id='.$class_id.'&session_id='.$session_id); 
                wp_redirect($confirmation_url);
                exit;
            }

            // Redirect to '/confirm?done=1' after successful submission
           
        }
    }
}
add_action('template_redirect', 'handle_exam_submission');


//----

function add_survey_overlay() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['survey_data'])) {
        $user_id = get_current_user_id();
        $survey_data = json_decode(stripslashes($_POST['survey_data']), true);
        
        if (is_array($survey_data)) {
            update_user_meta($user_id, 'survey1', $survey_data);
        }
        
        wp_redirect(home_url());
        exit;
    }

    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $survey_completed = get_user_meta($user_id, 'survey1', true);

        if (empty($survey_completed)) {
            ?>
            <style>
            .blur{
    background: linear-gradient(to top, #00000024, transparent);
    webkit-mask: linear-gradient(to bottom, transparent 1%, 15px, black 1%);
    mask: linear-gradient(to bottom, transparent 1%, 15px, black 1%);
    -webkit-backdrop-filter: blur(15px);
    backdrop-filter: blur(15px);}
                .survey-overlay {
                    position: fixed;
                    top: 0;
                    left: 0;
                    width: 100%;
                    height: 100%;
                    background: rgba(0, 0, 0, 0.8);
                    color: #fff;
                    display: flex;
                    justify-content: center;
                    align-items: center;
                    z-index: 9999;
                    overflow: hidden;
                    transition: opacity 0.3s ease;
                }

                .survey-content {
                    width: 80%;
                    max-width: 100%;
                    padding: 10px;
                    border-radius: 10px;
                    position: relative;
                }

                .survey-question {
                    display: none;
                }

                .survey-question.active {
                    display: block;
                }

                .option-box {
                    padding: 10px;
                    border: 2px solid #ddd;
                    border-radius: 5px;
                    margin: 5px 0;
                    cursor: pointer;
                    transition: background 0.3s, border-color 0.3s;
                    color: #fff;
                    font-weight:600;
                    font-size:20px;
                }

                .option-box.selected {
                    background: #58c2f7;
                    border-color: #58c2f7;
                    color: #fff;
                }

                .button-next {
                    margin-top: 20px;
                    padding: 20px 30px;
                    border: none;
                    background: #58c2f7;
                    color: #fff;
                    border-radius: 5px;
                    cursor: pointer;
                    font-size: 16px;
                }

                .button-next:hover {
                    background: #4aabf1;
                }

                .close-survey {
                    position: absolute;
                    top: 10px;
                    right: 10px;
                    font-size: 24px;
                    cursor: pointer;
                }
                .other-input {
                width: 100%;
                margin-top: 5px;
                padding: 8px;
                border: 1px solid #ddd;
                border-radius: 4px;
                display: none;
            }
            </style>
            <div id="survey-overlay" class="blur survey-overlay" style="display:none;">
                <div class="survey-content">
                    <h2 id="question-title"></h2>
                    <div id="options-container"></div>
                    <input type="text" id="other-input" class="other-input" placeholder="Please specify">
                    <button id="button-next" class="button-next">Next</button>
                    <button id="button-submit" class="button-submit" style="display:none;">Submit</button>
                </div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', () => {
                const surveyQuestions = [
                    {
                        question: "Before you start, we wanna know how did you discover Looksfam?",
                        options: ["Word of Mouth","Facebook","Google", "Online Advertisement", "Other"],
                        key: "discovery"
                    },
                    {
                        question: "What is your current academic level?",
                        options: ["Undergraduate", "Graduate", "Recent Graduate", "Other"],
                        key: "academic_level"
                    },
                    {
                        question: "Are you currently working, or are you studying full-time?",
                        options: ["Currently working", "Studying full-time", "Other"],
                        key: "working_status"
                    },
                    {
                        question: "What specific  licensure exam are you preparing for?",
                        options: ["Civil Engineering (CE)", "Electronics Engineering (ECE)", "Licensed Professional Teachers (LPT)","Civil Service", "Other"],
                        key: "exam"
                    },
                    {
                        question: "Have you previously taken licensure exams?",
                        options: ["Yes", "No"],
                        key: "previous_attempts"
                    },
                    {
                        question: "If yes, how many times have you taken the exam?",
                        options: ["1 time", "2 times", "More than 3 times", "I did not take yet."],
                        key: "attempt_count"
                    },
                    {
                        question: "If you are a retaker, what were the reasons for your previous attempts?",
                        options: ["Lack of preparation", "Unfamiliarity with exam format", "Anxiety or stress", "Other"],
                        key: "retaker_reason"
                    },
                    {
                        question: "What study methods do you currently use?",
                        options: ["Review centers", "Self-study", "Online courses", "Study groups", "Other"],
                        key: "study_method"
                    },
                    {
                        question: "What features do you find most important in a study platform?",
                        options: ["Personalized recommendations", "Interactive content", "Progress tracking", "Gamified elements", "Other"],
                        key: "features"
                    },
                    {
                        question: "What are your main goals for using Looksfam?",
                        options: ["Other"],
                        key: "goals"
                    },
                    {
                        question: "What features or tools would you like to see on the Looksfam platform to support your exam preparation?",
                        options: ["Other"],
                        key: "features_tools"
                    }
                ];

                let currentQuestionIndex = 0;
                const surveyData = {};

                const surveyOverlay = document.getElementById('survey-overlay');
                const questionTitle = document.getElementById('question-title');
                const optionsContainer = document.getElementById('options-container');
                const otherInput = document.getElementById('other-input');
                const nextButton = document.getElementById('button-next');
                const submitButton = document.getElementById('button-submit');

                function showQuestion(index) {
                    const question = surveyQuestions[index];
                    questionTitle.textContent = question.question;
                    optionsContainer.innerHTML = '';
                    question.options.forEach(option => {
                        const optionBox = document.createElement('div');
                        optionBox.className = 'option-box';
                        optionBox.textContent = option;
                        optionBox.addEventListener('click', () => selectOption(option));
                        optionsContainer.appendChild(optionBox);
                    });
                    otherInput.style.display = 'none';
                    otherInput.value = '';
                    nextButton.style.display = index < surveyQuestions.length - 1 ? 'inline-block' : 'none';
                    submitButton.style.display = index === surveyQuestions.length - 1 ? 'inline-block' : 'none';
                }

                function selectOption(option) {
                    const optionBoxes = optionsContainer.querySelectorAll('.option-box');
                    optionBoxes.forEach(box => box.classList.remove('selected'));
                    event.target.classList.add('selected');
                    if (option === 'Other') {
                        otherInput.style.display = 'block';
                    } else {
                        otherInput.style.display = 'none';
                        surveyData[surveyQuestions[currentQuestionIndex].key] = option;
                    }
                }

                nextButton.addEventListener('click', () => {
                    if (otherInput.style.display === 'block') {
                        surveyData[surveyQuestions[currentQuestionIndex].key] = otherInput.value;
                    }
                    currentQuestionIndex++;
                    if (currentQuestionIndex < surveyQuestions.length) {
                        showQuestion(currentQuestionIndex);
                    }
                });

                submitButton.addEventListener('click', () => {
                    if (otherInput.style.display === 'block') {
                        surveyData[surveyQuestions[currentQuestionIndex].key] = otherInput.value;
                    }
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.style.display = 'none';
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'survey_data';
                    input.value = JSON.stringify(surveyData);
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                });

                // Show the survey overlay
                surveyOverlay.style.display = 'flex';
                showQuestion(currentQuestionIndex);
            });
            </script>
            <?php
        }
    }
}
//add_action('wp_footer', 'add_survey_overlay');


function custom_user_survey_answers_submenu() {
    add_users_page(
        'User Survey Answers', // Page title
        'Survey Answers',      // Menu title
        'manage_options',      // Capability
        'user-survey-answers', // Menu slug
        'display_user_survey_answers_page' // Callback function
    );
}
add_action('admin_menu', 'custom_user_survey_answers_submenu');

function display_user_survey_answers_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    // Get all users
    $users = get_users();
    echo '<div class="wrap">';
    echo '<h1>User Survey Answers</h1>';
    echo '<table class="widefat fixed" cellspacing="0">';
    echo '<thead><tr>';
    echo '<th>User</th>';
    echo '<th>Discovery</th>';
    echo '<th>Academic Level</th>';
    echo '<th>Working Status</th>';
    echo '<th>Exam</th>';
    echo '<th>Retaker Reason</th>';
    echo '<th>Study Method</th>';
    echo '<th>Features</th>';
    echo '<th>Goals</th>';
    echo '<th>Features Tools</th>';
    echo '</tr></thead>';
    echo '<tbody>';
    foreach ($users as $user) {
        $user_id = $user->ID;
        $survey_data = get_user_meta($user_id, 'survey1', true);
        if ($survey_data && is_array($survey_data)) {
            echo '<tr>';
            echo '<td>' . esc_html($user->display_name) . '</td>';
            echo '<td>' . esc_html($survey_data['discovery'] ?? 'N/A') . '</td>';
            echo '<td>' . esc_html($survey_data['academic_level'] ?? 'N/A') . '</td>';
            echo '<td>' . esc_html($survey_data['working_status'] ?? 'N/A') . '</td>';
            echo '<td>' . esc_html($survey_data['exam'] ?? 'N/A') . '</td>';
            echo '<td>' . esc_html($survey_data['retaker_reason'] ?? 'N/A') . '</td>';
            echo '<td>' . esc_html($survey_data['study_method'] ?? 'N/A') . '</td>';
            echo '<td>' . esc_html($survey_data['features'] ?? 'N/A') . '</td>';
            echo '<td>' . esc_html($survey_data['goals'] ?? 'N/A') . '</td>';
            echo '<td>' . esc_html($survey_data['features_tools'] ?? 'N/A') . '</td>';
            echo '</tr>';
        }
    }
    echo '</tbody></table>';
    echo '</div>';
}

function add_survey2_overlay() {
    if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['survey2_data'])) {
        $user_id = get_current_user_id();
        $survey_data = json_decode(stripslashes($_POST['survey2_data']), true);
        
        if (is_array($survey_data)) {
            update_user_meta($user_id, 'survey2', $survey_data);
        }
        
        wp_redirect(home_url());
        exit;
    }

    if (is_user_logged_in()) {
        $user_id = get_current_user_id();
        $survey_completed = get_user_meta($user_id, 'survey2', true);

        if (empty($survey_completed)) {
            ?>
            <style>
            .blur{
                background: linear-gradient(to top, #00000024, transparent);
                webkit-mask: linear-gradient(to bottom, transparent 1%, 15px, black 1%);
                mask: linear-gradient(to bottom, transparent 1%, 15px, black 1%);
                -webkit-backdrop-filter: blur(15px);
                backdrop-filter: blur(15px);
            }
            .survey2-overlay {
                position: fixed;
                top: 0;
                left: 0;
                width: 100%;
                height: 100%;
                background: rgba(0, 0, 0, 0.8);
                color: #fff;
                display: flex;
                justify-content: center;
                align-items: center;
                z-index: 9999;
                overflow-y: auto;
                transition: opacity 0.3s ease;
            }

            .survey2-content {
                width: 80%;
                max-width: 600px;
                padding: 20px;
                border-radius: 10px;
                position: relative;
                margin: 20px 0;
            }

            .survey2-question {
                display: none;
                margin-bottom: 20px;
            }

            .survey2-question.active {
                display: block;
            }

            .question-title {
                font-size: 18px;
                margin-bottom: 15px;
                font-weight: bold;
            }

            .option-box {
                padding: 12px;
                border: 2px solid #ddd;
                border-radius: 5px;
                margin: 8px 0;
                cursor: pointer;
                transition: background 0.3s, border-color 0.3s;
                color: #fff;
                font-weight: 500;
                font-size: 16px;
            }

            .option-box.selected {
                background: #58c2f7;
                border-color: #58c2f7;
                color: #fff;
            }

            .checkbox-option {
                display: flex;
                align-items: center;
                padding: 12px;
                border: 2px solid #ddd;
                border-radius: 5px;
                margin: 8px 0;
                cursor: pointer;
                transition: background 0.3s, border-color 0.3s;
                color: #fff;
                font-weight: 500;
                font-size: 16px;
            }

            .checkbox-option.selected {
                background: #58c2f7;
                border-color: #58c2f7;
                color: #fff;
            }

            .checkbox-option input[type="checkbox"] {
                display: none;
            }

            .text-input, .textarea-input {
                width: 100%;
                margin-top: 10px;
                padding: 12px;
                border: 2px solid #ddd;
                border-radius: 5px;
                font-size: 16px;
                background: rgba(255, 255, 255, 0.1);
                color: #fff;
            }

            .textarea-input {
                min-height: 80px;
                resize: vertical;
            }

            .text-input::placeholder, .textarea-input::placeholder {
                color: #ccc;
            }

            .ranking-container {
                display: flex;
                flex-direction: column;
                gap: 10px;
            }

            .ranking-item {
                display: flex;
                align-items: center;
                padding: 12px;
                border: 2px solid #ddd;
                border-radius: 5px;
                background: rgba(255, 255, 255, 0.1);
                cursor: pointer;
                transition: background 0.3s, border-color 0.3s;
                position: relative;
            }

            .ranking-item.selected {
                background: #58c2f7;
                border-color: #58c2f7;
                color: #fff;
            }

            .ranking-number {
                background: #58c2f7;
                color: #fff;
                width: 25px;
                height: 25px;
                border-radius: 50%;
                display: flex;
                align-items: center;
                justify-content: center;
                font-weight: bold;
                margin-right: 15px;
                font-size: 14px;
            }

            .ranking-item:not(.selected) .ranking-number {
                background: #6c757d;
            }

            .scale-container {
                display: flex;
                justify-content: space-between;
                align-items: center;
                margin: 20px 0;
                gap: 5px;
                flex-wrap: wrap;
            }

            .scale-option {
                display: flex;
                flex-direction: column;
                align-items: center;
                cursor: pointer;
                padding: 10px 5px;
                border-radius: 8px;
                transition: all 0.3s ease;
                min-width: 40px;
            }

            .scale-option:hover {
                background: rgba(88, 194, 247, 0.3);
            }

            .scale-option.selected {
                background: #58c2f7;
                color: #fff;
                transform: scale(1.1);
            }

            .scale-option input[type="radio"] {
                display: none;
            }

            .scale-number {
                font-size: 18px;
                font-weight: bold;
                margin-bottom: 5px;
            }

            .scale-labels {
                display: flex;
                justify-content: space-between;
                margin-top: 10px;
                font-size: 14px;
                color: #ccc;
            }

            .button-next, .button-prev, .button-submit {
                /*margin: 10px 5px;*/
                padding: 15px 25px;
                border: none;
                background: #58c2f7;
                color: #fff;
                border-radius: 5px;
                cursor: pointer;
                font-size: 16px;
                font-weight: bold;
                width:49%;
            }

            .button-next:hover, .button-prev:hover, .button-submit:hover {
                background: #4aabf1;
            }

            .button-prev {
                background: #6c757d;
            }

            .button-prev:hover {
                background: #5a6268;
            }

            .progress-bar {
                width: 100%;
                height: 6px;
                background: rgba(255, 255, 255, 0.3);
                border-radius: 3px;
                margin-bottom: 20px;
                overflow: hidden;
            }

            .progress-fill {
                height: 100%;
                background: #58c2f7;
                transition: width 0.3s ease;
            }

            .other-input {
                width: 100%;
                margin-top: 10px;
                padding: 10px;
                border: 2px solid #ddd;
                border-radius: 4px;
                display: none;
                background: rgba(255, 255, 255, 0.1);
                color: #fff;
            }

            .other-input::placeholder {
                color: #ccc;
            }
            </style>

            <div id="survey2-overlay" class="blur survey2-overlay" style="display:none;">
                <div class="survey2-content">
                    <div class="progress-bar">
                        <div id="progress-fill" class="progress-fill" style="width: 0%;"></div>
                    </div>
                    <div id="survey2-questions-container"></div>
                    <div style="text-align: center; margin-top: 20px;">
                        <button id="button-prev" class="button-prev" style="display:none;">Previous</button>
                        <button id="button-next" class="button-next">Next</button>
                        <button id="button-submit" class="button-submit" style="display:none;">Submit</button>
                    </div>
                </div>
            </div>

            <script>
            document.addEventListener('DOMContentLoaded', () => {
                const survey2Questions = [
                    {
                        question: "How did you first hear about LooksFam?",
                        type: "single",
                        options: ["Friend or classmate", "Social media (Facebook, TikTok, etc.)", "Online search", "School or review center", "Event or seminar", "Other"],
                        key: "discovery_method"
                    },
                    {
                        question: "What was your first impression when you heard about LooksFam?",
                        type: "text",
                        key: "first_impression"
                    },
                    {
                        question: "Which part of LooksFam caught your attention most?",
                        type: "single",
                        options: ["Past Board Exam Questions", "Personalized study plans", "Mock exams and analytics", "Accessibility (anywhere, anytime)", "Other"],
                        key: "attention_feature"
                    },
                    {
                        question: "What is your current profession or role?",
                        type: "single",
                        options: ["Student", "Professional", "Educator/Trainer", "Other"],
                        key: "profession"
                    },
                    {
                        question: "Which board/licensure or competitive exam are you preparing for?",
                        type: "text",
                        key: "target_exam"
                    },
                    {
                        question: "How many months away is your target exam date?",
                        type: "text",
                        key: "months_to_exam"
                    },
                    {
                        question: "How do you currently prepare for your exams? (Select all that apply)",
                        type: "multiple",
                        options: ["Review centers", "Printed reviewers/books", "Online platforms/apps", "Study groups", "Self-study"],
                        key: "current_study_methods"
                    },
                    {
                        question: "What challenges do you face with your current study method?",
                        type: "single",
                        options: ["Lack of personalized content", "Time management", "Finding updated/relevant questions", "Motivation/consistency", "Other"],
                        key: "study_challenges"
                    },
                    {
                        question: "What features do you expect most from a platform like LooksFam? (Rank 1-5, with 1 being most important)",
                        type: "ranking",
                        options: ["Past Board Exam Questions", "Personalized study plans", "Performance tracking & analytics", "Community or peer discussion", "Mock exams & timed drills"],
                        key: "feature_ranking"
                    },
                    {
                        question: "What would make you choose LooksFam over other review options?",
                        type: "textarea",
                        key: "choosing_factors"
                    },
                    {
                        question: "How important is it for you to study on mobile vs. desktop?",
                        type: "single",
                        options: ["Mobile only", "Desktop only", "Both equally"],
                        key: "device_preference"
                    },
                    {
                        question: "What frustrations do you want LooksFam to solve for you?",
                        type: "textarea",
                        key: "frustrations_to_solve"
                    },
                    {
                        question: "On a scale of 1-10, how likely are you to recommend LooksFam if it meets your needs?",
                        type: "scale",
                        scale: [1, 2, 3, 4, 5, 6, 7, 8, 9, 10],
                        key: "recommendation_score"
                    }
                ];

                let currentQuestionIndex = 0;
                const survey2Data = {};

                const surveyOverlay = document.getElementById('survey2-overlay');
                const questionsContainer = document.getElementById('survey2-questions-container');
                const nextButton = document.getElementById('button-next');
                const prevButton = document.getElementById('button-prev');
                const submitButton = document.getElementById('button-submit');
                const progressFill = document.getElementById('progress-fill');

                function updateProgress() {
                    const progress = ((currentQuestionIndex + 1) / survey2Questions.length) * 100;
                    progressFill.style.width = progress + '%';
                }

                function renderQuestion(index) {
                    const question = survey2Questions[index];
                    let html = `<div class="survey2-question active">
                        <div class="question-title">${question.question}</div>`;

                    switch (question.type) {
                        case 'single':
                            question.options.forEach(option => {
                                html += `<div class="option-box" data-value="${option}">${option}</div>`;
                            });
                            if (question.options.includes('Other')) {
                                html += `<input type="text" class="other-input" placeholder="Please specify" style="display:none;">`;
                            }
                            break;

                        case 'multiple':
                            question.options.forEach(option => {
                                html += `<div class="checkbox-option" data-value="${option}">
                                    <input type="checkbox" value="${option}" id="cb_${option.replace(/\s+/g, '_')}">
                                    ${option}
                                </div>`;
                            });
                            break;

                        case 'text':
                            html += `<input type="text" class="text-input" placeholder="Your answer">`;
                            break;

                        case 'textarea':
                            html += `<textarea class="textarea-input" placeholder="Your detailed answer"></textarea>`;
                            break;

                        case 'ranking':
                            html += `<div class="ranking-container">`;
                            html += `<p style="margin-bottom: 15px; color: #ccc;">Click items in order of importance (1 = most important)</p>`;
                            question.options.forEach((option, idx) => {
                                html += `<div class="ranking-item" data-option="${option}">
                                    <div class="ranking-number"></div>
                                    <span>${option}</span>
                                </div>`;
                            });
                            html += `</div>`;
                            break;

                        case 'scale':
                            html += `<div class="scale-container">`;
                            question.scale.forEach(num => {
                                html += `<div class="scale-option" data-value="${num}">
                                    <input type="radio" name="scale_${question.key}" value="${num}" id="scale_${num}">
                                    <div class="scale-number">${num}</div>
                                </div>`;
                            });
                            html += `</div>`;
                            html += `<div class="scale-labels">
                                <span>Not likely</span>
                                <span>Very likely</span>
                            </div>`;
                            break;
                    }

                    html += `</div>`;
                    questionsContainer.innerHTML = html;

                    // Add event listeners
                    if (question.type === 'single') {
                        const optionBoxes = questionsContainer.querySelectorAll('.option-box');
                        optionBoxes.forEach(box => {
                            box.addEventListener('click', () => {
                                optionBoxes.forEach(b => b.classList.remove('selected'));
                                box.classList.add('selected');
                                const otherInput = questionsContainer.querySelector('.other-input');
                                if (box.dataset.value === 'Other' && otherInput) {
                                    otherInput.style.display = 'block';
                                } else if (otherInput) {
                                    otherInput.style.display = 'none';
                                }
                                survey2Data[question.key] = box.dataset.value;
                            });
                        });
                    }

                    if (question.type === 'multiple') {
                        const checkboxOptions = questionsContainer.querySelectorAll('.checkbox-option');
                        checkboxOptions.forEach(option => {
                            option.addEventListener('click', () => {
                                const checkbox = option.querySelector('input[type="checkbox"]');
                                checkbox.checked = !checkbox.checked;
                                option.classList.toggle('selected', checkbox.checked);
                            });
                        });
                    }

                    if (question.type === 'ranking') {
                        let rankingOrder = [];
                        const rankingItems = questionsContainer.querySelectorAll('.ranking-item');
                        
                        rankingItems.forEach(item => {
                            item.addEventListener('click', () => {
                                const option = item.dataset.option;
                                const existingIndex = rankingOrder.indexOf(option);
                                
                                if (existingIndex !== -1) {
                                    // Remove from ranking
                                    rankingOrder.splice(existingIndex, 1);
                                    item.classList.remove('selected');
                                } else if (rankingOrder.length < 5) {
                                    // Add to ranking
                                    rankingOrder.push(option);
                                    item.classList.add('selected');
                                }
                                
                                // Update all ranking numbers
                                rankingItems.forEach(rankItem => {
                                    const rankOption = rankItem.dataset.option;
                                    const rank = rankingOrder.indexOf(rankOption);
                                    const numberEl = rankItem.querySelector('.ranking-number');
                                    
                                    if (rank !== -1) {
                                        numberEl.textContent = rank + 1;
                                        rankItem.classList.add('selected');
                                    } else {
                                        numberEl.textContent = '';
                                        rankItem.classList.remove('selected');
                                    }
                                });
                                
                                // Save ranking data
                                const rankings = {};
                                rankingOrder.forEach((option, index) => {
                                    rankings[option] = index + 1;
                                });
                                survey2Data[question.key] = rankings;
                            });
                        });
                    }

                    if (question.type === 'scale') {
                        const scaleOptions = questionsContainer.querySelectorAll('.scale-option');
                        scaleOptions.forEach(option => {
                            option.addEventListener('click', () => {
                                scaleOptions.forEach(opt => opt.classList.remove('selected'));
                                option.classList.add('selected');
                                const radio = option.querySelector('input[type="radio"]');
                                radio.checked = true;
                                survey2Data[question.key] = parseInt(option.dataset.value);
                            });
                        });
                    }

                    updateProgress();
                    prevButton.style.display = index > 0 ? 'inline-block' : 'none';
                    nextButton.style.display = index < survey2Questions.length - 1 ? 'inline-block' : 'none';
                    submitButton.style.display = index === survey2Questions.length - 1 ? 'inline-block' : 'none';
                }

                function collectCurrentAnswer() {
                    const question = survey2Questions[currentQuestionIndex];
                    
                    switch (question.type) {
                        case 'single':
                            const otherInput = questionsContainer.querySelector('.other-input');
                            if (otherInput && otherInput.style.display === 'block') {
                                survey2Data[question.key] = otherInput.value;
                            }
                            break;

                        case 'multiple':
                            const checkboxes = questionsContainer.querySelectorAll('input[type="checkbox"]:checked');
                            survey2Data[question.key] = Array.from(checkboxes).map(cb => cb.value);
                            break;

                        case 'text':
                            const textInput = questionsContainer.querySelector('.text-input');
                            survey2Data[question.key] = textInput.value;
                            break;

                        case 'textarea':
                            const textareaInput = questionsContainer.querySelector('.textarea-input');
                            survey2Data[question.key] = textareaInput.value;
                            break;

                        case 'ranking':
                            // Ranking is handled by click events, no need to collect here
                            break;

                        case 'scale':
                            // Scale is handled by click events, no need to collect here
                            break;
                    }
                }

                nextButton.addEventListener('click', () => {
                    collectCurrentAnswer();
                    if (currentQuestionIndex < survey2Questions.length - 1) {
                        currentQuestionIndex++;
                        renderQuestion(currentQuestionIndex);
                    }
                });

                prevButton.addEventListener('click', () => {
                    collectCurrentAnswer();
                    if (currentQuestionIndex > 0) {
                        currentQuestionIndex--;
                        renderQuestion(currentQuestionIndex);
                    }
                });

                submitButton.addEventListener('click', () => {
                    collectCurrentAnswer();
                    
                    const form = document.createElement('form');
                    form.method = 'POST';
                    form.style.display = 'none';
                    
                    const input = document.createElement('input');
                    input.type = 'hidden';
                    input.name = 'survey2_data';
                    input.value = JSON.stringify(survey2Data);
                    
                    form.appendChild(input);
                    document.body.appendChild(form);
                    form.submit();
                });

                // Initialize survey
                surveyOverlay.style.display = 'flex';
                renderQuestion(currentQuestionIndex);
            });
            </script>
            <?php
        }
    }
}
add_action('wp_footer', 'add_survey2_overlay');

// Admin page to view survey2 responses
function custom_user_survey2_answers_submenu() {
    add_users_page(
        'User Survey 2 Answers', // Page title
        'Survey 2 Answers',      // Menu title
        'manage_options',        // Capability
        'user-survey2-answers',  // Menu slug
        'display_user_survey2_answers_page' // Callback function
    );
}
add_action('admin_menu', 'custom_user_survey2_answers_submenu');

function display_user_survey2_answers_page() {
    if (!current_user_can('manage_options')) {
        return;
    }
    
    // Handle delete action
    if (isset($_GET['action']) && $_GET['action'] === 'delete' && isset($_GET['user_id'])) {
        $user_id = intval($_GET['user_id']);
        if ($user_id > 0) {
            delete_user_meta($user_id, 'survey2');
            echo '<div class="notice notice-success is-dismissible"><p>Survey2 data deleted successfully!</p></div>';
        }
    }
    
    // Get all users
    $users = get_users();
    echo '<div class="wrap">';
    echo '<h1>User Survey 2 Answers - LooksFam User Research</h1>';
    
    // Add export functionality
    if (isset($_GET['export']) && $_GET['export'] === 'csv') {
        header('Content-Type: text/csv');
        header('Content-Disposition: attachment; filename="survey2_responses_' . date('Y-m-d') . '.csv"');
        
        $output = fopen('php://output', 'w');
        
        // CSV headers
        fputcsv($output, [
            'User', 'Discovery Method', 'First Impression', 'Attention Feature', 
            'Profession', 'Target Exam', 'Months to Exam', 'Current Study Methods', 
            'Study Challenges', 'Feature Ranking', 'Choosing Factors', 
            'Device Preference', 'Frustrations to Solve', 'Recommendation Score'
        ]);
        
        foreach ($users as $user) {
            $survey_data = get_user_meta($user->ID, 'survey2', true);
            if ($survey_data && is_array($survey_data)) {
                fputcsv($output, [
                    $user->display_name,
                    $survey_data['discovery_method'] ?? 'N/A',
                    $survey_data['first_impression'] ?? 'N/A',
                    $survey_data['attention_feature'] ?? 'N/A',
                    $survey_data['profession'] ?? 'N/A',
                    $survey_data['target_exam'] ?? 'N/A',
                    $survey_data['months_to_exam'] ?? 'N/A',
                    is_array($survey_data['current_study_methods'] ?? '') ? 
                        implode('; ', $survey_data['current_study_methods']) : 
                        ($survey_data['current_study_methods'] ?? 'N/A'),
                    $survey_data['study_challenges'] ?? 'N/A',
                    is_array($survey_data['feature_ranking'] ?? '') ? 
                        json_encode($survey_data['feature_ranking']) : 
                        ($survey_data['feature_ranking'] ?? 'N/A'),
                    $survey_data['choosing_factors'] ?? 'N/A',
                    $survey_data['device_preference'] ?? 'N/A',
                    $survey_data['frustrations_to_solve'] ?? 'N/A',
                    $survey_data['recommendation_score'] ?? 'N/A'
                ]);
            }
        }
        
        fclose($output);
        exit;
    }
    
    echo '<p><a href="?page=user-survey2-answers&export=csv" class="button button-primary">Export to CSV</a></p>';
    
    echo '<table class="widefat fixed" cellspacing="0" style="table-layout: auto;">';
    echo '<thead><tr>';
    echo '<th style="width: 80px;">User</th>';
    echo '<th>Discovery</th>';
    echo '<th>Impression</th>';
    echo '<th>Attention</th>';
    echo '<th>Profession</th>';
    echo '<th>Exam</th>';
    echo '<th>Months</th>';
    echo '<th>Study Methods</th>';
    echo '<th>Challenges</th>';
    echo '<th>Rankings</th>';
    echo '<th>Choosing Factors</th>';
    echo '<th>Device</th>';
    echo '<th>Frustrations</th>';
    echo '<th>Score</th>';
    echo '<th style="width: 80px;">Actions</th>';
    echo '</tr></thead>';
    echo '<tbody>';
    
    foreach ($users as $user) {
        $user_id = $user->ID;
        $survey_data = get_user_meta($user_id, 'survey2', true);
        
        if ($survey_data && is_array($survey_data)) {
            echo '<tr>';
            echo '<td>' . esc_html($user->display_name) . '</td>';
            echo '<td>' . esc_html($survey_data['discovery_method'] ?? 'N/A') . '</td>';
            echo '<td style="max-width: 200px; word-wrap: break-word;">' . esc_html(substr($survey_data['first_impression'] ?? 'N/A', 0, 100)) . '</td>';
            echo '<td>' . esc_html($survey_data['attention_feature'] ?? 'N/A') . '</td>';
            echo '<td>' . esc_html($survey_data['profession'] ?? 'N/A') . '</td>';
            echo '<td>' . esc_html($survey_data['target_exam'] ?? 'N/A') . '</td>';
            echo '<td>' . esc_html($survey_data['months_to_exam'] ?? 'N/A') . '</td>';
            
            // Handle multiple study methods
            $study_methods = $survey_data['current_study_methods'] ?? 'N/A';
            if (is_array($study_methods)) {
                $study_methods = implode(', ', $study_methods);
            }
            echo '<td>' . esc_html($study_methods) . '</td>';
            
            echo '<td>' . esc_html($survey_data['study_challenges'] ?? 'N/A') . '</td>';
            
            // Handle rankings
            $rankings = $survey_data['feature_ranking'] ?? 'N/A';
            if (is_array($rankings)) {
                $ranking_text = '';
                foreach ($rankings as $feature => $rank) {
                    $ranking_text .= substr($feature, 0, 20) . ': ' . $rank . '; ';
                }
                echo '<td style="max-width: 150px; font-size: 11px;">' . esc_html(trim($ranking_text, '; ')) . '</td>';
            } else {
                echo '<td>N/A</td>';
            }
            
            echo '<td style="max-width: 200px; word-wrap: break-word;">' . esc_html(substr($survey_data['choosing_factors'] ?? 'N/A', 0, 100)) . '</td>';
            echo '<td>' . esc_html($survey_data['device_preference'] ?? 'N/A') . '</td>';
            echo '<td style="max-width: 200px; word-wrap: break-word;">' . esc_html(substr($survey_data['frustrations_to_solve'] ?? 'N/A', 0, 100)) . '</td>';
            echo '<td>' . esc_html($survey_data['recommendation_score'] ?? 'N/A') . '</td>';
            echo '<td><a href="?page=user-survey2-answers&action=delete&user_id=' . $user_id . '" class="button button-small button-secondary" onclick="return confirm(\'Are you sure you want to delete this survey2 response?\')">Delete</a></td>';
            echo '</tr>';
        }
    }
    
    echo '</tbody></table>';
    echo '</div>';
}
// ================================
//  LOOKSFAM PILOT RUN FORM
// ================================
function looksfam_pilot_form_shortcode() {
    if ( isset($_POST['lpf_submit']) ) {
        $school      = sanitize_text_field($_POST['school']);
        $rep_name    = sanitize_text_field($_POST['rep_name']);
        $rep_email   = sanitize_email($_POST['rep_email']);
        $rep_mobile  = sanitize_text_field($_POST['rep_mobile']);
        $program     = sanitize_text_field($_POST['program']);
        $students    = sanitize_text_field($_POST['students']);
        $duration    = sanitize_text_field($_POST['duration']);
        $referral    = sanitize_text_field($_POST['referral']);
        
        // New fields
        $objectives  = isset($_POST['objectives']) ? array_map('sanitize_text_field', $_POST['objectives']) : [];
        $other_obj   = sanitize_text_field($_POST['other_objective']);
        
        // Combine objectives
        if (!empty($other_obj)) {
            $objectives[] = 'Others: ' . $other_obj;
        }
        $objectives_str = implode(', ', $objectives);
        
        // Save to custom post type "pilot"
        $post_id = wp_insert_post([
            'post_title'  => $school . ' - Pilot Application',
            'post_type'   => 'pilot',
            'post_status' => 'publish',
            'meta_input'  => [
                'school'      => $school,
                'rep_name'    => $rep_name,
                'rep_email'   => $rep_email,
                'rep_mobile'  => $rep_mobile,
                'program'     => $program,
                'students'    => $students,
                'duration'    => $duration,
                'referral'    => $referral,
                'objectives'  => $objectives_str
            ]
        ]);
        
        // Email to applicant
        $subject = "LOOKSFAM Pilot Run Application Received";
        $message = "Dear $rep_name,\n\nThank you for applying for the LOOKSFAM Pilot Run.\nOur team will review your application and contact you shortly.\n\nBest regards,\nLOOKSFAM Team";
        $headers = [
            'From:  Bentamo - BNTM Technologies Inc. - LOOKSFAM.CO <no-reply@' . $_SERVER['SERVER_NAME'] . '>',
            'BCC: bentamosabentamo@gmail.com'
        ];
        wp_mail($rep_email, $subject, $message, $headers);
        
        echo '<div class="lpf-success">✓ Your application has been submitted. Kindly check your email for confirmation.</div>';
    }
    
    ob_start(); ?>
    <div class="lpf-wrapper">
        <form method="post" class="lpf-form">
            <div class="lpf-field">
                <label>School / Institution Name <span class="lpf-required">*</span></label>
                <input type="text" name="school" required>
            </div>
            
            <div class="lpf-field">
                <label>Authorized Representative Name <span class="lpf-required">*</span></label>
                <input type="text" name="rep_name" required>
            </div>
            
            <div class="lpf-field">
                <label>Representative Email <span class="lpf-required">*</span></label>
                <input type="email" name="rep_email" required>
            </div>
            
            <div class="lpf-field">
                <label>Representative Mobile <span class="lpf-required">*</span></label>
                <input type="text" name="rep_mobile" required>
            </div>
            
            <div class="lpf-field">
                <label>Program / Department <span class="lpf-required">*</span></label>
                <input type="text" name="program" required>
            </div>
            
            <div class="lpf-field">
                <label>Estimated No. of Students <span class="lpf-required">*</span></label>
                <input type="number" name="students" min="1" required>
            </div>
            
            <div class="lpf-field">
                <label>Where did you heard LOOKSFAM? <span class="lpf-required">*</span></label>
                <input type="text" name="referral" required>
            </div>
            
            <div class="lpf-field">
                <label>Pilot Duration <span class="lpf-required">*</span></label>
                <select name="duration" required>
                    <option value="">Select duration</option>
                    <option value="30 Days">30 Days</option>
                    <option value="60 Days">60 Days</option>
                    <option value="90 Days">90 Days</option>
                </select>
            </div>
            
            <div class="lpf-field">
                <label>Pilot Run Objectives <span class="lpf-required">*</span></label>
                <div class="lpf-checkboxes">
                    <label class="lpf-checkbox">
                        <input type="checkbox" name="objectives[]" value="Improve board exam passing rate">
                        Improve board exam passing rate
                    </label>
                    <label class="lpf-checkbox">
                        <input type="checkbox" name="objectives[]" value="Integrate adaptive review tools for classes">
                        Integrate adaptive review tools for classes
                    </label>
                    <label class="lpf-checkbox">
                        <input type="checkbox" name="objectives[]" value="Supplement existing review programs">
                        Supplement existing review programs
                    </label>
                    <label class="lpf-checkbox">
                        <input type="checkbox" name="objectives[]" value="Evaluate LOOKSFAM for long-term institutional use">
                        Evaluate LOOKSFAM for long-term institutional use
                    </label>
                    <label class="lpf-checkbox">
                        <input type="checkbox" id="lpf-other-check" onclick="document.getElementById('lpf-other-field').focus()">
                        Others (please specify):
                    </label>
                    <input type="text" id="lpf-other-field" name="other_objective" placeholder="Specify other objective">
                </div>
            </div>
            
            <div class="lpf-field lpf-terms">
                <label>Terms & Conditions <span class="lpf-required">*</span></label>
                <label class="lpf-checkbox">
                    <input type="checkbox" required>
                    I confirm that the institution is applying for the LOOKSFAM Pilot Run.
                </label>
                <label class="lpf-checkbox">
                    <input type="checkbox" required>
                    I acknowledge that approval is subject to Bentamo - BNTM Technologies Inc. verification and availability.
                </label>
                <label class="lpf-checkbox">
                    <input type="checkbox" required>
                    I understand that pilot access is for evaluation purposes only.
                </label>
            </div>
            
            <div class="lpf-field lpf-privacy">
                <label class="lpf-checkbox">
                    <input type="checkbox" required>
                    <strong>Data Privacy Consent:</strong> I consent to the collection and processing of information for evaluating eligibility and conducting the pilot run, in compliance with the Data Privacy Act of 2012 and GDPR (if applicable).
                </label>
            </div>
            
            <button type="submit" name="lpf_submit" class="lpf-submit">Submit Application</button>
        </form>
    </div>
    
    <style>
        .lpf-wrapper {
            max-width: 600px;
            margin: 0 auto;
        }
        .lpf-form {
            background: var(--ast-global-color-5, #fff);
            padding: 2rem;
            border-radius: 8px;
            box-shadow: 0 2px 8px rgba(0,0,0,0.1);
        }
        .lpf-field {
            margin-bottom: 1.5rem;
        }
        .lpf-field label {
            display: block;
            margin-bottom: 0.5rem;
            color: var(--ast-global-color-2, #3a3a3a);
            font-weight: 500;
        }
        .lpf-required {
            color: var(--ast-global-color-0, #cc0000);
        }
        .lpf-field input[type="text"],
        .lpf-field input[type="email"],
        .lpf-field input[type="number"],
        .lpf-field select {
            width: 100%;
            padding: 0.75rem;
            border: 1px solid var(--ast-border-color, #ddd);
            border-radius: 4px;
            font-size: 1rem;
            transition: border-color 0.2s;
        }
        .lpf-field input:focus,
        .lpf-field select:focus {
            outline: none;
            border-color: var(--ast-global-color-0, #cc0000);
        }
        .lpf-checkboxes {
            padding: 0.5rem 0;
        }
        .lpf-checkbox {
            display: flex;
            align-items: flex-start;
            gap: 0.5rem;
            margin-bottom: 0.75rem;
            font-weight: 400;
            cursor: pointer;
        }
        .lpf-checkbox input[type="checkbox"] {
            margin-top: 0.25rem;
            cursor: pointer;
        }
        #lpf-other-field {
            width: 100%;
            margin-top: 0.5rem;
            padding: 0.5rem;
            border: 1px solid var(--ast-border-color, #ddd);
            border-radius: 4px;
        }
        .lpf-terms,
        .lpf-privacy {
            background: #f9f9f942;
            padding: 1rem;
            border-radius: 4px;
        }
        .lpf-submit {
            width: 100%;
            padding: 1rem;
            background: var(--ast-global-color-0, #cc0000);
            color: #fff;
            border: none;
            border-radius: 4px;
            font-size: 1rem;
            font-weight: 600;
            cursor: pointer;
            transition: background 0.2s;
        }
        .lpf-submit:hover {
            background: var(--ast-global-color-1, #a30000);
        }
        .lpf-success {
            max-width: 600px;
            margin: 0 auto 1.5rem;
            padding: 1rem;
            background: #d4edda;
            color: #155724;
            border: 1px solid #c3e6cb;
            border-radius: 4px;
            font-weight: 500;
        }
    </style>
    <?php return ob_get_clean();
}
add_shortcode('looksfam_pilot_form', 'looksfam_pilot_form_shortcode');

// ================================
//  REGISTER "pilot" POST TYPE
// ================================
function register_pilot_post_type() {
    register_post_type('pilot', [
        'label'        => 'Pilot Applications',
        'public'       => true,
        'supports'     => ['title', 'custom-fields'],
        'show_in_menu' => true,
        'menu_icon'    => 'dashicons-clipboard'
    ]);
}
add_action('init', 'register_pilot_post_type');

// ================================
//  ADD SUBMENU FOR PILOT APPLICATIONS
// ================================
function looksfam_pilot_admin_menu() {
    add_submenu_page(
        'edit.php?post_type=pilot',
        'All Applications',
        'View Table',
        'manage_options',
        'pilot-applications',
        'looksfam_pilot_table_page'
    );
}
add_action('admin_menu', 'looksfam_pilot_admin_menu');

// ================================
//  DISPLAY TABLE PAGE
// ================================
function looksfam_pilot_table_page() {
    global $wpdb;
    
    // Get all pilot applications
    $pilots = get_posts([
        'post_type'      => 'pilot',
        'posts_per_page' => -1,
        'orderby'        => 'date',
        'order'          => 'DESC'
    ]);
    ?>
    <div class="wrap">
        <h1>Pilot Run Applications</h1>
        <p class="description">Total Applications: <strong><?php echo count($pilots); ?></strong></p>
        
        <?php if (empty($pilots)): ?>
            <div class="notice notice-info">
                <p>No applications yet.</p>
            </div>
        <?php else: ?>
            <table class="wp-list-table widefat fixed striped">
                <thead>
                    <tr>
                        <th style="width: 100px;">Date</th>
                        <th>School</th>
                        <th>Representative</th>
                        <th>Email</th>
                        <th>Mobile</th>
                        <th>Program</th>
                        <th style="width: 80px;">Students</th>
                        <th style="width: 80px;">Duration</th>
                        <th>Objectives</th>
                        <th>Referral</th>
                    </tr>
                </thead>
                <tbody>
                    <?php foreach ($pilots as $pilot): 
                        $meta = get_post_meta($pilot->ID);
                    ?>
                        <tr>
                            <td><?php echo get_the_date('M j, Y', $pilot->ID); ?></td>
                            <td><strong><?php echo esc_html($meta['school'][0] ?? '-'); ?></strong></td>
                            <td><?php echo esc_html($meta['rep_name'][0] ?? '-'); ?></td>
                            <td>
                                <?php 
                                $email = $meta['rep_email'][0] ?? '';
                                echo $email ? '<a href="mailto:' . esc_attr($email) . '">' . esc_html($email) . '</a>' : '-';
                                ?>
                            </td>
                            <td><?php echo esc_html($meta['rep_mobile'][0] ?? '-'); ?></td>
                            <td><?php echo esc_html($meta['program'][0] ?? '-'); ?></td>
                            <td><?php echo esc_html($meta['students'][0] ?? '-'); ?></td>
                            <td><span class="lpf-badge"><?php echo esc_html($meta['duration'][0] ?? '-'); ?></span></td>
                            <td>
                                <?php 
                                $objectives = $meta['objectives'][0] ?? '';
                                if ($objectives) {
                                    echo '<div class="lpf-objectives">' . nl2br(esc_html(str_replace(', ', "\n• ", '• ' . $objectives))) . '</div>';
                                } else {
                                    echo '-';
                                }
                                ?>
                            </td>
                            
                            <td><?php echo esc_html($meta['referral'][0] ?? '-'); ?></td>
                        </tr>
                    <?php endforeach; ?>
                </tbody>
            </table>
        <?php endif; ?>
    </div>
    
    <style>
        .wrap h1 { margin-bottom: 10px; }
        .wrap .description { margin-bottom: 20px; }
        .wp-list-table td { 
            vertical-align: middle;
            font-size: 13px;
        }
        .wp-list-table th {
            font-weight: 600;
        }
        .lpf-badge {
            display: inline-block;
            padding: 4px 8px;
            background: #2271b1;
            color: #fff;
            border-radius: 3px;
            font-size: 11px;
            font-weight: 600;
        }
        .lpf-objectives {
            font-size: 12px;
            line-height: 1.6;
            color: #646970;
            max-width: 300px;
        }
        .wp-list-table a {
            text-decoration: none;
        }
        .wp-list-table a:hover {
            text-decoration: underline;
        }
    </style>
    <?php
}
?>