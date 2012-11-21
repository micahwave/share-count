<?php

/**
 * Plugin Name: Social Count
 * Description: Save load time by calculating share counts server-side. Supports Facebook, Twitter, Google+, LinkedIn and StumbleUpon.
 * Author: Micah Ernst
 * Author URI: http://micahernst.com
 */

include_once( dirname(__FILE__).'/networks.php' );

/**
 *
 */
class SocialCount {

	/**
	 * Possible sources to get counts from
	 */
	var $services = array(
		'facebook',
		'twitter',
		'google',
		'linkedin'
	);

	/**
	 * How long to wait in between updates. Default 15 minutes.
	 */
	var $timeout = 30;


	/**
	 * Constructor
	 */
	function __construct( $post_id = null, $buttons = array( 'facebook', 'twitter', 'google' ) ) {

		// add some social styles to the article page
		add_action( 'wp_enqueue_scripts', array( $this, 'enqueue_styles' ) );

		// todo: check options to see if we should load css or not

		// test
		add_action( 'time_before_single', function() {
			social_count();
		});
	}

	/**
	 * Add some css
	 */
	function enqueue_styles() {
		if( is_single() ) {
			wp_enqueue_style( 'social-count', plugins_url( '/stylesheets/screen.css', 'social-count' ) );
		}
	}

	/**
	 * Output the buttons we want
	 */
	function get_share( $post_id = null, $networks = array( 'facebook', 'twitter', 'google', 'linkedin', 'stumbleupon' ) ) {

		$post_id = !empty( $post_id ) ? $post_id : get_the_ID();

		// set the current time so we can reuse
		$current_time = current_time( 'timestamp' );

		// see when the last update was
		$last_update = get_post_meta( $post_id, 'share_count_last_update', true );

		// if theres a last update and it hasnt been long enough, get values from meta
		$use_meta = ( $last_update && ( $last_update + $this->timeout ) > $current_time ) ? true : false;

		// save as a var since were looping thru	
		$permalink = get_permalink( $post_id );

		$permalink = 'http://techland.time.com/2012/11/19/wii-u-review-redux-nintendo-adds-miiverse-netflix-eshop-and-more/';

		$permalink = 'http://techland.time.com/2012/11/20/oprah-tweets-love-for-microsoft-surface-from-an-ipad/';

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
				'<li class="%s"><a href="%s">'.$network.'&nbsp;<span class="icon"></span><span class="count">%s</span></a></li>',
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
$sc = new SocialCount();

/**
 * Helper function to get social count
 */
function social_count() {
	global $sc;
	echo $sc->get_share();
}