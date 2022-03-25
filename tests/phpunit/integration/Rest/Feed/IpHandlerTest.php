<?php

namespace MediaWiki\SecurityApi\Test\Unit\Feed\Handler;

use Config;
use MediaWiki\Permissions\PermissionManager;
use MediaWiki\Rest\LocalizedHttpException;
use MediaWiki\Rest\RequestData;
use MediaWiki\SecurityApi\Rest\Feed\IpHandler;
use MediaWiki\Tests\Rest\Handler\HandlerTestTrait;
use MediaWiki\User\UserIdentity;
use MediaWikiIntegrationTestCase;
use MockHttpTrait;
use Wikimedia\Message\MessageValue;

/**
 * @group SecurityApi
 * @covers \MediaWiki\SecurityApi\Rest\Feed\IpHandler
 */
class IpHandlerTest extends MediaWikiIntegrationTestCase {
	use MockHttpTrait;
	use HandlerTestTrait;

	/**
	 * @param array $options
	 * @return IpHandler
	 */
	private function getIpHandler( array $options = [] ): IpHandler {
		return new IpHandler( ...array_values( array_merge(
			[
				'config' => $this->createMock( Config::class ),
				'httpRequestFactory' => $this->makeMockHttpRequestFactory( 'baz' ),
				'permissionManager' => $this->createMock( PermissionManager::class ),
				'userIdentity' => $this->createMock( UserIdentity::class )
			],
			$options
		) ) );
	}

	/**
	 * @param string $ip
	 * @return RequestData
	 */
	private function getRequestData( string $ip = '1.1.1.1' ): RequestData {
		return new RequestData( [
			'pathParams' => [ 'ip' => $ip ],
		] );
	}

	public function testExecute() {
		$config = $this->createMock( Config::class );
		$config->method( 'get' )
			->willReturn( 'https://foo.bar' );

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( true );

		$handler = $this->getIpHandler( [
			'config' => $config,
			'permissionManager' => $permissionManager,
		] );
		$request = $this->getRequestData();
		$response = $this->executeHandler( $handler, $request );
		$this->assertSame( 200, $response->getStatusCode() );
	}

	/**
	 * @dataProvider provideExecuteErrors
	 * @param array $options
	 * @param array $expected
	 */
	public function testExecuteErrors( array $options, array $expected ) {
		$config = $this->createMock( Config::class );
		$config->method( 'get' )
			->willReturn( $options['baseUrl'] ?? null );

		$permissionManager = $this->createMock( PermissionManager::class );
		$permissionManager->method( 'userHasRight' )
			->willReturn( $options['userHasRight'] ?? null );

		$user = $this->createMock( UserIdentity::class );
		$user->method( 'isRegistered' )
			->willReturn( $options['userIsRegistered'] ?? false );

		$handler = $this->getIpHandler( [
			'config' => $config,
			'permissionManager' => $permissionManager,
			'userIdentity' => $user,
		] );
		$request = $this->getRequestData( $options['ip'] ?? '' );

		$this->expectExceptionObject(
			new LocalizedHttpException(
				new MessageValue(
					$expected['message'],
					$expected['messageParams'] ?? []
				),
				$expected['status']
			)
		);

		$this->executeHandler( $handler, $request );
	}

	public function provideExecuteErrors() {
		return [
			'access denied; not logged in' => [
				[
					'userHasRight' => false,
					'userIsRegistered' => false,
					'baseUrl' => 'https://foo.baz',
					'ip' => '1.1.1.1'
				],
				[
					'message' => 'securityapi-rest-access-denied',
					'status' => 401,
				],
			],
			'access denied; no user right' => [
				[
					'userHasRight' => false,
					'userIsRegistered' => true,
					'baseUrl' => 'https://foo.baz',
					'ip' => '1.1.1.1'
				],
				[
					'message' => 'securityapi-rest-access-denied',
					'status' => 403,
				],
			],
			'invalid ip' => [
				[
					'userHasRight' => true,
					'userIsRegistered' => true,
					'baseUrl' => 'https://foo.baz',
					'ip' => 'a.s.d.f'
				],
				[
					'message' => 'securityapi-invalid-ip',
					'status' => 400,
				],
			],
			'no base URL' => [
				[
					'userHasRight' => true,
					'userIsRegistered' => true,
					'baseUrl' => false,
					'ip' => '1.1.1.1'
				],
				[
					'message' => 'securityapi-invalid-url',
					'status' => 400,
				],
			],
		];
	}
}
