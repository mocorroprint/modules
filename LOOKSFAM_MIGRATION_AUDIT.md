# Looksfam Plugin Migration Audit & Guide

## Executive Summary

This document provides a complete audit of the `looksfam_og` WordPress plugin, detailing the database schema, data structures, features, and a comprehensive migration guide to replicate all functionality in a new system while maintaining feature parity.

---

## 1. DATABASE SCHEMA AUDIT

### 1.1 Core Custom Tables

#### **wp_exam_answers** (Primary Data Table)

This is the ONLY custom table required. All other data uses WordPress core tables.

```sql
CREATE TABLE wp_exam_answers (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    exam_id BIGINT(20) UNSIGNED NOT NULL,
    class_id BIGINT(20) UNSIGNED NOT NULL,
    exam_name VARCHAR(255) NOT NULL,
    question_id BIGINT(20) UNSIGNED NOT NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    user_answer TEXT NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    session_id VARCHAR(10) NOT NULL,
    question_category BIGINT(20) UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY exam_id (exam_id),
    KEY class_id (class_id),
    KEY question_id (question_id),
    KEY user_id (user_id),
    KEY session_id (session_id),
    KEY timestamp (timestamp)
);
```

**Purpose**: Stores all student answer submissions with tracking for analytics.

**Key Fields**:
- `exam_id`: Links to exam post (or category term for activities)
- `class_id`: Links to class post
- `question_id`: Links to question post
- `user_id`: WordPress user ID
- `session_id`: Unique identifier per exam attempt (format: Unix timestamp + random 4 digits)
- `is_correct`: Binary flag (1=correct, 0=incorrect)
- `question_category`: Term ID from question_category taxonomy

---

### 1.2 WordPress Post Types (Stored in wp_posts)

The plugin uses 4 custom post types:

| Post Type | Label | UI Visible | Purpose | Key Meta Fields |
|-----------|-------|------------|---------|-----------------|
| `exam` | Exams | No (show_ui=false) | Exam configurations | selected_questions, exam_results, question_results |
| `question` | Questions | Yes | Individual questions | multiple_choice_options, correct_answer, solution, question_results |
| `class` | Classes | Yes | Course/class containers | enrolled_students, exam_subject, exam_topic, _exam_num_questions, _exam_time_per_question |
| `transaction` | Transaction | Yes | Payment records | Standard transaction data |

**Post Type Registration** (from `class-post.php`):
```php
// Exam - hidden from UI, used for configuration
register_post_type('exam', [
    'public' => false,
    'show_ui' => false,
    'supports' => ['title'],
    'taxonomies' => ['main_board', 'subject', 'topic', 'subtopic']
]);

// Question - main content type
register_post_type('question', [
    'public' => true,
    'show_ui' => true,
    'supports' => ['title', 'thumbnail', 'editor']
]);

// Class - enrollment container
register_post_type('class', [
    'public' => false,
    'show_ui' => true,
    'supports' => ['title', 'editor']
]);

// Transaction - payment tracking
register_post_type('transaction', [
    'public' => false,
    'show_ui' => true,
    'supports' => ['title', 'editor']
]);
```

---

### 1.3 WordPress Taxonomies (Stored in wp_terms, wp_term_taxonomy, wp_term_relationships)

#### **question_category** (Hierarchical)
- **Attached to**: `question` post type
- **Purpose**: Hierarchical categorization of questions
- **Structure**: 
  - Exam (parent term, term_id typically 305 or similar)
    - Subject (children of Exam)
      - Topic (children of Subject)
        - Subtopic (children of Topic)
- **Usage**: Used to filter questions for exams and activities

#### **class** (Non-hierarchical)
- **Attached to**: `class` post type  
- **Purpose**: Class categorization (Free/Premium)
- **Terms**: 
  - `Free` - Classes accessible without payment
  - `Premium` - Classes requiring payment/enrollment key

---

### 1.4 User Meta Fields (Stored in wp_usermeta)

| Meta Key | Data Type | Purpose |
|----------|-----------|---------|
| `classes_enrolled` | Array of class IDs | Tracks which classes a user is enrolled in |
| `first_name` | String | User's first name (display in reports) |
| `last_name` | String | User's last name (display in reports) |
| `questions` | Array | Legacy storage of question attempts (being migrated to wp_exam_answers) |
| `free_trial_{class_id}` | Boolean | Tracks if user used free trial for specific class |

---

### 1.5 Post Meta Fields (Stored in wp_postmeta)

#### **Class Post Meta**:
| Meta Key | Data Type | Purpose |
|----------|-----------|---------|
| `enrolled_students` | Array of user IDs | List of students enrolled in this class |
| `exam_subject` | Term ID | Selected subject from question_category |
| `exam_topic` | Term ID | Selected topic from question_category |
| `_exam_num_questions` | Integer | Number of questions per activity (default: 10) |
| `_exam_time_per_question` | Integer | Time limit per question in seconds (default: 30) |

#### **Exam Post Meta**:
| Meta Key | Data Type | Purpose |
|----------|-----------|---------|
| `selected_questions` | Array of question IDs | Questions included in this exam |
| `exam_results` | Array | Legacy results storage (migrated to wp_exam_answers) |
| `question_results` | Array | Legacy per-question results (migrated to wp_exam_answers) |

#### **Question Post Meta**:
| Meta Key | Data Type | Purpose |
|----------|-----------|---------|
| `multiple_choice_options` | Array {A, B, C, D} | Four answer options |
| `correct_answer` | String (A/B/C/D) | Correct option letter |
| `solution` | Text | Explanation of correct answer |
| `question_results` | Array | Legacy results (migrated to wp_exam_answers) |

#### **Term Meta** (Stored in wp_termmeta):
| Meta Key | Data Type | Purpose |
|----------|-----------|---------|
| `class_keys` (term_id=305) | Array | Enrollment keys for classes |

**Class Keys Structure**:
```php
[
    'KEY123' => [
        'class' => 123,           // Class ID
        'status' => 'Used',       // Unused/Used/Expired
        'user' => 456,            // User ID who used it
        'used_timestamp' => '2024-01-01 12:00:00'
    ],
    // ... more keys
]
```

---

## 2. FEATURES AUDIT

### 2.1 Student-Side Features

#### **A. Enrollment System**

**Location**: `class-admin-enroll.php`

**Features**:
1. **Class Key Enrollment**
   - Students enter enrollment keys to join classes
   - Keys are stored in term_meta (term_id=305, key='class_keys')
   - Key status tracking: Unused → Used → Expired
   - One-time use per user (re-enrollment marks previous as Expired)

2. **Free Class Auto-Enrollment**
   - Classes tagged with "Free" term can be joined instantly
   - AJAX endpoint: `get_unused_enrollment_key` generates temporary key

3. **Premium Class Checkout**
   - Classes tagged with "Premium" redirect to `/checkout?class={id}`
   - Integration with payment gateway (external)

4. **Enrollment Display**
   - Shortcode: `[enrollment_form]`
   - Searchable class list with AJAX
   - Shows available classes (excludes already enrolled)
   - Go-back navigation to profile

**Data Flow**:
```
User enters key → Validate against term_meta(305, 'class_keys')
→ Update post_meta(class_id, 'enrolled_students', [user_ids])
→ Update user_meta(user_id, 'classes_enrolled', [class_ids])
→ Mark key as Used with timestamp
```

---

#### **B. Subject/Class Selection**

**Location**: Profile page (`class-display-user.php`)

**Features**:
1. **Profile Dashboard**
   - Displays all enrolled classes
   - Each class shows:
     - Class title
     - Subject & Topic (from question_category taxonomy)
     - Progress indicators
     - Activity/Exam links

2. **Category Navigation**
   - Hierarchical browsing: Subject → Topic → Subtopic
   - Filtered by class association (exam_subject, exam_topic meta)

3. **Access Control**
   - Only shows content for enrolled classes
   - Checks: `in_array(user_id, get_post_meta(class_id, 'enrolled_students'))`

---

#### **C. Answering Activities/Exams**

**Location**: `class-display-activity.php`, `class-display-exam.php`, `class-display-exercise.php`

**Activity Types**:
1. **Exams** (`[display_exam]` shortcode)
   - Pre-selected questions from exam post meta
   - One-time attempt tracking
   - Full statistics after completion

2. **Activities** (`[activity]` shortcode)
   - Dynamic question selection from category
   - Configurable question count (default: 10)
   - Based on class settings (_exam_num_questions)

3. **Exercises/Flashcards** (`[display_exercise]` shortcode)
   - Flashcard-style interface
   - Immediate feedback
   - Practice mode (unlimited attempts)

**Exam Interface Features**:
- Loading screen with spinner
- Timer display (countdown per question or total)
- Progress bar
- Flashcard flip animation
- Image support in questions (clickable zoom modal)
- Multiple choice selection (A/B/C/D)
- Solution reveal toggle
- Navigation: Previous/Next/Submit
- Overlay during submission
- Success modal with results table

**Answer Submission Process**:
```javascript
// Client-side
1. Collect answers: [{question_id, user_answer, is_correct}]
2. Generate session_id: time() + rand(1000,9999)
3. Batch submit via AJAX

// Server-side (save_exam_answers_batch)
1. Validate nonce
2. Check table exists
3. Batch INSERT into wp_exam_answers
4. Return success/failure
```

**Data Saved per Question**:
```php
[
    'exam_id' => int,
    'class_id' => int,
    'exam_name' => string,
    'question_id' => int,
    'user_id' => int,
    'user_answer' => string (A/B/C/D),
    'is_correct' => int (0/1),
    'timestamp' => datetime,
    'session_id' => string,
    'question_category' => int (term_id)
]
```

---

#### **D. Analytics & Statistics**

**Location**: `wordpress-plugin-template.php`, `class-display-review.php`, `class-admin-class.php`

**Analytics Levels**:

##### **1. Sitewide Analytics** (Admin)
- Total answers across all users/exams
- Overall accuracy rate
- Unique users count
- Total sessions count
- Query: Aggregates from wp_exam_answers

##### **2. Subject/Category Analytics**
- Performance by question_category
- Average accuracy per topic
- Question difficulty analysis
- Filters by taxonomy term

##### **3. Class-Level Analytics**
**Location**: `class-admin-class.php` metaboxes

**Student Statistics Table**:
- Student name (last, first)
- Total correct answers
- Unique sessions (exam attempts)
- Looksfam Accuracy (custom metric)
- Sorted by Looksfam Accuracy descending

**Question Statistics Table**:
- Question title
- Unique sessions count
- Total correct answers
- Looksfam Rating
- Sorted by Looksfam Rating descending

##### **4. Individual Exam Statistics** (Student View)

**Post-Exam Results**:
- Score percentage
- Correct/wrong breakdown per question
- Review mode with explanations
- Session comparison

##### **5. Individual Question Analytics**

**Looksfam Algorithm** (`questionstatlooksfam` function):
Complex scoring algorithm considering:
- User's correct rate vs others' correct rate
- Session efficiency (correct per session)
- Retention factor (time span between attempts)
- Decay factor (time since last attempt)
- Relative performance metrics

**Formula Components**:
```php
$user_correct_rate = $user_correct / $user_total;
$avg_others_correct_rate = $others_correct / $others_total;
$relative_correct_rate = $user_correct_rate / $avg_others_correct_rate;

$session_efficiency = $user_correct / $user_session_count;
$relative_session_efficiency = $session_efficiency / $avg_other_session_efficiency;

$retention_factor = min(1, log($retention_span + 1) / log(31));
$decay_factor = exp(-0.1 * $time_since_last);

$base_score = $relative_correct_rate * $relative_session_efficiency * 100;
$percentage_score = $base_score * (0.7 + 0.3 * $retention_factor) * $decay_factor;

// Session count adjustment
if ($user_session_count < $avg_others_session_count) {
    $percentage_score *= 1.2; // Bonus for fewer sessions
} else {
    $percentage_score *= 0.8; // Penalty for more sessions
}
```

**Cumulative Rating** (`questionoveralllooksfam`):
- Aggregates performance across all users for a question
- Shows overall difficulty/community performance

**Display Functions**:
- `displayquestionstat()` - Per-exam question breakdown
- `user_displayquestionstat()` - User-specific sorted view
- `questionstatlooksfam_batch()` - Optimized batch calculation

---

### 2.2 Admin-Side Features

#### **A. Question Management**

**Location**: `class-admin-question.php`

**Features**:
1. **JSON Import**
   - Bulk import questions from JSON files
   - Format: `{title, options: {A,B,C,D}, correct_answer, solution, category}`
   - AJAX upload with progress tracking
   - Duplicate detection

2. **Category Import**
   - Import question_category taxonomy from JSON
   - Supports hierarchical structure (parent/child)
   - Skips existing categories

3. **Question List View**
   - Custom admin page: "All Questions"
   - Pagination (50 per page)
   - Displays: ID, Title, Options, Correct Answer, Solution, Content
   - Sortable columns

4. **Bulk Update Tools**
   - "Update All Questions" - batch processing
   - Progress bar with AJAX
   - Fix content formatting

5. **Meta Box for Questions**
   - Multiple choice options (A/B/C/D inputs)
   - Correct answer dropdown
   - Solution textarea
   - Featured image upload (for diagram questions)
   - Image preview

---

#### **B. Exam Management**

**Location**: `class-admin-exam.php`

**Features**:
1. **Exam Configuration**
   - Select questions from category
   - Random or manual selection
   - Store in `selected_questions` meta

2. **Results Viewing**
   - View all student attempts
   - Per-session breakdown
   - Export capabilities (via PhpSpreadsheet)

---

#### **C. Class Management**

**Location**: `class-admin-class.php`

**Features**:
1. **Subject/Topic Assignment**
   - Dropdown for exam_subject (from question_category)
   - Dynamic dropdown for exam_topic (filtered by subject)
   - AJAX refresh on subject change

2. **Exam Settings**
   - Number of questions (default: 10)
   - Time per question in seconds (default: 30)

3. **Student Management**
   - Add students metabox (multi-select from all users)
   - Remove students metabox (checkbox list)
   - Total enrollees counter
   - Direct enrollment via `enrollclass()` function

4. **Analytics Dashboard**
   - Student performance table (sorted by Looksfam Accuracy)
   - Question performance table (sorted by Looksfam Rating)
   - Real-time calculations from wp_exam_answers

---

#### **D. Enrollment Key Management**

**Location**: `class-admin-enroll.php`

**Features**:
1. **Key Generation**
   - Stored in term_meta(305, 'class_keys')
   - Format: Alphanumeric code
   - Associated with specific class
   - Status tracking (Unused/Used/Expired)

2. **Key Distribution**
   - Free classes: Auto-generate unused key on click
   - Premium classes: Manual distribution or payment integration

3. **Key Validation**
   - Check status !== 'Used' OR (status === 'Used' AND user matches)
   - Prevent duplicate active enrollments
   - Timestamp tracking

---

#### **E. Database Management**

**Location**: `wordpress-plugin-template.php`

**Features**:
1. **Table Creation Tool**
   - Admin menu: Tools → Exam Answers DB
   - Create/drop wp_exam_answers table
   - Status indicator
   - Row count display

2. **Data Repair**
   - `fix_exam_answers_page` - AJAX batch processor
   - Fixes mismatched question/post IDs
   - Progress tracking with transients

---

## 3. MIGRATION GUIDE

### Phase 1: Database Schema Setup

#### Step 1.1: Create Custom Table

```sql
-- Execute on new database
CREATE TABLE IF NOT EXISTS wp_exam_answers (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    exam_id BIGINT(20) UNSIGNED NOT NULL,
    class_id BIGINT(20) UNSIGNED NOT NULL,
    exam_name VARCHAR(255) NOT NULL,
    question_id BIGINT(20) UNSIGNED NOT NULL,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    user_answer TEXT NOT NULL,
    is_correct TINYINT(1) NOT NULL DEFAULT 0,
    timestamp DATETIME DEFAULT CURRENT_TIMESTAMP,
    session_id VARCHAR(10) NOT NULL,
    question_category BIGINT(20) UNSIGNED DEFAULT NULL,
    created_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
    updated_at TIMESTAMP DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
    PRIMARY KEY (id),
    KEY exam_id (exam_id),
    KEY class_id (class_id),
    KEY question_id (question_id),
    KEY user_id (user_id),
    KEY session_id (session_id),
    KEY timestamp (timestamp),
    KEY question_category (question_category)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### Step 1.2: Register Post Types

```php
// In plugin initialization
add_action('init', function() {
    // Exam post type
    register_post_type('exam', [
        'labels' => ['name' => 'Exams', 'singular_name' => 'Exam'],
        'public' => false,
        'show_ui' => false,
        'supports' => ['title'],
        'show_in_nav_menus' => false,
    ]);

    // Question post type
    register_post_type('question', [
        'labels' => ['name' => 'Questions', 'singular_name' => 'Question'],
        'public' => true,
        'show_ui' => true,
        'supports' => ['title', 'thumbnail', 'editor'],
    ]);

    // Class post type
    register_post_type('class', [
        'labels' => ['name' => 'Classes', 'singular_name' => 'Class'],
        'public' => false,
        'show_ui' => true,
        'supports' => ['title', 'editor'],
        'has_archive' => false,
        'rewrite' => ['slug' => 'class']
    ]);

    // Transaction post type
    register_post_type('transaction', [
        'labels' => ['name' => 'Transactions', 'singular_name' => 'Transaction'],
        'public' => false,
        'show_ui' => true,
        'supports' => ['title', 'editor'],
        'has_archive' => false
    ]);
});
```

#### Step 1.3: Register Taxonomies

```php
add_action('init', function() {
    // Question Category (hierarchical)
    register_taxonomy('question_category', 'question', [
        'hierarchical' => true,
        'labels' => [
            'name' => 'Question Categories',
            'singular_name' => 'Question Category',
            'menu_name' => 'Question Categories'
        ],
        'show_ui' => true,
        'show_admin_column' => true,
        'query_var' => true,
        'rewrite' => ['slug' => 'question-category'],
    ]);

    // Class Category (for Free/Premium)
    register_taxonomy('class', 'class', [
        'hierarchical' => false,
        'labels' => [
            'name' => 'Class Categories',
            'singular_name' => 'Class Category'
        ],
        'show_ui' => true,
        'show_admin_column' => true,
    ]);
});
```

#### Step 1.4: Create Default Terms

```php
// Run once on activation
function create_default_terms() {
    // Create "Exam" parent category
    $exam_cat = wp_insert_term('Exam', 'question_category');
    
    // Create Free/Premium class categories
    wp_insert_term('Free', 'class');
    wp_insert_term('Premium', 'class');
    
    // Initialize class keys storage
    if ($exam_cat && !is_wp_error($exam_cat)) {
        update_term_meta($exam_cat['term_id'], 'class_keys', []);
    }
}
register_activation_hook(__FILE__, 'create_default_terms');
```

---

### Phase 2: Data Migration

#### Step 2.1: Migrate Legacy Answer Data

If migrating from old system using user_meta 'questions':

```php
function migrate_legacy_answers() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'exam_answers';
    
    // Get all users
    $users = get_users(['fields' => 'ID']);
    
    foreach ($users as $user_id) {
        $legacy_questions = get_user_meta($user_id, 'questions', true);
        
        if (!empty($legacy_questions) && is_array($legacy_questions)) {
            $batch = [];
            
            foreach ($legacy_questions as $q_data) {
                $batch[] = [
                    'exam_id' => 0, // May need mapping
                    'class_id' => $q_data['class_id'] ?? 0,
                    'exam_name' => 'Migrated',
                    'question_id' => $q_data['question_id'],
                    'user_id' => $user_id,
                    'user_answer' => $q_data['answer'] ?? '',
                    'is_correct' => $q_data['is_correct'] ? 1 : 0,
                    'timestamp' => $q_data['timestamp'] ?? current_time('mysql'),
                    'session_id' => $q_data['session_id'] ?? substr(md5(time() . rand()), 0, 10),
                    'question_category' => get_post_meta($q_data['question_id'], 'question_category', true)
                ];
                
                // Batch insert every 100 records
                if (count($batch) >= 100) {
                    save_exam_answers_batch($batch);
                    $batch = [];
                }
            }
            
            // Insert remaining
            if (!empty($batch)) {
                save_exam_answers_batch($batch);
            }
            
            // Optionally delete legacy meta
            // delete_user_meta($user_id, 'questions');
        }
    }
}
```

#### Step 2.2: Migrate Exam Results

```php
function migrate_exam_results() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'exam_answers';
    
    // Get all exam posts
    $exams = get_posts([
        'post_type' => 'exam',
        'numberposts' => -1,
        'post_status' => 'any'
    ]);
    
    foreach ($exams as $exam) {
        $exam_results = get_post_meta($exam->ID, 'exam_results', true);
        
        if (!empty($exam_results) && is_array($exam_results)) {
            $batch = [];
            
            foreach ($exam_results as $result) {
                $batch[] = [
                    'exam_id' => $exam->ID,
                    'class_id' => $result['class_id'] ?? 0,
                    'exam_name' => $exam->post_title,
                    'question_id' => $result['question_id'],
                    'user_id' => $result['user_id'],
                    'user_answer' => $result['user_answer'] ?? '',
                    'is_correct' => $result['is_correct'] ? 1 : 0,
                    'timestamp' => $result['timestamp'] ?? current_time('mysql'),
                    'session_id' => $result['session_id'] ?? substr(md5(time() . rand()), 0, 10),
                    'question_category' => get_post_meta($result['question_id'], 'question_category', true)
                ];
                
                if (count($batch) >= 100) {
                    save_exam_answers_batch($batch);
                    $batch = [];
                }
            }
            
            if (!empty($batch)) {
                save_exam_answers_batch($batch);
            }
        }
    }
}
```

---

### Phase 3: Feature Implementation

#### Step 3.1: Enrollment System

```php
// Shortcode: [enrollment_form]
function enrollment_shortcode() {
    if (!is_user_logged_in()) {
        return '<p>You must be logged in to enroll.</p>';
    }
    
    ob_start();
    ?>
    <div class="enrollment-container">
        <form method="post">
            <label>Enter Class Key:</label>
            <input type="text" name="enroll_key" required>
            <button type="submit">Enroll</button>
        </form>
        
        <div id="available-classes">
            <!-- AJAX-loaded class list -->
        </div>
    </div>
    <?php
    return ob_get_clean();
}
add_shortcode('enrollment_form', 'enrollment_shortcode');

// Process enrollment
function process_enrollment_key() {
    if (!isset($_POST['enroll_key'])) return null;
    
    $enroll_key = sanitize_text_field($_POST['enroll_key']);
    $user_id = get_current_user_id();
    
    // Get class keys from term meta (term_id may vary)
    $exam_cat = get_term_by('name', 'Exam', 'question_category');
    $class_keys = get_term_meta($exam_cat->term_id, 'class_keys', true) ?: [];
    
    // Validate key
    $class_id = null;
    foreach ($class_keys as $key => $data) {
        if ($key === $enroll_key && 
            ($data['status'] === 'Unused' || 
             ($data['status'] === 'Used' && $data['user'] == $user_id))) {
            $class_id = $data['class'];
            break;
        }
    }
    
    if (!$class_id) {
        return ['success' => false, 'message' => 'Invalid or used key'];
    }
    
    // Enroll user
    $enrolled_students = get_post_meta($class_id, 'enrolled_students', true) ?: [];
    if (!in_array($user_id, $enrolled_students)) {
        $enrolled_students[] = $user_id;
        update_post_meta($class_id, 'enrolled_students', $enrolled_students);
    }
    
    $classes_enrolled = get_user_meta($user_id, 'classes_enrolled', true) ?: [];
    if (!in_array($class_id, $classes_enrolled)) {
        $classes_enrolled[] = $class_id;
        update_user_meta($user_id, 'classes_enrolled', $classes_enrolled);
    }
    
    // Update key status
    foreach ($class_keys as $key => &$data) {
        if ($data['class'] == $class_id && $data['status'] === 'Used' && $data['user'] == $user_id) {
            $data['status'] = 'Expired';
        }
    }
    
    $class_keys[$enroll_key]['status'] = 'Used';
    $class_keys[$enroll_key]['user'] = $user_id;
    $class_keys[$enroll_key]['used_timestamp'] = current_time('mysql');
    update_term_meta($exam_cat->term_id, 'class_keys', $class_keys);
    
    return [
        'success' => true,
        'message' => 'Enrollment successful!',
        'redirect_url' => home_url('/profile')
    ];
}
```

#### Step 3.2: Answer Submission Handler

```php
function save_exam_answers_batch($answers_data) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'exam_answers';
    
    if (empty($answers_data)) return false;
    
    // Verify table exists
    if ($wpdb->get_var("SHOW TABLES LIKE '$table_name'") != $table_name) {
        return false;
    }
    
    // Prepare batch insert
    $values = [];
    $placeholders = [];
    
    foreach ($answers_data as $data) {
        $values[] = $data['exam_id'];
        $values[] = $data['class_id'];
        $values[] = $data['exam_name'];
        $values[] = $data['question_id'];
        $values[] = $data['user_id'];
        $values[] = $data['user_answer'];
        $values[] = $data['is_correct'];
        $values[] = $data['timestamp'];
        $values[] = $data['session_id'];
        $values[] = $data['question_category'];
        
        $placeholders[] = "(%d, %d, %s, %d, %d, %s, %d, %s, %s, %d)";
    }
    
    $sql = "INSERT INTO $table_name 
            (exam_id, class_id, exam_name, question_id, user_id, user_answer, 
             is_correct, timestamp, session_id, question_category) 
            VALUES " . implode(', ', $placeholders);
    
    return $wpdb->query($wpdb->prepare($sql, $values)) !== false;
}

// AJAX handler
function handle_exam_submission() {
    check_ajax_referer('flashcard_exam_submission_nonce', 'nonce');
    
    $answers = $_POST['answers'] ?? [];
    $session_id = $_POST['session_id'] ?? (time() . rand(1000, 9999));
    
    $saved = save_exam_answers_batch($answers);
    
    wp_send_json([
        'success' => $saved,
        'session_id' => $session_id
    ]);
}
add_action('wp_ajax_submit_flashcard_exam', 'handle_exam_submission');
```

#### Step 3.3: Analytics Functions

```php
/**
 * Calculate Looksfam Accuracy for a question
 */
function questionstatlooksfam($question_id, $user_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'exam_answers';
    
    // Get all attempts for this question
    $results = $wpdb->get_results($wpdb->prepare(
        "SELECT user_id, is_correct, timestamp, session_id 
         FROM $table_name 
         WHERE question_id = %d 
         ORDER BY timestamp ASC",
        $question_id
    ), ARRAY_A);
    
    if (empty($results)) return 0;
    
    // Separate user and others data
    $user_stats = ['correct' => 0, 'total' => 0, 'sessions' => []];
    $others_stats = ['correct' => 0, 'total' => 0, 'sessions' => []];
    $latest_time = 0;
    $first_time = PHP_INT_MAX;
    
    foreach ($results as $result) {
        $time = strtotime($result['timestamp']);
        $latest_time = max($latest_time, $time);
        
        if ($result['user_id'] == $user_id) {
            $user_stats['total']++;
            $user_stats['correct'] += $result['is_correct'];
            $user_stats['sessions'][$result['session_id']] = true;
            $first_time = min($first_time, $time);
        } else {
            $others_stats['total']++;
            $others_stats['correct'] += $result['is_correct'];
            $others_stats['sessions'][$result['session_id']] = true;
        }
    }
    
    // Calculate metrics
    $user_session_count = count($user_stats['sessions']);
    $others_session_count = count($others_stats['sessions']);
    
    $avg_others_correct_rate = $others_stats['total'] > 0 
        ? $others_stats['correct'] / $others_stats['total'] 
        : 0;
    
    $user_correct_rate = $user_stats['total'] > 0 
        ? $user_stats['correct'] / $user_stats['total'] 
        : 0;
    
    $relative_correct_rate = $avg_others_correct_rate > 0 
        ? $user_correct_rate / $avg_others_correct_rate 
        : 1;
    
    $session_efficiency = $user_session_count > 0 
        ? $user_stats['correct'] / $user_session_count 
        : 0;
    
    $avg_other_session_efficiency = $others_session_count > 0 
        ? $avg_others_correct_rate / $others_session_count 
        : 0;
    
    $relative_session_efficiency = $avg_other_session_efficiency > 0 
        ? $session_efficiency / $avg_other_session_efficiency 
        : 1;
    
    // Retention factor
    $retention_span = max(1, ($latest_time - $first_time) / 86400);
    $retention_factor = min(1, log($retention_span + 1) / log(31));
    
    // Decay factor
    $current_time = current_time('timestamp');
    $time_since_last = max(0, ($current_time - $latest_time) / 86400);
    $decay_factor = exp(-0.1 * $time_since_last);
    
    // Final score
    $base_score = $relative_correct_rate * $relative_session_efficiency * 100;
    $percentage_score = $base_score * (0.7 + 0.3 * $retention_factor) * $decay_factor;
    
    // Session adjustment
    $percentage_score *= ($user_session_count < $others_session_count) ? 1.2 : 0.8;
    
    return round(min(max($percentage_score, 0), 100), 2);
}

/**
 * Get sitewide statistics
 */
function get_sitewide_stats() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'exam_answers';
    
    return $wpdb->get_row(
        "SELECT 
            COUNT(*) as total_answers,
            SUM(is_correct) as correct_answers,
            COUNT(DISTINCT user_id) as unique_users,
            COUNT(DISTINCT session_id) as total_sessions,
            AVG(is_correct) as overall_accuracy
         FROM $table_name",
        ARRAY_A
    );
}

/**
 * Get class-level student statistics
 */
function get_class_student_stats($class_id) {
    global $wpdb;
    $table_name = $wpdb->prefix . 'exam_answers';
    
    $enrolled_students = get_post_meta($class_id, 'enrolled_students', true) ?: [];
    
    if (empty($enrolled_students)) return [];
    
    $student_ids = implode(',', array_map('intval', $enrolled_students));
    
    $stats = $wpdb->get_results(
        "SELECT 
            user_id,
            COUNT(*) as total_attempts,
            SUM(is_correct) as correct_answers,
            COUNT(DISTINCT session_id) as sessions,
            AVG(is_correct) as accuracy
         FROM $table_name 
         WHERE class_id = $class_id 
           AND user_id IN ($student_ids)
         GROUP BY user_id
         ORDER BY accuracy DESC",
        ARRAY_A
    );
    
    // Add Looksfam calculations
    foreach ($stats as &$stat) {
        // Would need to calculate per-question for accurate Looksfam
        $stat['looksfam_accuracy'] = 0; // Placeholder
    }
    
    return $stats;
}
```

---

### Phase 4: Admin Interfaces

#### Step 4.1: Question Management Metabox

```php
function add_question_meta_boxes() {
    add_meta_box(
        'question_options',
        'Multiple Choice Options',
        'render_question_options',
        'question',
        'normal',
        'high'
    );
}
add_action('add_meta_boxes', 'add_question_meta_boxes');

function render_question_options($post) {
    wp_nonce_field('question_options_nonce', 'question_options_nonce');
    
    $options = get_post_meta($post->ID, 'multiple_choice_options', true) ?: [];
    $correct = get_post_meta($post->ID, 'correct_answer', true);
    $solution = get_post_meta($post->ID, 'solution', true);
    ?>
    <div class="question-meta">
        <div class="option-row">
            <label>A:</label>
            <input type="text" name="options[A]" value="<?php echo esc_attr($options['A'] ?? ''); ?>" style="width:100%">
        </div>
        <div class="option-row">
            <label>B:</label>
            <input type="text" name="options[B]" value="<?php echo esc_attr($options['B'] ?? ''); ?>" style="width:100%">
        </div>
        <div class="option-row">
            <label>C:</label>
            <input type="text" name="options[C]" value="<?php echo esc_attr($options['C'] ?? ''); ?>" style="width:100%">
        </div>
        <div class="option-row">
            <label>D:</label>
            <input type="text" name="options[D]" value="<?php echo esc_attr($options['D'] ?? ''); ?>" style="width:100%">
        </div>
        
        <div class="correct-answer-row">
            <label>Correct Answer:</label>
            <select name="correct_answer">
                <option value="A" <?php selected($correct, 'A'); ?>>A</option>
                <option value="B" <?php selected($correct, 'B'); ?>>B</option>
                <option value="C" <?php selected($correct, 'C'); ?>>C</option>
                <option value="D" <?php selected($correct, 'D'); ?>>D</option>
            </select>
        </div>
        
        <div class="solution-row">
            <label>Solution:</label>
            <textarea name="solution" rows="4" style="width:100%"><?php echo esc_textarea($solution); ?></textarea>
        </div>
    </div>
    <?php
}

function save_question_options($post_id) {
    if (!isset($_POST['question_options_nonce']) || 
        !wp_verify_nonce($_POST['question_options_nonce'], 'question_options_nonce')) {
        return;
    }
    
    if (defined('DOING_AUTOSAVE') && DOING_AUTOSAVE) return;
    if (!current_user_can('edit_post', $post_id)) return;
    
    if (isset($_POST['options'])) {
        update_post_meta($post_id, 'multiple_choice_options', $_POST['options']);
    }
    
    if (isset($_POST['correct_answer'])) {
        update_post_meta($post_id, 'correct_answer', sanitize_text_field($_POST['correct_answer']));
    }
    
    if (isset($_POST['solution'])) {
        update_post_meta($post_id, 'solution', sanitize_textarea_field($_POST['solution']));
    }
}
add_action('save_post_question', 'save_question_options');
```

#### Step 4.2: Class Management Metaboxes

```php
function add_class_meta_boxes() {
    // Subject/Topic
    add_meta_box(
        'class_subject_topic',
        'Subject and Topic',
        'render_class_subject_topic',
        'class',
        'side',
        'default'
    );
    
    // Exam Settings
    add_meta_box(
        'class_exam_settings',
        'Exam Settings',
        'render_class_exam_settings',
        'class',
        'side',
        'default'
    );
    
    // Student Management
    add_meta_box(
        'class_students',
        'Students',
        'render_class_students',
        'class',
        'normal',
        'default'
    );
    
    // Analytics
    add_meta_box(
        'class_analytics',
        'Class Analytics',
        'render_class_analytics',
        'class',
        'normal',
        'default'
    );
}
add_action('add_meta_boxes', 'add_class_meta_boxes');

function render_class_exam_settings($post) {
    wp_nonce_field('class_settings_nonce', 'class_settings_nonce');
    
    $num_questions = get_post_meta($post->ID, '_exam_num_questions', true) ?: 10;
    $time_per_question = get_post_meta($post->ID, '_exam_time_per_question', true) ?: 30;
    ?>
    <p>
        <label>Number of Questions:</label>
        <input type="number" name="_exam_num_questions" value="<?php echo esc_attr($num_questions); ?>" min="1" style="width:100%">
    </p>
    <p>
        <label>Time per Question (seconds):</label>
        <input type="number" name="_exam_time_per_question" value="<?php echo esc_attr($time_per_question); ?>" min="1" style="width:100%">
    </p>
    <?php
}

function render_class_analytics($post) {
    $stats = get_class_student_stats($post->ID);
    
    if (empty($stats)) {
        echo '<p>No student data yet.</p>';
        return;
    }
    
    echo '<table class="widefat">';
    echo '<thead><tr><th>Student</th><th>Accuracy</th><th>Sessions</th></tr></thead>';
    echo '<tbody>';
    
    foreach ($stats as $student) {
        $user = get_user_by('ID', $student['user_id']);
        echo '<tr>';
        echo '<td>' . esc_html($user->display_name) . '</td>';
        echo '<td>' . round($student['accuracy'] * 100, 2) . '%</td>';
        echo '<td>' . $student['sessions'] . '</td>';
        echo '</tr>';
    }
    
    echo '</tbody></table>';
}
```

---

## 4. SHORTCODE REFERENCE

| Shortcode | Parameters | Purpose | Source File |
|-----------|-----------|---------|-------------|
| `[display_classes]` | posts_per_page, order, orderby, search_term | Display class catalog | class-admin-enroll.php |
| `[enrollment_form]` | none | Student enrollment interface | class-admin-enroll.php |
| `[display_exam]` | id (via GET) | Take/view exam | class-display-exam.php |
| `[activity]` | id, class_id, cat (via GET) | Category-based activity | class-display-activity.php |
| `[display_exercise]` | id, class_id | Flashcard practice | class-display-exercise.php |
| `[display_review]` | id, class_id | Review past attempts | class-display-review.php |
| `[display_user_profile]` | none | Student dashboard | class-display-user.php |

---

## 5. AJAX ENDPOINTS

| Action | Function | Purpose |
|--------|----------|---------|
| `get_topics` | get_topics_ajax | Load topics based on subject |
| `get_classes_by_search` | get_classes_by_search | AJAX class search |
| `get_unused_enrollment_key` | get_unused_enrollment_key | Get free class key |
| `submit_flashcard_exam` | handle_exam_submission | Submit answers |
| `upload_json_file` | handle_json_upload | Import questions |
| `process_question_batch` | process_question_batch | Bulk question update |
| `fix_exam_answers_batch` | handle_fix_exam_answers_batch | Repair answer data |

---

## 6. CRITICAL BUSINESS LOGIC

### 6.1 Session ID Generation
```php
$session_id = time() . rand(1000, 9999);
// Example: 17040672001234
```

### 6.2 Enrollment Validation
```php
function is_user_enrolled($user_id, $class_id) {
    $enrolled_students = get_post_meta($class_id, 'enrolled_students', true) ?: [];
    $classes_enrolled = get_user_meta($user_id, 'classes_enrolled', true) ?: [];
    
    return in_array($user_id, $enrolled_students) || in_array($class_id, $classes_enrolled);
}
```

### 6.3 Access Control for Exams
```php
// Before showing exam
if (!is_user_enrolled(get_current_user_id(), $class_id)) {
    return 'You must be enrolled in this class to access the exam.';
}

// Check if already taken (for one-time exams)
$exam_results = get_post_meta($exam_id, 'exam_results', true);
foreach ($exam_results as $result) {
    if ($result['user_id'] == get_current_user_id()) {
        // Show statistics instead
        return display_exam_statistics_ui($exam_id, get_current_user_id(), $class_id);
    }
}
```

---

## 7. MIGRATION CHECKLIST

### Pre-Migration
- [ ] Backup existing database
- [ ] Export all questions to JSON (using admin tool)
- [ ] Export class keys structure
- [ ] Document current term IDs (especially "Exam" category)
- [ ] List all active enrollments

### During Migration
- [ ] Create wp_exam_answers table
- [ ] Register post types and taxonomies
- [ ] Create default terms (Exam, Free, Premium)
- [ ] Migrate question data (JSON import or direct SQL)
- [ ] Migrate answer data from user_meta to wp_exam_answers
- [ ] Transfer class_keys to new term_meta structure
- [ ] Verify enrollment mappings (post_meta + user_meta)

### Post-Migration
- [ ] Test enrollment flow (Free and Premium)
- [ ] Test exam submission and storage in wp_exam_answers
- [ ] Verify analytics calculations match old system
- [ ] Test admin interfaces (question management, class management)
- [ ] Validate Looksfam accuracy scores
- [ ] Check AJAX endpoints functionality
- [ ] Test image uploads in questions
- [ ] Verify shortcode outputs

---

## 8. PERFORMANCE CONSIDERATIONS

### Database Indexing
The wp_exam_answers table includes indexes on:
- `exam_id` - Fast exam filtering
- `class_id` - Fast class filtering
- `question_id` - Fast question analytics
- `user_id` - Fast user history
- `session_id` - Fast session retrieval
- `timestamp` - Time-based queries

### Batch Processing
- Answer submissions use batch INSERT (100 records at a time)
- Analytics calculations should use batch queries where possible
- Consider caching Looksfam scores (transient or object cache)

### Query Optimization
```php
// Good: Single query for multiple questions
function questionstatlooksfam_batch($question_ids, $user_id, $class_id = null) {
    // Single query with WHERE question_id IN (...)
    // Process results in PHP
}

// Avoid: N+1 queries
foreach ($question_ids as $qid) {
    questionstatlooksfam($qid, $user_id); // Bad!
}
```

---

## 9. SECURITY CONSIDERATIONS

### Nonce Verification
All form submissions and AJAX calls must verify nonces:
```php
// Frontend form
wp_nonce_field('flashcard_exam_submission_nonce', 'flashcard_exam_submission_nonce');

// AJAX handler
check_ajax_referer('flashcard_exam_submission_nonce', 'nonce');

// Admin save
if (!isset($_POST['question_options_nonce']) || 
    !wp_verify_nonce($_POST['question_options_nonce'], 'question_options_nonce')) {
    return;
}
```

### Capability Checks
```php
// Admin operations
if (!current_user_can('manage_options')) {
    return;
}

// Edit posts
if (!current_user_can('edit_post', $post_id)) {
    return;
}
```

### Data Sanitization
```php
// Text input
sanitize_text_field($_POST['input'])

// Text area
sanitize_textarea_field($_POST['textarea'])

// Integer
intval($_POST['number'])

// Output escaping
esc_html($variable)
esc_attr($variable)
esc_url($variable)
```

---

## 10. CONCLUSION

This migration guide covers all essential components of the Looksfam plugin:

**Core Tables**: Only ONE custom table (wp_exam_answers) is required
**Post Types**: 4 types (exam, question, class, transaction)
**Taxonomies**: 2 types (question_category, class)
**Key Features**: Enrollment, exam-taking, analytics with Looksfam algorithm
**Migration Path**: Clear steps for schema, data, and feature parity

The system is designed around WordPress conventions while maintaining a custom table for high-volume answer data. The Looksfam analytics algorithm provides unique insights into student performance beyond simple accuracy metrics.

For questions or clarifications during migration, refer to the original source files in the `looksfam_og` directory, particularly:
- `wordpress-plugin-template.php` - Core functions and table management
- `class-admin-enroll.php` - Enrollment logic
- `class-display-activity.php` - Exam interface and submission
- `class-admin-question.php` - Question management
- `class-admin-class.php` - Class management and analytics
