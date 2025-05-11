<?php

/**
 * @package DrawIt - Draw.io Intergation
 * @version 1.0.1
 */
/*
Plugin Name:    DrawIt - Draw.io Intergation
Plugin URI:     Loc Hoang
Description:    Draw and edit flow charts, diagrams, images and more while editing a post.
Version:        1.0.1
Author:         Loc Hoang
Author URI:     Loc Hoang
License:        GPL3 or later
License URI:    https://www.gnu.org/licenses/gpl-3.0.html


    Copyright 2025 Loc Hoang

    This program is free software; you can redistribute it and/or modify
    it under the terms of the GNU General Public License, version 3, as 
    published by the Free Software Foundation.

    This program is distributed in the hope that it will be useful,
    but WITHOUT ANY WARRANTY; without even the implied warranty of
    MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
    GNU General Public License for more details.

    You should have received a copy of the GNU General Public License
    along with this program; if not, write to the Free Software
    Foundation, Inc., 51 Franklin St, Fifth Floor, Boston, MA  02110-1301  USA

*/

defined('ABSPATH') or die('No script kiddies please!');

// Define plugin constants
define('DRAWIT_VERSION', '1.0.1');
define('DRAWIT_PLUGIN_DIR', plugin_dir_path(__FILE__));
define('DRAWIT_PLUGIN_URL', plugin_dir_url(__FILE__));
define('DRAWIT_PLUGIN_SLUG', 'drawit');
define('DRAWIT_PLUGIN_LABEL', 'DrawIt');

// Include required files
require_once DRAWIT_PLUGIN_DIR . 'includes/class-drawit-config.php';
require_once DRAWIT_PLUGIN_DIR . 'includes/class-drawit.php';
require_once DRAWIT_PLUGIN_DIR . 'includes/class-drawit-admin.php';
require_once DRAWIT_PLUGIN_DIR . 'includes/class-drawit-media.php';
require_once DRAWIT_PLUGIN_DIR . 'includes/class-drawit-ajax.php';

// Initialize the plugin
function drawit_init()
{
    $plugin = new DrawIt();
    $plugin->run();
}

// Start the plugin
drawit_init();
