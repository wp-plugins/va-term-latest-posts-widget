<?php
// Exit if accessed directly
if ( !defined( 'ABSPATH' ) ) {
	exit;
}
/*
Plugin Name: VA Term Latest Posts Widget
Plugin URI: http://visualive.jp/
Description: This plugin adds a widget to display the new post list belonging to the specified term.
Author: KUCKLU
Version: 1.0.1
Author URI: http://visualive.jp/
Text Domain: va-term-latest-posts
Domain Path: /langs
License: GNU General Public License v2 or later
License URI: http://www.gnu.org/licenses/gpl-2.0.html

VisuAlive WordPress Plugin, Copyright (C) 2015 VisuAlive and KUCKLU.

This program is free software: you can redistribute it and/or modify
it under the terms of the GNU General Public License as published by
the Free Software Foundation, either version 2 of the License, or
(at your option) any later version.

This program is distributed in the hope that it will be useful,
but WITHOUT ANY WARRANTY; without even the implied warranty of
MERCHANTABILITY or FITNESS FOR A PARTICULAR PURPOSE.  See the
GNU General Public License for more details.

You should have received a copy of the GNU General Public License
along with this program.  If not, see <http://www.gnu.org/licenses/>.
*/
/**
 * VA Term Latest Posts.
 *
 * @package    WordPress
 * @subpackage VA Term Latest Posts
 * @author     KUCKLU <kuck1u@visualive.jp>
 * @copyright  Copyright (c) 2015 KUCKLU, VisuAlive.
 * @license    GPLv2 http://opensource.org/licenses/gpl-2.0.php
 * @link       http://visualive.jp/
 */
$va_term_latest_posts_widget_plugin_data = get_file_data( __FILE__, array( 'ver' => 'Version', 'langs' => 'Domain Path', 'mo' => 'Text Domain' ) );
define( 'VA_TERM_LATEST_POSTS_WIDGET_PLUGIN_URL', plugin_dir_url( __FILE__ ) );
define( 'VA_TERM_LATEST_POSTS_WIDGET_PLUGIN_PATH', plugin_dir_path( __FILE__ ) );
define( 'VA_TERM_LATEST_POSTS_WIDGET_DOMAIN', dirname( plugin_basename( __FILE__ ) ) );
define( 'VA_TERM_LATEST_POSTS_WIDGET_VERSION', $va_term_latest_posts_widget_plugin_data['ver'] );
define( 'VA_TERM_LATEST_POSTS_WIDGET_TEXTDOMAIN', $va_term_latest_posts_widget_plugin_data['mo'] );


/**
 * Register all of the default WordPress widgets on startup.
 * Calls 'widgets_init' action after all of the WordPress widgets have been
 * registered.
 */
function vatlpw_widgets_init() {
	if ( !is_blog_installed() )
		return;

	register_widget( 'VA_TERM_LATEST_POSTS_WIDGET' );
}
add_action( 'widgets_init', 'vatlpw_widgets_init' );

class VA_TERM_LATEST_POSTS_WIDGET extends WP_Widget {

	function __construct() {
		parent::__construct( VA_TERM_LATEST_POSTS_WIDGET_TEXTDOMAIN, __( 'VA Term Latest Posts', VA_TERM_LATEST_POSTS_WIDGET_TEXTDOMAIN ), array(
			'classname'   => VA_TERM_LATEST_POSTS_WIDGET_TEXTDOMAIN,
			'description' => __( 'Display the new post list belonging to the specified term.', VA_TERM_LATEST_POSTS_WIDGET_TEXTDOMAIN ),
		) );
	}

	function widget( $args, $instance ) {
		$tax_query          = array();
		$instance           = self::default_args( $instance );
		$instance['title']  = apply_filters( 'widget_title', empty( $instance['title'] ) ? '' : $instance['title'], $instance, $this->id_base );
		$builtin_post_types = get_post_types( array( 'public' => true, 'hierarchical' => false, '_builtin' => true ) );
		$custom_post_types  = get_post_types( array( 'public' => true, 'hierarchical' => false, '_builtin' => false ) );
		$post_types         = wp_parse_args( $custom_post_types, $builtin_post_types );

		unset( $post_types['attachment'] );
		extract( $args );
		extract( $instance );

		$output             = get_transient( $widget_id );

		if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) {
			delete_transient( $widget_id );
			$output = false;
		}

		if ( !$output ) {
			foreach ( $term_ids as $taxonomy => $ids ) {
				$tax_query[] = array(
					'taxonomy'         => $taxonomy,
					'terms'            => $ids,
					'include_children' => false,
					'field'            => 'term_id',
					'operator'         => 'IN'
				);
			}

			$tax_query['relation'] = 'OR';
			$query                 = array(
				'tax_query'      => $tax_query,
				'post_type'      => $post_types,
				'posts_per_page' => $posts_per_page,
			);
			$posts                 = get_posts( apply_filters( 'va_term_latest_posts_query_args', $query, $term_ids, $tax_query, $post_types, $posts_per_page ) );

			$output = $before_widget;

			if ( $title ) {
				$output .= $before_title . $title . $after_title;
			}

			$output .= '<ul class="post_list">';

			foreach ( $posts as $post ) {
				setup_postdata( $post );

				if ( has_post_thumbnail( $post->ID ) ) {
					$thumbnail_id  = get_post_thumbnail_id( $post->ID );
					$thumbnail_url = wp_get_attachment_image_src( (int) $thumbnail_id, $thumbnail_size )[0];
				} else {
					$thumbnail_url = false;
				}

				$output .= sprintf( '<li class="post_list_items"><a class="post_list_items_anchor" href="%s">', esc_url( get_permalink( $post->ID ) ) );

				if ( false != $thumbnail_url && $show_thumbnail ) {
					$output .= sprintf( '<div class="post_list_items_thumbnail"><img src="%s" alt="%s"></div>', esc_url( $thumbnail_url ), esc_attr( apply_filters( 'the_title', $post->post_title ) ) );
				}

				$output .= '<div class="post_list_items_meta">';
				$output .= sprintf( '<span class="post_list_items_meta_title">%s</span>', esc_attr( apply_filters( 'the_title', $post->post_title ) ) );

				if ( true == $show_time ) {
					$output .= sprintf( '<time class="post_list_items_meta_date" datetime="%s">%s</time>', esc_attr( get_the_date( 'c' ) ), get_the_date() );
				}

				$output .= '</div></a></li>';
			}
			wp_reset_postdata();

			$output .= '</ul>' . $after_widget;

			set_transient( $widget_id, $output, (int)$cache_time * HOUR_IN_SECONDS );
		}

		echo $output;
	}

	function form( $instance ) {
		$instance = self::default_args( $instance );
		$h5_css   = "font-size: 15px; margin: 1em 0 0;";

		extract( $instance );

		printf( '<h5 style="%s">%s:</h5>', $h5_css, __( 'Title', VA_TERM_LATEST_POSTS_WIDGET_TEXTDOMAIN ) );
		printf( '<p><label for="%1$s"><input class="widefat" id="%1$s" type="text" name="%2$s" value="%3$s"></label></p>', $this->get_field_id( 'title' ), $this->get_field_name( 'title' ), wp_strip_all_tags( $title ) );

		printf( '<h5 style="%s">%s:</h5>', $h5_css, __( 'Select terms', VA_TERM_LATEST_POSTS_WIDGET_TEXTDOMAIN ) );
		self::the_hierarchical_taxonomy_term_list( $term_ids );

		printf( '<h5 style="%s">%s:</h5>', $h5_css, __( 'Thumbnail', VA_TERM_LATEST_POSTS_WIDGET_TEXTDOMAIN ) );
		?>
		<p><label><input type="checkbox" name="<?php echo $this->get_field_name( 'show_thumbnail' ); ?>" value="1"<?php checked( (int)$show_thumbnail, 1 ); ?>> <?php _e( 'Show post thumbnail.', VA_TERM_LATEST_POSTS_WIDGET_TEXTDOMAIN ); ?></label></p>
		<?php
		self::the_image_sizes_select( $thumbnail_size );

		printf( '<h5 style="%s">%s:</h5>', $h5_css, __( 'Published datetime', VA_TERM_LATEST_POSTS_WIDGET_TEXTDOMAIN ) );
		?>
		<p><label><input type="checkbox" name="<?php echo $this->get_field_name( 'show_time' ); ?>" value="1"<?php checked( (int)$show_time, 1 ); ?>> <?php _e( 'Show published datetime.', VA_TERM_LATEST_POSTS_WIDGET_TEXTDOMAIN ); ?></label></p>
		<?php

		printf( '<h5 style="%s">%s:</h5>', $h5_css, __( 'Number of the posts to display', VA_TERM_LATEST_POSTS_WIDGET_TEXTDOMAIN ) );
		printf( '<p><label for="%1$s"><input id="%1$s" type="text" name="%2$s" value="%3$d" size="3">%4$s</label></p>', $this->get_field_id( 'posts_per_page' ), $this->get_field_name( 'posts_per_page' ), (int)$posts_per_page, __( ' post(s)', VA_TERM_LATEST_POSTS_WIDGET_TEXTDOMAIN ) );

		printf( '<h5 style="%s">%s:</h5>', $h5_css, __( 'Effective time of the cache', VA_TERM_LATEST_POSTS_WIDGET_TEXTDOMAIN ) );
		printf( '<p><label for="%1$s"><input id="%1$s" type="text" name="%2$s" value="%3$d" size="3">%4$s</label></p>', $this->get_field_id( 'cache_time' ), $this->get_field_name( 'cache_time' ), $cache_time, __( ' hour(s)', VA_TERM_LATEST_POSTS_WIDGET_TEXTDOMAIN ) );
	}

	function update( $new_instance, $old_instance ) {
		$sizes                      = self::get_image_sizes( true );
		$instance                   = $old_instance;
		$new_instance               = self::default_args( $new_instance );
		$instance['title']          = strip_tags( $new_instance['title'] );
		$instance['term_ids']       = is_array( $new_instance['term_ids'] ) ? $new_instance['term_ids'] : array();
		$instance['thumbnail_size'] = in_array( $new_instance['thumbnail_size'], $sizes ) ? $new_instance['thumbnail_size'] : 'thumbnail';
		$instance['show_thumbnail'] = (int)$new_instance['show_thumbnail'] === 1 ? true : false;
		$instance['show_time']      = (int)$new_instance['show_time'] === 1 ? true : false;
		$instance['posts_per_page'] = (int)$new_instance['posts_per_page'] === -1 | (int)$new_instance['posts_per_page'] > 0 ? (int)$new_instance[ 'posts_per_page' ] : 5;
		$instance['cache_time']     = (int)$new_instance['cache_time'] >= 1 ? (int)$new_instance['cache_time'] : 12;

		return $instance;
	}

	/**
	 * List available image sizes with width and height following
	 *
	 * @link  http://codex.wordpress.org/Function_Reference/get_intermediate_image_sizes
	 * @param string $size
	 * @return array|bool
	 */
	private function get_image_sizes( $name_list = false, $size = '' ) {
		global $_wp_additional_image_sizes;

		$sizes = array();
		$get_intermediate_image_sizes = get_intermediate_image_sizes();

		// Create the full array with sizes and crop info
		foreach( $get_intermediate_image_sizes as $_size ) :
			$name = ucwords( str_replace( '-', ' ', $_size ) );

			if ( in_array( $_size, array( 'thumbnail', 'medium', 'large' ) ) ) {
				$sizes[ $_size ]['name']   = $name;
				$sizes[ $_size ]['width']  = get_option( $_size . '_size_w' );
				$sizes[ $_size ]['height'] = get_option( $_size . '_size_h' );
				$sizes[ $_size ]['crop']   = (bool)get_option( $_size . '_crop' );
			} elseif ( isset( $_wp_additional_image_sizes[ $_size ] ) ) {
				$sizes[ $_size ] = array(
					'name'   => $name,
					'width'  => $_wp_additional_image_sizes[ $_size ]['width'],
					'height' => $_wp_additional_image_sizes[ $_size ]['height'],
					'crop'   => $_wp_additional_image_sizes[ $_size ]['crop']
				);
			}
		endforeach;

		// Get only 1 size if found
		if ( $size ) {
			if( isset( $sizes[ $size ] ) ) {
				return $sizes[ $size ];
			} else {
				return false;
			}
		} elseif ( $name_list ) {
			$names = array();

			foreach ( $sizes as $key => $value ) {
				$names[] = $key;
			}

			$sizes = $names;
		}

		return $sizes;
	}

	/**
	 * Widget default setting.
	 *
	 * @param  array $instance
	 * @return array
	 */
	private function default_args( $instance = array() ) {
		return wp_parse_args( $instance, array( 'title' => '', 'term_ids' => array(), 'thumbnail_size' => 'thumbnail', 'show_thumbnail' => false, 'show_time' => false, 'posts_per_page' => 5, 'cache_time' => 12 ) );
	}

	private function get_hierarchical_taxonomy_terms() {
		$result     = array();
		$taxonomies = get_taxonomies( '', 'objects' );
		unset($taxonomies['link_category'], $taxonomies['nav_menu'], $taxonomies['post_format']);

		foreach ( $taxonomies as $tax ) {
			$terms                       = get_terms( $tax->name, array( 'hide_empty' => false ) );
			$result[$tax->name]['label'] = $tax->label;
			$result[$tax->name]['terms'] = false;

			if ( !is_wp_error( $terms ) )
				$result[$tax->name]['terms'] = $terms;
		}

		return $result;
	}

	private function the_image_sizes_select( $size = 'thumbnail' ) {
		$sizes   = self::get_image_sizes();
		$output  = sprintf( '<p><label for="%s">%s: </label>', $this->get_field_id( 'thumbnail_size' ), __( 'Image size ', VA_TERM_LATEST_POSTS_WIDGET_TEXTDOMAIN ) );
		$output .= sprintf( '<select id="%s" name="%s">', $this->get_field_id( 'thumbnail_size' ), $this->get_field_name( 'thumbnail_size' ) );

		if ( $sizes ) {
			foreach ( $sizes as $key => $value ) {
				$selected = $key === $size ? ' selected' : '';
				$output  .= sprintf( '<option value="%s"%s>%s</option>', $key, $selected, $value['name'] );
			}
		}

		$output .= '</select></p>';

		echo $output;
	}

	private function the_hierarchical_taxonomy_term_list( $term_ids = array() ) {
		$taxonomies = self::get_hierarchical_taxonomy_terms();
		$output     = '';

		foreach ( $taxonomies as $key => $value ) {
			$output .= sprintf( '<p><strong>%s</strong></p>', $value['label'] );
			$output .= '<ul style="max-height: 150px; margin-left: 1em; overflow: scroll;">';

			if ( $value['terms'] ) {
				foreach ( $value['terms'] as $term ) {
					$checked = isset( $term_ids[$key] ) && in_array( $term->term_id, $term_ids[$key] ) ? ' checked' : '';
					$output .= sprintf( '<li><label><input type="checkbox" name="%s[%s][]" value="%d"%s> %s</label></li>', $this->get_field_name( 'term_ids' ), $key, $term->term_id, $checked, $term->name );
				}
			} else {
				$output .= 'Is not term.';
			}

			$output .= "</ul>";
		}

		echo $output;
	}
}
