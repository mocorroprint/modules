# Looksfam Plugin: Comprehensive Migration & Implementation Guide

## Executive Summary

This guide provides a step-by-step implementation plan to migrate the legacy `looksfam_og` plugin into a modern, scalable WordPress plugin architecture. The goal is to preserve all existing functionality (enrollment keys, subject selection, exam taking, multi-level analytics) while refactoring the codebase for maintainability, security, and performance.

**Migration Strategy:** "Strangler Fig Pattern" – We will build the new system alongside the old one, migrating data and functionality module by module, ensuring zero downtime.

---

## Phase 1: Database Schema & Data Architecture

### 1.1 Target Schema Design

We are moving away from ad-hoc meta queries to a structured relational schema where necessary, while leveraging WordPress core tables for extensibility.

#### A. Custom Table: `wp_lf_exam_answers`
*Purpose: High-performance storage for student responses. Replaces the legacy `wp_exam_answers`.*

```sql
CREATE TABLE wp_lf_exam_answers (
    id BIGINT(20) UNSIGNED NOT NULL AUTO_INCREMENT,
    user_id BIGINT(20) UNSIGNED NOT NULL,
    exam_id BIGINT(20) UNSIGNED NOT NULL,
    question_id BIGINT(20) UNSIGNED NOT NULL,
    answer_text LONGTEXT,
    is_correct TINYINT(1) DEFAULT 0,
    points_earned DECIMAL(10,2) DEFAULT 0.00,
    time_taken INT(11) DEFAULT 0, -- seconds
    session_id VARCHAR(64) NOT NULL, -- Groups answers from one sitting
    submitted_at DATETIME NOT NULL,
    metadata LONGTEXT, -- JSON blob for extra data (flags, hints used)
    PRIMARY KEY (id),
    KEY user_id (user_id),
    KEY exam_id (exam_id),
    KEY question_id (question_id),
    KEY session_id (session_id),
    KEY submitted_at (submitted_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4 COLLATE=utf8mb4_unicode_ci;
```

#### B. WordPress Core Utilization (No Custom Tables Needed)

| Legacy Concept | New Implementation | Storage Location |
| :--- | :--- | :--- |
| **Questions** | Custom Post Type `lf_question` | `wp_posts` + `wp_postmeta` |
| **Exams/Activities** | Custom Post Type `lf_exam` | `wp_posts` + `wp_postmeta` |
| **Subjects/Categories** | Taxonomy `lf_subject` | `wp_terms` + `wp_term_taxonomy` |
| **Classes/Sections** | Custom Post Type `lf_class` | `wp_posts` + `wp_postmeta` |
| **Enrollment Keys** | Term Meta on `lf_class` | `wp_termmeta` (`lf_enrollment_key`) |
| **Student Enrollment** | User Meta | `wp_usermeta` (`lf_enrolled_classes`) |
| **Exam Results/Score** | Post Meta on `lf_exam` (aggregated) or Custom Table | `wp_postmeta` / `wp_lf_exam_answers` |

### 1.2 Data Migration Scripts

Create a migration runner class `LF_Migration_Runner`. This should be run once via WP-CLI or a secure admin page.

#### Step 1: Migrate Questions
Legacy questions might be in a custom table or messy post meta. We normalize them.

```php
public function migrate_questions() {
    global $wpdb;
    $legacy_questions = $wpdb->get_results("SELECT * FROM wp_custom_questions"); // Hypothetical legacy table

    foreach ($legacy_questions as $q) {
        $post_id = wp_insert_post([
            'post_title'   => $q->question_text,
            'post_content' => $q->explanation ?? '',
            'post_type'    => 'lf_question',
            'post_status'  => 'publish',
        ]);

        // Migrate Options
        if (!empty($q->options)) {
            update_post_meta($post_id, '_lf_options', maybe_unserialize($q->options));
        }
        
        // Migrate Category
        if ($q->category_id) {
            wp_set_object_terms($post_id, (int)$q->category_id, 'lf_subject');
        }
    }
}
```

#### Step 2: Migrate Exam Answers (The Critical Data)
Preserving student history is vital.

```php
public function migrate_exam_answers() {
    global $wpdb;
    $table_name = $wpdb->prefix . 'lf_exam_answers';
    
    // Create table if not exists (run dbDelta here)
    
    $legacy_answers = $wpdb->get_results("SELECT * FROM wp_exam_answers");
    
    $inserts = [];
    foreach ($legacy_answers as $ans) {
        $inserts[] = $wpdb->prepare(
            "(%d, %d, %d, %s, %d, %f, %d, %s, %s, %s)",
            $ans->user_id,
            $ans->exam_id,
            $ans->question_id,
            $ans->answer,
            $ans->is_correct,
            $ans->score,
            $ans->time_taken,
            uniqid('mig_'), // Generate new session ID for legacy data
            $ans->timestamp,
            json_encode(['migrated' => true, 'legacy_id' => $ans->id])
        );
    }

    if (!empty($inserts)) {
        $sql = "INSERT INTO {$table_name} (user_id, exam_id, question_id, answer_text, is_correct, points_earned, time_taken, session_id, submitted_at, metadata) VALUES ";
        $sql .= implode(',', $inserts);
        $wpdb->query($sql);
    }
}
```

#### Step 3: Migrate Enrollment Keys & Classes
Legacy keys are often stored in option tables or term meta.

```php
public function migrate_classes_and_keys() {
    // Assume legacy classes are in wp_posts with post_type='class'
    $classes = get_posts(['post_type' => 'class', 'numberposts' => -1]);
    
    foreach ($classes as $class) {
        // Create new CPT
        $new_id = wp_insert_post([
            'post_title' => $class->post_title,
            'post_type'  => 'lf_class',
            'post_status'=> 'publish'
        ]);

        // Migrate Key from legacy meta
        $old_key = get_post_meta($class->ID, '_class_key', true);
        if ($old_key) {
            update_term_meta($new_id, '_lf_enrollment_key', $old_key); 
            // Note: If classes were terms, adjust accordingly. 
            // Best practice: Classes are CPT, Keys are Post Meta on CPT.
            update_post_meta($new_id, '_lf_enrollment_key', $old_key);
        }
    }
}
```

---

## Phase 2: Core Architecture & Registration

### 2.1 Plugin Boilerplate Structure

```text
looksfam-pro/
├── looksfam-pro.php          # Main entry point
├── includes/
│   ├── class-lf-post-types.php
│   ├── class-lf-taxonomies.php
│   ├── class-lf-database.php
│   ├── class-lf-enrollment.php
│   ├── class-lf-exam-engine.php
│   └── class-lf-analytics.php
├── assets/
│   ├── js/
│   │   ├── student-exam.js
│   │   └── admin-analytics.js
│   └── css/
├── templates/
│   ├── student-dashboard.php
│   ├── exam-interface.php
│   └── analytics-view.php
└── migrations/
    └── class-lf-migration-runner.php
```

### 2.2 Registering Post Types & Taxonomies

**File:** `includes/class-lf-post-types.php`

```php
class LF_Post_Types {
    public static function init() {
        add_action('init', [self::class, 'register_question_cpt']);
        add_action('init', [self::class, 'register_exam_cpt']);
        add_action('init', [self::class, 'register_class_cpt']);
        add_action('init', [self::class, 'register_subject_taxonomy']);
    }

    public static function register_question_cpt() {
        register_post_type('lf_question', [
            'labels' => ['name' => 'Questions', 'singular_name' => 'Question'],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'looksfam-dashboard',
            'supports' => ['title', 'editor'],
            'menu_icon' => 'dashicons-editor-help',
        ]);
    }

    public static function register_exam_cpt() {
        register_post_type('lf_exam', [
            'labels' => ['name' => 'Exams/Activities', 'singular_name' => 'Exam'],
            'public' => false,
            'show_ui' => true,
            'show_in_menu' => 'looksfam-dashboard',
            'supports' => ['title', 'editor'],
            'menu_icon' => 'dashicons-welcome-learn-more',
        ]);
    }

    public static function register_subject_taxonomy() {
        register_taxonomy('lf_subject', ['lf_question', 'lf_exam'], [
            'labels' => ['name' => 'Subjects'],
            'hierarchical' => true,
            'show_ui' => true,
            'show_admin_column' => true,
            'query_var' => true,
            'rewrite' => ['slug' => 'subject'],
        ]);
    }
}
```

---

## Phase 3: Feature Implementation - Student Side

### 3.1 Enrollment System (Keys & Subjects)

**Logic:**
1. Student enters a Class Key.
2. System validates key against `lf_class` posts meta `_lf_enrollment_key`.
3. If valid, add Class ID to User Meta `lf_enrolled_classes`.
4. Redirect to Dashboard.

**AJAX Handler:** `includes/class-lf-enrollment.php`

```php
public function ajax_enroll_student() {
    check_ajax_referer('lf_enroll_nonce', 'security');
    
    $user_id = get_current_user_id();
    $key = sanitize_text_field($_POST['enrollment_key']);
    
    // Find class by key
    $args = [
        'post_type' => 'lf_class',
        'meta_query' => [
            [
                'key' => '_lf_enrollment_key',
                'value' => $key,
                'compare' => '='
            ]
        ]
    ];
    $query = new WP_Query($args);
    
    if ($query->have_posts()) {
        $class_id = $query->posts[0]->ID;
        
        // Add to user meta (array of class IDs)
        $enrolled = get_user_meta($user_id, 'lf_enrolled_classes', true);
        if (!is_array($enrolled)) $enrolled = [];
        
        if (!in_array($class_id, $enrolled)) {
            $enrolled[] = $class_id;
            update_user_meta($user_id, 'lf_enrolled_classes', $enrolled);
        }
        
        wp_send_json_success(['message' => 'Enrolled successfully!', 'redirect' => get_permalink($class_id)]);
    } else {
        wp_send_json_error(['message' => 'Invalid enrollment key.']);
    }
}
```

### 3.2 Exam Interface & Taking Logic

**Frontend:** `assets/js/student-exam.js`
- Uses local storage to auto-save answers every 30s.
- Tracks time per question.
- Prevents navigation away (optional).

**Backend Processing:** `includes/class-lf-exam-engine.php`

```php
public function submit_exam_answer($exam_id, $question_id, $answer, $session_id) {
    global $wpdb;
    $table = $wpdb->prefix . 'lf_exam_answers';
    $user_id = get_current_user_id();
    
    // Fetch Correct Answer
    $correct_answer = get_post_meta($question_id, '_lf_correct_answer', true);
    $options = get_post_meta($question_id, '_lf_options', true);
    
    $is_correct = 0;
    $points = 0;
    
    // Grading Logic
    if (is_array($correct_answer)) {
        // Multi-select logic
        $is_correct = (empty(array_diff($correct_answer, $answer))) ? 1 : 0;
    } else {
        $is_correct = ($answer === $correct_answer) ? 1 : 0;
    }
    
    if ($is_correct) {
        $points = get_post_meta($exam_id, '_lf_points_per_question', true) ?: 1;
    }
    
    $wpdb->insert($table, [
        'user_id' => $user_id,
        'exam_id' => $exam_id,
        'question_id' => $question_id,
        'answer_text' => maybe_serialize($answer),
        'is_correct' => $is_correct,
        'points_earned' => $points,
        'time_taken' => $_POST['time_taken'] ?? 0,
        'session_id' => $session_id,
        'submitted_at' => current_time('mysql')
    ]);
    
    return $wpdb->insert_id;
}
```

### 3.3 Analytics (Student View)

**Metrics:**
1.  **Sitewide:** Total Exams Taken, Average Accuracy.
2.  **Subject:** Breakdown by `lf_subject`.
3.  **Class:** Performance relative to class average.

**Query Example (Class LF_Analytics):**

```php
public function get_student_analytics($user_id, $scope = 'sitewide') {
    global $wpdb;
    $table = $wpdb->prefix . 'lf_exam_answers';
    
    $where_clause = "user_id = %d";
    $params = [$user_id];
    
    if ($scope === 'subject') {
        // Join with posts to get taxonomy
        $sql = "SELECT t.name as subject, COUNT(a.id) as total, SUM(a.is_correct) as correct
                FROM {$table} a
                JOIN {$wpdb->posts} q ON a.question_id = q.ID
                JOIN {$wpdb->term_relationships} tr ON q.ID = tr.object_id
                JOIN {$wpdb->term_taxonomy} tt ON tr.term_taxonomy_id = tt.term_taxonomy_id
                JOIN {$wpdb->terms} t ON tt.term_id = t.term_id
                WHERE a.user_id = %d AND tt.taxonomy = 'lf_subject'
                GROUP BY t.term_id";
        return $wpdb->get_results($wpdb->prepare($sql, $user_id));
    }
    
    // Default Sitewide
    $sql = "SELECT COUNT(*) as total_questions, SUM(is_correct) as total_correct, SUM(points_earned) as total_points
            FROM {$table} WHERE {$where_clause}";
            
    return $wpdb->get_row($wpdb->prepare($sql, $params));
}
```

---

## Phase 4: Feature Implementation - Admin Side

### 4.1 Question Management (JSON Import)

Allow admins to bulk upload questions via JSON.

**JSON Structure:**
```json
[
  {
    "question": "What is 2+2?",
    "type": "multiple_choice",
    "options": ["3", "4", "5"],
    "correct": "4",
    "subject": "Math",
    "explanation": "Basic arithmetic."
  }
]
```

**Importer Logic:**
1. Decode JSON.
2. Loop through items.
3. `wp_insert_post` for `lf_question`.
4. `wp_set_object_terms` for subject.
5. `update_post_meta` for options/answer.

### 4.2 Exam Configuration

Admin UI to create an "Exam":
1. Select Subject(s).
2. Set number of questions (random pull) OR manually select specific IDs.
3. Set time limit.
4. Set visibility (Specific Classes or Public).

**Meta Box Save Logic:**
```php
public function save_exam_meta($post_id) {
    if (isset($_POST['lf_selected_questions'])) {
        update_post_meta($post_id, '_lf_selected_questions', array_map('intval', $_POST['lf_selected_questions']));
    }
    if (isset($_POST['lf_time_limit'])) {
        update_post_meta($post_id, '_lf_time_limit', intval($_POST['lf_time_limit']));
    }
}
```

### 4.3 Advanced Analytics (Admin View)

**Looksfam Algorithm Implementation:**
The legacy algorithm uses: *Accuracy + Session Efficiency + Retention Factor - Decay*.

```php
class LF_Algorithm {
    public static function calculate_score($user_id, $exam_id = null) {
        global $wpdb;
        $table = $wpdb->prefix . 'lf_exam_answers';
        
        // 1. Base Accuracy
        $stats = self::get_raw_stats($user_id, $exam_id);
        $accuracy = $stats->total_correct / $stats->total_questions;
        
        // 2. Efficiency (Time taken vs Average time)
        // Lower time with high accuracy = higher efficiency
        $avg_time = self::get_global_average_time($exam_id);
        $user_time = $stats->avg_time;
        $efficiency = min(1.5, ($avg_time / $user_time)); // Cap at 1.5x
        
        // 3. Retention (Performance over time - requires historical query)
        $retention = self::calculate_retention_factor($user_id);
        
        // 4. Decay (Penalty for inactivity)
        $last_active = self::get_last_activity_date($user_id);
        $decay = self::calculate_decay($last_active);
        
        $final_score = (($accuracy * 0.5) + ($efficiency * 0.2) + ($retention * 0.3)) - $decay;
        
        return max(0, min(100, $final_score * 100)); // Normalize to 0-100
    }
}
```

---

## Phase 5: Security & Optimization

### 5.1 Security Measures
1.  **Nonces:** All AJAX actions (`enroll`, `submit_answer`, `save_admin_settings`) must verify nonces.
2.  **Capabilities:** Wrap admin menus and processing in `current_user_can('manage_lf_exams')`.
3.  **SQL Injection:** Use `$wpdb->prepare()` for ALL custom table queries.
4.  **XSS:** Escape all output in templates using `esc_html()`, `esc_attr()`, `wp_kses_post()`.
5.  **Direct File Access:** Add `defined('ABSPATH') || exit;` to every PHP file.

### 5.2 Performance Optimization
1.  **Indexing:** Ensure `user_id`, `exam_id`, and `session_id` are indexed in `wp_lf_exam_answers`.
2.  **Caching:**
    *   Cache student analytics results (transients) for 5 minutes.
    *   Cache "Class Key" lookups.
3.  **Batch Processing:** When importing thousands of questions, use `wp_schedule_single_event` to process in batches to avoid PHP timeout.

---

## Phase 6: Migration Checklist & Rollout Plan

### Pre-Migration
- [ ] Full Database Backup.
- [ ] Staging Environment Setup (Clone of production).
- [ ] Install new plugin structure (deactivated) on staging.

### Execution (Staging)
- [ ] Run `LF_Migration_Runner` via WP-CLI: `wp lf migrate all`.
- [ ] Verify Row Counts: Compare `COUNT(*)` of legacy tables vs new tables.
- [ ] Spot Check: Randomly select 5 users, verify their enrollment and past scores match exactly.
- [ ] Test Enrollment: Generate a new key, try to enroll a test user.
- [ ] Test Exam: Take a new exam, verify answer recording and score calculation.
- [ ] Test Analytics: Compare legacy dashboard numbers with new dashboard numbers.

### Production Rollout
1.  **Maintenance Mode:** Enable brief maintenance window if data volume is huge (>100k rows).
2.  **Deploy Code:** Upload new plugin files.
3.  **Run Migration:** Execute migration script.
4.  **Verification:** Run automated sanity checks.
5.  **Go Live:** Disable maintenance mode.
6.  **Legacy Cleanup:** Do NOT delete legacy tables immediately. Keep them for 30 days as a fallback. Rename them with `_backup` suffix.

### Post-Migration Monitoring
- Monitor error logs for SQL errors.
- Watch server load during peak exam hours.
- Gather user feedback on UI/UX changes.

---

## Appendix: Shortcode & Template Tags

For easy integration into any theme:

```php
// [lf_student_dashboard]
function lf_render_student_dashboard() {
    if (!is_user_logged_in()) return 'Please log in.';
    ob_start();
    include LF_PLUGIN_DIR . 'templates/student-dashboard.php';
    return ob_get_clean();
}
add_shortcode('lf_student_dashboard', 'lf_render_student_dashboard');

// [lf_exam_runner id="123"]
function lf_render_exam($atts) {
    $atts = shortcode_atts(['id' => 0], $atts);
    if (!$atts['id']) return 'Exam ID missing.';
    ob_start();
    include LF_PLUGIN_DIR . 'templates/exam-interface.php';
    return ob_get_clean();
}
add_shortcode('lf_exam_runner', 'lf_render_exam');
```

This guide covers the full spectrum from database schema to frontend interaction, ensuring a robust, future-proof migration of the Looksfam platform.
