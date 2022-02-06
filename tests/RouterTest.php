<?php
namespace Subframe;

use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase {

	public function testFindRouteInNamespace() {
		$route = (new Router(new Request('GET', '/')))
				->findRouteInNamespace('Website');
		$this->assertSame(['Website\\Home', 'getIndex', []], $route);

		$route = (new Router(new Request('GET', '/contact')))
				->findRouteInNamespace('Website');
		$this->assertSame(['Website\\Home', 'getContact', []], $route);

		$route = (new Router(new Request('GET', '/about')))
				->findRouteInNamespace('Website');
		$this->assertSame(['Website\\About\\Home', 'getIndex', []], $route);

		$route = (new Router(new Request('GET', '/some-page')))
				->findRouteInNamespace('Website');
		$this->assertSame(['Website\\Home', 'get', ['some-page']], $route);

		$route = (new Router(new Request('GET', '/about/board')))
				->findRouteInNamespace('Website');
		$this->assertSame(['Website\\Home', 'get', ['about', 'board']], $route);

		$route = (new Router(new Request('GET', '/about/board/ante')))
				->findRouteInNamespace('Website');
		$this->assertSame(['Website\\About\\Board', 'get', ['ante']], $route);

		$route = (new Router(new Request('GET', '/about/board/ante/photos')))
				->findRouteInNamespace('Website');
		$this->assertSame(['Website\\About\\Board', 'getPhotos', ['ante']], $route);
	}

}
