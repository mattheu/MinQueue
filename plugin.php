<?php

/*
Plugin Name: MPH Minify
Plugin URI: http://matth.eu
Description: Mega Simple Minify. Minifies enqueued scripts & styles.
Author: Matthew Haines-Young
Version: 0.1
Author URI: http://www.matth.eu
*/


add_action( 'wp_enqueue_scripts', function() {

	$minify = new MPH_Minify(); 

	$minify->minify();

}, 100 );


class MPH_Minify {

	// Useful stuff.
	var $plugin_url;
	var $minify_url;

	var $scripts_queue;
	var $styles_queue;

	function __construct() {

		global $wp_scripts, $wp_styles;
		$this->wp_scripts = $wp_scripts;
		$this->wp_styles = $wp_styles;

		$this->plugin_url = plugins_url( basename( __DIR__ ) );
		$this->minify_url = trailingslashit( $this->plugin_url ) . 'php-minify/min/';

	}

	function minify() {

		$this->minify_scripts();
		$this->minify_styles();

	}

	function minify_scripts() {

		// Do this to set up the todos - in the correct order.	
		$this->wp_scripts->all_deps( $this->wp_scripts->queue );
		
		foreach ( $this->wp_scripts->to_do as $key => $handle )
			$this->scripts_queue[ $this->wp_scripts->groups[$handle] ][] = $handle;

		foreach ( $this->scripts_queue as $group => $handle )
			$this->process_scripts( $group );

	}

	function minify_styles() {

		// Do this to set up the todos - in the correct order.	
		$this->wp_styles->all_deps( $this->wp_styles->queue );
		
		foreach ( $this->wp_styles->to_do as $key => $handle )
			$this->styles_queue[ $this->wp_styles->groups[$handle] ][] = $handle;
		
		foreach ( $this->styles_queue as $group => $handle )
			$this->process_styles( $group );
			
	}

	function process_scripts( $group ) {
		
		$scripts = array();

		foreach ( $this->scripts_queue[$group] as $handle ) {
			
			$scripts[] = $this->get_script_path( $handle );

			$this->wp_scripts->dequeue( $handle );

		}	

		$min_src = $this->minify_url . '/?f=' . implode( ',', $scripts );
		
		// Enqueue the scripts
		$this->wp_scripts->add( 'mph-minify-script-group-' . $group, $min_src );
		$this->wp_scripts->add_data( 'mph-minify-script-group-' . $group, 'group', $group );		
		$this->wp_scripts->enqueue( 'mph-minify-script-group-' . $group );

	}

	function process_styles( $group ) {
		
		$styles = array();

		foreach ( $this->styles_queue[$group] as $handle ) {
			
			$styles[] = $this->get_style_path( $handle );

			$this->wp_styles->dequeue( $handle );

		}	

		$min_src = $this->minify_url . '/?f=' . implode( ',', $styles );
		
		// Enqueue the scripts
		$this->wp_styles->add( 'mph-minify-style-group-' . $group, $min_src );
		$this->wp_styles->add_data( 'mph-minify-style-group-' . $group, 'group', $group );		
		$this->wp_styles->enqueue( 'mph-minify-style-group-' . $group );

	}


	function get_script_path( $handle ) {

		$src = $this->wp_scripts->registered[$handle]->src;

		if ( ! preg_match('|^(https?:)?//|', $src) && ! ( $this->wp_scripts->content_url && 0 === strpos( $src, $this->wp_scripts->content_url ) ) )
			$src = $this->wp_scripts->base_url . $src;

		return str_replace( home_url(), '', $src );

	}

	function get_style_path( $handle ) {

		$src = $this->wp_styles->registered[$handle]->src;

		if ( ! preg_match('|^(https?:)?//|', $src) && ! ( $this->wp_styles->content_url && 0 === strpos( $src, $this->wp_styles->content_url ) ) )
			$src = $this->wp_styles->base_url . $src;

		return str_replace( home_url(), '', $src );

	}

}
