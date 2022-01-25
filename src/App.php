<?php
namespace Subframe;

/**
 * Represents the application itself
 */
class App {

	/** @var array */
	private $middlewares;

	/** @var Router */
	private $router;


	public function __construct(array $middlewares = []) {
		$this->router = new RouterMiddleware;
		$this->middlewares = $middlewares;
	}

	public function namespace(string $namespace, array $classArgs = []): self {
		$this->router->addNamespace($namespace, $classArgs);
		return $this;
	}

	public function get(string $uri, $callable, array $classArgs = []): self {
		$this->router->addRoute('GET', $uri, $callable, $classArgs);
		return $this;
	}

	public function post(string $uri, $callable, array $classArgs = []): self {
		$this->router->addRoute('POST', $uri, $callable, $classArgs);
		return $this;
	}

	public function put(string $uri, $callable, array $classArgs = []): self {
		$this->router->addRoute('PUT', $uri, $callable, $classArgs);
		return $this;
	}

	public function delete(string $uri, $callable, array $classArgs = []): self {
		$this->router->addRoute('DELETE', $uri, $callable, $classArgs);
		return $this;
	}

	public function options(string $uri, $callable, array $classArgs = []): self {
		$this->router->addRoute('OPTIONS', $uri, $callable, $classArgs);
		return $this;
	}

	public function view(string $uri, string $filename, array $data = []): self {
		$this->router->addView($uri, $filename, $data);
		return $this;
	}

	public function add($middleware) {
		$this->middlewares[] = $middleware;
	}

	public function handleRequestUri() {
		$this->handle(Request::fromRequestUri());
	}

	public function handleRelativeRequestUri() {
		$this->handle(Request::fromRelativeRequestUri());
	}

	public function handlePathInfo() {
		$this->handle(Request::fromPathInfo());
	}

	public function handle(RequestInterface $request) {
		$handler = new MiddlewareHandler(array_merge($this->middlewares, [$this->router]));
		$response = $handler->handle($request);
		$response->output();
	}

}