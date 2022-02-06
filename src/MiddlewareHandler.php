<?php
namespace Subframe;

class MiddlewareHandler implements RequestHandlerInterface {

	private $middlewares;

	public function __construct(array $middlewares) {
		$this->middlewares = $middlewares;
	}

	public function handle(RequestInterface $request): ResponseInterface {
		if (!$this->middlewares)
			throw new \Exception('Middleware stack is empty.', 500);
		$current = $this->middlewares[0];
		$next = new MiddlewareHandler(array_slice($this->middlewares, 1));
		if ($current instanceof MiddlewareInterface)
			$response = $current->process($request, $next);
		else
			$response = $current($request, function ($request) use ($next) {return $next->handle($request);});

		return $response;
	}

}
