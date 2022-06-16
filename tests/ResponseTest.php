<?php
namespace Subframe;

use PHPUnit\Framework\TestCase;

class ResponseTest extends TestCase {

	private $response;

	public function setUp(): void {
		$this->response = new Response('{}', 404, [
				'Content-Type' => 'application/json',
				'Content-Length' => '2',
		]);
	}

	public function testGetHeader() {
		self::assertEquals('application/json', $this->response->getHeader('content-type'));
	}

	public function testGetHeaders() {
		self::assertEquals([
				'Content-Type' => 'application/json',
				'Content-Length' => '2',
		], $this->response->getHeaders());
	}

	public function testWithHeader() {
		$with = $this->response
				->withHeader('location', '/123');
		self::assertEquals('application/json', $with->getHeader('content-type'));
		self::assertEquals('2', $with->getHeader('content-length'));
	}

	public function testWithoutHeader() {
		$without = $this->response
				->withoutHeader('content-type');
		self::assertEquals('application/json', $this->response->getHeader('content-type'));
		self::assertNull($without->getHeader('content-type'));
	}

	public function testOutput() {
		ob_start();
		@$this->response->send();
		$body = ob_get_clean();
		$status = http_response_code();
		//$headers = getallheaders();
		self::assertEquals('{}', $body);
		self::assertEquals(404, $status);
		//self::assertContains('Content-Type: application/json', $headers);
	}

	public function testFromView() {
		set_include_path(__DIR__.'/views');
		$response = Response::fromView('hello', ['name' => 'world'], 201);
		self::assertEquals(201, $response->getStatusCode());
		self::assertEquals('Hello, world!', $response->getBody());
	}

	public function testFromData() {
		$data = ['ante' => 'frane'];
		$response = Response::fromData($data, 202);
		self::assertEquals(202, $response->getStatusCode());
		self::assertEquals(json_encode($data), $response->getBody());
		self::assertStringContainsString('application/json', $response->getHeader('content-type'));
	}

}
