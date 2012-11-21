<?php

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