<?php
namespace Subframe;

use Closure;
use Exception;
use Throwable;

/**
 * Represents the outermost layer of the application, allowing for middleware and routes definition
 */
class App {

	/**
	 * The router
	 */
	private Router $router;

	/**
	 * The middleware stack
	 */
	private array $middlewares;


	/**
	 * The constructor
	 * @param array $middlewares The middleware stack
	 */
	public function __construct(array $middlewares = []) {
		$this->middlewares = $middlewares;
		$this->router = new Router();
	}

	/**
	 * Defines a route namespace, containing routes in its classes, to the routing list
	 */
	public function addNamespace(string $namespace, array $classArgs = []): self {
		$this->router->addNamespace($namespace, $classArgs);
		return $this;
	}

	/**
	 * Defines a route
	 */
	public function addRoute(string $method, string $uri, $callable, array $classArgs = []): self {
		$this->router->addRoute($method, $uri, $callable, $classArgs);
		return $this;
	}

	/**
	 * Defines a view route, that only presents a view
	 */
	public function addView(string $uri, string $filename, array $data = []): self {
		$this->router->addView($uri, $filename, $data);
		return $this;
	}

	/**
	 * Defines a view that is to be presented in case of an uncaught exception
	 * Specific views can be designated for specific exception codes, by providing an array with codes as keys
	 * @param string|array $filename The view's filename
	 */
	public function catchView($filename, array $data = []): self {
		$middleware = function (Request $request, Closure $next) use ($filename, $data): Response {
			try {
				$response = $next($request);
			} catch (Throwable $e) {
				$code = $e->getCode();
				$code = (is_numeric($code) && $code >= 400 && $code < 600 ? $code : 500);
				$data['error'] = $e->getMessage();
				if ($code >= 500)
					error_log($e . ' at ' . $request->getPathAndQueryString());
				if (is_array($filename))
					$filename = $filename[$code] ?? $filename[0] ?? null;
				if ($request->acceptsJson())
					$response = Response::fromJson(['error' => $e->getMessage()], $code);
				else
					$response = Response::fromView($filename, $data, $code);
			}
			return $response
					->addHeader('Cache-Control: max-age=0, must-revalidate');
		};
		array_unshift($this->middlewares, $middleware);

		return $this;
	}

	/**
	 * Defines a closure that is to be called in case of an uncaught exception
	 */
	public function catch(Closure $closure): self {
		array_unshift($this->middlewares, function (Request $request, Closure $next) use ($closure): Response {
			try {
				$response = $next($request);
			} catch (Throwable $e) {
				$response = $closure($request, $e);
			}
			return $response;
		});

		return $this;
	}

	/**
	 * Starts processing the given request
	 */
	public function handle(Request $request): void {
		$response = $this->handleNext($request);
		$response->send();
	}

	/**
	 * Pops the next middleware from stack and executes it
	 * @throws Exception
	 */
	private function handleNext(Request $request): Response {
		if ($this->middlewares) {
			$current = array_shift($this->middlewares);
			$response = $current($request, function ($request) {
				return $this->handleNext($request);
			});
		} else {
			$response = $this->router->dispatch($request);
			if (!$response)
				throw new Exception('Page not found.', 404);
		}

		return $response;
	}

}