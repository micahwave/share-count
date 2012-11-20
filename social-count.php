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

	public function getCount( $url ) {

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
 */
class FacebookCount {

	public function getCount( $url ) {

		$response = wp_remote_get( 'http://graph.facebook.com/?id=' . $url );

		if( !is_wp_error( $response ) ) {
			$json = json_decode( wp_remote_retrieve_body( $response ) );
			return isset( $json->shares ) ? $json->shares : false;
		} else {
			return false;
		}
	}
}

/**
 * Get counts for Google+
 */
class GoogleCount {

	public function getCount( $url ) {

		/*
		$curl = curl_init();
		curl_setopt($curl, CURLOPT_URL, "https://clients6.google.com/rpc");
		curl_setopt($curl, CURLOPT_POST, 1);
		curl_setopt($curl, CURLOPT_POSTFIELDS,
			'[{' .
			'"method":"pos.plusones.get",' .
			'"id":"p",' .
			'"params":{"nolog":true,"id":"' . $url . '","source":"widget","userId":"@viewer","groupId":"@self"},' . 
			'"jsonrpc":"2.0",' .
			'"key":"p",' .
			'"apiVersion":"v1"' .
			'}]');
		curl_setopt($curl, CURLOPT_RETURNTRANSFER, true);
		curl_setopt($curl, CURLOPT_HTTPHEADER, array('Content-type: application/json'));
		$response = curl_exec ($curl);
		curl_close ($curl);
		*/
		
		$response = wp_remote_post( 'https://clients6.google.com/rpc', array(
			'body' => '[{"method":"pos.plusones.get","id":"p","params":{"nolog":true,"id":"' . $url . '","source":"widget","userId":"@viewer","groupId":"@self"},"jsonrpc":"2.0","key":"p","apiVersion":"v1"}]',
			'headers' => array(
				'content-type' => 'application/json'
			)
		));

		if( !is_wp_error( $response ) ) {

			$json = json_decode( wp_remote_retrieve_body( $response ) );

			if( !isset( $json[0]['error'] ) ) {

				return $json[0]['result']['metadata']['globalCounts']['count'];
				
			} else {

				return false;
			}

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
	 * ID of the post were saving count meta for
	 */
	var $post_id;

	/**
	 * The last time we called our APIs for tweet counts
	 */
	var $last_update;

	/**
	 * Possible sources to get counts from
	 */
	var $networks = array(
		'facebook',
		'twitter',
		'google'
	);

	/**
	 * The different buttons were going to display
	 */
	var $buttons = array();

	/**
	 * How long to wait in between updates. Default 15 minutes.
	 */
	var $delay = 900;

	/**
	 * 
	 */
	var $current_time;

	/**
	 * Constructor
	 */
	function __construct( $post_id = null, $buttons = array( 'facebook', 'twitter', 'google' ) ) {

		$this->post_id = !empty( $post_id ) ? $post_id : get_the_ID();

		// make sure we only accept buttons for networks we support
		$this->buttons = array_filter( 'in_array', $this->networks );

		// when was this post updated last?
		$this->last_update = get_post_meta( $this->post_id, 'social_count_last_update', true );

		// set the current time so we can reuse
		$this->current_time = time();

		// no last_update, set it to the current time
		if( empty( $this->last_update ) ) $this->last_update = $this->current_time;

		$this->renderShare();

	}

	/**
	 * Is the current post ready to be scanned again?
	 */
	function updateCount() {
		return ( $this->last_update + $this->delay ) > $this->current_time ? true : false;
	}

	/**
	 * Get the number of interactions for the passed social network
	 */ 
	function getCount( $provider ) {

		// try to get from meta
		$count = get_post_meta( $this->post_id, 'social_count_'.$provider, true );

		// get the current count if there is no count or it hasnt been updated in awhile
		if( $this->updateCount() || empty( $count ) ) {

			$count = call_user_func_array( array( $provider.'count', 'getCount' ), array( 'url' => get_permalink( $this->post_id ) ) );

			// we have a value, update the meta
			if( !empty( $count ) ) {
				update_post_meta( $post_id, 'social_count_'.$provider, intval( $count ) );
			}
		}

		return $count;
	}

	/**
	 * Output the buttons we want
	 */
	function renderShare() {

		$json = array();

		foreach( $this->buttons as $button ) {
			$json[$button] = $this->getCount( $button );
		}
	}
}
$sc = new SocialCount();
