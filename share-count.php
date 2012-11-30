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
	 * Possible sources to get counts from
	 */
	var $services = array(
		'facebook',
		'twitter',
		'google',
		'linkedin',
		'stumbleupon'
	);

	/**
	 * How long to wait in between updates. Default 15 minutes.
	 */
	var $timeout = 30;

	/**
	 * Store the setting for this plugin
	 */
	var $options = array();

	/**
	 * Default option values
	 */
	var $defaults = array(
		'size' => 'default',
		'count' => 1,
		'verb' => 'like'
	);

	/**
	 * Fields to display on our settings page
	 */
	var $fields = array(
		'size' => 'Button Size',
		'count' => 'Display Count',
		'verb' => 'Facebook Verb to Display'
	);


	/**
	 * Constructor
	 */
	function __construct( $post_id = null, $buttons = array( 'facebook', 'twitter', 'google' ) ) {

		// add some social styles to the article page
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		add_action( 'admin_menu', array( $this, 'admin_menu' ) );

		$this->options = wp_parse_args( get_option( 'share_count_settings' ), $this->defaults );

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

		foreach( $this->fields as $field => $label ) {
			add_settings_field( $field, $label, array( $this, $field.'_field' ), 'share-count', 'general', array(
				'value' => $this->options[$field]
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
	 * Size options
	 */
	function size_field( $args ) {
		?>
		<input type="radio" name="share_count_settings[size]" value="default" <?php checked( 'default', $args['value'] ); ?>/> Default<br/>
		<input type="radio" name="share_count_settings[size]" value="large" <?php checked( 'large', $args['value'] ); ?>/> Large<br/>
		<?php
	}

	/**
	 *
	 */
	function count_field( $args ) {
		?>
		<input type="checkbox" name="share_count_settings[count]" value="1" <?php checked( 1, $args['value'] ); ?>/>
		Display share count next to button
		<?php
	}

	/**
	 *
	 */
	function verb_field( $args ) {
		?>
		<input type="radio" name="share_count_settings[verb]" value="like" <?php checked( 'like', $args['value'] ); ?>/> Like</br>
		<input type="radio" name="share_count_settings[verb]" value="recommend" <?php checked( 'recommend', $args['value'] ); ?>/> Recommend
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
		return $input;
	}

	/**
	 * Output the buttons we want
	 */
	function get_share( $post_id = null, $networks = array( 'facebook', 'twitter', 'google' ) ) {

		$post_id = !empty( $post_id ) ? $post_id : get_the_ID();

		// set the current time so we can reuse
		$current_time = current_time( 'timestamp' );

		// see when the last update was
		$last_update = get_post_meta( $post_id, 'share_count_last_update', true );

		// if theres a last update and it hasnt been long enough, get values from meta
		$use_meta = ( $last_update && ( $last_update + $this->timeout ) > $current_time ) ? true : false;

		// save as a var since were looping thru	
		$permalink = get_permalink( $post_id );

		$html = '<ul class="share-count">';

		// loop through the networks and output button markup
		foreach( $networks as $network ) {

			// get the count from a meta value
			if( $use_meta ) {

				$count = get_post_meta( $post_id, 'share_count_'.$network, true );

			// its been too long, get an updated count
			} else {
				
				$count = call_user_func_array( array( $network.'count', 'get_count' ), array( 'url' => $permalink ) );

				// save the count as long as its a number
				if( is_numeric( $count ) ) {
					update_post_meta( $post_id, 'share_count_'.$network, intval( $count ) );
				}
			}

			$html .= sprintf(
				'<li class="%s"><a href="%s">'.$network.'<span class="icon"></span><span class="count">%s</span></a></li>',
				strtolower( $network ),
				call_user_func_array( array( $network.'count', 'get_url' ), array( $post_id ) ),
				$count
			);
		}

		$html .= '</ul><!-- .share-count -->';

		// update the last_update value if we didnt use meta this time around
		if( !$use_meta ) {
			update_post_meta( $post_id, 'share_count_last_update', $current_time );
		}

		return $html;
	}
}
$sc = new ShareCount();

/**
 * Helper function to get social count
 */
function share_count() {
	global $sc;
	echo $sc->get_share();
}