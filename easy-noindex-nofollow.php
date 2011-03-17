<?php
/**
 * Plugin Name: Easy Noindex And Nofollow
 * Short Name: easy_noindex_nofollow
 * Description: Easily add Noindex and Nofollow to any post.
 * Author: Ivan Kristianto
 * Version: 1.0
 * Requires at least: 2.7
 * Tested up to: 3.1
 * Tags: noindex, nofollow, seo, google panda
 * Contributors: Ivan Kristianto
 * WordPress URI: http://wordpress.org/extend/plugins/easy-noindex-and-nofollow/
 * Author URI: http://www.ivankristianto.com/
 * Donate URI: http://www.ivankristianto.com/portfolio/
 * Plugin URI: http://www.ivankristianto.com/web-development/programming/easy-noindex-and-nofollow-wordpress-plugin/1797/
 *
 *
 * easy-noindex-and-nofollow - Easy Noindex And Nofollow
 * Copyright (C) 2011	IvanKristianto.com
 *
 * This program is free software - you can redistribute it and/or modify
 * it under the terms of the GNU General Public License as published by
 * the Free Software Foundation, either version 3 of the License, or
 * (at your option) any later version.
 *
 * This program is distributed in the hope that it will be useful,
 * but WITHOUT ANY WARRANTY; without even the implied warranty of
 * MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.	See the
 * GNU General Public License for more details.
 *
 * You should have received a copy of the GNU General Public License
 * along with this program.	If not, see <http://www.gnu.org/licenses/>.
 */

// exit if add_action or plugins_url functions do not exist
if (!function_exists('add_action') || !function_exists('plugins_url')) exit;

// function to replace wp_die if it doesn't exist
if (!function_exists('wp_die')) : function wp_die ($message = 'wp_die') { die($message); } endif;

// define some definitions if they already are not
!defined('WP_CONTENT_DIR') && define('WP_CONTENT_DIR', ABSPATH . 'wp-content');
!defined('WP_PLUGIN_DIR') && define('WP_PLUGIN_DIR', WP_CONTENT_DIR . '/plugins');
!defined('WP_CONTENT_URL') && define('WP_CONTENT_URL', get_option('siteurl') . '/wp-content');
!defined('WP_PLUGIN_URL') && define('WP_PLUGIN_URL', WP_CONTENT_URL . '/plugins');

function easy_noindex_nofollow_init() {
	if ( get_option( 'easy_noindex_nofollow_disable' ) ) {
		remove_action( 'wp_head', 'easy_noindex_nofollow_add_header', 1 );
	}
}

function easy_noindex_nofollow_add_header(){
	global $wp_query;
	if(is_single()){
		$post = $wp_query->get_queried_object();
		$easy_noindex_nofollow_index = get_post_meta( $post->ID, 'easy_noindex_nofollow_index', true );
		$easy_noindex_nofollow_follow = get_post_meta( $post->ID, 'easy_noindex_nofollow_follow', true );
		if(!empty( $easy_noindex_nofollow_index ) || !empty( $easy_noindex_nofollow_follow )){
			
			if ( !empty( $easy_noindex_nofollow_index ) && $easy_noindex_nofollow_index == "1" ){
				$noindex = "noindex";
			}
			else{
				$noindex = "index";
			}
			
			if ( !empty( $easy_noindex_nofollow_follow ) && $easy_noindex_nofollow_follow == "1" ){
				$nofollow = "nofollow";
			}
			else{
				$nofollow = "follow";
			}
			
			echo sprintf("<!--Add by easy-noindex-nofollow--><meta name=\"robots\" content=\" %s, %s\"/>\n", $noindex, $nofollow);
		}
	}
}

function easy_noindex_nofollow_add_meta_box() {
	add_meta_box( 'easy_noindex_nofollow_meta', __( 'Easy Noindex Nofollow', 'easy_noindex_nofollow' ), 'easy_noindex_nofollow_meta_box_content', 'page', 'advanced', 'high' );
	add_meta_box( 'easy_noindex_nofollow_meta', __( 'Easy Noindex Nofollow', 'easy_noindex_nofollow' ), 'easy_noindex_nofollow_meta_box_content', 'post', 'advanced', 'high' );
}

function easy_noindex_nofollow_meta_box_content( $post ) {
	$easy_noindex_nofollow_index = get_post_meta( $post->ID, 'easy_noindex_nofollow_index', true );
	$easy_noindex_nofollow_follow = get_post_meta( $post->ID, 'easy_noindex_nofollow_follow', true );

	if ( !empty( $easy_noindex_nofollow_index ) && $easy_noindex_nofollow_index == "1" )
		$easy_noindex_nofollow_index = ' checked="checked"';
	else
		$easy_noindex_nofollow_index = '';
	
	if ( !empty( $easy_noindex_nofollow_follow ) && $easy_noindex_nofollow_follow == "1" )
		$easy_noindex_nofollow_follow = ' checked="checked"';
	else
		$easy_noindex_nofollow_follow = '';

	echo '<p><label for="easy_noindex_nofollow_index"><input name="easy_noindex_nofollow_index" id="easy_noindex_nofollow_index"' . $easy_noindex_nofollow_index . ' type="checkbox"> ' . __( 'Add noindex.', 'easy_noindex_nofollow' ) . '</label></p>';
	
	echo '<p><label for="easy_noindex_nofollow_follow"><input name="easy_noindex_nofollow_follow" id="easy_noindex_nofollow_follow"' . $easy_noindex_nofollow_follow . ' type="checkbox"> ' . __( 'Add nofollow.', 'easy_noindex_nofollow' ) . '</label></p>';
}

function easy_noindex_nofollow_meta_box_save( $post_id ) {
	if ( defined('DOING_AUTOSAVE') && DOING_AUTOSAVE )
		return $post_id;
	// Record easy_noindex_nofollow disable
	if ( 'post' == $_POST['post_type'] || 'page' == $_POST['post_type'] ) {
		if ( current_user_can( 'edit_post', $post_id ) ) {
			if ( isset( $_POST['easy_noindex_nofollow_index'] ) )
				update_post_meta( $post_id, 'easy_noindex_nofollow_index', 1 );
			else
				update_post_meta( $post_id, 'easy_noindex_nofollow_index', 0 );
			
			if ( isset( $_POST['easy_noindex_nofollow_follow'] ) )
				update_post_meta( $post_id, 'easy_noindex_nofollow_follow', 1 );
			else
				update_post_meta( $post_id, 'easy_noindex_nofollow_follow', 0 );
		}
	}

  return $post_id;
}

// Only run if PHP5
if ( version_compare( phpversion(), '5.0', '>=' ) ) {
	add_action( 'init', 'easy_noindex_nofollow_init' );
	add_action( 'wp_head', 'easy_noindex_nofollow_add_header', 1 );
	add_action( 'admin_init', 'easy_noindex_nofollow_add_meta_box' );
	add_action( 'save_post', 'easy_noindex_nofollow_meta_box_save' );
}

?>