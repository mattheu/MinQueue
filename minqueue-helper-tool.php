<?php

/**
 *	Helper/Helper Tool
 *
 *	When enabled, adds button to menu bar on the front end of the site to display the helper tool.
 * 	Helper window shows all enqueued scripts, and highlights those that are minified.
 */

add_action( 'init', 'minqueue_tool' );
function minqueue_tool () {

	if ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) )
		return;

	$options = minqueue_get_options();

	if ( isset( $options['helper'] ) && true === $options['helper'] ) {

		add_action( 'wp_head', 'minqueue_helper_style' );
		add_action( 'wp_footer', 'minqueue_helper', 9999 );
		add_action( 'wp_footer', 'minqueue_helper_script', 9999 );

	}

}

/**
 * Helper tool for the minifyier
 *
 * Uses global var $minified_deps (as well as $wp_scritps & $wp_styles)
 */
function minqueue_helper() {

	global $wp_scripts, $wp_styles, $minified_deps;

	$styles_enqueued = array();
	$scripts_enqueued = array();

	// Get the queue of all scripts & styles that should be loaded.
	// A bit of a round about way as we need to know those loaded because they are a dependency.

	if ( ! empty( $wp_scripts ) ) {
		$scripts = wp_clone( $wp_scripts );
		$scripts->done = array();
		$scripts->to_do = array();
		$queue = array_unique( array_merge( array_keys( $minified_deps['WP_Scripts'] ), $scripts->queue ) );
		$scripts->all_deps( $queue );
		$scripts_enqueued = $scripts->to_do;
	}

	if ( ! empty( $wp_styles ) ) {
		$styles = wp_clone( $wp_styles );
		$styles->done = array();
		$styles->to_do = array();
		$queue = array_unique( array_merge( array_keys( $minified_deps['WP_Styles'] ), $styles->queue ) );
		$styles->all_deps( $queue );
		$styles_enqueued = $styles->to_do;
	}


	?>

	<div id="minqueue-helper">

		<div id="minqueue-helper-inner">

			<h2>Enqueued Scripts</h2>

			<ul>
				<?php minqueue_helper_list( $scripts_enqueued ); ?>
			</ul>

			<h2>Enqueued Styles</h2>

			<ul>
				<?php minqueue_helper_list( $styles_enqueued, false ); ?>
			</ul>

			<p><a href="<?php echo add_query_arg( 'page', 'minqueue', get_admin_url( null, 'options-general.php' ) ); ?>">Admin Page</a></p>

			<h2>Key</h2>

			<ul>
				<li class="minqueue-group-0">Orange: in header</li>
				<li class="minqueue-group-1">Yellow: in footer</li>
			</ul>

			<p>Files displayed in the order in which they are loaded.</p>
			<p>Only visible to admin users.<p>
			<p>Remember some scripts are loaded conditionally (on certain pages, or for logged in users etc).</p>

		</div>

	</div>

	<?php

}

/**
 * Output a <li>s of a list of assets for use in the helper
 *
 * @param  array  $asset_list list of handles to display
 * @param  boolean $scripts   whether minifying scripts. If false, minifyling styles.
 * @return null outputs <li> for each handle.
 */
function minqueue_helper_list( $asset_list, $scripts = true ) {

	global $minified_deps, $wp_scripts, $wp_styles;

	$options = minqueue_get_options();

	if ( $scripts )
		$class = &$wp_scripts;
	else
		$class = &$wp_styles;

	foreach ( $asset_list as $handle ) {

		// Don't show minified scripts.
		if ( 0 === strpos( $handle, 'minqueue' ) )
			continue;

		$classes = array();
		$classes['group'] = 'minqueue-group-' . ( isset( $class->registered[$handle]->extra['group'] ) ? $class->registered[$handle]->extra['group'] : 0 );

		if ( array_key_exists( $handle, $minified_deps[get_class($class)] ) )
			$classes['minified'] = 'minqueue-minified';

		$checked = false;
		foreach( $options[( 'WP_Scripts' == get_class($class) ) ? 'scripts_manual' : 'styles_manual'] as $queue )
			if( ! $checked )
				$checked = in_array( $handle, $queue );

		$disabled = ( ! is_user_logged_in() || ! current_user_can( 'manage_options' ) ) ? true : false;

		printf( 
			'<li class="%s" title="%s"><span class="minqueue-icon">%s</span>%s</li>',
			implode( ' ', array_map( 'sanitize_html_class', $classes ) ),
			esc_attr( implode( ', ', $class->registered[$handle]->deps ) ),
			( $checked ) ? '&#10004;' : '&bull;',
			esc_html( $handle )
		);

	}

}

/**
 * Helper Styles. Inserted into head.
 * OK OK, I know I'm not even correclty enqueuing my own styles
 * but I just didn't want to complicate things...
 * 
 * @return null
 */
function minqueue_helper_style() {

	?>

	<style>
		#minqueue-helper { position: fixed; top: 10px; bottom: 10px; right: 10px; overflow: hidden; width: 180px; border-radius: 10px; background: rgba(0,0,0,0.8); border: none; color: #FFF; padding: 10px; z-index: 9999; }
		.admin-bar #minqueue-helper { top: 38px; }
		#minqueue-helper form { height: 100%; }
		#minqueue-helper-inner { height: 100%; overflow: auto; }
		#minqueue-helper * { background: none !important; text-shadow: none !important; padding: 0 !important; }
		#minqueue-helper h2 { font-family: sans-serif; font-size: 18px; line-height: 1.5; margin-bottom: 5px; letter-spacing: normal; color: #FFF; font-size: 12px; font-family: verdana, sans-serif; background: none; text-shadow: none; padding: 0;  }
		#minqueue-helper ul,
		#minqueue-helper p { margin-bottom: 15px; }
		#minqueue-helper ul,
		#minqueue-helper p,
		#minqueue-helper li { padding: 0; margin-left: 0; margin-right: 0; font-size: 10px; font-family: verdana, sans-serif; line-height: 1.5; }
		#minqueue-helper li.minqueue-group-0 { color: orange;}
		#minqueue-helper li.minqueue-group-1 { color: yellow;}
		#minqueue-helper li input { margin-right: 7px; }
		#minqueue-helper li span.minqueue-icon { display: inline-block; width: 10px; display: none;  }
		#minqueue-helper li:before { content: '\2022'; display: inline-block; width: 10px; }
		#minqueue-helper li.minqueue-minified:before { content: '\2714'; }
		#minqueue-helper a,
		#minqueue-helper a:link,
		#minqueue-helper a:visited { color: inherit; text-decoration: none; }
		#minqueue-helper a:hover { color: inherit; text-decoration: underline; }
		#minqueue-helper-submit,
		#minqueue-helper-submit:hover
		#minqueue-helper-submit:active { border: 1px solid black !important; border-radius: 5px; box-shadow: inset 0 1px 0 rgba(255,255,255,0.6), inset 0 -1px 3px rgba(0,0,0,0.2); padding-bottom: 3px !important; padding-left: 6px !important; padding-right: 6px !important; padding-top: 2px !important; vertical-align: middle; }
		#minqueue-helper-submit { background-image: -moz-linear-gradient(top,#CCC,#999) !important; background-image: -ms-linear-gradient(top,#CCC,#999) !important; background-image: -webkit-gradient(linear,0 0,0 100%,from(#CCC),to(#999)) !important; background-image: -webkit-linear-gradient(top,#CCC,#999) !important; background-image: -o-linear-gradient(top,#CCC,#999) !important; background-image: -webkit-linear-gradient(top,#CCC,#999) !important; background-image: linear-gradient(top,#CCC,#999) !important;  }
		#minqueue-helper-submit:hover { background-image: -moz-linear-gradient(top,#FFF,#AAA) !important; background-image: -ms-linear-gradient(top,#FFF,#AAA) !important; background-image: -webkit-gradient(linear,0 0,0 100%,from(#FFF),to(#AAA)) !important; background-image: -webkit-linear-gradient(top,#FFF,#AAA) !important; background-image: -o-linear-gradient(top,#FFF,#AAA) !important; background-image: -webkit-linear-gradient(top,#FFF,#AAA) !important; background-image: linear-gradient(top,#FFF,#AAA) !important;  }
		#minqueue-helper-submit:active {  background-image: -moz-linear-gradient(top,#999,#AAA) !important; background-image: -ms-linear-gradient(top,#999,#AAA) !important; background-image: -webkit-gradient(linear,0 0,0 100%,from(#999),to(#AAA)) !important; background-image: -webkit-linear-gradient(top,#999,#AAA) !important; background-image: -o-linear-gradient(top,#999,#AAA) !important; background-image: -webkit-linear-gradient(top,#999,#AAA) !important; background-image: linear-gradient(top,#999,#AAA) !important; box-shadow: inset 0 -1px 1px rgba(255,255,255,0.3), inset 0 1px 3px rgba(0,0,0,0.2); }
	</style>

	<?php

}


/**
 * Helper Script.
 * OK OK. I really do realise that I'm not enqueuing my scripts
 * but I really really didn't want to complicate things...
 * 
 * @return null
 */
function minqueue_helper_script() {

	?>

	<script>

		var MinQueue = {

			display : document.getElementById('minqueue-helper'),
			button  : document.createElement('a'),

			insertButton : function() {
				var button = this.button,
				    adminBarContainer = document.getElementById( 'wp-admin-bar-top-secondary' ),
				    li = document.createElement( 'li' );
				button.setAttribute( 'href', '#');
				button.setAttribute('class', 'ab-item' );
				button.appendChild( document.createTextNode( 'MinQueue' ) );
				li.appendChild( button );
				adminBarContainer.appendChild( li );
			},

			toggleDisplay : function(e,el) {
				if ( this.display.style.display === 'block' )
					this.display.style.display = 'none';
				else
					this.display.style.display = 'block';
			},

			init : function() {
				var self = this;
				self.display.style.display = 'none';
				self.insertButton();
				self.button.addEventListener( 'click', function(e) { 
					self.toggleDisplay.call( self, e, this ) 
				} );
			}

		}

		MinQueue.init();

	</script>

	<?php

}
