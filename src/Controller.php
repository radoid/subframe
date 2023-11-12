<?php
namespace Subframe;

/**
 * Base MVC controller implementation
 * @package Subframe PHP Framework
 */
class Controller {

	/** @var Request */
	protected $request;

	public function __construct(Request $request) {
		$this->request = $request;
	}

}
