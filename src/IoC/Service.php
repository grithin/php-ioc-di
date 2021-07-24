<?php

namespace Grithin\IoC;

class Service implements SpecialTypeInterface{
	public function __construct($id, $options=[]){
		$this->id = $id;
		$this->options = $options;
	}
}