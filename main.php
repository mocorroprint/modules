<?php
/**
 * Module Name: Finance Tracker
 * Module Slug: ft
 * Description: Personal finance tracker — wallets, transactions, budgets, categories, goals, insights. Mobile-first PWA with offline support.
 * Version: 2.0.0
 * Author: BNTM
 */

if (!defined('ABSPATH')) exit;

define('BNTM_FT_PATH', dirname(__FILE__) . '/');
define('BNTM_FT_URL', plugin_dir_url(__FILE__));
define('BNTM_FT_PRO_PRICE', 199);
define('BNTM_FT_SW_VERSION', '2.0.0');

function ft_default_pro_features() {
    return [
        'wallets'      => ['title' => 'Unlimited Wallets', 'desc' => 'Free plan starts with limited wallets.', 'color' => '#ef4444', 'enabled' => 1],
        'budgets'      => ['title' => 'Budgets & Budget Alerts', 'desc' => 'Unlimited budgets with spending alerts.', 'color' => '#f59e0b', 'enabled' => 1],
        'goals'        => ['title' => 'Savings Goals & Allocations', 'desc' => 'Track and allocate toward any goal.', 'color' => '#10b981', 'enabled' => 1],
        'insights'     => ['title' => 'Full Insights & Charts', 'desc' => 'Spending charts, health score, and trends.', 'color' => '#3b82f6', 'enabled' => 1],
        'recurring'    => ['title' => 'Recurring Transactions', 'desc' => 'Auto-post salary, bills, and subscriptions.', 'color' => '#8b5cf6', 'enabled' => 1],
        'offline'      => ['title' => 'Offline Mode', 'desc' => 'Queue writes while offline and sync later.', 'color' => '#ec4899', 'enabled' => 1],
        'history'      => ['title' => 'Full Transaction History', 'desc' => 'Access all your history anytime.', 'color' => '#f97316', 'enabled' => 1],
    ];
}

function ft_get_pro_price() {
    return max(0, (float) get_option('ft_pro_price', BNTM_FT_PRO_PRICE));
}

function ft_get_free_wallet_limit() {
    return max(0, (int) get_option('ft_free_wallet_limit', 1));
}

function ft_get_free_category_limit() {
    return max(0, (int) get_option('ft_free_category_limit', 3));
}

function ft_get_home_transaction_limit() {
    return max(1, min(30, (int) get_option('ft_home_transaction_limit', 5)));
}

function ft_get_pro_features() {
    $defaults = ft_default_pro_features();
    $saved = get_option('ft_pro_features', []);
    if (!is_array($saved)) $saved = [];
    foreach ($defaults as $key => $feature) {
        if (!isset($saved[$key]) || !is_array($saved[$key])) $saved[$key] = [];
        $saved[$key] = array_merge($feature, $saved[$key]);
        $saved[$key]['enabled'] = !empty($saved[$key]['enabled']) ? 1 : 0;
    }
    return $saved;
}

// ============================================================
// MODULE CONFIGURATION
// ============================================================

function bntm_ft_get_pages() {
    return [
        'Finance Tracker'       => '[ft_app]',
        'Finance Tracker Admin' => '[ft_dashboard]',
    ];
}

function bntm_ft_get_tables() {
    global $wpdb;
    $charset = $wpdb->get_charset_collate();
    $p       = $wpdb->prefix;
    return [
        'ft_wallets' => "CREATE TABLE {$p}ft_wallets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            name VARCHAR(100) NOT NULL,
            type ENUM('cash','bank','ewallet','credit') NOT NULL DEFAULT 'cash',
            balance DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            color VARCHAR(20) DEFAULT '#6366f1',
            icon VARCHAR(50) DEFAULT 'wallet',
            include_in_total TINYINT(1) DEFAULT 1,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id), INDEX idx_user (user_id)
        ) {$charset};",
        'ft_categories' => "CREATE TABLE {$p}ft_categories (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            name VARCHAR(100) NOT NULL,
            parent_id BIGINT UNSIGNED DEFAULT NULL,
            type ENUM('expense','income','both') NOT NULL DEFAULT 'expense',
            icon VARCHAR(50) DEFAULT 'tag',
            color VARCHAR(20) DEFAULT '#6366f1',
            sort_order INT DEFAULT 0,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id), INDEX idx_user (user_id)
        ) {$charset};",
        'ft_transactions' => "CREATE TABLE {$p}ft_transactions (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            type ENUM('expense','income','transfer','adjustment') NOT NULL DEFAULT 'expense',
            amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            wallet_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            to_wallet_id BIGINT UNSIGNED DEFAULT NULL,
            category_id BIGINT UNSIGNED DEFAULT NULL,
            note TEXT DEFAULT NULL,
            tags VARCHAR(500) DEFAULT NULL,
            transaction_date DATE NOT NULL,
            is_recurring TINYINT(1) DEFAULT 0,
            realize_date DATE DEFAULT NULL,
            is_realized TINYINT(1) DEFAULT 1,
            recurrence_id BIGINT UNSIGNED DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id), INDEX idx_user (user_id),
            INDEX idx_date (transaction_date), INDEX idx_wallet (wallet_id), INDEX idx_category (category_id)
        ) {$charset};",
        'ft_budgets' => "CREATE TABLE {$p}ft_budgets (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            category_id BIGINT UNSIGNED DEFAULT NULL,
            name VARCHAR(100) NOT NULL,
            amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            period ENUM('monthly','weekly','yearly') DEFAULT 'monthly',
            rollover TINYINT(1) DEFAULT 0,
            alert_at INT DEFAULT 80,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id), INDEX idx_user (user_id)
        ) {$charset};",
        'ft_goals' => "CREATE TABLE {$p}ft_goals (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            name VARCHAR(100) NOT NULL,
            target_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            current_amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            deadline DATE DEFAULT NULL,
            icon VARCHAR(50) DEFAULT 'target',
            color VARCHAR(20) DEFAULT '#10b981',
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id), INDEX idx_user (user_id)
        ) {$charset};",
        'ft_goal_allocations' => "CREATE TABLE {$p}ft_goal_allocations (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            goal_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            wallet_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            note TEXT DEFAULT NULL,
            status VARCHAR(50) NOT NULL DEFAULT 'unrealized',
            plan_date DATE DEFAULT NULL,
            realize_date DATE DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            INDEX idx_user (user_id), INDEX idx_goal (goal_id)
        ) {$charset};",
        'ft_recurrences' => "CREATE TABLE {$p}ft_recurrences (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            rand_id VARCHAR(20) UNIQUE NOT NULL,
            business_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            name VARCHAR(100) NOT NULL,
            type ENUM('expense','income') DEFAULT 'expense',
            amount DECIMAL(15,2) NOT NULL DEFAULT 0.00,
            wallet_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            category_id BIGINT UNSIGNED DEFAULT NULL,
            frequency ENUM('daily','weekly','monthly','yearly') DEFAULT 'monthly',
            next_date DATE NOT NULL,
            note TEXT DEFAULT NULL,
            auto_post TINYINT(1) DEFAULT 0,
            status VARCHAR(50) NOT NULL DEFAULT 'active',
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_business (business_id), INDEX idx_user (user_id)
        ) {$charset};",
        'ft_licenses' => "CREATE TABLE {$p}ft_licenses (
            id BIGINT UNSIGNED NOT NULL AUTO_INCREMENT PRIMARY KEY,
            user_id BIGINT UNSIGNED NOT NULL DEFAULT 0,
            license_key VARCHAR(64) UNIQUE NOT NULL,
            plan VARCHAR(20) NOT NULL DEFAULT 'pro',
            status VARCHAR(20) NOT NULL DEFAULT 'pending',
            payment_id VARCHAR(128) DEFAULT NULL,
            amount DECIMAL(10,2) DEFAULT 0.00,
            currency VARCHAR(10) DEFAULT 'PHP',
            purchased_at DATETIME DEFAULT NULL,
            created_at DATETIME DEFAULT CURRENT_TIMESTAMP,
            updated_at DATETIME DEFAULT CURRENT_TIMESTAMP ON UPDATE CURRENT_TIMESTAMP,
            INDEX idx_user (user_id)
        ) {$charset};",
    ];
}

function bntm_ft_get_shortcodes() {
    return [
        'ft_app'       => 'bntm_shortcode_ft_app',
        'ft_dashboard' => 'bntm_shortcode_ft_dashboard',
    ];
}

function bntm_ft_create_tables() {
    require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
    foreach (bntm_ft_get_tables() as $sql) dbDelta($sql);
    ft_seed_default_categories();
}

function bntm_ft_maybe_upgrade_schema() {
    global $wpdb;
    $table = $wpdb->prefix . 'ft_goal_allocations';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $table)) !== $table) return;
    foreach (['plan_date' => "ADD COLUMN plan_date DATE DEFAULT NULL AFTER status", 'realize_date' => "ADD COLUMN realize_date DATE DEFAULT NULL AFTER plan_date"] as $col => $sql) {
        if (!$wpdb->get_var("SHOW COLUMNS FROM {$table} LIKE '{$col}'"))
            $wpdb->query("ALTER TABLE {$table} {$sql}");
    }
    // Ensure ft_licenses exists
    $lt = $wpdb->prefix . 'ft_licenses';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s", $lt)) !== $lt) {
        require_once(ABSPATH . 'wp-admin/includes/upgrade.php');
        dbDelta(bntm_ft_get_tables()['ft_licenses']);
    }
}

function ft_seed_default_categories() {
    global $wpdb;
    $table = $wpdb->prefix . 'ft_categories';
    if ($wpdb->get_var("SELECT COUNT(*) FROM {$table} WHERE user_id=0 AND business_id=0") > 0) return;
    $defaults = [
        ['Food & Drink','expense','utensils','#f59e0b'],['Transport','expense','car','#3b82f6'],
        ['Shopping','expense','bag','#ec4899'],['Entertainment','expense','film','#8b5cf6'],
        ['Health','expense','heart','#ef4444'],['Bills & Utilities','expense','zap','#f97316'],
        ['Education','expense','book','#06b6d4'],['Travel','expense','map-pin','#10b981'],
        ['Salary','income','briefcase','#22c55e'],['Freelance','income','code','#14b8a6'],
        ['Investment','income','trending-up','#6366f1'],['Gift','income','gift','#f43f5e'],
    ];
    foreach ($defaults as $d) {
        $wpdb->insert($table, ['rand_id'=>bntm_rand_id(),'business_id'=>0,'user_id'=>0,'name'=>$d[0],'type'=>$d[1],'icon'=>$d[2],'color'=>$d[3],'status'=>'active'], ['%s','%d','%d','%s','%s','%s','%s','%s']);
    }
}

// ============================================================
// PRO HELPERS
// ============================================================

function ft_is_pro($user_id = null) {
    if (!$user_id) $user_id = get_current_user_id();
    if (user_can($user_id, 'manage_options')) return true; // admins always pro
    return (bool) get_user_meta($user_id, 'ft_pro_active', true);
}

function ft_encrypt_key($value) {
    if (empty($value)) return '';
    $key = defined('AUTH_KEY') ? AUTH_KEY : 'ft_fallback_key_32chars_padded!!';
    $iv  = openssl_random_pseudo_bytes(16);
    $enc = openssl_encrypt($value, 'AES-256-CBC', substr(hash('sha256', $key), 0, 32), 0, $iv);
    return base64_encode($iv . '::' . $enc);
}

function ft_decrypt_key($value) {
    if (empty($value)) return '';
    $key   = defined('AUTH_KEY') ? AUTH_KEY : 'ft_fallback_key_32chars_padded!!';
    $parts = explode('::', base64_decode($value), 2);
    if (count($parts) !== 2) return '';
    [$iv, $enc] = $parts;
    return openssl_decrypt($enc, 'AES-256-CBC', substr(hash('sha256', $key), 0, 32), 0, $iv);
}

function ft_get_maya_config() {
    return [
        'mode'       => get_option('ft_maya_mode', 'sandbox'),
        'public_key' => ft_decrypt_key(get_option('ft_maya_public_key_enc', '')),
        'secret_key' => ft_decrypt_key(get_option('ft_maya_secret_key_enc', '')),
    ];
}

function ft_generate_license_key() {
    return strtoupper(bin2hex(random_bytes(16)));
}

// ============================================================
// PWA: SERVICE WORKER + MANIFEST ENDPOINTS
// ============================================================

add_action('init', function () {
    add_rewrite_rule('^ft-sw\.js$', 'index.php?ft_sw=1', 'top');
    add_rewrite_rule('^ft-manifest\.json$', 'index.php?ft_manifest=1', 'top');
});
add_filter('query_vars', function ($vars) { $vars[] = 'ft_sw'; $vars[] = 'ft_manifest'; return $vars; });

add_action('template_redirect', function () {
    if (get_query_var('ft_manifest') || isset($_GET['ft_manifest'])) {
        $page = get_page_by_path('finance-tracker');
        $start_url = $page ? get_permalink($page->ID) : home_url('/finance-tracker/');
        header('Content-Type: application/manifest+json');
        header('Cache-Control: public, max-age=86400');
        echo json_encode([
            'name'             => 'Finance Tracker',
            'short_name'       => 'FinTrack',
            'description'      => 'Personal Finance Tracker',
            'start_url'        => $start_url,
            'scope'            => $start_url,
            'display'          => 'standalone',
            'orientation'      => 'portrait',
            'background_color' => '#080810',
            'theme_color'      => '#ef4444',
            'icons'            => [
                ['src' => BNTM_FT_URL . 'icons/icon-192.png', 'sizes' => '192x192', 'type' => 'image/png', 'purpose' => 'any maskable'],
                ['src' => BNTM_FT_URL . 'icons/icon-512.png', 'sizes' => '512x512', 'type' => 'image/png', 'purpose' => 'any maskable'],
            ],
            'categories'       => ['finance', 'productivity'],
            'lang'             => 'en',
            'dir'              => 'ltr',
        ]);
        exit;
    }

    if (get_query_var('ft_sw') || isset($_GET['ft_sw'])) {
        $v        = BNTM_FT_SW_VERSION;
        $ajax_url = admin_url('admin-ajax.php');
        header('Content-Type: application/javascript');
        header('Cache-Control: no-cache, no-store');
        echo <<<JS
const FT_CACHE = 'ft-app-v{$v}';
const FT_STATIC = [
  self.registration.scope,
  'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&family=JetBrains+Mono:wght@400;600;700&display=swap',
];

self.addEventListener('install', e => {
  e.waitUntil(
    caches.open(FT_CACHE).then(c => c.addAll(FT_STATIC)).then(() => self.skipWaiting())
  );
});
self.addEventListener('activate', e => {
  e.waitUntil(
    caches.keys().then(keys => Promise.all(
      keys.filter(k => k !== FT_CACHE).map(k => caches.delete(k))
    )).then(() => self.clients.claim())
  );
});
self.addEventListener('fetch', e => {
  const url = new URL(e.request.url);
  // Never intercept AJAX or non-GET except page shell
  if (url.href.includes('admin-ajax.php')) return;
  if (e.request.method !== 'GET') return;
  // For navigation (app shell) — network first, cache fallback
  if (e.request.mode === 'navigate') {
    e.respondWith(
      fetch(e.request).then(r => {
        const clone = r.clone();
        caches.open(FT_CACHE).then(c => c.put(e.request, clone));
        return r;
      }).catch(() => caches.match(e.request))
    );
    return;
  }
  // Static assets — cache first
  e.respondWith(
    caches.match(e.request).then(cached => cached || fetch(e.request).then(r => {
      if (r && r.status === 200) {
        const clone = r.clone();
        caches.open(FT_CACHE).then(c => c.put(e.request, clone));
      }
      return r;
    }))
  );
});
// Receive sync message from client
self.addEventListener('message', e => {
  if (e.data === 'skipWaiting') self.skipWaiting();
});
JS;
        exit;
    }
});

// ============================================================
// ADMIN DASHBOARD REDIRECT: non-admins → app page
// ============================================================

add_action('template_redirect', function () {
    global $post;
    if (!$post) return;
    if (has_shortcode($post->post_content, 'ft_dashboard') && !current_user_can('manage_options')) {
        $app = get_page_by_path('finance-tracker');
        wp_safe_redirect($app ? get_permalink($app->ID) : home_url('/'));
        exit;
    }
});

function ft_is_app_page($post = null) {
    if (!$post) $post = get_post();
    return $post && isset($post->post_content) && has_shortcode($post->post_content, 'ft_app');
}

function ft_get_pwa_manifest_url() {
    return add_query_arg('ft_manifest', '1', home_url('/'));
}

function ft_get_pwa_sw_url() {
    return add_query_arg('ft_sw', '1', home_url('/'));
}

function ft_get_pwa_head_markup() {
    $manifest_url = ft_get_pwa_manifest_url();
    $theme_color = '#ef4444';
    $font_url = 'https://fonts.googleapis.com/css2?family=DM+Sans:ital,opsz,wght@0,9..40,300;0,9..40,400;0,9..40,500;0,9..40,600;0,9..40,700;0,9..40,800&family=JetBrains+Mono:wght@400;600;700&display=swap';
    return implode("\n", [
        '<meta name="mobile-web-app-capable" content="yes">',
        '<meta name="apple-mobile-web-app-capable" content="yes">',
        '<meta name="apple-touch-fullscreen" content="yes">',
        '<meta name="apple-mobile-web-app-status-bar-style" content="black-translucent">',
        '<meta name="apple-mobile-web-app-title" content="FinTrack">',
        '<meta name="theme-color" content="' . esc_attr($theme_color) . '" id="metaThemeColor">',
        '<meta name="format-detection" content="telephone=no">',
        '<link rel="manifest" href="' . esc_url($manifest_url) . '">',
        '<link rel="apple-touch-icon" href="' . esc_url(BNTM_FT_URL . 'icons/icon-192.png') . '">',
        '<link rel="apple-touch-startup-image" href="' . esc_url(BNTM_FT_URL . 'icons/splash.png') . '">',
        '<link rel="preconnect" href="https://fonts.googleapis.com">',
        '<link rel="preconnect" href="https://fonts.gstatic.com" crossorigin>',
        '<link href="' . esc_url($font_url) . '" rel="stylesheet">',
    ]);
}

add_action('template_redirect', function () {
    if (!ft_is_app_page()) return;
    ob_start(function ($html) {
        $markup = ft_get_pwa_head_markup();
        if (stripos($html, 'id="metaThemeColor"') !== false) return $html;
        if (stripos($html, '</head>') !== false) return preg_replace('/<\/head>/i', $markup . "\n</head>", $html, 1);
        return $markup . "\n" . $html;
    });
}, 0);

// ============================================================
// AJAX ACTION HOOKS
// ============================================================

$ft_ajax_actions = [
    'ft_get_dashboard_data'      => ['bntm_ajax_ft_get_dashboard_data', true],
    'ft_add_wallet'              => ['bntm_ajax_ft_add_wallet', false],
    'ft_edit_wallet'             => ['bntm_ajax_ft_edit_wallet', false],
    'ft_delete_wallet'           => ['bntm_ajax_ft_delete_wallet', false],
    'ft_add_transaction'         => ['bntm_ajax_ft_add_transaction', false],
    'ft_edit_transaction'        => ['bntm_ajax_ft_edit_transaction', false],
    'ft_delete_transaction'      => ['bntm_ajax_ft_delete_transaction', false],
    'ft_realize_transaction'     => ['bntm_ajax_ft_realize_transaction', false],
    'ft_get_transactions'        => ['bntm_ajax_ft_get_transactions', true],
    'ft_add_category'            => ['bntm_ajax_ft_add_category', false],
    'ft_edit_category'           => ['bntm_ajax_ft_edit_category', false],
    'ft_delete_category'         => ['bntm_ajax_ft_delete_category', false],
    'ft_save_budget'             => ['bntm_ajax_ft_save_budget', false],
    'ft_delete_budget'           => ['bntm_ajax_ft_delete_budget', false],
    'ft_save_goal'               => ['bntm_ajax_ft_save_goal', false],
    'ft_delete_goal'             => ['bntm_ajax_ft_delete_goal', false],
    'ft_save_recurrence'         => ['bntm_ajax_ft_save_recurrence', false],
    'ft_edit_recurrence'         => ['bntm_ajax_ft_edit_recurrence', false],
    'ft_delete_recurrence'       => ['bntm_ajax_ft_delete_recurrence', false],
    'ft_get_insights'            => ['bntm_ajax_ft_get_insights', true],
    'ft_allocate_goal'           => ['bntm_ajax_ft_allocate_goal', false],
    'ft_get_goal_allocations'    => ['bntm_ajax_ft_get_goal_allocations', false],
    'ft_realize_goal_allocation' => ['bntm_ajax_ft_realize_goal_allocation', false],
    'ft_edit_goal_allocation'    => ['bntm_ajax_ft_edit_goal_allocation', false],
    'ft_delete_goal_allocation'  => ['bntm_ajax_ft_delete_goal_allocation', false],
    'ft_process_scheduled'       => ['bntm_ajax_ft_process_scheduled', true],
    'ft_sync_queue'              => ['bntm_ajax_ft_sync_queue', false],
    'ft_start_pro_checkout'      => ['bntm_ajax_ft_start_pro_checkout', false],
    'ft_save_maya_settings'      => ['bntm_ajax_ft_save_maya_settings', false],
    'ft_save_pro_settings'       => ['bntm_ajax_ft_save_pro_settings', false],
];
foreach ($ft_ajax_actions as $action => [$cb, $nopriv]) {
    add_action("wp_ajax_{$action}", $cb);
    if ($nopriv) add_action("wp_ajax_nopriv_{$action}", $cb);
}
add_action('template_redirect', 'ft_handle_maya_return');

// ============================================================
// ADMIN SHORTCODE
// ============================================================

function bntm_shortcode_ft_dashboard() {
    if (!is_user_logged_in()) return '<div class="bntm-notice">Please log in.</div>';
    if (!current_user_can('manage_options')) {
        $app = get_page_by_path('finance-tracker');
        $url = $app ? get_permalink($app->ID) : home_url('/');
        wp_safe_redirect($url); exit;
    }
    $active_tab = isset($_GET['tab']) ? sanitize_text_field($_GET['tab']) : 'overview';
    ob_start();
    ?>
    <script>var ajaxurl='<?php echo admin_url('admin-ajax.php'); ?>';</script>
    <div class="bntm-ft-admin-container">
        <div class="bntm-tabs">
            <?php foreach (['overview'=>'Overview','users'=>'Users','subscriptions'=>'Subscriptions','transactions'=>'Transactions','analytics'=>'Analytics','settings'=>'Settings'] as $k=>$l): ?>
            <a href="?tab=<?php echo $k;?>" class="bntm-tab <?php echo $active_tab===$k?'active':'';?>"><?php echo $l;?></a>
            <?php endforeach; ?>
        </div>
        <div class="bntm-tab-content">
            <?php
            match($active_tab) {
                'overview'      => print ft_admin_overview_tab(),
                'users'         => print ft_admin_users_tab(),
                'subscriptions' => print ft_admin_subscriptions_tab(),
                'transactions'  => print ft_admin_transactions_tab(),
                'analytics'     => print ft_admin_analytics_tab(),
                'settings'      => print ft_admin_settings_tab(),
                default         => print ft_admin_overview_tab(),
            };
            ?>
        </div>
    </div>
    <?php
    $content = ob_get_clean();
    return bntm_universal_container('Finance Tracker Admin', $content);
}

function ft_admin_overview_tab() {
    global $wpdb; $p = $wpdb->prefix;
    $total_users  = (int)$wpdb->get_var("SELECT COUNT(DISTINCT user_id) FROM {$p}ft_wallets WHERE status='active'");
    $total_txns   = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}ft_transactions WHERE status='active'");
    $total_income = (float)$wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$p}ft_transactions WHERE type='income' AND status='active' AND MONTH(transaction_date)=MONTH(NOW())");
    $total_expense= (float)$wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$p}ft_transactions WHERE type='expense' AND status='active' AND MONTH(transaction_date)=MONTH(NOW())");
    $total_pro    = (int)$wpdb->get_var("SELECT COUNT(*) FROM {$p}ft_licenses WHERE status='active'");
    $total_revenue= (float)$wpdb->get_var("SELECT COALESCE(SUM(amount),0) FROM {$p}ft_licenses WHERE status='active'");
    ob_start(); ?>
    <div class="bntm-stats-row">
        <?php foreach ([
            ['Active Users', number_format($total_users), 'all time', '#6366f1'],
            ['Transactions', number_format($total_txns), 'all time', '#10b981'],
            ['Pro Users', number_format($total_pro), 'lifetime', '#f59e0b'],
            ['Revenue', '₱'.number_format($total_revenue,2), 'lifetime', '#22c55e'],
            ['Income (Month)', '₱'.number_format($total_income,2), 'current month', '#22c55e'],
            ['Expenses (Month)', '₱'.number_format($total_expense,2), 'current month', '#ef4444'],
        ] as [$title,$val,$label,$color]): ?>
        <div class="bntm-stat-card"><div class="stat-content"><h3><?php echo $title;?></h3><p class="stat-number" style="color:<?php echo $color;?>"><?php echo $val;?></p><span class="stat-label"><?php echo $label;?></span></div></div>
        <?php endforeach; ?>
    </div>
    <div class="bntm-form-section"><h3>Recent Transactions</h3>
        <?php $recent=$wpdb->get_results("SELECT t.*,c.name as cat_name,w.name as wallet_name FROM {$p}ft_transactions t LEFT JOIN {$p}ft_categories c ON c.id=t.category_id LEFT JOIN {$p}ft_wallets w ON w.id=t.wallet_id WHERE t.status='active' ORDER BY t.transaction_date DESC,t.id DESC LIMIT 20"); ?>
        <div class="bntm-table-wrapper"><table class="bntm-table"><thead><tr><th>Date</th><th>User</th><th>Type</th><th>Category</th><th>Wallet</th><th>Amount</th></tr></thead><tbody>
        <?php foreach ($recent as $t): $ud=get_userdata($t->user_id); ?>
        <tr><td><?php echo esc_html($t->transaction_date);?></td><td><?php echo esc_html($ud?$ud->display_name:'#'.$t->user_id);?></td>
        <td><span style="padding:2px 8px;border-radius:999px;font-size:12px;font-weight:600;background:<?php echo $t->type==='income'?'#dcfce7':'#fee2e2';?>;color:<?php echo $t->type==='income'?'#16a34a':'#dc2626';?>"><?php echo ucfirst(esc_html($t->type));?></span></td>
        <td><?php echo esc_html($t->cat_name??'—');?></td><td><?php echo esc_html($t->wallet_name??'—');?></td>
        <td style="font-weight:600;color:<?php echo $t->type==='income'?'#16a34a':'#dc2626';?>"><?php echo $t->type==='income'?'+':'-';?>₱<?php echo number_format($t->amount,2);?></td></tr>
        <?php endforeach; ?>
        </tbody></table></div></div>
    <?php return ob_get_clean();
}

function ft_admin_users_tab() {
    global $wpdb; $p=$wpdb->prefix;
    bntm_ft_maybe_upgrade_schema();
    $notice = ft_admin_handle_user_subscription_action();
    $users_table = $wpdb->users;
    $users=$wpdb->get_results("
        SELECT u.ID as user_id,
            (SELECT COUNT(*) FROM {$p}ft_wallets w WHERE w.user_id=u.ID AND w.status='active') as wallet_count,
            (SELECT COUNT(*) FROM {$p}ft_transactions t WHERE t.user_id=u.ID AND t.status='active') as txn_count,
            (SELECT COALESCE(SUM(t.amount),0) FROM {$p}ft_transactions t WHERE t.user_id=u.ID AND t.type='income' AND t.status='active') as total_income,
            (SELECT COALESCE(SUM(t.amount),0) FROM {$p}ft_transactions t WHERE t.user_id=u.ID AND t.type='expense' AND t.status='active') as total_expense
        FROM {$users_table} u
        WHERE EXISTS (SELECT 1 FROM {$p}ft_wallets w WHERE w.user_id=u.ID)
           OR EXISTS (SELECT 1 FROM {$p}ft_transactions t WHERE t.user_id=u.ID)
           OR EXISTS (SELECT 1 FROM {$p}ft_licenses l WHERE l.user_id=u.ID)
           OR EXISTS (SELECT 1 FROM {$wpdb->usermeta} um WHERE um.user_id=u.ID AND um.meta_key='ft_pro_active')
        ORDER BY txn_count DESC, wallet_count DESC
        LIMIT 100
    ");
    ob_start(); ?>
    <?php echo $notice; ?>
    <div class="bntm-form-section"><h3>User Activity</h3><div class="bntm-table-wrapper"><table class="bntm-table"><thead><tr><th>User</th><th>Plan</th><th>Wallets</th><th>Transactions</th><th>Income</th><th>Expenses</th><th>Net</th><th>Subscription</th></tr></thead><tbody>
    <?php foreach ($users as $u): $ud=get_userdata($u->user_id); $is_pro=ft_is_pro($u->user_id); $net=$u->total_income-$u->total_expense; ?>
    <tr><td><strong><?php echo esc_html($ud?$ud->display_name:'#'.$u->user_id);?></strong><?php if($ud):?><br><small><?php echo esc_html($ud->user_email);?></small><?php endif;?></td>
    <td><span style="padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;background:<?php echo $is_pro?'#fee2e2':'#f3f4f6';?>;color:<?php echo $is_pro?'#dc2626':'#6b7280';?>"><?php echo $is_pro?'PRO':'FREE';?></span></td>
    <td><?php echo $u->wallet_count;?></td><td><?php echo number_format($u->txn_count);?></td>
    <td style="color:#16a34a;font-weight:600;">₱<?php echo number_format($u->total_income,2);?></td>
    <td style="color:#dc2626;font-weight:600;">₱<?php echo number_format($u->total_expense,2);?></td>
    <td style="font-weight:700;color:<?php echo $net>=0?'#16a34a':'#dc2626';?>">₱<?php echo number_format($net,2);?></td>
    <td>
        <form method="post" style="display:inline-flex;gap:6px;align-items:center;" onsubmit="return confirm('<?php echo $is_pro ? 'Revoke Pro access for this user?' : 'Grant Pro access to this user?'; ?>')">
            <?php wp_nonce_field('ft_user_subscription','ft_nonce');?>
            <input type="hidden" name="ft_action" value="<?php echo $is_pro ? 'revoke_user_pro' : 'grant_user_pro';?>">
            <input type="hidden" name="user_id" value="<?php echo (int)$u->user_id;?>">
            <button type="submit" class="<?php echo $is_pro ? '' : 'bntm-btn-primary bntm-btn-small';?>" style="<?php echo $is_pro ? 'background:#fee2e2;color:#dc2626;border:none;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:12px;' : ''; ?>"><?php echo $is_pro ? 'Revoke Pro' : 'Grant Pro';?></button>
        </form>
    </td></tr>
    <?php endforeach; ?>
    </tbody></table></div></div>
    <?php return ob_get_clean();
}

function ft_admin_handle_user_subscription_action() {
    if (!isset($_POST['ft_action']) || !in_array($_POST['ft_action'], ['grant_user_pro', 'revoke_user_pro'], true)) return '';
    if (!current_user_can('manage_options') || !check_admin_referer('ft_user_subscription', 'ft_nonce')) return '<div class="bntm-notice bntm-notice-error">Unauthorized subscription update.</div>';
    global $wpdb;
    $user_id = absint($_POST['user_id'] ?? 0);
    if (!$user_id || !get_userdata($user_id)) return '<div class="bntm-notice bntm-notice-error">User not found.</div>';
    $lt = $wpdb->prefix . 'ft_licenses';
    bntm_ft_maybe_upgrade_schema();
    if ($_POST['ft_action'] === 'grant_user_pro') {
        $active = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$lt} WHERE user_id=%d AND status='active' ORDER BY id DESC LIMIT 1", $user_id));
        if (!$active) {
            $license_key = ft_generate_license_key();
            $wpdb->insert($lt, [
                'user_id' => $user_id, 'license_key' => $license_key, 'plan' => 'pro', 'status' => 'active',
                'payment_id' => 'manual-admin', 'amount' => 0, 'currency' => 'PHP', 'purchased_at' => current_time('mysql'),
            ], ['%d','%s','%s','%s','%s','%f','%s','%s']);
        } else {
            $license_key = $active->license_key;
        }
        update_user_meta($user_id, 'ft_pro_active', 1);
        update_user_meta($user_id, 'ft_pro_license_key', $license_key);
        update_user_meta($user_id, 'ft_pro_purchased_at', current_time('mysql'));
        return '<div class="bntm-notice bntm-notice-success">Pro access granted.</div>';
    }
    $wpdb->update($lt, ['status' => 'revoked'], ['user_id' => $user_id, 'status' => 'active'], ['%s'], ['%d','%s']);
    delete_user_meta($user_id, 'ft_pro_active');
    delete_user_meta($user_id, 'ft_pro_license_key');
    return '<div class="bntm-notice bntm-notice-success">Pro access revoked.</div>';
}

function ft_admin_subscriptions_tab() {
    global $wpdb; $p=$wpdb->prefix;
    $lt = $p.'ft_licenses';
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s",$lt)) !== $lt) {
        return '<div class="bntm-form-section"><p>License table not yet created.</p></div>';
    }
    $notice = '';
    if (isset($_POST['ft_action']) && in_array($_POST['ft_action'], ['activate_license', 'revoke_license'], true) && check_admin_referer('ft_manual_activate','ft_nonce') && current_user_can('manage_options')) {
        $lid = intval($_POST['license_id']??0);
        $lic = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$lt} WHERE id=%d",$lid));
        if ($lic) {
            if ($_POST['ft_action']==='activate_license') {
                $wpdb->update($lt,['status'=>'active','purchased_at'=>current_time('mysql')],['id'=>$lid],['%s','%s'],['%d']);
                update_user_meta($lic->user_id,'ft_pro_active',1);
                update_user_meta($lic->user_id,'ft_pro_license_key',$lic->license_key);
                $notice = '<div class="bntm-notice bntm-notice-success">License activated.</div>';
            } elseif ($_POST['ft_action']==='revoke_license') {
                $wpdb->update($lt,['status'=>'revoked'],['id'=>$lid],['%s'],['%d']);
                delete_user_meta($lic->user_id,'ft_pro_active');
                $notice = '<div class="bntm-notice bntm-notice-error">License revoked.</div>';
            }
        }
    }
    $licenses=$wpdb->get_results("SELECT * FROM {$lt} ORDER BY created_at DESC LIMIT 100");
    ob_start(); ?>
    <?php echo $notice; ?>
    <div class="bntm-form-section"><h3>Subscriptions / Licenses</h3>
    <div class="bntm-table-wrapper"><table class="bntm-table"><thead><tr><th>User</th><th>License Key</th><th>Plan</th><th>Status</th><th>Amount</th><th>Payment ID</th><th>Date</th><th>Actions</th></tr></thead><tbody>
    <?php if(empty($licenses)): ?><tr><td colspan="8" style="text-align:center;color:#6b7280;">No licenses yet.</td></tr>
    <?php else: foreach($licenses as $l): $ud=get_userdata($l->user_id); ?>
    <tr>
        <td><?php echo esc_html($ud?$ud->display_name:'#'.$l->user_id);?><?php if($ud):?><br><small><?php echo esc_html($ud->user_email);?></small><?php endif;?></td>
        <td><code style="font-size:11px;"><?php echo esc_html(substr($l->license_key,0,8).'...');?></code></td>
        <td><span style="font-weight:700;color:#6366f1;"><?php echo strtoupper(esc_html($l->plan));?></span></td>
        <td><span style="padding:2px 8px;border-radius:999px;font-size:11px;font-weight:700;background:<?php echo $l->status==='active'?'#dcfce7':($l->status==='pending'?'#fef9c3':'#fee2e2');?>;color:<?php echo $l->status==='active'?'#16a34a':($l->status==='pending'?'#ca8a04':'#dc2626');?>"><?php echo ucfirst(esc_html($l->status));?></span></td>
        <td>₱<?php echo number_format($l->amount,2);?></td>
        <td><small><?php echo esc_html($l->payment_id??'—');?></small></td>
        <td><?php echo esc_html($l->purchased_at??$l->created_at);?></td>
        <td>
            <?php if($l->status!=='active'): ?>
            <form method="post" style="display:inline;" onsubmit="return confirm('Manually activate this license?')">
                <?php wp_nonce_field('ft_manual_activate','ft_nonce');?>
                <input type="hidden" name="ft_action" value="activate_license"><input type="hidden" name="license_id" value="<?php echo $l->id;?>">
                <button type="submit" class="bntm-btn-primary bntm-btn-small">Activate</button>
            </form>
            <?php else: ?>
            <form method="post" style="display:inline;" onsubmit="return confirm('Revoke this license?')">
                <?php wp_nonce_field('ft_manual_activate','ft_nonce');?>
                <input type="hidden" name="ft_action" value="revoke_license"><input type="hidden" name="license_id" value="<?php echo $l->id;?>">
                <button type="submit" style="background:#fee2e2;color:#dc2626;border:none;padding:4px 10px;border-radius:6px;cursor:pointer;font-size:12px;">Revoke</button>
            </form>
            <?php endif;?>
        </td>
    </tr>
    <?php endforeach; endif;?>
    </tbody></table></div></div>
    <?php return ob_get_clean();
}

function ft_admin_transactions_tab() {
    global $wpdb; $p=$wpdb->prefix;
    $month=isset($_GET['month'])?sanitize_text_field($_GET['month']):date('Y-m');
    $txns=$wpdb->get_results($wpdb->prepare("SELECT t.*,c.name as cat_name,w.name as wallet_name FROM {$p}ft_transactions t LEFT JOIN {$p}ft_categories c ON c.id=t.category_id LEFT JOIN {$p}ft_wallets w ON w.id=t.wallet_id WHERE t.status='active' AND DATE_FORMAT(t.transaction_date,'%%Y-%%m')=%s ORDER BY t.transaction_date DESC,t.id DESC LIMIT 200",$month));
    ob_start(); ?>
    <div class="bntm-form-section"><h3>Transactions — <?php echo esc_html($month);?></h3>
    <div style="margin-bottom:16px;"><form method="get" style="display:inline-flex;gap:8px;">
        <?php foreach($_GET as $k=>$v) if($k!=='month') echo '<input type="hidden" name="'.esc_attr($k).'" value="'.esc_attr($v).'">'; ?>
        <input type="month" name="month" value="<?php echo esc_attr($month);?>" class="bntm-input">
        <button type="submit" class="bntm-btn-primary bntm-btn-small">Filter</button>
    </form></div>
    <div class="bntm-table-wrapper"><table class="bntm-table"><thead><tr><th>Date</th><th>Type</th><th>Category</th><th>Wallet</th><th>Amount</th><th>Note</th></tr></thead><tbody>
    <?php if(empty($txns)): ?><tr><td colspan="6" style="text-align:center;color:#6b7280;">No transactions</td></tr>
    <?php else: foreach($txns as $t): ?>
    <tr><td><?php echo esc_html($t->transaction_date);?></td><td><span style="font-size:12px;padding:2px 8px;border-radius:999px;background:<?php echo $t->type==='income'?'#dcfce7':'#fee2e2';?>;color:<?php echo $t->type==='income'?'#16a34a':'#dc2626';?>"><?php echo ucfirst($t->type);?></span></td>
    <td><?php echo esc_html($t->cat_name??'—');?></td><td><?php echo esc_html($t->wallet_name??'—');?></td>
    <td style="font-weight:600;color:<?php echo $t->type==='income'?'#16a34a':'#dc2626';?>"><?php echo $t->type==='income'?'+':'-';?>₱<?php echo number_format($t->amount,2);?></td>
    <td><?php echo esc_html($t->note??'');?></td></tr>
    <?php endforeach; endif;?>
    </tbody></table></div></div>
    <?php return ob_get_clean();
}

function ft_admin_analytics_tab() {
    global $wpdb; $p=$wpdb->prefix;
    $monthly=$wpdb->get_results("SELECT DATE_FORMAT(transaction_date,'%Y-%m') as month,SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as income,SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) as expense FROM {$p}ft_transactions WHERE status='active' GROUP BY month ORDER BY month DESC LIMIT 12");
    $top_cats=$wpdb->get_results("SELECT c.name,c.color,SUM(t.amount) as total FROM {$p}ft_transactions t JOIN {$p}ft_categories c ON c.id=t.category_id WHERE t.type='expense' AND t.status='active' AND t.transaction_date>=DATE_FORMAT(NOW(),'%Y-%m-01') GROUP BY c.id ORDER BY total DESC LIMIT 8");
    ob_start(); ?>
    <div class="bntm-stats-row" style="margin-bottom:24px;flex-wrap:wrap;">
    <?php foreach(array_reverse($monthly) as $m): ?>
    <div class="bntm-stat-card" style="min-width:160px;"><div class="stat-content"><h3><?php echo esc_html($m->month);?></h3>
    <p style="color:#16a34a;font-size:14px;margin:4px 0;">+₱<?php echo number_format($m->income,0);?></p>
    <p style="color:#dc2626;font-size:14px;margin:0;">-₱<?php echo number_format($m->expense,0);?></p>
    <span class="stat-label" style="color:<?php echo ($m->income-$m->expense)>=0?'#16a34a':'#dc2626';?>">Net: ₱<?php echo number_format($m->income-$m->expense,0);?></span></div></div>
    <?php endforeach; ?>
    </div>
    <div class="bntm-form-section"><h3>Top Expense Categories (This Month)</h3>
    <?php if(empty($top_cats)): ?><p style="color:#6b7280;">No data.</p>
    <?php else: $max=max(array_column($top_cats,'total')); foreach($top_cats as $c): $pct=$max>0?($c->total/$max*100):0; ?>
    <div style="margin-bottom:12px;"><div style="display:flex;justify-content:space-between;margin-bottom:4px;"><span style="font-weight:600;"><?php echo esc_html($c->name);?></span><span style="color:#6b7280;">₱<?php echo number_format($c->total,2);?></span></div>
    <div style="background:#f3f4f6;border-radius:999px;height:8px;overflow:hidden;"><div style="width:<?php echo $pct;?>%;background:<?php echo esc_attr($c->color);?>;height:100%;border-radius:999px;"></div></div></div>
    <?php endforeach; endif;?>
    </div>
    <?php return ob_get_clean();
}

function ft_admin_settings_tab() {
    $config = ft_get_maya_config();
    $mode   = $config['mode'];
    $has_pub = !empty(get_option('ft_maya_public_key_enc',''));
    $has_sec = !empty(get_option('ft_maya_secret_key_enc',''));
    $features = ft_get_pro_features();
    $pro_price = ft_get_pro_price();
    $free_wallet_limit = ft_get_free_wallet_limit();
    $free_category_limit = ft_get_free_category_limit();
    $home_transaction_limit = ft_get_home_transaction_limit();
    ob_start(); ?>
    <div class="bntm-form-section">
        <h3>Payment Gateway Settings</h3>
        <p style="color:#6b7280;margin-bottom:16px;">Keys are encrypted before storing. Leave blank to keep existing key.</p>
        <div id="ftAdminMayaMsg"></div>
        <div style="display:grid;gap:12px;max-width:500px;">
            <div><label style="font-size:12px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px;">MODE</label>
            <select id="ftMayaMode" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px;">
                <option value="sandbox" <?php selected($mode,'sandbox');?>>Sandbox (Testing)</option>
                <option value="live" <?php selected($mode,'live');?>>Live (Production)</option>
            </select></div>
            <div><label style="font-size:12px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px;">PUBLIC KEY <?php echo $has_pub?'<span style="color:#22c55e;">✓ Set</span>':'<span style="color:#ef4444;">Not set</span>';?></label>
            <input type="text" id="ftMayaPub" placeholder="pk-..." style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px;font-family:monospace;"></div>
            <div><label style="font-size:12px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px;">SECRET KEY <?php echo $has_sec?'<span style="color:#22c55e;">✓ Set</span>':'<span style="color:#ef4444;">Not set</span>';?></label>
            <input type="password" id="ftMayaSec" placeholder="sk-..." style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px;font-family:monospace;"></div>
            <button onclick="ftSaveMayaSettings()" style="padding:12px;background:#6366f1;color:white;border:none;border-radius:8px;font-weight:700;cursor:pointer;">Save Settings</button>
        </div>
    </div>
    <div class="bntm-form-section" style="margin-top:24px;">
        <h3>Pro Plan & Limits</h3>
        <p style="color:#6b7280;margin-bottom:16px;">These values control the upgrade sheet, checkout price, free-plan limits, and home transaction preview.</p>
        <div id="ftAdminProMsg"></div>
        <div style="display:grid;gap:12px;max-width:640px;">
            <div style="display:grid;grid-template-columns:repeat(auto-fit,minmax(150px,1fr));gap:12px;">
                <div><label style="font-size:12px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px;">PRO PRICE (PHP)</label><input type="number" id="ftProPrice" min="0" step="0.01" value="<?php echo esc_attr($pro_price);?>" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px;"></div>
                <div><label style="font-size:12px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px;">FREE WALLETS</label><input type="number" id="ftFreeWalletLimit" min="0" step="1" value="<?php echo esc_attr($free_wallet_limit);?>" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px;"></div>
                <div><label style="font-size:12px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px;">FREE CATEGORIES</label><input type="number" id="ftFreeCategoryLimit" min="0" step="1" value="<?php echo esc_attr($free_category_limit);?>" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px;"></div>
                <div><label style="font-size:12px;font-weight:700;color:#6b7280;display:block;margin-bottom:4px;">HOME TRANSACTIONS</label><input type="number" id="ftHomeTransactionLimit" min="1" max="30" step="1" value="<?php echo esc_attr($home_transaction_limit);?>" style="width:100%;padding:10px;border:1px solid #e5e7eb;border-radius:8px;"></div>
            </div>
            <div style="display:grid;gap:10px;">
                <?php foreach ($features as $key => $feature): ?>
                <div style="display:grid;grid-template-columns:auto 1fr 1fr 82px;gap:8px;align-items:center;padding:10px;border:1px solid #e5e7eb;border-radius:10px;">
                    <label style="display:flex;align-items:center;gap:6px;font-weight:700;font-size:12px;color:#374151;"><input type="checkbox" class="ftProFeatureEnabled" data-key="<?php echo esc_attr($key);?>" <?php checked(!empty($feature['enabled']));?>> Show</label>
                    <input type="text" class="ftProFeatureTitle" data-key="<?php echo esc_attr($key);?>" value="<?php echo esc_attr($feature['title']);?>" style="width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:8px;">
                    <input type="text" class="ftProFeatureDesc" data-key="<?php echo esc_attr($key);?>" value="<?php echo esc_attr($feature['desc']);?>" style="width:100%;padding:8px;border:1px solid #e5e7eb;border-radius:8px;">
                    <input type="color" class="ftProFeatureColor" data-key="<?php echo esc_attr($key);?>" value="<?php echo esc_attr($feature['color']);?>" style="width:100%;height:36px;border:1px solid #e5e7eb;border-radius:8px;background:white;">
                </div>
                <?php endforeach; ?>
            </div>
            <button onclick="ftSaveProSettings()" style="padding:12px;background:#ef4444;color:white;border:none;border-radius:8px;font-weight:700;cursor:pointer;">Save Pro Settings</button>
        </div>
    </div>
    <script>
    async function ftSaveMayaSettings(){
        const btn=event.target; btn.disabled=true; btn.textContent='Saving...';
        const fd=new FormData(); fd.append('action','ft_save_maya_settings'); fd.append('nonce','<?php echo wp_create_nonce("ft_admin_nonce");?>');
        fd.append('mode',document.getElementById('ftMayaMode').value); fd.append('public_key',document.getElementById('ftMayaPub').value); fd.append('secret_key',document.getElementById('ftMayaSec').value);
        const r=await fetch('<?php echo admin_url("admin-ajax.php");?>',{method:'POST',body:fd}); const d=await r.json();
        btn.disabled=false; btn.textContent='Save Settings';
        const msg=document.getElementById('ftAdminMayaMsg');
        msg.innerHTML=d.success?'<div style="background:#dcfce7;color:#16a34a;padding:10px;border-radius:8px;margin-bottom:12px;">Settings saved!</div>':'<div style="background:#fee2e2;color:#dc2626;padding:10px;border-radius:8px;margin-bottom:12px;">'+(d.data||'Error')+'</div>';
        setTimeout(()=>msg.innerHTML='',3000);
    }
    async function ftSaveProSettings(){
        const btn=event.target; btn.disabled=true; btn.textContent='Saving...';
        const features={};
        document.querySelectorAll('.ftProFeatureTitle').forEach(input=>{
            const key=input.dataset.key;
            features[key]={
                title:input.value,
                desc:document.querySelector(`.ftProFeatureDesc[data-key="${key}"]`)?.value||'',
                color:document.querySelector(`.ftProFeatureColor[data-key="${key}"]`)?.value||'#ef4444',
                enabled:document.querySelector(`.ftProFeatureEnabled[data-key="${key}"]`)?.checked?1:0
            };
        });
        const fd=new FormData(); fd.append('action','ft_save_pro_settings'); fd.append('nonce','<?php echo wp_create_nonce("ft_admin_nonce");?>');
        fd.append('price',document.getElementById('ftProPrice').value);
        fd.append('free_wallet_limit',document.getElementById('ftFreeWalletLimit').value);
        fd.append('free_category_limit',document.getElementById('ftFreeCategoryLimit').value);
        fd.append('home_transaction_limit',document.getElementById('ftHomeTransactionLimit').value);
        fd.append('features',JSON.stringify(features));
        const r=await fetch('<?php echo admin_url("admin-ajax.php");?>',{method:'POST',body:fd}); const d=await r.json();
        btn.disabled=false; btn.textContent='Save Pro Settings';
        const msg=document.getElementById('ftAdminProMsg');
        msg.innerHTML=d.success?'<div style="background:#dcfce7;color:#16a34a;padding:10px;border-radius:8px;margin-bottom:12px;">Pro settings saved!</div>':'<div style="background:#fee2e2;color:#dc2626;padding:10px;border-radius:8px;margin-bottom:12px;">'+(d.data||'Error')+'</div>';
        setTimeout(()=>msg.innerHTML='',3000);
    }
    </script>
    <?php return ob_get_clean();
}

// ============================================================
// MAIN APP SHORTCODE
// ============================================================

function bntm_shortcode_ft_app() {
    if (!is_user_logged_in()) {
        return '<div style="padding:40px;text-align:center;font-family:sans-serif;"><p>Please <a href="' . wp_login_url(get_permalink()) . '">log in</a> to use Finance Tracker.</p></div>';
    }
    bntm_ft_maybe_upgrade_schema();
    $user_id    = get_current_user_id();
    $nonce      = wp_create_nonce('ft_app_nonce');
    $user       = wp_get_current_user();
    $first_name = $user->first_name ?: $user->display_name;
    $is_pro     = ft_is_pro($user_id) ? 'true' : 'false';
    $pro_price  = ft_get_pro_price();
    $pro_features = array_values(array_filter(ft_get_pro_features(), fn($f) => !empty($f['enabled'])));
    $free_wallet_limit = ft_get_free_wallet_limit();
    $free_category_limit = ft_get_free_category_limit();
    $home_transaction_limit = ft_get_home_transaction_limit();
    $page_url   = get_permalink();
    $page_scope = wp_parse_url($page_url, PHP_URL_PATH) ?: '/';
    $manifest_url = ft_get_pwa_manifest_url();
    $sw_url       = ft_get_pwa_sw_url();
    ob_start();
?>
<style>
/* ── TOKENS ── */
:root{--accent:#ef4444;--accent-hover:#dc2626;--accent-rgb:239,68,68;--income:#22c55e;--expense:#f43f5e;--transfer:#38bdf8;--warn:#f59e0b;--radius:22px;--shadow:0 8px 32px rgba(0,0,0,.14);--shadow-sm:0 2px 8px rgba(0,0,0,.08);--t:.2s cubic-bezier(.4,0,.2,1);--font:'DM Sans',sans-serif;--font-mono:'JetBrains Mono',monospace;--sat:env(safe-area-inset-top,0px);--sab:env(safe-area-inset-bottom,0px);--sal:env(safe-area-inset-left,0px);--sar:env(safe-area-inset-right,0px);}
/* ── LIGHT ── */
#ft-app[data-theme="light"]{--bg:#f2f2f7;--bg-card:#ffffff;--bg-card-2:#f9fafb;--bg-elevated:#ffffff;--text:#0f172a;--text-2:#64748b;--text-3:#94a3b8;--border:rgba(0,0,0,.07);--overlay:rgba(0,0,0,.45);--input-bg:#f1f5f9;--nav-bg:rgba(255,255,255,.94);--hero-from:#fee2e2;--hero-to:#fecaca;}
/* ── DARK ── */
[data-theme="dark"]{--bg:#080810;--bg-card:#0f0f1a;--bg-card-2:#14141f;--bg-elevated:#1a1a2e;--text:#f1f5f9;--text-2:#94a3b8;--text-3:#475569;--border:rgba(255,255,255,.08);--overlay:rgba(0,0,0,.82);--input-bg:#1a1a2e;--nav-bg:rgba(8,8,16,.96);--hero-from:#1e1b4b;--hero-to:#0f0f1a;}
*,*::before,*::after{box-sizing:border-box;margin:0;padding:0;-webkit-tap-highlight-color:transparent;}
html,body{height:100%;overscroll-behavior:none;font-family:var(--font);background:#080810;}
/* Full-screen app wrapper — accounts for safe areas */
#ft-app{position:fixed;inset:0;max-width:430px;margin:0 auto;background:var(--bg);color:var(--text);display:flex;flex-direction:column;overflow:hidden;padding-top:var(--sat);}
/* VIEWS */
.ft-view{display:none;flex-direction:column;flex:1;overflow-y:auto;overflow-x:hidden;-webkit-overflow-scrolling:touch;padding-bottom:calc(64px + var(--sab));}
.ft-view.active{display:flex;}
@keyframes fadeUp{from{opacity:0;transform:translateY(12px);}to{opacity:1;transform:none;}}
.ft-view.active>*{animation:fadeUp .25s ease both;}
.ft-view.active>*:nth-child(1){animation-delay:.03s;}.ft-view.active>*:nth-child(2){animation-delay:.06s;}.ft-view.active>*:nth-child(3){animation-delay:.09s;}.ft-view.active>*:nth-child(4){animation-delay:.12s;}
/* NAV */
.ft-nav{position:absolute;bottom:0;left:0;right:0;background:var(--nav-bg);backdrop-filter:blur(24px);-webkit-backdrop-filter:blur(24px);border-top:1px solid var(--border);display:grid;grid-template-columns:repeat(5,1fr);padding-bottom:var(--sab);z-index:100;}
.ft-nav-item{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:10px 0 8px;cursor:pointer;user-select:none;-webkit-user-select:none;}
.ft-nav-item svg{width:22px;height:22px;stroke:var(--text-3);transition:stroke var(--t),transform var(--t);}
.ft-nav-item span{font-size:10px;color:var(--text-3);margin-top:3px;font-weight:600;transition:color var(--t);}
.ft-nav-item.active svg{stroke:var(--accent);}.ft-nav-item.active span{color:var(--accent);}
.ft-nav-item:active svg{transform:scale(.88);}
.ft-nav-fab{display:flex;align-items:center;justify-content:center;}
.ft-fab-btn{width:52px;height:52px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent-hover));border:none;cursor:pointer;display:flex;align-items:center;justify-content:center;box-shadow:0 4px 20px rgba(var(--accent-rgb),.5);transition:transform var(--t),box-shadow var(--t);margin-top:-10px;}
.ft-fab-btn:active{transform:scale(.92);}
.ft-fab-btn svg{width:22px;height:22px;stroke:white;stroke-width:2.5;}
/* TOPBAR */
.ft-topbar{display:flex;align-items:center;justify-content:space-between;padding:16px 20px 8px;position:sticky;top:0;background:var(--bg);z-index:50;flex-shrink:0;}
.ft-topbar-title{font-size:22px;font-weight:800;letter-spacing:-.5px;}
.ft-topbar-actions{display:flex;gap:8px;align-items:center;}
.ft-icon-btn{width:38px;height:38px;border-radius:50%;background:var(--bg-card);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;transition:background var(--t);}
.ft-icon-btn svg{width:18px;height:18px;stroke:var(--text-2);}
/* HERO */
.ft-hero{margin:8px 16px 12px;border-radius:28px;background:linear-gradient(145deg,var(--hero-from),var(--hero-to));padding:28px 24px 24px;position:relative;overflow:hidden;flex-shrink:0;}
.ft-hero::before{content:'';position:absolute;top:-50px;right:-50px;width:200px;height:200px;border-radius:50%;background:rgba(var(--accent-rgb),.1);}
.ft-hero-label{font-size:11px;font-weight:700;color:var(--text-2);margin-bottom:6px;text-transform:uppercase;letter-spacing:.8px;}
.ft-hero-balance{font-family:var(--font-mono);font-size:38px;font-weight:700;letter-spacing:-1px;color:var(--text);line-height:1;}
.ft-hero-balance sup{font-size:20px;margin-right:2px;vertical-align:top;margin-top:8px;}
.ft-hero-sub{display:flex;gap:20px;margin-top:20px;flex-wrap:wrap;}
.ft-hero-stat{display:flex;flex-direction:column;gap:2px;}
.ft-hero-stat-label{font-size:11px;color:var(--text-2);display:flex;align-items:center;gap:4px;font-weight:500;}
.ft-hero-stat-label svg{width:12px;height:12px;}
.ft-hero-stat-val{font-size:16px;font-weight:700;font-family:var(--font-mono);}
.ft-hero-stat-val.inc{color:var(--income);}.ft-hero-stat-val.exp{color:var(--expense);}
/* SECTION */
.ft-section{padding:0 16px;margin-bottom:16px;flex-shrink:0;}
.ft-section-header{display:flex;justify-content:space-between;align-items:center;margin-bottom:12px;}
.ft-section-title{font-size:16px;font-weight:700;letter-spacing:-.3px;}
.ft-section-link{font-size:13px;color:var(--accent);font-weight:700;cursor:pointer;border:none;background:none;padding:0;}
/* WALLETS */
.ft-wallets-scroll{display:flex;gap:12px;overflow-x:auto;padding:4px 16px 8px;scrollbar-width:none;flex-shrink:0;}
.ft-wallets-scroll::-webkit-scrollbar{display:none;}
.ft-wallet-card{min-width:168px;flex-shrink:0;border-radius:22px;padding:18px 16px 16px;background:var(--bg-card);border:1.5px solid var(--border);cursor:pointer;transition:transform var(--t);position:relative;overflow:hidden;}
.ft-wallet-card:active{transform:scale(.97);}
.ft-wallet-header{display:flex;align-items:center;justify-content:space-between;margin-bottom:12px;}
.ft-wallet-icon-wrap{width:38px;height:38px;border-radius:12px;display:flex;align-items:center;justify-content:center;}
.ft-wallet-icon-wrap svg{width:20px;height:20px;stroke:white;stroke-width:1.8;}
.ft-wallet-edit-btn{width:28px;height:28px;border-radius:50%;background:var(--input-bg);border:1px solid var(--border);display:flex;align-items:center;justify-content:center;cursor:pointer;}
.ft-wallet-edit-btn svg{width:13px;height:13px;stroke:var(--text-2);}
.ft-wallet-type{font-size:10px;color:var(--text-3);font-weight:700;text-transform:uppercase;letter-spacing:.8px;margin-bottom:4px;}
.ft-wallet-name{font-size:14px;font-weight:700;margin-bottom:10px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ft-wallet-balance{font-family:var(--font-mono);font-size:19px;font-weight:700;}
.ft-wallet-reserved{font-size:10px;color:var(--warn);font-weight:600;margin-top:4px;}
.ft-wallet-add{border:2px dashed var(--border);background:transparent;display:flex;flex-direction:column;align-items:center;justify-content:center;gap:6px;color:var(--text-3);}
.ft-wallet-add svg{width:24px;height:24px;stroke:var(--text-3);}
.ft-wallet-add span{font-size:12px;font-weight:600;}
/* BUDGET */
.ft-budget-item,.ft-goal-card{background:var(--bg-card);border:1.5px solid var(--border);border-radius:22px;padding:18px;margin-bottom:12px;}
.ft-budget-meta{display:flex;justify-content:space-between;align-items:flex-start;margin-bottom:10px;}
.ft-budget-name{font-size:15px;font-weight:700;}
.ft-budget-amounts{font-size:12px;color:var(--text-2);font-family:var(--font-mono);display:flex;justify-content:space-between;gap:8px;margin-top:8px;}
.ft-budget-track{height:8px;background:var(--input-bg);border-radius:999px;overflow:hidden;}
.ft-budget-fill{height:100%;border-radius:999px;transition:width .6s cubic-bezier(.4,0,.2,1);}
.ft-budget-footer{display:flex;justify-content:space-between;margin-top:8px;font-size:11px;color:var(--text-3);}
/* TRANSACTIONS */
.ft-txn-item{display:flex;align-items:center;gap:12px;padding:13px 0;border-bottom:1px solid var(--border);cursor:pointer;}
.ft-txn-item:last-child{border-bottom:none;}
.ft-txn-icon{width:44px;height:44px;border-radius:14px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.ft-txn-icon svg{width:20px;height:20px;stroke:white;stroke-width:1.8;}
.ft-txn-info{flex:1;min-width:0;}
.ft-txn-name{font-size:14px;font-weight:600;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ft-txn-meta{font-size:11px;color:var(--text-2);margin-top:2px;}
.ft-txn-amount{font-family:var(--font-mono);font-size:15px;font-weight:700;white-space:nowrap;}
.ft-txn-amount.inc{color:var(--income);}.ft-txn-amount.exp{color:var(--expense);}
.ft-txn-date-group{font-size:11px;font-weight:700;color:var(--text-3);letter-spacing:.8px;text-transform:uppercase;padding:12px 0 4px;border-bottom:1px solid var(--border);margin-bottom:4px;}
/* SCHEDULED */
.ft-scheduled-item{display:flex;align-items:center;gap:10px;padding:10px 12px;background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:14px;margin-bottom:8px;}
.ft-scheduled-item svg{width:18px;height:18px;stroke:var(--warn);flex-shrink:0;}
.ft-scheduled-info{flex:1;min-width:0;}
.ft-scheduled-name{font-size:13px;font-weight:600;}
.ft-scheduled-date{font-size:11px;color:var(--warn);}
.ft-scheduled-amount{font-family:var(--font-mono);font-size:13px;font-weight:700;color:var(--warn);}
.ft-realize-btn{padding:4px 10px;border-radius:8px;border:none;cursor:pointer;font-size:11px;font-weight:700;font-family:var(--font);background:rgba(34,197,94,.15);color:var(--income);}
/* GOALS */
.ft-goal-header{display:flex;align-items:flex-start;gap:12px;margin-bottom:16px;}
.ft-goal-title{font-size:15px;font-weight:700;}
.ft-goal-deadline{font-size:11px;color:var(--text-2);}
.ft-goal-progress{height:10px;background:var(--input-bg);border-radius:999px;overflow:hidden;margin-bottom:8px;}
.ft-goal-fill{height:100%;border-radius:999px;transition:width .6s cubic-bezier(.4,0,.2,1);}
.ft-goal-stats{display:flex;justify-content:space-between;font-size:12px;color:var(--text-2);font-family:var(--font-mono);}
.ft-goal-pct{font-weight:700;color:var(--text);}
.ft-goal-alloc-row{display:flex;gap:12px;margin-top:10px;padding:10px 12px;background:var(--input-bg);border-radius:12px;}
.ft-goal-alloc-stat{display:flex;flex-direction:column;gap:2px;}
.ft-goal-alloc-label{font-size:10px;color:var(--text-3);font-weight:700;text-transform:uppercase;letter-spacing:.5px;}
.ft-goal-alloc-val{font-size:13px;font-weight:700;font-family:var(--font-mono);}
/* INSIGHTS */
.ft-insight-card{background:var(--bg-card);border:1.5px solid var(--border);border-radius:22px;padding:18px;margin-bottom:12px;}
.ft-insight-title{font-size:11px;font-weight:700;color:var(--text-3);margin-bottom:14px;text-transform:uppercase;letter-spacing:.8px;}
.ft-donut-wrap{position:relative;display:flex;justify-content:center;margin-bottom:14px;}
.ft-donut-center{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;}
.ft-donut-center strong{font-size:17px;font-weight:700;font-family:var(--font-mono);}
.ft-donut-center span{font-size:10px;color:var(--text-2);display:block;}
.ft-legend{display:grid;grid-template-columns:1fr 1fr;gap:8px;}
.ft-legend-item{display:flex;align-items:center;gap:8px;}
.ft-legend-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
.ft-legend-name{color:var(--text-2);font-size:11px;}
.ft-legend-val{font-weight:700;font-size:12px;font-family:var(--font-mono);}
.ft-trend-bar-wrap{display:flex;align-items:flex-end;gap:6px;height:80px;margin-bottom:10px;}
.ft-trend-bar{flex:1;border-radius:6px 6px 0 0;transition:height .5s cubic-bezier(.4,0,.2,1);min-height:4px;}
.ft-trend-labels{display:flex;gap:6px;}
.ft-trend-label{flex:1;text-align:center;font-size:10px;color:var(--text-3);}
.ft-health{background:linear-gradient(135deg,var(--hero-from),var(--hero-to));border-radius:22px;padding:20px;margin-bottom:12px;display:flex;align-items:center;gap:16px;}
.ft-health-ring{position:relative;width:80px;height:80px;flex-shrink:0;}
.ft-health-ring svg{transform:rotate(-90deg);}
.ft-health-score{position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);font-family:var(--font-mono);font-size:20px;font-weight:700;}
.ft-health-info h3{font-size:16px;font-weight:700;margin-bottom:4px;}
.ft-health-info p{font-size:13px;color:var(--text-2);line-height:1.4;}
.ft-chips{display:flex;gap:8px;padding:0 0 12px;overflow-x:auto;scrollbar-width:none;flex-shrink:0;}
.ft-chips::-webkit-scrollbar{display:none;}
.ft-chip{padding:7px 16px;border-radius:999px;border:1.5px solid var(--border);font-size:13px;font-weight:700;color:var(--text-2);cursor:pointer;white-space:nowrap;transition:all var(--t);background:var(--bg-card);flex-shrink:0;}
.ft-chip.active{background:var(--accent);border-color:var(--accent);color:white;}
/* SETTINGS */
.ft-settings-section{margin-bottom:20px;}
.ft-settings-title{font-size:11px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.8px;padding:0 4px;margin-bottom:8px;}
.ft-settings-item{display:flex;align-items:center;justify-content:space-between;background:var(--bg-card);border:1.5px solid var(--border);padding:14px 16px;border-radius:16px;margin-bottom:6px;cursor:pointer;transition:background var(--t);}
.ft-settings-item:active{background:var(--bg-card-2);}
.ft-settings-item-left{display:flex;align-items:center;gap:12px;}
.ft-settings-icon{width:38px;height:38px;border-radius:12px;background:var(--input-bg);display:flex;align-items:center;justify-content:center;}
.ft-settings-icon svg{width:18px;height:18px;stroke:var(--accent);}
.ft-settings-item-text strong{font-size:14px;font-weight:700;display:block;}
.ft-settings-item-text span{font-size:12px;color:var(--text-2);}
.ft-settings-chevron{stroke:var(--text-3);width:16px;height:16px;}
.ft-theme-option{display:flex;align-items:center;gap:12px;padding:12px 14px;border-radius:14px;border:2px solid var(--border);cursor:pointer;transition:border-color var(--t);background:var(--bg-card);margin-bottom:8px;}
.ft-theme-option.selected{border-color:var(--accent);}
.ft-theme-option-icon{width:40px;height:40px;border-radius:12px;display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.ft-theme-option-icon svg{width:20px;height:20px;stroke:white;}
.ft-theme-option-text{flex:1;}
.ft-theme-option-text strong{font-size:14px;font-weight:700;display:block;}
.ft-theme-option-text span{font-size:12px;color:var(--text-2);}
.ft-theme-option-check{width:22px;height:22px;border-radius:50%;border:2px solid var(--border);display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.ft-theme-option.selected .ft-theme-option-check{background:var(--accent);border-color:var(--accent);}
.ft-theme-option.selected .ft-theme-option-check svg{display:block!important;}
.ft-accent-grid{display:flex;gap:10px;flex-wrap:wrap;}
.ft-accent-swatch{width:36px;height:36px;border-radius:50%;cursor:pointer;border:3px solid transparent;transition:border-color var(--t),transform var(--t);}
.ft-accent-swatch:active{transform:scale(.9);}
.ft-accent-swatch.selected{border-color:var(--text);}
/* PRO BADGE */
.ft-pro-badge{display:inline-flex;align-items:center;gap:4px;padding:3px 10px;border-radius:999px;background:linear-gradient(135deg,var(--accent),var(--accent-hover));color:white;font-size:11px;font-weight:800;letter-spacing:.5px;}
.ft-pro-gate{background:var(--bg-card);border:2px dashed var(--border);border-radius:22px;padding:28px 20px;text-align:center;margin-bottom:12px;}
.ft-pro-gate-icon{width:56px;height:56px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent-hover));display:flex;align-items:center;justify-content:center;margin:0 auto 12px;}
.ft-pro-gate-icon svg{width:28px;height:28px;stroke:white;stroke-width:1.8;}
.ft-pro-gate h3{font-size:16px;font-weight:800;margin-bottom:6px;}
.ft-pro-gate p{font-size:13px;color:var(--text-2);margin-bottom:16px;line-height:1.5;}
/* OFFLINE BANNER */
.ft-offline-banner{position:fixed;top:var(--sat);left:50%;transform:translateX(-50%) translateY(-100%);width:100%;max-width:430px;background:#f59e0b;color:white;font-size:13px;font-weight:700;text-align:center;padding:10px 20px;z-index:9999;transition:transform .3s ease;display:flex;align-items:center;justify-content:center;gap:8px;}
.ft-offline-banner.show{transform:translateX(-50%) translateY(0);}
.ft-offline-banner svg{width:16px;height:16px;stroke:white;flex-shrink:0;}
/* SYNC STATUS */
.ft-sync-status{position:fixed;bottom:calc(70px + var(--sab));right:16px;background:var(--bg-elevated);border:1px solid var(--border);border-radius:12px;padding:6px 12px;font-size:11px;font-weight:700;color:var(--text-2);display:flex;align-items:center;gap:6px;z-index:200;opacity:0;transition:opacity .3s;pointer-events:none;}
.ft-sync-status.show{opacity:1;}
.ft-sync-status svg{width:14px;height:14px;}
.ft-sync-dot{width:8px;height:8px;border-radius:50%;background:var(--income);}
.ft-sync-dot.pending{background:var(--warn);animation:pulse 1.2s infinite;}
@keyframes pulse{0%,100%{opacity:1;}50%{opacity:.4;}}
/* SHEETS */
.ft-overlay{position:fixed;inset:0;background:var(--overlay);z-index:200;opacity:0;pointer-events:none;transition:opacity var(--t);}
.ft-overlay.show{opacity:1;pointer-events:all;}
.ft-sheet{position:fixed;bottom:0;left:50%;transform:translateX(-50%) translateY(100%);width:100%;max-width:430px;background:var(--bg-elevated);border-radius:28px 28px 0 0;z-index:201;padding:16px 20px calc(32px + var(--sab));transition:transform .35s cubic-bezier(.4,0,.2,1);max-height:calc(92dvh - var(--sat));overflow-y:auto;overscroll-behavior:contain;-webkit-overflow-scrolling:touch;}
.ft-sheet.show{transform:translateX(-50%) translateY(0);}
.ft-sheet-handle{width:36px;height:4px;background:var(--border);border-radius:999px;margin:0 auto 20px;}
.ft-sheet-title{font-size:18px;font-weight:800;margin-bottom:20px;letter-spacing:-.3px;}
/* FORM */
.ft-form-group{margin-bottom:14px;}
.ft-form-label{font-size:11px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.8px;margin-bottom:6px;display:block;}
.ft-input{width:100%;padding:13px 14px;background:var(--input-bg);border:1.5px solid var(--border);border-radius:14px;font-family:var(--font);font-size:15px;color:var(--text);outline:none;transition:border-color var(--t);-webkit-appearance:none;}
.ft-input:focus{border-color:var(--accent);}
.ft-input::placeholder{color:var(--text-3);}
.ft-select{width:100%;padding:13px 14px;background:var(--input-bg);border:1.5px solid var(--border);border-radius:14px;font-family:var(--font);font-size:15px;color:var(--text);outline:none;-webkit-appearance:none;appearance:none;background-image:url("data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' width='16' height='16' fill='none' stroke='%2394a3b8' stroke-width='2' viewBox='0 0 24 24'%3E%3Cpath stroke-linecap='round' stroke-linejoin='round' d='M19 9l-7 7-7-7'/%3E%3C/svg%3E");background-repeat:no-repeat;background-position:right 14px center;padding-right:40px;}
.ft-select:focus{border-color:var(--accent);}
.ft-amount-prefix{position:relative;display:flex;align-items:center;background:var(--input-bg);border:1.5px solid var(--border);border-radius:14px;}
.ft-amount-prefix:focus-within{border-color:var(--accent);}
.ft-amount-prefix span{padding-left:14px;color:var(--text-3);font-family:var(--font-mono);font-size:16px;white-space:nowrap;}
.ft-amount-prefix input{border:none;background:transparent;font-family:var(--font-mono);font-size:22px;font-weight:700;color:var(--text);padding:13px 12px;flex:1;outline:none;width:0;}
.ft-form-row{display:flex;gap:10px;}
.ft-form-row .ft-form-group{flex:1;}
.ft-toggle{display:flex;align-items:center;gap:10px;padding:13px 14px;background:var(--input-bg);border:1.5px solid var(--border);border-radius:14px;cursor:pointer;}
.ft-toggle input[type=checkbox]{width:18px;height:18px;accent-color:var(--accent);}
.ft-toggle label{font-size:14px;font-weight:600;cursor:pointer;flex:1;}
/* BUTTONS */
.ft-btn{width:100%;padding:15px;border-radius:16px;border:none;font-family:var(--font);font-size:15px;font-weight:700;cursor:pointer;transition:transform var(--t),box-shadow var(--t);letter-spacing:-.1px;}
.ft-btn:active{transform:scale(.97);}
.ft-btn-primary{background:linear-gradient(135deg,var(--accent),var(--accent-hover));color:white;box-shadow:0 4px 16px rgba(var(--accent-rgb),.35);}
.ft-btn-secondary{background:var(--input-bg);color:var(--text);}
.ft-btn-danger{background:rgba(239,68,68,.12);color:#ef4444;}
.ft-btn-warn{background:rgba(245,158,11,.12);color:#f59e0b;}
.ft-btn-success{background:rgba(34,197,94,.12);color:#22c55e;}
.ft-btn-sm{padding:8px 14px;font-size:12px;width:auto;border-radius:10px;}
.ft-btn-row{display:flex;gap:10px;}
.ft-btn-row .ft-btn{flex:1;}
.ft-btn-pro{background:linear-gradient(135deg,var(--accent),var(--accent-hover));color:white;box-shadow:0 4px 20px rgba(var(--accent-rgb),.4);}
/* TYPE TABS */
.ft-type-tabs{display:flex;background:var(--input-bg);border-radius:14px;padding:4px;gap:4px;margin-bottom:20px;}
.ft-type-tab{flex:1;padding:10px;border-radius:10px;border:none;cursor:pointer;font-family:var(--font);font-size:13px;font-weight:700;background:transparent;color:var(--text-2);transition:all var(--t);}
.ft-type-tab.active{background:var(--bg-elevated);color:var(--text);box-shadow:var(--shadow-sm);}
.ft-type-tab.exp.active{color:var(--expense);}.ft-type-tab.inc.active{color:var(--income);}.ft-type-tab.tra.active{color:var(--transfer);}
/* CAT GRID */
.ft-cat-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:8px;margin-bottom:4px;}
.ft-cat-chip{display:flex;flex-direction:column;align-items:center;gap:5px;padding:10px 6px;border-radius:14px;border:2px solid var(--border);cursor:pointer;transition:all var(--t);background:var(--bg-card);}
.ft-cat-chip.selected{border-color:var(--accent);background:rgba(var(--accent-rgb),.1);}
.ft-cat-icon{width:36px;height:36px;border-radius:10px;display:flex;align-items:center;justify-content:center;}
.ft-cat-icon svg{width:18px;height:18px;stroke:white;stroke-width:1.8;}
.ft-cat-label{font-size:10px;font-weight:600;color:var(--text-2);text-align:center;line-height:1.2;}
/* LOADER / TOAST / EMPTY */
.ft-loader{display:flex;justify-content:center;padding:24px;}
.ft-spinner{width:28px;height:28px;border:3px solid var(--border);border-top-color:var(--accent);border-radius:50%;animation:spin .7s linear infinite;}
@keyframes spin{to{transform:rotate(360deg);}}
.ft-toast{position:fixed;top:calc(var(--sat) + 16px);left:50%;transform:translateX(-50%) translateY(-120px);background:var(--bg-elevated);border:1px solid var(--border);border-radius:16px;padding:12px 20px;font-size:14px;font-weight:600;box-shadow:var(--shadow);z-index:999;transition:transform .3s cubic-bezier(.4,0,.2,1);display:flex;align-items:center;gap:8px;max-width:320px;white-space:nowrap;}
.ft-toast.show{transform:translateX(-50%) translateY(0);}
.ft-toast.success svg{stroke:var(--income);}.ft-toast.error svg{stroke:var(--expense);}
.ft-toast svg{width:18px;height:18px;flex-shrink:0;}
.ft-empty{display:flex;flex-direction:column;align-items:center;justify-content:center;padding:40px 20px;gap:12px;color:var(--text-3);}
.ft-empty svg{width:52px;height:52px;opacity:.35;}
.ft-empty p{font-size:14px;text-align:center;line-height:1.5;}
/* ALLOC */
.ft-alloc-item{display:flex;align-items:center;gap:10px;padding:10px 12px;background:var(--input-bg);border-radius:12px;margin-bottom:8px;}
.ft-alloc-item-dot{width:9px;height:9px;border-radius:50%;flex-shrink:0;}
.ft-alloc-item-info{flex:1;min-width:0;}
.ft-alloc-item-name{font-size:12px;font-weight:600;}
.ft-alloc-item-meta{font-size:11px;color:var(--text-3);}
.ft-alloc-item-amount{font-family:var(--font-mono);font-size:13px;font-weight:700;}
.ft-alloc-item-actions{display:flex;gap:4px;}
.ft-alloc-item-actions button{border:none;background:none;cursor:pointer;font-size:11px;font-weight:700;font-family:var(--font);padding:3px 6px;border-radius:6px;}
.ft-alloc-chart-legend{display:flex;flex-direction:column;gap:8px;margin:12px 0;}
.ft-alloc-chart-item{display:flex;align-items:center;gap:8px;font-size:11px;color:var(--text-2);}
.ft-alloc-chart-dot{width:10px;height:10px;border-radius:50%;flex-shrink:0;}
.ft-alloc-chart-name{flex:1;min-width:0;white-space:nowrap;overflow:hidden;text-overflow:ellipsis;}
.ft-alloc-chart-val{font-family:var(--font-mono);font-weight:700;color:var(--text);}
/* INSTALL PROMPT */
.ft-install-prompt{position:fixed;bottom:calc(80px + var(--sab));left:50%;transform:translateX(-50%);width:calc(100% - 32px);max-width:398px;background:var(--bg-elevated);border:1.5px solid var(--border);border-radius:20px;padding:16px 18px;display:flex;align-items:center;gap:14px;box-shadow:var(--shadow);z-index:150;animation:fadeUp .4s ease;}
.ft-install-prompt-icon{width:48px;height:48px;border-radius:14px;background:linear-gradient(135deg,var(--accent),var(--accent-hover));display:flex;align-items:center;justify-content:center;flex-shrink:0;}
.ft-install-prompt-icon svg{width:26px;height:26px;stroke:white;}
.ft-install-prompt-text{flex:1;}
.ft-install-prompt-text strong{font-size:14px;font-weight:800;display:block;}
.ft-install-prompt-text span{font-size:12px;color:var(--text-2);}
.ft-install-prompt-actions{display:flex;flex-direction:column;gap:4px;}
.ft-install-yes{padding:7px 14px;border-radius:10px;border:none;background:var(--accent);color:white;font-size:12px;font-weight:700;cursor:pointer;font-family:var(--font);}
.ft-install-no{padding:5px;border:none;background:none;color:var(--text-3);font-size:11px;cursor:pointer;font-family:var(--font);}
*::-webkit-scrollbar{width:4px;}*::-webkit-scrollbar-thumb{background:var(--border);border-radius:999px;}
</style>
<div id="ft-app" data-theme="light">

<!-- OFFLINE BANNER -->
<div class="ft-offline-banner" id="ftOfflineBanner">
  <svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M18.364 5.636a9 9 0 010 12.728M15.536 8.464a5 5 0 010 7.072M6.343 17.657a9 9 0 010-12.728M9.172 15.536a5 5 0 010-7.072M12 12h.01"/></svg>
  You're offline — reconnect to sync changes
</div>

<!-- SYNC STATUS -->
<div class="ft-sync-status" id="ftSyncStatus">
  <div class="ft-sync-dot" id="ftSyncDot"></div>
  <span id="ftSyncMsg">Synced</span>
</div>

<!-- TOAST -->
<div class="ft-toast" id="ftToast">
  <svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
  <span id="ftToastMsg"></span>
</div>
<div class="ft-overlay" id="ftOverlay"></div>

<!-- ══ VIEW: HOME ══ -->
<div class="ft-view active" id="view-home">
  <div class="ft-topbar">
    <div><div style="font-size:11px;color:var(--text-2);font-weight:700;text-transform:uppercase;letter-spacing:.6px;">Good <?php echo ft_greeting();?></div><div class="ft-topbar-title"><?php echo esc_html($first_name);?></div></div>
    <div class="ft-topbar-actions">
      <div class="ft-icon-btn" onclick="ftProcessScheduled()" title="Sync">
        <svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
      </div>
    </div>
  </div>
  <div class="ft-hero">
    <div class="ft-hero-label">Total Balance</div>
    <div class="ft-hero-balance"><sup>₱</sup><span id="heroBalVal">—</span></div>
    <div class="ft-hero-sub">
      <div class="ft-hero-stat"><div class="ft-hero-stat-label"><svg fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M4.5 10.5L12 3m0 0l7.5 7.5M12 3v18"/></svg>Income</div><div class="ft-hero-stat-val inc" id="heroInc">₱—</div></div>
      <div class="ft-hero-stat"><div class="ft-hero-stat-label"><svg fill="none" viewBox="0 0 24 24" stroke-width="2.5"><path stroke-linecap="round" stroke-linejoin="round" d="M19.5 13.5L12 21m0 0l-7.5-7.5M12 21V3"/></svg>Expenses</div><div class="ft-hero-stat-val exp" id="heroExp">₱—</div></div>
      <div class="ft-hero-stat"><div class="ft-hero-stat-label">Savings</div><div class="ft-hero-stat-val" id="heroSav" style="color:var(--accent)">₱—</div></div>
    </div>
  </div>
  <div class="ft-section"><div class="ft-section-header"><div class="ft-section-title">Wallets</div><button class="ft-section-link" onclick="ftOpenAddWallet()">+ Add</button></div></div>
  <div class="ft-wallets-scroll" id="walletsList"><div class="ft-loader"><div class="ft-spinner"></div></div></div>
  <div class="ft-section" id="scheduledSection" style="display:none;"><div class="ft-section-header"><div class="ft-section-title">Upcoming</div></div><div id="scheduledList"></div></div>
  <div class="ft-section"><div class="ft-section-header"><div class="ft-section-title">Budgets</div><button class="ft-section-link" onclick="ftCheckProThen(()=>ftSwitchView('insights'))">See all</button></div><div id="homeBudgets"><div class="ft-loader"><div class="ft-spinner"></div></div></div></div>
  <div class="ft-section"><div class="ft-section-header"><div class="ft-section-title">Goals</div><button class="ft-section-link" onclick="ftCheckProThen(()=>ftSwitchView('insights'))">See all</button></div><div id="homeGoals"><div class="ft-loader"><div class="ft-spinner"></div></div></div></div>
  <div class="ft-section"><div class="ft-section-header"><div class="ft-section-title">Recent</div><button class="ft-section-link" onclick="ftSwitchView('txns')">See all</button></div><div id="homeRecentTxns"><div class="ft-loader"><div class="ft-spinner"></div></div></div></div>
</div>

<!-- ══ VIEW: TRANSACTIONS ══ -->
<div class="ft-view" id="view-txns">
  <div class="ft-topbar"><div class="ft-topbar-title">Transactions</div>
    <div class="ft-topbar-actions"><div class="ft-icon-btn" onclick="document.getElementById('txnFilterMonth').showPicker?.()??document.getElementById('txnFilterMonth').click()"><svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 4a1 1 0 011-1h16a1 1 0 011 1v2.586a1 1 0 01-.293.707l-6.414 6.414a1 1 0 00-.293.707V17l-4 4v-6.586a1 1 0 00-.293-.707L3.293 7.293A1 1 0 013 6.586V4z"/></svg></div></div>
  </div>
  <div style="padding:0 16px 8px;flex-shrink:0;"><input type="month" id="txnFilterMonth" class="ft-input" style="font-size:14px;padding:10px 14px;" value="<?php echo date('Y-m');?>" onchange="ftLoadTransactions()"></div>
  <div class="ft-section" style="flex:1;"><div id="txnsList"><div class="ft-loader"><div class="ft-spinner"></div></div></div></div>
</div>

<!-- ══ VIEW: INSIGHTS (Pro-gated) ══ -->
<div class="ft-view" id="view-insights">
  <div class="ft-topbar"><div class="ft-topbar-title">Insights</div></div>
  <div class="ft-section">
    <div class="ft-health"><div class="ft-health-ring"><svg width="80" height="80" viewBox="0 0 80 80"><circle cx="40" cy="40" r="34" fill="none" stroke="rgba(var(--accent-rgb),.12)" stroke-width="8"/><circle cx="40" cy="40" r="34" fill="none" stroke="var(--accent)" stroke-width="8" stroke-dasharray="213.63" stroke-dashoffset="213.63" stroke-linecap="round" id="healthRing" style="transition:stroke-dashoffset 1s cubic-bezier(.4,0,.2,1)"/></svg><div class="ft-health-score" id="healthScore">—</div></div>
    <div class="ft-health-info"><h3>Financial Health</h3><p id="healthMsg">Analyzing...</p></div></div>
  </div>
  <div id="insightsProGate"></div>
  <div id="insightsProContent" style="display:none;">
    <div class="ft-section"><div class="ft-chips" id="insightChips">
      <div class="ft-chip active" data-period="this_month" onclick="ftSelectInsightPeriod(this)">This Month</div>
      <div class="ft-chip" data-period="last_month" onclick="ftSelectInsightPeriod(this)">Last Month</div>
      <div class="ft-chip" data-period="3_months" onclick="ftSelectInsightPeriod(this)">3 Months</div>
      <div class="ft-chip" data-period="6_months" onclick="ftSelectInsightPeriod(this)">6 Months</div>
      <div class="ft-chip" data-period="year" onclick="ftSelectInsightPeriod(this)">This Year</div>
    </div></div>
    <div class="ft-section">
      <div class="ft-insight-card"><div class="ft-insight-title">Spending by Category</div><div class="ft-donut-wrap"><canvas id="donutChart" width="180" height="180" style="max-width:180px;"></canvas><div class="ft-donut-center"><strong id="donutTotal">₱—</strong><span>total</span></div></div><div class="ft-legend" id="donutLegend"></div></div>
      <div class="ft-insight-card"><div class="ft-insight-title">6-Month Trend</div><div class="ft-trend-bar-wrap" id="trendBars"></div><div class="ft-trend-labels" id="trendLabels"></div></div>
      <div class="ft-insight-card"><div class="ft-insight-title">Period Summary</div><div id="insightStatsBody"><div class="ft-loader"><div class="ft-spinner"></div></div></div></div>
    </div>
    <div class="ft-topbar"><div class="ft-topbar-title">Budgets &amp; Goals</div>
      <div class="ft-topbar-actions"><button class="ft-section-link" onclick="ftShowSheet('budgetSheet')">+ Budget</button><button class="ft-section-link" style="margin-left:8px;" onclick="ftShowSheet('goalSheet')">+ Goal</button></div>
    </div>
    <div class="ft-section"><div class="ft-section-header"><div class="ft-section-title">Budgets</div></div><div id="budgetsList"><div class="ft-loader"><div class="ft-spinner"></div></div></div></div>
    <div class="ft-section"><div class="ft-section-header"><div class="ft-section-title">Goals</div></div><div id="goalsList"><div class="ft-loader"><div class="ft-spinner"></div></div></div></div>
  </div>
</div>

<!-- ══ VIEW: SETTINGS ══ -->
<div class="ft-view" id="view-profile">
  <div class="ft-topbar"><div class="ft-topbar-title">Settings</div></div>
  <div style="padding:0 16px 24px;display:flex;align-items:center;gap:16px;flex-shrink:0;">
    <div id="profileAvatar" style="width:64px;height:64px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent-hover));display:flex;align-items:center;justify-content:center;font-size:24px;font-weight:800;color:white;flex-shrink:0;"><?php echo strtoupper(substr($first_name,0,1));?></div>
    <div><div style="font-size:18px;font-weight:800;"><?php echo esc_html($user->display_name);?></div>
    <div style="font-size:13px;color:var(--text-2);"><?php echo esc_html($user->user_email);?></div>
    <div id="planBadgeWrap" style="margin-top:6px;"></div></div>
  </div>
  <div style="padding:0 16px;">
    <!-- Subscription -->
    <div class="ft-settings-section">
      <div class="ft-settings-title">Subscription</div>
      <div id="subscriptionBlock"></div>
    </div>
    <!-- PWA Install -->
    <div class="ft-settings-section" id="pwaInstallSection" style="display:none;">
      <div class="ft-settings-title">App</div>
      <div class="ft-settings-item" onclick="ftInstallPWA()" id="pwaInstallItem">
        <div class="ft-settings-item-left"><div class="ft-settings-icon"><svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg></div><div class="ft-settings-item-text"><strong>Install App</strong><span>Add to Home Screen</span></div></div>
        <svg class="ft-settings-chevron" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg>
      </div>
    </div>
    <!-- Theme -->
    <div class="ft-settings-section">
      <div class="ft-settings-title">Theme</div>
      <div class="ft-theme-option" id="themeOptLight" onclick="ftSetTheme('light')">
        <div class="ft-theme-option-icon" style="background:linear-gradient(135deg,#e0e7ff,#ddd6fe)"><svg fill="none" viewBox="0 0 24 24" stroke="#6366f1"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364-6.364l-.707.707M6.343 17.657l-.707.707M17.657 17.657l-.707-.707M6.343 6.343l-.707-.707M12 7a5 5 0 100 10A5 5 0 0012 7z"/></svg></div>
        <div class="ft-theme-option-text"><strong>Light</strong><span>Clean &amp; bright</span></div>
        <div class="ft-theme-option-check"><svg fill="none" viewBox="0 0 24 24" style="display:none;width:14px;height:14px;stroke:white"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></div>
      </div>
      <div class="ft-theme-option" id="themeOptDark" onclick="ftSetTheme('dark')">
        <div class="ft-theme-option-icon" style="background:linear-gradient(135deg,#1e1b4b,#0f172a)"><svg fill="none" viewBox="0 0 24 24" stroke="white"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z"/></svg></div>
        <div class="ft-theme-option-text"><strong>Dark</strong><span>High contrast dark</span></div>
        <div class="ft-theme-option-check"><svg fill="none" viewBox="0 0 24 24" style="display:none;width:14px;height:14px;stroke:white"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="3" d="M5 13l4 4L19 7"/></svg></div>
      </div>
    </div>
    <!-- Accent -->
    <div class="ft-settings-section">
      <div class="ft-settings-title">Accent Color</div>
      <div style="background:var(--bg-card);border:1.5px solid var(--border);border-radius:16px;padding:14px 16px;">
        <div class="ft-accent-grid" id="accentGrid">
          <?php foreach(['#ef4444','#dc2626','#f97316','#f59e0b','#22c55e','#14b8a6','#3b82f6','#06b6d4','#6366f1','#8b5cf6','#ec4899'] as $ac): ?>
          <div class="ft-accent-swatch" style="background:<?php echo $ac;?>;" data-color="<?php echo $ac;?>" onclick="ftSetAccent('<?php echo $ac;?>')"></div>
          <?php endforeach; ?>
        </div>
      </div>
    </div>
    <!-- Finance -->
    <div class="ft-settings-section">
      <div class="ft-settings-title">Finance</div>
      <div class="ft-settings-item" onclick="ftOpenAddWallet()"><div class="ft-settings-item-left"><div class="ft-settings-icon"><svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg></div><div class="ft-settings-item-text"><strong>Add Wallet</strong><span>Cash, Bank, E-wallet, Credit</span></div></div><svg class="ft-settings-chevron" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></div>
      <div class="ft-settings-item" onclick="ftShowSheet('categorySheet')"><div class="ft-settings-item-left"><div class="ft-settings-icon"><svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg></div><div class="ft-settings-item-text"><strong>Categories</strong><span>Add &amp; edit</span></div></div><svg class="ft-settings-chevron" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></div>
      <div class="ft-settings-item" onclick="ftCheckProThen(()=>ftShowSheet('recurSheet'))"><div class="ft-settings-item-left"><div class="ft-settings-icon"><svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg></div><div class="ft-settings-item-text"><strong>Recurring</strong><span>Subscriptions &amp; salary <span class="ft-pro-badge">PRO</span></span></div></div><svg class="ft-settings-chevron" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></div>
    </div>
    <!-- Account -->
    <div class="ft-settings-section">
      <div class="ft-settings-title">Account</div>
      <div class="ft-settings-item" onclick="window.location.href='<?php echo wp_logout_url(home_url()); ?>'"><div class="ft-settings-item-left"><div class="ft-settings-icon"><svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1"/></svg></div><div class="ft-settings-item-text"><strong style="color:#ef4444;">Sign Out</strong></div></div><svg class="ft-settings-chevron" fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5l7 7-7 7"/></svg></div>
    </div>
  </div>
</div>

<!-- NAV -->
<nav class="ft-nav">
  <div class="ft-nav-item active" id="nav-home" onclick="ftSwitchView('home')"><svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 12l2-2m0 0l7-7 7 7M5 10v10a1 1 0 001 1h3m10-11l2 2m-2-2v10a1 1 0 01-1 1h-3m-6 0a1 1 0 001-1v-4a1 1 0 011-1h2a1 1 0 011 1v4a1 1 0 001 1m-6 0h6"/></svg><span>Home</span></div>
  <div class="ft-nav-item" id="nav-txns" onclick="ftSwitchView('txns')"><svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg><span>Records</span></div>
  <div class="ft-nav-fab"><button class="ft-fab-btn" onclick="ftShowSheet('addTxnSheet')"><svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" d="M12 4v16m8-8H4"/></svg></button></div>
  <div class="ft-nav-item" id="nav-insights" onclick="ftSwitchView('insights')"><svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 8v8m-4-5v5m-4-2v2m-2 4h12a2 2 0 002-2V6a2 2 0 00-2-2H6a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg><span>Insights</span></div>
  <div class="ft-nav-item" id="nav-profile" onclick="ftSwitchView('profile')"><svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10.325 4.317c.426-1.756 2.924-1.756 3.35 0a1.724 1.724 0 002.573 1.066c1.543-.94 3.31.826 2.37 2.37a1.724 1.724 0 001.065 2.572c1.756.426 1.756 2.924 0 3.35a1.724 1.724 0 00-1.066 2.573c.94 1.543-.826 3.31-2.37 2.37a1.724 1.724 0 00-2.572 1.065c-.426 1.756-2.924 1.756-3.35 0a1.724 1.724 0 00-2.573-1.066c-1.543.94-3.31-.826-2.37-2.37a1.724 1.724 0 00-1.065-2.572c-1.756-.426-1.756-2.924 0-3.35a1.724 1.724 0 001.066-2.573c-.94-1.543.826-3.31 2.37-2.37.996.608 2.296.07 2.572-1.065z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/></svg><span>Settings</span></div>
</nav>

<!-- ══ SHEETS ══ -->
<!-- ADD/EDIT TRANSACTION -->
<div class="ft-sheet" id="addTxnSheet">
  <div class="ft-sheet-handle"></div>
  <input type="hidden" id="editTxnId" value="">
  <div class="ft-type-tabs">
    <button class="ft-type-tab exp active" onclick="ftSelectType('expense',this)">Expense</button>
    <button class="ft-type-tab inc" onclick="ftSelectType('income',this)">Income</button>
    <button class="ft-type-tab tra" onclick="ftSelectType('transfer',this)">Transfer</button>
  </div>
  <div class="ft-form-group"><label class="ft-form-label">Amount</label><div class="ft-amount-prefix"><span>₱</span><input type="number" id="txnAmount" placeholder="0.00" step="0.01" min="0" inputmode="decimal"></div></div>
  <div class="ft-form-group" id="txnCatWrap"><label class="ft-form-label">Category</label><div class="ft-cat-grid" id="txnCatGrid"><div class="ft-loader"><div class="ft-spinner"></div></div></div></div>
  <div class="ft-form-group"><label class="ft-form-label">From Wallet</label><select class="ft-select" id="txnWallet"></select></div>
  <div class="ft-form-group" id="txnToWalletWrap" style="display:none;"><label class="ft-form-label">To Wallet</label><select class="ft-select" id="txnToWallet"></select></div>
  <div class="ft-form-row">
    <div class="ft-form-group"><label class="ft-form-label">Date</label><input type="date" class="ft-input" id="txnDate" value="<?php echo date('Y-m-d');?>"></div>
    <div class="ft-form-group"><label class="ft-form-label">Realize On <span style="font-size:9px;color:var(--text-3);text-transform:none;letter-spacing:0;">(blank=now)</span></label><input type="date" class="ft-input" id="txnRealizeDate"></div>
  </div>
  <div style="font-size:11px;color:var(--text-3);margin:-8px 0 12px;line-height:1.5;">Future realize date = pending until that date.</div>
  <div class="ft-form-group"><label class="ft-form-label">Note</label><input type="text" class="ft-input" id="txnNote" placeholder="e.g. Lunch, Salary deposit"></div>
  <button class="ft-btn ft-btn-primary" id="saveTxnBtn" onclick="ftSaveTransaction()">Save Transaction</button>
  <button class="ft-btn ft-btn-danger" id="deleteTxnBtn" style="display:none;margin-top:8px;" onclick="ftDeleteTxnFromEdit()">Delete</button>
</div>

<!-- ADD/EDIT WALLET -->
<div class="ft-sheet" id="walletSheet">
  <div class="ft-sheet-handle"></div>
  <div class="ft-sheet-title" id="walletSheetTitle">Add Wallet</div>
  <input type="hidden" id="editWalletId" value="">
  <div class="ft-form-group"><label class="ft-form-label">Name</label><input type="text" class="ft-input" id="wName" placeholder="e.g. BDO Savings"></div>
  <div class="ft-form-row">
    <div class="ft-form-group"><label class="ft-form-label">Type</label><select class="ft-select" id="wType"><option value="cash">Cash</option><option value="bank">Bank</option><option value="ewallet">E-Wallet</option><option value="credit">Credit</option></select></div>
    <div class="ft-form-group" id="wBalanceWrap"><label class="ft-form-label">Balance</label><div class="ft-amount-prefix"><span>₱</span><input type="number" id="wBalance" placeholder="0.00" step="0.01" min="0" inputmode="decimal"></div></div>
  </div>
  <div class="ft-form-group"><label class="ft-form-label">Color</label><div style="display:flex;gap:8px;flex-wrap:wrap;" id="wColorPicker"><?php foreach(['#6366f1','#f59e0b','#3b82f6','#8b5cf6','#ef4444','#22c55e','#ec4899','#14b8a6','#f97316'] as $c): ?><div onclick="ftSelectColor(this,'<?php echo $c;?>')" data-color="<?php echo $c;?>" style="width:32px;height:32px;border-radius:50%;background:<?php echo $c;?>;cursor:pointer;border:3px solid <?php echo $c==='#6366f1'?'white':'transparent';?>;transition:border-color .2s;"></div><?php endforeach;?></div><input type="hidden" id="wColor" value="#6366f1"></div>
  <div class="ft-toggle" onclick="document.getElementById('wInclude').checked=!document.getElementById('wInclude').checked"><input type="checkbox" id="wInclude" checked><label for="wInclude">Include in total balance</label></div>
  <div style="margin-top:14px;">
    <button class="ft-btn ft-btn-primary" id="saveWalletBtn" onclick="ftSaveWallet()">Add Wallet</button>
    <button class="ft-btn ft-btn-danger" id="deleteWalletBtn" style="display:none;margin-top:8px;" onclick="ftDeleteWallet()">Delete Wallet</button>
  </div>
</div>

<!-- ADD BUDGET (Pro) -->
<div class="ft-sheet" id="budgetSheet">
  <div class="ft-sheet-handle"></div>
  <div class="ft-sheet-title" id="budgetSheetTitle">Add Budget</div>
  <input type="hidden" id="editBudgetId" value="">
  <div class="ft-form-group"><label class="ft-form-label">Name</label><input type="text" class="ft-input" id="bName" placeholder="e.g. Food Budget"></div>
  <div class="ft-form-group"><label class="ft-form-label">Category (optional)</label><select class="ft-select" id="bCategory"><option value="">All Expenses</option></select></div>
  <div class="ft-form-row">
    <div class="ft-form-group"><label class="ft-form-label">Amount</label><div class="ft-amount-prefix"><span>₱</span><input type="number" id="bAmount" placeholder="0.00" step="0.01" inputmode="decimal"></div></div>
    <div class="ft-form-group"><label class="ft-form-label">Period</label><select class="ft-select" id="bPeriod"><option value="monthly">Monthly</option><option value="weekly">Weekly</option><option value="yearly">Yearly</option></select></div>
  </div>
  <div class="ft-form-group"><label class="ft-form-label">Alert At (%)</label><input type="number" class="ft-input" id="bAlert" value="80" min="1" max="100"></div>
  <button class="ft-btn ft-btn-primary" id="saveBudgetBtn" onclick="ftSaveBudget()">Save Budget</button>
  <button class="ft-btn ft-btn-danger" id="deleteBudgetBtn" style="display:none;margin-top:8px;" onclick="ftDeleteBudgetFromEdit()">Delete</button>
</div>

<!-- ADD GOAL (Pro) -->
<div class="ft-sheet" id="goalSheet">
  <div class="ft-sheet-handle"></div>
  <div class="ft-sheet-title" id="goalSheetTitle">Add Goal</div>
  <input type="hidden" id="editGoalId" value="">
  <div class="ft-form-group"><label class="ft-form-label">Name</label><input type="text" class="ft-input" id="gName" placeholder="e.g. Emergency Fund"></div>
  <div class="ft-form-row">
    <div class="ft-form-group"><label class="ft-form-label">Target</label><div class="ft-amount-prefix"><span>₱</span><input type="number" id="gTarget" placeholder="0.00" step="0.01" inputmode="decimal"></div></div>
    <div class="ft-form-group"><label class="ft-form-label">Current</label><div class="ft-amount-prefix"><span>₱</span><input type="number" id="gCurrent" placeholder="0.00" step="0.01" inputmode="decimal"></div></div>
  </div>
  <div class="ft-form-group"><label class="ft-form-label">Deadline (optional)</label><input type="date" class="ft-input" id="gDeadline"></div>
  <div class="ft-form-group"><label class="ft-form-label">Color</label><div style="display:flex;gap:8px;flex-wrap:wrap;"><?php foreach(['#10b981','#6366f1','#f59e0b','#ef4444','#3b82f6','#8b5cf6','#ec4899'] as $c): ?><div onclick="ftSelectGColor(this,'<?php echo $c;?>')" data-color="<?php echo $c;?>" style="width:32px;height:32px;border-radius:50%;background:<?php echo $c;?>;cursor:pointer;border:3px solid <?php echo $c==='#10b981'?'white':'transparent';?>;transition:border-color .2s;"></div><?php endforeach;?><input type="hidden" id="gColor" value="#10b981"></div></div>
  <button class="ft-btn ft-btn-primary" id="saveGoalBtn" onclick="ftSaveGoal()">Save Goal</button>
  <button class="ft-btn ft-btn-danger" id="deleteGoalBtn" style="display:none;margin-top:8px;" onclick="ftDeleteGoalFromEdit()">Delete</button>
</div>

<!-- GOAL ALLOCATION -->
<div class="ft-sheet" id="goalAllocSheet">
  <div class="ft-sheet-handle"></div>
  <div class="ft-sheet-title" id="goalAllocTitle">Allocate to Goal</div>
  <input type="hidden" id="allocGoalId" value="">
  <div id="allocPieWrap" style="display:flex;justify-content:center;margin-bottom:16px;position:relative;">
    <canvas id="allocPieChart" width="160" height="160" style="max-width:160px;"></canvas>
    <div style="position:absolute;top:50%;left:50%;transform:translate(-50%,-50%);text-align:center;"><strong id="allocPieTotal" style="font-family:var(--font-mono);font-size:16px;">₱0</strong><span style="font-size:10px;color:var(--text-2);display:block;">allocated</span></div>
  </div>
  <div style="background:var(--input-bg);border-radius:16px;padding:14px;margin-bottom:16px;">
    <div style="font-size:12px;font-weight:700;color:var(--text-2);text-transform:uppercase;letter-spacing:.5px;margin-bottom:12px;">Add Allocation</div>
    <div class="ft-form-group"><label class="ft-form-label">From Wallet</label><select class="ft-select" id="allocWallet"></select></div>
    <div class="ft-form-group"><label class="ft-form-label">Amount</label><div class="ft-amount-prefix"><span>₱</span><input type="number" id="allocAmount" placeholder="0.00" step="0.01" inputmode="decimal"></div></div>
    <div class="ft-form-group"><label class="ft-form-label">Plan Date</label><input type="date" class="ft-input" id="allocPlanDate" value="<?php echo date('Y-m-d');?>"></div>
    <div class="ft-form-group"><label class="ft-form-label">Note (optional)</label><input type="text" class="ft-input" id="allocNote" placeholder="e.g. Monthly savings"></div>
    <div class="ft-btn-row">
      <button class="ft-btn ft-btn-warn ft-btn-sm" style="flex:1;" onclick="ftAllocateGoal(0)">Plan Only</button>
      <button class="ft-btn ft-btn-success ft-btn-sm" style="flex:1;" onclick="ftAllocateGoal(1)">Move Funds</button>
    </div>
    <div style="font-size:10px;color:var(--text-3);margin-top:8px;line-height:1.5;"><strong style="color:var(--warn);">Plan Only</strong> — reserve. <strong style="color:var(--income);">Move Funds</strong> — deducts wallet.</div>
  </div>
  <div style="font-size:11px;font-weight:700;color:var(--text-3);text-transform:uppercase;letter-spacing:.5px;margin-bottom:8px;">Allocations</div>
  <div id="allocHistoryList"></div>
</div>

<!-- EDIT ALLOCATION -->
<div class="ft-sheet" id="editAllocSheet">
  <div class="ft-sheet-handle"></div>
  <div class="ft-sheet-title">Edit Allocation</div>
  <input type="hidden" id="editAllocId" value="">
  <div class="ft-form-group"><label class="ft-form-label">Amount</label><div class="ft-amount-prefix"><span>₱</span><input type="number" id="editAllocAmount" step="0.01" inputmode="decimal"></div></div>
  <div class="ft-form-group"><label class="ft-form-label">Note</label><input type="text" class="ft-input" id="editAllocNote"></div>
  <div class="ft-btn-row"><button class="ft-btn ft-btn-secondary" onclick="ftHideSheet('editAllocSheet')">Cancel</button><button class="ft-btn ft-btn-primary" onclick="ftUpdateAllocation()">Save</button></div>
  <button class="ft-btn ft-btn-danger" style="margin-top:8px;" onclick="ftDeleteAllocation()">Remove</button>
</div>

<!-- CATEGORY MANAGER -->
<div class="ft-sheet" id="categorySheet">
  <div class="ft-sheet-handle"></div>
  <div class="ft-sheet-title">Categories</div>
  <div id="catManagerList" style="margin-bottom:16px;"></div>
  <hr style="border:none;border-top:1px solid var(--border);margin:12px 0;">
  <div style="font-size:15px;font-weight:800;margin-bottom:12px;">Add Category</div>
  <div class="ft-form-row">
    <div class="ft-form-group" style="flex:2;"><label class="ft-form-label">Name</label><input type="text" class="ft-input" id="catName" placeholder="e.g. Coffee"></div>
    <div class="ft-form-group"><label class="ft-form-label">Type</label><select class="ft-select" id="catType"><option value="expense">Expense</option><option value="income">Income</option><option value="both">Both</option></select></div>
  </div>
  <div class="ft-form-group"><label class="ft-form-label">Color</label><div style="display:flex;gap:8px;flex-wrap:wrap;" id="catColorPicker"><?php foreach(['#6366f1','#f59e0b','#3b82f6','#8b5cf6','#ef4444','#22c55e','#ec4899','#14b8a6','#f97316','#f43f5e'] as $c): ?><div onclick="ftSelectCatColor(this,'<?php echo $c;?>')" data-color="<?php echo $c;?>" style="width:28px;height:28px;border-radius:50%;background:<?php echo $c;?>;cursor:pointer;border:2px solid <?php echo $c==='#6366f1'?'white':'transparent';?>;transition:border-color .2s;"></div><?php endforeach;?></div><input type="hidden" id="catColor" value="#6366f1"></div>
  <button class="ft-btn ft-btn-primary" onclick="ftSaveCategory()">Add Category</button>
</div>

<!-- EDIT CATEGORY -->
<div class="ft-sheet" id="editCatSheet">
  <div class="ft-sheet-handle"></div>
  <div class="ft-sheet-title">Edit Category</div>
  <input type="hidden" id="editCatId" value="">
  <div class="ft-form-row">
    <div class="ft-form-group" style="flex:2;"><label class="ft-form-label">Name</label><input type="text" class="ft-input" id="editCatName"></div>
    <div class="ft-form-group"><label class="ft-form-label">Type</label><select class="ft-select" id="editCatType"><option value="expense">Expense</option><option value="income">Income</option><option value="both">Both</option></select></div>
  </div>
  <div class="ft-form-group"><label class="ft-form-label">Color</label><div style="display:flex;gap:8px;flex-wrap:wrap;" id="editCatColorPicker"></div><input type="hidden" id="editCatColor" value="#6366f1"></div>
  <div class="ft-btn-row"><button class="ft-btn ft-btn-secondary" onclick="ftHideSheet('editCatSheet')">Cancel</button><button class="ft-btn ft-btn-primary" onclick="ftUpdateCategory()">Save</button></div>
</div>

<!-- RECURRING (Pro) -->
<div class="ft-sheet" id="recurSheet">
  <div class="ft-sheet-handle"></div>
  <div class="ft-sheet-title">Recurring <span class="ft-pro-badge">PRO</span></div>
  <div id="recurList" style="margin-bottom:16px;"></div>
  <hr style="border:none;border-top:1px solid var(--border);margin:12px 0;">
  <div style="font-size:15px;font-weight:800;margin-bottom:12px;">Add Recurring</div>
  <div class="ft-form-group"><label class="ft-form-label">Name</label><input type="text" class="ft-input" id="rName" placeholder="e.g. Netflix"></div>
  <div class="ft-form-row">
    <div class="ft-form-group"><label class="ft-form-label">Type</label><select class="ft-select" id="rType"><option value="expense">Expense</option><option value="income">Income</option></select></div>
    <div class="ft-form-group"><label class="ft-form-label">Frequency</label><select class="ft-select" id="rFreq"><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly" selected>Monthly</option><option value="yearly">Yearly</option></select></div>
  </div>
  <div class="ft-form-group"><label class="ft-form-label">Amount</label><div class="ft-amount-prefix"><span>₱</span><input type="number" id="rAmount" placeholder="0.00" step="0.01" inputmode="decimal"></div></div>
  <div class="ft-form-group"><label class="ft-form-label">Wallet</label><select class="ft-select" id="rWallet"></select></div>
  <div class="ft-form-group"><label class="ft-form-label">Next Date</label><input type="date" class="ft-input" id="rNextDate" value="<?php echo date('Y-m-d');?>"></div>
  <button class="ft-btn ft-btn-primary" onclick="ftSaveRecurrence()">Save Recurring</button>
</div>

<!-- EDIT RECURRING -->
<div class="ft-sheet" id="editRecurSheet">
  <div class="ft-sheet-handle"></div>
  <div class="ft-sheet-title">Edit Recurring</div>
  <input type="hidden" id="editRecurId" value="">
  <div class="ft-form-group"><label class="ft-form-label">Name</label><input type="text" class="ft-input" id="editRName"></div>
  <div class="ft-form-row">
    <div class="ft-form-group"><label class="ft-form-label">Type</label><select class="ft-select" id="editRType"><option value="expense">Expense</option><option value="income">Income</option></select></div>
    <div class="ft-form-group"><label class="ft-form-label">Frequency</label><select class="ft-select" id="editRFreq"><option value="daily">Daily</option><option value="weekly">Weekly</option><option value="monthly">Monthly</option><option value="yearly">Yearly</option></select></div>
  </div>
  <div class="ft-form-group"><label class="ft-form-label">Amount</label><div class="ft-amount-prefix"><span>₱</span><input type="number" id="editRAmount" step="0.01" inputmode="decimal"></div></div>
  <div class="ft-form-group"><label class="ft-form-label">Wallet</label><select class="ft-select" id="editRWallet"></select></div>
  <div class="ft-form-group"><label class="ft-form-label">Next Date</label><input type="date" class="ft-input" id="editRNextDate"></div>
  <div class="ft-btn-row"><button class="ft-btn ft-btn-secondary" onclick="ftHideSheet('editRecurSheet')">Cancel</button><button class="ft-btn ft-btn-primary" onclick="ftUpdateRecurrence()">Save</button></div>
  <button class="ft-btn ft-btn-danger" style="margin-top:8px;" onclick="ftDeleteRecurFromEdit()">Delete</button>
</div>

<!-- PRO UPGRADE SHEET -->
<div class="ft-sheet" id="proSheet">
  <div class="ft-sheet-handle"></div>
  <div style="text-align:center;margin-bottom:24px;">
    <div style="width:72px;height:72px;border-radius:50%;background:linear-gradient(135deg,var(--accent),var(--accent-hover));display:flex;align-items:center;justify-content:center;margin:0 auto 14px;"><svg width="36" height="36" fill="none" viewBox="0 0 24 24" stroke="white"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3l14 9-14 9V3z"/></svg></div>
    <div style="font-size:24px;font-weight:800;letter-spacing:-.5px;">Finance Tracker <span style="background:linear-gradient(135deg,var(--accent),var(--accent-hover));-webkit-background-clip:text;-webkit-text-fill-color:transparent;">Pro</span></div>
    <div style="font-size:14px;color:var(--text-2);margin-top:6px;">One-time payment · Lifetime access</div>
    <div style="font-size:36px;font-weight:800;margin-top:16px;font-family:var(--font-mono);">₱<?php echo esc_html(number_format($pro_price, 0));?></div>
  </div>
  <div style="display:flex;flex-direction:column;gap:10px;margin-bottom:24px;">
    <?php foreach($pro_features as $feature): $title=$feature['title']; $desc=$feature['desc']; $color=$feature['color']; ?>
    <div style="display:flex;align-items:center;gap:12px;padding:12px 14px;background:var(--input-bg);border-radius:14px;">
      <div style="width:36px;height:36px;border-radius:10px;background:<?php echo $color;?>20;display:flex;align-items:center;justify-content:center;flex-shrink:0;"><svg width="18" height="18" fill="none" viewBox="0 0 24 24" stroke="<?php echo $color;?>"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg></div>
      <div><div style="font-size:14px;font-weight:700;"><?php echo esc_html($title);?></div><div style="font-size:12px;color:var(--text-2);"><?php echo esc_html($desc);?></div></div>
    </div>
    <?php endforeach;?>
  </div>
  <button class="ft-btn ft-btn-pro" onclick="ftStartProCheckout()" id="proCheckoutBtn">Upgrade Now — ₱<?php echo esc_html(number_format($pro_price, 0));?></button>
  <div style="font-size:11px;color:var(--text-3);text-align:center;margin-top:12px;">Secure payment · No subscription · No hidden fees</div>
</div>

<!-- INSTALL PROMPT (shown dynamically) -->
<div class="ft-install-prompt" id="ftInstallPrompt" style="display:none;">
  <div class="ft-install-prompt-icon"><svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg></div>
  <div class="ft-install-prompt-text"><strong>Install Finance Tracker</strong><span>Add to Home Screen for the best experience</span></div>
  <div class="ft-install-prompt-actions"><button class="ft-install-yes" onclick="ftInstallPWA()">Install</button><button class="ft-install-no" onclick="ftDismissInstall()">Later</button></div>
</div>

<script>
// ══════════════════════════════════════════════════════════════
// CONSTANTS & STATE
// ══════════════════════════════════════════════════════════════
const AJAXURL='<?php echo admin_url("admin-ajax.php");?>',NONCE='<?php echo $nonce;?>',USER_ID=<?php echo $user_id;?>,TODAY_STR='<?php echo date("Y-m-d");?>',IS_PRO=<?php echo $is_pro;?>,PRO_PRICE=<?php echo wp_json_encode((float)$pro_price);?>;
const FT_FREE_WALLET_LIMIT=<?php echo (int)$free_wallet_limit;?>,FT_FREE_CAT_LIMIT=<?php echo (int)$free_category_limit;?>,FT_HOME_TXN_LIMIT=<?php echo (int)$home_transaction_limit;?>;

const S={
  wallets:[],categories:[],budgets:[],goals:[],recurrences:[],
  activeType:'expense',selectedCat:null,selectedColor:'#6366f1',selectedGColor:'#10b981',selectedCatColor:'#6366f1',
  insightPeriod:'this_month',unrealizedPerWallet:{},currentAllocGoalId:null,
  isPro:IS_PRO,
  accent:localStorage.getItem('ft_accent')||'#ef4444',
  theme:localStorage.getItem('ft_theme')||'light',
  isOnline:navigator.onLine,
  deferredInstallPrompt:null,
};

// ══════════════════════════════════════════════════════════════
// INDEXEDDB (Offline Storage — Pro only for writes)
// ══════════════════════════════════════════════════════════════
const FT_DB_NAME='ft_offline_v1',FT_DB_VER=2;
let ftDB=null;

function ftOpenDB(){
  return new Promise((res,rej)=>{
    const req=indexedDB.open(FT_DB_NAME,FT_DB_VER);
    req.onupgradeneeded=e=>{
      const db=e.target.result;
      ['wallets','categories','budgets','goals','recurrences','transactions','sync_queue'].forEach(store=>{
        if(!db.objectStoreNames.contains(store)){
          const s=db.createObjectStore(store,{keyPath:'id',autoIncrement:store==='sync_queue'});
          if(store==='sync_queue') s.createIndex('created_at','created_at');
        }
      });
    };
    req.onsuccess=e=>{ftDB=e.target.result;res(ftDB);};
    req.onerror=()=>rej(req.error);
  });
}
async function ftDBGet(store,key){
  const db=await ftOpenDB();
  return new Promise((res,rej)=>{const t=db.transaction(store,'readonly');const r=t.objectStore(store).get(key);r.onsuccess=()=>res(r.result);r.onerror=()=>rej(r.error);});
}
async function ftDBGetAll(store){
  const db=await ftOpenDB();
  return new Promise((res,rej)=>{const t=db.transaction(store,'readonly');const r=t.objectStore(store).getAll();r.onsuccess=()=>res(r.result);r.onerror=()=>rej(r.error);});
}
async function ftDBPutAll(store,items){
  const db=await ftOpenDB();
  return new Promise((res,rej)=>{
    const t=db.transaction(store,'readwrite');
    const s=t.objectStore(store);
    s.clear();
    items.forEach(i=>s.put(i));
    t.oncomplete=()=>res();t.onerror=()=>rej(t.error);
  });
}
async function ftDBAddToQueue(action,payload){
  const db=await ftOpenDB();
  return new Promise((res,rej)=>{
    const t=db.transaction('sync_queue','readwrite');
    t.objectStore('sync_queue').add({action,payload,created_at:Date.now(),retries:0});
    t.oncomplete=()=>res();t.onerror=()=>rej(t.error);
  });
}
async function ftDBGetQueue(){
  const db=await ftOpenDB();
  return new Promise((res,rej)=>{const t=db.transaction('sync_queue','readonly');const r=t.objectStore('sync_queue').getAll();r.onsuccess=()=>res(r.result);r.onerror=()=>rej(r.error);});
}
async function ftDBClearQueueItem(id){
  const db=await ftOpenDB();
  return new Promise((res,rej)=>{const t=db.transaction('sync_queue','readwrite');t.objectStore('sync_queue').delete(id);t.oncomplete=()=>res();t.onerror=()=>rej(t.error);});
}

// ══════════════════════════════════════════════════════════════
// INIT
// ══════════════════════════════════════════════════════════════
document.addEventListener('DOMContentLoaded',async()=>{
  ftApplyTheme(S.theme);
  ftApplyAccent(S.accent,false);
  ftUpdateThemeUI(S.theme);
  ftUpdateAccentUI(S.accent);
  ftRenderSubscriptionBlock();
  ftInitOnlineStatus();
  await ftOpenDB();
  await ftLoadDashboard();
  ftProcessScheduled();
  ftRegisterSW();
  ftInitInstallPrompt();
  // Handle Maya return
  const params=new URLSearchParams(location.search);
  if(params.get('maya_payment')==='success'){ftToast('Pro activated! Welcome 🎉','success');S.isPro=true;ftRenderSubscriptionBlock();history.replaceState({},'',location.pathname);}
  if(params.get('maya_payment')==='failed'){ftToast('Payment failed. Try again.','error');history.replaceState({},'',location.pathname);}
});

// ══════════════════════════════════════════════════════════════
// SERVICE WORKER
// ══════════════════════════════════════════════════════════════
function ftRegisterSW(){
  if(!('serviceWorker' in navigator)) return;
  navigator.serviceWorker.register('<?php echo esc_url($sw_url);?>',{scope:'<?php echo esc_js($page_scope);?>'})
    .then(reg=>{
      reg.addEventListener('updatefound',()=>{
        const nw=reg.installing;
        nw.addEventListener('statechange',()=>{if(nw.state==='installed'&&navigator.serviceWorker.controller)nw.postMessage('skipWaiting');});
      });
    }).catch(()=>{});
}

// ══════════════════════════════════════════════════════════════
// ONLINE / OFFLINE
// ══════════════════════════════════════════════════════════════
function ftInitOnlineStatus(){
  const banner=document.getElementById('ftOfflineBanner');
  function update(online){
    S.isOnline=online;
    banner.classList.toggle('show',!online);
    if(online) ftToast('Back online — syncing…','success'),ftSyncQueue();
  }
  window.addEventListener('online',()=>update(true));
  window.addEventListener('offline',()=>update(false));
  if(!navigator.onLine) banner.classList.add('show');
}
function ftShowSyncStatus(msg,pending=false){
  const el=document.getElementById('ftSyncStatus');
  const dot=document.getElementById('ftSyncDot');
  const msgEl=document.getElementById('ftSyncMsg');
  msgEl.textContent=msg;
  dot.className='ft-sync-dot'+(pending?' pending':'');
  el.classList.add('show');
  if(!pending) setTimeout(()=>el.classList.remove('show'),2500);
}

// Drain sync queue when back online
async function ftSyncQueue(){
  if(!S.isOnline) return;
  const queue=await ftDBGetQueue();
  if(!queue.length) return;
  ftShowSyncStatus(`Syncing ${queue.length} item(s)…`,true);
  let synced=0;
  for(const item of queue){
    try{
      const res=await ftAjax(item.action,item.payload);
      if(res.success){await ftDBClearQueueItem(item.id);synced++;}
    }catch(e){}
  }
  if(synced>0){ftShowSyncStatus('Synced ✓',false);await ftLoadDashboard();}
  else ftShowSyncStatus('Sync done',false);
}

// ══════════════════════════════════════════════════════════════
// PWA INSTALL PROMPT
// ══════════════════════════════════════════════════════════════
function ftInitInstallPrompt(){
  window.addEventListener('beforeinstallprompt',e=>{
    e.preventDefault();S.deferredInstallPrompt=e;
    const dismissed=localStorage.getItem('ft_install_dismissed');
    const installed=localStorage.getItem('ft_installed');
    if(!dismissed&&!installed){
      setTimeout(()=>{document.getElementById('ftInstallPrompt').style.display='flex';},3000);
    }
    document.getElementById('pwaInstallSection').style.display='';
  });
  window.addEventListener('appinstalled',()=>{
    localStorage.setItem('ft_installed','1');
    document.getElementById('ftInstallPrompt').style.display='none';
    ftToast('App installed!','success');
  });
  // iOS hint
  const isIOS=/iphone|ipad|ipod/i.test(navigator.userAgent)&&!navigator.standalone;
  if(isIOS&&!localStorage.getItem('ft_ios_hint')){
    document.getElementById('pwaInstallSection').style.display='';
    setTimeout(()=>{ftToast('Tap Share → Add to Home Screen to install','success');localStorage.setItem('ft_ios_hint','1');},2000);
  }
}
function ftInstallPWA(){
  if(S.deferredInstallPrompt){S.deferredInstallPrompt.prompt();S.deferredInstallPrompt.userChoice.then(()=>{S.deferredInstallPrompt=null;});}
  document.getElementById('ftInstallPrompt').style.display='none';
}
function ftDismissInstall(){document.getElementById('ftInstallPrompt').style.display='none';localStorage.setItem('ft_install_dismissed','1');}

// ══════════════════════════════════════════════════════════════
// PRO GATING
// ══════════════════════════════════════════════════════════════
function ftCheckProThen(cb){
  if(S.isPro){cb();return;}
  ftShowSheet('proSheet');
}
function ftRenderSubscriptionBlock(){
  const wrap=document.getElementById('subscriptionBlock');
  const badge=document.getElementById('planBadgeWrap');
  if(!wrap) return;
  if(S.isPro){
    badge.innerHTML='<span class="ft-pro-badge">PRO</span>';
    wrap.innerHTML=`<div style="background:linear-gradient(135deg,rgba(var(--accent-rgb),.12),rgba(var(--accent-rgb),.04));border:1.5px solid rgba(var(--accent-rgb),.22);border-radius:16px;padding:16px;">
      <div style="display:flex;align-items:center;gap:10px;"><div style="width:40px;height:40px;border-radius:12px;background:linear-gradient(135deg,var(--accent),var(--accent-hover));display:flex;align-items:center;justify-content:center;"><svg width="20" height="20" fill="none" viewBox="0 0 24 24" stroke="white"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 3l14 9-14 9V3z"/></svg></div>
      <div><strong style="font-size:15px;">Finance Tracker Pro</strong><div style="font-size:12px;color:var(--text-2);">Lifetime access · All features unlocked</div></div></div></div>`;
  } else {
    badge.innerHTML='<span style="padding:2px 8px;border-radius:999px;background:var(--input-bg);font-size:11px;font-weight:700;color:var(--text-2);">FREE</span>';
    wrap.innerHTML=`<div style="background:var(--bg-card);border:1.5px solid var(--border);border-radius:16px;padding:16px;">
      <div style="font-size:14px;font-weight:700;margin-bottom:4px;">Free Plan</div>
      <div style="font-size:12px;color:var(--text-2);margin-bottom:12px;">Limited to ${FT_FREE_WALLET_LIMIT} wallet${FT_FREE_WALLET_LIMIT===1?'':'s'}, ${FT_FREE_CAT_LIMIT} custom categories, basic tracking, no charts or goals.</div>
      <button class="ft-btn ft-btn-pro" style="padding:12px;" onclick="ftShowSheet('proSheet')">Upgrade to Pro — ₱${PRO_PRICE}</button>
    </div>`;
  }
}
function ftRenderInsightsProGate(){
  const gate=document.getElementById('insightsProGate');
  const content=document.getElementById('insightsProContent');
  if(S.isPro){gate.innerHTML='';content.style.display='';return;}
  content.style.display='none';
  gate.innerHTML=`<div class="ft-section"><div class="ft-pro-gate">
    <div class="ft-pro-gate-icon"><svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 19v-6a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2a2 2 0 002-2zm0 0V9a2 2 0 012-2h2a2 2 0 012 2v10m-6 0a2 2 0 002 2h2a2 2 0 002-2m0 0V5a2 2 0 012-2h2a2 2 0 012 2v14a2 2 0 01-2 2h-2a2 2 0 01-2-2z"/></svg></div>
    <h3>Pro Feature</h3>
    <p>Full charts, spending breakdowns, budget management, savings goals and more — all in Pro.</p>
    <button class="ft-btn ft-btn-pro" onclick="ftShowSheet('proSheet')">Upgrade to Pro — ₱${PRO_PRICE}</button>
  </div></div>`;
}
async function ftStartProCheckout(){
  const btn=document.getElementById('proCheckoutBtn');
  if(btn){btn.disabled=true;btn.textContent='Redirecting...';}
  const res=await ftAjax('ft_start_pro_checkout',{current_page:location.href});
  if(res.success&&res.data.redirect_url){window.location.href=res.data.redirect_url;}
  else{if(btn){btn.disabled=false;btn.textContent=`Upgrade Now — ₱${PRO_PRICE}`;}ftToast(res.data||'Payment unavailable. Contact support.','error');}
}

// ══════════════════════════════════════════════════════════════
// THEME & ACCENT
// ══════════════════════════════════════════════════════════════
function ftApplyTheme(t){
  const app=document.getElementById('ft-app');
  if(app) app.dataset.theme=t;
  const metaTheme=document.getElementById('metaThemeColor');
  if(metaTheme) metaTheme.content=t==='dark'?'#080810':'#f2f2f7';
}
function ftSetTheme(t){S.theme=t;localStorage.setItem('ft_theme',t);ftApplyTheme(t);ftUpdateThemeUI(t);ftToast('Theme updated','success');}
function ftUpdateThemeUI(t){
  ['Light','Dark'].forEach(k=>{
    const el=document.getElementById('themeOpt'+k);if(!el)return;
    el.classList.toggle('selected',t===k.toLowerCase());
    const chk=el.querySelector('.ft-theme-option-check svg');if(chk)chk.style.display=t===k.toLowerCase()?'block':'none';
  });
}
function ftApplyAccent(hex,toast=true){
  const r=parseInt(hex.slice(1,3),16),g=parseInt(hex.slice(3,5),16),b=parseInt(hex.slice(5,7),16);
  const d=c=>Math.max(0,c-22);
  const hover='#'+[d(r),d(g),d(b)].map(x=>x.toString(16).padStart(2,'0')).join('');
  [document.documentElement,document.getElementById('ft-app')].forEach(el=>{
    if(!el) return;
    el.style.setProperty('--accent',hex);el.style.setProperty('--accent-hover',hover);el.style.setProperty('--accent-rgb',`${r},${g},${b}`);
  });
  const metaTheme=document.getElementById('metaThemeColor');
  if(metaTheme) metaTheme.content=hex;
  const fab=document.querySelector('.ft-fab-btn');if(fab)fab.style.boxShadow=`0 6px 24px rgba(${r},${g},${b},.5)`;
  const av=document.getElementById('profileAvatar');if(av)av.style.background=`linear-gradient(135deg,${hex},${hover})`;
}
function ftSetAccent(hex){S.accent=hex;localStorage.setItem('ft_accent',hex);ftApplyAccent(hex);ftUpdateAccentUI(hex);ftToast('Accent updated','success');}
function ftUpdateAccentUI(hex){document.querySelectorAll('.ft-accent-swatch').forEach(s=>s.classList.toggle('selected',s.dataset.color===hex));}

// ══════════════════════════════════════════════════════════════
// VIEWS
// ══════════════════════════════════════════════════════════════
function ftSwitchView(v){
  document.querySelectorAll('.ft-view').forEach(el=>el.classList.remove('active'));
  document.querySelectorAll('.ft-nav-item').forEach(el=>el.classList.remove('active'));
  document.getElementById('view-'+v)?.classList.add('active');
  document.getElementById('nav-'+v)?.classList.add('active');
  if(v==='txns') ftLoadTransactions();
  if(v==='insights'){ftRenderInsightsProGate();if(S.isPro)ftLoadInsights();}
}
function ftCurrentView(){return document.querySelector('.ft-view.active')?.id?.replace('view-','')||'home';}

// ══════════════════════════════════════════════════════════════
// SHEETS
// ══════════════════════════════════════════════════════════════
function ftShowSheet(id){
  document.getElementById(id)?.classList.add('show');
  document.getElementById('ftOverlay').classList.add('show');
  if(id==='addTxnSheet'&&!document.getElementById('editTxnId').value){ftPopulateCatGrid();ftPopulateWalletSelects();ftResetTxnForm();}
  if(id==='budgetSheet'){ftCheckProThen(()=>{ftPopulateBudgetCats();});if(!S.isPro){ftHideSheet('budgetSheet');ftShowSheet('proSheet');return;}}
  if(id==='goalSheet'&&!S.isPro){ftHideSheet('goalSheet');ftShowSheet('proSheet');return;}
  if(id==='categorySheet') ftRenderCatManager();
  if(id==='recurSheet'){if(!S.isPro){ftHideSheet('recurSheet');ftShowSheet('proSheet');return;}ftPopulateWalletSelects();ftRenderRecurList();}
  if(id==='goalAllocSheet') ftPopulateAllocWallet();
}
function ftHideSheet(id){
  document.getElementById(id)?.classList.remove('show');
  if(!document.querySelectorAll('.ft-sheet.show').length) document.getElementById('ftOverlay').classList.remove('show');
  if(id==='budgetSheet') ftResetBudgetForm();
  if(id==='goalSheet') ftResetGoalForm();
  if(id==='walletSheet') ftResetWalletForm();
}
document.getElementById('ftOverlay').addEventListener('click',()=>{
  document.querySelectorAll('.ft-sheet.show').forEach(s=>s.classList.remove('show'));
  document.getElementById('ftOverlay').classList.remove('show');
});

// ══════════════════════════════════════════════════════════════
// TOAST
// ══════════════════════════════════════════════════════════════
let _toastTimer=null;
function ftToast(msg,type='success'){
  const t=document.getElementById('ftToast');
  t.className='ft-toast '+type;
  t.querySelector('svg').innerHTML=type==='success'?'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>':'<path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M6 18L18 6M6 6l12 12"/>';
  document.getElementById('ftToastMsg').textContent=msg;
  t.classList.add('show');
  clearTimeout(_toastTimer);
  _toastTimer=setTimeout(()=>t.classList.remove('show'),2800);
}

// ══════════════════════════════════════════════════════════════
// AJAX (network-first, offline queue for Pro)
// ══════════════════════════════════════════════════════════════
const FT_READ_ACTIONS=new Set(['ft_get_dashboard_data','ft_get_transactions','ft_get_insights','ft_get_goal_allocations']);
async function ftAjax(action,data={}){
  if(!S.isOnline&&!FT_READ_ACTIONS.has(action)){
    if(S.isPro){
      await ftDBAddToQueue(action,{...data,nonce:NONCE});
      ftShowSyncStatus(`Queued (offline)`,true);
      return{success:true,data:{message:'Queued for sync',offline:true}};
    } else {
      return{success:false,data:'No internet connection'};
    }
  }
  const fd=new FormData();fd.append('action',action);fd.append('nonce',NONCE);
  Object.entries(data).forEach(([k,v])=>fd.append(k,v));
  try{
    const r=await fetch(AJAXURL,{method:'POST',body:fd});
    return r.json();
  }catch(e){
    if(S.isPro&&!FT_READ_ACTIONS.has(action)){
      await ftDBAddToQueue(action,{...data,nonce:NONCE});
      ftShowSyncStatus('Queued (offline)',true);
      return{success:true,data:{message:'Queued',offline:true}};
    }
    return{success:false,data:'Network error'};
  }
}
function ftFmt(n){return Number(n||0).toLocaleString('en-PH',{minimumFractionDigits:2,maximumFractionDigits:2});}
function escH(s){if(!s)return '';return String(s).replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');}
function ftCssVar(n){return getComputedStyle(document.getElementById('ft-app')).getPropertyValue(n).trim();}

// ══════════════════════════════════════════════════════════════
// WALLET ICONS
// ══════════════════════════════════════════════════════════════
const FT_WALLET_ICONS={
  cash:`<svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M17 9V7a2 2 0 00-2-2H5a2 2 0 00-2 2v6a2 2 0 002 2h2m2 4h10a2 2 0 002-2v-6a2 2 0 00-2-2H9a2 2 0 00-2 2v6a2 2 0 002 2zm7-5a2 2 0 11-4 0 2 2 0 014 0z"/></svg>`,
  bank:`<svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M8 14v3m4-3v3m4-3v3M3 21h18M3 10h18M3 7l9-4 9 4M4 10h16v11H4V10z"/></svg>`,
  ewallet:`<svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M12 18h.01M8 21h8a2 2 0 002-2V5a2 2 0 00-2-2H8a2 2 0 00-2 2v14a2 2 0 002 2z"/></svg>`,
  credit:`<svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="1.8" d="M3 10h18M7 15h1m4 0h1m-7 4h12a3 3 0 003-3V8a3 3 0 00-3-3H6a3 3 0 00-3 3v8a3 3 0 003 3z"/></svg>`,
};

// ══════════════════════════════════════════════════════════════
// DASHBOARD LOAD
// ══════════════════════════════════════════════════════════════
async function ftLoadDashboard(){
  let d=null;
  if(!S.isOnline&&S.isPro){
    // Read from IndexedDB snapshot
    const [wallets,cats,budgets,goals,recurrences,txns]=await Promise.all([
      ftDBGetAll('wallets'),ftDBGetAll('categories'),ftDBGetAll('budgets'),
      ftDBGetAll('goals'),ftDBGetAll('recurrences'),ftDBGetAll('transactions'),
    ]);
    d={wallets,categories:cats,budgets,goals,recurrences,recent_transactions:txns.slice(0,FT_HOME_TXN_LIMIT),
      scheduled_transactions:[],budgets_with_spent:budgets,unrealized_per_wallet:{},
      total_balance:wallets.filter(w=>w.include_in_total).reduce((s,w)=>s+parseFloat(w.balance||0),0),
      month_income:0,month_expense:0};
  } else {
    const res=await ftAjax('ft_get_dashboard_data');
    if(!res.success) return;
    d=res.data;
    // Snapshot to IndexedDB
    if(S.isPro){
      await Promise.all([
        ftDBPutAll('wallets',d.wallets||[]),ftDBPutAll('categories',d.categories||[]),
        ftDBPutAll('budgets',d.budgets||[]),ftDBPutAll('goals',d.goals||[]),
        ftDBPutAll('recurrences',d.recurrences||[]),
        ftDBPutAll('transactions',d.recent_transactions||[]),
      ]);
    }
  }
  S.wallets=d.wallets||[];S.categories=d.categories||[];S.budgets=d.budgets||[];
  S.goals=d.goals||[];S.recurrences=d.recurrences||[];S.unrealizedPerWallet=d.unrealized_per_wallet||{};
  document.getElementById('heroBalVal').textContent=ftFmt(d.total_balance);
  document.getElementById('heroInc').textContent='₱'+ftFmt(d.month_income);
  document.getElementById('heroExp').textContent='₱'+ftFmt(d.month_expense);
  document.getElementById('heroSav').textContent='₱'+ftFmt((d.month_income||0)-(d.month_expense||0));
  ftRenderWallets();
  ftRenderScheduled(d.scheduled_transactions||[]);
  ftRenderHomeBudgets(d.budgets_with_spent||[]);
  ftRenderHomeGoals(d.goals||[]);
  ftRenderHomeTransactions(d.recent_transactions||[]);
  ftRenderBudgets();
  ftRenderGoals();
  if(ftCurrentView()==='insights'&&S.isPro) ftLoadInsights();
  if(ftCurrentView()==='txns') ftLoadTransactions();
}

// ══════════════════════════════════════════════════════════════
// WALLETS
// ══════════════════════════════════════════════════════════════
function ftRenderWallets(){
  const el=document.getElementById('walletsList');
  if(!S.wallets.length){el.innerHTML=`<div style="padding:0 16px;"><div class="ft-wallet-card ft-wallet-add" onclick="ftOpenAddWallet()"><svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg><span>Add Wallet</span></div></div>`;return;}
  let html=S.wallets.map(w=>{
    const res=S.unrealizedPerWallet[w.id]||0;
    return `<div class="ft-wallet-card"><div class="ft-wallet-header"><div class="ft-wallet-icon-wrap" style="background:${w.color}">${FT_WALLET_ICONS[w.type]||FT_WALLET_ICONS.cash}</div><div class="ft-wallet-edit-btn" onclick="event.stopPropagation();ftOpenEditWallet(${w.id})"><svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M11 5H6a2 2 0 00-2 2v11a2 2 0 002 2h11a2 2 0 002-2v-5m-1.414-9.414a2 2 0 112.828 2.828L11.828 15H9v-2.828l8.586-8.586z"/></svg></div></div><div class="ft-wallet-type">${w.type.replace('ewallet','e-wallet')}</div><div class="ft-wallet-name">${escH(w.name)}</div><div class="ft-wallet-balance">₱${ftFmt(w.balance)}</div>${res>0?`<div class="ft-wallet-reserved">₱${ftFmt(res)} reserved</div>`:''}</div>`;
  }).join('');
  html+=`<div class="ft-wallet-card ft-wallet-add" onclick="ftOpenAddWallet()"><svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg><span>Add</span></div>`;
  el.innerHTML=html;
}
function ftOpenAddWallet(){
  const limit=S.isPro?999:FT_FREE_WALLET_LIMIT;
  if(S.wallets.length>=limit&&!S.isPro){ftCheckProThen(()=>{});return;}
  ftResetWalletForm();ftShowSheet('walletSheet');
}
function ftOpenEditWallet(id){
  const w=S.wallets.find(x=>x.id==id);if(!w)return;
  document.getElementById('editWalletId').value=id;document.getElementById('wName').value=w.name;
  document.getElementById('wType').value=w.type;document.getElementById('wBalance').value='';
  document.getElementById('wInclude').checked=w.include_in_total==1;document.getElementById('wColor').value=w.color;
  document.getElementById('walletSheetTitle').textContent='Edit Wallet';document.getElementById('saveWalletBtn').textContent='Save Changes';
  document.getElementById('deleteWalletBtn').style.display='';document.getElementById('wBalanceWrap').style.display='none';
  document.querySelectorAll('#wColorPicker [data-color]').forEach(d=>d.style.borderColor=d.dataset.color===w.color?'white':'transparent');
  ftShowSheet('walletSheet');
}
async function ftSaveWallet(){
  const name=document.getElementById('wName').value.trim();if(!name){ftToast('Enter a name','error');return;}
  const editId=document.getElementById('editWalletId').value;
  let res;
  if(editId) res=await ftAjax('ft_edit_wallet',{wallet_id:editId,name,type:document.getElementById('wType').value,color:document.getElementById('wColor').value,include_in_total:document.getElementById('wInclude').checked?1:0});
  else res=await ftAjax('ft_add_wallet',{name,type:document.getElementById('wType').value,balance:document.getElementById('wBalance').value||0,color:document.getElementById('wColor').value});
  if(res.success){ftToast(editId?'Wallet updated!':'Wallet added!','success');ftHideSheet('walletSheet');await ftLoadDashboard();}
  else ftToast(res.data?.message||res.data||'Error','error');
}
async function ftDeleteWallet(){
  if(!confirm('Delete this wallet?'))return;
  const res=await ftAjax('ft_delete_wallet',{wallet_id:document.getElementById('editWalletId').value});
  if(res.success){ftToast('Deleted','success');ftHideSheet('walletSheet');await ftLoadDashboard();}
}
function ftResetWalletForm(){
  document.getElementById('editWalletId').value='';document.getElementById('wName').value='';
  document.getElementById('wBalance').value='';document.getElementById('walletSheetTitle').textContent='Add Wallet';
  document.getElementById('saveWalletBtn').textContent='Add Wallet';document.getElementById('deleteWalletBtn').style.display='none';
  document.getElementById('wBalanceWrap').style.display='';
}

// ══════════════════════════════════════════════════════════════
// SCHEDULED
// ══════════════════════════════════════════════════════════════
function ftRenderScheduled(txns){
  const sec=document.getElementById('scheduledSection'),el=document.getElementById('scheduledList');
  if(!txns||!txns.length){sec.style.display='none';return;}
  sec.style.display='';
  el.innerHTML=txns.map(t=>`<div class="ft-scheduled-item"><svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z"/></svg><div class="ft-scheduled-info" onclick='ftOpenTxnItem(${JSON.stringify(t).replace(/'/g,"\\'")})' style="cursor:pointer;"><div class="ft-scheduled-name">${escH(t.note||t.goal_transaction_name||t.cat_name||t.type)}</div><div class="ft-scheduled-date">Realizes ${t.realize_date||t.transaction_date} · ${escH(t.wallet_name||'')}</div></div><div class="ft-scheduled-amount">${t.type==='income'?'+':'-'}₱${ftFmt(t.amount)}</div>${t.transaction_source==='goal_allocation'?`<button class="ft-realize-btn" onclick="ftRealizeAllocation(${t.id})">Realize</button>`:`<button class="ft-realize-btn" onclick="ftRealizeNow(${t.id})">Realize</button>`}</div>`).join('');
}
async function ftRealizeNow(id){
  const res=await ftAjax('ft_realize_transaction',{transaction_id:id});
  if(res.success){ftToast('Realized!','success');await ftLoadDashboard();}else ftToast(res.data?.message||'Error','error');
}
async function ftProcessScheduled(){
  const res=await ftAjax('ft_process_scheduled');
  if(res.success&&res.data?.processed>0){ftToast(`${res.data.processed} auto-processed`,'success');await ftLoadDashboard();}
}

// ══════════════════════════════════════════════════════════════
// BUDGETS
// ══════════════════════════════════════════════════════════════
function ftBudgetMeta(b){
  const now=new Date();
  if(b.period==='weekly'){const s=new Date(now);s.setDate(s.getDate()-((s.getDay()+6)%7));const e=new Date(s);e.setDate(e.getDate()+6);return{daysLeft:Math.max(1,Math.ceil((e-now)/86400000)+1)};}
  if(b.period==='yearly'){return{daysLeft:Math.max(1,Math.ceil((new Date(now.getFullYear(),11,31)-now)/86400000)+1)};}
  return{daysLeft:Math.max(1,Math.ceil((new Date(now.getFullYear(),now.getMonth()+1,0)-now)/86400000)+1)};
}
function ftBudgetHTML(b,actions=false){
  const pct=b.amount>0?Math.min(100,(b.spent/b.amount)*100):0;
  const color=pct>=100?'var(--expense)':pct>=(b.alert_at||80)?'var(--warn)':'var(--income)';
  const rem=Math.max(0,b.amount-b.spent);const daily=rem/ftBudgetMeta(b).daysLeft;
  const period=(b.period||'monthly').replace(/^./,m=>m.toUpperCase());
  return `<div class="ft-budget-item"><div class="ft-budget-meta"><div><div class="ft-budget-name">${escH(b.name)}</div><div style="font-size:11px;color:var(--text-3);">${period}${b.cat_name?` · ${escH(b.cat_name)}`:''}</div></div></div><div class="ft-budget-track"><div class="ft-budget-fill" style="width:${pct}%;background:${color};"></div></div><div class="ft-budget-amounts"><span>₱${ftFmt(b.spent)} spent</span><span style="font-weight:700;color:${color}">${pct.toFixed(0)}%</span><span>₱${ftFmt(b.amount)}</span></div><div class="ft-budget-footer"><span style="color:${color};font-weight:700;">${pct.toFixed(0)}% used</span><span>₱${ftFmt(daily)}/day left</span></div>${actions?`<div class="ft-btn-row" style="margin-top:12px;"><button class="ft-btn ft-btn-primary ft-btn-sm" onclick="ftOpenEditBudget(${b.id})">Edit</button><button class="ft-btn ft-btn-danger ft-btn-sm" onclick="ftDeleteBudget(${b.id})">Delete</button></div>`:''}</div>`;
}
function ftRenderHomeBudgets(budgets){
  const el=document.getElementById('homeBudgets');
  if(!S.isPro){el.innerHTML=`<div class="ft-pro-gate" style="margin:0;"><h3>Pro Feature</h3><p>Budgets help you stay on track.</p><button class="ft-btn ft-btn-pro" style="padding:11px;" onclick="ftShowSheet('proSheet')">Upgrade to Pro</button></div>`;return;}
  if(!budgets||!budgets.length){el.innerHTML=`<div class="ft-empty" style="padding:20px;"><p>No budgets. <button class="ft-section-link" onclick="ftShowSheet('budgetSheet')">Add →</button></p></div>`;return;}
  el.innerHTML=budgets.slice(0,2).map(b=>ftBudgetHTML(b,false)).join('');
}
function ftRenderBudgets(){
  const el=document.getElementById('budgetsList');if(!el)return;
  if(!S.budgets||!S.budgets.length){el.innerHTML=`<div class="ft-empty"><p>No budgets yet.</p></div>`;return;}
  el.innerHTML=S.budgets.map(b=>ftBudgetHTML(b,true)).join('');
}
function ftPopulateBudgetCats(){const opts=S.categories.filter(c=>c.type==='expense'||c.type==='both').map(c=>`<option value="${c.id}">${escH(c.name)}</option>`).join('');document.getElementById('bCategory').innerHTML='<option value="">All Expenses</option>'+opts;}
function ftOpenEditBudget(id){
  const b=S.budgets.find(x=>x.id==id);if(!b)return;
  ftPopulateBudgetCats();
  document.getElementById('editBudgetId').value=id;document.getElementById('budgetSheetTitle').textContent='Edit Budget';document.getElementById('saveBudgetBtn').textContent='Save Changes';document.getElementById('deleteBudgetBtn').style.display='';
  document.getElementById('bName').value=b.name||'';document.getElementById('bCategory').value=b.category_id||'';document.getElementById('bAmount').value=b.amount||'';document.getElementById('bPeriod').value=b.period||'monthly';document.getElementById('bAlert').value=b.alert_at||80;
  ftShowSheet('budgetSheet');
}
function ftResetBudgetForm(){document.getElementById('editBudgetId').value='';document.getElementById('budgetSheetTitle').textContent='Add Budget';document.getElementById('saveBudgetBtn').textContent='Save Budget';document.getElementById('deleteBudgetBtn').style.display='none';['bName','bCategory','bAmount'].forEach(id=>document.getElementById(id).value='');document.getElementById('bPeriod').value='monthly';document.getElementById('bAlert').value='80';}
async function ftSaveBudget(){
  const name=document.getElementById('bName').value.trim(),amount=document.getElementById('bAmount').value;
  if(!name||!amount){ftToast('Name and amount required','error');return;}
  const res=await ftAjax('ft_save_budget',{budget_id:document.getElementById('editBudgetId').value,name,amount,category_id:document.getElementById('bCategory').value,period:document.getElementById('bPeriod').value,alert_at:document.getElementById('bAlert').value});
  if(res.success){ftToast('Budget saved!','success');ftHideSheet('budgetSheet');await ftLoadDashboard();}else ftToast(res.data?.message||'Error','error');
}
async function ftDeleteBudget(id){
  if(!confirm('Delete budget?'))return;
  const res=await ftAjax('ft_delete_budget',{budget_id:id});
  if(res.success){ftToast('Deleted','success');await ftLoadDashboard();}
}
async function ftDeleteBudgetFromEdit(){const id=document.getElementById('editBudgetId').value;if(!id)return;ftHideSheet('budgetSheet');await ftDeleteBudget(id);}

// ══════════════════════════════════════════════════════════════
// GOALS
// ══════════════════════════════════════════════════════════════
function ftGoalHTML(g,mini=false){
  const pct=g.target_amount>0?Math.min(100,(g.current_amount/g.target_amount)*100):0;
  const left=g.deadline?`Due ${new Date(g.deadline).toLocaleDateString('en-PH',{month:'short',day:'numeric',year:'numeric'})}`:'No deadline';
  const ap=parseFloat(g.alloc_planned||0),ar=parseFloat(g.alloc_realized||0);
  if(mini) return `<div class="ft-goal-card" style="padding:14px 16px;"><div class="ft-goal-header" style="margin-bottom:10px;"><div style="flex:1;min-width:0;"><div class="ft-goal-title">${escH(g.name)}</div><div class="ft-goal-deadline">${left}</div></div></div><div class="ft-goal-progress"><div class="ft-goal-fill" style="width:${pct}%;background:${g.color};"></div></div><div class="ft-goal-stats"><span>₱${ftFmt(g.current_amount)} saved</span><span class="ft-goal-pct">${pct.toFixed(0)}%</span><span>₱${ftFmt(g.target_amount)}</span></div></div>`;
  return `<div class="ft-goal-card"><div class="ft-goal-header"><div style="flex:1;min-width:0;"><div class="ft-goal-title">${escH(g.name)}</div><div class="ft-goal-deadline">${left}</div></div></div><div class="ft-goal-progress"><div class="ft-goal-fill" style="width:${pct}%;background:${g.color};"></div></div><div class="ft-goal-stats"><span>₱${ftFmt(g.current_amount)} saved</span><span class="ft-goal-pct">${pct.toFixed(0)}%</span><span>₱${ftFmt(g.target_amount)} goal</span></div>${ap>0||ar>0?`<div class="ft-goal-alloc-row"><div class="ft-goal-alloc-stat"><div class="ft-goal-alloc-label">Planned</div><div class="ft-goal-alloc-val" style="color:var(--warn);">₱${ftFmt(ap)}</div></div><div class="ft-goal-alloc-stat"><div class="ft-goal-alloc-label">Moved</div><div class="ft-goal-alloc-val" style="color:var(--income);">₱${ftFmt(ar)}</div></div><div class="ft-goal-alloc-stat"><div class="ft-goal-alloc-label">Remaining</div><div class="ft-goal-alloc-val">₱${ftFmt(Math.max(0,parseFloat(g.target_amount)-parseFloat(g.current_amount)))}</div></div></div>`:''}<div style="display:flex;gap:8px;margin-top:12px;flex-wrap:wrap;"><button class="ft-btn ft-btn-secondary ft-btn-sm" onclick="ftOpenEditGoal(${g.id})">Edit</button><button class="ft-btn ft-btn-primary ft-btn-sm" onclick="ftOpenGoalAlloc(${g.id},'${escH(g.name)}',${g.target_amount},${g.current_amount})">Allocate</button><button class="ft-btn ft-btn-danger ft-btn-sm" onclick="ftDeleteGoal(${g.id})">Delete</button></div></div>`;
}
function ftRenderHomeGoals(goals){
  const el=document.getElementById('homeGoals');if(!el)return;
  if(!S.isPro){el.innerHTML=`<div class="ft-pro-gate" style="margin:0;"><h3>Pro Feature</h3><p>Set savings goals and track your progress.</p><button class="ft-btn ft-btn-pro" style="padding:11px;" onclick="ftShowSheet('proSheet')">Upgrade to Pro</button></div>`;return;}
  if(!goals||!goals.length){el.innerHTML=`<div class="ft-empty" style="padding:20px;"><p>No goals. <button class="ft-section-link" onclick="ftShowSheet('goalSheet')">Add →</button></p></div>`;return;}
  el.innerHTML=goals.slice(0,2).map(g=>ftGoalHTML(g,true)).join('');
}
function ftRenderGoals(){
  const el=document.getElementById('goalsList');if(!el)return;
  if(!S.goals||!S.goals.length){el.innerHTML=`<div class="ft-empty"><p>No goals yet.</p></div>`;return;}
  el.innerHTML=S.goals.map(g=>ftGoalHTML(g,false)).join('');
}
function ftOpenEditGoal(id){
  const g=S.goals.find(x=>x.id==id);if(!g)return;
  document.getElementById('editGoalId').value=id;document.getElementById('goalSheetTitle').textContent='Edit Goal';document.getElementById('saveGoalBtn').textContent='Save Changes';document.getElementById('deleteGoalBtn').style.display='';
  document.getElementById('gName').value=g.name||'';document.getElementById('gTarget').value=g.target_amount||'';document.getElementById('gCurrent').value=g.current_amount||'';document.getElementById('gDeadline').value=g.deadline||'';document.getElementById('gColor').value=g.color||'#10b981';
  document.querySelectorAll('#goalSheet [data-color]').forEach(d=>d.style.borderColor=d.dataset.color===document.getElementById('gColor').value?'white':'transparent');
  ftShowSheet('goalSheet');
}
function ftResetGoalForm(){document.getElementById('editGoalId').value='';document.getElementById('goalSheetTitle').textContent='Add Goal';document.getElementById('saveGoalBtn').textContent='Save Goal';document.getElementById('deleteGoalBtn').style.display='none';['gName','gTarget','gCurrent','gDeadline'].forEach(id=>document.getElementById(id).value='');document.getElementById('gColor').value='#10b981';}
async function ftSaveGoal(){
  const name=document.getElementById('gName').value.trim(),target=document.getElementById('gTarget').value;
  if(!name||!target){ftToast('Name and target required','error');return;}
  const res=await ftAjax('ft_save_goal',{goal_id:document.getElementById('editGoalId').value,name,target_amount:target,current_amount:document.getElementById('gCurrent').value||0,deadline:document.getElementById('gDeadline').value,color:document.getElementById('gColor').value});
  if(res.success){ftToast('Goal saved!','success');ftHideSheet('goalSheet');await ftLoadDashboard();}else ftToast(res.data?.message||'Error','error');
}
async function ftDeleteGoal(id){if(!confirm('Delete goal?'))return;const res=await ftAjax('ft_delete_goal',{goal_id:id});if(res.success){ftToast('Deleted','success');await ftLoadDashboard();}}
async function ftDeleteGoalFromEdit(){const id=document.getElementById('editGoalId').value;if(!id)return;ftHideSheet('goalSheet');await ftDeleteGoal(id);}

// ══════════════════════════════════════════════════════════════
// GOAL ALLOCATIONS
// ══════════════════════════════════════════════════════════════
function ftOpenGoalAlloc(id,name,target,current){
  S.currentAllocGoalId=id;document.getElementById('allocGoalId').value=id;document.getElementById('goalAllocTitle').textContent=`Allocate: ${name}`;
  document.getElementById('allocAmount').value='';document.getElementById('allocNote').value='';document.getElementById('allocPlanDate').value=TODAY_STR;
  ftPopulateAllocWallet();ftLoadAllocHistory(id,target,current);ftShowSheet('goalAllocSheet');
}
function ftPopulateAllocWallet(){
  const opts=S.wallets.map(w=>{const res=S.unrealizedPerWallet[w.id]||0;return `<option value="${w.id}">${escH(w.name)} — ₱${ftFmt(w.balance)} (avail: ₱${ftFmt(parseFloat(w.balance)-res)})</option>`;}).join('');
  document.getElementById('allocWallet').innerHTML=opts||'<option>No wallets</option>';
}
async function ftLoadAllocHistory(goalId,target,current){
  const res=await ftAjax('ft_get_goal_allocations',{goal_id:goalId});
  const el=document.getElementById('allocHistoryList');
  const allocs=res.success?res.data.allocations:[];
  ftDrawAllocPie(allocs,parseFloat(target||0),parseFloat(current||0));
  if(!allocs.length){el.innerHTML='<p style="color:var(--text-3);font-size:12px;text-align:center;padding:12px;">No allocations yet.</p>';return;}
  el.innerHTML=allocs.map(a=>`<div class="ft-alloc-item"><div class="ft-alloc-item-dot" style="background:${a.status==='realized'?'var(--income)':'var(--warn)'}"></div><div class="ft-alloc-item-info"><div class="ft-alloc-item-name">${escH(a.wallet_name||'Wallet')}${a.note?` — ${escH(a.note)}`:''}</div><div class="ft-alloc-item-meta">${a.status_label||a.status} · ${a.display_date||((a.created_at||'').split(' ')[0])}</div></div><div class="ft-alloc-item-amount" style="color:${a.status==='realized'?'var(--income)':'var(--warn)'}">₱${ftFmt(a.amount)}</div><div class="ft-alloc-item-actions">${a.status==='unrealized'?`<button style="color:var(--income);" onclick="ftRealizeAllocation(${a.id})">Realize</button>`:''}<button style="color:var(--accent);" onclick="ftOpenEditAlloc(${a.id},${a.amount},'${escH(a.note||'')}')">Edit</button><button style="color:var(--expense);" onclick="ftDeleteAllocation(${a.id})">×</button></div></div>`).join('');
}
function ftDrawAllocPie(allocs,target,current){
  const canvas=document.getElementById('allocPieChart'),ctx=canvas.getContext('2d');
  const wrap=document.getElementById('allocPieWrap');
  ctx.clearRect(0,0,160,160);
  const colors=['#22c55e','#f59e0b','#3b82f6','#ec4899','#8b5cf6','#14b8a6'];
  const entries=allocs.map((a,i)=>({val:parseFloat(a.amount)||0,color:colors[i%colors.length],name:`${a.wallet_name||'Wallet'}${a.note?` - ${a.note}`:''}`})).filter(x=>x.val>0);
  const allocated=entries.reduce((s,x)=>s+x.val,0);
  const remaining=Math.max(0,(parseFloat(target)||0)-allocated);
  const total=allocated+remaining||target||1;
  document.getElementById('allocPieTotal').textContent='₱'+ftFmt(allocated);
  let start=-Math.PI/2;
  entries.forEach(e=>{const slice=(e.val/total)*Math.PI*2;ctx.beginPath();ctx.moveTo(80,80);ctx.arc(80,80,72,start,start+slice);ctx.closePath();ctx.fillStyle=e.color;ctx.fill();start+=slice;});
  if(remaining>0){const slice=(remaining/total)*Math.PI*2;ctx.beginPath();ctx.moveTo(80,80);ctx.arc(80,80,72,start,start+slice);ctx.closePath();ctx.fillStyle='rgba(100,116,139,.2)';ctx.fill();}
  ctx.beginPath();ctx.arc(80,80,48,0,Math.PI*2);ctx.fillStyle=ftCssVar('--bg-elevated')||'#fff';ctx.fill();
  let legend=wrap.parentNode.querySelector('.ft-alloc-chart-legend');
  if(!legend){legend=document.createElement('div');legend.className='ft-alloc-chart-legend';wrap.insertAdjacentElement('afterend',legend);}
  legend.innerHTML=[...entries.map(e=>`<div class="ft-alloc-chart-item"><div class="ft-alloc-chart-dot" style="background:${e.color}"></div><div class="ft-alloc-chart-name">${escH(e.name)}</div><div class="ft-alloc-chart-val">₱${ftFmt(e.val)}</div></div>`),remaining>0?`<div class="ft-alloc-chart-item"><div class="ft-alloc-chart-dot" style="background:rgba(100,116,139,.2)"></div><div class="ft-alloc-chart-name">Remaining</div><div class="ft-alloc-chart-val">₱${ftFmt(remaining)}</div></div>`:``].join('');
}
async function ftAllocateGoal(realize){
  const goalId=document.getElementById('allocGoalId').value,walletId=document.getElementById('allocWallet').value,amount=document.getElementById('allocAmount').value,note=document.getElementById('allocNote').value,planDate=document.getElementById('allocPlanDate')?.value||TODAY_STR;
  if(!amount||!walletId){ftToast('Fill all fields','error');return;}
  const res=await ftAjax('ft_allocate_goal',{goal_id:goalId,wallet_id:walletId,amount,note,plan_date:planDate,realize});
  if(res.success){ftToast(res.data.message,'success');document.getElementById('allocAmount').value='';document.getElementById('allocNote').value='';document.getElementById('allocPlanDate').value=TODAY_STR;await ftLoadDashboard();const g=S.goals.find(x=>x.id==goalId);if(g)ftLoadAllocHistory(goalId,g.target_amount,g.current_amount);}else ftToast(res.data?.message||'Error','error');
}
async function ftRealizeAllocation(id){
  const res=await ftAjax('ft_realize_goal_allocation',{allocation_id:id});
  if(res.success){ftToast('Realized!','success');await ftLoadDashboard();const gid=S.currentAllocGoalId,g=S.goals.find(x=>x.id==gid);if(gid&&g)ftLoadAllocHistory(gid,g.target_amount,g.current_amount);}else ftToast(res.data?.message||'Error','error');
}
function ftOpenEditAlloc(id,amount,note){document.getElementById('editAllocId').value=id;document.getElementById('editAllocAmount').value=amount;document.getElementById('editAllocNote').value=note;ftShowSheet('editAllocSheet');}
async function ftUpdateAllocation(){
  const id=document.getElementById('editAllocId').value,amount=document.getElementById('editAllocAmount').value,note=document.getElementById('editAllocNote').value;
  const res=await ftAjax('ft_edit_goal_allocation',{allocation_id:id,amount,note});
  if(res.success){ftToast('Updated!','success');ftHideSheet('editAllocSheet');await ftLoadDashboard();const gid=S.currentAllocGoalId,g=S.goals.find(x=>x.id==gid);if(gid&&g)ftLoadAllocHistory(gid,g.target_amount,g.current_amount);}else ftToast(res.data?.message||'Error','error');
}
async function ftDeleteAllocation(id){
  if(typeof id==='undefined')id=document.getElementById('editAllocId').value;
  if(!confirm('Remove allocation?'))return;
  const res=await ftAjax('ft_delete_goal_allocation',{allocation_id:id});
  if(res.success){ftToast('Removed','success');ftHideSheet('editAllocSheet');await ftLoadDashboard();const gid=S.currentAllocGoalId,g=S.goals.find(x=>x.id==gid);if(gid&&g)ftLoadAllocHistory(gid,g.target_amount,g.current_amount);}else ftToast(res.data?.message||'Error','error');
}

// ══════════════════════════════════════════════════════════════
// TRANSACTIONS
// ══════════════════════════════════════════════════════════════
async function ftLoadTransactions(){
  const month=document.getElementById('txnFilterMonth').value;
  document.getElementById('txnsList').innerHTML='<div class="ft-loader"><div class="ft-spinner"></div></div>';
  const res=await ftAjax('ft_get_transactions',{month});if(!res.success)return;
  ftRenderTxnList(res.data.transactions,'txnsList');
}
function ftRenderHomeTransactions(txns){ftRenderTxnList((txns||[]).slice(0,FT_HOME_TXN_LIMIT),'homeRecentTxns');}
function ftRenderTxnList(txns,targetId){
  const el=document.getElementById(targetId);
  if(!txns||!txns.length){el.innerHTML=`<div class="ft-empty"><svg fill="none" stroke="var(--text-3)" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 5H7a2 2 0 00-2 2v12a2 2 0 002 2h10a2 2 0 002-2V7a2 2 0 00-2-2h-2M9 5a2 2 0 002 2h2a2 2 0 002-2M9 5a2 2 0 012-2h2a2 2 0 012 2"/></svg><p>No transactions yet</p></div>`;return;}
  const grouped={};txns.forEach(t=>{if(!grouped[t.transaction_date])grouped[t.transaction_date]=[];grouped[t.transaction_date].push(t);});
  const today=new Date().toISOString().split('T')[0],yday=new Date(Date.now()-86400000).toISOString().split('T')[0];
  let html='';
  Object.entries(grouped).forEach(([date,items])=>{
    const label=date===today?'Today':date===yday?'Yesterday':new Date(date).toLocaleDateString('en-PH',{month:'short',day:'numeric'});
    html+=`<div class="ft-txn-date-group">${label}</div>`;
    items.forEach(t=>{
      const isInc=t.type==='income',color=isInc?'var(--income)':t.type==='transfer'?'var(--transfer)':'var(--expense)';
      const cleanJson=JSON.stringify(t).replace(/\\/g,'\\\\').replace(/'/g,"\\'").replace(/"/g,'&quot;');
      html+=`<div class="ft-txn-item" onclick="ftOpenTxnItem('${cleanJson}')"><div class="ft-txn-icon" style="background:${t.cat_color||color}">${ftCatIcon(t.cat_icon)}</div><div class="ft-txn-info"><div class="ft-txn-name">${escH(t.note||t.cat_name||t.type)}</div><div class="ft-txn-meta">${escH(t.wallet_name||'')} · ${escH(t.cat_name||t.type)}${t.status_badge?` · ${escH(t.status_badge)}`:''}</div></div><div class="ft-txn-amount ${isInc?'inc':'exp'}">${isInc?'+':'-'}₱${ftFmt(t.amount)}</div></div>`;
    });
  });
  el.innerHTML=html;
}
function ftOpenTxnItem(t){
  if(typeof t==='string')try{t=JSON.parse(t);}catch(e){return;}
  if(t.transaction_source==='goal_allocation'){const goal=S.goals.find(g=>g.id==t.goal_id);ftOpenGoalAlloc(t.goal_id,t.goal_name||goal?.name||'Goal',goal?.target_amount||0,goal?.current_amount||0);return;}
  ftOpenEditTxn(t);
}
function ftOpenEditTxn(t){
  if(typeof t==='string')try{t=JSON.parse(t);}catch(e){return;}
  ftResetTxnForm();
  document.getElementById('editTxnId').value=t.id;document.getElementById('txnAmount').value=parseFloat(t.amount);
  document.getElementById('txnNote').value=t.note||'';document.getElementById('txnDate').value=t.transaction_date;
  if(t.realize_date)document.getElementById('txnRealizeDate').value=t.realize_date;
  S.activeType=t.type;S.selectedCat=t.category_id?parseInt(t.category_id):null;
  document.querySelectorAll('.ft-type-tab').forEach(b=>{b.classList.remove('active');if((b.classList.contains('exp')&&t.type==='expense')||(b.classList.contains('inc')&&t.type==='income')||(b.classList.contains('tra')&&t.type==='transfer'))b.classList.add('active');});
  document.getElementById('txnToWalletWrap').style.display=t.type==='transfer'?'':'none';document.getElementById('txnCatWrap').style.display=t.type==='transfer'?'none':'';
  ftPopulateCatGrid();ftPopulateWalletSelects();
  setTimeout(()=>{const ws=document.getElementById('txnWallet');if(ws)ws.value=t.wallet_id;if(t.to_wallet_id){const tw=document.getElementById('txnToWallet');if(tw)tw.value=t.to_wallet_id;}},50);
  document.getElementById('saveTxnBtn').textContent='Save Changes';document.getElementById('deleteTxnBtn').style.display='';
  ftShowSheet('addTxnSheet');
}
function ftResetTxnForm(){
  document.getElementById('editTxnId').value='';document.getElementById('txnAmount').value='';document.getElementById('txnNote').value='';
  document.getElementById('txnDate').value=TODAY_STR;document.getElementById('txnRealizeDate').value='';
  document.getElementById('saveTxnBtn').textContent='Save Transaction';document.getElementById('deleteTxnBtn').style.display='none';
  S.activeType='expense';S.selectedCat=null;
  document.querySelectorAll('.ft-type-tab').forEach(b=>b.classList.remove('active'));document.querySelector('.ft-type-tab.exp')?.classList.add('active');
  document.getElementById('txnToWalletWrap').style.display='none';document.getElementById('txnCatWrap').style.display='';
}
async function ftSaveTransaction(){
  const amount=document.getElementById('txnAmount').value,wallet=document.getElementById('txnWallet').value;
  if(!amount||!wallet){ftToast('Amount and wallet required','error');return;}
  const editId=document.getElementById('editTxnId').value;
  const btn=document.getElementById('saveTxnBtn');btn.disabled=true;btn.textContent='Saving…';
  const data={type:S.activeType,amount,wallet_id:wallet,to_wallet_id:document.getElementById('txnToWallet').value,category_id:S.selectedCat||'',note:document.getElementById('txnNote').value,transaction_date:document.getElementById('txnDate').value,realize_date:document.getElementById('txnRealizeDate').value};
  const res=editId?await ftAjax('ft_edit_transaction',{...data,transaction_id:editId}):await ftAjax('ft_add_transaction',data);
  btn.disabled=false;btn.textContent=editId?'Save Changes':'Save Transaction';
  if(res.success){
    ftToast(res.data?.offline?'Saved offline — will sync when online':editId?'Updated!':'Saved!','success');
    ftHideSheet('addTxnSheet');ftResetTxnForm();await ftLoadDashboard();
    if(ftCurrentView()==='txns')ftLoadTransactions();
  }else ftToast(res.data?.message||res.data||'Error','error');
}
async function ftDeleteTxnFromEdit(){
  const id=document.getElementById('editTxnId').value;if(!id)return;
  if(!confirm('Delete transaction?'))return;
  const res=await ftAjax('ft_delete_transaction',{transaction_id:id});
  if(res.success){ftToast('Deleted','success');ftHideSheet('addTxnSheet');ftResetTxnForm();await ftLoadDashboard();if(ftCurrentView()==='txns')ftLoadTransactions();}else ftToast(res.data?.message||'Error','error');
}

// ══════════════════════════════════════════════════════════════
// POPULATE SELECTS & COLOR PICKERS
// ══════════════════════════════════════════════════════════════
function ftPopulateWalletSelects(){
  const opts=S.wallets.map(w=>`<option value="${w.id}">${escH(w.name)} (₱${ftFmt(w.balance)})</option>`).join('');
  ['txnWallet','txnToWallet','rWallet'].forEach(id=>{const el=document.getElementById(id);if(el)el.innerHTML=opts||'<option value="">No wallets</option>';});
}
function ftPopulateCatGrid(){
  const type=S.activeType,cats=S.categories.filter(c=>c.type===type||c.type==='both');
  const html=cats.map(c=>`<div class="ft-cat-chip ${S.selectedCat==c.id?'selected':''}" data-id="${c.id}" onclick="ftSelectCat(${c.id},this)"><div class="ft-cat-icon" style="background:${c.color}">${ftCatIcon(c.icon)}</div><div class="ft-cat-label">${escH(c.name)}</div></div>`).join('')||'<p style="color:var(--text-3);font-size:12px;grid-column:1/-1;">No categories.</p>';
  document.getElementById('txnCatGrid').innerHTML=html;
}
function ftSelectCat(id,el){S.selectedCat=id;document.querySelectorAll('#txnCatGrid .ft-cat-chip').forEach(c=>c.classList.remove('selected'));el.classList.add('selected');}
function ftSelectType(type,btn){S.activeType=type;S.selectedCat=null;document.querySelectorAll('.ft-type-tab').forEach(b=>b.classList.remove('active'));btn.classList.add('active');document.getElementById('txnToWalletWrap').style.display=type==='transfer'?'':'none';document.getElementById('txnCatWrap').style.display=type==='transfer'?'none':'';ftPopulateCatGrid();}
function ftSelectColor(el,c){S.selectedColor=c;document.getElementById('wColor').value=c;document.querySelectorAll('#wColorPicker [data-color]').forEach(d=>d.style.borderColor='transparent');el.style.borderColor='white';}
function ftSelectGColor(el,c){S.selectedGColor=c;document.getElementById('gColor').value=c;document.querySelectorAll('#goalSheet [data-color]').forEach(d=>d.style.borderColor='transparent');el.style.borderColor='white';}
function ftSelectCatColor(el,c){S.selectedCatColor=c;document.getElementById('catColor').value=c;document.querySelectorAll('#catColorPicker [data-color]').forEach(d=>d.style.borderColor='transparent');el.style.borderColor='white';}

// ══════════════════════════════════════════════════════════════
// CATEGORIES
// ══════════════════════════════════════════════════════════════
async function ftSaveCategory(){
  const name=document.getElementById('catName').value.trim();if(!name){ftToast('Enter a name','error');return;}
  const res=await ftAjax('ft_add_category',{name,type:document.getElementById('catType').value,color:document.getElementById('catColor').value});
  if(res.success){ftToast('Added!','success');document.getElementById('catName').value='';await ftLoadDashboard();ftRenderCatManager();}else ftToast(res.data?.message||'Error','error');
}
function ftRenderCatManager(){
  const el=document.getElementById('catManagerList');
  if(!S.categories.length){el.innerHTML='<p style="color:var(--text-3);font-size:13px;">No categories yet.</p>';return;}
  el.innerHTML=S.categories.map(c=>`<div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;background:var(--input-bg);border-radius:14px;padding:10px 12px;"><div style="width:28px;height:28px;border-radius:8px;background:${c.color};flex-shrink:0;"></div><span style="flex:1;font-size:14px;font-weight:600;">${escH(c.name)}</span><span style="font-size:11px;color:var(--text-3);background:var(--bg-card);padding:2px 8px;border-radius:6px;">${c.type}</span><button onclick="ftOpenEditCat(${JSON.stringify(c).replace(/"/g,'&quot;')})" style="border:none;background:none;color:var(--accent);cursor:pointer;font-size:12px;font-weight:700;font-family:var(--font);">Edit</button><button onclick="ftDeleteCat(${c.id})" style="border:none;background:none;color:var(--expense);cursor:pointer;font-size:14px;font-weight:700;">×</button></div>`).join('');
}
function ftOpenEditCat(c){
  if(typeof c==='string')c=JSON.parse(c);
  const colors=['#6366f1','#f59e0b','#3b82f6','#8b5cf6','#ef4444','#22c55e','#ec4899','#14b8a6','#f97316','#f43f5e'];
  document.getElementById('editCatId').value=c.id;document.getElementById('editCatName').value=c.name;document.getElementById('editCatType').value=c.type;document.getElementById('editCatColor').value=c.color;
  document.getElementById('editCatColorPicker').innerHTML=colors.map(cl=>`<div onclick="ftSelEditCatColor(this,'${cl}')" data-color="${cl}" style="width:28px;height:28px;border-radius:50%;background:${cl};cursor:pointer;border:2px solid ${cl===c.color?'white':'transparent'};transition:border-color .2s;"></div>`).join('');
  ftShowSheet('editCatSheet');
}
function ftSelEditCatColor(el,c){document.getElementById('editCatColor').value=c;document.querySelectorAll('#editCatColorPicker [data-color]').forEach(d=>d.style.borderColor='transparent');el.style.borderColor='white';}
async function ftUpdateCategory(){
  const res=await ftAjax('ft_edit_category',{category_id:document.getElementById('editCatId').value,name:document.getElementById('editCatName').value,type:document.getElementById('editCatType').value,color:document.getElementById('editCatColor').value,icon:'tag'});
  if(res.success){ftToast('Updated!','success');ftHideSheet('editCatSheet');await ftLoadDashboard();ftRenderCatManager();}else ftToast(res.data?.message||'Error','error');
}
async function ftDeleteCat(id){const res=await ftAjax('ft_delete_category',{category_id:id});if(res.success){ftToast('Deleted','success');await ftLoadDashboard();ftRenderCatManager();}}

// ══════════════════════════════════════════════════════════════
// RECURRENCES (Pro)
// ══════════════════════════════════════════════════════════════
async function ftSaveRecurrence(){
  const name=document.getElementById('rName').value.trim(),amount=document.getElementById('rAmount').value;
  if(!name||!amount){ftToast('Name and amount required','error');return;}
  const res=await ftAjax('ft_save_recurrence',{name,type:document.getElementById('rType').value,amount,wallet_id:document.getElementById('rWallet').value,frequency:document.getElementById('rFreq').value,next_date:document.getElementById('rNextDate').value});
  if(res.success){ftToast('Saved!','success');document.getElementById('rName').value='';document.getElementById('rAmount').value='';await ftLoadDashboard();ftRenderRecurList();}else ftToast(res.data?.message||'Error','error');
}
function ftRenderRecurList(){
  const el=document.getElementById('recurList');
  if(!S.recurrences.length){el.innerHTML='<p style="color:var(--text-3);font-size:13px;">No recurring.</p>';return;}
  el.innerHTML=S.recurrences.map(r=>`<div style="display:flex;align-items:center;gap:10px;margin-bottom:8px;background:var(--input-bg);border-radius:14px;padding:10px 12px;"><div style="flex:1;min-width:0;"><div style="font-size:14px;font-weight:700;">${escH(r.name)}</div><div style="font-size:11px;color:var(--text-3);">₱${ftFmt(r.amount)} · ${r.frequency} · Next: ${r.next_date}</div></div><button onclick="ftOpenEditRecur(${JSON.stringify(r).replace(/"/g,'&quot;')})" style="border:none;background:none;color:var(--accent);cursor:pointer;font-size:12px;font-weight:700;font-family:var(--font);">Edit</button><button onclick="ftDeleteRecur(${r.id})" style="border:none;background:none;color:var(--expense);cursor:pointer;font-size:14px;font-weight:700;">×</button></div>`).join('');
}
function ftOpenEditRecur(r){
  if(typeof r==='string')r=JSON.parse(r);
  document.getElementById('editRecurId').value=r.id;document.getElementById('editRName').value=r.name;document.getElementById('editRType').value=r.type;document.getElementById('editRFreq').value=r.frequency;document.getElementById('editRAmount').value=r.amount;document.getElementById('editRNextDate').value=r.next_date;
  document.getElementById('editRWallet').innerHTML=S.wallets.map(w=>`<option value="${w.id}" ${w.id==r.wallet_id?'selected':''}>${escH(w.name)}</option>`).join('');
  ftShowSheet('editRecurSheet');
}
async function ftUpdateRecurrence(){
  const res=await ftAjax('ft_edit_recurrence',{recurrence_id:document.getElementById('editRecurId').value,name:document.getElementById('editRName').value,type:document.getElementById('editRType').value,amount:document.getElementById('editRAmount').value,frequency:document.getElementById('editRFreq').value,next_date:document.getElementById('editRNextDate').value,wallet_id:document.getElementById('editRWallet').value});
  if(res.success){ftToast('Updated!','success');ftHideSheet('editRecurSheet');await ftLoadDashboard();}else ftToast(res.data?.message||'Error','error');
}
async function ftDeleteRecurFromEdit(){if(!confirm('Delete recurring?'))return;const res=await ftAjax('ft_delete_recurrence',{recurrence_id:document.getElementById('editRecurId').value});if(res.success){ftToast('Deleted','success');ftHideSheet('editRecurSheet');await ftLoadDashboard();ftRenderRecurList();}}
async function ftDeleteRecur(id){const res=await ftAjax('ft_delete_recurrence',{recurrence_id:id});if(res.success){ftToast('Deleted','success');await ftLoadDashboard();ftRenderRecurList();}}

// ══════════════════════════════════════════════════════════════
// INSIGHTS (Pro)
// ══════════════════════════════════════════════════════════════
function ftSelectInsightPeriod(el){document.querySelectorAll('#insightChips .ft-chip').forEach(c=>c.classList.remove('active'));el.classList.add('active');S.insightPeriod=el.dataset.period;ftLoadInsights();}
async function ftLoadInsights(){
  if(!S.isPro){ftRenderInsightsProGate();return;}
  const res=await ftAjax('ft_get_insights',{period:S.insightPeriod});if(!res.success)return;
  const d=res.data,score=d.health_score||0;
  const circ=213.63;
  setTimeout(()=>{document.getElementById('healthRing').style.strokeDashoffset=circ-(score/100)*circ;},100);
  document.getElementById('healthScore').textContent=score;
  const msgs={excellent:'Excellent! Keep it up!',good:'Good financial habits.',fair:'Room to improve.',poor:'Cut back on spending.'};
  const clrs={excellent:'var(--income)',good:'var(--accent)',fair:'var(--warn)',poor:'var(--expense)'};
  const lvl=score>=80?'excellent':score>=60?'good':score>=40?'fair':'poor';
  document.getElementById('healthMsg').textContent=msgs[lvl];document.getElementById('healthRing').style.stroke=clrs[lvl];
  ftDrawDonut(d.category_breakdown||[]);ftDrawTrend(d.monthly_trend||[]);
  document.getElementById('insightStatsBody').innerHTML=`<div style="display:grid;grid-template-columns:1fr 1fr;gap:12px;"><div style="background:var(--input-bg);border-radius:14px;padding:14px;"><div style="font-size:10px;color:var(--text-3);font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Income</div><div style="font-size:18px;font-weight:700;font-family:var(--font-mono);color:var(--income);">₱${ftFmt(d.period_income)}</div></div><div style="background:var(--input-bg);border-radius:14px;padding:14px;"><div style="font-size:10px;color:var(--text-3);font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Expenses</div><div style="font-size:18px;font-weight:700;font-family:var(--font-mono);color:var(--expense);">₱${ftFmt(d.period_expense)}</div></div><div style="background:var(--input-bg);border-radius:14px;padding:14px;"><div style="font-size:10px;color:var(--text-3);font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Net</div><div style="font-size:18px;font-weight:700;font-family:var(--font-mono);color:${(d.period_income-d.period_expense)>=0?'var(--income)':'var(--expense)'};">₱${ftFmt(d.period_income-d.period_expense)}</div></div><div style="background:var(--input-bg);border-radius:14px;padding:14px;"><div style="font-size:10px;color:var(--text-3);font-weight:700;text-transform:uppercase;letter-spacing:.5px;margin-bottom:4px;">Savings Rate</div><div style="font-size:18px;font-weight:700;font-family:var(--font-mono);color:var(--accent);">${d.period_income>0?((1-(d.period_expense/d.period_income))*100).toFixed(0):0}%</div></div></div>${d.burn_rate_msg?`<div style="margin-top:12px;padding:12px 14px;background:rgba(245,158,11,.08);border:1px solid rgba(245,158,11,.2);border-radius:12px;font-size:13px;color:var(--warn);">⚡ ${escH(d.burn_rate_msg)}</div>`:''}`;
}
function ftDrawDonut(cats){
  const c=document.getElementById('donutChart').getContext('2d');c.clearRect(0,0,180,180);
  const total=cats.reduce((s,cat)=>s+parseFloat(cat.total),0);
  document.getElementById('donutTotal').textContent='₱'+ftFmt(total);
  if(!cats.length){c.beginPath();c.arc(90,90,70,0,Math.PI*2);c.strokeStyle='rgba(100,116,139,.2)';c.lineWidth=20;c.stroke();document.getElementById('donutLegend').innerHTML='<p style="color:var(--text-3);font-size:12px;grid-column:1/-1;">No data</p>';return;}
  let start=-Math.PI/2;
  cats.forEach(cat=>{const slice=(parseFloat(cat.total)/total)*Math.PI*2;c.beginPath();c.arc(90,90,70,start,start+slice);c.arc(90,90,50,start+slice,start,true);c.fillStyle=cat.color||'#6366f1';c.fill();start+=slice;});
  document.getElementById('donutLegend').innerHTML=cats.slice(0,8).map(cat=>`<div class="ft-legend-item"><div class="ft-legend-dot" style="background:${cat.color}"></div><div><div class="ft-legend-name">${escH(cat.name)}</div><div class="ft-legend-val">₱${ftFmt(cat.total)}</div></div></div>`).join('');
}
function ftDrawTrend(months){
  const bw=document.getElementById('trendBars'),lw=document.getElementById('trendLabels');
  if(!months.length){bw.innerHTML='<p style="color:var(--text-3);font-size:12px;">No data</p>';return;}
  const mv=Math.max(...months.map(m=>Math.max(parseFloat(m.income||0),parseFloat(m.expense||0))),1);
  bw.innerHTML=months.map(m=>{const ih=Math.max(4,(parseFloat(m.income||0)/mv)*76),eh=Math.max(4,(parseFloat(m.expense||0)/mv)*76);return `<div style="flex:1;display:flex;flex-direction:column;align-items:center;justify-content:flex-end;"><div style="width:100%;max-width:20px;display:flex;gap:2px;align-items:flex-end;height:76px;"><div class="ft-trend-bar" style="flex:1;height:${ih}px;background:var(--income);opacity:.75;"></div><div class="ft-trend-bar" style="flex:1;height:${eh}px;background:var(--expense);opacity:.75;"></div></div></div>`;}).join('');
  lw.innerHTML=months.map(m=>`<div class="ft-trend-label">${m.label||m.month?.slice(5)||''}</div>`).join('');
}

// ══════════════════════════════════════════════════════════════
// CAT ICONS
// ══════════════════════════════════════════════════════════════
function ftCatIcon(icon){
  const icons={utensils:`<svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M3 3h2l.4 2M7 13h10l4-8H5.4M7 13L5.4 5M7 13l-2.293 2.293c-.63.63-.184 1.707.707 1.707H17m0 0a2 2 0 100 4 2 2 0 000-4zm-8 2a2 2 0 11-4 0 2 2 0 014 0z"/></svg>`,car:`<svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 17a2 2 0 11-4 0 2 2 0 014 0zM19 17a2 2 0 11-4 0 2 2 0 014 0zM3 11l1.5-4.5A2 2 0 016.4 5h11.2a2 2 0 011.9 1.5L21 11M3 11h18M3 11v6h18v-6"/></svg>`,bag:`<svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M16 11V7a4 4 0 00-8 0v4M5 9h14l1 12H4L5 9z"/></svg>`,film:`<svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 4v16M17 4v16M3 8h4m10 0h4M3 12h18M3 16h4m10 0h4M4 20h16a1 1 0 001-1V5a1 1 0 00-1-1H4a1 1 0 00-1 1v14a1 1 0 001 1z"/></svg>`,heart:`<svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4.318 6.318a4.5 4.5 0 000 6.364L12 20.364l7.682-7.682a4.5 4.5 0 00-6.364-6.364L12 7.636l-1.318-1.318a4.5 4.5 0 00-6.364 0z"/></svg>`,zap:`<svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>`,book:`<svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253"/></svg>`,'map-pin':`<svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17.657 16.657L13.414 20.9a1.998 1.998 0 01-2.827 0l-4.244-4.243a8 8 0 1111.314 0z"/><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 11a3 3 0 11-6 0 3 3 0 016 0z"/></svg>`,briefcase:`<svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 13.255A23.931 23.931 0 0112 15c-3.183 0-6.22-.62-9-1.745M16 6V4a2 2 0 00-2-2h-4a2 2 0 00-2 2v2m4 6h.01M5 20h14a2 2 0 002-2V8a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>`,code:`<svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 20l4-16m4 4l4 4-4 4M6 16l-4-4 4-4"/></svg>`,'trending-up':`<svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 7h8m0 0v8m0-8l-8 8-4-4-6 6"/></svg>`,gift:`<svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v13m0-13V6a2 2 0 112 2h-2zm0 0V5.5A2.5 2.5 0 109.5 8H12zm-7 4h14M5 12a2 2 0 110-4h14a2 2 0 110 4M5 12v7a2 2 0 002 2h10a2 2 0 002-2v-7"/></svg>`,tag:`<svg fill="none" viewBox="0 0 24 24"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A2 2 0 013 12V7a4 4 0 014-4z"/></svg>`};
  return icons[icon]||icons.tag;
}
</script>
<?php
    return ob_get_clean();
}

// ============================================================
// HELPERS
// ============================================================
function ft_greeting(){$h=(int)date('H');return $h<12?'morning':($h<17?'afternoon':'evening');}
function ft_get_budget_date_range($period){
    $period=$period?:'monthly';
    switch($period){
        case 'weekly':$s=date('Y-m-d',strtotime('monday this week'));$e=date('Y-m-d',strtotime('sunday this week'));break;
        case 'yearly':$s=date('Y-01-01');$e=date('Y-12-31');break;
        default:$s=date('Y-m-01');$e=date('Y-m-t');break;
    }
    return[$s,$e];
}
function ft_get_goal_allocation_transaction_entries($user_id,$realized_only=null,$month=null,$limit=0){
    global $wpdb;$p=$wpdb->prefix;
    if(!$wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s",$p.'ft_goal_allocations')))return[];
    $where=["a.user_id=%d","a.status!='deleted'"];$params=[$user_id];
    if($realized_only===true)$where[]="a.status='realized'";
    elseif($realized_only===false)$where[]="a.status='unrealized'";
    if($month){$where[]="DATE_FORMAT(a.created_at,'%%Y-%%m')=%s";$params[]=$month;}
    $sql="SELECT a.*,g.name AS goal_name,w.name AS wallet_name FROM {$p}ft_goal_allocations a LEFT JOIN {$p}ft_goals g ON g.id=a.goal_id LEFT JOIN {$p}ft_wallets w ON w.id=a.wallet_id WHERE ".implode(' AND ',$where)." ORDER BY a.created_at DESC";
    if($limit>0)$sql.=" LIMIT ".intval($limit);
    $rows=$wpdb->get_results($wpdb->prepare($sql,...$params));
    foreach($rows as $row){
        $row->transaction_source='goal_allocation';$row->type='expense';
        $row->transaction_date=$row->status==='realized'?($row->realize_date?:substr($row->created_at,0,10)):($row->plan_date?:substr($row->created_at,0,10));
        $row->is_realized=$row->status==='realized'?1:0;$row->realize_date=$row->status==='realized'?($row->realize_date?:$row->transaction_date):($row->plan_date?:$row->transaction_date);
        $row->cat_name='Goal Allocation';$row->cat_color=$row->status==='realized'?'#22c55e':'#f59e0b';$row->cat_icon='tag';
        $row->note='Allocation: '.($row->goal_name?:'Goal');$row->status_badge=$row->status==='realized'?'Realized':'Planned';
        $row->goal_transaction_name=($row->goal_name?:'Goal').' - '.ucfirst($row->status);
        $row->display_date=$row->status==='realized'?($row->realize_date?:substr($row->created_at,0,10)):($row->plan_date?:substr($row->created_at,0,10));
        $row->status_label=$row->status==='realized'?'Realized allocation':'Unrealized allocation';
    }
    return $rows;
}


// ============================================================
// AJAX: DASHBOARD DATA
// ============================================================

function bntm_ajax_ft_get_dashboard_data() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Please log in']); return; }
    bntm_ft_maybe_upgrade_schema();
    global $wpdb; $p=$wpdb->prefix; $user_id=get_current_user_id(); $month=date('Y-m');

    $wallets=$wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$p}ft_wallets WHERE user_id=%d AND status='active' ORDER BY id ASC",$user_id));
    $total_balance=array_sum(array_map(fn($w)=>floatval($w->balance),array_filter($wallets,fn($w)=>$w->include_in_total)));

    $unrealized_per_wallet=[];
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s",$p.'ft_goal_allocations'))===$p.'ft_goal_allocations') {
        $ur=$wpdb->get_results($wpdb->prepare(
            "SELECT wallet_id,SUM(amount) as reserved FROM {$p}ft_goal_allocations WHERE user_id=%d AND status='unrealized' GROUP BY wallet_id",$user_id));
        foreach ($ur as $row) $unrealized_per_wallet[$row->wallet_id]=floatval($row->reserved);
    }

    $month_income=(float)$wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}ft_transactions WHERE user_id=%d AND type='income' AND status='active' AND is_realized=1 AND DATE_FORMAT(transaction_date,'%%Y-%%m')=%s",$user_id,$month));
    $month_expense=(float)$wpdb->get_var($wpdb->prepare(
        "SELECT COALESCE(SUM(amount),0) FROM {$p}ft_transactions WHERE user_id=%d AND type='expense' AND status='active' AND is_realized=1 AND DATE_FORMAT(transaction_date,'%%Y-%%m')=%s",$user_id,$month));

    $categories=$wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$p}ft_categories WHERE (user_id=%d OR user_id=0) AND status='active' ORDER BY sort_order ASC,id ASC",$user_id));

    $budgets_raw=$wpdb->get_results($wpdb->prepare(
        "SELECT b.*,c.name as cat_name FROM {$p}ft_budgets b LEFT JOIN {$p}ft_categories c ON c.id=b.category_id WHERE b.user_id=%d AND b.status='active'",$user_id));
    $budgets_with_spent=[];
    foreach ($budgets_raw as $b) {
        [$date_from,$date_to]=ft_get_budget_date_range($b->period);
        $sql="SELECT COALESCE(SUM(amount),0) FROM {$p}ft_transactions WHERE user_id=%d AND type='expense' AND status='active' AND is_realized=1 AND transaction_date BETWEEN %s AND %s";
        $params=[$user_id,$date_from,$date_to];
        if (intval($b->category_id)>0) { $sql.=" AND category_id=%d"; $params[]=$b->category_id; }
        $b->spent=(float)$wpdb->get_var($wpdb->prepare($sql,...$params));
        $budgets_with_spent[]=$b;
    }

    $goals=$wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$p}ft_goals WHERE user_id=%d AND status='active' ORDER BY id ASC",$user_id));
    $goal_alloc_map=[];
    if ($wpdb->get_var($wpdb->prepare("SHOW TABLES LIKE %s",$p.'ft_goal_allocations'))===$p.'ft_goal_allocations') {
        $ar=$wpdb->get_results($wpdb->prepare(
            "SELECT goal_id,SUM(amount) as total_planned,SUM(CASE WHEN status='realized' THEN amount ELSE 0 END) as total_realized FROM {$p}ft_goal_allocations WHERE user_id=%d AND status!='deleted' GROUP BY goal_id",$user_id));
        foreach ($ar as $row) $goal_alloc_map[$row->goal_id]=['planned'=>floatval($row->total_planned),'realized'=>floatval($row->total_realized)];
    }
    foreach ($goals as &$g) {
        $g->alloc_planned=$goal_alloc_map[$g->id]['planned']??0;
        $g->alloc_realized=$goal_alloc_map[$g->id]['realized']??0;
    } unset($g);

    $recurrences=$wpdb->get_results($wpdb->prepare(
        "SELECT * FROM {$p}ft_recurrences WHERE user_id=%d AND status='active' ORDER BY next_date ASC",$user_id));

    $home_txn_limit = ft_get_home_transaction_limit();
    $recent_query_limit = max(10, $home_txn_limit * 2);
    $recent_transactions=$wpdb->get_results($wpdb->prepare(
        "SELECT t.*,c.name as cat_name,c.color as cat_color,c.icon as cat_icon,w.name as wallet_name,'' as status_badge
         FROM {$p}ft_transactions t
         LEFT JOIN {$p}ft_categories c ON c.id=t.category_id
         LEFT JOIN {$p}ft_wallets w ON w.id=t.wallet_id
         WHERE t.user_id=%d AND t.status='active' AND t.is_realized=1
         ORDER BY t.transaction_date DESC,t.id DESC LIMIT %d",$user_id,$recent_query_limit));
    $recent_transactions=array_values(array_merge($recent_transactions,ft_get_goal_allocation_transaction_entries($user_id,true,null,$recent_query_limit)));
    usort($recent_transactions,fn($a,$b)=>strcmp($b->transaction_date??'',$a->transaction_date??'')?:((int)($b->id??0)<=>( int)($a->id??0)));
    $recent_transactions=array_slice($recent_transactions,0,$home_txn_limit);

    $scheduled_transactions=$wpdb->get_results($wpdb->prepare(
        "SELECT t.*,c.name as cat_name,c.color as cat_color,c.icon as cat_icon,w.name as wallet_name,'' as status_badge
         FROM {$p}ft_transactions t
         LEFT JOIN {$p}ft_categories c ON c.id=t.category_id
         LEFT JOIN {$p}ft_wallets w ON w.id=t.wallet_id
         WHERE t.user_id=%d AND t.status='active' AND t.is_realized=0
         ORDER BY t.realize_date ASC LIMIT 30",$user_id));
    $scheduled_transactions=array_values(array_merge($scheduled_transactions,ft_get_goal_allocation_transaction_entries($user_id,false,null,30)));
    usort($scheduled_transactions,fn($a,$b)=>strcmp($a->realize_date??$a->transaction_date??'',$b->realize_date??$b->transaction_date??''));
    $scheduled_transactions=array_slice($scheduled_transactions,0,20);

    wp_send_json_success([
        'wallets'=>$wallets,'unrealized_per_wallet'=>$unrealized_per_wallet,
        'categories'=>$categories,'budgets'=>$budgets_with_spent,'budgets_with_spent'=>$budgets_with_spent,
        'goals'=>$goals,'recurrences'=>$recurrences,'recent_transactions'=>$recent_transactions,
        'scheduled_transactions'=>$scheduled_transactions,'total_balance'=>$total_balance,
        'month_income'=>$month_income,'month_expense'=>$month_expense,
    ]);
}

// ============================================================
// AJAX: TRANSACTIONS
// ============================================================

function bntm_ajax_ft_get_transactions() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Please log in']); return; }
    global $wpdb; $p=$wpdb->prefix; $user_id=get_current_user_id();
    $month=sanitize_text_field($_POST['month']??date('Y-m'));
    $txns=$wpdb->get_results($wpdb->prepare(
        "SELECT t.*,c.name as cat_name,c.color as cat_color,c.icon as cat_icon,w.name as wallet_name,'' as status_badge
         FROM {$p}ft_transactions t
         LEFT JOIN {$p}ft_categories c ON c.id=t.category_id
         LEFT JOIN {$p}ft_wallets w ON w.id=t.wallet_id
         WHERE t.user_id=%d AND t.status='active' AND DATE_FORMAT(t.transaction_date,'%%Y-%%m')=%s
         ORDER BY t.transaction_date DESC,t.id DESC",$user_id,$month));
    $txns=array_merge($txns,ft_get_goal_allocation_transaction_entries($user_id,null,$month));
    usort($txns,fn($a,$b)=>strcmp($b->transaction_date??'',$a->transaction_date??'')?:((int)($b->id??0)<=>(int)($a->id??0)));
    wp_send_json_success(['transactions'=>$txns]);
}

function bntm_ajax_ft_add_transaction() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); return; }
    global $wpdb; $p=$wpdb->prefix; $user_id=get_current_user_id();
    $type        = sanitize_text_field($_POST['type']);
    $amount      = floatval($_POST['amount']);
    $wallet_id   = intval($_POST['wallet_id']);
    $to_wallet   = intval($_POST['to_wallet_id']??0);
    $category_id = intval($_POST['category_id']??0);
    $note        = sanitize_text_field($_POST['note']??'');
    $date        = sanitize_text_field($_POST['transaction_date']??date('Y-m-d'));
    $realize_date= sanitize_text_field($_POST['realize_date']??'');
    $today       = date('Y-m-d');
    $is_realized = ($realize_date===''||$realize_date<=$today) ? 1 : 0;
    if ($amount<=0) { wp_send_json_error(['message'=>'Invalid amount.']); return; }
    $wpdb->query('START TRANSACTION');
    try {
        $wpdb->insert($p.'ft_transactions',[
            'rand_id'=>bntm_rand_id(),'business_id'=>$user_id,'user_id'=>$user_id,
            'type'=>$type,'amount'=>$amount,'wallet_id'=>$wallet_id,
            'to_wallet_id'=>$to_wallet?:null,'category_id'=>$category_id?:null,
            'note'=>$note,'transaction_date'=>$date,
            'realize_date'=>$is_realized?null:$realize_date,'is_realized'=>$is_realized,'status'=>'active',
        ],['%s','%d','%d','%s','%f','%d','%d','%d','%s','%s','%s','%d','%s']);
        if (!$wpdb->insert_id) throw new Exception('Insert failed');
        if ($is_realized) {
            if ($type==='expense') $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance-%f WHERE id=%d AND user_id=%d",$amount,$wallet_id,$user_id));
            elseif ($type==='income') $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance+%f WHERE id=%d AND user_id=%d",$amount,$wallet_id,$user_id));
            elseif ($type==='transfer'&&$to_wallet) {
                $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance-%f WHERE id=%d AND user_id=%d",$amount,$wallet_id,$user_id));
                $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance+%f WHERE id=%d AND user_id=%d",$amount,$to_wallet,$user_id));
            }
        }
        $wpdb->query('COMMIT');
        wp_send_json_success(['message'=>'Transaction saved!']);
    } catch (Exception $e) { $wpdb->query('ROLLBACK'); wp_send_json_error(['message'=>$e->getMessage()]); }
}

function bntm_ajax_ft_edit_transaction() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); return; }
    global $wpdb; $p=$wpdb->prefix; $user_id=get_current_user_id();
    $id  = intval($_POST['transaction_id']);
    $old = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}ft_transactions WHERE id=%d AND user_id=%d AND status='active'",$id,$user_id));
    if (!$old) { wp_send_json_error(['message'=>'Not found.']); return; }
    $new_amount   = floatval($_POST['amount']);
    $new_date     = sanitize_text_field($_POST['transaction_date']??$old->transaction_date);
    $new_note     = sanitize_text_field($_POST['note']??'');
    $new_cat      = intval($_POST['category_id']??0)?:null;
    $new_wallet   = intval($_POST['wallet_id']??$old->wallet_id);
    $realize_date = sanitize_text_field($_POST['realize_date']??'');
    $today        = date('Y-m-d');
    $is_realized  = ($realize_date===''||$realize_date<=$today) ? 1 : 0;
    $wpdb->query('START TRANSACTION');
    try {
        // Reverse old balance if was realized
        if ((int)$old->is_realized===1) {
            if ($old->type==='expense') $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance+%f WHERE id=%d AND user_id=%d",floatval($old->amount),$old->wallet_id,$user_id));
            elseif ($old->type==='income') $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance-%f WHERE id=%d AND user_id=%d",floatval($old->amount),$old->wallet_id,$user_id));
            elseif ($old->type==='transfer') {
                $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance+%f WHERE id=%d AND user_id=%d",floatval($old->amount),$old->wallet_id,$user_id));
                if ($old->to_wallet_id) $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance-%f WHERE id=%d AND user_id=%d",floatval($old->amount),$old->to_wallet_id,$user_id));
            }
        }
        // Apply new
        if ($is_realized) {
            if ($old->type==='expense') $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance-%f WHERE id=%d AND user_id=%d",$new_amount,$new_wallet,$user_id));
            elseif ($old->type==='income') $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance+%f WHERE id=%d AND user_id=%d",$new_amount,$new_wallet,$user_id));
        }
        $wpdb->update($p.'ft_transactions',
            ['amount'=>$new_amount,'category_id'=>$new_cat,'note'=>$new_note,'transaction_date'=>$new_date,'wallet_id'=>$new_wallet,'realize_date'=>$realize_date?:null,'is_realized'=>$is_realized],
            ['id'=>$id,'user_id'=>$user_id],['%f','%d','%s','%s','%d','%s','%d'],['%d','%d']);
        $wpdb->query('COMMIT');
        wp_send_json_success(['message'=>'Transaction updated!']);
    } catch (Exception $e) { $wpdb->query('ROLLBACK'); wp_send_json_error(['message'=>$e->getMessage()]); }
}

function bntm_ajax_ft_realize_transaction() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); return; }
    global $wpdb; $p=$wpdb->prefix; $user_id=get_current_user_id(); $id=intval($_POST['transaction_id']);
    $txn=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}ft_transactions WHERE id=%d AND user_id=%d AND is_realized=0 AND status='active'",$id,$user_id));
    if (!$txn) { wp_send_json_error(['message'=>'Not found.']); return; }
    $wpdb->query('START TRANSACTION');
    try {
        if ($txn->type==='expense') $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance-%f WHERE id=%d AND user_id=%d",floatval($txn->amount),$txn->wallet_id,$user_id));
        elseif ($txn->type==='income') $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance+%f WHERE id=%d AND user_id=%d",floatval($txn->amount),$txn->wallet_id,$user_id));
        $wpdb->update($p.'ft_transactions',['is_realized'=>1,'realize_date'=>date('Y-m-d')],['id'=>$id],['%d','%s'],['%d']);
        $wpdb->query('COMMIT');
        wp_send_json_success(['message'=>'Transaction realized!']);
    } catch (Exception $e) { $wpdb->query('ROLLBACK'); wp_send_json_error(['message'=>$e->getMessage()]); }
}

function bntm_ajax_ft_delete_transaction() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); return; }
    global $wpdb; $p=$wpdb->prefix; $user_id=get_current_user_id(); $id=intval($_POST['transaction_id']);
    $txn=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}ft_transactions WHERE id=%d AND user_id=%d",$id,$user_id));
    if (!$txn) { wp_send_json_error(['message'=>'Not found.']); return; }
    $wpdb->query('START TRANSACTION');
    try {
        $wpdb->update($p.'ft_transactions',['status'=>'deleted'],['id'=>$id],['%s'],['%d']);
        if ((int)$txn->is_realized===1) {
            if ($txn->type==='expense') $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance+%f WHERE id=%d",$txn->amount,$txn->wallet_id));
            elseif ($txn->type==='income') $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance-%f WHERE id=%d",$txn->amount,$txn->wallet_id));
            elseif ($txn->type==='transfer') {
                $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance+%f WHERE id=%d",$txn->amount,$txn->wallet_id));
                if ($txn->to_wallet_id) $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance-%f WHERE id=%d",$txn->amount,$txn->to_wallet_id));
            }
        }
        $wpdb->query('COMMIT');
        wp_send_json_success(['message'=>'Deleted.']);
    } catch (Exception $e) { $wpdb->query('ROLLBACK'); wp_send_json_error(['message'=>$e->getMessage()]); }
}

// ============================================================
// AJAX: WALLETS
// ============================================================

function bntm_ajax_ft_add_wallet() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); return; }
    global $wpdb; $user_id=get_current_user_id();
    // Enforce free plan wallet limit
    if (!ft_is_pro($user_id)) {
        $count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ft_wallets WHERE user_id=%d AND status='active'",$user_id));
        $limit = ft_get_free_wallet_limit();
        if ($count >= $limit) { wp_send_json_error(['message'=>'Upgrade to Pro for unlimited wallets.']); return; }
    }
    $result=$wpdb->insert($wpdb->prefix.'ft_wallets',[
        'rand_id'=>bntm_rand_id(),'business_id'=>$user_id,'user_id'=>$user_id,
        'name'=>sanitize_text_field($_POST['name']),'type'=>sanitize_text_field($_POST['type']),
        'balance'=>floatval($_POST['balance']??0),'color'=>sanitize_text_field($_POST['color']??'#6366f1'),
        'include_in_total'=>1,'status'=>'active',
    ],['%s','%d','%d','%s','%s','%f','%s','%d','%s']);
    if ($result) wp_send_json_success(['message'=>'Wallet added!']);
    else wp_send_json_error(['message'=>'Failed to add wallet.']);
}

function bntm_ajax_ft_edit_wallet() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); return; }
    global $wpdb; $id=intval($_POST['wallet_id']); $user_id=get_current_user_id();
    $result=$wpdb->update($wpdb->prefix.'ft_wallets',
        ['name'=>sanitize_text_field($_POST['name']),'type'=>sanitize_text_field($_POST['type']),'color'=>sanitize_text_field($_POST['color']),'include_in_total'=>intval($_POST['include_in_total']??1)],
        ['id'=>$id,'user_id'=>$user_id],['%s','%s','%s','%d'],['%d','%d']);
    if ($result!==false) wp_send_json_success(['message'=>'Wallet updated!']);
    else wp_send_json_error(['message'=>'Failed.']);
}

function bntm_ajax_ft_delete_wallet() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); return; }
    global $wpdb; $id=intval($_POST['wallet_id']); $user_id=get_current_user_id();
    $wpdb->update($wpdb->prefix.'ft_wallets',['status'=>'deleted'],['id'=>$id,'user_id'=>$user_id],['%s'],['%d','%d']);
    wp_send_json_success(['message'=>'Wallet removed.']);
}

// ============================================================
// AJAX: CATEGORIES
// ============================================================

function bntm_ajax_ft_add_category() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); return; }
    global $wpdb; $user_id=get_current_user_id();
    // Free limit: 3 custom categories (default ones have user_id=0)
    if (!ft_is_pro($user_id)) {
        $count=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$wpdb->prefix}ft_categories WHERE user_id=%d AND status='active'",$user_id));
        $limit = ft_get_free_category_limit();
        if ($count >= $limit) { wp_send_json_error(['message'=>'Upgrade to Pro for unlimited categories.']); return; }
    }
    $result=$wpdb->insert($wpdb->prefix.'ft_categories',[
        'rand_id'=>bntm_rand_id(),'business_id'=>$user_id,'user_id'=>$user_id,
        'name'=>sanitize_text_field($_POST['name']),'type'=>sanitize_text_field($_POST['type']),
        'color'=>sanitize_text_field($_POST['color']??'#6366f1'),'icon'=>'tag','status'=>'active',
    ],['%s','%d','%d','%s','%s','%s','%s','%s']);
    if ($result) wp_send_json_success(['message'=>'Category added!']);
    else wp_send_json_error(['message'=>'Failed.']);
}

function bntm_ajax_ft_edit_category() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); return; }
    global $wpdb; $id=intval($_POST['category_id']); $user_id=get_current_user_id();
    $r=$wpdb->update($wpdb->prefix.'ft_categories',
        ['name'=>sanitize_text_field($_POST['name']),'type'=>sanitize_text_field($_POST['type']),'color'=>sanitize_text_field($_POST['color']),'icon'=>sanitize_text_field($_POST['icon']??'tag')],
        ['id'=>$id,'user_id'=>$user_id],['%s','%s','%s','%s'],['%d','%d']);
    $r!==false ? wp_send_json_success(['message'=>'Updated!']) : wp_send_json_error(['message'=>'Failed.']);
}

function bntm_ajax_ft_delete_category() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); return; }
    global $wpdb; $id=intval($_POST['category_id']); $user_id=get_current_user_id();
    $wpdb->update($wpdb->prefix.'ft_categories',['status'=>'deleted'],['id'=>$id,'user_id'=>$user_id],['%s'],['%d','%d']);
    wp_send_json_success(['message'=>'Deleted.']);
}

// ============================================================
// AJAX: BUDGETS (Pro only)
// ============================================================

function bntm_ajax_ft_save_budget() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); return; }
    $user_id=get_current_user_id();
    if (!ft_is_pro($user_id)) { wp_send_json_error(['message'=>'Pro feature. Please upgrade.']); return; }
    global $wpdb; $budget_id=intval($_POST['budget_id']??0);
    $data=['name'=>sanitize_text_field($_POST['name']),'category_id'=>intval($_POST['category_id']??0),'amount'=>floatval($_POST['amount']),'period'=>sanitize_text_field($_POST['period']??'monthly'),'alert_at'=>intval($_POST['alert_at']??80)];
    $formats=['%s','%d','%f','%s','%d'];
    if ($budget_id>0) {
        $result=$wpdb->update($wpdb->prefix.'ft_budgets',$data,['id'=>$budget_id,'user_id'=>$user_id],$formats,['%d','%d']);
    } else {
        $result=$wpdb->insert($wpdb->prefix.'ft_budgets',array_merge(['rand_id'=>bntm_rand_id(),'business_id'=>$user_id,'user_id'=>$user_id],$data,['status'=>'active']),['%s','%d','%d','%s','%d','%f','%s','%d','%s']);
    }
    if ($result!==false) wp_send_json_success(['message'=>'Budget saved!']);
    else wp_send_json_error(['message'=>'Failed.']);
}

function bntm_ajax_ft_delete_budget() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); return; }
    global $wpdb; $id=intval($_POST['budget_id']); $user_id=get_current_user_id();
    $wpdb->update($wpdb->prefix.'ft_budgets',['status'=>'deleted'],['id'=>$id,'user_id'=>$user_id],['%s'],['%d','%d']);
    wp_send_json_success(['message'=>'Deleted.']);
}

// ============================================================
// AJAX: GOALS (Pro only)
// ============================================================

function bntm_ajax_ft_save_goal() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); return; }
    $user_id=get_current_user_id();
    if (!ft_is_pro($user_id)) { wp_send_json_error(['message'=>'Pro feature. Please upgrade.']); return; }
    global $wpdb; $goal_id=intval($_POST['goal_id']??0);
    $data=['name'=>sanitize_text_field($_POST['name']),'target_amount'=>floatval($_POST['target_amount']),'current_amount'=>floatval($_POST['current_amount']??0),'deadline'=>sanitize_text_field($_POST['deadline']??'')?:null,'color'=>sanitize_text_field($_POST['color']??'#10b981')];
    $formats=['%s','%f','%f','%s','%s'];
    if ($goal_id>0) {
        $result=$wpdb->update($wpdb->prefix.'ft_goals',$data,['id'=>$goal_id,'user_id'=>$user_id],$formats,['%d','%d']);
    } else {
        $result=$wpdb->insert($wpdb->prefix.'ft_goals',array_merge(['rand_id'=>bntm_rand_id(),'business_id'=>$user_id,'user_id'=>$user_id],$data,['status'=>'active']),['%s','%d','%d','%s','%f','%f','%s','%s','%s']);
    }
    if ($result!==false) wp_send_json_success(['message'=>'Goal saved!']);
    else wp_send_json_error(['message'=>'Failed.']);
}

function bntm_ajax_ft_delete_goal() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); return; }
    global $wpdb; $id=intval($_POST['goal_id']); $user_id=get_current_user_id();
    $wpdb->update($wpdb->prefix.'ft_goals',['status'=>'deleted'],['id'=>$id,'user_id'=>$user_id],['%s'],['%d','%d']);
    wp_send_json_success(['message'=>'Deleted.']);
}

// ============================================================
// AJAX: RECURRENCES (Pro only)
// ============================================================

function bntm_ajax_ft_save_recurrence() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); return; }
    $user_id=get_current_user_id();
    if (!ft_is_pro($user_id)) { wp_send_json_error(['message'=>'Pro feature. Please upgrade.']); return; }
    global $wpdb;
    $result=$wpdb->insert($wpdb->prefix.'ft_recurrences',[
        'rand_id'=>bntm_rand_id(),'business_id'=>$user_id,'user_id'=>$user_id,
        'name'=>sanitize_text_field($_POST['name']),'type'=>sanitize_text_field($_POST['type']??'expense'),
        'amount'=>floatval($_POST['amount']),'wallet_id'=>intval($_POST['wallet_id']),
        'frequency'=>sanitize_text_field($_POST['frequency']??'monthly'),
        'next_date'=>sanitize_text_field($_POST['next_date']??date('Y-m-d')),'status'=>'active',
    ],['%s','%d','%d','%s','%s','%f','%d','%s','%s','%s']);
    if ($result) wp_send_json_success(['message'=>'Recurring saved!']);
    else wp_send_json_error(['message'=>'Failed.']);
}

function bntm_ajax_ft_edit_recurrence() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); return; }
    global $wpdb; $id=intval($_POST['recurrence_id']); $user_id=get_current_user_id();
    $r=$wpdb->update($wpdb->prefix.'ft_recurrences',
        ['name'=>sanitize_text_field($_POST['name']),'amount'=>floatval($_POST['amount']),'frequency'=>sanitize_text_field($_POST['frequency']),'next_date'=>sanitize_text_field($_POST['next_date']),'wallet_id'=>intval($_POST['wallet_id']),'type'=>sanitize_text_field($_POST['type'])],
        ['id'=>$id,'user_id'=>$user_id],['%s','%f','%s','%s','%d','%s'],['%d','%d']);
    $r!==false ? wp_send_json_success(['message'=>'Updated!']) : wp_send_json_error(['message'=>'Failed.']);
}

function bntm_ajax_ft_delete_recurrence() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); return; }
    global $wpdb; $id=intval($_POST['recurrence_id']); $user_id=get_current_user_id();
    $wpdb->update($wpdb->prefix.'ft_recurrences',['status'=>'deleted'],['id'=>$id,'user_id'=>$user_id],['%s'],['%d','%d']);
    wp_send_json_success(['message'=>'Deleted.']);
}

// ============================================================
// AJAX: INSIGHTS (Pro only)
// ============================================================

function bntm_ajax_ft_get_insights() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Please log in']); return; }
    $user_id=get_current_user_id();
    if (!ft_is_pro($user_id)) { wp_send_json_error(['message'=>'Pro feature.']); return; }
    global $wpdb; $p=$wpdb->prefix;
    $period=sanitize_text_field($_POST['period']??'this_month');
    $date_from=match($period){
        'last_month'=>date('Y-m-01',strtotime('-1 month')),
        '3_months'  =>date('Y-m-01',strtotime('-3 months')),
        '6_months'  =>date('Y-m-01',strtotime('-6 months')),
        'year'      =>date('Y-01-01'),
        default     =>date('Y-m-01'),
    };
    $date_to=match($period){'last_month'=>date('Y-m-t',strtotime('-1 month')),default=>date('Y-m-d')};
    $income=(float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM {$p}ft_transactions WHERE user_id=%d AND type='income' AND status='active' AND is_realized=1 AND transaction_date BETWEEN %s AND %s",$user_id,$date_from,$date_to));
    $expense=(float)$wpdb->get_var($wpdb->prepare("SELECT COALESCE(SUM(amount),0) FROM {$p}ft_transactions WHERE user_id=%d AND type='expense' AND status='active' AND is_realized=1 AND transaction_date BETWEEN %s AND %s",$user_id,$date_from,$date_to));
    $cat_breakdown=$wpdb->get_results($wpdb->prepare("SELECT c.name,c.color,SUM(t.amount) as total FROM {$p}ft_transactions t JOIN {$p}ft_categories c ON c.id=t.category_id WHERE t.user_id=%d AND t.type='expense' AND t.status='active' AND t.is_realized=1 AND t.transaction_date BETWEEN %s AND %s GROUP BY c.id ORDER BY total DESC LIMIT 8",$user_id,$date_from,$date_to));
    $monthly_trend=$wpdb->get_results($wpdb->prepare("SELECT DATE_FORMAT(transaction_date,'%%Y-%%m') as month,DATE_FORMAT(transaction_date,'%%b') as label,SUM(CASE WHEN type='income' THEN amount ELSE 0 END) as income,SUM(CASE WHEN type='expense' THEN amount ELSE 0 END) as expense FROM {$p}ft_transactions WHERE user_id=%d AND status='active' AND is_realized=1 AND transaction_date>=DATE_FORMAT(DATE_SUB(NOW(),INTERVAL 6 MONTH),'%%Y-%%m-01') GROUP BY month ORDER BY month ASC",$user_id));
    $savings_rate=$income>0?(($income-$expense)/$income):0;
    $budgets=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}ft_budgets WHERE user_id=%d AND status='active'",$user_id));
    $has_wallets=(int)$wpdb->get_var($wpdb->prepare("SELECT COUNT(*) FROM {$p}ft_wallets WHERE user_id=%d AND status='active'",$user_id));
    $score=min(100,round(min(50,max(0,$savings_rate*100))+($budgets>0?20:0)+($has_wallets>0?10:0)+($income>0?20:0)));
    $burn_rate_msg='';
    if ($period==='this_month'&&$expense>0) {
        $days_passed=intval(date('d'));$days_in_month=intval(date('t'));
        $projected=($expense/$days_passed)*$days_in_month;
        if ($projected>$income&&$income>0) $burn_rate_msg="At this rate you'll spend ₱".number_format($projected,0)." this month — ".number_format($projected-$income,0)." over income.";
    }
    wp_send_json_success(['period_income'=>$income,'period_expense'=>$expense,'category_breakdown'=>$cat_breakdown,'monthly_trend'=>$monthly_trend,'health_score'=>$score,'burn_rate_msg'=>$burn_rate_msg]);
}

// ============================================================
// AJAX: GOAL ALLOCATIONS
// ============================================================

function bntm_ajax_ft_allocate_goal() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); return; }
    bntm_ft_maybe_upgrade_schema();
    global $wpdb; $p=$wpdb->prefix; $user_id=get_current_user_id();
    $goal_id   = intval($_POST['goal_id']); $wallet_id=intval($_POST['wallet_id']);
    $amount    = floatval($_POST['amount']); $note=sanitize_text_field($_POST['note']??'');
    $realize   = intval($_POST['realize']??0);
    $plan_date = sanitize_text_field($_POST['plan_date']??date('Y-m-d'));
    if ($amount<=0) { wp_send_json_error(['message'=>'Invalid amount.']); return; }
    $wpdb->query('START TRANSACTION');
    try {
        $wpdb->insert($p.'ft_goal_allocations',['rand_id'=>bntm_rand_id(),'user_id'=>$user_id,'goal_id'=>$goal_id,'wallet_id'=>$wallet_id,'amount'=>$amount,'note'=>$note,'status'=>$realize?'realized':'unrealized','plan_date'=>$plan_date?:date('Y-m-d'),'realize_date'=>$realize?date('Y-m-d'):null],['%s','%d','%d','%d','%f','%s','%s','%s','%s']);
        $wpdb->query($wpdb->prepare("UPDATE {$p}ft_goals SET current_amount=LEAST(current_amount+%f,target_amount) WHERE id=%d AND user_id=%d",$amount,$goal_id,$user_id));
        if ($realize) {
            $w=$wpdb->get_row($wpdb->prepare("SELECT balance FROM {$p}ft_wallets WHERE id=%d AND user_id=%d",$wallet_id,$user_id));
            if (!$w||$w->balance<$amount) throw new Exception('Insufficient balance.');
            $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance-%f WHERE id=%d AND user_id=%d",$amount,$wallet_id,$user_id));
        }
        $wpdb->query('COMMIT');
        wp_send_json_success(['message'=>$realize?'Funds moved!':'Allocation planned.']);
    } catch (Exception $e) { $wpdb->query('ROLLBACK'); wp_send_json_error(['message'=>$e->getMessage()]); }
}

function bntm_ajax_ft_get_goal_allocations() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(); return; }
    bntm_ft_maybe_upgrade_schema();
    global $wpdb; $p=$wpdb->prefix; $user_id=get_current_user_id(); $goal_id=intval($_POST['goal_id']);
    $allocs=$wpdb->get_results($wpdb->prepare("SELECT a.*,w.name as wallet_name,g.name as goal_name FROM {$p}ft_goal_allocations a LEFT JOIN {$p}ft_wallets w ON w.id=a.wallet_id LEFT JOIN {$p}ft_goals g ON g.id=a.goal_id WHERE a.user_id=%d AND a.goal_id=%d AND a.status!='deleted' ORDER BY COALESCE(a.realize_date,a.plan_date,DATE(a.created_at)) DESC,a.created_at DESC",$user_id,$goal_id));
    foreach ($allocs as &$alloc) {
        $alloc->status_label=$alloc->status==='realized'?'Realized allocation':'Unrealized allocation';
        $alloc->goal_transaction_name=($alloc->goal_name?:'Goal').' - '.ucfirst($alloc->status);
        $alloc->display_date=$alloc->status==='realized'?($alloc->realize_date?:substr($alloc->created_at,0,10)):($alloc->plan_date?:substr($alloc->created_at,0,10));
    } unset($alloc);
    wp_send_json_success(['allocations'=>$allocs]);
}

function bntm_ajax_ft_realize_goal_allocation() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); return; }
    bntm_ft_maybe_upgrade_schema();
    global $wpdb; $p=$wpdb->prefix; $user_id=get_current_user_id(); $id=intval($_POST['allocation_id']??0);
    $alloc=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}ft_goal_allocations WHERE id=%d AND user_id=%d AND status='unrealized'",$id,$user_id));
    if (!$alloc) { wp_send_json_error(['message'=>'Allocation not found.']); return; }
    $wallet=$wpdb->get_row($wpdb->prepare("SELECT balance FROM {$p}ft_wallets WHERE id=%d AND user_id=%d",$alloc->wallet_id,$user_id));
    if (!$wallet||floatval($wallet->balance)<floatval($alloc->amount)) { wp_send_json_error(['message'=>'Insufficient balance.']); return; }
    $wpdb->query('START TRANSACTION');
    try {
        $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance-%f WHERE id=%d AND user_id=%d",floatval($alloc->amount),$alloc->wallet_id,$user_id));
        $wpdb->update($p.'ft_goal_allocations',['status'=>'realized','realize_date'=>date('Y-m-d')],['id'=>$id,'user_id'=>$user_id],['%s','%s'],['%d','%d']);
        $wpdb->query('COMMIT');
        wp_send_json_success(['message'=>'Allocation realized!']);
    } catch (Exception $e) { $wpdb->query('ROLLBACK'); wp_send_json_error(['message'=>$e->getMessage()]); }
}

function bntm_ajax_ft_edit_goal_allocation() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); return; }
    global $wpdb; $p=$wpdb->prefix; $user_id=get_current_user_id();
    $id=intval($_POST['allocation_id']); $amount=floatval($_POST['amount']); $note=sanitize_text_field($_POST['note']??'');
    $old=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}ft_goal_allocations WHERE id=%d AND user_id=%d AND status!='deleted'",$id,$user_id));
    if (!$old) { wp_send_json_error(['message'=>'Not found.']); return; }
    $diff=$amount-floatval($old->amount);
    $wpdb->query('START TRANSACTION');
    try {
        $wpdb->update($p.'ft_goal_allocations',['amount'=>$amount,'note'=>$note],['id'=>$id],['%f','%s'],['%d']);
        $wpdb->query($wpdb->prepare("UPDATE {$p}ft_goals SET current_amount=GREATEST(0,LEAST(current_amount+%f,target_amount)) WHERE id=%d AND user_id=%d",$diff,$old->goal_id,$user_id));
        if ($old->status==='realized'&&$diff!==0.0) {
            if ($diff>0) $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance-%f WHERE id=%d AND user_id=%d",$diff,$old->wallet_id,$user_id));
            else $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance+%f WHERE id=%d AND user_id=%d",abs($diff),$old->wallet_id,$user_id));
        }
        $wpdb->query('COMMIT');
        wp_send_json_success(['message'=>'Allocation updated!']);
    } catch (Exception $e) { $wpdb->query('ROLLBACK'); wp_send_json_error(['message'=>$e->getMessage()]); }
}

function bntm_ajax_ft_delete_goal_allocation() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); return; }
    global $wpdb; $p=$wpdb->prefix; $user_id=get_current_user_id(); $id=intval($_POST['allocation_id']);
    $old=$wpdb->get_row($wpdb->prepare("SELECT * FROM {$p}ft_goal_allocations WHERE id=%d AND user_id=%d AND status!='deleted'",$id,$user_id));
    if (!$old) { wp_send_json_error(['message'=>'Not found.']); return; }
    $wpdb->query('START TRANSACTION');
    try {
        $wpdb->update($p.'ft_goal_allocations',['status'=>'deleted'],['id'=>$id],['%s'],['%d']);
        $wpdb->query($wpdb->prepare("UPDATE {$p}ft_goals SET current_amount=GREATEST(0,current_amount-%f) WHERE id=%d AND user_id=%d",floatval($old->amount),$old->goal_id,$user_id));
        if ($old->status==='realized') $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance+%f WHERE id=%d AND user_id=%d",floatval($old->amount),$old->wallet_id,$user_id));
        $wpdb->query('COMMIT');
        wp_send_json_success(['message'=>'Allocation removed.']);
    } catch (Exception $e) { $wpdb->query('ROLLBACK'); wp_send_json_error(['message'=>$e->getMessage()]); }
}

// ============================================================
// AJAX: PROCESS SCHEDULED
// ============================================================

function bntm_ajax_ft_process_scheduled() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(); return; }
    global $wpdb; $p=$wpdb->prefix; $user_id=get_current_user_id(); $today=date('Y-m-d'); $processed=0;
    // Realize overdue pending transactions
    $due=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$p}ft_transactions WHERE user_id=%d AND is_realized=0 AND realize_date<=%s AND status='active'",$user_id,$today));
    foreach ($due as $t) {
        if ($t->type==='expense') $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance-%f WHERE id=%d AND user_id=%d",floatval($t->amount),$t->wallet_id,$user_id));
        elseif ($t->type==='income') $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance+%f WHERE id=%d AND user_id=%d",floatval($t->amount),$t->wallet_id,$user_id));
        $wpdb->update($p.'ft_transactions',['is_realized'=>1],['id'=>$t->id],['%d'],['%d']);
        $processed++;
    }
    // Auto-post due recurrences (Pro only)
    if (ft_is_pro($user_id)) {
        $recurs=$wpdb->get_results($wpdb->prepare("SELECT * FROM {$p}ft_recurrences WHERE user_id=%d AND status='active' AND next_date<=%s",$user_id,$today));
        foreach ($recurs as $r) {
            $wpdb->insert($p.'ft_transactions',['rand_id'=>bntm_rand_id(),'business_id'=>$user_id,'user_id'=>$user_id,'type'=>$r->type,'amount'=>$r->amount,'wallet_id'=>$r->wallet_id,'category_id'=>$r->category_id,'note'=>$r->name.' (auto)','transaction_date'=>$r->next_date,'is_recurring'=>1,'recurrence_id'=>$r->id,'is_realized'=>1,'status'=>'active'],['%s','%d','%d','%s','%f','%d','%d','%s','%s','%d','%d','%d','%s']);
            if ($r->type==='expense') $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance-%f WHERE id=%d AND user_id=%d",floatval($r->amount),$r->wallet_id,$user_id));
            else $wpdb->query($wpdb->prepare("UPDATE {$p}ft_wallets SET balance=balance+%f WHERE id=%d AND user_id=%d",floatval($r->amount),$r->wallet_id,$user_id));
            $next=match($r->frequency){
                'daily'  =>date('Y-m-d',strtotime($r->next_date.' +1 day')),
                'weekly' =>date('Y-m-d',strtotime($r->next_date.' +1 week')),
                'yearly' =>date('Y-m-d',strtotime($r->next_date.' +1 year')),
                default  =>date('Y-m-d',strtotime($r->next_date.' +1 month')),
            };
            $wpdb->update($p.'ft_recurrences',['next_date'=>$next],['id'=>$r->id],['%s'],['%d']);
            $processed++;
        }
    }
    wp_send_json_success(['processed'=>$processed]);
}

// ============================================================
// AJAX: OFFLINE SYNC QUEUE
// ============================================================

function bntm_ajax_ft_sync_queue() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error(['message'=>'Unauthorized']); return; }
    $user_id = get_current_user_id();
    if (!ft_is_pro($user_id)) { wp_send_json_error(['message'=>'Pro feature.']); return; }

    $queue_raw = sanitize_text_field($_POST['queue'] ?? '');
    if (empty($queue_raw)) { wp_send_json_success(['processed' => 0]); return; }

    $queue = json_decode(stripslashes($queue_raw), true);
    if (!is_array($queue) || empty($queue)) { wp_send_json_success(['processed' => 0]); return; }

    // Map of safe actions that can be queued
    $allowed_actions = [
        'ft_add_transaction', 'ft_edit_transaction', 'ft_delete_transaction',
        'ft_add_wallet', 'ft_edit_wallet', 'ft_delete_wallet',
        'ft_save_budget', 'ft_delete_budget',
        'ft_save_goal', 'ft_delete_goal',
        'ft_allocate_goal', 'ft_realize_goal_allocation',
        'ft_save_recurrence', 'ft_edit_recurrence', 'ft_delete_recurrence',
        'ft_add_category', 'ft_edit_category', 'ft_delete_category',
    ];

    $processed = 0;
    $errors    = [];

    foreach ($queue as $item) {
        $action  = sanitize_text_field($item['action'] ?? '');
        $payload = is_array($item['payload'] ?? null) ? $item['payload'] : [];

        if (!in_array($action, $allowed_actions, true)) {
            $errors[] = "Skipped unknown action: {$action}";
            continue;
        }

        // Inject user_id and nonce into $_POST so handlers work normally
        foreach ($payload as $k => $v) {
            $_POST[$k] = sanitize_text_field((string)$v);
        }
        $_POST['nonce'] = wp_create_nonce('ft_app_nonce'); // re-issue fresh nonce
        $_POST['action'] = $action;

        // Call the handler directly
        $handler_map = [
            'ft_add_transaction'         => 'bntm_ajax_ft_add_transaction',
            'ft_edit_transaction'        => 'bntm_ajax_ft_edit_transaction',
            'ft_delete_transaction'      => 'bntm_ajax_ft_delete_transaction',
            'ft_add_wallet'              => 'bntm_ajax_ft_add_wallet',
            'ft_edit_wallet'             => 'bntm_ajax_ft_edit_wallet',
            'ft_delete_wallet'           => 'bntm_ajax_ft_delete_wallet',
            'ft_save_budget'             => 'bntm_ajax_ft_save_budget',
            'ft_delete_budget'           => 'bntm_ajax_ft_delete_budget',
            'ft_save_goal'               => 'bntm_ajax_ft_save_goal',
            'ft_delete_goal'             => 'bntm_ajax_ft_delete_goal',
            'ft_allocate_goal'           => 'bntm_ajax_ft_allocate_goal',
            'ft_realize_goal_allocation' => 'bntm_ajax_ft_realize_goal_allocation',
            'ft_save_recurrence'         => 'bntm_ajax_ft_save_recurrence',
            'ft_edit_recurrence'         => 'bntm_ajax_ft_edit_recurrence',
            'ft_delete_recurrence'       => 'bntm_ajax_ft_delete_recurrence',
            'ft_add_category'            => 'bntm_ajax_ft_add_category',
            'ft_edit_category'           => 'bntm_ajax_ft_edit_category',
            'ft_delete_category'         => 'bntm_ajax_ft_delete_category',
        ];

        if (isset($handler_map[$action]) && function_exists($handler_map[$action])) {
            // Buffer the JSON output so it doesn't terminate execution
            ob_start();
            try {
                call_user_func($handler_map[$action]);
                $out = ob_get_clean();
                $result = json_decode($out, true);
                if (!empty($result['success'])) $processed++;
                else $errors[] = $action.': '.($result['data']['message'] ?? 'error');
            } catch (Throwable $e) {
                ob_get_clean();
                $errors[] = $action.': '.$e->getMessage();
            }
        }
    }

    wp_send_json_success(['processed' => $processed, 'errors' => $errors]);
}

// ============================================================
// AJAX: MAYA SETTINGS (Admin only)
// ============================================================

function bntm_ajax_ft_save_maya_settings() {
    check_ajax_referer('ft_admin_nonce','nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }
    $mode       = sanitize_text_field($_POST['mode'] ?? 'sandbox');
    $public_key = sanitize_text_field($_POST['public_key'] ?? '');
    $secret_key = sanitize_text_field($_POST['secret_key'] ?? '');
    update_option('ft_maya_mode', $mode);
    if (!empty($public_key)) update_option('ft_maya_public_key_enc', ft_encrypt_key($public_key));
    if (!empty($secret_key)) update_option('ft_maya_secret_key_enc', ft_encrypt_key($secret_key));
    wp_send_json_success(['message' => 'Settings saved.']);
}

function bntm_ajax_ft_save_pro_settings() {
    check_ajax_referer('ft_admin_nonce','nonce');
    if (!current_user_can('manage_options')) { wp_send_json_error('Unauthorized'); return; }

    update_option('ft_pro_price', max(0, (float) ($_POST['price'] ?? BNTM_FT_PRO_PRICE)));
    update_option('ft_free_wallet_limit', max(0, (int) ($_POST['free_wallet_limit'] ?? 1)));
    update_option('ft_free_category_limit', max(0, (int) ($_POST['free_category_limit'] ?? 3)));
    update_option('ft_home_transaction_limit', max(1, min(30, (int) ($_POST['home_transaction_limit'] ?? 5))));

    $defaults = ft_default_pro_features();
    $raw = wp_unslash($_POST['features'] ?? '{}');
    $posted = json_decode($raw, true);
    if (!is_array($posted)) $posted = [];
    $features = [];
    foreach ($defaults as $key => $default) {
        $item = isset($posted[$key]) && is_array($posted[$key]) ? $posted[$key] : [];
        $features[$key] = [
            'title'   => sanitize_text_field($item['title'] ?? $default['title']),
            'desc'    => sanitize_text_field($item['desc'] ?? $default['desc']),
            'color'   => sanitize_hex_color($item['color'] ?? $default['color']) ?: $default['color'],
            'enabled' => !empty($item['enabled']) ? 1 : 0,
        ];
    }
    update_option('ft_pro_features', $features);
    wp_send_json_success(['message' => 'Pro settings saved.']);
}

// ============================================================
// AJAX: PRO CHECKOUT (Maya)
// ============================================================

function bntm_ajax_ft_start_pro_checkout() {
    check_ajax_referer('ft_app_nonce','nonce');
    if (!is_user_logged_in()) { wp_send_json_error('Please log in.'); return; }
    $user_id = get_current_user_id();
    if (ft_is_pro($user_id)) { wp_send_json_error('Already Pro!'); return; }

    $config = ft_get_maya_config();
    if (empty($config['public_key'])) {
        wp_send_json_error('Payment gateway not configured. Please contact support.');
        return;
    }

    $user         = wp_get_current_user();
    $current_page = esc_url_raw($_POST['current_page'] ?? home_url('/'));
    $amount       = ft_get_pro_price();
    $license_key  = ft_generate_license_key();
    $statement_ref= strtoupper(substr(md5($license_key.'|'.time()),0,12));

    // Insert pending license
    global $wpdb;
    $lt = $wpdb->prefix.'ft_licenses';
    $wpdb->insert($lt,[
        'user_id'     => $user_id,
        'license_key' => $license_key,
        'plan'        => 'pro',
        'status'      => 'pending',
        'amount'      => $amount,
        'currency'    => 'PHP',
    ],['%d','%s','%s','%s','%f','%s']);
    $license_id = $wpdb->insert_id;

    // Store transient token
    $token = wp_generate_password(32,false,false);
    $app_page = get_page_by_path('finance-tracker');
    $base_url = $app_page ? get_permalink($app_page->ID) : home_url('/');
    $sep = strpos($base_url,'?')===false?'?':'&';
    $success_url = $base_url.$sep.'maya_payment=success&ft_token='.rawurlencode($token);
    $failure_url = $current_page.(strpos($current_page,'?')===false?'?':'&').'maya_payment=failed';

    set_transient('ft_pro_checkout_'.$token,[
        'license_id'    => $license_id,
        'license_key'   => $license_key,
        'user_id'       => $user_id,
        'amount'        => $amount,
        'statement_ref' => $statement_ref,
    ],DAY_IN_SECONDS);

    $payload=[
        'totalAmount'=>['value'=>floatval($amount),'currency'=>'PHP'],
        'buyer'=>['firstName'=>$user->first_name?:$user->display_name,'lastName'=>$user->last_name?:'','contact'=>['email'=>$user->user_email]],
        'items'=>[[
            'name'        =>'Finance Tracker Pro — '.$statement_ref,
            'quantity'    =>1,
            'amount'      =>['value'=>floatval($amount)],
            'totalAmount' =>['value'=>floatval($amount)],
        ]],
        'redirectUrl'=>['success'=>$success_url,'failure'=>$failure_url,'cancel'=>$failure_url],
        'requestReferenceNumber'=>$statement_ref,
        'metadata'=>['type'=>'ft_pro','statement_ref'=>$statement_ref],
    ];

    $result = ft_create_maya_checkout($payload,$config);

    if (!$result['success']) {
        delete_transient('ft_pro_checkout_'.$token);
        $wpdb->delete($lt,['id'=>$license_id],['%d']);
        wp_send_json_error($result['message']);
        return;
    }

    // Save payment_id
    $wpdb->update($lt,['payment_id'=>sanitize_text_field($result['checkout_id'])],['id'=>$license_id],['%s'],['%d']);

    wp_send_json_success(['redirect_url'=>$result['redirect_url'],'message'=>'Redirecting to secure checkout…']);
}

// ============================================================
// MAYA API HELPER
// ============================================================

function ft_create_maya_checkout(array $payload, array $config): array {
    $base_url = $config['mode']==='sandbox'
        ? 'https://pg-sandbox.paymaya.com/checkout/v1/checkouts'
        : 'https://pg.maya.ph/checkout/v1/checkouts';

    $response = wp_remote_post($base_url,[
        'method'  => 'POST',
        'headers' => [
            'Authorization' => 'Basic '.base64_encode($config['public_key'].':'),
            'Content-Type'  => 'application/json',
        ],
        'body'    => json_encode($payload),
        'timeout' => 30,
    ]);

    if (is_wp_error($response)) {
        return ['success'=>false,'message'=>'Network error: '.$response->get_error_message()];
    }

    $code = wp_remote_retrieve_response_code($response);
    $data = json_decode(wp_remote_retrieve_body($response),true);

    if (!in_array($code,[200,201])) {
        $msg = $data['message'] ?? $data['error'] ?? 'Checkout creation failed.';
        if (empty($msg)&&isset($data['errors'])) $msg=wp_json_encode($data['errors']);
        return ['success'=>false,'message'=>$msg];
    }

    if (empty($data['checkoutId'])) {
        return ['success'=>false,'message'=>'Payment gateway did not return a valid session.'];
    }

    $redirect = $data['redirectUrl']
        ??($config['mode']==='sandbox'
            ? 'https://pg-sandbox.paymaya.com/checkout?id='.$data['checkoutId']
            : 'https://pg.maya.ph/checkout?id='.$data['checkoutId']);

    return ['success'=>true,'checkout_id'=>$data['checkoutId'],'redirect_url'=>$redirect,'message'=>'OK'];
}

// ============================================================
// MAYA RETURN HANDLER (template_redirect)
// ============================================================

function ft_handle_maya_return() {
    $token        = isset($_GET['ft_token'])      ? sanitize_text_field(wp_unslash($_GET['ft_token']))      : '';
    $maya_payment = isset($_GET['maya_payment'])  ? sanitize_text_field(wp_unslash($_GET['maya_payment'])) : '';
    if (empty($token) || $maya_payment !== 'success') return;

    $checkout = get_transient('ft_pro_checkout_'.$token);
    if (empty($checkout['license_id']) || empty($checkout['user_id'])) return;

    global $wpdb;
    $lt = $wpdb->prefix.'ft_licenses';
    $lic = $wpdb->get_row($wpdb->prepare("SELECT * FROM {$lt} WHERE id=%d LIMIT 1",intval($checkout['license_id'])));
    if (!$lic) { delete_transient('ft_pro_checkout_'.$token); return; }

    if ($lic->status !== 'active') {
        $wpdb->update($lt,[
            'status'      => 'active',
            'purchased_at'=> current_time('mysql'),
        ],['id'=>intval($lic->id)],['%s','%s'],['%d']);

        // Grant Pro to user
        update_user_meta(intval($checkout['user_id']),'ft_pro_active',1);
        update_user_meta(intval($checkout['user_id']),'ft_pro_license_key',$lic->license_key);
        update_user_meta(intval($checkout['user_id']),'ft_pro_purchased_at',current_time('mysql'));
    }

    delete_transient('ft_pro_checkout_'.$token);

    // Redirect back to app with success flag
    $app_page = get_page_by_path('finance-tracker');
    $url      = $app_page ? get_permalink($app_page->ID) : home_url('/');
    $sep      = strpos($url,'?')===false?'?':'&';
    wp_safe_redirect($url.$sep.'maya_payment=success');
    exit;
}
