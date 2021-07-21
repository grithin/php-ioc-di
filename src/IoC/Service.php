<?php

namespace Grithin\IoC;

class Service{
	public function __construct($id, $options=[]){
		$this->id = $id;
		$this->options = $options;
	}
}