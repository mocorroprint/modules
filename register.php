<?php
if (!defined('ABSPATH')) {
    exit;
}

function bntm_get_registration_settings() {
    $defaults = [
        'enabled' => 0,
        'require_verification' => 0,
        'fields' => ['first_name', 'last_name', 'email', 'username', 'phone', 'dob'],
    ];

    $settings = get_option('bntm_registration_settings', []);
    if (!is_array($settings)) {
        $settings = [];
    }

    $settings = wp_parse_args($settings, $defaults);
    if (!is_array($settings['fields'])) {
        $settings['fields'] = $defaults['fields'];
    }

    return $settings;
}

function bntm_send_verification_email($user_id) {
    $user = get_userdata($user_id);
    if (!$user) {
        return false;
    }

    $token = wp_generate_password(32, false, false);
    update_user_meta($user_id, 'bntm_email_verify_token', $token);

    $link = add_query_arg([
        'bntm_verify_email' => 1,
        'uid' => $user_id,
        'token' => $token,
    ], home_url('/'));

    $subject = 'Verify your email';
    $message = "Hello {$user->display_name},\n\nPlease verify your account by opening this link:\n{$link}\n\nIf you did not request this, you can ignore this email.";

    return wp_mail($user->user_email, $subject, $message);
}

add_action('init', 'bntm_handle_email_verification');
function bntm_handle_email_verification() {
    if (empty($_GET['bntm_verify_email']) || empty($_GET['uid']) || empty($_GET['token'])) {
        return;
    }

    $user_id = absint($_GET['uid']);
    $token = sanitize_text_field(wp_unslash($_GET['token']));

    $saved_token = get_user_meta($user_id, 'bntm_email_verify_token', true);
    $redirect = home_url('/login');

    if ($saved_token && hash_equals($saved_token, $token)) {
        update_user_meta($user_id, 'bntm_email_verified', 1);
        delete_user_meta($user_id, 'bntm_email_verify_token');
        $redirect = add_query_arg('verified', '1', $redirect);
    } else {
        $redirect = add_query_arg('verified', '0', $redirect);
    }

    wp_safe_redirect($redirect);
    exit;
}

function bntm_shortcode_register() {
    if (is_user_logged_in()) {
        $dashboard = get_page_by_title('Dashboard');
        $url = $dashboard ? get_permalink($dashboard->ID) : home_url();
        wp_redirect($url);
        exit;
    }

    $settings = bntm_get_registration_settings();
    if (empty($settings['enabled'])) {
        return '<div class="bntm-notice bntm-notice-error">Registration is currently disabled.</div>';
    }

    $logo = bntm_get_site_logo();
    $title = bntm_get_site_title();
    $nonce = wp_create_nonce('bntm_register');

    ob_start();
    ?>
    <div class="br-wrap">
        <div class="br-panel">
            <div class="br-circle2"></div>
            <div class="br-brand">
                <?php if ($logo) : ?>
                    <div class="br-logo-frame"><img src="<?php echo esc_url($logo); ?>" alt="<?php echo esc_attr($title); ?>"></div>
                <?php endif; ?>
                <div class="br-brand-name"><?php echo esc_html($title); ?></div>
                <p class="br-brand-sub">Create your account to start using your workspace.</p>
            </div>
        </div>
        <div class="br-form-side">
            <div class="br-card">
                <h1 class="br-card-heading">Create Account</h1>
                <p class="br-card-sub">Fill in your details to register</p>
                <form id="bntm-register-form" novalidate>
                    <div class="br-field" <?php echo in_array('first_name', $settings['fields'], true) ? '' : 'style="display:none"'; ?>><label>First Name</label><input type="text" name="first_name" <?php echo in_array('first_name', $settings['fields'], true) ? 'required' : ''; ?>></div>
                    <div class="br-field" <?php echo in_array('last_name', $settings['fields'], true) ? '' : 'style="display:none"'; ?>><label>Last Name</label><input type="text" name="last_name" <?php echo in_array('last_name', $settings['fields'], true) ? 'required' : ''; ?>></div>
                    <div class="br-field" <?php echo in_array('email', $settings['fields'], true) ? '' : 'style="display:none"'; ?>><label>Email</label><input type="email" name="email" <?php echo in_array('email', $settings['fields'], true) ? 'required' : ''; ?>></div>
                    <div class="br-field" <?php echo in_array('username', $settings['fields'], true) ? '' : 'style="display:none"'; ?>><label>Username</label><input type="text" name="username" <?php echo in_array('username', $settings['fields'], true) ? 'required' : ''; ?>></div>
                    <div class="br-field" <?php echo in_array('phone', $settings['fields'], true) ? '' : 'style="display:none"'; ?>><label>Phone Number</label><input type="text" name="phone" <?php echo in_array('phone', $settings['fields'], true) ? 'required' : ''; ?>></div>
                    <div class="br-field" <?php echo in_array('dob', $settings['fields'], true) ? '' : 'style="display:none"'; ?>><label>Date of Birth</label><input type="date" name="dob" <?php echo in_array('dob', $settings['fields'], true) ? 'required' : ''; ?>></div>
                    <div class="br-field" <?php echo in_array('address', $settings['fields'], true) ? '' : 'style="display:none"'; ?>><label>Address</label><input type="text" name="address" <?php echo in_array('address', $settings['fields'], true) ? 'required' : ''; ?>></div>
                    <div class="br-field"><label>Password</label><input type="password" name="password" required minlength="8"></div>
                    <div class="br-field"><label>Confirm Password</label><input type="password" name="confirm_password" required minlength="8"></div>
                    <label class="br-terms"><input type="checkbox" name="accept_terms" value="1" required> I agree to the Terms and Conditions</label>
                    <button type="submit" class="br-submit" id="brSubmitBtn">Register</button>
                    <div class="br-message" id="register-message"></div>
                </form>
            </div>
        </div>
    </div>
    <style>
    .br-wrap{display:flex;min-height:100vh;background:#f0f4f8;font-family:'Plus Jakarta Sans',-apple-system,BlinkMacSystemFont,'Segoe UI',sans-serif}.br-panel{flex:1 1 45%;max-width:500px;background:var(--bntm-primary);position:relative;padding:52px 56px}.br-panel::before{content:'';position:absolute;inset:0;background-image:radial-gradient(circle,rgba(255,255,255,.13) 1px,transparent 1px);background-size:28px 28px}.br-circle2{position:absolute;bottom:-90px;left:-90px;width:320px;height:320px;border-radius:50%;background:rgba(255,255,255,.05)}.br-logo-frame{display:inline-flex;align-items:center;justify-content:center;background:#fff;border-radius:16px;padding:12px 18px;margin-bottom:32px}.br-logo-frame img{height:40px;width:auto;max-width:150px}.br-brand-name{font-size:30px;font-weight:800;color:#fff}.br-brand-sub{font-size:15px;color:rgba(255,255,255,.62);max-width:280px}.br-form-side{flex:1 1 55%;display:flex;align-items:center;justify-content:center;padding:40px 24px}.br-card{background:#fff;border-radius:20px;padding:44px 44px 40px;width:100%;max-width:460px;box-shadow:0 0 0 1px rgba(0,0,0,.04),0 4px 6px rgba(0,0,0,.04),0 20px 40px rgba(0,0,0,.08)}.br-card-heading{font-size:24px;font-weight:800;color:#0f172a}.br-card-sub{font-size:14px;color:#94a3b8;margin-bottom:24px}.br-field{margin-bottom:14px}.br-field label{display:block;font-size:11px;font-weight:700;color:#475569;text-transform:uppercase;margin-bottom:6px}.br-field input{display:block;width:100%;padding:12px 14px;border:1.5px solid #e2e8f0;border-radius:10px;font-size:14px}.br-terms{display:flex;align-items:center;gap:8px;font-size:13px;color:#64748b;margin:8px 0 14px;cursor:pointer}.br-submit{width:100%;padding:13px 20px;background:var(--bntm-primary);color:#fff;border:none;border-radius:10px;font-size:15px;font-weight:700;cursor:pointer}.br-message{margin-top:12px}
    @media(max-width:768px){.br-wrap{flex-direction:column;background:var(--bntm-primary)}.br-panel{display:none}.br-form-side{padding:0;align-items:flex-end}.br-card{max-width:100%;border-radius:24px 24px 0 0;min-height:100vh;padding:30px 24px}}
    .bntm-topbar,.bntm-header,.bntm-sidebar,.bntm-sidebar-overlay,.bntm-layout{display:none!important}.bntm-content{background:transparent!important;box-shadow:none!important;padding:0!important}.bntm-container{padding:0!important;max-width:100%!important}
    </style>
    <script>
    (function(){
        var ajaxurl = '<?php echo esc_js(admin_url('admin-ajax.php')); ?>';
        document.getElementById('bntm-register-form').addEventListener('submit', function(e){
            e.preventDefault();
            var form=this; var msg=document.getElementById('register-message'); var btn=document.getElementById('brSubmitBtn');
            btn.disabled=true; btn.textContent='Registering...';
            var data=new FormData(form);
            data.append('action','bntm_register_user');
            data.append('_ajax_nonce','<?php echo esc_js($nonce); ?>');
            fetch(ajaxurl,{method:'POST',body:data}).then(function(r){return r.json();}).then(function(json){
                msg.innerHTML='<div class="bntm-notice bntm-notice-'+(json.success?'success':'error')+'">'+(json.data.message||json.data)+'</div>';
                if(json.success && json.data.redirect){ setTimeout(function(){ location.href=json.data.redirect; }, 900); }
                btn.disabled=false; btn.textContent='Register';
            }).catch(function(){ msg.innerHTML='<div class="bntm-notice bntm-notice-error">Registration failed.</div>'; btn.disabled=false; btn.textContent='Register'; });
        });
    })();
    </script>
    <?php
    return ob_get_clean();
}

add_action('wp_ajax_nopriv_bntm_register_user', 'bntm_ajax_register_user');
add_action('wp_ajax_bntm_register_user', 'bntm_ajax_register_user');
function bntm_ajax_register_user() {
    check_ajax_referer('bntm_register');

    $settings = bntm_get_registration_settings();
    if (empty($settings['enabled'])) {
        wp_send_json_error(['message' => 'Registration is disabled.']);
    }

    $fields = $settings['fields'];
    $first_name = sanitize_text_field($_POST['first_name'] ?? '');
    $last_name = sanitize_text_field($_POST['last_name'] ?? '');
    $email = sanitize_email($_POST['email'] ?? '');
    $username = sanitize_user($_POST['username'] ?? '');
    $phone = sanitize_text_field($_POST['phone'] ?? '');
    $dob = sanitize_text_field($_POST['dob'] ?? '');
    $address = sanitize_text_field($_POST['address'] ?? '');
    $password = $_POST['password'] ?? '';
    $confirm_password = $_POST['confirm_password'] ?? '';
    $accept_terms = isset($_POST['accept_terms']) && $_POST['accept_terms'] === '1';

    if (in_array('username', $fields, true) && empty($username)) {
        wp_send_json_error(['message' => 'Username is required.']);
    }
    if (in_array('email', $fields, true) && empty($email)) {
        wp_send_json_error(['message' => 'Email is required.']);
    }
    if (in_array('phone', $fields, true) && empty($phone)) {
        wp_send_json_error(['message' => 'Phone number is required.']);
    }
    if (in_array('dob', $fields, true) && empty($dob)) {
        wp_send_json_error(['message' => 'Date of birth is required.']);
    }
    if (in_array('address', $fields, true) && empty($address)) {
        wp_send_json_error(['message' => 'Address is required.']);
    }
    if (strlen($password) < 8) {
        wp_send_json_error(['message' => 'Password must be at least 8 characters.']);
    }
    if ($password !== $confirm_password) {
        wp_send_json_error(['message' => 'Passwords do not match.']);
    }
    if (!$accept_terms) {
        wp_send_json_error(['message' => 'You must accept the terms and conditions.']);
    }

    if (empty($username)) {
        $username = sanitize_user(current(explode('@', $email)) ?: 'user' . wp_generate_password(5, false, false));
    }

    if (empty($email)) {
        $email = $username . '+' . wp_generate_password(5, false, false) . '@example.local';
    }

    if (username_exists($username)) {
        wp_send_json_error(['message' => 'Username already exists.']);
    }
    if (email_exists($email)) {
        wp_send_json_error(['message' => 'Email already exists.']);
    }

    $user_id = wp_create_user($username, $password, $email);
    if (is_wp_error($user_id)) {
        wp_send_json_error(['message' => $user_id->get_error_message()]);
    }

    wp_update_user([
        'ID' => $user_id,
        'first_name' => $first_name,
        'last_name' => $last_name,
        'role' => 'subscriber',
    ]);

    update_user_meta($user_id, 'bntm_role', 'staff');
    update_user_meta($user_id, 'bntm_account_status', 'active');
    if (!empty($phone)) {
        update_user_meta($user_id, 'bntm_phone', $phone);
    }
    if (!empty($dob)) {
        update_user_meta($user_id, 'bntm_dob', $dob);
    }
    if (!empty($address)) {
        update_user_meta($user_id, 'bntm_address', $address);
    }

    if (!empty($settings['require_verification'])) {
        update_user_meta($user_id, 'bntm_email_verified', 0);
        bntm_send_verification_email($user_id);
        wp_send_json_success(['message' => 'Registration successful. Please verify your email before login.', 'redirect' => home_url('/login')]);
    }

    update_user_meta($user_id, 'bntm_email_verified', 1);

    wp_send_json_success(['message' => 'Registration successful. You can now log in.', 'redirect' => home_url('/login')]);
}
