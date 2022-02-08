<?php
namespace Subframe;

use PHPUnit\Framework\TestCase;

class RequestTest extends TestCase {

	/** @var Request */
	private $request1;

	/** @var Request */
	private $request2;


	public function setUp(): void {
		$this->request1 = new Request(
				'GET',
				'/contact',
				['page' => '3'],
				[],
				['remember' => '1'],
				[],
				[],
				[]
		);

		$this->request2 = new Request(
				'POST',
				'/contact',
				['page' => '3'],
				['ante' => 'frane'],
				['remember' => '1'],
				[],
				[
						'CONTENT_TYPE' => 'application/x-www-form-urlencoded',
						'HTTP_ACCEPT' => '*/json',
						'HTTP_X_REQUESTED_WITH' => 'XMLHttpRequest'
				]
		);
	}

	public function testGetMethod() {
		$this->assertEquals('GET', $this->request1->getMethod());
		$this->assertEquals('POST', $this->request2->getMethod());
	}

	public function testGetUri() {
		$this->assertEquals('/contact', $this->request1->getUri());
		$this->assertEquals('/contact', $this->request2->getUri());
	}

	public function testGetHeader() {
		$this->assertEquals('application/x-www-form-urlencoded', $this->request2->getHeader('content-type'));
	}

	public function testGetHeaders() {
		$headers = $this->request2->getHeaders();
		$this->assertEquals('application/x-www-form-urlencoded', $headers['Content-Type']);
	}

	public function testAcceptsJson() {
		$this->assertFalse($this->request1->acceptsJson());
		$this->assertTrue($this->request2->acceptsJson());
	}

	public function testIsAjax() {
		$this->assertFalse($this->request1->isAjax());
		$this->assertTrue($this->request2->isAjax());
	}

	public function testGet() {
		$this->assertSame('3', $this->request1->get('page'));
		$this->assertSame('3', $this->request2->get('page'));
		$this->assertNull($this->request1->get('non-existent'));
		$this->assertNull($this->request2->get('non-existent'));
	}

	public function testPost() {
		$this->assertNull($this->request1->post('non-existent'));
		$this->assertNull($this->request2->post('non-existent'));
		$this->assertEquals('frane', $this->request2->post('ante'));
	}

	public function testCookie() {
		$this->assertSame('1', $this->request1->cookie('remember'));
		$this->assertSame('1', $this->request2->cookie('remember'));
		$this->assertNull($this->request1->cookie('non-existent'));
		$this->assertNull($this->request2->cookie('non-existent'));
	}

}
