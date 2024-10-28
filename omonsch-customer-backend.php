<?php
/*
Plugin Name: Oliver Monschau - Customer Backend
Description: Customizes the WordPress admin area for customers.
Version: 0.3.0
Author: Oliver Monschau, Michael Amting
*/

defined('ABSPATH') or die('No script kiddies please!');

// Automatically update plugin from GitHub
require_once plugin_dir_path(__FILE__) . 'github-updater.php';
new WP_GitHub_Updater(plugin_basename(__FILE__));

// Include admin customizations
require_once plugin_dir_path(__FILE__) . 'admin-customizations.php';

// Add new "Kunde" role with custom capabilities
function add_customer_role() {
    // Rolle "Kunde" hinzufügen, falls sie noch nicht existiert
    add_role('kunde', 'Kunde', [
        'read' => true,
        'edit_posts' => true,
        'delete_posts' => false,
        'upload_files' => true
    ]);

    // Fehlende Berechtigungen hinzufügen
    $role = get_role('kunde');
    if ($role) {
        $role->add_cap('manage_options');      // Zugriff auf allgemeine Einstellungen
        $role->add_cap('edit_theme_options');  // Zugriff auf Customizer und Design
    }
}
register_activation_hook(__FILE__, 'add_customer_role');

// Remove "Kunde" role on plugin deactivation
function remove_customer_role() {
    remove_role('kunde');
}
register_deactivation_hook(__FILE__, 'remove_customer_role');
