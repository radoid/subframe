<?php
use PHPUnit\Framework\TestCase;
use Subframe\Response;

class ResponseTest extends TestCase {

	public function testHeaders(): void {
		$response = new Response('body', 123, ['host: example', 'accepts: */json']);
		$response->addHeader('Set-Cookie: key1=value1');
		$response->addHeader('set-cookie: key2=value2');
		$this->assertEquals('example', $response->getHeader('host'));
		$this->assertEquals('example', $response->getHeader('HOST'));
		
		// $cookies = $response->getHeader('set-cookie');
		// $this->assertIsArray($cookies);
		// $this->assertContains('key1=value1', $cookies);
		// $this->assertContains('key2=value2', $cookies);
		// $this->assertEquals(['*/json'], $response->getHeader('Accepts'));
	}

}
