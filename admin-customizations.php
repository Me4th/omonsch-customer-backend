<?php

// Remove unwanted dashboard widgets
function remove_dashboard_widgets() {
    remove_meta_box('dashboard_quick_press', 'dashboard', 'side');      // Remove "Quick Draft"
    remove_meta_box('dashboard_primary', 'dashboard', 'side');          // Remove "WordPress News"
    remove_meta_box('dashboard_site_health', 'dashboard', 'normal');    // Remove "Site Health"
    remove_meta_box('dashboard_right_now', 'dashboard', 'normal');      // Remove "At a Glance"
}
add_action('wp_dashboard_setup', 'remove_dashboard_widgets');

// Remove WordPress logo from admin bar
function remove_wp_logo_from_admin_bar() {
    global $wp_admin_bar;
    $wp_admin_bar->remove_node('wp-logo');
}
add_action('wp_before_admin_bar_render', 'remove_wp_logo_from_admin_bar', 0);

// Hide specific menu items except for Appearance and Customizer
function customize_admin_menu() {
    global $submenu;

    // Remove specific submenu items under Appearance and other sections
    remove_submenu_page('themes.php', 'theme-editor.php');              // Hide "Appearance" -> "Theme Editor"
    remove_submenu_page('plugins.php', 'plugin-editor.php');            // Hide "Plugins" -> "Plugin Editor"
    remove_submenu_page('options-general.php', 'options-writing.php');  // Hide "Settings" -> "Writing"
    remove_submenu_page('options-general.php', 'options-reading.php');  // Hide "Settings" -> "Reading"
    remove_submenu_page('options-general.php', 'options-discussion.php'); // Hide "Settings" -> "Discussion"
    remove_submenu_page('options-general.php', 'options-media.php');    // Hide "Settings" -> "Media"
    remove_submenu_page('options-general.php', 'options-permalink.php'); // Hide "Settings" -> "Permalinks"
}
add_action('admin_menu', 'customize_admin_menu', 999);

// Ensure "Appearance" menu and Pages are accessible for "Kunde" role
function ensure_menu_access_for_customer() {
    $role = get_role('kunde');
    if ($role) {
        $role->add_cap('edit_theme_options');
        $role->add_cap('edit_posts');
        $role->add_cap('delete_posts');
        $role->add_cap('delete_others_posts');
        $role->add_cap('edit_pages');
        $role->add_cap('delete_pages');
        $role->add_cap('delete_others_pages');
        $role->add_cap('edit_published_pages');
        $role->add_cap('delete_private_pages');
        $role->add_cap('delete_private_posts');
        $role->add_cap('edit_private_pages');
        $role->add_cap('edit_private_posts');
        $role->add_cap('publish_posts');
        $role->add_cap('publish_pages');
        $role->add_cap('edit_others_pages');
        $role->add_cap('edit_others_posts');
        $role->add_cap('edit_published_posts');
        $role->add_cap('upload_files');
    }
}
add_action('admin_init', 'ensure_menu_access_for_customer');

// Remove WordPress version from footer
function remove_wp_version_footer() {
    remove_filter('update_footer', 'core_update_footer'); // WordPress version
}
add_action('admin_menu', 'remove_wp_version_footer');

// Remove "Thank you for using WordPress" text
function customize_footer_text() {
    return '';
}
add_filter('admin_footer_text', 'customize_footer_text');

// Adjustments to fix ALT text and Beaver Builder issues
function allow_alt_and_bb_editing() {
    if (is_admin()) {
        echo '<style>
            input#siteurl, input#home, input#new_admin_email { background-color: #ddd; pointer-events: none; }
        </style>';
    }
}
add_action('admin_head', 'allow_alt_and_bb_editing');

// Enable editing for media library fields
function enable_media_library_editing() {
    remove_action('admin_head', 'disable_settings_fields');
}
add_action('admin_head', 'enable_media_library_editing');

// Add custom banner from CDN
function add_custom_admin_banner() {
    $banner_html = file_get_contents('https://cdn.omonschau.de/files/wp-backend-banner.txt');

    echo '
    <script>
    document.addEventListener("DOMContentLoaded", function() {
        var wpbody = document.getElementById("wpbody-content");
        var customBanner = document.createElement("div");
        customBanner.innerHTML = `' . addslashes($banner_html) . '`;
        wpbody.prepend(customBanner);
    });
    </script>';
}
add_action('admin_notices', 'add_custom_admin_banner', 20);

function show_update_banner() {
    $update_plugins = get_site_transient('update_plugins');
    $update_themes = get_site_transient('update_themes');
    $update_core = get_site_transient('update_core');

    $plugin_updates_available = !empty($update_plugins->response);
    $theme_updates_available = !empty($update_themes->response);

    $core_update_available = false;
    if (!empty($update_core->updates)) {
        foreach ($update_core->updates as $core_update) {
            if ($core_update->response == 'upgrade' && empty($core_update->locale)) {
                $core_update_available = true;
                break;
            }
        }
    }

    if ($plugin_updates_available || $theme_updates_available || $core_update_available) {
        $last_update = get_option('last_update_time');
        $last_update_display = $last_update ? date('d.m.Y \u\m H:i', $last_update) : 'unbekannt';

        echo '
        <script>
        if (!localStorage.getItem("updateBannerClosed")) {
            document.addEventListener("DOMContentLoaded", function() {
                var wpbody = document.getElementById("wpbody-content");
                var banner = document.createElement("div");
                banner.id = "update-banner";
                banner.innerHTML = `
                    <span id="close-banner">&times;</span>
                    <p>Es gibt ausstehende Updates für Ihre Website. Die letzte vollständige Aktualisierung hat am ' . $last_update_display . ' stattgefunden.</p>
                    <style>
                        #update-banner {
                          padding: 2px 20px;
                          background-color: #f44336;
                          color: white;
                          margin-bottom: 15px;
                          margin-right: 20px;
                          position: relative;
                        }
                        #close-banner {
                            margin-left: 15px;
                            color: white;
                            font-weight: bold;
                            float: right;
                            font-size: 22px;
                            line-height: 20px;
                            cursor: pointer;
                            transition: 0.3s;
                            position: absolute;
                            top: 50%;
                            transform: translateY(-58%);
                            right: 20px;
                        }
                        #close-banner:hover {
                          color: black;
                        }
                    </style>
                `;
                wpbody.prepend(banner);

                document.getElementById("close-banner").addEventListener("click", function() {
                    banner.style.display = "none";
                    localStorage.setItem("updateBannerClosed", "true");
                });
            });
        }
        </script>';
    }
}
add_action('admin_notices', 'show_update_banner', 10);

// Update the last update time when updates are done
function set_last_update_time() {
    update_option('last_update_time', time());
}
add_action('upgrader_process_complete', 'set_last_update_time', 10, 2);

// Custom Login Logo
// Set "https://cdn.omonschau.de/images/logos/wp_login_logo_omonschau.png" as the custom login logo
function custom_login_logo() {
    echo '<style>
        #login h1 a {
            background-image: url("https://cdn.omonschau.de/images/logos/wp_login_logo_omonschau.png");
            background-size: contain;
            width: 100%;
            height: 100px;
        }
    </style>';
}
add_action('login_head', 'custom_login_logo');

?>
