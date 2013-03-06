<?php

/**
 * Plugin Name: Share Count
 * Description: Save load time by calculating share counts server-side. Supports Facebook, Twitter, Google+, LinkedIn and StumbleUpon.
 * Author: Micah Ernst
 * Author URI: http://micahernst.com
 */

include_once( dirname( __FILE__ ).'/networks.php' );

/**
 *
 */
class ShareCount {

	/**
	 * Networks that the plugin supports
	 */
	var $networks = array(
		'facebook',
		'twitter',
		'google',
		'linkedin',
		'stumbleupon',
		'reddit'
	);

	/**
	 * Display names for the networks we support
	 */
	var $labels = array(
		'facebook' => 'Facebook',
		'twitter' => 'Twitter',
		'google' => 'Google+',
		'linkedin' => 'LinkedIn',
		'stumbleupon' => 'StumbleUpon',
		'reddit' => 'Reddit'
	);

	/**
	 * Store the setting for this plugin
	 */
	var $options = array();

	/**
	 * Default option values
	 */
	var $defaults = array(
		'facebook' => 1,
		'twitter' => 1,
		'google' => 1,
		'linkedin' => 1,
		'stumbleupon' => 1,
		'reddit' => 1
	);

	/**
	 * Constructor
	 */
	function __construct( $post_id = null ) {

		// add some social styles to the article page
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		// add our menu stuff
		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		// get the options for this plugin
		$this->options = wp_parse_args( get_option( 'share_count_settings' ), $this->defaults );

		add_action( 'share_count_cron', array( $this, 'process_posts' ) );
		add_action( 'init', array( $this, 'init' ) );

	}

	/**
	 * Setup our cron
	 */
	function init() {
		if( !wp_next_scheduled( 'share_count_cron' ) ) {
			wp_schedule_event( time(), 'hourly', 'share_count_cron' );
		}
	}

	/**
	 * Add some css
	 */
	function enqueue_styles() {
		if( is_single() ) {
			wp_enqueue_style( 'share-count', plugins_url( '/stylesheets/screen.css', 'share-count' ) );
		}
	}

	/**
	 * Options page for some of our settings
	 */
	function submenu_page() {

		add_settings_section( 'general', 'General', '__return_false', 'share-count' );

		foreach( $this->networks as $network ) {
			add_settings_field( $network, $this->labels[$network], array( $this, 'network_field' ), 'share-count', 'general', array(
				'value' => $this->options[$network],
				'name' => $network
			));
		}

		?>
		<div class="wrap">

			<div id="icon-options-general" class="icon32"></div>

			<h2>Share Count</h2>

			<form action="options.php" method="post">

				<table class="form-table">
					<?php

					settings_fields( 'share_count_settings' );

					do_settings_fields( 'share-count', 'general' );

					?>
				</table>

				<input type="submit" name="submit" class="button-primary" value="Save Changes"/>

			</form>

		</div>
		<?php		
	}

	/**
	 * Output a checkbox for enabling/disabling a social network
	 */
	function network_field( $args ) {
		?>
		<input type="checkbox" name="share_count_settings[<?php echo $args['name']; ?>]" value="1" <?php checked( 1, $args['value'] ); ?>/>
		<?php
	}

	/**
	 * Register our submenu page
	 */
	function admin_menu() {

		register_setting( 'share_count_settings', 'share_count_settings', array( $this, 'validate_settings' ) );

		add_submenu_page( 'options-general.php', 'Share Count', 'Share Count', 'manage_options', 'share-count', array( $this, 'submenu_page' ) );
	}

	/**
	 *
	 */
	function validate_settings( $input ) {

		$output = array();

		foreach( $this->networks as $network ) {
			if( !empty( $input[$network] ) ) {
				$output[$network] = 1;
			} else {
				$output[$network] = 0;
			}
		}

		return $output;
	}

	/**
	 * Output the buttons we want
	 */
	function get_share( $post_id = null ) {

		$post_id = !empty( $post_id ) ? $post_id : get_the_ID();

		$html = '';

		$total_count = get_post_meta( $post_id, 'share_count_total', true );

		$share_text = $total_count ? '<strong>'.intval( $total_count ).'</strong>Sharing' : 'Share';

		$html .= '<div class="share-count">';
		$html .= '<div class="share-button"><i class="icon"></i>'.$share_text.'</div>';
		$html .= '<ul class="share-popup">';

		foreach( $this->networks as $network ) {

			// try to grab the count
			$count = get_post_meta( $post_id, 'share_count_'.$network, true );

			$html .= sprintf(
				'<li class="%s"><a href="%s" target="_blank"><i class="icon"></i>%s</span></a><span class="count">%s</span></li>',
				$network,
				call_user_func_array( array( $network.'count', 'get_url' ), array( $post_id ) ),
				$this->labels[$network],
				!empty( $count ) ? $count : 0
			);
		}

		$html .= '</ul>';
		$html .= '</div>';
		

		return $html;
	}

	/**
	 * Get the count data for the supported networks
	 */
	function get_share_data( $post_id = null ) {

		$post_id = !empty( $post_id ) ? $post_id : get_the_ID();

		// save as a var since were looping thru	
		$permalink = get_permalink( $post_id );

		$total_count = 0;

		// loop through the networks and output button markup
		foreach( $this->networks as $network ) {

			$count = intval( call_user_func_array( array( $network.'count', 'get_count' ), array( 'url' => $permalink ) ) );

			// save the count
			if( $count ) {

				update_post_meta( $post_id, 'share_count_'.$network, intval( $count ) );

				// increase the total count
				$total_count += $count;
			}
		}

		// save the total count
		if( !empty( $total_count ) ){
			update_post_meta( $post_id, 'share_count_total', $total_count );
		}
		
	}

	/**
	 * Fetch the social data for the last x posts
	 */
	function process_posts() {

		$posts = get_posts( array(
			'posts_per_page' => 100,
			'posts_status' => 'publish'
		));

		if( $posts ) {
			foreach( $posts as $post ) {
				$this->get_share_data( $post->ID );
			}
		}
	}
}
$sc = new ShareCount();

/**
 * Helper function to get social count
 */
function share_count() {
	global $sc;
	echo $sc->get_share();
	wp_enqueue_script( 'share-count', plugins_url( 'js/share-count.js', __FILE__ ), null, null, true );
}