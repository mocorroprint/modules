<?php
/**
 * Plugin Deactivation Handler
 * 
 * Handles plugin deactivation events.
 *
 * @package Looksfam\Core
 */

namespace Looksfam\Core;

class Deactivator {
    
    /**
     * Deactivate the plugin
     */
    public static function deactivate() {
        // Flush rewrite rules
        flush_rewrite_rules();
        
        // Clear any scheduled cron events
        wp_clear_scheduled_hook( 'looksfam_daily_cleanup' );
    }
}
