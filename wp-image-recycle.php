<?php
/*
Plugin Name: ImageRecycle pdf & image compression
Plugin URI: https://www.imagerecycle.com/cms/wordpress
Description: ImageRecycle is an automatic image & PDF optimizer that save up to 80% of the media weight without loosing quality. Speed up your website, keep visitors on board!
Author: ImageRecycle
Version: 1.1.2
Author URI: https://www.imagerecycle.com
Licence : GNU General Public License version 2 or later; http://www.gnu.org/licenses/gpl-2.0.html
Copyright : Copyright (C) 2014 Imagerecycle (https://www.imagerecycle.com). All rights reserved.
*/

// Prohibit direct script loading
defined( 'ABSPATH' ) || die( 'No direct script access allowed!' );

if (!defined('WPIO_IMAGERECYCLE'))
    define('WPIO_IMAGERECYCLE', plugin_dir_path(__FILE__));

 define('WPIO_IMAGERECYCLE_VERSION', '1.1.0');
require_once( WPIO_IMAGERECYCLE . 'class/class-image-otimizer.php' );
register_activation_hook( __FILE__, array('wpImageRecycle','install') );
register_uninstall_hook( __FILE__, array('wpImageRecycle','uninstall') );

new wpImageRecycle();
