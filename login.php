<?php 
/* ============================================================================
   LOGIN MODULE WITH ADAPTIVE LIMIT CHECKING
   ============================================================================ */
function bntm_shortcode_login() {
    if ( is_user_logged_in() ) {
        $dashboard = get_page_by_title( 'Dashboard' );
        $url = $dashboard ? get_permalink( $dashboard->ID ) : home_url();
        wp_redirect( $url );
        exit;
    }

    $logo  = bntm_get_site_logo();
    $title = bntm_get_site_title();
    $nonce = wp_create_nonce( 'bntm_login' );
    $registration_settings = function_exists('bntm_get_registration_settings') ? bntm_get_registration_settings() : ['enabled' => 0];
    $registration_page = get_page_by_title('Register');
    $registration_url = $registration_page ? get_permalink($registration_page->ID) : home_url('/register');

    ob_start();
    ?>
    <!DOCTYPE html>
    <html>
    <head>
    <meta charset="<?php echo get_bloginfo( 'charset' ); ?>">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title><?php echo esc_html( $title ); ?> &mdash; Sign In</title>
    <?php wp_head(); ?>
    <style>
    *, *::before, *::after { box-sizing: border-box; margin: 0; padding: 0; }

    html, body {
        height: 100%;
        font-family: 'Plus Jakarta Sans', -apple-system, BlinkMacSystemFont, 'Segoe UI', sans-serif;
        -webkit-font-smoothing: antialiased;
        background: #f0f4f8;
    }

    .bl-wrap {
        display: flex;
        min-height: 100vh;
    }

    /* ═══════════════════════════════════════
       LEFT BRANDED PANEL (desktop)
    ═══════════════════════════════════════ */
    .bl-panel {
        flex: 1 1 45%;
        max-width: 500px;
        background: var(--bntm-primary);
        position: relative;
        display: flex;
        flex-direction: column;
        justify-content: space-between;
        padding: 52px 56px;
        overflow: hidden;
        animation: panelIn 0.7s cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    @keyframes panelIn {
        from { transform: translateX(-40px); opacity: 0; }
        to   { transform: translateX(0); opacity: 1; }
    }

    /* Dot grid */
    .bl-panel::before {
        content: '';
        position: absolute;
        inset: 0;
        background-image: radial-gradient(circle, rgba(255,255,255,0.13) 1px, transparent 1px);
        background-size: 28px 28px;
        pointer-events: none;
    }

    /* Top-right glow circle */
    .bl-panel::after {
        content: '';
        position: absolute;
        top: -130px;
        right: -130px;
        width: 440px;
        height: 440px;
        border-radius: 50%;
        background: rgba(255,255,255,0.07);
        pointer-events: none;
    }

    /* Bottom-left circle */
    .bl-circle2 {
        position: absolute;
        bottom: -90px;
        left: -90px;
        width: 320px;
        height: 320px;
        border-radius: 50%;
        background: rgba(255,255,255,0.05);
        pointer-events: none;
    }

    /* ── Logo frame ── */
    .bl-brand {
        position: relative;
        z-index: 1;
        animation: fadeUp 0.7s 0.15s cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    .bl-logo-frame {
        display: inline-flex;
        align-items: center;
        justify-content: center;
        background: white;
        border-radius: 16px;
        padding: 12px 18px;
        margin-bottom: 32px;
        box-shadow: 0 8px 24px rgba(0,0,0,0.15);
    }

    .bl-logo-frame img {
        height: 40px;
        width: auto;
        max-width: 150px;
        object-fit: contain;
        display: block;
    }

    .bl-brand-name {
        font-size: 30px;
        font-weight: 800;
        color: white;
        letter-spacing: -0.7px;
        line-height: 1.15;
        margin-bottom: 12px;
    }

    .bl-brand-sub {
        font-size: 15px;
        color: rgba(255,255,255,0.62);
        line-height: 1.65;
        max-width: 280px;
    }

    .bl-features {
        position: relative;
        z-index: 1;
        display: flex;
        flex-direction: column;
        gap: 14px;
        animation: fadeUp 0.7s 0.28s cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    .bl-feature {
        display: flex;
        align-items: center;
        gap: 12px;
        color: rgba(255,255,255,0.78);
        font-size: 14px;
        font-weight: 500;
    }

    .bl-feature-dot {
        width: 7px;
        height: 7px;
        border-radius: 50%;
        background: rgba(255,255,255,0.45);
        flex-shrink: 0;
    }

    /* ═══════════════════════════════════════
       RIGHT FORM PANEL (desktop)
    ═══════════════════════════════════════ */
    .bl-form-side {
        flex: 1 1 55%;
        display: flex;
        align-items: center;
        justify-content: center;
        padding: 40px 24px;
        background: #f0f4f8;
        animation: formIn 0.65s 0.1s cubic-bezier(0.22, 1, 0.36, 1) both;
    }

    @keyframes formIn {
        from { opacity: 0; transform: translateY(18px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    .bl-card {
        background: white;
        border-radius: 20px;
        padding: 44px 44px 40px;
        width: 100%;
        max-width: 420px;
        box-shadow:
            0 0 0 1px rgba(0,0,0,0.04),
            0 4px 6px rgba(0,0,0,0.04),
            0 20px 40px rgba(0,0,0,0.08);
    }

    /* Desktop: no mobile header */
    .bl-mobile-header { display: none; }

    .bl-card-heading {
        font-size: 24px;
        font-weight: 800;
        color: #0f172a;
        letter-spacing: -0.5px;
        margin-bottom: 6px;
    }

    .bl-card-sub {
        font-size: 14px;
        color: #94a3b8;
        margin-bottom: 30px;
        line-height: 1.5;
    }

    /* ── Form fields ── */
    .bl-field { margin-bottom: 16px; }

    .bl-field label {
        display: block;
        font-size: 11px;
        font-weight: 700;
        color: #475569;
        text-transform: uppercase;
        letter-spacing: 0.7px;
        margin-bottom: 7px;
    }

    .bl-field-wrap { position: relative; }

    .bl-field input[type="text"],
    .bl-field input[type="password"],
    .bl-field input[type="email"] {
        display: block;
        width: 100%;
        padding: 12px 14px;
        border: 1.5px solid #e2e8f0;
        border-radius: 10px;
        font-size: 14px;
        font-family: inherit;
        color: #1e293b;
        background: white;
        transition: border-color 0.18s, box-shadow 0.18s;
        outline: none;
        -webkit-appearance: none;
    }

    .bl-field input:focus {
        border-color: var(--bntm-primary);
        box-shadow: 0 0 0 3px rgba(59,130,246,0.1);
    }

    .bl-pw-toggle {
        position: absolute;
        right: 11px;
        top: 50%;
        transform: translateY(-50%);
        background: none;
        border: none;
        cursor: pointer;
        color: #94a3b8;
        padding: 4px;
        display: flex;
        align-items: center;
        transition: color 0.14s;
    }

    .bl-pw-toggle:hover { color: #475569; }

    .bl-remember {
        display: flex;
        align-items: center;
        gap: 8px;
        margin-bottom: 22px;
        cursor: pointer;
        user-select: none;
    }

    .bl-remember input[type="checkbox"] {
        width: 15px;
        height: 15px;
        accent-color: var(--bntm-primary);
        cursor: pointer;
        flex-shrink: 0;
    }

    .bl-remember span {
        font-size: 13px;
        color: #64748b;
        font-weight: 500;
    }

    .bl-submit {
        width: 100%;
        padding: 13px 20px;
        background: var(--bntm-primary);
        color: white;
        border: none;
        border-radius: 10px;
        font-size: 15px;
        font-weight: 700;
        font-family: inherit;
        cursor: pointer;
        transition: background 0.18s, transform 0.14s, box-shadow 0.18s;
        position: relative;
        overflow: hidden;
        letter-spacing: -0.1px;
    }

    .bl-submit:hover {
        background: var(--bntm-primary-hover, var(--bntm-primary));
        transform: translateY(-1px);
        box-shadow: 0 6px 20px rgba(59,130,246,0.28);
    }

    .bl-submit:active { transform: translateY(0); box-shadow: none; }

    .bl-submit:disabled {
        opacity: 0.65;
        cursor: not-allowed;
        transform: none;
        box-shadow: none;
    }

    .bl-submit.loading::after {
        content: '';
        position: absolute;
        inset: 0;
        background: linear-gradient(90deg, transparent, rgba(255,255,255,0.18), transparent);
        animation: shimmer 1.2s infinite;
    }

    @keyframes shimmer {
        from { transform: translateX(-100%); }
        to   { transform: translateX(100%); }
    }

    .bl-message { margin-top: 14px; }

    .bntm-notice {
        padding: 11px 14px;
        border-radius: 8px;
        font-size: 13px;
        font-weight: 500;
        line-height: 1.5;
    }

    .bntm-notice-error  { background: #fef2f2; color: #b91c1c; border: 1px solid #fecaca; }
    .bntm-notice-success { background: #f0fdf4; color: #166534; border: 1px solid #bbf7d0; }

    @keyframes fadeUp {
        from { opacity: 0; transform: translateY(16px); }
        to   { opacity: 1; transform: translateY(0); }
    }

    /* ═══════════════════════════════════════
       MOBILE — full branded screen + card
    ═══════════════════════════════════════ */
    @media (max-width: 768px) {

        body { background: var(--bntm-primary); }

        .bl-wrap {
            flex-direction: column;
            min-height: 100vh;
            position: relative;
        }

        /* Hide the desktop left panel */
        .bl-panel { display: none; }

        /* Mobile top area: logo + title on the primary bg */
        .bl-mobile-header {
            display: flex;
            flex-direction: column;
            align-items: center;
            padding: 52px 24px 36px;
            position: relative;
            z-index: 1;
            animation: fadeUp 0.55s 0.05s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        /* White frame for logo on mobile */
        .bl-mobile-logo-frame {
            display: inline-flex;
            align-items: center;
            justify-content: center;
            background: white;
            border-radius: 18px;
            padding: 12px 20px;
            margin-bottom: 16px;
            box-shadow: 0 8px 28px rgba(0,0,0,0.18);
        }

        .bl-mobile-logo-frame img {
            height: auto;
            width: 100px;
            max-width: 160px;
            object-fit: contain;
            display: block;
        }

        .bl-mobile-site-name {
            font-size: 22px;
            font-weight: 800;
            color: white;
            letter-spacing: -0.4px;
            text-align: center;
        }

        .bl-mobile-site-sub {
            font-size: 13px;
            color: rgba(255,255,255,0.6);
            margin-top: 5px;
            text-align: center;
        }

        /* Form side: white sheet sliding up from bottom */
        .bl-form-side {
            flex: 1;
            background: transparent;
            padding: 0;
            align-items: flex-end;
            justify-content: stretch;
            animation: cardSlideUp 0.6s 0.18s cubic-bezier(0.22, 1, 0.36, 1) both;
        }

        @keyframes cardSlideUp {
            from { opacity: 0; transform: translateY(40px); }
            to   { opacity: 1; transform: translateY(0); }
        }

        .bl-card {
            width: 100%;
            max-width: 100%;
            border-radius: 24px 24px 0 0;
            padding: 30px 24px 36px;
            box-shadow: 0 -8px 40px rgba(0,0,0,0.14);
            /* Extend card to bottom of viewport */
            min-height: calc(100vh - 240px);
        }

        /* On mobile hide the desktop heading since mobile header carries branding */
        .bl-card-heading { font-size: 20px; margin-bottom: 4px; }
        .bl-card-sub { font-size: 13px; margin-bottom: 22px; }

        .bl-field input[type="text"],
        .bl-field input[type="password"],
        .bl-field input[type="email"] {
            padding: 13px 14px;
            font-size: 15px; /* prevent iOS zoom */
        }

        .bl-submit { padding: 14px 20px; font-size: 15px; }
    }

    /* Extra-small phones */
    @media (max-width: 380px) {
        .bl-mobile-header { padding: 40px 20px 28px; }
        .bl-card { padding: 24px 18px 30px; }
    }

    /* Hide BNTM shell elements */
    .bntm-topbar, .bntm-header, .bntm-sidebar,
    .bntm-sidebar-overlay, .bntm-layout { display: none !important; }
    .bntm-content { background: transparent !important; box-shadow: none !important; padding: 0 !important; }
    .bntm-container { padding: 0 !important; max-width: 100% !important; }
    </style>
    </head>
    <body>
    <?php wp_body_open(); ?>

    <div class="bl-wrap">

        <!-- ── Desktop left panel ── -->
        <div class="bl-panel">
            <div class="bl-circle2"></div>

            <div class="bl-brand">
                <?php if ( $logo ) : ?>
                    <div class="bl-logo-frame">
                        <img src="<?php echo esc_url( $logo ); ?>"
                             alt="<?php echo esc_attr( $title ); ?>">
                    </div>
                <?php endif; ?>
                <div class="bl-brand-name"><?php echo esc_html( $title ); ?></div>
                <p class="bl-brand-sub">Sign in to access your workspace and business tools.</p>
            </div>

            <div class="bl-features">
                <div class="bl-feature"><div class="bl-feature-dot"></div>Secure single-session access</div>
                <div class="bl-feature"><div class="bl-feature-dot"></div>Role-based module access</div>
                <div class="bl-feature"><div class="bl-feature-dot"></div>Real-time business tools</div>
            </div>
        </div>

        <!-- ── Mobile top header (primary bg) ── -->
        <div class="bl-mobile-header">
            <?php if ( $logo ) : ?>
                <div class="bl-mobile-logo-frame">
                    <img src="<?php echo esc_url( $logo ); ?>"
                         alt="<?php echo esc_attr( $title ); ?>">
                </div>
            <?php endif; ?>
            <div class="bl-mobile-site-name"><?php echo esc_html( $title ); ?></div>
            <div class="bl-mobile-site-sub">Sign in to continue</div>
        </div>

        <!-- ── Form panel ── -->
        <div class="bl-form-side">
            <div class="bl-card">

                <h1 class="bl-card-heading">Welcome back</h1>
                <p class="bl-card-sub">Enter your credentials to continue</p>

                <form id="bntm-login-form" novalidate>

                    <div class="bl-field">
                        <label for="username">Username or Email</label>
                        <div class="bl-field-wrap">
                            <input type="text"
                                   id="username"
                                   name="username"
                                   placeholder="you@example.com"
                                   autocomplete="username"
                                   required
                                   autofocus>
                        </div>
                    </div>

                    <div class="bl-field">
                        <label for="password">Password</label>
                        <div class="bl-field-wrap">
                            <input type="password"
                                   id="password"
                                   name="password"
                                   placeholder="••••••••"
                                   autocomplete="current-password"
                                   required
                                   style="padding-right:42px;">
                            <button type="button" class="bl-pw-toggle" id="blPwToggle" aria-label="Show password">
                                <svg id="blEyeShow" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 12a3 3 0 11-6 0 3 3 0 016 0z"/>
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M2.458 12C3.732 7.943 7.523 5 12 5c4.478 0 8.268 2.943 9.542 7-1.274 4.057-5.064 7-9.542 7-4.477 0-8.268-2.943-9.542-7z"/>
                                </svg>
                                <svg id="blEyeHide" width="18" height="18" fill="none" stroke="currentColor" viewBox="0 0 24 24" style="display:none">
                                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.875 18.825A10.05 10.05 0 0112 19c-4.478 0-8.268-2.943-9.543-7a9.97 9.97 0 011.563-3.029m5.858.908a3 3 0 114.243 4.243M9.878 9.878l4.242 4.242M9.88 9.88l-3.29-3.29m7.532 7.532l3.29 3.29M3 3l3.59 3.59m0 0A9.953 9.953 0 0112 5c4.478 0 8.268 2.943 9.543 7a10.025 10.025 0 01-4.132 5.411m0 0L21 21"/>
                                </svg>
                            </button>
                        </div>
                    </div>

                    <label class="bl-remember">
                        <input type="checkbox" name="remember" value="1">
                        <span>Keep me signed in</span>
                    </label>

                    <button type="submit" class="bl-submit" id="blSubmitBtn">Sign In</button>
                    <?php if (!empty($registration_settings['enabled'])) : ?>
                        <p style="margin-top:12px;text-align:center;font-size:13px;color:#64748b;">
                            Need an account?
                            <a href="<?php echo esc_url($registration_url); ?>" style="color:var(--bntm-primary);font-weight:600;text-decoration:none;">Register here</a>
                        </p>
                    <?php endif; ?>

                    <div class="bl-message" id="login-message"></div>

                </form>
            </div>
        </div>

    </div><!-- .bl-wrap -->

    <script>
    (function () {
        var ajaxurl = '<?php echo esc_js( admin_url( 'admin-ajax.php' ) ); ?>';

        /* Password toggle */
        var pwInput = document.getElementById('password');
        var toggle  = document.getElementById('blPwToggle');
        var eyeShow = document.getElementById('blEyeShow');
        var eyeHide = document.getElementById('blEyeHide');

        if (toggle) {
            toggle.addEventListener('click', function () {
                var show = pwInput.type === 'password';
                pwInput.type = show ? 'text' : 'password';
                eyeShow.style.display = show ? 'none'  : 'block';
                eyeHide.style.display = show ? 'block' : 'none';
            });
        }

        /* Device fingerprint */
        function getDeviceFingerprint() {
            var str  = navigator.userAgent + window.screen.width + 'x' + window.screen.height
                     + Intl.DateTimeFormat().resolvedOptions().timeZone
                     + navigator.language + navigator.platform;
            var hash = 0;
            for (var i = 0; i < str.length; i++) {
                var c = str.charCodeAt(i);
                hash  = ((hash << 5) - hash) + c;
                hash  = hash & hash;
            }
            return Math.abs(hash).toString(36);
        }

        /* Form submit */
        document.getElementById('bntm-login-form').addEventListener('submit', function (e) {
            e.preventDefault();

            var form = this;
            var msg  = document.getElementById('login-message');
            var btn  = document.getElementById('blSubmitBtn');

            btn.disabled    = true;
            btn.textContent = 'Signing in…';
            btn.classList.add('loading');
            msg.innerHTML   = '';

            var data = new FormData();
            data.append('action',             'bntm_login');
            data.append('username',           form.username.value);
            data.append('password',           form.password.value);
            data.append('remember',           form.remember.checked ? '1' : '0');
            data.append('device_fingerprint', getDeviceFingerprint());
            data.append('_ajax_nonce',        '<?php echo esc_js( $nonce ); ?>');

            fetch(ajaxurl, { method: 'POST', body: data })
                .then(function (r) { return r.json(); })
                .then(function (json) {
                    btn.classList.remove('loading');
                    if (json.success) {
                        btn.textContent = '✓ Redirecting…';
                        msg.innerHTML   = '<div class="bntm-notice bntm-notice-success">' + json.data.message + '</div>';
                        setTimeout(function () { location.href = json.data.redirect; }, 800);
                    } else {
                        msg.innerHTML   = '<div class="bntm-notice bntm-notice-error">' + json.data + '</div>';
                        btn.disabled    = false;
                        btn.textContent = 'Sign In';
                    }
                })
                .catch(function (err) {
                    console.error(err);
                    btn.classList.remove('loading');
                    msg.innerHTML   = '<div class="bntm-notice bntm-notice-error">Unexpected error. Please try again.</div>';
                    btn.disabled    = false;
                    btn.textContent = 'Sign In';
                });
        });
    })();
    </script>

    <?php wp_footer(); ?>
    </body>
    </html>
    <?php
    return ob_get_clean();
}
/* ============================================================================
   ADAPTIVE LIMIT CHECKING SYSTEM
   ============================================================================ */

/**
 * Get adaptive cache duration based on proximity to limits
 */
function bntm_get_adaptive_cache_duration() {
    $cached_status = get_transient('bntm_limit_status');
    
    if ($cached_status === false) {
        return 5 * MINUTE_IN_SECONDS; // Initial check: 5 minutes
    }
    
    // If locked, check frequently (users shouldn't be here anyway)
    if ($cached_status['locked']) {
        return 2 * MINUTE_IN_SECONDS; // 2 minutes
    }
    
    // If warning (80%+), check more often
    if ($cached_status['warning']) {
        return 5 * MINUTE_IN_SECONDS; // 5 minutes
    }
    
    // Calculate highest percentage from any limit
    $highest_percentage = 0;
    
    // Check warning limits
    if (!empty($cached_status['limits_warning'])) {
        foreach ($cached_status['limits_warning'] as $limit) {
            if (isset($limit['percentage']) && $limit['percentage'] > $highest_percentage) {
                $highest_percentage = $limit['percentage'];
            }
        }
    }
    
    // If at 50-79%, moderate checking
    if ($highest_percentage >= 50) {
        return 10 * MINUTE_IN_SECONDS; // 10 minutes
    }
    
    // If healthy (under 50%), check less frequently
    return 30 * MINUTE_IN_SECONDS; // 30 minutes
}

/**
 * Clear limit cache when relevant data changes
 */
function bntm_clear_limit_cache() {
    delete_transient('bntm_limit_status');
}

// Clear cache when limits are updated
add_action('update_option_bntm_table_limits', 'bntm_clear_limit_cache');
add_action('update_option_bntm_user_limit', 'bntm_clear_limit_cache');

// Clear cache when new records are added
add_action('user_register', 'bntm_clear_limit_cache');

/* ============================================================================
   LOGIN & SESSION MANAGEMENT
   ============================================================================ */


// Helper function to get client identifier (IP + Device)
function bntm_get_client_identifier() {
    $ip = '';
    
    // Get IP address
    if (!empty($_SERVER['HTTP_CLIENT_IP'])) {
        $ip = $_SERVER['HTTP_CLIENT_IP'];
    } elseif (!empty($_SERVER['HTTP_X_FORWARDED_FOR'])) {
        $ip = explode(',', $_SERVER['HTTP_X_FORWARDED_FOR'])[0];
    } else {
        $ip = $_SERVER['REMOTE_ADDR'] ?? '';
    }
    
    $device_fingerprint = sanitize_text_field($_POST['device_fingerprint'] ?? '');
    
    return md5($ip . $device_fingerprint);
}

// Check if client is locked out
function bntm_is_client_locked($client_id) {
    $lockout_data = get_transient('bntm_login_lockout_' . $client_id);
    
    if ($lockout_data) {
        $locked_until = $lockout_data['locked_until'];
        if (time() < $locked_until) {
            $remaining_minutes = ceil(($locked_until - time()) / 60);
            return [
                'locked' => true,
                'minutes' => $remaining_minutes
            ];
        } else {
            // Lockout expired, clean up
            delete_transient('bntm_login_lockout_' . $client_id);
            delete_transient('bntm_login_attempts_' . $client_id);
        }
    }
    
    return ['locked' => false];
}

// Record failed login attempt
function bntm_record_failed_attempt($client_id) {
    $max_attempts = 5;
    $lockout_duration = 15 * MINUTE_IN_SECONDS; // 15 minutes
    
    $attempts = get_transient('bntm_login_attempts_' . $client_id);
    
    if (!$attempts) {
        $attempts = [
            'count' => 0,
            'first_attempt' => time()
        ];
    }
    
    $attempts['count']++;
    $attempts['last_attempt'] = time();
    
    // Store attempts for 15 minutes
    set_transient('bntm_login_attempts_' . $client_id, $attempts, $lockout_duration);
    
    // Check if max attempts reached
    if ($attempts['count'] >= $max_attempts) {
        $locked_until = time() + $lockout_duration;
        set_transient('bntm_login_lockout_' . $client_id, [
            'locked_until' => $locked_until,
            'attempt_count' => $attempts['count']
        ], $lockout_duration);
        
        return [
            'locked' => true,
            'attempts' => $attempts['count']
        ];
    }
    
    return [
        'locked' => false,
        'attempts' => $attempts['count'],
        'remaining' => $max_attempts - $attempts['count']
    ];
}

// Clear login attempts on successful login
function bntm_clear_login_attempts($client_id) {
    delete_transient('bntm_login_attempts_' . $client_id);
    delete_transient('bntm_login_lockout_' . $client_id);
}

// AJAX Login Handler with Rate Limiting
add_action('wp_ajax_bntm_login', 'bntm_ajax_login');
add_action('wp_ajax_nopriv_bntm_login', 'bntm_ajax_login');
function bntm_ajax_login() {
    check_ajax_referer('bntm_login');
    
    $username = sanitize_text_field($_POST['username'] ?? '');
    $password = $_POST['password'] ?? '';
    $remember = isset($_POST['remember']) && $_POST['remember'] === '1';
    
    if (empty($username) || empty($password)) {
        wp_send_json_error('Please enter your username and password.');
    }
    
    // Get client identifier (IP + Device)
    $client_id = bntm_get_client_identifier();
    
    // Check if client is locked out
    $lockout_status = bntm_is_client_locked($client_id);
    if ($lockout_status['locked']) {
        wp_send_json_error('Too many failed login attempts. Please try again in ' . $lockout_status['minutes'] . ' minute(s).');
    }
    
    // Attempt login
    $creds = [
        'user_login'    => $username,
        'user_password' => $password,
        'remember'      => $remember
    ];
    
    $user = wp_signon($creds, false);
    
    if (is_wp_error($user)) {
        // Record failed attempt
        $attempt_status = bntm_record_failed_attempt($client_id);
        
        if ($attempt_status['locked']) {
            wp_send_json_error('Too many failed login attempts. Your account has been locked for 15 minutes.');
        } else {
            $remaining = $attempt_status['remaining'];
            $message = 'Invalid username or password.';
            if ($remaining <= 2) {
                $message .= ' You have ' . $remaining . ' attempt(s) remaining.';
            }
            wp_send_json_error($message);
        }
    }

    $account_status = get_user_meta($user->ID, 'bntm_account_status', true);
    if ($account_status === 'frozen') {
        wp_logout();
        wp_send_json_error('Your account is frozen. Please contact your administrator.');
    }

    $registration_settings = function_exists('bntm_get_registration_settings') ? bntm_get_registration_settings() : ['require_verification' => 0];
    if (!empty($registration_settings['require_verification'])) {
        $verified_meta = get_user_meta($user->ID, 'bntm_email_verified', true);
        $is_verified = ($verified_meta === '' || (int) $verified_meta === 1);
        if (!$is_verified) {
            wp_logout();
            wp_send_json_error('Please verify your email before logging in.');
        }
    }
    
    // Successful login - clear attempts
    bntm_clear_login_attempts($client_id);
    
    // Generate unique session token
    $session_token = wp_generate_password(32, false);
    
    // Store session token in user meta
    update_user_meta($user->ID, '_bntm_session_token', $session_token);
    
    // Store session token in cookie
    setcookie('bntm_session_token', $session_token, time() + (30 * DAY_IN_SECONDS), COOKIEPATH, COOKIE_DOMAIN, is_ssl(), true);
    
    // Set WordPress authentication
    wp_set_current_user($user->ID);
    wp_set_auth_cookie($user->ID, $remember);
    
    // Check limits for non-admins
    $is_admin = user_can($user, 'manage_options');
    
    if (!$is_admin) {
        // Uncomment when limit checking function is ready
        // $limit_status = bntm_check_all_limits();
        
        // If limits are exceeded, prevent login
        // if ($limit_status['locked']) {
        //     wp_logout();
        //     delete_user_meta($user->ID, '_bntm_session_token');
        //     setcookie('bntm_session_token', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        //     
        //     wp_send_json_error('System limits reached. Please contact your administrator to expand capacity.');
        // }
        
        // Set last limit check timestamp
        update_user_meta($user->ID, '_bntm_last_limit_check', time());
    }
    
    // Redirects
    if ($is_admin) {
        $redirect = admin_url();
    } else {
        $dashboard = get_page_by_title('Dashboard');
        $redirect = $dashboard ? get_permalink($dashboard->ID) : home_url('dashboard');
    }
    
    wp_send_json_success([
        'message'  => 'Login successful!',
        'redirect' => $redirect
    ]);
}

add_filter('authenticate', 'bntm_block_frozen_users_auth', 30, 3);
function bntm_block_frozen_users_auth($user, $username, $password) {
    if (is_wp_error($user) || !$user instanceof WP_User) {
        return $user;
    }

    $account_status = get_user_meta($user->ID, 'bntm_account_status', true);
    if ($account_status === 'frozen') {
        return new WP_Error('bntm_frozen', 'Your account is frozen. Please contact your administrator.');
    }

    $registration_settings = function_exists('bntm_get_registration_settings') ? bntm_get_registration_settings() : ['require_verification' => 0];
    if (!empty($registration_settings['require_verification'])) {
        $verified_meta = get_user_meta($user->ID, 'bntm_email_verified', true);
        $is_verified = ($verified_meta === '' || (int) $verified_meta === 1);
        if (!$is_verified) {
            return new WP_Error('bntm_unverified', 'Please verify your email before logging in.');
        }
    }

    return $user;
}

// Check session validity and limits on every page load (OPTIMIZED)
add_action('init', 'bntm_check_single_session', 1);
function bntm_check_single_session() {
    // Skip checks for AJAX, admin, and non-logged-in users
    if (wp_doing_ajax() || is_admin() || !is_user_logged_in()) {
        return;
    }

    $user_id = get_current_user_id();
    $account_status = get_user_meta($user_id, 'bntm_account_status', true);
    if ($account_status === 'frozen') {
        wp_logout();
        wp_safe_redirect(home_url('/login'));
        exit;
    }
    
    // Get stored session token from user meta
    $stored_token = get_user_meta($user_id, '_bntm_session_token', true);
    
    // Get session token from cookie
    $cookie_token = $_COOKIE['bntm_session_token'] ?? '';
    
    // If tokens don't match, user is logged in elsewhere
    if (empty($stored_token) || $stored_token !== $cookie_token) {
        wp_logout();
        setcookie('bntm_session_token', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
        
        $login_page = get_page_by_title('Login');
        $login_url = $login_page ? get_permalink($login_page->ID) : wp_login_url();
        $redirect_url = add_query_arg('session_error', '1', $login_url);
        
        wp_redirect($redirect_url);
        exit;
    }
    
    // Limit check with adaptive timing for non-admins
    if (!current_user_can('manage_options')) {
        $last_check = get_user_meta($user_id, '_bntm_last_limit_check', true);
        $current_time = time();
        
        // Get the cached status to determine check interval
        $cached_status = get_transient('bntm_limit_status');
        
        // Determine check interval based on status
        if ($cached_status && isset($cached_status['warning'])) {
            $check_interval = $cached_status['warning'] ? 300 : 900; // 5 min if warning, 15 min if ok
        } else {
            $check_interval = 900; // Default 15 minutes
        }
        
        // Only check if interval has passed
        if (empty($last_check) || ($current_time - $last_check) > $check_interval) {
            //$limit_status = bntm_check_all_limits();
            update_user_meta($user_id, '_bntm_last_limit_check', $current_time);
            
            // If locked, force logout immediately
            if ($limit_status['locked']) {
                wp_logout();
                delete_user_meta($user_id, '_bntm_session_token');
                setcookie('bntm_session_token', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
                
                $login_page = get_page_by_title('Login');
                $login_url = $login_page ? get_permalink($login_page->ID) : wp_login_url();
                $redirect_url = add_query_arg('limit_error', '1', $login_url);
                
                wp_redirect($redirect_url);
                exit;
            }
        }
    }
}

// Show error message on login page
add_action('wp_footer', 'bntm_show_session_error_message');
function bntm_show_session_error_message() {
    $error_message = '';
    
    if (isset($_GET['session_error']) && $_GET['session_error'] == '1') {
        $error_message = 'Your account is already logged in from another location. Please log in again.';
    } elseif (isset($_GET['limit_error']) && $_GET['limit_error'] == '1') {
        $error_message = 'System limits have been reached. Please contact your administrator to expand capacity.';
    }
    
    if (!empty($error_message)) {
        ?>
        <script>
        document.addEventListener('DOMContentLoaded', function() {
            const msgDiv = document.getElementById('login-message');
            if (msgDiv) {
                msgDiv.innerHTML = '<div class="bntm-notice bntm-notice-error"><?php echo esc_js($error_message); ?></div>';
            }
        });
        </script>
        <?php
    }
}

// Clear session token on logout
add_action('wp_logout', 'bntm_clear_session_token');
function bntm_clear_session_token() {
    $user_id = get_current_user_id();
    if ($user_id) {
        delete_user_meta($user_id, '_bntm_session_token');
        delete_user_meta($user_id, '_bntm_last_limit_check');
        setcookie('bntm_session_token', '', time() - 3600, COOKIEPATH, COOKIE_DOMAIN);
    }
}

// AJAX Logout Handler
add_action('wp_ajax_bntm_logout', 'bntm_ajax_logout');
function bntm_ajax_logout() {
    wp_logout();
    $login_page = get_page_by_title('Login');
    $redirect = $login_page ? get_permalink($login_page->ID) : wp_login_url();
    wp_send_json_success(['redirect' => $redirect]);
}

/* ============================================================================
   SECURITY & ACCESS RESTRICTIONS
   ============================================================================ */

/**
 * Allow only admins to access /wp-admin/.
 * Non-admins are redirected to homepage.
 */

// 1️⃣ Restrict access to /wp-admin/ for non-admins
add_action('init', function () {
    if (is_admin() && !wp_doing_ajax() && !current_user_can('manage_options')) {
        wp_redirect(home_url());
        exit;
    }
});

// 2️⃣ Hide wp-login.php from public access
add_action('init', function () {
    $request_uri = basename(parse_url($_SERVER['REQUEST_URI'], PHP_URL_PATH));

    // If someone tries to access wp-login.php directly
    if ($request_uri === 'wp-login.php' && !current_user_can('manage_options')) {
        wp_redirect(home_url());
        exit;
    }
});

// 3️⃣ Disable default login URL for non-admins
add_action('login_init', function () {
    if (!current_user_can('manage_options')) {
        wp_redirect(home_url());
        exit;
    }
});

// 4️⃣ Improve security headers
add_action('send_headers', function () {
    if (!current_user_can('manage_options')) {
        header('X-Robots-Tag: noindex, nofollow', true);
    }
});

/* ============================================================================
   WORDPRESS LOGIN PAGE CUSTOMIZATION
   ============================================================================ */

// Change the login logo
add_action('login_enqueue_scripts', 'bntm_custom_login_logo');
function bntm_custom_login_logo() {
    $logo = bntm_get_site_logo();
    
    if (!$logo) {
        return;
    }
    ?>
    <style type="text/css">
        #login h1 a, .login h1 a {
            background-image: url('<?php echo esc_url($logo); ?>');
            height: 100px;
            width: 320px;
            background-size: contain;
            background-repeat: no-repeat;
            background-position: center;
            padding-bottom: 0;
        }
    </style>
    <?php
}

// Change the logo URL (points to home instead of WordPress.org)
add_filter('login_headerurl', 'bntm_custom_login_logo_url');
function bntm_custom_login_logo_url() {
    return home_url();
}

// Change the logo title attribute
add_filter('login_headertext', 'bntm_custom_login_logo_title');
function bntm_custom_login_logo_title() {
    return get_bloginfo('name');
}

// Apply custom styling with --bntm-primary color
add_action('login_enqueue_scripts', 'bntm_custom_login_styles');
function bntm_custom_login_styles() {
    ?>
    <style type="text/css">
        /* Get the primary color from your theme */
        :root {
            --bntm-primary: <?php echo bntm_get_setting('color_primary', '#3b82f6'); ?>;
        }
        
        /* Login form styling */
        .login form {
            border-radius: 12px;
            box-shadow: 0 20px 60px rgba(0,0,0,0.3);
        }
        
        /* Input fields */
        .login input[type="text"],
        .login input[type="password"] {
            border-radius: 6px;
            border: 1px solid #ddd;
            padding: 10px;
        }
        
        .login input[type="text"]:focus,
        .login input[type="password"]:focus {
            border-color: var(--bntm-primary);
            box-shadow: 0 0 0 1px var(--bntm-primary);
        }
        
        /* Submit button */
        .button {
            background: var(--bntm-primary)!important;
            border-color: var(--bntm-primary)!important;
          	color:white!important;
            box-shadow: none;
            text-shadow: none;
            border-radius: 6px;
            padding: 8px 16px;
            height: auto;
            line-height: 1.5;
        }
        
        .button:hover,
        .button:focus {
            background: var(--bntm-primary)!important;
            border-color: var(--bntm-primary)!important;
            opacity: 0.9;
        }
        
        /* Checkbox */
        .login .forgetmenot input[type="checkbox"]:checked::before {
            color: var(--bntm-primary);
        }
        
        /* Links */
        .login #nav a,
        .login #backtoblog a {
            color: white;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        
        .login #nav a:hover,
        .login #backtoblog a:hover {
            color: white;
            opacity: 0.8;
        }
        
        /* Message boxes */
        .login .message,
        .login .success {
            border-left-color: var(--bntm-primary);
        }
        
        /* Privacy policy link */
        .login .privacy-policy-page-link {
            color: white;
            text-shadow: 0 1px 2px rgba(0,0,0,0.3);
        }
        
        .login .privacy-policy-page-link:hover {
            opacity: 0.8;
        }
    </style>
    <?php
}

/* ============================================================================
   PERFORMANCE MONITORING & DEBUGGING (Optional)
   ============================================================================ */

/**
 * Log limit check performance (for debugging)
 * Remove in production or use only when needed
 */
function bntm_log_limit_check_performance($start_time, $context = '') {
    if (defined('WP_DEBUG') && WP_DEBUG) {
        $execution_time = (microtime(true) - $start_time) * 1000;
        error_log(sprintf(
            'BNTM Limit Check [%s]: %.2fms',
            $context,
            $execution_time
        ));
    }
}

/**
 * Get limit check statistics (for admin dashboard)
 */
function bntm_get_limit_statistics() {
    $limit_status = bntm_check_all_limits();
    
    $stats = [
        'total_limits' => 0,
        'limits_set' => 0,
        'limits_reached' => count($limit_status['limits_reached']),
        'limits_warning' => count($limit_status['limits_warning']),
        'cache_duration' => bntm_get_adaptive_cache_duration(),
        'last_checked' => $limit_status['last_checked'] ?? 'Never'
    ];
    
    // Count total limits
    $user_limit = get_option('bntm_user_limit', 0);
    if ($user_limit > 0) {
        $stats['total_limits']++;
        $stats['limits_set']++;
    }
    
    $table_limits = get_option('bntm_table_limits', []);
    $stats['total_limits'] += count($table_limits);
    
    foreach ($table_limits as $limit) {
        if ($limit > 0) {
            $stats['limits_set']++;
        }
    }
    
    return $stats;
}

/* ============================================================================
   CRON JOBS FOR BACKGROUND LIMIT CHECKING (Optional)
   ============================================================================ */

/**
 * Optional: Check limits in background via WP Cron
 * This reduces on-demand checks during user sessions
 */
add_action('bntm_check_limits_cron', 'bntm_background_limit_check');
function bntm_background_limit_check() {
    // Force refresh cache
    bntm_check_all_limits(true);
}

// Schedule cron job (runs every 5 minutes)
/*if (!wp_next_scheduled('bntm_check_limits_cron')) {
    wp_schedule_event(time(), 'bntm_five_minutes', 'bntm_check_limits_cron');
}

// Add custom cron schedule
add_filter('cron_schedules', 'bntm_custom_cron_schedules');
function bntm_custom_cron_schedules($schedules) {
    if (!isset($schedules['bntm_five_minutes'])) {
        $schedules['bntm_five_minutes'] = [
            'interval' => 300,
            'display' => __('Every 5 Minutes')
        ];
    }
    return $schedules;
}
*/
// Clear cron on plugin deactivation (if using plugin structure)
register_deactivation_hook(__FILE__, 'bntm_clear_scheduled_cron');
function bntm_clear_scheduled_cron() {
    $timestamp = wp_next_scheduled('bntm_check_limits_cron');
    if ($timestamp) {
        wp_unschedule_event($timestamp, 'bntm_check_limits_cron');
    }
}

?>
