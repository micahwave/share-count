<?php

/**
 * Plugin Name: Social Count
 * Description: Save time by calculating share counts server-side.
 * Author: Micah Ernst
 * Author URI: http://micahernst.com
 */

/**
 * Get counts for Twitter
 */
class TwitterCount {

	public function get_count( $url ) {

		$response = wp_remote_get( 'http://urls.api.twitter.com/1/urls/count.json?url=' . $url );

		if( !is_wp_error( $response ) ) {
			return json_decode( wp_remote_retrieve_body( $response ) )->count;
		} else {
			return false;
		}
	}
}

/**
 * Get counts for Facebook
 *
 * To get share, like, comment and total count use: https://graph.facebook.com/fql?q=SELECT%20url,%20normalized_url,%20share_count,%20like_count,%20comment_count,%20total_count,commentsbox_count,%20comments_fbid,%20click_count%20FROM%20link_stat%20WHERE%20url='http://techland.time.com/2012/11/15/all-time-100-video-games/'
 *
 * To get overall count use: http://graph.facebook.com/?id=http://techland.time.com/2012/11/15/all-time-100-video-games/
 */
class FacebookCount {

	public function get_count( $url ) {

		$response = wp_remote_get( 'http://graph.facebook.com/?id=' . $url );

		if( !is_wp_error( $response ) ) {
			$json = json_decode( wp_remote_retrieve_body( $response ) );
			return isset( $json->shares ) ? $json->shares : 0;
		} else {
			return false;
		}
	}
}

/**
 * Get counts for Google+
 */
class GoogleCount {

	public function get_count( $url ) {
		
		$response = wp_remote_post( 'https://clients6.google.com/rpc', array(
			'body' => '[{"method":"pos.plusones.get","id":"p","params":{"nolog":true,"id":"' . $url . '","source":"widget","userId":"@viewer","groupId":"@self"},"jsonrpc":"2.0","key":"p","apiVersion":"v1"}]',
			'headers' => array(
				'content-type' => 'application/json'
			)
		));

		if( !is_wp_error( $response ) ) {

			$json = json_decode( wp_remote_retrieve_body( $response ) );

			if( !isset( $json[0]->error ) ) {

				return $json[0]->result->metadata->globalCounts->count;
				
			} else {

				return false;
			}

		} else {
			return false;
		}
	}
}

/**
 * Get counts for LinkedIn
 *
 * https://developer.linkedin.com/documents/share-linkedin
 */
class LinkedInCount {

	public function get_count( $url ) {

		$response = wp_remote_get( 'http://www.linkedin.com/countserv/count/share?url=' . $url );

		if( !is_wp_error( $response ) ) {

			// this returns a jsonp response, we need to strip that part of the response so we can get just the data
			$response = str_replace( array( 'IN.Tags.Share.handleCount(', ');' ), '', wp_remote_retrieve_body( $response ) );
			$json = json_decode( $response );
			return isset( $json->count ) ? $json->count : 0;

		} else {

			return false;
		}
	}
}

/**
 * StumbleUpon
 */
class StumbleUponCount {

	public function get_count( $url ) {

		$response = wp_remote_get( 'http://www.linkedin.com/countserv/count/share?url=' . $url );

		if( !is_wp_error( $response ) ) {

			// this returns a jsonp response, we need to strip that part of the response so we can get just the data
			$response = str_replace( array( 'IN.Tags.Share.handleCount(', ');' ), '', wp_remote_retrieve_body( $response ) );
			$json = json_decode( $response );
			return isset( $json->count ) ? $json->count : 0;

		} else {

			return false;
		}
	}
}

/**
 *
 */
class SocialCount {

	/**
	 * Possible sources to get counts from
	 */
	var $services = array(
		'facebook' => 'https://www.facebook.com/sharer/sharer.php?u=%s',
		'twitter' => 'https://twitter.com/intent/tweet?text=%s',
		'google' => 'https://plusone.google.com/_/+1/confirm?url=%s',
		'linkedin' => 'http://www.linkedin.com/shareArticle?mini=true&url=%s'
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

	}

	function enqueue_styles() {
		if( is_single() ) {
			wp_enqueue_style( 'social-count', plugins_url( '/stylesheets/screen.css', 'social-count' ) );
		}
	}

	/**
	 * Output the buttons we want
	 */
	function get_share( $post_id = null, $networks = array( 'facebook', 'twitter', 'google', 'linkedin' ) ) {

		$post_id = !empty( $post_id ) ? $post_id : get_the_ID();

		// set the current time so we can reuse
		$current_time = current_time( 'timestamp' );

		// see when the last update was
		$last_update = get_post_meta( $post_id, 'social_count_last_update', true );

		// if theres a last update but it hasnt been long enough, get values from meta
		$use_meta = ( $last_update && ( $last_update + $this->timeout ) > $current_time ) ? true : false;

		// save as a var since were looping thru	
		$permalink = get_permalink( $post_id );

		$permalink = 'http://techland.time.com/2012/11/19/wii-u-review-redux-nintendo-adds-miiverse-netflix-eshop-and-more/';

		$permalink = 'http://techland.time.com/2012/11/20/oprah-tweets-love-for-microsoft-surface-from-an-ipad/';

		$html = '<ul class="social-count">';

		// loop through the networks and output button markup
		foreach( $networks as $network ) {

			// get the count from a meta value
			if( $use_meta ) {

				$count = get_post_meta( $post_id, 'social_count_'.$network, true );

			// its been too long, get an updated count
			} else {
				
				$count = call_user_func_array( array( $network.'count', 'get_count' ), array( 'url' => $permalink ) );

				// save the count as long as its a number
				if( is_numeric( $count ) ) {
					update_post_meta( $post_id, 'social_count_'.$network, intval( $count ) );
				}
			}

			$html .= sprintf(
				'<li class="%s"><a href="%s">'.$network.'&nbsp;<span class="icon"></span><span class="count">%s</span></a></li>',
				strtolower( $network ),
				sprintf( $this->services[$network], $permalink ),
				$count
			);
		}

		$html .= '</ul><!-- .social-count -->';

		// update the last_update value if we didnt use meta this time around
		if( !$use_meta ) {



			echo 'did not use meta<br/>';
			
			update_post_meta( $post_id, 'social_count_last_update', $current_time );
		} else {
			
			echo 'used meta</br>';
			echo 'will scrape in another ' . ( ( $last_update + $this->timeout ) - $current_time ) . ' seconds';
		}

		echo $html;
	}
}
$sc = new SocialCount();

/**
 * Helper function to get social count
 */
function social_count() {
	
	global $sc;

	$sc->get_share();
}