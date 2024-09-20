<?php

namespace MediaWiki\SecurityApi\Rest\Feed;

use Config;
use MediaWiki\Http\HttpRequestFactory;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\Response;
use MediaWiki\Rest\SimpleHandler;
use MediaWiki\User\UserIdentity;
use RequestContext;
use Wikimedia\Message\MessageValue;

class AllHandler extends SimpleHandler {
	/** @var Config */
	private $config;

	/** @var HttpRequestFactory */
	private $httpRequestFactory;

	/** @var PermissionManager */
	private $permissionManager;

	/** @var UserIdentity */
	private $user;

	/**
	 * @param Config $config
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param PermissionManager $permissionManager
	 * @param UserIdentity $user
	 */
	public function __construct(
		Config $config,
		HttpRequestFactory $httpRequestFactory,
		PermissionManager $permissionManager,
		UserIdentity $user
	) {
		$this->config = $config;
		$this->httpRequestFactory = $httpRequestFactory;
		$this->permissionManager = $permissionManager;
		$this->user = $user;
	}

	/**
	 * @param Config $config
	 * @param HttpRequestFactory $httpRequestFactory
	 * @param PermissionManager $permissionManager
	 * @return self
	 */
	public static function factory(
		Config $config,
		HttpRequestFactory $httpRequestFactory,
		PermissionManager $permissionManager
	) {
		return new self(
			$config,
			$httpRequestFactory,
			$permissionManager,
			RequestContext::getMain()->getUser()
		);
	}

	/**
	 * Get all info on ips provided by ipoid/feed
	 *
	 * @return Response
	 */
	public function run(): Response {
		if (
			!$this->permissionManager->userHasRight( $this->user, 'securityapi-ipoid-feed' )
		) {
			throw new LocalizedHttpException(
				new MessageValue( 'securityapi-rest-access-denied' ), $this->user->isRegistered() ? 403 : 401 );
		}

		$baseUrl = $this->config->get( 'SecurityApiIpoidUrl' );
		if ( !$baseUrl ) {
			throw new LocalizedHttpException(
				new MessageValue( 'securityapi-invalid-url' ), 400 );
		}

		// Get response from ipoid
		$url = $baseUrl . '/feed/v1/all';
		$req = $this->httpRequestFactory->create( $url, [ 'method' => 'GET' ], __METHOD__ );
		$response = $req->execute();

		if ( !$response->isOK() ) {
			throw new LocalizedHttpException(
				new MessageValue( 'securityapi-rest-error' ), $response->getErrors()[0]['params'][0] );
		}

		return $this->getResponseFactory()->createJson( json_decode( $req->getContent() ) );
	}
}
