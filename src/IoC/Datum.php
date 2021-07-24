<?php

namespace Grithin\IoC;

class Datum implements SpecialTypeInterface{
	public function __construct($id){
		$this->id = $id;
	}
}