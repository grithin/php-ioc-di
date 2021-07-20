<?php
namespace Grithin;

use Grithin\IoC\NotFound;

/** Provided as a proxy to SL for things requiring \Psr\Container\ContainerInterface */

class PsrServiceLocator implements \Psr\Container\ContainerInterface{
	public function __construct($sl){
		$this->sl = $sl;
	}
	public function get(string $id){
		return $this->sl->get($id);
	}
	public function has(string $id): bool {
		return $this->sl->has($id);
	}
}