<?php
/*
	Meteonet - the backend that manages data from weather stations
	used by meteogargano.org
	
	Copyright (C) 2013-2021 Filippo Gurgoglione (ziofil@gmail.com)
	All rights reserved.
	
	Permission is hereby granted, free of charge, to any person obtaining a 
	copy of this software and associated documentation files (the "Software"), 
	to deal in the Software without restriction, including without limitation 
	the rights to use, copy, modify, merge, publish, distribute, sublicense, 
	and/or sell copies of the Software, and to permit persons to whom the 
	Software is furnished to do so, subject to the following conditions:
	
	The above copyright notice and this permission notice shall be included 
	in all copies or substantial portions of the Software.
	
	THE SOFTWARE IS PROVIDED "AS IS", WITHOUT WARRANTY OF ANY KIND, EXPRESS 
	OR IMPLIED, INCLUDING BUT NOT LIMITED TO THE WARRANTIES OF MERCHANTABILITY, 
	FITNESS FOR A PARTICULAR PURPOSE AND NONINFRINGEMENT. IN NO EVENT SHALL THE 
	AUTHORS OR COPYRIGHT HOLDERS BE LIABLE FOR ANY CLAIM, DAMAGES OR OTHER 
	LIABILITY, WHETHER IN AN ACTION OF CONTRACT, TORT OR OTHERWISE, ARISING FROM, 
	OUT OF OR IN CONNECTION WITH THE SOFTWARE OR THE USE OR OTHER DEALINGS IN THE 
	SOFTWARE.
*/
/*
Plugin Name: MeteoNET
Plugin URI: http://www.meteogargano.org/
Description: A Meteorological network plugin
Author: ziofil
Author URI: http://www.meteogargano.org/
Version: 0.0.1
*/

/*function themeslug_enqueue_style() {
	wp_enqueue_style( 'core', 'style.css', false ); 
}

function themeslug_enqueue_script() {
	wp_enqueue_script( 'my-js', 'filename.js', false );
}

add_action( 'wp_enqueue_scripts', 'themeslug_enqueue_style' );


TODO
*/


//Stop direct call
if(preg_match('#' . basename(__FILE__) . '#', $_SERVER['PHP_SELF'])) { die('You are not allowed to call this page directly.');}

if (!class_exists('meteonet')) {
	include_once (dirname(__FILE__) . '/config.php');
	include_once (dirname(__FILE__) . '/utils.php');
	include_once (dirname(__FILE__) . '/core.php');
	include_once (dirname(__FILE__) . '/router.php');
	include_once (dirname(__FILE__) . '/interface.php');

	class meteonet {
		var $version = '0.1';
		var $minimum_WP = '3.2';

		var $net;
		var $db_config;
		var $db;

		var $action;
		var $plugin_page;

		function meteonet() {
			$this->plugin_page = $plugin_page;
			add_action( 'wp_enqueue_scripts', array(&$this, "enqueue_script") );
		}

		public function enqueue_script() {

			wp_enqueue_script( 'meteonet-js-1', '/wp-content/plugins/meteonet/jquery-1.8.2.min.js', false );
			wp_enqueue_script( 'meteonet-js-2', '/wp-content/plugins/meteonet/jquery.timer.js', false );
			wp_enqueue_script( 'meteonet-js-3', '/wp-content/plugins/meteonet/jquery-ui.js', false );
			wp_enqueue_script( 'meteonet-js-4', '/wp-content/plugins/meteonet/jquery.corner.js', false );
			wp_enqueue_script( 'meteonet-js-5', '/wp-content/plugins/meteonet/flexigrid.pack.js', false );
			wp_enqueue_script( 'meteonet-js', '/wp-content/plugins/meteonet/meteonet.js',  '', '20131023' );
			wp_enqueue_style( 'meteonet-css-1', '/wp-content/plugins/meteonet/jquery-ui.css', false );
			wp_enqueue_style( 'meteonet-css2', '/wp-content/plugins/meteonet/flexigrid.pack.css', false );
			wp_enqueue_style( 'meteonet-css', '/wp-content/plugins/meteonet/meteonet.css', false );

		}

	}

	global $meteonet;
	$meteonet = new meteonet();
}
