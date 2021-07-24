<?php

namespace Grithin\IoC;

class Call implements SpecialTypeInterface{
	public $callable;
	public function __construct($callable){
		$this->callable = $callable;
	}
}