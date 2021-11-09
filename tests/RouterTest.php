<?php
use PHPUnit\Framework\TestCase;
use Subframe\Router;

class RouterTest extends TestCase {

	public function testFindRouteInNamespace() {
		$route = (new Router('GET', '/'))->findRouteInNamespace('Website');
		$this->assertSame(['Website\\Home', 'getIndex', []], $route);

		$route = (new Router('GET', '/contact'))->findRouteInNamespace('Website');
		$this->assertSame(['Website\\Home', 'getContact', []], $route);

		$route = (new Router('GET', '/about'))->findRouteInNamespace('Website');
		$this->assertSame(['Website\\About\\Home', 'getIndex', []], $route);

		$route = (new Router('GET', '/some-page'))->findRouteInNamespace('Website');
		$this->assertSame(['Website\\Home', 'get', ['some-page']], $route);

		$route = (new Router('GET', '/about/board'))->findRouteInNamespace('Website');
		$this->assertSame(['Website\\Home', 'get', ['about', 'board']], $route);

		$route = (new Router('GET', '/about/board/ante'))->findRouteInNamespace('Website');
		$this->assertSame(['Website\\About\\Board', 'get', ['ante']], $route);

		$route = (new Router('GET', '/about/board/ante/photos'))->findRouteInNamespace('Website');
		$this->assertSame(['Website\\About\\Board', 'getPhotos', ['ante']], $route);
	}

}
