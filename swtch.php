<?php
/**
 * Plugin Name: SWTCH
 * Plugin URI:
 * Description: Static WordPress To Common HTML
 * Version: 0.2.0
 * Author: Jeremy Heminger
 * License: GPL-2.0-or-later
 * Text Domain: swtch
 */

if ( ! defined( 'ABSPATH' ) ) {
    exit;
}

define( 'SWTCH_PLUGIN_FILE', __FILE__ );
define( 'SWTCH_PLUGIN_DIR', plugin_dir_path( __FILE__ ) );

require_once SWTCH_PLUGIN_DIR . 'includes/settings.php';
require_once SWTCH_PLUGIN_DIR . 'includes/ignore-rules.php';
require_once SWTCH_PLUGIN_DIR . 'includes/assets.php';
require_once SWTCH_PLUGIN_DIR . 'includes/export.php';
require_once SWTCH_PLUGIN_DIR . 'includes/sitemap.php';
require_once SWTCH_PLUGIN_DIR . 'includes/redirects.php';
require_once SWTCH_PLUGIN_DIR . 'includes/deployment.php';
require_once SWTCH_PLUGIN_DIR . 'includes/ajax.php';
require_once SWTCH_PLUGIN_DIR . 'includes/admin.php';
