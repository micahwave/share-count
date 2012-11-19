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

		if( isset( $_GET['debug'] ) ) {
			die( print_r( $response ) );
		}
		

		if( $response ) {
			$json = json_decode($response, true);

			//die_r( $json );
			if(!isset($json[0]['error'])) {
				return $json[0]['result']['metadata']['globalCounts']['count'];
			} else {
				return NULL;
			}
		} else {
			return NULL;
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
	 * Possible sources to get counts from
	 */
	var $providers = array(
		'facebook',
		'twitter',
		'google'
	);

	/**
	 * Constructor
	 */
	function __construct( $args = array() ) {

		$args = wp_parse_args( $args, array(
			'post_id' => get_the_ID(),
			'providers' => $this->providers
		));

		$this->renderShare();

	}

	function getCount( $provider ) {

		// try to get from meta
		$count = get_post_meta( $this->post_id, 'social_count_'.$provider, true );

		// no count, make API request
		if( empty( $count ) ) {
			//get_permalink( $this->post_id )
			$count = call_user_func_array( array( ucwords( $provider ).'Count', 'getCount' ), array( 'url' => 'http://www.time.com' ) );
		}

		return $count;

	}

	function renderShare() {

		$json = array();

		foreach( $this->providers as $provider ) {
			$json[$provider] = $this->getCount( $provider );
		}

		//die_r( $json );

	}
}
$sc = new SocialCount();
