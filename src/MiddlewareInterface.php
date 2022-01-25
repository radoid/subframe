<?php
namespace Subframe;

interface MiddlewareInterface {

	public function process(RequestInterface $request, RequestHandlerInterface $handler): ResponseInterface;

}