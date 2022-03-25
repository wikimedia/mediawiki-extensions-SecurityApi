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
use Wikimedia\IPUtils;
use Wikimedia\Message\MessageValue;
use Wikimedia\ParamValidator\ParamValidator;

class IpHandler extends SimpleHandler {
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
	 * Get all info on an ip provided by security-api/feed
	 *
	 * @param string $ip
	 * @return Response
	 */
	public function run( string $ip ): Response {
		if ( !$this->permissionManager->userHasRight( $this->user, 'securityapi-feed' ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'securityapi-rest-access-denied' ), $this->user->isRegistered() ? 403 : 401 );
		}

		if ( !IPUtils::isValid( $ip ) ) {
			throw new LocalizedHttpException(
				new MessageValue( 'securityapi-invalid-ip' ), 400 );
		}

		$baseUrl = $this->config->get( 'SecurityApiUrl' );
		if ( !$baseUrl ) {
			throw new LocalizedHttpException(
				new MessageValue( 'securityapi-invalid-url' ), 400 );
		}

		// Get response from security-api
		$url = $baseUrl . '/feed/v1/ip/' . $ip;
		$req = $this->httpRequestFactory->create( $url, [ 'method' => 'GET' ] );
		$response = $req->execute();

		if ( !$response->isOK() ) {
			throw new LocalizedHttpException(
				new MessageValue( 'securityapi-rest-error' ), $response->getErrors()[0]['params'][0] );
		}

		return $this->getResponseFactory()->createJson( json_decode( $req->getContent() ) );
	}

	/**
	 * @inheritDoc
	 */
	public function getParamSettings() {
		return [
			'ip' => [
				self::PARAM_SOURCE => 'path',
				ParamValidator::PARAM_TYPE => 'string',
				ParamValidator::PARAM_REQUIRED => true,
			],
		];
	}
}
