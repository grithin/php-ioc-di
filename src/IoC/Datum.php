<?php

namespace Grithin\IoC;

class Datum implements SpecialTypeInterface{
	/** params
	< id > < the id of the datum >
	< path > < once datum is resolved, the path to the actual data desired (when datum is a complex structure) >
	*/
	public function __construct($id, $path=''){
		$this->id = $id;
		$this->path = $path;
	}
}