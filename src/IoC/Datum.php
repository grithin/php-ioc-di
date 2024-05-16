<?php

namespace Grithin\IoC;


/**
 * Primarily used as a flag indicator a specific Datum should be used
 */
class Datum implements SpecialTypeInterface{
	public $id;
	public $path;
	/**
	 * @param string $id the id of the datum >
	 * @param string $path='' once datum is resolved, the path to the actual data desired (when datum is a complex structure) >
	 */
	public function __construct($id, $path=''){
		$this->id = $id;
		$this->path = $path;
	}
}
