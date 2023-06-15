<?php
namespace Subframe;

use PHPUnit\Framework\TestCase;

class RouterTest extends TestCase {

	public function testFindRouteInNamespace() {
		$route = (new Router())->findRouteInNamespace(new Request('GET', '/'), 'Website');
		$this->assertSame(['Website\\Home', 'getIndex', []], $route);

		$route = (new Router())->findRouteInNamespace(new Request('GET', '/contact'), 'Website');
		$this->assertSame(['Website\\Home', 'getContact', []], $route);

		$route = (new Router())->findRouteInNamespace(new Request('GET', '/about'), 'Website');
		$this->assertSame(['Website\\About\\Home', 'getIndex', []], $route);

		$route = (new Router())->findRouteInNamespace(new Request('GET', '/some-page'), 'Website');
		$this->assertSame(['Website\\Home', 'get', ['some-page']], $route);

		$route = (new Router())->findRouteInNamespace(new Request('GET', '/about/board'), 'Website');
		$this->assertSame(['Website\\Home', 'get', ['about', 'board']], $route);

		$route = (new Router())->findRouteInNamespace(new Request('GET', '/about/board/ante'), 'Website');
		$this->assertSame(['Website\\About\\Board', 'get', ['ante']], $route);

		$route = (new Router())->findRouteInNamespace(new Request('GET', '/about/board/ante/photos'), 'Website');
		$this->assertSame(['Website\\About\\Board', 'getPhotos', ['ante']], $route);
	}

}
