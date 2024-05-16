<?php

namespace Grithin\IoC;

/**
 * An object to be used for later resolving a service
 */
class Service implements SpecialTypeInterface{
	public $id;
	public $options;

	/**
	 * Create a service object for use when generate a service by the dependency injector
	 * @param mixed $id the service locator id for the service
	 * @param mixed $options=[] initialization options for the service
	 */
	public function __construct($id, $options=[]){
		$this->id = $id;
		$this->options = $options;
	}
}
