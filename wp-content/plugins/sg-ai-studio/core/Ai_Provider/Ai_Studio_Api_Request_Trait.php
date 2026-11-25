<?php
declare( strict_types=1 );

namespace SG_AI_Studio\Ai_Provider;

use WordPress\AiClient\Common\Exception\RuntimeException;
use WordPress\AiClient\Providers\Http\DTO\Request;
use WordPress\AiClient\Providers\Http\DTO\RequestOptions;
use WordPress\AiClient\Providers\Http\DTO\Response;
use WordPress\AiClient\Providers\Http\Enums\HttpMethodEnum;

/**
 * Sends AI Studio API requests through the WordPress AI Client SDK transporter.
 *
 * Going through the SDK transporter (instead of wp_remote_post() directly)
 * lets decorators such as the AI plugin's Logging_Http_Transporter intercept
 * the request, so it shows up in Tools > AI Request Logs.
 *
 * Must be used by classes extending AbstractApiBasedModel.
 *
 * @package SG_AI_Studio
 */
trait Ai_Studio_Api_Request_Trait {

	/**
	 * Sends a POST request to the AI Studio API.
	 *
	 * @param string $url   The endpoint URL.
	 * @param array  $body  The request body.
	 * @param string $token The AI Studio auth token.
	 * @return Response The API response.
	 * @throws RuntimeException If the body cannot be encoded or the request fails at the transport level.
	 */
	protected function sendApiRequest( string $url, array $body, string $token ): Response {
		$encoded_body = wp_json_encode( $body );

		if ( false === $encoded_body ) {
			throw new RuntimeException( 'Failed to encode the AI Studio request body.' );
		}

		// WP HTTP defaults to a 5 second timeout, far too short for generation requests.
		$options = $this->getRequestOptions();
		$options = null !== $options ? clone $options : new RequestOptions();
		if ( null === $options->getTimeout() ) {
			$options->setTimeout( 300.0 );
		}

		$request = new Request(
			HttpMethodEnum::POST(),
			$url,
			array(
				'Authorization' => 'Bearer ' . $token,
				'Content-Type'  => 'application/json',
				'Connection'    => 'keep-alive',
				'Accept'        => '*/*',
			),
			$encoded_body,
			$options
		);

		return $this->getHttpTransporter()->send( $request );
	}
}
