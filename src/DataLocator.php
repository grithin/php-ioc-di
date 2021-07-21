<?php
namespace Grithin;

use Grithin\IoC\{DataNotFound};

/** simple container */
class DataLocator{
	public $data;
	public function __construct(&$data){
		$this->data = &$data;
	}
	public function get($id){
		if(!$this->has($id)){
			throw new DataNotFound($id);
		}
		return $this->data[$id];
	}
	public function set($id, $thing){
		$this->data[$id] = $thing;
	}
	public function has($id){
		if(isset($this->data[$id]) || array_key_exists($id, $this->data)){
			return true;
		}
		return false;
	}

}