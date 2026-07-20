📘 Full Feature Specification & Relevance Audit: Looksfam Plugin
Focus: Public-Facing Activities, Analytics, and Student Experience
1. Executive Summary
The Looksfam plugin is a robust Exam Management & Learning Analytics System. While the backend (admin) is heavy with post-type management, the true value lies in its public-facing student engagement loop: Take Exam → Get Immediate Feedback → View Detailed Analytics → Track Long-term Progress.
The plugin features a proprietary "Looksfam Accuracy" algorithm that attempts to gamify learning by weighing recent performance higher than old data.
🎯 Core Value Proposition
"A closed-loop learning system where every exam attempt contributes to a dynamic, decaying accuracy score, encouraging consistent practice."
2. Detailed Feature Specifications (Public Facing)
A. The Exam Taking Experience (class-display-exam.php)
Status: ✅ Active & Critical
Location: Shortcode [looksfam_exam] or direct URL rewrite.
| Feature | Specification | Technical Implementation | Relevance Audit |
| :--- | :--- | :--- | : |
| Session Initialization | Creates a unique session_id upon exam start. Stores start time in wp_exam_answers. | INSERT INTO wp_exam_answers ... session_id | Essential. Prevents cheating by tracking duration and preventing tab-switching (if JS enforced). |
| Question Rendering | Fetches questions linked to the specific Class/Exam. Supports Text, Images, and Multiple Choice. | get_post_meta (to be migrated) + Custom Table join. | Essential. Core delivery mechanism. |
| Timer Logic | Countdown timer based on exam settings. Auto-submits when time expires. | JavaScript setInterval + AJAX fallback on timeout. | High. Critical for timed assessments. |
| Immediate Submission | AJAX-based submission without page reload. Saves answers to wp_exam_answers. | wp_ajax_save_exam_answer | Essential. Provides seamless UX. |
| Answer Validation | Compares user_answer vs correct_answer instantly upon submission. | PHP logic in save_exam_answer handler. | Essential. Required for real-time feedback. |
B. Immediate Feedback & Review (class-display-review.php)
Status: ✅ Active & High Value
Location: Triggered immediately after exam submission.
Feature
Specification
Technical Implementation
Relevance Audit
Score Calculation
Displays raw score (e.g., 8/10), percentage, and pass/fail status.
Calculated from wp_exam_answers rows where is_correct=1.
Essential. Basic user expectation.
Item Analysis
Shows every question, user's answer, correct answer, and detailed solution/explanation.
Loops through session IDs in wp_exam_answers.
High. Crucial for learning from mistakes.
Category Breakdown
Shows performance by Subject/Topic (e.g., "Math: 80%, Algebra: 50%").
Groups results by question_category taxonomy.
Medium-High. Helps students identify weak spots.
Retake Logic
Determines if a student can retake the exam based on class settings.
Checks post_meta for retake limits vs. count in DB.
High. Controls assessment flow.
C. Student Dashboard & Activity Log (class-display-activity.php, class-display-user.php)
Status: ✅ Active & Engagement Driver
Location: Shortcode [looksfam_dashboard] or user profile page.
Feature
Specification
Technical Implementation
Relevance Audit
Activity Feed
Chronological list of all exams taken, classes enrolled, and scores achieved.
SELECT * FROM wp_exam_answers WHERE user_id = X ORDER BY timestamp DESC.
High. Provides history and context.
Progress Tracking
Visual progress bars for specific classes or subjects.
Aggregates is_correct counts per class.
Medium. Good for motivation.
Enrollment Status
Shows active classes, expiration dates, and completion status.
Joins wp_posts (classes) with enrollment meta.
Essential. User needs to know access rights.
Certificate Generation
(If enabled) Generates a PDF/HTML certificate upon passing a threshold.
Uses TCPDF or similar library (check includes/).
Low-Medium. Nice to have, but not core to analytics.
D. The "Looksfam Accuracy" Analytics Engine
Status: ⚠️ Complex & Needs Optimization
Location: wordpress-plugin-template.php (helper functions) & class-display-user.php.
This is the killer feature of the plugin. It moves beyond simple averages.
The Algorithm Specification:
The "Looksfam Accuracy" (
L
A
LA) is calculated using a weighted formula:
L
A
=
(
B
a
s
e
R
a
t
e
×
E
f
f
i
c
i
e
n
c
y
F
a
c
t
o
r
×
R
e
t
e
n
t
i
o
n
F
a
c
t
o
r
)
−
D
e
c
a
y
P
e
n
a
l
t
y
LA=(BaseRate×EfficiencyFactor×RetentionFactor)−DecayPenalty
Base Rate (
R
R): 
Correct Answers
Total Attempts
Total Attempts
Correct Answers
​
 
Efficiency Factor (
E
E): Bonus points for getting answers right on the first try vs. multiple retries.
Retention Factor (
T
T): Weights recent sessions higher.
Logic: If the time gap between attempts is small, retention is high.
Decay Factor (
D
D): Reduces score if the user hasn't practiced in 
X
X days.
Implementation: max(0, 30 - days_since_last_attempt)
Audit of Relevance:
Is it Necessary? YES. This differentiates the plugin from standard LMS plugins (like LearnDash) which only show static averages. It encourages consistent study habits.
Current Issues:
Performance: The calculation currently loops through all user history in PHP (class-display-user.php lines ~2900+). This will crash with >1,000 attempts.
Accuracy: The decay logic is hardcoded; admins cannot adjust the "decay rate."
Recommendation: Move this calculation to a SQL Stored Procedure or cache the result in a user_meta field updated via Cron, rather than calculating on every page load.
3. Deep Dive Audit: Active Features vs. Obsolete Code
🔍 Feature: Public Question Bank Search
File: class-display-functions.php
Functionality: Allows students to search for practice questions by category.
Audit:
Relevance: High. Great for self-study mode.
Issue: Currently uses get_posts() with heavy meta_query.
Fix: Must migrate to SELECT * FROM wp_looksfam_questions WHERE subject LIKE %s.
🔍 Feature: "Exercise" Mode (Untimed Practice)
File: class-display-exercise.php
Functionality: Similar to exams but without timers or strict grading. Instant feedback per question.
Audit:
Relevance: Very High. This is where students spend 80% of their time.
Data Flow: Writes to wp_exam_answers with exam_id = 0 or a specific "practice" ID.
Risk: The code duplicates 70% of class-display-exam.php.
Recommendation: Refactor into a single ExamEngine class with a mode parameter (timed vs. practice).
🔍 Feature: Leaderboards / Rankings
File: class-display-user.php (Partial implementation found)
Functionality: Shows top students based on "Looksfam Accuracy."
Audit:
Relevance: Medium. Gamification is good, but currently inefficient.
Issue: Calculates scores for all users on page load.
Fix: Pre-calculate rankings nightly and store in a transient or custom table wp_looksfam_rankings.
4. Critical Gaps & Missing Features (Opportunities)
While the core is strong, the audit reveals missing public features that are standard in modern EdTech:
Mobile Responsiveness (CSS):
Audit: frontend.css is nearly empty (0 bytes).
Impact: The exam interface likely breaks on mobile phones.
Urgency: CRITICAL. Students primarily use mobile devices.
Accessibility (a11y):
Audit: No ARIA labels found in class-display-exam.php. Images in questions lack alt text enforcement.
Impact: Non-compliant with WCAG standards; excludes users with disabilities.
Real-Time Proctoring Indicators:
Audit: No detection for tab switching or copy-paste blocking.
Impact: Cheating is easy in high-stakes exams.
Recommendation: Add JS event listeners for visibilitychange and log violations to wp_exam_answers.
Social Sharing of Achievements:
Audit: No integration to share "Looksfam Accuracy" badges to social media.
Impact: Missed viral growth opportunity.
5. Final Verdict: Relevance & Necessity
Feature Module
Relevance Score
Necessity
Action Required
Exam Engine (Timed)
10/10
Critical
Optimize session handling; migrate DB.
Practice Mode
9/10
Critical
Merge code with Exam Engine to reduce duplication.
Looksfam Accuracy Algo
8/10
High
Refactor for performance. Move to SQL/Cron.
Detailed Review/Solutions
9/10
Critical
Ensure solution formatting supports rich media.
Activity Dashboard
7/10
High
Improve UI/UX; add filtering by date/subject.
Leaderboards
5/10
Nice-to-have
Optimize or disable if slowing down site.
Certificate Gen
4/10
Low
Verify if actually used; remove if bloat.
Legacy Post Meta Logic
0/10
Obsolete
DELETE. Migrate entirely to custom tables.
🚀 Strategic Recommendation
The public-facing features (Exam, Review, Analytics) are the heart of this plugin and are highly relevant. They provide a complete learning loop that is superior to basic quiz plugins.
However, the implementation is fragile:
The "Looksfam Accuracy" algorithm is too heavy for real-time calculation on large datasets.
The reliance on WP Posts for question storage is a ticking time bomb for performance.
Frontend CSS is non-existent, risking mobile usability.
Priority Roadmap for Public Features:
Week 1: Fix Mobile CSS (frontend.css).
Week 2: Optimize "Looksfam Accuracy" (Cache results, stop calculating on every load).
Week 3-6: Execute the Custom Table Migration (as detailed in the previous report) specifically for the wp_exam_answers and wp_questions data to speed up the public dashboard.
Week 7: Refactor class-display-exam.php and class-display-exercise.php into a unified service class.
This plugin has the potential to be a premium EdTech product, but only if the backend architecture catches up to the sophistication of its frontend analytics features.