<?php
/**
 * Plugin Activation Handler
 * 
 * Handles plugin activation events including custom table creation.
 *
 * @package Looksfam\Core
 */

namespace Looksfam\Core;

class Activator {
    
    /**
     * Activate the plugin
     */
    public static function activate() {
        // Create custom tables
        Database::create_tables();
        
        // Add default options if needed
        if ( ! get_option( 'looksfam_version' ) ) {
            add_option( 'looksfam_version', LOOKSFAM_VERSION );
        }
        
        // Flush rewrite rules
        flush_rewrite_rules();
    }
}
