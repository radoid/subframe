<?php
namespace {
	use PHPUnit\Framework\TestCase;
	use Subframe\Request;
	use Subframe\Response;
	use Subframe\Router;
	use WebsiteNamespace\AboutUs;
	use WebsiteNamespace\Contact;
	use WebsiteNamespace\Home;

	class Example {
		public function noParams(): void {}
		public function oneParam(string $param): void {}
		public function twoParams(string $param1, string $param2): void {}
		public function optionalParams(string $param1, ?string $param2 = null): void {}
	}

	class RouterTest extends TestCase {

		public function testMatchClass(): void {
			$router = new Router();

			$route = $router->matchClass(Example::class, 'GET', ['non-action']);
			$this->assertNull($route);

			$route = $router->matchClass(Example::class, 'GET', ['no-params']);
			$this->assertNotNull($route);

			$route = $router->matchClass(Example::class, 'GET', ['one-param', 'value']);
			$this->assertNotNull($route);

			$route = $router->matchClass(Example::class, 'GET', ['two-params', 'value1', 'value2']);
			$this->assertNotNull($route);

			$route = $router->matchClass(Example::class, 'GET', ['optional-params']);
			$this->assertNull($route);

			$route = $router->matchClass(Example::class, 'GET', ['optional-params', 'value1']);
			$this->assertNotNull($route);

			$route = $router->matchClass(Example::class, 'GET', ['optional-params', 'value1', 'value2']);
			$this->assertNotNull($route);

			$route = $router->matchClass(Example::class, 'GET', ['optional-params', 'value1', 'value2', 'value3']);
			$this->assertNull($route);
		}

		public function testRoute(): void {
			$router = (new Router())
					->addRoute('GET', '/', fn() => 'test');
			$response = $router->dispatch(new Request('GET', '/'));
			$this->assertInstanceOf(Response::class, $response);
			$this->assertStringContainsString('test', $response->getBody());

			$response = $router->dispatch(new Request('GET', '/non-existent'));
			$this->assertNull($response);

			$response = $router->dispatch(new Request('POST', '/'));
			$this->assertNull($response);

			$response = $router->dispatch(new Request('POST', '/non-existent'));
			$this->assertNull($response);

			$response = $router->dispatch(new Request('invalid', '/'));
			$this->assertNull($response);

			$response = $router->dispatch(new Request('GET', 'invalid'));
			$this->assertNull($response);
		}

		public function testMatchNamespace(): void {
			$router = new Router();

			$route = $router->matchNamespace(new Request('GET', '/'), 'EmptyNamespace');
			$this->assertNull($route);

			$route = $router->matchNamespace(new Request('GET', '/'), 'WebsiteNamespace');
			$this->assertEquals([[Home::class, 'index'], [], []], $route);

			$route = $router->matchNamespace(new Request('GET', '/subpage'), 'WebsiteNamespace');
			$this->assertEquals([[Home::class, 'subpage'], [], []], $route);

			$route = $router->matchNamespace(new Request('GET', '/nonexistent'), 'WebsiteNamespace');
			$this->assertNull($route);

			$route = $router->matchNamespace(new Request('GET', '/contact'), 'WebsiteNamespace');
			$this->assertEquals([[Contact::class, 'getIndex'], [], []], $route);

			$route = $router->matchNamespace(new Request('GET', '/contact/subpage'), 'WebsiteNamespace');
			$this->assertEquals([[Contact::class, 'subpage'], [], []], $route);

			$route = $router->matchNamespace(new Request('GET', '/contact/nonexistent'), 'WebsiteNamespace');
			$this->assertNull($route);
		}

		public function testDashesInClassesAndMethods(): void {
			$router = new Router();

			$route = $router->matchNamespace(new Request('GET', '/privacy-policy'), 'WebsiteNamespace');
			$this->assertEquals([[Home::class, 'privacyPolicy'], [], []], $route);

			$route = $router->matchNamespace(new Request('GET', '/about-us'), 'WebsiteNamespace');
			$this->assertEquals([[AboutUs::class, 'index'], [], []], $route);
		}

		public function testMethodPrefixesHavePriority(): void {
			$router = new Router();

			$route = $router->matchNamespace(new Request('GET', '/contact'), 'WebsiteNamespace');
			$this->assertEquals([[Contact::class, 'getIndex'], [], []], $route);

			$route = $router->matchNamespace(new Request('POST', '/contact'), 'WebsiteNamespace');
			$this->assertEquals([[Contact::class, 'postIndex'], [], []], $route);
		}

		public function testDispatch(): void {
			$router = new Router('WebsiteNamespace');

			$response = $router->dispatch(new Request('GET', '/'));
			$this->assertEquals('WebsiteNamespace\Home::index', $response->getBody());

			$response = $router->dispatch(new Request('GET', '/contact'));
			$this->assertEquals('WebsiteNamespace\Contact::getIndex', $response->getBody());

			$response = $router->dispatch(new Request('POST', '/contact'));
			$this->assertEquals('WebsiteNamespace\Contact::postIndex', $response->getBody());
		}

		public function testS(): void {}
		
	}
}

namespace EmptyNamespace {
}

namespace WebsiteNamespace {
	class Home {
		public function index() { return __METHOD__; }
		public function subpage() { return __METHOD__; }
		public function privacyPolicy() { return __METHOD__; }
	}
	class AboutUs {
		public function index() { return __METHOD__; }
		public function subpage() { return __METHOD__; }
	}
	class Contact {
		public function index() { return __METHOD__; }
		public function getIndex() { return __METHOD__; }
		public function postIndex() { return __METHOD__; }
		public function subpage() { return __METHOD__; }
	}
}
