<?php 



// Function to display exam statistics
function exercise_content($atts) {
    $atts = shortcode_atts(array(
        'exam' => 0,
    ), $atts);

    $exam_id = intval($_GET['id']);
    $exam_title = get_the_title($exam_id);
    $class_id = intval($_GET['class_id']);
    $user_id = get_current_user_id();

    if (empty($exam_id) || get_post_type($exam_id) !== 'exam') {
        return 'Invalid or missing exam ID.';
    }

    if (empty(intval($_GET['take']))) {
        $exam_results = get_post_meta($exam_id, 'exam_results', true);
        $user_check = false;
        foreach ($exam_results as $result) {
            if($result['user_id'] == $user_id){
                $user_check = true;
                break;
            }
        }
        
        if ($user_check) {
            $type = 'exercises';
            return display_exam_statistics_ui($exam_id, $user_id, $class_id,$type);
        }
    }

    $selected_questions = get_post_meta($exam_id, 'selected_questions', true);


    if (!empty($selected_questions)) {
        return display_exam_ui($exam_id, $selected_questions, $exam_title, $class_id, 'exercise');
    } else {
        return 'This exam has no selected questions.';
    }

}
add_shortcode('exercise', 'exercise_content');


function display_exam_with_solution_ui($exam_id, $selected_questions, $exam_title, $class_id) {
    $associated_classes = get_post_meta($exam_id, 'associated_classes', true);

    if (empty($associated_classes)) {
        return '<p>Exam is not associated with any class. It can be viewed by everyone.</p>';
    } elseif (is_user_enrolled_in_any_class($associated_classes)) {
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
            <div class="timer-container">
                <div class="countdown" id="countdown" style="text-align:center;"></div>
                <div class="timer-bar" id="timer=progress-bar"></div>
            </div>
            <h3><?php echo $exam_title; ?></h3>
            <div class="score-display" id="score-display" style="text-align:center; font-weight:bold;"></div>

            <div class="progress-container">
                <div class="progress-bar" id="progress-bar"></div>
            </div>

            <style>
                .options-grid {
                    display: grid;
                    grid-template-columns: repeat(2, 1fr);
                    gap: 10px;
                    margin-top: 10px;
                }

                .option-container {
                    border: 1px solid #ccc;
                    border-radius: 10px;
                    min-height: 250px;
                    padding: 10px;
                    cursor: pointer;
                    display: flex;
                    align-items: center;
                    justify-content: center;
                    text-align: center;
                }

                /*.option-container.disabled {
                    cursor: not-allowed;
                    background-color: #e9ecef;
                }*/

                .dis-button{
                    cursor: not-allowed!important;
                    background-color: #e9ecef!important; 
                }

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
                    background-color: #c1e2b3; /* Correct answer color (green) */
                }

                .incorrect-answer {
                    background-color: #ffcccc; /* Incorrect answer color (red) */
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

                .button-container {
                    display: flex;
                    justify-content: space-between;
                    margin-top: 10px;
                }

                .button-primary {
                    width: calc(33.33% - 10px); /* Adjust the width as needed */
                    margin: 5px;
                }  

                .go-back-button {
                    margin-top: 20px;
                    margin-bottom: 20px;
                    height: 50px;
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
            </style>

            <div class="question-container">
                <?php foreach ($selected_questions as $index => $question_id): 
                    $question = get_post($question_id);
                    $question_title = esc_html($question->post_title);
                    $question_solution = get_post_meta($question_id, 'solution', true);
                    if (empty($question_solution)) {
                        $question_solution = 'No Solution available';
                    }
                    $multiple_choice_options = get_post_meta($question_id, 'multiple_choice_options', true);
                    $correct_answer = get_post_meta($question_id, 'correct_answer', true);
                ?>
                    <div class='question' id='question-<?php echo $index; ?>'>
                        <p><strong>Question:</strong> <?php echo $question_title; ?></p>
                        <div class="options-grid">
                            <?php 
                            $shuffled_letters = ['A', 'B', 'C', 'D'];
                            shuffle($shuffled_letters);
                            foreach ($shuffled_letters as $option):
                                $optionId = "option-$index-$option";
                            ?>
                                <div class='option-container' onclick='selectOption(<?php echo $index; ?>, "<?php echo $optionId; ?>", "<?php echo $correct_answer; ?>")' id='option-container-<?php echo $optionId; ?>'>
                                    <input hidden type='radio' name='user_answers[<?php echo $question_id; ?>]' value='<?php echo $option; ?>' id='<?php echo $optionId; ?>'>
                                    <label for='<?php echo $optionId; ?>'>
                                        <h4><?php echo $multiple_choice_options[$option]; ?></h4>
                                    </label>
                                </div>
                            <?php endforeach; ?>
                        </div>
                        <div>
                            Any concerns or suggestions for this question? <a href='contact?id=<?php echo $question_id; ?>' target='_blank'>Click here</a><br>
                            <div id="message-<?php echo $index; ?>" style="margin-top: 10px; display:none; text-align:center;">Please select an answer!</div>
                            <div class="solution" id="solution-<?php echo $index; ?>" style="margin-top: 10px; display:none;">
                                <h4>Solution:</h4>
                                <?php echo nl2br(esc_html($question_solution)); ?>
                            </div>
                        </div>
                    </div>
                <?php endforeach; ?>
            </div>

            <div class="button-container" style="display:none">
                <input type="button" value="Confirm Answer" class="button-primary" id="check-question" style="border-radius: 10px; width:100%;">
            </div>
            <div class="button-container">
                <input type="button" value="Previous Question" class="button-primary" id="prev-question" style="border-radius: 10px; width:50%; display:none;">
                <input disabled class="dis-button button-primary" type="button" value="Next Question" id="next-question" style="border-radius: 10px; width:50%;">
                <input disabled type="submit" name="submit_exam" value="Submit Exam" class="dis-button button-primary" id="back" style="border-radius: 10px; width:50%; display:none;">
            </div>
        </form>

        <script>
       var score = 0; // Initialize score
var totalQuestions;
var questions;
var currentIndex = 0;
var answeredQuestions = {}; // Track answered questions

function updateScoreDisplay() {
    var scoreDisplay = document.getElementById("score-display");
    scoreDisplay.textContent = score + "/" + totalQuestions;
}

function checkIfAllAnswered() {
    var allAnswered = Object.keys(answeredQuestions).length === totalQuestions;
    var submitButton = document.getElementById("back");

    if (allAnswered) {
        submitButton.disabled = false;
        submitButton.classList.remove("dis-button");
    } else {
        submitButton.disabled = true;
        submitButton.classList.add("dis-button");
    }
}

function selectOption(currentIndex, optionId, correctAnswer) {
    var radioElement = document.getElementById(optionId);
    if (radioElement) {
        var questionContainer = document.querySelector(".question:nth-child(" + (currentIndex + 1) + ")");
        var optionContainers = questionContainer.querySelectorAll(".option-container");

        radioElement.checked = true;
        var selectedOptionValue = radioElement.value;
        var isCorrect = selectedOptionValue === correctAnswer;

        var radioButtons = questionContainer.querySelectorAll("input[type=radio]");
        radioButtons.forEach(function (radio) {
            var optionContainer = document.getElementById("option-container-" + radio.id);
            if (radio.checked && optionContainer) {
                optionContainer.style.backgroundColor = isCorrect ? "#c1e2b3" : "#ffcccc";
            } else if (optionContainer) {
                optionContainer.style.backgroundColor = "";
            }
        });

        // Highlight correct answer if the selected option is wrong
        if (!isCorrect) {
            var correctAnswerContainer = document.getElementById("option-container-option-" + currentIndex + "-" + correctAnswer);
            if (correctAnswerContainer) {
                correctAnswerContainer.style.backgroundColor = "#c1e2b3"; // Correct answer color (green)
            }
        }

        // Disable all option containers
        optionContainers.forEach(function (optionContainer) {
            optionContainer.classList.add("disabled");
            optionContainer.onclick = null; // Disable click event
        });

        // Increment score if the answer is correct
        if (isCorrect) {
            score++;
        }

        // Update the score display
        updateScoreDisplay();

        // Enable next button
        var nextButton = document.getElementById("next-question");
        nextButton.disabled = false;
        nextButton.classList.remove("dis-button");

        // Hide message
        var messageDiv = document.getElementById("message-" + currentIndex);
        messageDiv.style.display = "none";

        // Mark question as answered
        answeredQuestions[currentIndex] = true;

        // Check if all questions are answered
        checkIfAllAnswered();
    } else {
        console.error("Radio element not found: " + optionId);
    }
}

function showQuestion(index) {
    questions.forEach(function (question) {
        question.style.display = "none";
    });
    questions[index].style.display = "block";
    var progressWidth = (index + 1) / totalQuestions * 100;
    document.getElementById("progress-bar").style.width = progressWidth + "%";
    var prevButton = document.getElementById("prev-question");
    prevButton.style.display = index > 0 ? "inline-block" : "none";

    // Always enable the next button if the question has been answered
    var nextButton = document.getElementById("next-question");
    nextButton.disabled = false;
    nextButton.classList.remove("dis-button");
}

document.addEventListener("DOMContentLoaded", function() {
    questions = document.querySelectorAll(".question");
    totalQuestions = <?php echo count($selected_questions); ?>;
    var nextButton = document.getElementById("next-question");
    var prevButton = document.getElementById("prev-question");
    var backButton = document.getElementById("back");
    var checkButton = document.getElementById("check-question");

    checkButton.addEventListener("click", function() {
        var selectedOption = questions[currentIndex].querySelector("input[type=radio]:checked");
        var messageDiv = document.getElementById("message-" + currentIndex);

        if (!selectedOption) {
            messageDiv.style.display = "block";
            nextButton.disabled = true;
            nextButton.classList.add("dis-button");
        } else {
            messageDiv.style.display = "none";
            var correctAnswer = "<?php echo $correct_answer; ?>";
            selectOption(currentIndex, selectedOption.id, correctAnswer);
        }
    });

    nextButton.addEventListener("click", function() {
        currentIndex++;
        showQuestion(currentIndex);
        nextButton.style.display = currentIndex < totalQuestions - 1 ? "inline-block" : "none";
        prevButton.style.display = currentIndex > 0 ? "inline-block" : "none";
        backButton.style.display = currentIndex < totalQuestions - 1 ? "none" : "inline-block";
    });

    prevButton.addEventListener("click", function() {
        currentIndex--;
        showQuestion(currentIndex);
        nextButton.style.display = currentIndex < totalQuestions - 1 ? "inline-block" : "none";
        prevButton.style.display = currentIndex > 0 ? "inline-block" : "none";
        backButton.style.display = currentIndex < totalQuestions - 1 ? "none" : "inline-block";
    });

            // Show the first question initially
            showQuestion(currentIndex);
            updateScoreDisplay(); // Initial score display
        });



        </script>
        <?php
        return ob_get_clean();
    } else {
        return '<p>You are not enrolled in any class associated with this exam.</p>';
    }
}


?>