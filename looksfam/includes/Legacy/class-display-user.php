<?php 
function dropdown_menu() {
    ob_start(); 
    
    lf_loading_screen();?>
    
    <!-- Dropdown Menu (right side) -->
    <div class="menu-container" >
        <button class=" menu-button" style="border-radius:10px;padding:5px 10px;cursor:pointer;">
            ☰ 
        </button>
        <div class="dropdown-menu">
            <a href="<?php echo home_url('/profile'); ?>">Profile</a>
            <a href="<?php echo wp_logout_url(home_url()); ?>">Logout</a>
        </div>
    </div>

    <style>
    .menu-container {
      position: relative;
      display: inline-block;
      position:absolute;
      right:20px;
      z-index:999;
    }
    .menu-button {
      margin:20px 0px;
      color: #fff;
      border: none;
      height:50px;
      background-color: #00000024!important;
      width:70px;
          font-size: 28px;
    }
    .dropdown-menu {
      display: none;
      position: absolute;
      right: 0;
      margin-top: 5px;
      background: #fff;
      border-radius: 8px;
      box-shadow: 0 4px 8px rgba(0,0,0,0.2);
      overflow: hidden;
      min-width: 150px;
      opacity: 0;
      transform: translateY(-10px);
      transition: all 0.3s ease;
    }
    .dropdown-menu a {
      display: block;
      padding: 10px;
      text-decoration: none;
      color: #333;
    }
    .dropdown-menu a:hover {
      background: #f0f0f0;
    }
    .menu-container.open .dropdown-menu {
      display: block;
      opacity: 1;
      transform: translateY(0);
    }
    </style>

    <script>
    document.addEventListener("DOMContentLoaded", function() {
        const btn = document.querySelector('.menu-button');
        const container = document.querySelector('.menu-container');
        if(btn){
            btn.addEventListener('click', function() {
                container.classList.toggle('open');
            });
        }
    });
    </script>
    
    <?php
    return ob_get_clean();
}

function calculatePercentageScore($exam_id, $user_id) {
    $prevExamQuery = get_post_meta($exam_id, 'exam_results', true);
    $selectedQuestions = get_post_meta($exam_id, 'selected_questions', true);

    $highestCorrectCount = 0;
    $sessionCorrectCount = 0;
    $latestSessionId = '';

    foreach ($prevExamQuery as $result) {
        if ($result['user_id'] == $user_id) {
            $correctCount = $result['is_correct'];
            $sessionId = $result['session_id'];

            if ($sessionId === $latestSessionId) {
                $sessionCorrectCount += $correctCount;
            } else {
                $latestSessionId = $sessionId;
                $sessionCorrectCount = $correctCount;
            }

            if ($sessionCorrectCount > $highestCorrectCount) {
                $highestCorrectCount = $sessionCorrectCount;
            }
        }
    }

    $totalCount = count($selectedQuestions);
    $percentageScore = ($totalCount > 0) && ($highestCorrectCount !==0) ? round(($highestCorrectCount / $totalCount) * 100, 2) : 0;

    return $percentageScore;
}

function displayStarRating($percentageScore) {
    // Determine star rating
    $starRating = ($percentageScore >= 91) ? 5 : (($percentageScore >= 76) ? 4 : (($percentageScore >= 61) ? 3 : (($percentageScore >= 31) ? 2 : (($percentageScore >= 1) ? 1 : 0))));

        echo '<p style="font-size:50px;margin-bottom: 0px;">';
    // Display stars based on the rating
    for ($i = 1; $i <= $starRating; $i++) {
        echo '⭐';
    }
    
        echo '</p>';
}
function topscores_exam($class_id, $current_user_id) {
    ob_start();
    hover_looks();

    // Get all user IDs

    // Create an array to store user scores
    $user_scores = array();
    
    $enrolled_students = get_post_meta($class_id, 'enrolled_students', true) ?: array();

    // Loop through each user
    foreach ($enrolled_students as $user_id) {
        $score_info = topscoredisplay($class_id, $user_id);

        // Only include users with scores
        if ($score_info) {
            $user_scores[$user_id] = $score_info;
        }
    }

    // Sort the user scores in descending order
    arsort($user_scores);

    // Take only the top three scores
    $top_three_scores = array_slice($user_scores, 0, 10, true);

    ?>
    <h3 style="text-align:center;">Top Users</h3>
    <div class="stats-container" style="max-height: 50vh;">
        <div class="stats-line" style="border-bottom: 3px solid #fff;">
            <div class="stat-con-top" style="flex-grow: 1; text-align: left;">
                <strong>User</strong>
            </div>
            <div class="stat-con" style="flex-grow: 1; text-align: right;">
                <strong>
                    Rating
                    <!-- Info Icon -->
                    <span class="info-icon" onclick="toggleDescription()">
                        &#8505; <!-- Unicode for info symbol -->
                    </span>
                    <!-- Description Box -->
                    <div id="info-description" class="description-box" style="display: none;">
                        This rating is an AI algorithm percentage retention of your question and answers that decays over time. Can you stay on top?
                    </div>
                </strong>
            </div>
        </div>

        <?php
        // Loop through the top three scores
        $ranking = 1;
        foreach ($top_three_scores as $user_id => $score_info) {
            $username = esc_html(get_userdata($user_id)->user_login);
            ?>
            <div class="stats-line" style="margin-top: 0px;">
                <div class="stat-con-top" style="flex-grow: 1; text-align: left;">
                    <?php echo $ranking . '. ' . $username; ?>
                </div>
                <div class="stat-con" style="flex-grow: 1; text-align: right;">
                    <?php echo $score_info; ?>%
                </div>
            </div>
            <?php
            $ranking++; // Increment the ranking
        }

        // Your current standing
        $current_user_ranking = array_search($current_user_id, array_keys($user_scores)) + 1;
        $current_user_score = topscoredisplay($class_id, $current_user_id);
        ?>
        <div class="stats-line-user">
            <div class="stat-con-top" style="flex-grow: 1; text-align: left;">
                <h4 style="color:black"><?php echo $current_user_ranking . '. You - ' . esc_html(get_userdata($current_user_id)->user_login); ?></h4>
            </div>
            <div class="stat-con" style="flex-grow: 1; text-align: right;">
                <h4 style="color:black"><?php echo $current_user_score; ?>%</h4>
            </div>
        </div>
    </div>


    <?php

    $statcontent = ob_get_clean();
    return $statcontent;
}

function hover_looks() {
    ?>
    <script>
    function toggleDescription() {
        var descriptionBox = document.getElementById('info-description');
        if (descriptionBox.style.display === 'none' || descriptionBox.style.display === '') {
            descriptionBox.style.display = 'block';
        } else {
            descriptionBox.style.display = 'none';
        }
    }

    document.addEventListener('DOMContentLoaded', function() {
        document.querySelector('.info-icon').addEventListener('mouseover', function() {
            document.getElementById('info-description').style.display = 'block';
        });

        document.querySelector('.info-icon').addEventListener('mouseout', function() {
            document.getElementById('info-description').style.display = 'none';
        });
    });
    </script>

    <style>
    .info-icon {
        font-size: 1.2em;
        cursor: pointer;
        margin-left: 5px;
    }

    .description-box {
        
        border-radius: 5px;
        color: #ffffff;
        background-color: var(--ast-global-color-0);
        padding: 10px;
        position: absolute;
        z-index: 1000;
        margin-top: 5px;
        width: 200px;
        /*top: -50%;*/
        left: 80%;
        font-size: 12px;
    }

    @media (max-width: 720px) {
        .description-box {
            left: 45%;
        }
    }

    .stats-container {
        position: relative;
    }
    </style>
    <?php
}

function topscoredisplay($class_id, $user_id, $mode = 'looksfam') {
    global $wpdb;
    $table_name = $wpdb->prefix . 'exam_answers';
    
    // Get exam metadata
    $exam_topic_id   = get_post_meta($class_id, 'exam_topic', true);
    $exam_subject_id = get_post_meta($class_id, 'exam_subject', true);
    $parent_term_id  = $exam_topic_id ?: $exam_subject_id;
    
    // Build relevant categories
    $relevant_categories = $parent_term_id ? [$parent_term_id] : [];
    if ($parent_term_id) {
        $subcategories = get_terms([
            'taxonomy'   => 'question_category',
            'parent'     => $parent_term_id,
            'hide_empty' => false,
            'fields'     => 'ids',
        ]);
        if (!empty($subcategories)) {
            $relevant_categories = array_merge($relevant_categories, $subcategories);
        }
    }
    
    // Single query filtered by user and categories
    $placeholders = implode(',', array_fill(0, count($relevant_categories), '%d'));
    $activity_results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT question_id, is_correct, session_id, question_category 
             FROM $table_name 
             WHERE class_id = %d 
             AND user_id = %d 
             AND question_category IN ($placeholders)
             ORDER BY timestamp ASC",
            array_merge([$class_id, $user_id], $relevant_categories)
        ),
        ARRAY_A
    );
    
    if (empty($activity_results)) return 0;
    
    // Initialize stats
    $stats = [
        'q_sessions'      => 0,
        'total_algo_look' => 0,
        'correct_answers' => 0,
        'sessions'        => []
    ];
    
    // For looksfam mode, batch calculate all algo scores at once
    if ($mode === 'looksfam') {
        $question_ids = array_unique(array_column($activity_results, 'question_id'));
        $algo_scores = questionstatlooksfam_batch($question_ids, $user_id, $class_id);
        
        foreach ($activity_results as $result) {
            $stats['q_sessions']++;
            $stats['total_algo_look'] += $algo_scores[$result['question_id']] ?? 0;
            $stats['sessions'][$result['session_id']] = true;
            
            if ($result['is_correct']) {
                $stats['correct_answers']++;
            }
        }
        
        return ($stats['total_algo_look'] > 0 && $stats['correct_answers'] > 0) 
            ? round(($stats['total_algo_look'] / ($stats['q_sessions'] * 100)) * 100, 2) 
            : 0;
    }
    
    // For overall mode, no need for algo calculation
    if ($mode === 'overall') {
        foreach ($activity_results as $result) {
            $stats['q_sessions']++;
            $stats['sessions'][$result['session_id']] = true;
            
            if ($result['is_correct']) {
                $stats['correct_answers']++;
            }
        }
        
        $unique_sessions = count($stats['sessions']);
        return ($unique_sessions > 0) 
            ? round(($stats['correct_answers'] / $stats['q_sessions']) * 100, 2) 
            : 0;
    }
    
    return 0;
}




// Check if the current user is enrolled in any of the associated classes
function class_enrollees($class_id) {
    $current_user = wp_get_current_user();
    $user_id = $current_user->ID;
    $enrolled_students = get_post_meta($class_id, 'enrolled_students', true);

    foreach ($enrolled_students as $class_id) {
        $number_enrollees ++;
    }
    
    $number_enrollees += 0; 

    return $number_enrollees; // User is not enrolled in any of the associated classes
}
/**
 * Helper function to check if user is enrolled in a specific class
 */
function is_user_enrolled_in_class($user_id, $class_id) {
    $classes_enrolled = get_user_meta($user_id, 'classes_enrolled', true);
    
    // If no enrolled classes, return false
    if (empty($classes_enrolled) || !is_array($classes_enrolled)) {
        return false;
    }
    
    // Check if the class_id exists in the enrolled classes array
    return in_array($class_id, $classes_enrolled);
}

function get_styles() {
    ob_start();
    ?>
    <style>
        .button-container { display: flex; justify-content: space-between; margin-top: 10px; }
        .progress-container { height: 10px; background-color: rgba(1, 1, 1, 0.15); border-radius: 5px; overflow: hidden; }
        .progress-bar { height: 100%; border-radius: 5px; background-color: #e5e5e5; }
        .go-back-button { margin-top: 20px; margin-bottom: 20px; height: 50px; }
        .classes60 { margin-top: 20px; margin-bottom: 20px; }
        .stats-line { margin-top: 20px; display: flex; align-items: center; border-bottom: 1px solid #fff; }
        .stat-con-top { width: 50%; }
        .stats-line-user { padding: 20px 20px 0; margin: 0 -10px; display: flex; align-items: center; border: 3px solid #fff;background:#fff;color:black!important; border-radius: 10px; }
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
                .button-container-menu {
                    margin: 5px 0px;
                    display: flex;
                    gap: 10px;
                    flex-wrap: wrap;
                }
            .custom-button {
                        height:70px;
                        border-radius: 10px;
                        flex: 1;
                        cursor: pointer;
                        transition: background 0.3s ease;
                        padding: 10px;
                        text-align: center;
                    }
                .button-primary {
                    width:100%;
                    height:100px;
                    margin: 5px;
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
                    margin-top: 20px;
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
                 .category-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        .category-button {
            height: 200px;
        }
        .category-button button h5:hover {
           color:var(--ast-global-color-0)!important;
        }
        @media (max-width: 544px) {
            .category-grid {
                grid-template-columns: repeat(1, 1fr);
            }
            .category-link {
                font-size:unset!important;
            }
            .category-button {
                height: 110px!important;
            }
        }
        
        .category-link {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            width: 100%;
            border-radius: 15px;
            text-decoration: none;
            font-weight: bold;
            text-align: center;
            padding: 10px;
            box-sizing: border-box;
            font-weight:700;
            flex-direction: column;
                box-shadow: 0 4px 15px rgba(88, 194, 247, 0.3);
        }
        .category-link h4 {
            margin-bottom: 0px;
           
        }
        
        
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
        
        .card-icon {
            font-size: 24px;
            opacity: 0.7;
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
        
        /* Mobile Responsive */
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
        }
        .bb {
             position: relative;
            width: 100%;
            background: #00000012;
            
        }
        
        .bb::before {
            content: ''; /* Create the pseudo-element */
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            backdrop-filter: blur(15px); /* Apply the blur effect */
            -webkit-backdrop-filter: blur(15px); /* For Safari support */
            background: rgba(0, 0, 0, 0.2); /* Optional: Add a semi-transparent overlay */
            pointer-events: none; /* Make the overlay unclickable */
        }
        
        .bb > * {
            position: relative; /* Ensure child elements are above the blur */
           
        }
    </style>
    <?php
    return ob_get_clean();
}
function user_profile_shortcode() {
    //stop_all_wp_queries();
    $current_user = wp_get_current_user();
    $class_id = intval($_GET['class_id']);
    
    if (!$current_user || !$current_user->ID) {
        return 'User not found.';
    }
    
    ob_start();
    echo get_styles();
    add_action('wp_footer', 'add_survey2_overlay');
    if (!empty($class_id)) {
        // Check if user is enrolled in the requested class
        if (!is_user_enrolled_in_class($current_user->ID, $class_id)) {
            // User is not enrolled in this class, redirect to checkout or show error
            ?>
            <div style="text-align: center; padding: 20px; background: #f9f9f9; border-radius: 5px; margin: 20px 0;">
                <h3 style="color:#000">Access Denied</h3>
                <p style="color:#000">You are not enrolled in this class. Please enroll first to access the content.</p>
                <a href="<?php echo home_url('/checkout?class=' . $class_id); ?>" 
                   style="background: var(--ast-global-color-0); color: white; padding: 10px 20px; text-decoration: none; border-radius: 5px; display: inline-block; margin-top: 10px;">
                   Enroll Now
                </a>
                <br><br>
                <a href="<?php echo home_url('/profile'); ?>" 
                   style="color: #666; text-decoration: underline;">
                   Back to Profile
                </a>
            </div>
            <?php
            return ob_get_clean();
        }
        
        // User is enrolled, proceed with displaying class content
        $exam_topic = get_post_meta($class_id, 'exam_topic', true);
        $exam_subject = get_post_meta($class_id, 'exam_subject', true);
        if (!empty($exam_topic) || !empty($exam_subject)) {
            echo display_class_ran_page($class_id, $current_user->ID);
        } else {
            echo display_class_page($class_id, $current_user->ID);
        }
    } else {
        echo display_profile_page($current_user);
    }
    
    return ob_get_clean();
}

function display_analytics_graph($class_id, $user_id) {
    
    $has_free_trial = get_user_meta($user_id, 'free_trial_' . $class_id, true);
    //if (!current_user_can('manage_options')) return '<p>Analytics Coming Soon.</p>';
    ob_start();
    
    // Get all activity results for this class from the database
    global $wpdb;
    $table_name = $wpdb->prefix . 'exam_answers';
    
    $activity_results = $wpdb->get_results(
        $wpdb->prepare(
            "SELECT question_id, user_id, is_correct, session_id, question_category 
             FROM $table_name 
             WHERE class_id = %d 
             ORDER BY timestamp ASC",
            $class_id
        ),
        ARRAY_A
    );
    
    if (empty($activity_results)) {
        echo '<p>No activity data available for analytics.</p>';
        return ob_get_clean();
    }
    
    // Get parent term (topic or subject) similar to topscoredisplay
    $exam_topic_id = get_post_meta($class_id, 'exam_topic', true);
    $exam_subject_id = get_post_meta($class_id, 'exam_subject', true);
    $parent_term_id = $exam_topic_id ?: $exam_subject_id;
    
    if (!$parent_term_id) {
        echo '<p>No categories found for analytics.</p>';
        return ob_get_clean();
    }
    
    // Get relevant categories (parent + subcategories) similar to topscoredisplay
    $relevant_categories = [$parent_term_id];
    
    // Get subcategories
    $subcategories = get_terms([
        'taxonomy' => 'question_category',
        'parent' => $parent_term_id,
        'hide_empty' => false,
    ]);
    
    if (empty($subcategories) || is_wp_error($subcategories)) {
        echo '<p>No subcategories found for analytics.</p>';
        return ob_get_clean();
    }
    
    // Add subcategories to relevant categories
    $subcategory_ids = wp_list_pluck($subcategories, 'term_id');
    $relevant_categories = array_merge($relevant_categories, $subcategory_ids);
    
    // Get all unique users who participated in relevant categories
    $all_users = [];
    foreach ($activity_results as $result) {
        if (in_array($result['question_category'], $relevant_categories)) {
            $all_users[] = $result['user_id'];
        }
    }
    $all_users = array_unique($all_users);
    
    // Get current user info
    $current_user_info = get_userdata($user_id);
    $current_user_name = $current_user_info ? $current_user_info->display_name : 'You';
    
    ?>
    <div class="analytics-container">
        <h3 style="text-align: center; margin-bottom: 10px;">Performance Analytics</h3>
       
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
            <?php foreach ($subcategories as $index => $category): 
                $is_locked = $has_free_trial && $index >= 2; // Lock all except first 2 if free trial
                
                $user_scores = [];
                $current_user_score = 0;
                $average_score = 0;
                
                // Calculate scores only for unlocked categories (first 2 for free trial, all for full access)
                if (!$is_locked) {
                    // Calculate scores for all users in this category using the helper function
                    foreach ($all_users as $uid) {
                        $score = calculate_category_score_from_db($activity_results, $uid, $category->term_id, "overall");
                        if ($score > 0) { // Only include users who have attempted questions in this category
                            $user_scores[] = $score;
                        }
                        
                        if ($uid == $user_id) {
                            $current_user_score = $score;
                        }
                    }
                    
                    // Calculate average of all users who attempted questions in this category
                    $average_score = !empty($user_scores) ? round(array_sum($user_scores) / count($user_scores), 2) : 0;
                } else {
                    // Set default/placeholder values for locked categories
                    $current_user_score = rand(50, 80); // Random user score between 50 and 80
                    $average_score = rand(50, 80); // Random average score between 50 and 80
                }
            ?>
                <div class="category-chart-wrapper <?php echo $is_locked ? 'locked-chart' : ''; ?>" style="animation-delay: <?php echo $index * 0.2; ?>s;">
                    <?php if ($is_locked): ?>
                        <div class="locked-overlay">
                            <div class="lock-content">
                                <div class="lock-icon">🔒</div>
                                <h4>Upgrade to Unlock</h4>
                                <p>Get access to detailed analytics for all categories</p>
                                <a href="<?php echo home_url('/checkout?class=' . $class_id); ?>" class="upgrade-btn">
                                    Upgrade Now
                                </a>
                            </div>
                        </div>
                    <?php endif; ?>
                    
                    <h4 class="category-title"><?php echo esc_html($category->name); ?></h4>
                    
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
                                                 data-score="<?php echo $current_user_score; ?>" 
                                                 style="height: <?php echo $current_user_score == 0 ? '2px' : max(($current_user_score / 100) * 100, 2) . '%'; ?>; animation-delay: <?php echo ($index * 0.2) + 0.5; ?>s;">
                                                <div class="bar-value"><?php echo $current_user_score; ?>%</div>
                                            </div>
                                            <div class="bar average-bar" 
                                                 data-score="<?php echo $average_score; ?>" 
                                                 style="height: <?php echo $average_score == 0 ? '2px' : max(($average_score / 100) * 100, 2) . '%'; ?>; animation-delay: <?php echo ($index * 0.2) + 0.7; ?>s;">
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
                            <span class="stat-value user-color"><?php echo $current_user_score . '%'; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Overall Accuracy:</span>
                            <span class="stat-value average-color"><?php echo $average_score . '%'; ?></span>
                        </div>
                        <div class="stat-item">
                            <span class="stat-label">Difference:</span>
                            <span class="stat-value <?php echo $current_user_score >= $average_score ? 'positive' : 'negative'; ?>">
                                <?php 
                                echo $current_user_score >= $average_score ? '+' : '';
                                echo $current_user_score - $average_score . '%';
                                ?>
                            </span>
                        </div>
                        <?php if ($is_locked): ?>
                        <div class="stat-item locked-indicator">
                            <span class="stat-label" style="color: #f59e0b; font-weight: 600;">🔒 Sample Data</span>
                            <span class="stat-value" style="color: #f59e0b;">Upgrade to view real data</span>
                        </div>
                        <?php endif; ?>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
        
    </div>
    
    <style>
        .analytics-container {
            padding: 0;
            border-radius: 15px;
            margin: 20px 0;
        }
        
        .categories-grid {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 30px;
            margin-bottom: 30px;
        }
        
        .category-chart-wrapper {
            background: white;
            border-radius: 15px;
            padding: 20px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #f1f5f9;
            opacity: 0;
            transform: translateY(20px);
            animation: slideInUp 0.8s ease forwards;
            position: relative;
        }
        
        /* Locked chart styles */
        .category-chart-wrapper.locked-chart {
            position: relative;
        }
        
        .category-chart-wrapper.locked-chart::after {
            content: "SAMPLE DATA";
            position: absolute;
            top: 10px;
            right: 10px;
            background: rgba(245, 158, 11, 0.1);
            color: #f59e0b;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: 600;
            border: 1px solid rgba(245, 158, 11, 0.3);
        }
        
        .locked-indicator {
            background: rgba(245, 158, 11, 0.05);
            padding: 8px;
            border-radius: 6px;
            margin-top: 8px;
        }
        
        .locked-overlay {
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: rgba(255, 255, 255, 0.05);
            border-radius: 15px;
            display: flex;
            align-items: center;
            justify-content: center;
            z-index: 10;
            backdrop-filter: blur(5px);
        }
        
        .lock-content {
            text-align: center;
            padding: 20px;
        }
        
        .lock-content .lock-icon {
            font-size: 48px;
            margin-bottom: 15px;
            opacity: 0.7;
        }
        
        .lock-content h4 {
            margin: 0 0 10px 0;
            color: #374151;
            font-size: 18px;
            font-weight: 600;
        }
        
        .lock-content p {
            margin: 0 0 20px 0;
            color: #6b7280;
            font-size: 14px;
        }
        
        .upgrade-btn {
            display: inline-block;
            background: linear-gradient(90deg, #58c2f7, #4facfe);
            color: white;
            text-decoration: none;
            padding: 10px 20px;
            border-radius: 10px;
            font-size: 14px;
            font-weight: 600;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        
        .upgrade-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
            color: white;
            text-decoration: none;
        }
        
        .category-title {
            text-align: center;
            margin: 0 0 20px 0;
            color: #1e293b;
            font-size: 16px;
            font-weight: 600;
            padding-bottom: 10px;
            border-bottom: 2px solid #f1f5f9;
        }
        
        .single-chart-container {
            overflow-x: auto;
        }
        
        .chart-wrapper {
            display: flex;
            min-width: 200px;
            height: 150px;
            position: relative;
            margin-bottom: 30px;
        }
        
        .y-axis {
            display: flex;
            flex-direction: column;
            justify-content: space-between;
            width: 40px;
            height: 160px;
            margin-right: 15px;
            padding-top: 10px;
        }
        
        .y-label {
            font-size: 11px;
            color: #64748b;
            text-align: right;
            line-height: 1;
            font-weight: 500;
        }
        
        .chart-content {
            display: flex;
            align-items: flex-end;
            height: 160px;
            flex: 1;
            justify-content: center;
            padding: 10px 0 0 0;
            border-left: 2px solid #e2e8f0;
            border-bottom: 2px solid #e2e8f0;
            position: relative;
        }
        
        /* Grid lines */
        .chart-content::before {
            content: '';
            position: absolute;
            left: 0;
            top: 10px;
            right: 0;
            height: calc(100% - 10px);
            background-image: 
                linear-gradient(to bottom, transparent 0%, transparent calc(25% - 1px), #f1f5f9 25%, transparent calc(25% + 1px)),
                linear-gradient(to bottom, transparent 0%, transparent calc(50% - 1px), #f1f5f9 50%, transparent calc(50% + 1px)),
                linear-gradient(to bottom, transparent 0%, transparent calc(75% - 1px), #f1f5f9 75%, transparent calc(75% + 1px));
        }
        
        .category-group {
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
            justify-content: flex-end;
            position: relative;
        }
        
        @keyframes slideInUp {
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .category-bars {
            display: flex;
            flex-direction: column;
            align-items: center;
            height: 100%;
            justify-content: flex-end;
        }
        
        .bar-pair {
            display: flex;
            gap: 12px;
            align-items: flex-end;
            height: 100%;
        }
        
        .bar {
            width: 30px;
            background: #e5e7eb;
            border-radius: 5px 5px 0 0;
            position: relative;
            cursor: pointer;
            transition: all 0.3s ease;
            height: 2px;
        }
        
        .bar.user-bar {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
        
        .bar.average-bar {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }
        
        .bar.animate {
            animation: growBar 1.2s ease forwards;
        }
        
        .bar[data-height="0"] {
            background: #e5e7eb !important;
            height: 2px !important;
            border-radius: 0;
        }
        
        @keyframes growBar {
            from {
                transform: scaleY(0);
                transform-origin: bottom;
            }
            to {
                transform: scaleY(1);
                transform-origin: bottom;
            }
        }
        
        .bar:hover {
            transform: scaleY(1) scale(1.05);
            filter: brightness(1.1);
        }
        
        .bar-value {
            position: absolute;
            top: -25px;
            left: 50%;
            transform: translateX(-50%);
            background: rgba(0,0,0,0.8);
            color: white;
            padding: 4px 8px;
            border-radius: 4px;
            font-size: 10px;
            font-weight: bold;
            opacity: 0;
            transition: opacity 0.3s ease;
            pointer-events: none;
            white-space: nowrap;
        }
        
        .bar:hover .bar-value {
            opacity: 1;
        }
        
        .bar-labels {
            display: flex;
            gap: 12px;
            margin-top: 8px;
            position: absolute;
            bottom: -25px;
            left: 50%;
            transform: translateX(-50%);
        }
        
        .label {
            font-size: 11px;
            font-weight: 600;
            text-align: center;
            width: 30px;
        }
        
        .user-label {
            color: #ef4444;
        }
        
        .avg-label {
            color: #3b82f6;
        }
        
        .category-stats {
            display: flex;
            flex-direction: column;
            gap: 8px;
            padding-top: 15px;
            border-top: 1px solid #f1f5f9;
        }
        
        .stat-item {
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .stat-label {
            font-size: 12px;
            color: #64748b;
            font-weight: 500;
        }
        
        .stat-value {
            font-size: 13px;
            font-weight: 700;
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
        
        .legend {
            display: flex;
            justify-content: center;
            gap: 30px;
            margin-top: 20px;
        }
        
        .legend-item {
            display: flex;
            align-items: center;
            gap: 8px;
            font-size: 14px;
            font-weight: 500;
        }
        
        .legend-color {
            width: 16px;
            height: 16px;
            border-radius: 3px;
        }
        
        .user-color:not(.stat-value) {
            background: linear-gradient(135deg, #ef4444, #dc2626);
        }
        
        .average-color:not(.stat-value) {
            background: linear-gradient(135deg, #3b82f6, #2563eb);
        }
        
        @media (max-width: 768px) {
            .categories-grid {
                grid-template-columns: 1fr;
                gap: 20px;
            }
            
            .category-chart-wrapper {
                padding: 15px;
            }
            
            .chart-wrapper {
                min-width: 180px;
            }
            
            .chart-content {
                height: 140px;
            }
            
            .y-axis {
                height: 140px;
                width: 35px;
            }
            
            .y-label {
                font-size: 10px;
            }
            
            .bar {
                width: 25px;
            }
            
            .label {
                width: 25px;
                font-size: 10px;
            }
            
            .bar-pair {
                gap: 10px;
            }
            
            .bar-labels {
                gap: 10px;
            }
            
            .category-title {
                font-size: 14px;
            }
            
            .legend {
                gap: 20px;
            }
            
            .legend-item {
                font-size: 13px;
            }
            
            .lock-content .lock-icon {
                font-size: 36px;
            }
            
            .lock-content h4 {
                font-size: 16px;
            }
            
            .upgrade-btn {
                padding: 8px 16px;
                font-size: 12px;
            }
        }
        
        @media (max-width: 480px) {
            .categories-grid {
                grid-template-columns: 1fr;
            }
            
            .category-stats {
                gap: 6px;
            }
            
            .stat-item {
                font-size: 11px;
            }
        }
    </style>
    
    <script>
        document.addEventListener('DOMContentLoaded', function() {
            // Set bar heights based on data attributes with proper alignment to 0%
            const bars = document.querySelectorAll('.bar');
            bars.forEach(bar => {
                const height = parseFloat(bar.getAttribute('data-height')) || 0;
                
                if (height === 0) {
                    // For 0% values, show a thin line at the bottom
                    bar.style.height = '2px';
                    bar.style.backgroundColor = '#e5e7eb';
                    bar.style.background = '#e5e7eb';
                    bar.style.transform = 'scaleY(1)'; // Override animation for 0% bars
                } else {
                    // Calculate height as percentage of available chart space
                    const availableHeight = 100;
                    const heightPixels = (height / 100) * availableHeight;
                    bar.style.height = Math.max(heightPixels, 2) + '%';
                }
            });
            
            // Trigger animation after height is set
            setTimeout(() => {
                bars.forEach(bar => {
                    if (parseFloat(bar.getAttribute('data-height')) > 0) {
                        bar.classList.add('animate');
                    }
                });
            }, 100);
            
            // Add hover effects for better interaction
            bars.forEach(bar => {
                bar.addEventListener('mouseenter', function() {
                    const value = this.querySelector('.bar-value');
                    if (value) {
                        value.style.opacity = '1';
                    }
                });
                
                bar.addEventListener('mouseleave', function() {
                    const value = this.querySelector('.bar-value');
                    if (value) {
                        value.style.opacity = '0';
                    }
                });
            });
        });
    </script>
    
    <?php
    return ob_get_clean();
}

/**
 * Helper function to calculate score for a specific category and user from activity results array
 */
function calculate_category_score_from_db($activity_results, $user_id, $category_id, $mode = 'overall') {
    
    $user_stats = array_reduce($activity_results, function($acc, $result) use ($user_id, $category_id) {
        // Convert to int for proper comparison
        if (intval($result['user_id']) == intval($user_id) && intval($result['question_category']) == intval($category_id)) {
            $acc['q_sessions']++;
            $acc['total_algo_look'] += questionstatlooksfam($result['question_id'], $user_id);
            if ($result['is_correct'] == '1' || $result['is_correct'] == 1) $acc['correct_answers']++;
            $acc['sessions'][$result['session_id']] = true; // unique sessions
        }
        return $acc;
    }, ['q_sessions' => 0, 'total_algo_look' => 0, 'correct_answers' => 0, 'sessions' => []]);
    
   // Default "looksfam"
    if ($mode === 'looksfam') {
        return ($user_stats['total_algo_look'] > 0 && $user_stats['correct_answers'] > 0) 
            ? round(($user_stats['total_algo_look'] / ($user_stats['q_sessions'] * 100)) * 100, 2) 
            : 0;
    }
    
    // "overall" mode → % correct / unique sessions
    if ($mode === 'overall') {
        $unique_sessions = count($user_stats['sessions']);
        return ($unique_sessions > 0) 
            ? round(($user_stats['correct_answers'] / $user_stats['q_sessions']) * 100, 2) 
            : 0;
    }
    
    return 0;
}

function display_class_ran_page($class_id, $user_id) {
    ob_start();
    
    // Check if user has free trial for this class
    $has_free_trial = get_user_meta($user_id, 'free_trial_' . $class_id, true);
    
    
    $exam_topic = get_term_by('id', get_post_meta($class_id, 'exam_topic', true), 'question_category');
    $exam_subject = get_term_by('id', get_post_meta($class_id, 'exam_subject', true), 'question_category');
     
    $category_name = $exam_topic ? 'Topic' : 'Subject';
    $parent_term = $exam_topic ? $exam_topic : $exam_subject;
    //$current_user_looksfam = topscoredisplay($class_id, $user_id);
    $current_user_overall = topscoredisplay($class_id, $user_id, 'overall'); 
    
    ?>
    <div style="padding:10px;height:100%">
        <div class="button-container">
            <input type="button" value="←" class="go-back-button" onclick="window.location.href='<?php echo home_url('/profile'); ?>'" style="border-radius: 10px;">
            
            <?php echo dropdown_menu(); ?>
        </div>
        <div class="button-container" style="flex-direction: row;display:none">
            <a href="<?php echo home_url('/profile'); ?>" 
               
               style="border-radius: 10px; display: inline-block;  text-align: center;">
                ← Go Back
            </a>
        </div>
        
        <!--<h5 style="text-align: center;"><?php echo $category_name . ': ' . $parent_term->name; ?></h5>-->
        <h5 style="text-align: center;"><?php echo  $parent_term->name; ?></h5>
        
        <div class="dashboard-container">
            <div id="info-description" class="description-box" style="display: none;">
                This rating is an AI algorithm percentage based on your familiarity and retention on this question.
            </div>
            <!-- Looksfam Accuracy Card -->
            <div class="dashboard-card" >
                <div class="card-header">
                    <h3 class="card-title">
                        Overall Looksfam Accuracy
                        <span class="info-icon" onclick="toggleDescription()">&#8505;</span>
                        
                    </h3>
                </div>
                <div class="card-value"><?php echo round($current_user_looksfam, 2).'%'; ?></div>
            <small>Retention Rating</small>
            </div>
        
            <!-- Unique Correct Answers Card -->
            <div class="dashboard-card" >
                <div class="card-header">
                    <h3 class="card-title">Overall Question Accuracy</h3>
                </div>
                <div class="card-value"><?php echo round($current_user_overall, 2).'%'; ?></div>
            <small>(Correct Answers/ Total Answers)</small>
            </div>
        </div>
        
        <script>
        function toggleDescription() {
            const desc = document.getElementById('info-description');
            desc.style.display = desc.style.display === 'none' ? 'block' : 'none';
        }
        </script>
        
        <!-- Analytics Button -->
        <div class="button-container" style="text-align: center; margin: 20px 0;">
            <button class="custom-button" onclick="openAnalyticsModal(<?php echo $class_id; ?>, <?php echo $user_id; ?>)">
                View Analytics & Leaderboard
            </button>
        </div>
        
        <!-- Analytics Modal -->
        <div id="analyticsModal" class="modal bb" style="z-index:10000">
            
            <div class="modal-content">
            <div class="modal-header">
                <h3>Analytics & Leaderboard</h3>
                <span class="close" onclick="closeAnalyticsModal()">&times;</span>
            </div>   
                <div class="modal-body">
                    <div id="analytics-content">
                        <!-- Content will be loaded here via AJAX -->
                        <div class="loading-spinner">
                            <div class="spinner"></div>
                            <p>Loading analytics...</p>
                        </div>
                    </div>
                </div>
            </div>
        </div>
        
        <h5 style="text-align: center;">Choose a Topic</h5>
        <div class="classes60">
            
            <?php
            if ($parent_term) :
                $subcategories = get_terms([
                    'taxonomy' => 'question_category',
                    'parent' => $parent_term->term_id,
                    'hide_empty' => false,
                ]);
                if (!empty($subcategories) && !is_wp_error($subcategories)) :
                ?>
                    <div class="category-grid">
                        <?php 
                        $index = 0;
                        foreach ($subcategories as $subcategory) : 
                            $is_locked = $has_free_trial && $index > 1; // Lock all except first if free trial
                            $index++;
                        ?>
                            <div class="category-button <?php echo $is_locked ? 'locked' : ''; ?>">
                                <?php if ($is_locked) : ?>
                                    <a href="<?php echo home_url('/checkout?class=' . $class_id); ?>" class="button category-link locked-button">
                                        <div class="lock-icon">🔒</div>
                                        <h6 style="text-align: center;margin-bottom:0px;"><?php echo esc_html($subcategory->name); ?></h6>
                                        <small class="upgrade-text">Upgrade to access</small>
                                    </a>
                                <?php else : ?>
                                    <a href="<?php echo home_url("activity?class_id={$class_id}&cat={$subcategory->term_id}&exam_id=8080"); ?>" class="button category-link">
                                        <h6 style="text-align: center;margin-bottom:0px;"><?php echo esc_html($subcategory->name); ?></h6>
                                    </a>
                                <?php endif; ?>
                            </div>
                        <?php endforeach; ?>
                    </div>
                <?php else : ?>
                    <p>No subcategories found for <?php echo $parent_term->name; ?>.</p>
                <?php endif; ?>
            <?php else : ?>
                <p>No categories found.</p>
            <?php endif; ?>
        </div>
    </div>
    
    <style>
        .category-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        .category-button {
            height: 200px;
        }
        
        /* Locked button styles */
        .category-button.locked {
            opacity: 0.6;
        }
        
        .locked-button {
            background-color: #f0f0f0 !important;
            color: #999 !important;
            position: relative;
        }
        
        .locked-button:hover {
            background-color: #f0f0f0 !important;
            color: #999 !important;
        }
        
        .lock-icon {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .upgrade-text {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }
        
        /* Analytics Button */
        .analytics-button {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: white;
            border: none;
            padding: 12px 24px;
            border-radius: 25px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.2);
        }
        
        .analytics-button:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(0, 0, 0, 0.3);
        }
        
        /* Loading Spinner */
        .loading-spinner {
            text-align: center;
            padding: 40px 20px;
        }
        
        .spinner {
            border: 4px solid #f3f3f3;
            border-top: 4px solid #667eea;
            border-radius: 50%;
            width: 50px;
            height: 50px;
            animation: spin 1s linear infinite;
            margin: 0 auto 20px;
        }
        
        @keyframes spin {
            0% { transform: rotate(0deg); }
            100% { transform: rotate(360deg); }
        }
        
        /* Modal Styles */
        .modal {
            display: none;
            position: fixed;
            z-index: 1000;
            left: 0;
            top: 0;
            width: 100%;
            height: 100%;
            background-color: rgba(0, 0, 0, 0.5);
            backdrop-filter: blur(10px);
                padding-top: 30px;
        }
        
        .modal-content {
            background-color: #fefefe00;
            margin: auto;
            padding: 0;
            border: none;
            border-radius: 15px;
            width: 90%;
            max-width: 800px;
            max-height: 85vh;
            overflow-y: auto;
            box-shadow: 0 10px 30px rgba(0, 0, 0, 0.3);
            animation: modalSlideIn 0.3s ease-out;
        }
        
        @keyframes modalSlideIn {
            from {
                opacity: 0;
                transform: translateY(-50px);
            }
            to {
                opacity: 1;
                transform: translateY(0);
            }
        }
        
        .modal-header {
            
            background: linear-gradient(90deg, #58c2f7, #4facfe);
            color: white;
            padding: 20px 25px;
            border-radius: 15px 15px 0 0;
            display: flex;
            justify-content: space-between;
            align-items: center;
        }
        
        .modal-header h3 {
            margin: 0;
            font-size: 20px;
            font-weight: 600;
        }
        
        .close {
            color: white;
            font-size: 28px;
            font-weight: bold;
            cursor: pointer;
            line-height: 1;
            transition: opacity 0.3s ease;
        }
        
        .close:hover {
            opacity: 0.7;
        }
        
        .modal-body {
            padding: 25px;
        }
        
        .analytics-section {
            display: flex;
            flex-direction: column;
            gap: 25px;
        }
        
        .stats-section {
            display: flex;
            justify-content: center;
        }
        
        .stat-card {
            
            background: linear-gradient(90deg, #58c2f7, #4facfe);
            color: white;
            padding: 20px;
            border-radius: 15px;
            text-align: center;
            width:100%;
            box-shadow: 0 4px 15px rgba(0, 0, 0, 0.1);
        }
        
        .stat-number {
            font-size: 2.5em;
            font-weight: bold;
            margin-bottom: 5px;
        }
        
        .stat-label {
            font-size: 14px;
            opacity: 0.9;
            text-transform: uppercase;
            letter-spacing: 1px;
        }
        
        .leaderboard-section {
            background: #f8f9fa54;
            border-radius: 15px;
            padding: 20px;
            border: 1px solid #e9ecef;
        }
        
        .analytics-graph-section {
            background: #f8f9fa54;
            border-radius: 15px;
            padding: 20px;
            border: 1px solid #e9ecef;
        }
        
        .analytics-graph-section h4 {
            margin: 0 0 15px 0;
            color: #495057;
            font-size: 18px;
        }
        
        @media (max-width: 768px) {
            .modal-content {
                margin: 10% auto;
                width: 95%;
                max-height: 80vh;
            }
            
            .modal-header {
                padding: 15px 20px;
            }
            
            .modal-header h3 {
                font-size: 18px;
            }
            
            .modal-body {
                padding: 20px;
            }
            
            .analytics-button {
                padding: 10px 20px;
                font-size: 14px;
            }
            
            .stat-number {
                font-size: 2em;
            }
        }
        
        @media (max-width: 544px) {
            .category-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .category-link {
                font-size:unset!important;
            }
            .category-button {
                height: 110px!important;
            }
            .lock-icon {
                font-size: 18px;
            }
            .upgrade-text {
                font-size: 10px;
            }
        }
        
        .category-link {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            width: 100%;
            border-radius: 15px;
            text-decoration: none;
            font-weight: bold;
            text-align: center;
            padding: 10px;
            box-sizing: border-box;
            font-weight:700;
            flex-direction: column;
        }
        .category-link h4 {
            margin-bottom: 0px;
           
        }
    </style>
    
    <script>
        let analyticsLoaded = false;
        
        function openAnalyticsModal(classId, userId) {
            document.getElementById('analyticsModal').style.display = 'block';
            document.body.style.overflow = 'hidden';
            
            // Load analytics content via AJAX only if not already loaded
            if (!analyticsLoaded) {
                loadAnalyticsContent(classId, userId);
            }
        }
        
        function loadAnalyticsContent(classId, userId) {
            const contentDiv = document.getElementById('analytics-content');
            
            // Show loading spinner
            contentDiv.innerHTML = `
                <div class="loading-spinner">
                    <div class="spinner"></div>
                    <p>Loading analytics...</p>
                </div>
            `;
            
            // AJAX request to load analytics
            const xhr = new XMLHttpRequest();
            xhr.open('POST', '<?php echo admin_url('admin-ajax.php'); ?>', true);
            xhr.setRequestHeader('Content-Type', 'application/x-www-form-urlencoded');
            
            xhr.onreadystatechange = function() {
                if (xhr.readyState === 4) {
                    if (xhr.status === 200) {
                        try {
                            const response = JSON.parse(xhr.responseText);
                            if (response.success) {
                                contentDiv.innerHTML = response.data;
                                analyticsLoaded = true;
                            } else {
                                contentDiv.innerHTML = '<p style="text-align: center; color: #dc3545;">Error loading analytics: ' + (response.data || 'Unknown error') + '</p>';
                            }
                        } catch (e) {
                            contentDiv.innerHTML = '<p style="text-align: center; color: #dc3545;">Error parsing response. Please try again.</p>';
                        }
                    } else {
                        contentDiv.innerHTML = '<p style="text-align: center; color: #dc3545;">Network error. Please check your connection and try again.</p>';
                    }
                }
            };
            
            const params = `action=load_class_analytics&class_id=${classId}&user_id=${userId}&nonce=${encodeURIComponent('<?php echo wp_create_nonce('load_analytics_nonce'); ?>')}`;
            xhr.send(params);
        }
        
        function closeAnalyticsModal() {
            document.getElementById('analyticsModal').style.display = 'none';
            document.body.style.overflow = 'auto';
        }
        
        // Close modal when clicking outside of it
        window.onclick = function(event) {
            var modal = document.getElementById('analyticsModal');
            if (event.target == modal) {
                closeAnalyticsModal();
            }
        }
        
        // Close modal with Escape key
        document.addEventListener('keydown', function(event) {
            if (event.key === 'Escape') {
                closeAnalyticsModal();
            }
        });
    </script>
    <?php
    return ob_get_clean();
}

// AJAX handler function - Add this to your functions.php or appropriate file
function handle_load_class_analytics() {
    // Verify nonce for security
    if (!wp_verify_nonce($_POST['nonce'], 'load_analytics_nonce')) {
        wp_send_json_error('Security check failed');
        return;
    }
    
    $class_id = intval($_POST['class_id']);
    $user_id = intval($_POST['user_id']);
    
    if (!$class_id || !$user_id) {
        wp_send_json_error('Invalid parameters');
        return;
    }
    
    // Generate the analytics content
    ob_start();
    ?>
    <div class="analytics-section">
        <div class="stats-section">
            <div class="stat-card">
                <div class="stat-number"><?php echo class_enrollees($class_id); ?></div>
                <div class="stat-label">Total Users</div>
            </div>
        </div>
        
        <div class="leaderboard-section">
            <?php echo topscores_exam($class_id, $user_id); ?>
        </div>
        
        
        
        <div class="analytics-graph-section">
            <?php echo display_analytics_graph($class_id, $user_id); ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    
    wp_send_json_success($content);
}

// Hook the AJAX handler
add_action('wp_ajax_load_class_analytics', 'handle_load_class_analytics');
add_action('wp_ajax_nopriv_load_class_analytics', 'handle_load_class_analytics');

function display_class_page($class_id, $user_id) {
    ob_start();
    ?>
    
        </style>
    <div>
        <div class="button-container">
            <input type="button" value="←" class="go-back-button" onclick="window.location.href='<?php echo home_url('/profile'); ?>'" style="border-radius: 10px;">
        </div>
        <h3 style="text-align:center;">Top Looksfammers</h3>
        <div><?php echo topscores_exam($class_id, $user_id); ?></div>
        <div style="text-align:center;font-weight: bold;">Total Users : <?php echo class_enrollees($class_id); ?></div>
        
        <div class="classes60">
            <h3>Exams</h3>
            <div style="width:100%;">
                <input type="text" id="exam_search" placeholder="Search for exams here" style="border-radius: 10px;width:100%;">
            </div>
            <div id="available_exams"></div>
        </div>
    </div>
    <?php echo get_exam_search_script($class_id); ?>
    <?php
    return ob_get_clean();
}



function get_exam_search_script($class_id) {
    ob_start();
    ?>
    <script>
                if (typeof ajaxurl === "undefined") {
                    var ajaxurl = "<?php echo admin_url('admin-ajax.php'); ?>";
                }
                function updateExamsList(searchTerm) {
                    jQuery.ajax({
                        url: ajaxurl,
                        type: "POST",
                        data: {
                            action: "get_exams_by_search",
                            search_term: searchTerm,
                            class_id: <?php echo $class_id; ?>,
                        },
                        success: function(response) {
                            document.getElementById("available_exams").innerHTML = response;
                        },
                        error: function(xhr, status, error) {
                            console.log("Error:", error);
                        }
                    });
                }
                document.getElementById("exam_search").addEventListener("input", function() {
                    var searchTerm = this.value;
                    updateExamsList(searchTerm);
                });
                updateExamsList("");
            </script>
    <?php
    return ob_get_clean();
}
function display_profile_page($user) {
    ob_start();
    ?>
    <style>
        /* Feature Slider Styles */
        .dashboard-hero {
        background: linear-gradient(135deg, #58c2f7, #4facfe);
            border-radius: 20px;
            padding: 30px 20px;
            margin-bottom: 30px;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .feature-slider {
            position: relative;
            height: 200px;
            overflow: hidden;
        }
        
        .feature-slide {
            position: absolute;
            top: 0;
            left: 0;
            width: 100%;
            height: 100%;
            display: flex;
            flex-direction: column;
            justify-content: center;
            align-items: center;
            text-align: center;
            opacity: 0;
            transform: translateX(100%);
            transition: all 0.8s ease-in-out;
        }
        
        .feature-slide.active {
            opacity: 1;
            transform: translateX(0);
        }
        
        .feature-slide.prev {
            transform: translateX(-100%);
        }
        
        .feature-icon {
            font-size: 48px;
            filter: drop-shadow(0 4px 8px rgba(0,0,0,0.3));
        }
        
        .feature-title {
            font-size: 24px;
            font-weight: 700;
            margin-bottom: 10px;
            text-shadow: 0 2px 4px rgba(0,0,0,0.3);
        }
        
        .feature-description {
            font-size: 16px;
            opacity: 0.9;
            max-width: 300px;
            line-height: 1.4;
        }
        
        .slider-dots {
            display: flex;
            justify-content: center;
            gap: 10px;
            margin-top: 20px;
        }
        
        .dot {
            width: 12px;
            height: 12px;
            border-radius: 50%;
            background: rgba(255,255,255,0.4);
            cursor: pointer;
            transition: all 0.3s ease;
        }
        
        .dot.active {
            background: white;
            transform: scale(1.2);
        }
        
        
        
        
        
        /* Quick Actions Section */
        .quick-actions {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #f1f5f9;
        }
        
        .quick-actions h3 {
            margin: 0 0 20px 0;
            color: #1e293b;
            font-size: 20px;
            font-weight: 600;
        }
        
        .action-buttons {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 15px;
        }
        
        .action-btn {
            background: linear-gradient(135deg, #58c2f7, #4facfe);
            color: white;
            border: none;
            padding: 15px 20px;
            border-radius: 12px;
            font-size: 14px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-decoration: none;
            display: flex;
            align-items: center;
            justify-content: center;
            gap: 8px;
            box-shadow: 0 4px 15px rgba(88, 194, 247, 0.3);
        }
        
        .action-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 6px 20px rgba(88, 194, 247, 0.4);
            color: white;
            text-decoration: none;
        }
        
        .action-btn.secondary {
            background: linear-gradient(135deg, #76d3b5, #76d3b5);
            box-shadow: 0 4px 15px rgba(102, 126, 234, 0.3);
        }
        
        .action-btn.secondary:hover {
            box-shadow: 0 6px 20px rgba(102, 126, 234, 0.4);
        }
        
        /* Welcome Section Enhancement */
        .welcome-section {
            background: white;
            border-radius: 15px;
            padding: 25px;
            margin-bottom: 30px;
            box-shadow: 0 4px 20px rgba(0,0,0,0.08);
            border: 1px solid #f1f5f9;
        }
        
        .welcome-section h2 {
            color: #1e293b;
            margin: 0 0 15px 0;
            font-size: 28px;
            font-weight: 700;
        }
        
        .welcome-section h3 {
            color: #475569;
            margin: 0 0 20px 0;
            font-size: 18px;
            font-weight: 500;
        }
        
        .social-link {
            color: #58c2f7;
            text-decoration: none;
            font-weight: 600;
            transition: color 0.3s ease;
        }
        
        .social-link:hover {
            color: #4facfe;
            text-decoration: underline;
        }
        
        /* Existing styles maintained */
        .category-grid {
            display: grid;
            grid-template-columns: repeat(3, 1fr);
            gap: 10px;
        }
        .category-button {
            height: 200px;
        }
        .category-link {
            display: flex;
            align-items: center;
            justify-content: center;
            height: 100%;
            width: 100%;
            border-radius: 15px;
            text-decoration: none;
            font-weight: bold;
            text-align: center;
            padding: 10px;
            box-sizing: border-box;
            font-weight:700;
            flex-direction: column;
        }
        .category-link h4 {
            margin-bottom: 0px;
        }
        
        /* Locked button styles */
        .category-button.locked {
            opacity: 0.6;
        }
        .free-trial {
            background-color: #76d3b5 !important;
        }
        
        .free-trial:hover {
            background-color: #76d3b5b8 !important;
        }
        .locked-button {
            background-color: #f0f0f0 !important;
            color: #999 !important;
            cursor: not-allowed !important;
            position: relative;
        }
        
        .locked-button:hover {
            background-color: #f0f0f0 !important;
            color: #999 !important;
        }
        
        .lock-icon {
            font-size: 24px;
            margin-bottom: 5px;
        }
        
        .upgrade-text {
            font-size: 11px;
            color: #666;
            margin-top: 5px;
        }
        .status-fixed {
            position: absolute;
            padding: 8px 12px;
            width:100%;
            z-index: 10000;
            text-align: center;
        }
        
        /* Responsive Design */
        @media (max-width: 768px) {
            .dashboard-hero {
                padding: 20px 15px;
                margin-bottom: 20px;
            }
            
            .feature-slider {
                height: 180px;
            }
            
            .feature-title {
                font-size: 20px;
            }
            
            .feature-description {
                font-size: 14px;
            }
            
            .action-buttons {
                grid-template-columns: 1fr;
            }
            
            .welcome-section h2 {
                font-size: 24px;
            }
        }
        
        @media (max-width: 544px) {
            .category-grid {
                grid-template-columns: repeat(2, 1fr);
            }
            .category-link {
                font-size:unset!important;
            }
            .category-button {
                height: 110px;
            }
            
            .feature-icon {
                font-size: 36px;
            }
            
            .feature-title {
                font-size: 18px;
            }
            
            .stat-number {
                font-size: 24px;
            }
        }
    </style>
    
    <div style="padding:10px">
        <div class="button-container">
            <?php echo dropdown_menu(); ?>
        </div>
        <!-- Welcome Section -->
        <div class="welcome-section">
            <h2>Welcome, <?php echo esc_html($user->first_name) . ' ' . esc_html($user->last_name); ?></h2>
            <h3>Practice Board Exam Questions here!</h3>
            <div class="dashboard-hero">
            <div class="feature-slider">
                <div class="feature-slide active">
                    <div class="feature-icon">📊</div>
                    <div class="feature-title">Advanced Analytics</div>
                    <div class="feature-description">Track your performance with detailed analytics, compare with peers, and identify areas for improvement</div>
                </div>
                
                <div class="feature-slide">
                    <div class="feature-icon">🎯</div>
                    <div class="feature-title">Targeted Practice</div>
                    <div class="feature-description">Know your strengths and weakness on subjects for board exams.</div>
                </div>
                
                <div class="feature-slide">
                    <div class="feature-icon">🏆</div>
                    <div class="feature-title">Leaderboards & Competition</div>
                    <div class="feature-description">Know what are your standing on national reviews!</div>
                </div>
                <div class="feature-slide">
                    <div class="feature-icon">📚</div>
                    <div class="feature-title">15+</div>
                    <div class="feature-description">Available Courses</div>
                </div>
                <div class="feature-slide">
                    <div class="feature-icon">✅</div>
                    <div class="feature-title">10,000+</div>
                    <div class="feature-description">Practice Questions</div>
                </div>
            </div>
            
            <div class="slider-dots">
                <span class="dot active" onclick="currentSlide(1)"></span>
                <span class="dot" onclick="currentSlide(2)"></span>
                <span class="dot" onclick="currentSlide(3)"></span>
                <span class="dot" onclick="currentSlide(4)"></span>
                <span class="dot" onclick="currentSlide(5)"></span>
            </div>
        </div>
            <h3>Follow us on our Facebook page <a href="https://www.facebook.com/looksfam" target="_blank" class="social-link">HERE</a>.</h3>
        </div>
        <!-- Hero Section with Feature Slider -->
        
        
        
        <!-- Quick Actions -->
        <div class="quick-actions">
            <h3>Quick Actions</h3>
            <div class="action-buttons">
                <a href="<?php echo home_url('/enroll'); ?>" class="action-btn">
                    Explore Reviewers
                </a>
                <a href="https://www.facebook.com/looksfam" target="_blank" class="action-btn secondary">
                    Follow on Facebook
                </a>
                <a href="https://m.me/looksfam" target="_blank" class="action-btn secondary">
                    Report Issue
                </a>
            </div>
            <!-- Classes Enrolled Section -->
            <div>
                <h3 style="color: #1e293b; font-size: 22px; font-weight: 600; margin-bottom: 20px;">Classes Enrolled</h3>
                <div class="category-grid">
                    <?php echo get_enrolled_classes($user->ID); ?>
                </div>
            </div>
        </div>
        
        
        
        
    </div>
    
    <script>
        let slideIndex = 1;
        showSlide(slideIndex);
        
        // Auto-advance slides every 4 seconds
        setInterval(function() {
            slideIndex++;
            if (slideIndex > 5) slideIndex = 1;
            showSlide(slideIndex);
            updateDots();
        }, 4000);
        
        function currentSlide(n) {
            slideIndex = n;
            showSlide(slideIndex);
            updateDots();
        }
        
        function showSlide(n) {
            let slides = document.querySelectorAll('.feature-slide');
            
            slides.forEach((slide, index) => {
                slide.classList.remove('active', 'prev');
                
                if (index + 1 === n) {
                    slide.classList.add('active');
                } else if (index + 1 < n) {
                    slide.classList.add('prev');
                }
            });
        }
        
        function updateDots() {
            let dots = document.querySelectorAll('.dot');
            dots.forEach((dot, index) => {
                dot.classList.remove('active');
                if (index + 1 === slideIndex) {
                    dot.classList.add('active');
                }
            });
        }
        
        // Add smooth scrolling animation on page load
        document.addEventListener('DOMContentLoaded', function() {
            const cards = document.querySelectorAll('.stat-card');
            cards.forEach((card, index) => {
                card.style.animationDelay = `${index * 0.1}s`;
                card.style.animation = 'slideInUp 0.6s ease forwards';
            });
        });
        
        // Add CSS animation keyframes
        const style = document.createElement('style');
        style.textContent = `
            @keyframes slideInUp {
                from {
                    opacity: 0;
                    transform: translateY(30px);
                }
                to {
                    opacity: 1;
                    transform: translateY(0);
                }
            }
        `;
        document.head.appendChild(style);
    </script>
    
    <?php
    return ob_get_clean();
}

function validate_class_key($user_id, $class_id, $class_keys) {
    $expired_key = null;
    $expired_duration = 0;
    $revoke_key = null;

    // First loop to check for "Used" status
    foreach ($class_keys as $key => $data) {
        if ($data['class'] == $class_id && isset($data['user']) && $data['user'] == $user_id) {
            
           

            $timestamp = $data['used_timestamp'];
            $duration = isset($data['duration']) ? $data['duration'] : 0;
            
             // Skip validation if timestamp is blank
            if (empty($data['used_timestamp'])) {
                // Check for "Used" status first
                if ($data['status'] === 'Used' ) {
                    return array(
                        'is_valid' => true, 
                        'is_expired' => false, 
                        'key' => $key, 
                        'duration' => $duration, 
                        'expiry_date' => ''
                    );
                }
            }

            // Convert decimal months to days
            $days = round($duration * 30.44); // Average days per month
            
            // Calculate expiry date in days
            $expiry_date = strtotime($timestamp . " +{$days} days");
            $formatted_expiry_date = date('Y-m-d H:i:s', $expiry_date); // Format the expiry date

            // Skip validation if timestamp is blank
            if (empty($data['used_timestamp'])) {
                return array(
                    'is_valid' => true, 
                    'is_expired' => false, 
                    'key' => $key, 
                    'duration' => $duration, 
                    'expiry_date' => $formatted_expiry_date
                );
            }

            // Check for "Used" status first
            if ($data['status'] === 'Used' && time() <= $expiry_date) {
                return array(
                    'is_valid' => true, 
                    'is_expired' => false, 
                    'key' => $key, 
                    'duration' => $duration, 
                    'expiry_date' => $formatted_expiry_date
                );
            }
        }
    }

    // If no "Used" key is found, check for "Revoke" and "Expired" statuses
    foreach ($class_keys as $key => $data) {
        if ($data['class'] == $class_id && isset($data['user']) && $data['user'] == $user_id) {

            

            $timestamp = $data['used_timestamp'];
            $duration = isset($data['duration']) ? $data['duration'] : 0;

            // Convert decimal months to days
            $days = round($duration * 30.44); // Average days per month
            
            // Calculate expiry date in days
            $expiry_date = strtotime($timestamp . " +{$days} days");
            $formatted_expiry_date = date('Y-m-d H:i:s', $expiry_date); // Format the expiry date

            // Check for "Revoke" status and store the key
            if ($data['status'] === 'Revoke') {
                $revoke_key = $key;
                return array(
                    'is_valid' => true, 
                    'is_expired' => false, 
                    'key' => $key, 
                    'duration' => $duration, 
                    'expiry_date' => $formatted_expiry_date,
                    'revoke' => true
                );
            }

            // Save the expired key for later if no "Used" key is found
            if ($data['status'] === 'Expired' || time() > $expiry_date) {
                $expired_key = $key;
                $expired_duration = $duration;
                // Update the status to "Expired" if not already
                if ($data['status'] !== 'Expired') {
                    $class_keys[$key]['status'] = 'Expired';
                    //update_term_meta(305, 'class_keys', $class_keys);
                }
            }
        }
    }

    // Return the expired key if no "Used" or "Revoke" key was found
    if ($expired_key !== null) {
        return array(
            'is_valid' => true, 
            'is_expired' => true, 
            'key' => $expired_key, 
            'duration' => $expired_duration, 
            'expiry_date' => $formatted_expiry_date
        );
    }

    // Return the revoke key if no "Used" key was found but "Revoke" was
    if ($revoke_key !== null) {
        return array(
            'is_valid' => true, 
            'revoke' => true, 
            'key' => $revoke_key, 
            'duration' => $expired_duration, 
            'expiry_date' => $formatted_expiry_date
        );
    }

    // No valid key found
    return array(
        'is_valid' => false, 
        'is_expired' => false, 
        'key' => null, 
        'duration' => 0, 
        'expiry_date' => null
    );
}



function get_enrolled_classes($user_id) {
    $classes_enrolled = get_user_meta($user_id, 'classes_enrolled', true);
    $class_keys = get_term_meta(305, 'class_keys', true) ?: array();
    
    // Check if user has no enrolled classes
    if (empty($classes_enrolled)) {
        // Redirect to enroll page
        wp_redirect(home_url('/enroll'));
        exit;
    }
    
    $valid_classes_found = false;
    ob_start();
    
    foreach ($classes_enrolled as $class_id) {
        // ✅ Only process published classes
        if (get_post_type($class_id) && get_post_status($class_id) === 'publish') {
            $class_title = get_the_title($class_id);

            // Validate the class key
            $validation_result = validate_class_key($user_id, $class_id, $class_keys);
            
            // If there's no valid key, skip this class
            if (!$validation_result['is_valid']) {
                continue;
            }
            
            // Mark that we found at least one valid class
            $valid_classes_found = true;
            
            $is_expired = isset($validation_result['is_expired']) && $validation_result['is_expired'];
            $revoke = isset($validation_result['revoke']) && $validation_result['revoke'];
            
            // Redirect handling
            if ($is_expired || $revoke) {
                $link = home_url('/checkout?class=' . $class_id);
            } else {
                $link = home_url('/profile?class_id=' . $class_id);
            }
            
            $has_free_trial = get_user_meta($user_id, 'free_trial_' . $class_id, true);
            $duration = $validation_result['expiry_date'];
            ?>
            <div class="category-button <?php echo $is_expired || $revoke ? 'locked' : ''; ?>">
                <a href="<?php echo $link; ?>" class="button category-link <?php echo $is_expired || $revoke ? 'locked-button' : ''; ?> <?php echo $has_free_trial ? 'free-trial' : ''; ?>">
                    <div class="progress-container" style="width:80%;margin:5px 0px;display:none;">
                        <div class="progress-bar" style="width: <?php echo isset($progress_percentage) ? $progress_percentage : 0; ?>%;"></div>
                    </div>
                    <?php if ($has_free_trial): ?>
                        <small class="upgrade-text">FREE TRIAL</small>
                    <?php endif; ?>
                    <h6 style="text-align: center;margin-bottom:0px;"><?php echo $class_title; ?></h6>
                    <div class="status-fixed">
                        <?php echo $is_expired || $revoke ? '<div class="lock-icon">🔒</div>' : ''; ?>
                        <?php if ($is_expired): ?>
                            <small class="upgrade-text">Expired - Click to Renew</small>
                        <?php elseif ($revoke): ?>
                            <small class="upgrade-text">Payment Unverified - Click to Process again</small>
                        <?php endif; ?>
                    </div>
                </a>
            </div>
            <?php
        }
    }
    
    $output = ob_get_clean();
    
    // If no valid published classes were found, redirect to enroll page
    if (!$valid_classes_found || empty($output)) {
        wp_redirect(home_url('/enroll'));
        exit;
    }
    
    return $output;
}

add_shortcode('user_profile', 'user_profile_shortcode');


// Function to get classes based on search term
function get_exams_by_search() {
    ob_start();
    $search_term = sanitize_text_field($_POST['search_term']);
    $class_id = sanitize_text_field($_POST['class_id']);
    $current_user = wp_get_current_user();

    if (!empty($class_id)) {
        echo getClassCategoryStatus($class_id);
        $class_title = get_the_title($class_id);
        $associated_exams = get_post_meta($class_id, 'associated_exams', true);
        $pre_exams = get_post_meta($class_id, 'associated_pre_exam', true);
        $post_exams = get_post_meta($class_id, 'associated_post_exam', true);

        if (!empty($associated_exams)) {
            /*foreach ($pre_exams as $exam_id) {
                $exam_title = get_the_title($exam_id);

                if (stripos($exam_title, $search_term) !== false || empty($search_term)) {
                    $percentageScore = calculatePercentageScore($exam_id, $current_user->ID);

                    ?>
                    <div class="button-container">
                        <button onclick="window.location.href='<?php echo home_url('/exam?id=' . $exam_id . '&class_id=' . $class_id); ?>';" style="border-radius: 10px;width:100%;">
                            <?php displayStarRating($percentageScore); ?>
                            <div style="margin-top:5px;"><?php echo $percentageScore; ?>%</div>
                            EXAM <br>
                            <?php echo esc_html($exam_title); ?>
                        </button>
                    </div>
                    <?php
                }
            }*/

            foreach ($associated_exams as $exam_id) {
                $exam_title = get_the_title($exam_id);

                if (stripos($exam_title, $search_term) !== false || empty($search_term)) {
                    $percentageScore = calculatePercentageScore($exam_id, $current_user->ID);

                    ?>
                    <div class="button-container">
                        <button onclick="window.location.href='<?php echo home_url('/exercises?id=' . $exam_id . '&class_id=' . $class_id); ?>';" style="border-radius: 10px;width:100%;">
                            <?php displayStarRating($percentageScore); ?>
                            <div style="margin-top:5px;"><?php echo $percentageScore; ?>%</div>
                            EXERCISE <br>
                            <?php echo esc_html($exam_title); ?>
                        </button>
                    </div>
                    <?php
                }
            }

           /* foreach ($post_exams as $exam_id) {
                $exam_title = get_the_title($exam_id);

                if (stripos($exam_title, $search_term) !== false || empty($search_term)) {
                    $percentageScore = calculatePercentageScore($exam_id, $current_user->ID);

                    ?>
                    <div class="button-container">
                        <button onclick="window.location.href='<?php echo home_url('/exam?id=' . $exam_id . '&class_id=' . $class_id); ?>';" style="border-radius: 10px;width:100%;">
                            <?php displayStarRating($percentageScore); ?>
                            <div style="margin-top:5px;"><?php echo $percentageScore; ?>%</div>
                            EXAM <br>
                            <?php echo esc_html($exam_title); ?>
                        </button>
                    </div>
                    <?php
                }
            }*/
        } else {
            echo 'No associated exams for this class.';
        }
    } else {
        echo 'Not enrolled in any classes.';
    }

    $output = ob_get_clean();
    echo $output;
    wp_die();
}

add_action('wp_ajax_get_exams_by_search', 'get_exams_by_search');
add_action('wp_ajax_nopriv_get_exams_by_search', 'get_exams_by_search');


function calculateClassProgress($class_id) {
   
$associated_class = get_post_meta($class_id, 'associated_exams', true);
$current_user = wp_get_current_user();
$user_id = $current_user->ID;

$class_progress = 0;
$total_class_progress = 0;
$prevExamHasResult = false;

if (empty($associated_class)) {
    // Get the exam topic and subject from the custom fields
    $exam_topic = get_term_by('id', get_post_meta($class_id, 'exam_topic', true), 'question_category');
    $exam_subject = get_term_by('id', get_post_meta($class_id, 'exam_subject', true), 'question_category');
    
    $category_name = $exam_topic ? 'Topic' : 'Subject';
    $parent_term = $exam_topic ? $exam_topic : $exam_subject;

    $relevant_categories = array();
    
    // Gather relevant categories (parent and subcategories)
    if ($parent_term) {
        $relevant_categories[] = $parent_term->term_id;

        $subcategories = get_terms([
            'taxonomy' => 'question_category',
            'parent' => $parent_term->term_id,
            'hide_empty' => false,
        ]);

        foreach ($subcategories as $subcat) {
            $relevant_categories[] = $subcat->term_id;
        }
    }

    // Get all questions from the 'question_category' taxonomy for the relevant categories
    $args = array(
        'post_type' => 'question',
        'tax_query' => array(
            array(
                'taxonomy' => 'question_category',
                'field'    => 'term_id',
                'terms'    => $relevant_categories,
                'include_children' => true,
            ),
        ),
        'posts_per_page' => -1, // Get all questions
    );
    
    $questions_in_category = get_posts($args);
    $total_questions = count($questions_in_category); // Count the total number of questions
    
    // Initialize counters for user progress
    $correct_answers = 0;
    $sessions = 0;
    $q_sessions = 0;
    $displayed_session = array();
    
    // Retrieve user's question data
    $user_questions = get_user_meta($user_id, 'questions', true);

    if (!empty($user_questions) && is_array($user_questions)) {
        foreach ($user_questions as $question) {
            $question_category = isset($question['question_category']) ? intval($question['question_category']) : 0;
           
            
            if (in_array($question_category, $relevant_categories)) {
                 $q_sessions++ ;
                // Check for correct answers
                if ($question['is_correct']) {
                    $correct_answers++;
                }

                // Count unique sessions
                if (!in_array($question['session_id'], $displayed_session)) {
                    $displayed_session[] = $question['session_id'];
                    $sessions++;
                }
            }
        }
    }

    // Calculate the class progress bar as a percentage of correct answers over the total number of questions
    //$classbar = ($total_questions > 0) ? round(($correct_answers / $total_questions) * 100, 2) : 0;
   $classbar = ($total_questions > 0) ? round(($q_sessions / $total_questions) * 100, 2) : 0;

        
    }else{

        
    // Loop through associated class exams
    foreach ($associated_class as $exam_id) {
        $percentageScore = calculatePercentageScore($exam_id, get_current_user_id());

        // Check if the percentage score is 60% or more
        $prevExamHasResult = $percentageScore >= 60;

        // Increment progress counters
        $class_progress += $prevExamHasResult ? 1 : 0;
        $total_class_progress++;
    }


    // Calculate class progress percentage
    $classbar = ($total_class_progress > 0) ? ($class_progress / $total_class_progress) * 100 : 0;

    }

    


    return $classbar;
}

function user_home_sc() {
    $current_user = wp_get_current_user();

    // Check if the user is logged in and is not an administrator
    if ($current_user && $current_user->ID && !in_array('administrator', $current_user->roles)) {
        $login = home_url('/profile');
        wp_redirect($login);
        exit; // Always call exit after wp_redirect
    }
}
add_shortcode('user_home', 'user_home_sc'); // turn off to edit login page


// Add this to your theme's functions.php file
function looksfam_register_shortcode($atts) {
    // Don't show the form if user is already logged in
    if (is_user_logged_in()) {
        return '<div class="looksfam-register-message">You are already registered and logged in.</div>';
    }

    // Handle form submission
    $message = '';
    if (isset($_POST['looksfam_register_submit']) && $_POST['looksfam_register_submit']) {
        $message = looksfam_handle_registration();
    }

    ob_start();
    echo looksfam_register_styles();
    ?>
    

    <div class="looksfam-register-form">
        <?php if ($message): ?>
            <?php echo $message; ?>
        <?php endif; ?>

        <div class="looksfam-logo">
            <img src="https://www.looksfam.co/wp-content/uploads/2023/11/LOOk-2-1.png" alt="Looksfam Logo">
        </div>

        <h2 class="looksfam-form-title">Create Your Account</h2>
        <p class="looksfam-form-subtitle">Join lookfams and start revolutionizing your board exam!</p>

        <form method="post" id="looksfam-register-form" action="">
            <?php wp_nonce_field('looksfam_register_nonce', 'looksfam_nonce'); ?>

            <div class="looksfam-form-row">
                <div class="looksfam-form-group">
                    <label for="first_name" class="looksfam-form-label">First Name</label>
                    <input type="text" id="first_name" name="first_name" class="looksfam-form-input" required 
                           value="<?php echo isset($_POST['first_name']) ? esc_attr($_POST['first_name']) : ''; ?>">
                </div>
                <div class="looksfam-form-group">
                    <label for="last_name" class="looksfam-form-label">Last Name</label>
                    <input type="text" id="last_name" name="last_name" class="looksfam-form-input" required
                           value="<?php echo isset($_POST['last_name']) ? esc_attr($_POST['last_name']) : ''; ?>">
                </div>
            </div>

            <div class="looksfam-form-group">
                <label for="username" class="looksfam-form-label">Username</label>
                <input type="text" id="username" name="username" class="looksfam-form-input" required
                       value="<?php echo isset($_POST['username']) ? esc_attr($_POST['username']) : ''; ?>">
            </div>

            <div class="looksfam-form-group">
                <label for="email" class="looksfam-form-label">Email Address</label>
                <input type="email" id="email" name="email" class="looksfam-form-input" required
                       value="<?php echo isset($_POST['email']) ? esc_attr($_POST['email']) : ''; ?>">
            </div>

            <div class="looksfam-form-group">
                <label for="password" class="looksfam-form-label">Password</label>
                <input type="password" id="password" name="password" class="looksfam-form-input" required minlength="8">
                <div class="looksfam-password-strength">
                    Password must be at least 8 characters long
                </div>
            </div>

            <div class="looksfam-form-group">
                <label for="confirm_password" class="looksfam-form-label">Confirm Password</label>
                <input type="password" id="confirm_password" name="confirm_password" class="looksfam-form-input" required>
            </div>

            <div class="looksfam-checkbox-group">
                <input type="checkbox" id="terms" name="terms" class="looksfam-checkbox" required
                       <?php echo isset($_POST['terms']) ? 'checked' : ''; ?>>
                <label for="terms" class="looksfam-checkbox-label">
                    I agree to the <a href="https://www.looksfam.co/privacy-policy/" target="_blank">Terms of Service and Privacy Policy</a>
                </label>
            </div>

            <input type="hidden" name="looksfam_register_submit" value="1">
            <button type="submit" class="looksfam-submit-btn">
                Create Account
            </button>
        </form>

        <div class="looksfam-login-link">
            Already have an account? <a href="<?php echo home_url('/login'); ?>">Sign in here</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('looksfam-register-form');
            const password = document.getElementById('password');
            const confirmPassword = document.getElementById('confirm_password');
            const submitBtn = document.querySelector('.looksfam-submit-btn');

            // Password confirmation validation
            function validatePasswords() {
                if (password.value && confirmPassword.value) {
                    if (password.value !== confirmPassword.value) {
                        confirmPassword.setCustomValidity('Passwords do not match');
                    } else {
                        confirmPassword.setCustomValidity('');
                    }
                }
            }

            password.addEventListener('input', validatePasswords);
            confirmPassword.addEventListener('input', validatePasswords);

            // Form submission handling
            form.addEventListener('submit', function(e) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = 'Creating Account...';
                
                // Re-enable button after 5 seconds to prevent permanent disable
                setTimeout(function() {
                    if (submitBtn.disabled) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = 'Create Account';
                    }
                }, 5000);
            });

            // Smooth focus animations
            const inputs = document.querySelectorAll('.looksfam-form-input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });
        });
    </script>

    <?php
    return ob_get_clean();
}

// Handle the registration process
function looksfam_handle_registration() {
    // Verify nonce
    if (!isset($_POST['looksfam_nonce']) || !wp_verify_nonce($_POST['looksfam_nonce'], 'looksfam_register_nonce')) {
        return '<div class="looksfam-message error">Security check failed. Please try again.</div>';
    }

    // Sanitize input data
    $first_name = sanitize_text_field($_POST['first_name']);
    $last_name = sanitize_text_field($_POST['last_name']);
    $username = sanitize_user($_POST['username']);
    $email = sanitize_email($_POST['email']);
    $password = $_POST['password'];
    $confirm_password = $_POST['confirm_password'];

    // Validation
    $errors = array();

    if (empty($first_name)) $errors[] = 'First name is required.';
    if (empty($last_name)) $errors[] = 'Last name is required.';
    if (empty($username)) $errors[] = 'Username is required.';
    if (empty($email) || !is_email($email)) $errors[] = 'Valid email is required.';
    if (empty($password)) $errors[] = 'Password is required.';
    if (strlen($password) < 8) $errors[] = 'Password must be at least 8 characters long.';
    if ($password !== $confirm_password) $errors[] = 'Passwords do not match.';
    if (!isset($_POST['terms'])) $errors[] = 'You must agree to the Terms of Service.';

    // Check if username exists
    if (username_exists($username)) {
        $errors[] = 'Username already exists. Please choose another.';
    }

    // Check if email exists
    if (email_exists($email)) {
        $errors[] = 'Email already exists. Please use another email address.';
    }

    // If there are errors, return them
    if (!empty($errors)) {
        return '<div class="looksfam-message error">' . implode('<br>', $errors) . '</div>';
    }

    // Create the user
    $user_data = array(
        'user_login' => $username,
        'user_email' => $email,
        'user_pass' => $password,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'display_name' => $first_name . ' ' . $last_name,
    );

    $user_id = wp_insert_user($user_data);

    if (is_wp_error($user_id)) {
        return '<div class="looksfam-message error">Registration failed: ' . $user_id->get_error_message() . '</div>';
    } else {
        // Auto-login the user
        wp_set_current_user($user_id);
        wp_set_auth_cookie($user_id);
        
        // Redirect to profile page
        $redirect_url = home_url('/profile/');
        
        return '<div class="looksfam-message success">
            Registration successful! Welcome to Looksfam. Redirecting...
            <script>
                setTimeout(function() {
                    window.location.href = "' . esc_url($redirect_url) . '";
                }, 2000);
            </script>
        </div>';
    }
}

// Register the shortcode
add_shortcode('looksfam_register', 'looksfam_register_shortcode');

// Add this to your theme's functions.php file
function looksfam_login_shortcode($atts) {
    // Redirect if user is already logged in
    if (is_user_logged_in()) {
        return '<div class="looksfam-login-message">You are already logged in.</div>';
    }

    // Handle form submission
    $message = '';
    if (isset($_POST['looksfam_login_submit']) && $_POST['looksfam_login_submit']) {
        $message = looksfam_handle_login();
    }

    ob_start();
    echo looksfam_register_styles();
    echo looksfam_login_additional_styles();
    ?>
    
    <div class="looksfam-register-form">
        <?php if ($message): ?>
            <?php echo $message; ?>
        <?php endif; ?>

        <div class="looksfam-logo">
            <img src="https://www.looksfam.co/wp-content/uploads/2023/11/LOOk-2-1.png" alt="Looksfam Logo">
        </div>

        <h2 class="looksfam-form-title">Welcome Back</h2>
        <p class="looksfam-form-subtitle">Continue your board exam preparation!</p>

        <form method="post" id="looksfam-login-form" action="">
            <?php wp_nonce_field('looksfam_login_nonce', 'looksfam_nonce'); ?>

            <div class="looksfam-form-group">
                <label for="username_email" class="looksfam-form-label">Username or Email</label>
                <input type="text" id="username_email" name="username_email" class="looksfam-form-input" required
                       value="<?php echo isset($_POST['username_email']) ? esc_attr($_POST['username_email']) : ''; ?>"
                       placeholder="Enter your username or email">
            </div>

            <div class="looksfam-form-group">
                <label for="password" class="looksfam-form-label">Password</label>
                <div class="looksfam-password-wrapper">
                    <input type="password" id="password" name="password" class="looksfam-form-input" required
                           placeholder="Enter your password">
                    <button type="button" class="looksfam-password-toggle" id="password-toggle">
                        <span class="looksfam-toggle-text">Show</span>
                    </button>
                </div>
            </div>

            <div class="looksfam-form-options">
                <div class="looksfam-checkbox-group">
                    <input type="checkbox" id="remember_me" name="remember_me" class="looksfam-checkbox"
                           <?php echo isset($_POST['remember_me']) ? 'checked' : ''; ?>>
                    <label for="remember_me" class="looksfam-checkbox-label">Remember me</label>
                </div>
                <div class="looksfam-forgot-password">
                    <a href="<?php echo wp_lostpassword_url(); ?>">Forgot Password?</a>
                </div>
            </div>

            <input type="hidden" name="looksfam_login_submit" value="1">
            <button type="submit" class="looksfam-submit-btn">
                Sign In
            </button>
        </form>

        <div class="looksfam-register-link">
            Don't have an account? <a href="<?php echo home_url('/register'); ?>">Create one here</a>
        </div>
    </div>

    <script>
        document.addEventListener('DOMContentLoaded', function() {
            const form = document.getElementById('looksfam-login-form');
            const submitBtn = document.querySelector('.looksfam-submit-btn');
            const passwordInput = document.getElementById('password');
            const passwordToggle = document.getElementById('password-toggle');

            // Password toggle functionality
            passwordToggle.addEventListener('click', function() {
                const type = passwordInput.getAttribute('type') === 'password' ? 'text' : 'password';
                passwordInput.setAttribute('type', type);
                
                const toggleText = passwordToggle.querySelector('.looksfam-toggle-text');
                toggleText.textContent = type === 'password' ? 'Show' : 'Hide';
            });

            // Form submission handling
            form.addEventListener('submit', function(e) {
                submitBtn.disabled = true;
                submitBtn.innerHTML = 'Signing In...';
                
                // Re-enable button after 5 seconds to prevent permanent disable
                setTimeout(function() {
                    if (submitBtn.disabled) {
                        submitBtn.disabled = false;
                        submitBtn.innerHTML = 'Sign In';
                    }
                }, 5000);
            });

            // Smooth focus animations
            const inputs = document.querySelectorAll('.looksfam-form-input');
            inputs.forEach(input => {
                input.addEventListener('focus', function() {
                    this.parentElement.style.transform = 'scale(1.02)';
                });
                
                input.addEventListener('blur', function() {
                    this.parentElement.style.transform = 'scale(1)';
                });
            });
        });
    </script>

    <?php
    return ob_get_clean();
}

// Handle the login process
function looksfam_handle_login() {
    // Verify nonce
    if (!isset($_POST['looksfam_nonce']) || !wp_verify_nonce($_POST['looksfam_nonce'], 'looksfam_login_nonce')) {
        return '<div class="looksfam-message error">Security check failed. Please try again.</div>';
    }
    // Sanitize input data
    $username_email = sanitize_text_field($_POST['username_email']);
    $password = $_POST['password'];
    $remember_me = isset($_POST['remember_me']);
    // Validation
    $errors = array();
    if (empty($username_email)) $errors[] = 'Username or email is required.';
    if (empty($password)) $errors[] = 'Password is required.';
    // If there are errors, return them
    if (!empty($errors)) {
        return '<div class="looksfam-message error">' . implode('<br>', $errors) . '</div>';
    }
    // Prepare login credentials
    $credentials = array(
        'user_login'    => $username_email,
        'user_password' => $password,
        'remember'      => $remember_me
    );
    // If input contains @, it's probably an email
    if (strpos($username_email, '@') !== false) {
        $user = get_user_by('email', $username_email);
        if ($user) {
            $credentials['user_login'] = $user->user_login;
        }
    }
    // Attempt to sign the user in
    $user = wp_signon($credentials, false);
    if (is_wp_error($user)) {
        $error_message = $user->get_error_message();
        
        // Customize error messages for better user experience
        if (strpos($error_message, 'Invalid username') !== false || 
            strpos($error_message, 'The password you entered') !== false ||
            strpos($error_message, 'incorrect') !== false) {
            $error_message = 'Invalid username/email or password. Please try again.';
        }
        
        return '<div class="looksfam-message error">' . $error_message . '</div>';
    } else {
        // Login successful - set authentication cookies
        wp_set_auth_cookie($user->ID, $remember_me, is_ssl());
        
        // Set current user
        wp_set_current_user($user->ID);
        
        // Optional: Set additional custom cookies if needed
        // Example: Set a custom login timestamp cookie
        $cookie_expiry = $remember_me ? time() + (14 * DAY_IN_SECONDS) : 0; // 14 days if remember me, session cookie otherwise
        setcookie('looksfam_login_time', time(), $cookie_expiry, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        
        // Optional: Set user role cookie for quick access
        $user_roles = $user->roles;
        $primary_role = !empty($user_roles) ? $user_roles[0] : 'subscriber';
        setcookie('looksfam_user_role', $primary_role, $cookie_expiry, COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
        
        // Trigger WordPress login action
        do_action('wp_login', $user->user_login, $user);
        
        // Login successful, redirect to profile page
        $redirect_url = home_url('/profile/');
        
        return '<div class="looksfam-message success">
            Login successful! Welcome back. Redirecting...
            <script>
                setTimeout(function() {
                    window.location.href = "' . esc_url($redirect_url) . '";
                }, 2000);
            </script>
        </div>';
    }
}

// Register the login shortcode
add_shortcode('looksfam_login', 'looksfam_login_shortcode');

function looksfam_login_additional_styles() {
    echo '<style>
        .looksfam-password-wrapper {
            position: relative;
        }

        .looksfam-password-toggle {
            position: absolute;
            right: 12px;
            top: 50%;
            transform: translateY(-50%);
            background: none;
            border: none;
            color: var(--text-secondary);
            cursor: pointer;
            font-size: 14px;
            font-weight: 600;
            padding: 4px 8px;
            border-radius: 6px;
            transition: all 0.2s ease;
        }

        .looksfam-password-toggle:hover {
            background: var(--ast-global-color-subtle-background);
            color: var(--text-primary);
        }

        .looksfam-form-options {
            display: flex;
            justify-content: space-between;
            align-items: center;
            margin-bottom: 24px;
            flex-wrap: wrap;
            gap: 12px;
        }

        .looksfam-forgot-password {
            text-align:center;
        }
        .looksfam-forgot-password a {
            color: var(--ast-global-color-primary);
            text-decoration: none;
            font-size: 14px;
            font-weight: 600;
        }

        .looksfam-forgot-password a:hover {
            text-decoration: underline;
        }

        .looksfam-register-link {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--border-color);
        }

        .looksfam-register-link a {
            color: var(--ast-global-color-primary);
            text-decoration: none;
            font-weight: 600;
        }

        .looksfam-register-link a:hover {
            text-decoration: underline;
        }

        .looksfam-login-message {
            padding: 16px;
            border-radius: 12px;
            background: #dbeafe;
            color: #1d4ed8;
            border: 1px solid #bfdbfe;
            text-align: center;
            font-weight: 600;
        }

        @media (max-width: 768px) {
            .looksfam-form-options {
                flex-direction: column;
                align-items: flex-start;
                gap: 16px;
            }
        }

        @media (max-width: 480px) {
            .looksfam-password-toggle {
                right: 8px;
            }
        }
    </style>';
}

// Optional: Add some basic CSS to prevent conflicts
function looksfam_register_styles() {
    echo '<style>
        :root {
            --text-primary: var(--ast-global-color-5);
            --text-secondary: var(--ast-global-color-4);
            --border-color: var(--ast-global-color-5);
            --error-color: #ef4444;
            --success-color: #10b981;
        }

        .looksfam-register-form {
            margin: 10px 0px;
            border-radius: 16px;
            background:#ffffff57;
            box-shadow: 0 20px 25px -5px rgba(0, 0, 0, 0.1), 0 10px 10px -5px rgba(0, 0, 0, 0.04);
            padding: 40px;
            position: relative;
            overflow: hidden;
        }

        .looksfam-logo {
            text-align: center;
            margin-bottom: 32px;
        }

        .looksfam-logo img {
            max-width: 200px;
            height: auto;
            max-height: 80px;
            object-fit: contain;
        }

        .looksfam-form-title {
            text-align: center;
            font-size: 28px;
            font-weight: 700;
            color: var(--text-primary);
            margin-bottom: 8px;
        }

        .looksfam-form-subtitle {
            text-align: center;
            color: var(--text-secondary);
            margin-bottom: 0px!important;
            font-size: 16px;
        }

        .looksfam-form-group {
            margin-bottom: 20px;
        }

        .looksfam-form-row {
            display: grid;
            grid-template-columns: 1fr 1fr;
            gap: 16px;
            margin-bottom: 20px;
        }

        .looksfam-form-label {
            display: block;
            font-weight: 600;
            color: var(--text-primary);
            margin-bottom: 8px;
            font-size: 14px;
        }

        .looksfam-form-input {
            width: 100%;
            padding: 14px 16px;
            border: 2px solid var(--border-color);
            border-radius: 12px;
            font-size: 16px;
            background: white;
            transition: all 0.3s ease;
            font-family: inherit;
            box-sizing: border-box;
        }

        .looksfam-form-input:focus {
            outline: none;
            border-color: var(--ast-global-color-primary);
            box-shadow: 0 0 0 3px rgba(79, 70, 229, 0.1);
            transform: translateY(-1px);
        }

        .looksfam-form-input:invalid {
            border-color: var(--error-color);
        }

        .looksfam-password-strength {
            margin-top: 8px;
            font-size: 12px;
            color: var(--text-secondary);
        }

        .looksfam-checkbox-group {
            display: flex;
            align-items: flex-start;
            gap: 12px;
            margin-bottom: 24px;
            padding: 16px;
            background: var(--ast-global-color-subtle-background);
            border-radius: 12px;
        }

        .looksfam-checkbox {
            width: 18px;
            height: 18px;
            border: 2px solid var(--border-color);
            border-radius: 4px;
            cursor: pointer;
            flex-shrink: 0;
            margin-top: 2px;
        }

        .looksfam-checkbox:checked {
            background: var(--ast-global-color-primary);
            border-color: var(--ast-global-color-primary);
        }

        .looksfam-checkbox-label {
            font-size: 14px;
            line-height: 1.5;
            color: var(--text-secondary);
            cursor: pointer;
        }

        .looksfam-checkbox-label a {
            color: var(--ast-global-color-primary);
            text-decoration: none;
            font-weight: 600;
        }

        .looksfam-checkbox-label a:hover {
            text-decoration: underline;
        }

        .looksfam-submit-btn {
            width: 100%;
            background: linear-gradient(135deg, var(--ast-global-color-primary), var(--ast-global-color-secondary));
            color: white;
            border: none;
            padding: 16px 24px;
            border-radius: 12px;
            font-size: 16px;
            font-weight: 600;
            cursor: pointer;
            transition: all 0.3s ease;
            text-transform: uppercase;
            letter-spacing: 0.5px;
        }

        .looksfam-submit-btn:hover {
            transform: translateY(-2px);
            box-shadow: 0 10px 20px -5px rgba(79, 70, 229, 0.4);
        }

        .looksfam-submit-btn:active {
            transform: translateY(0);
        }

        .looksfam-submit-btn:disabled {
            opacity: 0.6;
            cursor: not-allowed;
            transform: none;
        }

        .looksfam-login-link {
            text-align: center;
            margin-top: 24px;
            padding-top: 24px;
            border-top: 1px solid var(--border-color);
        }

        .looksfam-login-link a {
            color: var(--ast-global-color-primary);
            text-decoration: none;
            font-weight: 600;
        }

        .looksfam-login-link a:hover {
            text-decoration: underline;
        }

        .looksfam-message {
            padding: 16px;
            border-radius: 12px;
            margin-bottom: 20px;
            font-weight: 600;
        }

        .looksfam-message.success {
            background: #dcfce7;
            color: #16a34a;
            border: 1px solid #bbf7d0;
        }

        .looksfam-message.error {
            background: #fef2f2;
            color: #dc2626;
            border: 1px solid #fecaca;
        }

        /* Mobile Responsiveness */
        @media (max-width: 768px) {
            .looksfam-register-container {
                padding: 20px 16px;
                margin: 10px;
            }

            .looksfam-register-form {
                padding: 32px 24px;
            }

            .looksfam-form-title {
                font-size: 24px;
            }

            .looksfam-form-row {
                grid-template-columns: 1fr;
                gap: 0;
            }

            .looksfam-form-group {
                margin-bottom: 16px;
            }

            .looksfam-logo img {
                max-width: 150px;
            }
            .looksfam-checkbox-group {
                width:100%;
            }
        }

        @media (max-width: 480px) {
            .looksfam-register-form {
                padding: 24px 20px;
            }

            .looksfam-form-input {
                padding: 12px 14px;
                font-size: 16px; /* Prevents zoom on iOS */
            }
        }
        </style>';
}
?>