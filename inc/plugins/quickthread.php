<?php
/**
 * Quick Thread Plugin for MyBB
 * Inspired by XenForo's Quick Thread Feature
 * 
 * @package QuickThread
 * @author PARADOX
 * @version 1.0
 * @license MIT License
 */

// Prevent direct access
if(!defined("IN_MYBB"))
{
    die("Direct initialization of this file is not allowed.");
}

// Define PluginLibrary constant
if (!defined("PLUGINLIBRARY")) {
    define("PLUGINLIBRARY", MYBB_ROOT . "inc/plugins/pluginlibrary.php");
}

// Plugin Hooks
$plugins->add_hook('global_start', 'quickthread_global_start');
$plugins->add_hook('forumdisplay_start', 'quickthread_forumdisplay_start');
$plugins->add_hook('forumdisplay_threadlist', 'quickthread_forumdisplay');
$plugins->add_hook('index_start', 'quickthread_index_start');
$plugins->add_hook('index_end', 'quickthread_index');
$plugins->add_hook('xmlhttp', 'quickthread_ajax_handler');

/**
 * Plugin Information
 */
function quickthread_info()
{
    return array(
        "name"          => "Quick Thread",
        "description"   => "Thread creation with inline expanding form and AJAX submission. Features smooth animations, instant thread creation, and forum chooser overlay.",
        "website"       => "https://mybb.epizy.com",
        "author"        => "PARADOX",
        "authorsite"    => "https://community.mybb.com/user-106685.html",
        "version"       => "2.1.0",
        "guid"          => "",
        "codename"      => "quickthread",
        "compatibility" => "18*"
    );
}

/**
 * Check if plugin is installed
 */
function quickthread_is_installed()
{
    global $db;
    
    $query = $db->simple_select('settinggroups', '*', "name='quickthread'");
    return ($db->num_rows($query) > 0);
}

/**
 * Plugin Installation
 */
function quickthread_install()
{
    global $db, $PL;
    
    // Require PluginLibrary
    if(!file_exists(PLUGINLIBRARY))
    {
        flash_message("PluginLibrary is required for Quick Thread plugin.", "error");
        admin_redirect("index.php?module=config-plugins");
    }
    
    require_once PLUGINLIBRARY;
    $PL = new PluginLibrary();
    
    // Add settings from JSON file
    quickthread_add_settings();
    
    // Add templates using PluginLibrary
    quickthread_add_templates();
    
    // Add stylesheet using PluginLibrary
    quickthread_add_stylesheet();
    
    rebuild_settings();
}

/**
 * Plugin Uninstallation
 */
function quickthread_uninstall()
{
    global $db, $PL;
    
    if(file_exists(PLUGINLIBRARY))
    {
        require_once PLUGINLIBRARY;
        $PL = new PluginLibrary();
    }
    
    // Remove settings
    quickthread_remove_settings();
    
    // Remove templates
    quickthread_remove_templates();
    
    // Remove stylesheet
    quickthread_remove_stylesheet();
    
    rebuild_settings();
}

/**
 * Plugin Activation
 */
function quickthread_activate()
{
    global $db, $PL;
    
    if(file_exists(PLUGINLIBRARY))
    {
        require_once PLUGINLIBRARY;
        $PL = new PluginLibrary();
    }
    
    // Ensure templates and stylesheet are up to date
    quickthread_add_templates();
    quickthread_add_stylesheet();
    
    // Apply template modifications using MyBB's built-in function
    require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
    
    // Add to forumdisplay_threadlist template (after inline mod column)
    find_replace_templatesets(
        'forumdisplay_threadlist',
        '#\{\$inlinemodcol\}\s*</tr>#',
        '{$inlinemodcol}</tr>'."\n\t<tr class=\"inline-row\">\n\t\t<td align=\"center\" colspan=\"{\$colspan}\">{\$quickthread}</td>\n\t</tr>"
    );
    
    // Add to index template
    find_replace_templatesets(
        'index',
        '#\{\$header\}#',
        '{$header}'."\n".'{$quickthread_button}{$quickthread_chooser}'
    );
    
    rebuild_settings();
}

/**
 * Plugin Deactivation
 */
function quickthread_deactivate()
{
    require_once MYBB_ROOT.'inc/adminfunctions_templates.php';
    
    // Remove from forumdisplay_threadlist
    find_replace_templatesets(
        'forumdisplay_threadlist',
        '#\{\$inlinemodcol\}</tr>\s*<tr class="inline-row">\s*<td align="center" colspan="\{\$colspan\}">\{\$quickthread\}</td>\s*</tr>#',
        '{$inlinemodcol}
	</tr>'
    );
    
    // Remove quick thread button and chooser from index template
    find_replace_templatesets(
        'index',
        '#\{\$header\}\s*\{\$quickthread_button\}\{\$quickthread_chooser\}#',
        '{$header}'
    );
}

// ============================================================
// INSTALLATION FUNCTIONS
// ============================================================

/**
 * Add settings using PluginLibrary (same as Reactions plugin)
 */
function quickthread_add_settings()
{
    global $PL;

    if(file_exists(PLUGINLIBRARY))
    {
        require_once PLUGINLIBRARY;
        $PL = new PluginLibrary();
        
        $settingsFile = MYBB_ROOT.'inc/plugins/Quickthread/settings.json';
        if(file_exists($settingsFile))
        {
            $json = @file_get_contents($settingsFile);
            $data = @json_decode($json, true);
            if(is_array($data) && isset($data['group']) && isset($data['settings']) && is_array($data['settings']))
            {
                $group = $data['group'];
                $name = !empty($group['name']) ? $group['name'] : 'quickthread';
                $title = !empty($group['title']) ? $group['title'] : 'Quick Thread Settings';
                $description = !empty($group['description']) ? $group['description'] : 'Configure the Quick Thread feature';
                
                // Format settings for PluginLibrary (needs name as key)
                $formattedSettings = array();
                foreach($data['settings'] as $setting)
                {
                    if(isset($setting['name']))
                    {
                        $sname = $setting['name'];
                        // Strip group name prefix if it exists to avoid doubling
                        if(strpos($sname, $name . '_') === 0)
                        {
                            $sname = substr($sname, strlen($name) + 1);
                        }
                        unset($setting['name']);
                        $formattedSettings[$sname] = $setting;
                    }
                }
                
                $PL->settings($name, $title, $description, $formattedSettings);
            }
        }
    }
}

/**
 * Remove settings
 */
function quickthread_remove_settings()
{
    global $PL;

    if(file_exists(PLUGINLIBRARY))
    {
        require_once PLUGINLIBRARY;
        $PL = new PluginLibrary();
        $PL->settings_delete('quickthread', true);
    }
}

/**
 * Add templates using PluginLibrary
 */
function quickthread_add_templates()
{
    global $db, $PL;

    // Check if templates already exist
    $query = $db->simple_select('templates', 'COUNT(*) as count', "title='quickthread' AND sid=-2");
    $template_count = $db->fetch_field($query, 'count');
    
    if($template_count > 0)
    {
        return;
    }

    if(file_exists(PLUGINLIBRARY))
    {
        require_once PLUGINLIBRARY;
        $PL = new PluginLibrary();

        $basePath = MYBB_ROOT.'inc/plugins/Quickthread/Templates/';

        $template_files = array(
            'quickthread' => 'quickthread.html',
            'quickthread_form_fields' => 'quickthread_form_fields.html',
            'quickthread_chooser' => 'quickthread_chooser.html',
            'quickthread_chooser_category' => 'quickthread_chooser_category.html',
            'quickthread_chooser_forum' => 'quickthread_chooser_forum.html',
            'quickthread_newthread_button' => 'quickthread_newthread_button.html',
            'quickthread_errors' => 'quickthread_errors.html',
            'quickthread_error_item' => 'quickthread_error_item.html'
        );

        $template_list = array();
        foreach($template_files as $key => $file)
        {
            $path = $basePath.$file;
            if(file_exists($path))
            {
                $template_list[$key] = file_get_contents($path);
            }
        }

        if($template_list)
        {
            $PL->templates('quickthread', 'Quick Thread', $template_list);
        }
    }
}

function quickthread_remove_templates()
{
    global $PL;

    if(file_exists(PLUGINLIBRARY))
    {
        require_once PLUGINLIBRARY;
        $PL = new PluginLibrary();
        $PL->templates_delete('quickthread', true);
    }
}

/**
 * Add stylesheet using PluginLibrary
 */
function quickthread_add_stylesheet()
{
    global $PL;
    
    if(file_exists(PLUGINLIBRARY))
    {
        require_once PLUGINLIBRARY;
        $PL = new PluginLibrary();
        
        $css_file = MYBB_ROOT.'inc/plugins/Quickthread/Assets/quickthread.css';
        if(file_exists($css_file))
        {
            $css = file_get_contents($css_file);
            $PL->stylesheet('quickthread', $css);
        }
    }
}

/**
 * Remove stylesheet
 */
function quickthread_remove_stylesheet()
{
    global $PL;
    
    if(file_exists(PLUGINLIBRARY))
    {
        require_once PLUGINLIBRARY;
        $PL = new PluginLibrary();
        $PL->stylesheet_delete('quickthread');
    }
}

// ============================================================
// HOOK FUNCTIONS
// ============================================================

/**
 * Global start hook - add templates to templatelist
 */
function quickthread_global_start()
{
    global $templatelist;
    
    if(!isset($templatelist))
    {
        $templatelist = "";
    }
    
    // Templates are stored with 'quickthread_' prefix by PluginLibrary
    $templatelist .= ",quickthread_quickthread,quickthread_quickthread_form_fields,quickthread_quickthread_chooser,quickthread_quickthread_chooser_category,quickthread_quickthread_chooser_forum,quickthread_quickthread_newthread_button,quickthread_quickthread_errors,quickthread_quickthread_error_item";
    
    // Add codebuttons template for MyBB editor
    $templatelist .= ",codebuttons";
}

/**
 * Load resources on forumdisplay start (before header)
 */
function quickthread_forumdisplay_start()
{
    global $mybb;
    
    if(($mybb->settings['quickthread_enabled'] ?? 0) == 1)
    {
        quickthread_add_resources();
    }
}

/**
 * Load resources on index start (before header)
 */
function quickthread_index_start()
{
    global $mybb;
    
    if($mybb->settings['quickthread_enabled'] == 1 && $mybb->settings['quickthread_showchooser'] == 1)
    {
        quickthread_add_resources();
    }
}

/**
 * Add Quick Thread form to forum display
 */
function quickthread_forumdisplay()
{
    global $mybb, $templates, $fid, $forumpermissions, $forum, $lang, $quickthread, $headerinclude, $theme, $foruminfo;
    
    // Initialize quick thread as empty
    $quickthread = '';
    
    // Check if we can use quick thread
    $can_use = quickthread_can_use($fid);
    
    // If false, user cannot use quick thread at all
    if($can_use === false)
    {
        return;
    }
    
    // Load language files
    $lang->load('quickthread');
    $lang->load('newthread');
    
    // Check if guest (can view but can't post)
    $is_guest = ($can_use === 'guest');
    
    // Generate posthash for attachments
    $posthash = md5($mybb->user['uid'] . random_str() . TIME_NOW);
    
    // Build prefix select - show if user is logged in and prefixes exist
    $prefixselect = '';
    if(!$is_guest && count(build_prefixes($fid)) > 0)
    {
        $prefixselect = build_prefix_select($fid);
        // Add class to the select element
        $prefixselect = str_replace('<select ', '<select class="qt-prefix" ', $prefixselect);
    }
    
    // Format user avatar
    if($mybb->user['uid'])
    {
        $avatar = format_avatar($mybb->user['avatar'], $mybb->user['avatardimensions'], '40x40');
        $mybb->user['avatar_formatted'] = "<img src=\"{$avatar['image']}\" alt=\"\" width=\"40\" height=\"40\" />";
    }
    else
    {
        // Use default avatar path for guests
        $mybb->user['avatar_formatted'] = "<img src=\"{$mybb->settings['bburl']}/images/default_avatar.png\" alt=\"\" width=\"40\" height=\"40\" />";
    }
    
    // Set guest notice display
    $guest_notice_display = $is_guest ? '' : 'display:none;';
    
    // Build the MyBB editor (like the old working plugin)
    $codebuttons = '';
    $smilieinserter = '';
    
    if($mybb->settings['bbcodeinserter'] != 0 && $foruminfo['allowmycode'] != 0 && (!$mybb->user['uid'] || $mybb->user['showcodebuttons'] != 0))
    {
        $codebuttons = build_mycode_inserter("qt_message_".$fid, $foruminfo['allowsmilies']);
        if($foruminfo['allowsmilies'] != 0)
        {
            $smilieinserter = build_clickable_smilies("qt_message_".$fid);
        }
    }
    
    // Build message textarea - use standard MyBB textarea that works with SCEditor
    $message_textarea = "<textarea name=\"message\" id=\"qt_message_{$fid}\" rows=\"8\" cols=\"80\" class=\"textarea\" style=\"width: 100%;\" tabindex=\"2\"></textarea>";
    
    // Post options
    $sig_checked = $mybb->user['signature'] ? 'checked="checked"' : '';
    $sub_checked = '';
    if($mybb->user['uid'] > 0)
    {
        if($mybb->user['subscriptionmethod'] == 1 || $mybb->user['subscriptionmethod'] == 2)
        {
            $sub_checked = 'checked="checked"';
        }
    }
    
    // Custom fields (simplified)
    $custom_fields = '';
    
    // Captcha for guests
    $captcha = '';
    if($mybb->user['uid'] == 0 && $mybb->settings['captchaimage'])
    {
        require_once MYBB_ROOT.'inc/class_captcha.php';
        $post_captcha = new captcha(false, "post_captcha");
        
        if($post_captcha->type == 1)
        {
            $post_captcha->build_captcha();
        }
        elseif(in_array($post_captcha->type, array(4, 5, 8)))
        {
            $post_captcha->build_recaptcha();
        }
        elseif(in_array($post_captcha->type, array(6, 7)))
        {
            $post_captcha->build_hcaptcha();
        }
        
        if($post_captcha->html)
        {
            $captcha = $post_captcha->html;
        }
    }
    
    // Attachment field - simplified
    $attachment_field = '';
    
    // Moderator options
    $modoptions = '';
    if(is_moderator($fid, 'canopenclosethreads'))
    {
        $modoptions .= '<br /><label><input type="checkbox" class="checkbox" name="modoptions[closethread]" value="1" />&nbsp;<strong>'.$lang->close_thread.'</strong></label>';
    }
    if(is_moderator($fid, 'canmanagethreads'))
    {
        $modoptions .= '<br /><label><input type="checkbox" class="checkbox" name="modoptions[stickthread]" value="1" />&nbsp;<strong>'.$lang->stick_thread.'</strong></label>';
    }
    
    // Poll options
    $postpollchecked = '';
    $numpolloptions = '2';
    $maxpolloptions = !empty($mybb->settings['maxpolloptions']) ? (int)$mybb->settings['maxpolloptions'] : 10;
    $poll_options_display = 'display: none;';
    $lang->quickthread_poll_options_desc = $lang->sprintf($lang->quickthread_poll_options_desc, $maxpolloptions);
    
	$all_perms = forum_permissions();
	$fperms = isset($all_perms[$fid]) ? $all_perms[$fid] : array();
    
    // Get the form fields template
    $form_fields_html = '';
    eval('$form_fields_html = "' . $templates->get('quickthread_quickthread_form_fields') . '";');
    
    // Animation setting
    $quickthread_animate = $mybb->settings['quickthread_animate'] ? 'true' : 'false';
    
    // AJAX mode setting
    $quickthread_ajax_mode = $mybb->settings['quickthread_ajax_only'] ? 'true' : 'false';
    
    // Generate the quick thread HTML
    eval('$quickthread = "' . $templates->get('quickthread_quickthread') . '";');
}

/**
 * Add forum chooser to index page
 */
function quickthread_index()
{
    global $mybb, $templates, $lang, $quickthread_button, $quickthread_chooser, $header;
    
    // Initialize variables as empty
    $quickthread_button = '';
    $quickthread_chooser = '';
    
    // Check if chooser is enabled
    if($mybb->settings['quickthread_showchooser'] != 1)
    {
        return;
    }
    
    // Only for logged in users
    if($mybb->user['uid'] == 0)
    {
        return;
    }
    
    // Load language files
    $lang->load('quickthread');
    $lang->load('newthread');
    
    // Build forum list
    $forum_list = quickthread_build_forum_list();
    
    // Generate button and chooser
    eval('$quickthread_button = "' . $templates->get('quickthread_quickthread_newthread_button') . '";');
    eval('$quickthread_chooser = "' . $templates->get('quickthread_quickthread_chooser') . '";');
}

/**
 * Get theme for AJAX requests
 * Ensures theme is loaded when accessing via xmlhttp.php
 */
function quickthread_get_theme()
{
    global $mybb, $cache, $db;

    // Try to get theme from user settings
    if(isset($mybb->user['style']) && (int)$mybb->user['style'] != 0)
    {
        $loadstyle = "tid='".(int)$mybb->user['style']."'";
    }
    else
    {
        $loadstyle = "def='1'";
    }

    // Load theme from database
    $query = $db->simple_select('themes', 'name, tid, properties, imgdir', $loadstyle, array('limit' => 1));
    $theme_data = $db->fetch_array($query);

    if(!$theme_data)
    {
        // Fall back to default theme from cache
        $theme_data = $cache->read('default_theme');
    }

    // Parse properties
    if(!empty($theme_data['properties']))
    {
        $theme_data = array_merge($theme_data, my_unserialize($theme_data['properties']));
    }

    return $theme_data;
}

/**
 * Handle AJAX requests
 */
function quickthread_ajax_handler()
{
    global $mybb, $db, $templates, $lang, $theme, $cache;

    // Check if this is a quick thread AJAX request
    $action = $mybb->input['action'];

    if($action == 'quickthread_load_form')
    {
        // Enable error capturing for debugging
        $error_msg = '';

        // Load the expanded form via AJAX
        $fid = (int)$mybb->input['fid'];

        if(!$fid)
        {
            json_response(array('error' => 'Invalid forum ID'));
        }

        // Load language
        $lang->load('quickthread');
        $lang->load('newthread');
        $lang->load('member');
        
        // Get forum info FIRST
        $forum = get_forum($fid);
        
        if(!$forum || $forum['type'] != 'f')
        {
            json_response(array('error' => 'Invalid forum'));
        }
        
        // Check permissions - forum_permissions returns array indexed by fid
        $all_perms = forum_permissions();
        $fperms = isset($all_perms[$fid]) ? $all_perms[$fid] : array();
        
        if(empty($fperms) || $fperms['canview'] == 0 || $fperms['canpostthreads'] == 0)
        {
            // Allow guests to see the form (they'll need to login to post)
            if($mybb->user['uid'] == 0)
            {
                // Guest can load form but will see login message
            }
            else
            {
                json_response(array('error' => 'Permission denied'));
            }
        }
        
        // Get the forum info for smilies
        $forum = get_forum($fid);
        $allow_smilies = $forum['allowsmilies'] ? 1 : 0;
        
        // Build the codebuttons using MyBB's built-in function
        $codebuttons = build_mycode_inserter("qt_message_{$fid}", $allow_smilies);
        
        // Textarea for quick thread
        $message_textarea = "<textarea name=\"message\" id=\"qt_message_{$fid}\" rows=\"6\" class=\"textarea\" style=\"width: 100%;\" tabindex=\"2\"></textarea>";

        // Build post icons - disabled for now
        $posticons_row = '';

        // Build attachment field
        $attachment_field = '';
        if($forum['allowattachments'] && $mybb->user['uid'] > 0)
        {
            // Attachment field is complex - skip for quick thread to avoid errors
            // Users can use "More Options" to access full attachment interface
            $attachment_field = '';
        }

        // Post options - set default values
        $sig_checked = $mybb->user['signature'] ? 'checked="checked"' : '';
        $sub_checked = '';
        if($mybb->user['uid'] > 0)
        {
            // Check user subscription default
            if($mybb->user['subscriptionmethod'] == 1 || $mybb->user['subscriptionmethod'] == 2)
            {
                $sub_checked = 'checked="checked"';
            }
        }

        // Custom fields (thread fields) - simplified for quick thread
        $custom_fields = '';

        // Ensure fid is available for template
        $fid = (int)$fid;

        // Captcha for guests
        $captcha = '';
        if($mybb->user['uid'] == 0 && $mybb->settings['captchaimage'])
        {
            // Use registration captcha
            $cap = '<img src="' . $mybb->settings['bburl'] . '/captcha.php?action=new" alt="" id="imagecode" />';
            $captcha = '<div class="qt-captcha"><strong>' . $lang->verification . ':</strong><br />' . $cap . '<br /><input type="text" name="imagestring" size="25" maxlength="50" /></div>';
        }

        // Get the form fields template
        $form_html = '';
        $template_content = $templates->get('quickthread_quickthread_form_fields');

        if(!$template_content)
        {
            json_response(array('error' => 'Template not found: quickthread_quickthread_form_fields. Please reactivate the plugin.'));
        }

        // Make sure required variables exist
        $foruminfo = $forum;
        
        // Build the form HTML
        ob_start();
        eval('$form_html = "' . $template_content . '";');
        $eval_error = ob_get_clean();
        
        if(!empty($eval_error))
        {
            json_response(array('error' => 'Template error: ' . $eval_error));
        }

        json_response(array(
            'success' => true,
            'html' => $form_html
        ));
    }
    
    // Handle AJAX thread submission
    if($action == 'quickthread_submit')
    {
        $lang->load('quickthread');
        $lang->load('newthread');
        
        $fid = (int)$mybb->input['fid'];
        
        if(!$fid)
        {
            json_response(array('error' => 'Invalid forum ID'));
        }
        
        // Verify post key
        if(!verify_post_check($mybb->input['my_post_key']))
        {
            json_response(array('error' => 'Invalid security token. Please refresh the page.'));
        }
        
        // Get forum info
        $forum = get_forum($fid);
        if(!$forum || $forum['type'] != 'f')
        {
            json_response(array('error' => 'Invalid forum'));
        }
        
        // Check permissions
        $forumpermissions = forum_permissions($fid);
        if($forumpermissions['canpostthreads'] != 1)
        {
            json_response(array('error' => 'You do not have permission to post threads in this forum'));
        }
        
        // Check for required fields
        $subject = trim($mybb->input['subject']);
        $message = trim($mybb->input['message']);
        
        $errors = array();
        
        if(empty($subject))
        {
            $errors[] = $lang->error_nosubject;
        }
        
        if(empty($message))
        {
            $errors[] = $lang->error_nomessage;
        }
        
        if(!empty($errors))
        {
            json_response(array('errors' => $errors));
        }
        
        // Load required functions
        require_once MYBB_ROOT . 'inc/functions.php';
        require_once MYBB_ROOT . 'inc/datahandlers/post.php';
        
        $uid = $mybb->user['uid'];
        $username = $mybb->user['uid'] ? $mybb->user['username'] : $mybb->get_input('username');
        
        // Set up posthandler
        $posthandler = new PostDataHandler("insert");
        $posthandler->action = "thread";
        
        // Prepare thread data
        $new_thread = array(
            "fid" => $fid,
            "subject" => $subject,
            "prefix" => $mybb->get_input('threadprefix', MyBB::INPUT_INT),
            "icon" => $mybb->get_input('icon', MyBB::INPUT_INT),
            "uid" => $uid,
            "username" => $username,
            "message" => $message,
            "posthash" => $mybb->input['posthash'],
            "savedraft" => 0
        );
        
        $posthandler->set_data($new_thread);
        
        // Validate
        $posthandler->validate_thread();
        $thread_errors = $posthandler->get_errors();
        
        if(!empty($thread_errors))
        {
            $error_messages = array();
            foreach($thread_errors as $error)
            {
                $error_messages[] = is_array($error) ? $error[0] : $error;
            }
            json_response(array('errors' => $error_messages));
        }
        
        // Insert thread
        $thread_info = $posthandler->insert_thread();
        
        if($thread_info && $thread_info['tid'])
        {
            $tid = $thread_info['tid'];
            $pid = $thread_info['pid'];
            
            // Handle poll if requested
            if($mybb->input['postpoll'] == 1 && $forumpermissions['canpostpolls'])
            {
                $poll_options = $mybb->get_input('numpolloptions', MyBB::INPUT_INT);
                if($poll_options < 2) $poll_options = 2;
                if($poll_options > 10) $poll_options = 10;
                
                json_response(array(
                    'success' => true,
                    'redirect' => 'polls.php?action=newpoll&tid=' . $tid . '&polloptions=' . $poll_options
                ));
            }
            
            // Get the thread link
            $thread_link = get_thread_link($tid);
            
            json_response(array(
                'success' => true,
                'tid' => $tid,
                'pid' => $pid,
                'link' => $thread_link,
                'message' => $lang->quickthread_success
            ));
        }
        else
        {
            json_response(array('error' => 'Failed to create thread. Please try again.'));
        }
    }
}

/**
 * Build forum list for chooser with proper nesting
 */
function quickthread_build_forum_list()
{
    global $db, $mybb, $templates, $lang, $theme;
    
    $lang->load('quickthread');
    
    $categories_html = '';
    
    // Get all active forums with permissions
    $all_forums = array();
    $query = $db->simple_select('forums', 'fid, pid, name, type, active, threads', 'active!=0', array('order_by' => 'pid, disporder'));
    while($forum = $db->fetch_array($query))
    {
        // Check permissions
        $perms = forum_permissions($forum['fid']);
        if($perms && $perms['canview'] == 1 && $perms['canpostthreads'] == 1)
        {
            $all_forums[$forum['fid']] = $forum;
        }
    }
    
    // Build hierarchy
    $forum_tree = array();
    foreach($all_forums as $fid => $forum)
    {
        if($forum['type'] == 'c')
        {
            // Category
            $forum_tree[$fid] = array(
                'forum' => $forum,
                'subforums' => array(),
                'is_category' => true
            );
        }
        elseif($forum['pid'] == 0)
        {
            // Top-level forum
            $forum_tree[$fid] = array(
                'forum' => $forum,
                'subforums' => array(),
                'is_category' => false
            );
        }
    }
    
    // Add sub-forums to their parents
    foreach($all_forums as $fid => $forum)
    {
        if($forum['type'] == 'f' && $forum['pid'] > 0)
        {
            // This is a sub-forum
            $pid = $forum['pid'];
            if(isset($forum_tree[$pid]))
            {
                $forum_tree[$pid]['subforums'][] = $forum;
            }
            elseif(isset($forum_tree[$forum['pid']]))
            {
                // Try parent's parent
                $parent = $all_forums[$forum['pid']];
                if(isset($forum_tree[$parent['pid']]))
                {
                    $forum_tree[$parent['pid']]['subforums'][] = $forum;
                }
            }
        }
    }
    
    // Render the tree
    foreach($forum_tree as $fid => $data)
    {
        if($data['is_category'])
        {
            // Render category
            $category_forums = '';
            
            // First render direct child forums
            foreach($data['subforums'] as $forum)
            {
                $category_forums .= quickthread_render_forum_item($forum, 0);
                
                // Check for nested sub-forums
                foreach($all_forums as $subfid => $subforum)
                {
                    if($subforum['pid'] == $forum['fid'])
                    {
                        $category_forums .= quickthread_render_forum_item($subforum, 1);
                    }
                }
            }
            
            if(!empty($category_forums))
            {
                $category['name'] = $data['forum']['name'];
                eval('$categories_html .= "' . $templates->get('quickthread_quickthread_chooser_category') . '";');
            }
        }
    }
    
    return $categories_html;
}

/**
 * Render a single forum item
 */
function quickthread_render_forum_item($forum, $depth)
{
    global $mybb, $templates;
    
    $forum_link = $mybb->settings['bburl'] . '/newthread.php?fid=' . $forum['fid'];
    $thread_count = $forum['threads'];
    $disabled_class = '';
    $disabled_attr = '';
    $depth_class = 'qt-depth-' . $depth;
    
    $html = '';
    eval('$html = "' . $templates->get('quickthread_quickthread_chooser_forum') . '";');
    
    return $html;
}

/**
 * Add CSS and JS resources
 */
function quickthread_add_resources()
{
    global $mybb, $headerinclude, $theme;
    
    static $added = false;
    
    if($added)
    {
        return;
    }
    
    // JavaScript
    $js_url = $mybb->settings['bburl'] . '/inc/plugins/Quickthread/Assets/quickthread.js?v=2.0';
    
    // CSS
    $css_url = $mybb->settings['bburl'] . '/inc/plugins/Quickthread/Assets/quickthread.css?v=2.0';
    
    // Add to headerinclude
    $headerinclude .= "\n<link rel=\"stylesheet\" type=\"text/css\" href=\"{$css_url}\" />\n";
    $headerinclude .= "<script type=\"text/javascript\" src=\"{$js_url}\"></script>\n";
    
    // Only add SCEditor resources on forumdisplay pages
    // (not needed on index or other pages)
    if(THIS_SCRIPT == 'forumdisplay.php')
    {
        // Add SCEditor styles to header
        $editor_css = '<link rel="stylesheet" href="'.$mybb->asset_url.'/jscripts/sceditor/themes/'.$theme['editortheme'].'" type="text/css" media="all" />';
        
        // Add SCEditor scripts
        $editor_js = '';
        $editor_js .= '<script type="text/javascript" src="'.$mybb->asset_url.'/jscripts/sceditor/jquery.sceditor.bbcode.min.js?ver=1832"></script>';
        $editor_js .= '<script type="text/javascript" src="'.$mybb->asset_url.'/jscripts/bbcodes_sceditor.js?ver=1837"></script>';
        $editor_js .= '<script type="text/javascript" src="'.$mybb->asset_url.'/jscripts/sceditor/plugins/undo.js?ver=1832"></script>';
        
        $headerinclude .= $editor_css . "\n";
        $headerinclude .= $editor_js . "\n";
    }
    
    $added = true;
}

// ============================================================
// HELPER FUNCTIONS
// ============================================================

/**
 * Check if user can use quick thread in this forum
 * Returns: 
 *   - true: can use (logged in and can post)
 *   - false: cannot use (guest or no permission)
 *   - 'guest': can view form but need to login to post
 */
function quickthread_can_use($fid)
{
    global $mybb, $forumpermissions, $forum;
    
    // If $forum is not set, load it
    if(empty($forum))
    {
        $forum = get_forum($fid);
    }
    
    // If $forumpermissions is not set, load it and get the specific forum's permissions
    if(empty($forumpermissions))
    {
        $all_perms = forum_permissions();
        $fpermissions = isset($all_perms[$fid]) ? $all_perms[$fid] : array();
    }
    else
    {
        // Use the already-loaded permissions for this forum
        $fpermissions = isset($forumpermissions[$fid]) ? $forumpermissions[$fid] : array();
    }
    
    // Plugin enabled?
    if(($mybb->settings['quickthread_enabled'] ?? 0) != 1)
    {
        return false;
    }
    
    // Check forum restrictions from settings
    if(!empty($mybb->settings['quickthread_forums']))
    {
        $allowed_forums = array_map('trim', explode(',', $mybb->settings['quickthread_forums']));
        if(!in_array($fid, $allowed_forums))
        {
            return false;
        }
    }
    
    // Must be able to view the forum
    if(empty($fpermissions) || $fpermissions['canview'] == 0)
    {
        return false;
    }
    
    // Check if user is suspended
    if($mybb->user['suspendposting'] == 1)
    {
        return false;
    }
    
    // Check forum type - must be a regular forum (not category, etc)
    if($forum['type'] != 'f' || $forum['open'] == 0 || $forum['linkto'] != '')
    {
        return false;
    }
    
    // If logged in and can post threads, full access
    if($mybb->user['uid'] > 0 && $fpermissions['canpostthreads'])
    {
        return true;
    }
    
    // If guest, return 'guest' - show form but with login message
    if($mybb->user['uid'] == 0)
    {
        return 'guest';
    }
    
    // Logged in but can't post threads in this forum
    return false;
}

/**
 * JSON response helper
 */
function json_response($data)
{
    global $mybb;

    header('Content-Type: application/json');
    echo json_encode($data);
    exit;
}

/**
 * Build thread custom fields for quick thread
 * Simplified version that generates HTML for custom fields
 */
function quickthread_build_custom_fields($fid, $thread)
{
    global $db, $mybb, $templates, $lang;

    $custom_fields_html = '';

    // Get custom thread fields for this forum
    $query = $db->simple_select('threadfields', '*', "(forums LIKE '%,{$fid},%' OR forums='-1') AND editable != '0'", array('order_by' => 'disporder'));
    while($threadfield = $db->fetch_array($query))
    {
        // Skip hidden or profile-only fields
        if($threadfield['editable'] == 0 || $threadfield['hidden'] == 1)
        {
            continue;
        }

        $tf_name = htmlspecialchars_uni($threadfield['name']);
        $tf_desc = htmlspecialchars_uni($threadfield['description']);
        $tf_type = $threadfield['type'];
        $tf_required = $threadfield['required'] == 1 ? ' *' : '';
        $tf_value = isset($thread['fid' . $threadfield['fid']]) ? $thread['fid' . $threadfield['fid']] : '';

        $field_html = '';

        // Generate field based on type
        switch($tf_type)
        {
            case 'text':
                $field_html = '<input type="text" name="fid' . $threadfield['fid'] . '" class="textbox" size="30" maxlength="250" value="' . htmlspecialchars_uni($tf_value) . '" />';
                break;
            case 'textarea':
                $field_html = '<textarea name="fid' . $threadfield['fid'] . '" class="textarea" rows="3" cols="40">' . htmlspecialchars_uni($tf_value) . '</textarea>';
                break;
            case 'select':
                $field_html = '<select name="fid' . $threadfield['fid'] . '" class="textbox">';
                $options = explode('\n', $threadfield['options']);
                foreach($options as $option)
                {
                    $option = trim($option);
                    $selected = ($tf_value == $option) ? ' selected="selected"' : '';
                    $field_html .= '<option value="' . htmlspecialchars_uni($option) . '"' . $selected . '>' . htmlspecialchars_uni($option) . '</option>';
                }
                $field_html .= '</select>';
                break;
            case 'multiselect':
                $field_html = '<select name="fid' . $threadfield['fid'] . '[]" class="textbox" multiple="multiple" size="3">';
                $options = explode('\n', $threadfield['options']);
                $selected_values = explode('\n', $tf_value);
                foreach($options as $option)
                {
                    $option = trim($option);
                    $selected = in_array($option, $selected_values) ? ' selected="selected"' : '';
                    $field_html .= '<option value="' . htmlspecialchars_uni($option) . '"' . $selected . '>' . htmlspecialchars_uni($option) . '</option>';
                }
                $field_html .= '</select>';
                break;
            case 'radio':
                $options = explode('\n', $threadfield['options']);
                foreach($options as $option)
                {
                    $option = trim($option);
                    $checked = ($tf_value == $option) ? ' checked="checked"' : '';
                    $field_html .= '<label class="qt-inline-label"><input type="radio" name="fid' . $threadfield['fid'] . '" value="' . htmlspecialchars_uni($option) . '"' . $checked . ' /> ' . htmlspecialchars_uni($option) . '</label> ';
                }
                break;
            case 'checkbox':
                $options = explode('\n', $threadfield['options']);
                $checked_values = explode('\n', $tf_value);
                foreach($options as $option)
                {
                    $option = trim($option);
                    $checked = in_array($option, $checked_values) ? ' checked="checked"' : '';
                    $field_html .= '<label class="qt-inline-label"><input type="checkbox" name="fid' . $threadfield['fid'] . '[]" value="' . htmlspecialchars_uni($option) . '"' . $checked . ' /> ' . htmlspecialchars_uni($option) . '</label> ';
                }
                break;
            default:
                $field_html = '<input type="text" name="fid' . $threadfield['fid'] . '" class="textbox" size="30" value="' . htmlspecialchars_uni($tf_value) . '" />';
        }

        if(!empty($field_html))
        {
            $custom_fields_html .= '<div class="qt-custom-field">';
            $custom_fields_html .= '<label>' . $tf_name . $tf_required . '</label>';
            if($tf_desc)
            {
                $custom_fields_html .= '<small class="qt-field-desc">' . $tf_desc . '</small>';
            }
            $custom_fields_html .= $field_html;
            $custom_fields_html .= '</div>';
        }
    }

    return $custom_fields_html;
}

/**
 * Build Quick Thread MyCode inserter (editor buttons)
 * Custom implementation for Quick Thread plugin
 */
function quickthread_build_mycode_inserter($bind, $id = null, $allow_mycode = 1)
{
    global $mybb, $lang;

    if($allow_mycode == 0)
    {
        return '';
    }

    // Build the MyCode buttons HTML
    $editor_id = $id ? $id : $bind;

    $codebuttons = "
    <div class='qt-editor-toolbar' id='qt_toolbar_{$editor_id}'>
        <div class='qt-editor-row'>
            <button type='button' class='qt-btn' onclick='qt_insertTag(\"{$editor_id}\", \"[b]\", \"[/b]\")' title='Bold'><strong>B</strong></button>
            <button type='button' class='qt-btn' onclick='qt_insertTag(\"{$editor_id}\", \"[i]\", \"[/i]\")' title='Italic'><em>I</em></button>
            <button type='button' class='qt-btn' onclick='qt_insertTag(\"{$editor_id}\", \"[u]\", \"[/u]\")' title='Underline'><u>U</u></button>
            <button type='button' class='qt-btn' onclick='qt_insertTag(\"{$editor_id}\", \"[s]\", \"[/s]\")' title='Strikethrough'><s>S</s></button>
            <span class='qt-separator'>|</span>
            <button type='button' class='qt-btn' onclick='qt_insertTag(\"{$editor_id}\", \"[url]\", \"[/url]\")' title='Link'>🔗</button>
            <button type='button' class='qt-btn' onclick='qt_insertTag(\"{$editor_id}\", \"[email]\", \"[/email]\")' title='Email'>✉️</button>
            <button type='button' class='qt-btn' onclick='qt_insertTag(\"{$editor_id}\", \"[img]\", \"[/img]\")' title='Image'>🖼️</button>
            <span class='qt-separator'>|</span>
            <button type='button' class='qt-btn' onclick='qt_insertTag(\"{$editor_id}\", \"[quote]\", \"[/quote]\")' title='Quote'>💬</button>
            <button type='button' class='qt-btn' onclick='qt_insertTag(\"{$editor_id}\", \"[code]\", \"[/code]\")' title='Code'><code>{</code></button>
        </div>
    </div>
    <script>
    function qt_insertTag(textareaId, openTag, closeTag) {
        var textarea = document.getElementById(textareaId);
        if(!textarea) return;

        var start = textarea.selectionStart;
        var end = textarea.selectionEnd;
        var value = textarea.value;
        var selected = value.substring(start, end);

        textarea.value = value.substring(0, start) + openTag + selected + closeTag + value.substring(end);
        textarea.focus();
        textarea.selectionStart = start + openTag.length + selected.length;
        textarea.selectionEnd = textarea.selectionStart;
    }
    </script>
    ";

    return $codebuttons;
}

/**
 * Get post icons HTML for Quick Thread
 */
function quickthread_get_post_icons()
{
    global $db, $mybb;

    // Get icons - without order_by to avoid column errors
    $query = $db->simple_select('icons', '*', '', array('limit' => 50));
    $num_icons = $db->num_rows($query);

    if($num_icons == 0)
    {
        return '';
    }

    $posticons = '';
    $i = 0;

    while($icon = $db->fetch_array($query))
    {
        $icon['path'] = str_replace('{theme}', $mybb->theme['imgdir'], $icon['path']);
        $icon['path'] = htmlspecialchars_uni($mybb->settings['bburl'] . '/' . $icon['path']);
        $icon['name'] = htmlspecialchars_uni($icon['name']);

        $checked = '';
        if($i == 0)
        {
            $checked = 'checked="checked"';
        }

        $posticons .= "
        <label class='qt-posticon-label'>
            <input type='radio' name='icon' value='{$icon['path']}' {$checked} />
            <img src='{$icon['path']}' alt='{$icon['name']}' title='{$icon['name']}' />
        </label>";

        $i++;
    }

    return $posticons;
}