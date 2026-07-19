<?php
function deepseek_seo_generator_menu() {
    add_submenu_page(
        'edit.php',                 // Parent slug (posts)
        'SEO Generator',            // Page title
        'SEO Generator',            // Menu title
        'edit_posts',               // Capability
        'deepseek-seo-generator',   // Menu slug
        'deepseek_seo_generator_page' // Function to display the page
    );
    
    // Add settings page under Settings menu
    add_submenu_page(
        'options-general.php',      // Parent slug (settings)
        'SEO Generator Settings',   // Page title
        'SEO Generator Settings',   // Menu title
        'manage_options',           // Capability
        'deepseek-seo-settings',    // Menu slug
        'deepseek_seo_settings_page' // Function to display the settings page
    );
}
add_action('admin_menu', 'deepseek_seo_generator_menu');

// Register settings
function deepseek_seo_register_settings() {
    register_setting(
        'deepseek_seo_settings',    // Option group
        'deepseek_api_key',         // Option name
        array(
            'sanitize_callback' => 'sanitize_text_field',
            'default' => ''
        )
    );
}
add_action('admin_init', 'deepseek_seo_register_settings');

// Settings page content
function deepseek_seo_settings_page() {
    // Get saved API key
    $api_key = get_option('deepseek_api_key', '');
    ?>
    <div class="wrap">
        <h1>SEO Generator Settings</h1>
        
        <form method="post" action="options.php">
            <?php settings_fields('deepseek_seo_settings'); ?>
            <?php do_settings_sections('deepseek_seo_settings'); ?>
            
            <table class="form-table">
                <tr valign="top">
                    <th scope="row">DeepSeek API Key</th>
                    <td>
                        <input type="text" name="deepseek_api_key" value="<?php echo esc_attr($api_key); ?>" 
                               class="regular-text" placeholder="Enter your DeepSeek API key">
                        <p class="description">
                            Your API key is required to generate content with DeepSeek. 
                            Get your API key from <a href="https://deepseek.com" target="_blank">DeepSeek</a>.
                        </p>
                    </td>
                </tr>
            </table>
            
            <?php submit_button('Save Settings'); ?>
        </form>
    </div>
    <?php
}

// Register script and style
function deepseek_seo_generator_scripts() {
    wp_register_style('deepseek-seo-generator-css', false);
    wp_enqueue_style('deepseek-seo-generator-css');
    wp_add_inline_style('deepseek-seo-generator-css', '
        .deepseek-container {
            max-width: 1200px;
            margin: 20px auto;
            padding: 20px;
            background: #fff;
            border-radius: 5px;
            box-shadow: 0 0 10px rgba(0,0,0,0.1);
        }
        .deepseek-form {
            margin-bottom: 20px;
            display: grid;
            grid-template-columns: 1fr 1fr;
            grid-gap: 15px;
        }
        .deepseek-form .full-width {
            grid-column: 1 / -1;
        }
        .deepseek-form label {
            display: block;
            margin-bottom: 5px;
            font-weight: bold;
        }
        .deepseek-form input[type="text"], 
        .deepseek-form textarea {
            width: 100%;
            padding: 10px;
            border: 1px solid #ddd;
            border-radius: 4px;
        }
        .deepseek-form textarea {
            min-height: 120px;
        }
        .deepseek-buttons {
            display: flex;
            gap: 10px;
            margin-top: 15px;
        }
        .deepseek-button {
            padding: 10px 15px;
            background-color: #2271b1;
            color: white;
            border: none;
            border-radius: 4px;
            cursor: pointer;
            font-size: 14px;
        }
        .deepseek-button:hover {
            background-color: #135e96;
        }
        .deepseek-button.secondary {
            background-color: #f0f0f1;
            color: #2c3338;
            border: 1px solid #2c3338;
        }
        .deepseek-button.secondary:hover {
            background-color: #dcdcde;
        }
        .deepseek-button.disabled {
            background-color: #ddd;
            cursor: not-allowed;
        }
        .deepseek-loading {
            display: none;
            margin: 20px 0;
            text-align: center;
        }
        .deepseek-loading .spinner {
            display: inline-block;
            width: 20px;
            height: 20px;
            border: 3px solid rgba(0, 0, 0, 0.3);
            border-radius: 50%;
            border-top-color: #2271b1;
            animation: spin 1s ease-in-out infinite;
            margin-right: 10px;
        }
        @keyframes spin {
            to { transform: rotate(360deg); }
        }
        .deepseek-result {
            margin-top: 20px;
            padding: 20px;
            border: 1px solid #ddd;
            border-radius: 4px;
            display: none;
        }
        .deepseek-tabs {
            display: flex;
            border-bottom: 1px solid #ccc;
            margin-bottom: 15px;
        }
        .deepseek-tab {
            padding: 10px 15px;
            cursor: pointer;
            margin-right: 5px;
            border: 1px solid transparent;
            border-bottom: none;
        }
        .deepseek-tab.active {
            background-color: #fff;
            border-color: #ccc;
            border-bottom-color: white;
            margin-bottom: -1px;
        }
        .deepseek-tab-content {
            display: none;
        }
        .deepseek-tab-content.active {
            display: block;
        }
        .deepseek-meta {
            margin-top: 20px;
            padding: 15px;
            background-color: #f9f9f9;
            border-radius: 4px;
        }
        .deepseek-meta h3 {
            margin-top: 0;
        }
        .deepseek-meta p {
            margin: 5px 0;
        }
        .deepseek-meta label {
            font-weight: bold;
            display: inline-block;
            min-width: 120px;
        }
        .api-settings-notice {
            background-color: #f0f6fc;
            border-left: 4px solid #2271b1;
            padding: 12px;
            margin-bottom: 20px;
        }
        .category-checkboxes {
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(200px, 1fr));
            gap: 10px;
            max-height: 200px;
            overflow-y: auto;
            border: 1px solid #ddd;
            padding: 15px;
            border-radius: 4px;
            background-color: #fafafa;
        }
        .category-checkbox {
            display: flex;
            align-items: center;
            font-weight: normal !important;
            margin-bottom: 0 !important;
            cursor: pointer;
        }
        .category-checkbox input[type="checkbox"] {
            margin-right: 8px;
            width: auto;
        }
    ');

    // Register and enqueue the script
    wp_register_script('deepseek-seo-generator-js', false);
    wp_enqueue_script('deepseek-seo-generator-js');
    wp_add_inline_script('deepseek-seo-generator-js', '
        jQuery(document).ready(function($) {
            // Tab functionality
            $(".deepseek-tab").click(function() {
                $(".deepseek-tab").removeClass("active");
                $(this).addClass("active");
                $(".deepseek-tab-content").removeClass("active");
                $("#" + $(this).data("tab")).addClass("active");
            });

            // Handle generate button click - no form submission
            $("#deepseek-generate-btn").click(function() {
                // Disable button to prevent multiple clicks
                if ($(this).hasClass("disabled")) {
                    return;
                }
                
                // Get form values
                var keywordsText = $("#keywords").val();
                var keywords = keywordsText.split("\\n").filter(function(keyword) {
                    return keyword.trim() !== "";
                });
                
                var website = $("#website").val();
                
                // Validate inputs
                if (keywords.length === 0) {
                    alert("Please enter at least one keyword");
                    return;
                }
                
                if (!website) {
                    alert("Please enter your website URL");
                    return;
                }
                
                // Check if API key is set
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "check_deepseek_api_key",
                        nonce: $("#deepseek_nonce").val()
                    },
                    success: function(response) {
                        if (response.success) {
                            // API key exists, proceed with generation
                            var prompt = constructPrompt(keywords, website);
                            generateContent(prompt);
                        } else {
                            alert("API key is not set. Please go to Settings > SEO Generator Settings to enter your API key.");
                        }
                    },
                    error: function() {
                        alert("Error checking API key. Please try again.");
                    }
                });
            });
            
            function generateContent(prompt) {
                // Show loading animation
                $("#deepseek-generate-btn").addClass("disabled").prop("disabled", true);
                $(".deepseek-loading").show();
                $(".deepseek-result").hide();
                
                // Send AJAX request
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "process_deepseek_request",
                        nonce: $("#deepseek_nonce").val(),
                        message: prompt
                    },
                    success: function(response) {
                        if (response.success) {
                            processResponse(response.data);
                            $(".deepseek-result").show();
                            $(".deepseek-tab[data-tab=\'content-tab\']").click();
                        } else {
                            alert("Error: " + response.data);
                        }
                    },
                    error: function() {
                        alert("An error occurred while processing your request. Please try again.");
                    },
                    complete: function() {
                        $("#deepseek-generate-btn").removeClass("disabled").prop("disabled", false);
                        $(".deepseek-loading").hide();
                    }
                });
            }
            
           // Create a new post with the generated content
            $("#publish-post").click(function() {
                var title = $("#generated-title").text();
                var content = $("#content-tab").html();
                var metaDescription = $("#meta-description").text();
                var excerpt = $("#excerpt").text();
                var keywordsText = $("#keywords").val();
                var keywords = keywordsText.split("\\n").filter(function(keyword) {
                    return keyword.trim() !== "";
                }).join(",");
                // Get selected picture URL (the one that was randomly chosen)
                var pictureUrls = $("#picture_url").val().trim();
                var selectedPictureUrl = "";
                if (pictureUrls) {
                    var urlLines = pictureUrls.split("\\n").filter(function(url) {
                        return url.trim() !== "";
                    });
                    if (urlLines.length > 0) {
                        var randomIndex = Math.floor(Math.random() * urlLines.length);
                        selectedPictureUrl = urlLines[randomIndex].trim();
                    }
                }
                
                // Get selected categories
                var selectedCategories = [];
                $(".category-checkbox input:checked").each(function() {
                    selectedCategories.push($(this).val());
                });
                // Create a new post
                $.ajax({
                    url: ajaxurl,
                    type: "POST",
                    data: {
                        action: "create_post_from_deepseek",
                        nonce: $("#deepseek_nonce").val(),
                        title: title,
                        content: content,
                        meta_description: metaDescription,
                        excerpt: excerpt,
                        keywords: keywords,
                        categories: selectedCategories,
                        picture_url: selectedPictureUrl
                    },
                    success: function(response) {
                        if (response.success) {
                            alert("Post created successfully!");
                            // Open edit page in a new tab
                            window.open(response.data.post_link, "_blank");
                        } else {
                            alert("Error creating post: " + response.data);
                        }
                    },
                    error: function() {
                        alert("An error occurred while creating the post. Please try again.");
                    }
                });
            });
            
            // Copy HTML to clipboard
            $("#copy-html").click(function() {
                copyToClipboard($("#html-tab").text());
                alert("HTML copied to clipboard!");
            });
            
        function constructPrompt(keywords, website) {
            var keyword1 = keywords[0] || "";
            var keyword2 = keywords[1] || "";
            var keyword3 = keywords[2] || "";
            
            // Get additional information
            var additionalInfo = $("#additional_info").val().trim();
            var additionalInfoText = "";
            if (additionalInfo) {
                additionalInfoText = "\\n\\nAdditional Requirements and Context:\\n" + additionalInfo;
            }
            
             // Get picture URLs and select random one
            var pictureUrls = $("#picture_url").val().trim();
            var pictureInstruction = "";
            if (pictureUrls) {
                var urlLines = pictureUrls.split("\\n").filter(function(url) {
                    return url.trim() !== "";
                });
                
                if (urlLines.length > 0) {
                    // Select random URL from the list
                    var randomIndex = Math.floor(Math.random() * urlLines.length);
                    var selectedPictureUrl = urlLines[randomIndex].trim();
                    pictureInstruction = `\\n\\nINCLUDE THIS EXACT IMAGE URL(${selectedPictureUrl}): Add this image AFTER the introduction paragraph: <img src="${selectedPictureUrl}" alt="${keyword1}" style="max-height: 400px; width: auto; margin: 20px 0; border-radius: 8px;">`;
                }
            }
            
            
            // Get the latest 10 posts for potential backlinks
            var recentPosts = [];
            $.ajax({
                url: ajaxurl,
                type: "POST",
                async: false,
                data: {
                    action: "get_recent_posts",
                    nonce: $("#deepseek_nonce").val()
                },
                success: function(response) {
                    if (response.success) {
                        recentPosts = response.data;
                    }
                }
            });
            
               // Format the posts data for the prompt
            var postsData = "";
            if (recentPosts.length > 0) {
                postsData = "Here are recent blog posts from the website that you can use as backlinks if they\\\'re relevant to this new content (only include backlinks that would be truly helpful to readers):\\n";
                recentPosts.forEach(function(post) {
                    postsData += `- Title: \\"${post.title}\\" | URL: ${website}${post.slug}\\n`;
                });
                postsData += "\\nOnly link to these posts if they are relevant to the topic. If none are relevant, don\\\'t force any backlinks to these posts.";
            }
            
            
            return `Write an extremely comprehensive, detailed, and engaging article that will keep readers engaged for 5-10 minutes (aim for 4000-6000 words). This article must be 100% human-like with natural flow, personal insights, and authentic storytelling elements.
       
        
ARTICLE SPECIFICATIONS:
- Target Reading Time: 5-10 minutes 
- Primary Keyword: ${keyword1}
- Secondary Keywords: ${keyword2 || ""}, ${keyword3 || ""}
- Website: ${website}
- Format: HTML only (start with <!-- Article and end with -->)

ENGAGEMENT REQUIREMENTS:
1. Have a Keyword based Title. Make it as simple as possible straightforward for SEO Optimized where dont add \":\" and the statement after it . Dont use \" Ultimate  \" and other words that is used by AI.
2. Start with a compelling hook(SIMPLE a statement) - a surprising statistic, thought-provoking question, or relatable scenario
3. Include personal anecdotes, case studies, or real-world examples throughout
4. Use storytelling techniques to maintain reader interest
5. Add actionable tips, step-by-step guides, and practical advice
6. Include relevant statistics, data points, and expert quotes
7. Create sections that build upon each other logically
8. Use conversational tone with direct reader address ("you", "your")
9. Include thought-provoking questions that encourage reflection

STRUCTURE (each section 150-300 words):
- Hook introduction (150 words minimum words)${pictureInstruction}
- 8-10 main sections (150 words minimum each)
- FAQ section (6-8 questions, 150-200 words per answer)
- Conclusion with CTAs (150 words minimum)
- Include bullet points, numbered lists, and examples within the 200-300 word paragraphs
- Add call-to-action boxes throughout the content


WRITING REQUIREMENTS:
- Every paragraph: 150 words minimum exactly
- Conversational, engaging tone
- Include statistics, examples, actionable tips
- Natural keyword integration (8-10 times)
- H2/H3 headings with keyword variations

 WRITING STYLE:
- Use active voice and varied sentence structures
- Include transitional phrases and natural flow
- Add personal touches and authentic voice
- Use contractions and conversational language
- Include industry insights and expert perspectives
- Make complex topics accessible and engaging
- Add humor where appropriate and natural


ENGAGEMENT ELEMENTS TO INCLUDE IN RANDOM ORDER. TYPE OF ELEMENTS CAN BE DUPLICATED AS LONG AS IT IS 8-10 SECTIONS. I PREFER LONG PARAGRAPH SECTIONS 150 words minimum:
- "Pro Tips" or "Expert Insights" boxes
- Step-by-step tutorials or guides
- Common mistakes to avoid
- Success stories or case studies
- Comparison tables or lists
- Action items and checklists
- "Did You Know?" interesting facts

SEO OPTIMIZATION:
- H1 title with primary keyword
- Meta title (60 chars max)
- Meta description (155 chars max)
- Excerpt (150-200 words)
- Internal linking opportunities

 CALL-TO-ACTIONS:
- Include 2-3 natural CTAs linking to ${website}/register
- Add phone number CTAs where relevant
- Encourage reader engagement and comments
- Suggest next steps or related actions

${postsData}


${additionalInfoText}

CRITICAL:
- Generate all the contents on the Table of Contents
- ONLY GENERATE CONTENTS WHERE IT CAN FIT 4000 tokens maximum for API generation. (Dont exceed so it wont get cut)
- Each section body (under each header) must contain atleast 150 words
- Do not summarize or skip ANY section in the outline
- Do NOT include conclusion header — just CTA wrap-up
- NO external CSS. Style all CTAs inline (style="")
- Ensure all internal links are contextually relevant
- Avoid repetition. Each section should tackle a unique angle or aspect
- Always focus on clarity, originality, and depth
- Assume the reader is smart but time-sensitive — make every word count

Goal: Create the most valuable, comprehensive ${keyword1} guide that readers bookmark and share.`;
}
            
            // Function to process the AI response
            function processResponse(response) {
                // Extract parts of the response
                var htmlMatch = response.match(/<html>(.*?)<\/html>/s);
                var htmlContent = htmlMatch ? htmlMatch[1] : response;
                
                // Extract meta information
                var metaTitleMatch = response.match(/Meta title: (.*?)$/m);
                var metaDescriptionMatch = response.match(/meta-description: (.*?)$/m);
                var excerptMatch = response.match(/excerpt: (.*?)$/m);
                var titleMatch = response.match(/<h1>(.*?)<\/h1>/);
                
                // Set content
                $("#content-tab").html(response);
                $("#html-tab").text(htmlContent);
                
                // Set meta information
                if (titleMatch) {
                    $("#generated-title").text(titleMatch[1]);
                }
                
                if (metaTitleMatch) {
                    $("#meta-title").text(metaTitleMatch[1]);
                }
                
                if (metaDescriptionMatch) {
                    $("#meta-description").text(metaDescriptionMatch[1]);
                }
                
                if (excerptMatch) {
                    $("#excerpt").text(excerptMatch[1]);
                }
            }
            
            // Function to copy text to clipboard
            function copyToClipboard(text) {
                var textarea = document.createElement("textarea");
                textarea.value = text;
                document.body.appendChild(textarea);
                textarea.select();
                document.execCommand("copy");
                document.body.removeChild(textarea);
            }
        });
    ');
}
// Get recent posts for potential backlinks
add_action('wp_ajax_get_recent_posts', 'get_recent_posts_for_deepseek');
add_action('wp_ajax_nopriv_get_recent_posts', 'get_recent_posts_for_deepseek');

function get_recent_posts_for_deepseek() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'deepseek_nonce')) {
        wp_send_json_error('Security check failed');
    }
    
    // Get latest 10 posts
    $args = array(
        'post_type' => 'post',
        'post_status' => 'publish',
        'posts_per_page' => 10,
        'orderby' => 'date',
        'order' => 'DESC',
    );
    
    $posts = get_posts($args);
    $formatted_posts = array();
    
    foreach ($posts as $post) {
        $formatted_posts[] = array(
            'id' => $post->ID,
            'title' => html_entity_decode(get_the_title($post->ID)),
            'slug' => rtrim(str_replace(home_url(), '', get_permalink($post->ID)), '/'),
            'excerpt' => get_the_excerpt($post->ID)
        );
    }
    
    wp_send_json_success($formatted_posts);
}
// Only load scripts on our page
function deepseek_seo_generator_load_scripts($hook) {
    if ('posts_page_deepseek-seo-generator' !== $hook) {
        return;
    }
    wp_enqueue_script('jquery');
    deepseek_seo_generator_scripts();
}
add_action('admin_enqueue_scripts', 'deepseek_seo_generator_load_scripts');

// Check if API key exists
add_action('wp_ajax_check_deepseek_api_key', 'check_deepseek_api_key');
function check_deepseek_api_key() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'deepseek_nonce')) {
        wp_send_json_error('Security check failed');
        die();
    }
    
    // Get API key from options
    $api_key = get_option('deepseek_api_key', '');
    
    if (empty($api_key)) {
        wp_send_json_error('API key not found');
    } else {
        wp_send_json_success('API key exists');
    }
    
    die();
}

// Add settings link on plugin page
function deepseek_seo_settings_link($links) {
    $settings_link = '<a href="options-general.php?page=deepseek-seo-settings">Settings</a>';
    array_unshift($links, $settings_link);
    return $links;
}
$plugin = plugin_basename(__FILE__);
add_filter("plugin_action_links_$plugin", 'deepseek_seo_settings_link');

// The page content
function deepseek_seo_generator_page() {
    // Get API key status
    $api_key = get_option('deepseek_api_key', '');
    $has_api_key = !empty($api_key);
    ?>
    <div class="wrap">
        <h1>Blog Generator</h1>
        
        <div class="deepseek-container">
            <?php if (!$has_api_key) : ?>
            <div class="api-settings-notice">
                <p><strong>API Key Required:</strong> You need to set your DeepSeek API key before you can generate content. 
                <a href="<?php echo admin_url('options-general.php?page=deepseek-seo-settings'); ?>">Go to settings page</a> to enter your API key.</p>
            </div>
            <?php endif; ?>
            <div id="deepseek-form" class="deepseek-form">
                <?php wp_nonce_field('deepseek_nonce', 'deepseek_nonce'); ?>
                
                <div class="full-width">
                    <label for="keywords">Keywords * (enter one keyword per line, first one is primary)</label>
                    <textarea id="keywords" name="keywords" required placeholder="Enter your keywords, one per line"></textarea>
                </div>
                
                <div class="full-width">
                    <label for="website">Website URL *</label>
                    <input type="text" id="website" name="website" required value="<?php echo esc_url(home_url()); ?>" placeholder="https://example.com">
                </div>
                
                <div class="full-width">
                    <label for="additional_info">Additional Information (optional)</label>
                    <textarea id="additional_info" name="additional_info" placeholder="Enter any additional details, requirements, or context for the article..."></textarea>
                </div>
                
                <div class="deepseek-buttons full-width">
                    <button type="button" id="deepseek-generate-btn" class="deepseek-button" <?php echo !$has_api_key ? 'disabled' : ''; ?>>Generate SEO Blog</button>
                </div>
                <div class="full-width">
                    <label for="post_categories">Select Categories (optional)</label>
                    <div id="post_categories" class="category-checkboxes">
                        <?php
                        $categories = get_categories(array('hide_empty' => false));
                        if ($categories) {
                            foreach ($categories as $category) {
                                echo '<label class="category-checkbox">';
                                echo '<input type="checkbox" name="categories[]" value="' . $category->term_id . '">';
                                echo ' ' . esc_html($category->name);
                                echo '</label>';
                            }
                        } else {
                            echo '<p>No categories found. <a href="' . admin_url('edit-tags.php?taxonomy=category') . '">Create categories first</a></p>';
                        }
                        ?>
                    </div>
                </div>
                 <div class="full-width">
                    <label for="picture_url">Picture URLs (optional - one per line)</label>
                    <textarea id="picture_url" name="picture_url" placeholder="https://example.com/image1.jpg&#10;https://example.com/image2.jpg&#10;https://example.com/image3.jpg">
                       https://www.looksfam.co/wp-content/uploads/2023/12/10-1.png
                       https://www.looksfam.co/wp-content/uploads/2023/11/cropped-LOOk-3.png
                    </textarea>
                    <small style="display: block; margin-top: 5px; color: #666;">Enter multiple image URLs, one per line. A random image will be selected for the article. Leave blank if you don't want to include a picture.</small>
                </div>
            </div>
            
            <div class="deepseek-loading">
                <div class="spinner"></div>
                <span>Generating content... This may take a minute or two.</span>
            </div>
            
            <div class="deepseek-result">
                <div class="deepseek-meta">
                    <h3>Generated Blog Information</h3>
                    <p><label>Title:</label> <span id="generated-title"></span></p>
                    <p><label>Meta Title:</label> <span id="meta-title"></span></p>
                    <p><label>Meta Description:</label> <span id="meta-description"></span></p>
                    <p><label>Excerpt:</label> <span id="excerpt"></span></p>
                </div>
                
                <div class="deepseek-tabs">
                    <div class="deepseek-tab active" data-tab="content-tab">Content Preview</div>
                    <div class="deepseek-tab" data-tab="html-tab">HTML Code</div>
                </div>
                
                <div class="deepseek-tab-content active" id="content-tab"></div>
                <div class="deepseek-tab-content" id="html-tab"></div>
                
                <div class="deepseek-buttons" style="margin-top: 20px;">
                    <button id="publish-post" class="deepseek-button">Publish as Post</button>
                    <button id="copy-html" class="deepseek-button secondary">Copy HTML</button>
                </div>
            </div>
        </div>
    </div>
    <?php
}

// Handle AJAX request
add_action('wp_ajax_process_deepseek_request', 'process_deepseek_request');
add_action('wp_ajax_nopriv_process_deepseek_request', 'process_deepseek_request');

function process_deepseek_request() {
    // Verify nonce for security
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'deepseek_nonce')) {
        wp_send_json_error('Security check failed');
        die();
    }

    // Get user message
    $message = sanitize_textarea_field($_POST['message']);
    if (empty($message)) {
        wp_send_json_error('Message is required');
        die();
    }

    // Get API key from WordPress options
    $api_key = get_option('deepseek_api_key', '');
    if (empty($api_key)) {
        wp_send_json_error('DeepSeek API key is not configured. Please enter your API key in the Settings > SEO Generator Settings.');
        die();
    }

    // Prepare the API request
    $api_url = 'https://api.deepseek.com/v1/chat/completions';
    $request_body = array(
        'model' => 'deepseek-chat',
        'messages' => array(
            array(
                'role' => 'user',
                'content' => $message
            )
        ),
        'temperature' => 0.7,
        'max_tokens' => 4000
    );

    $response = wp_remote_post($api_url, array(
        'headers' => array(
            'Content-Type' => 'application/json',
            'Authorization' => 'Bearer ' . $api_key
        ),
        'body' => json_encode($request_body),
        'timeout' => 180 // Increased timeout for long responses
    ));

    // Handle API response
    if (is_wp_error($response)) {
        wp_send_json_error($response->get_error_message());
        die();
    }

    $response_body = json_decode(wp_remote_retrieve_body($response), true);
    
    if (isset($response_body['error'])) {
        wp_send_json_error($response_body['error']['message']);
        die();
    }

    // Process successful response
    $ai_response = isset($response_body['choices'][0]['message']['content']) ? 
                   $response_body['choices'][0]['message']['content'] : 
                   'No content returned from API';

    wp_send_json_success($ai_response);
    die();
}
// Handle creating a post from the generated content
add_action('wp_ajax_create_post_from_deepseek', 'create_post_from_deepseek');
function create_post_from_deepseek() {
    // Verify nonce
    if (!isset($_POST['nonce']) || !wp_verify_nonce($_POST['nonce'], 'deepseek_nonce')) {
        wp_send_json_error('Security check failed');
        die();
    }
    
    // Check user capabilities
    if (!current_user_can('edit_posts')) {
        wp_send_json_error('You do not have permission to create posts');
        die();
    }
    
    // Get the content
    $title = sanitize_text_field($_POST['title']);
    
    $picture_url = sanitize_url($_POST['picture_url'] ?? '');
    
    $meta_description = sanitize_text_field($_POST['meta_description']);
    $excerpt = sanitize_textarea_field($_POST['excerpt']);
    $keywords = sanitize_text_field($_POST['keywords']);
    
    // Extract HTML content from code blocks
    $content = $_POST['content'];
    $content = extract_html_from_content($content);
    $content = wp_kses_post($content."[ads]");
    
    
    // Get selected categories
    $categories = array();
    if (isset($_POST['categories']) && is_array($_POST['categories'])) {
        $categories = array_map('intval', $_POST['categories']);
    }
    
    // Create post object
    $post_arr = array(
        'post_title'   => $title,
        'post_content' => $content,
        'post_excerpt' => $excerpt,
        'post_status'  => 'publish',
        'post_type'    => 'post',
        'post_category' => $categories // This will assign the categories
    );
    
    // Insert the post into the database
    $post_id = wp_insert_post($post_arr);
    if (is_wp_error($post_id)) {
        wp_send_json_error($post_id->get_error_message());
        die();
    }
    
    // Alternative way to set categories (more reliable)
    if (!empty($categories)) {
        wp_set_post_categories($post_id, $categories);
    }
    
    // Save meta description (works with Yoast SEO, Rank Math, etc.)
    update_post_meta($post_id, '_yoast_wpseo_metadesc', $meta_description);
    update_post_meta($post_id, 'rank_math_description', $meta_description);
    
    // Save keywords as tags
    if (!empty($keywords)) {
        $keywords_array = explode(',', $keywords);
        //wp_set_post_tags($post_id, $keywords_array);
    }
    
    // Return success with edit URL
    wp_send_json_success(array(
        'post_id' => $post_id,
        'post_link' => get_permalink($post_id),
        'edit_url' => get_edit_post_link($post_id, 'url')
    ));
    die();
}

// Helper function to extract HTML content from markdown code blocks
function extract_html_from_content($content) {
    // Remove any leading/trailing whitespace
    $content = trim($content);
    
    // Pattern to match ```html...``` blocks (case insensitive, handles whitespace)
    $pattern = '/```\s*html\s*\n?(.*?)\n?```/is';
    
    if (preg_match($pattern, $content, $matches)) {
        // Extract the HTML content from the first match
        $html_content = trim($matches[1]);
        return $html_content;
    }
    
    // If no HTML block found, try to find any code block
    $pattern = '/```\s*\n?(.*?)\n?```/is';
    if (preg_match($pattern, $content, $matches)) {
        $code_content = trim($matches[1]);
        // Check if it looks like HTML (contains HTML tags)
        if (preg_match('/<[^>]+>/', $code_content)) {
            return $code_content;
        }
    }
    
    // If no code blocks found, return the original content
    // but remove any stray ``` markers
    $content = preg_replace('/```\s*(html)?\s*/i', '', $content);
    $content = str_replace('```', '', $content);
    
    return trim($content);
}

// Add settings page to WordPress admin
function ai_chat_add_settings_page() {
    add_options_page(
        'AI Chat Settings', 
        'AI Chat Settings', 
        'manage_options', 
        'ai-chat-settings', 
        'ai_chat_settings_page'
    );
}
add_action('admin_menu', 'ai_chat_add_settings_page');
function company_summary_shortcode() {
    
    ob_start();
    ?>
        <h2 >Available Reviewers</h2>
    
    <?php
    echo do_shortcode('[display_classes]');
    ?>
     
    <div class="company-summaries-container">
        <h2 class="">Featured Business Directory</h2>
        <!-- OPEXBI -->
        <div class="company-card">
            <h3 class="company-title">OPEXBI</h3>
            <div class="company-description">Empowering businesses with <a href="https://www.opexbi.com">AI automation</a>, <a href="https://www.opexbi.com">business intelligence</a>, and <a href="https://www.opexbi.com">workflow optimization</a>.</div>
            <div class="company-services"><strong>Key Features:</strong> <ul>
                <li><a href="https://www.opexbi.com">Custom AI Assistants</a></li>
                <li><a href="https://www.opexbi.com">Business Process Automation</a></li>
                <li><a href="https://www.opexbi.com">Data Insights & Analytics</a></li>
                <li><a href="https://www.opexbi.com">Sales & Marketing AI</a></li>
            </ul></div>
            <a href="https://www.opexbi.com" class="cta-button">Optimize Your Business</a>
        </div>
        
        <!-- SpreeRewards -->
        <div class="company-card">
            <h3 class="company-title">SpreeRewards</h3>
            <div class="company-description">Unlock exclusive <a href="https://www.spreerewards.com">rewards and cashback</a> for shopping, dining, and entertainment.</div>
            <div class="company-services"><strong>Features:</strong> <ul>
                <li><a href="https://www.spreerewards.com">Loyalty Rewards Program</a></li>
                <li><a href="https://www.spreerewards.com">Shopping Cashback Deals</a></li>
                <li><a href="https://www.spreerewards.com">Exclusive Member Discounts</a></li>
                <li><a href="https://www.spreerewards.com">Restaurant & Travel Offers</a></li>
            </ul></div>
            <a href="https://www.spreerewards.com" class="cta-button">Start Earning Rewards</a>
        </div>
        
        <!-- Looksfam -->
        <div class="company-card">
            <h3 class="company-title">Looksfam</h3>
            <div class="company-description">The Philippines' leading <a href="https://www.looksfam.co">online reviewer</a> platform for <a href="https://www.looksfam.co">board exam preparation</a>, specializing in <a href="https://www.looksfam.co">LET</a>, <a href="https://www.looksfam.co">LPT</a>, and <a href="https://www.looksfam.co">Civil Engineering board exam</a>.</div>
            <div class="company-services"><strong>Key Features:</strong> <ul>
                <li><a href="https://www.looksfam.co">Professional Board Exam Reviewer</a></li>
                <li><a href="https://www.looksfam.co">Mock Board Examinations</a></li>
                <li><a href="https://www.looksfam.co">Teacher Licensure Exam Prep</a></li>
                <li><a href="https://www.looksfam.co">Civil Engineering Review</a></li>
            </ul></div>
            <a href="https://www.looksfam.co" class="cta-button">Start Your Exam Prep Journey</a>
        </div>

        <!-- Bentamo -->
        <div class="company-card">
            <h3 class="company-title">Bentamo</h3>
            <div class="company-description">Leading provider of <a href="https://www.bentamo.site">SEO services</a>, <a href="https://www.bentamo.site">business automation</a>, and <a href="https://www.bentamo.site">digital marketing solutions</a> in the Philippines.</div>
            <div class="company-services"><strong>Services:</strong> <ul>
                <li><a href="https://www.bentamo.site">Search Engine Optimization</a></li>
                <li><a href="https://www.bentamo.site">Social Media Marketing</a></li>
                <li><a href="https://www.bentamo.site">Content Marketing Strategy</a></li>
                <li><a href="https://www.bentamo.site">Marketing Automation</a></li>
                <li><a href="https://www.bentamo.site">Business Process Automation</a></li>
                <li><a href="https://www.bentamo.site">Website Development</a></li>
            </ul></div>
            <a href="https://www.bentamo.site" class="cta-button">Transform Your Business</a>
        </div>

        <!-- Xaps -->
        <div class="company-card">
            <h3 class="company-title">Xaps</h3>
            <div class="company-description">Leading provider of <a href="https://www.xaps.me">digital business cards</a> and <a href="https://www.xaps.me">NFC solutions in the Philippines</a>.</div>
            <div class="company-services"><strong>Features:</strong> <ul>
                <li><a href="https://www.xaps.me">Digital Business Cards Philippines</a></li>
                <li><a href="https://www.xaps.me">NFC Business Cards</a></li>
                <li><a href="https://www.xaps.me">Smart Contact Management</a></li>
                <li><a href="https://www.xaps.me">Digital Networking Solutions</a></li>
            </ul></div>
            <a href="https://www.xaps.me" class="cta-button">Create Your Digital Card</a>
        </div>

        <!-- SPower Solutions -->
        <div class="company-card">
            <h3 class="company-title">SPower Solutions</h3>
            <div class="company-description">Leading provider of <a href="https://www.spowersolutions.ph">solar power solutions</a> and <a href="https://www.spowersolutions.ph">renewable energy</a> in the Philippines.</div>
            <div class="company-services"><strong>Services:</strong> <ul>
                <li><a href="https://www.spowersolutions.ph">Solar Panel Installation</a></li>
                <li><a href="https://www.spowersolutions.ph">Solar Energy Systems</a></li>
                <li><a href="https://www.spowersolutions.ph">Renewable Energy Solutions</a></li>
                <li><a href="https://www.spowersolutions.ph">Solar Power Consultation</a></li>
            </ul></div>
            <a href="https://www.spowersolutions.ph" class="cta-button">Go Solar Today</a>
        </div>

        <!-- N Hotel -->
        <div class="company-card">
            <h3 class="company-title">N Hotel</h3>
            <div class="company-description">Premier <a href="https://www.nhotelcdo.com">Cagayan de Oro hotel</a> offering luxurious <a href="https://www.nhotelcdo.com">CDO accommodation</a>.</div>
            <div class="company-services"><strong>Amenities:</strong> <ul>
                <li><a href="https://www.nhotelcdo.com">Hotels in Cagayan de Oro</a></li>
                <li><a href="https://www.nhotelcdo.com">CDO Event Venues</a></li>
                <li><a href="https://www.nhotelcdo.com">Business Hotel CDO</a></li>
                <li><a href="https://www.nhotelcdo.com">Luxury Accommodation CDO</a></li>
            </ul></div>
            <a href="https://www.nhotelcdo.com" class="cta-button">Book Your Stay</a>
        </div>

        <!-- Best Friend Goodies -->
        <div class="company-card">
            <h3 class="company-title">Best Friend Goodies</h3>
            <div class="company-description">Your ultimate destination for <a href="https://www.bestfriendgoodies.com">Cagayan de Oro pasalubong</a> and <a href="https://www.bestfriendgoodies.com">CDO souvenirs</a>.</div>
            <div class="company-services"><strong>Offerings:</strong> <ul>
                <li><a href="https://www.bestfriendgoodies.com">CDO Pasalubong Center</a></li>
                <li><a href="https://www.bestfriendgoodies.com">Local Delicacies CDO</a></li>
                <li><a href="https://www.bestfriendgoodies.com">Gift Shops in CDO</a></li>
                <li><a href="https://www.bestfriendgoodies.com">Mindanao Souvenirs</a></li>
            </ul></div>
            <a href="https://www.bestfriendgoodies.com" class="cta-button">Shop Now</a>
        </div>
    </div>
      
        
        
        
    <style>
        .company-summaries-container {
            max-width: 1200px;
            margin: 0 auto;
            padding: 20px;
            display: grid;
            grid-template-columns: repeat(auto-fit, minmax(300px, 1fr));
            gap: 20px;
        }
        .main-section-title {
            grid-column: 1/-1;
            text-align: center;
            font-size: 2.5em;
            color: #333;
            margin-bottom: 30px;
        }
        .company-card {
            background: #fff;
            border-radius: 10px;
            padding: 20px;
            box-shadow: 0 3px 10px rgba(0,0,0,0.1);
            transition: transform 0.3s ease;
        }
        .company-card:hover {
            transform: translateY(-5px);
        }
        .company-title {
            color: #2c3e50;
            font-size: 1.5em;
            margin-bottom: 15px;
            border-bottom: 2px solid #3498db;
            padding-bottom: 10px;
        }
        .company-description {
            color: #555;
            margin-bottom: 15px;
            line-height: 1.6;
        }
        .company-services ul {
            list-style-type: none;
            padding-left: 0;
        }
        .company-services li {
            margin: 5px 0;
            color: #666;
        }
        .cta-button {
            display: inline-block;
            padding: 10px 20px;
            background: #3498db;
            color: white;
            text-decoration: none;
            border-radius: 5px;
            margin-top: 15px;
            transition: background 0.3s ease;
        }
        .cta-button:hover {
            background: #2980b9;
        }
        a {
            color: #3498db;
            text-decoration: none;
        }
        a:hover {
            text-decoration: underline;
        }
    </style>
    <?php
    return ob_get_clean();
}
add_shortcode('ads', 'company_summary_shortcode');
?>