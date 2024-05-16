<?php
namespace Grithin;

use Grithin\Arrays;
use Grithin\IoC\{DataNotFound, Call, Factory, Datum};

/** simple container */
class DataLocator{
	public $data;
	public $lazy =[];
	public $factories = [];
	public $sl;
	public $injector;
	public $ids;
	public function __construct($sl, &$data){
		$this->sl = $sl;
		$this->injector = $sl->injector();
		$this->data = &$data;
	}
	public function get($id){
		# may be providing a Datum object instead of an id
		if($id instanceof Datum){
			return $this->resolve_datum($id);
		}
		if($this->has_datum($id)){
			return $this->get_datum($id);
		}elseif($this->has_lazy($id)){
			return $this->get_by_lazy($id);
		}elseif($this->has_factory($id)){
			return $this->get_by_factory($id);
		}
		throw new DataNotFound($id, 'Datum not found: '.$id);

	}
	public function get_datum($id){
		return $this->data[$id];
	}
	/** will be used once to produce data */
	public function get_by_lazy($id){
		$thing = $this->lazy[$id];
		if($thing){
			$this->data[$id] = $this->injector->call($thing);
			return $this->data[$id];
		}
	}
	/** will produce data more than once */
	public function get_by_factory($id){
		$thing = $this->factories[$id];
		if($thing){
			return $this->injector->call($thing);
		}
	}

	/**
	 * Set a data id to match against something.  "Something" can be data, a factory, or a function
	 * @param string $id
	 * @param mixed $thing data or closure or Call type
	 *
	 */
	public function set($id, $thing){
		if(isset($this->ids[$id])){
			$this->unset($id);
		}

		# if it's a function, set it as lazy
		if($thing instanceof \Closure || $thing instanceof Call){
			if($thing instanceof Factory){
				return $this->set_factory($id, $thing);
			}else{
				return $this->set_lazy($id, $thing);
			}
		}else{
			return $this->set_datum($id, $thing);
		}
	}
	/** resolve a Datum object to some value */
	public function resolve_datum($Datum){
		$value = $this->get($Datum->id);
		if($Datum->path){
			$value = Arrays::get($value, $Datum->path);
		}
		return $value;
	}
	public function set_datum($id, $thing){
		$this->data[$id] = $thing;
		$this->set_id($id);
	}
	public function set_lazy($id, $thing){
		$this->lazy[$id] = $thing;
		$this->set_id($id);
	}
	public function set_factory($id, $thing){
		$this->factories[$id] = $thing;
		$this->set_id($id);
	}
	public function set_id($id){
		$this->ids[$id] = true;
	}
	public function unset($id){
		unset($this->ids[$id]);
		unset($this->factories[$id]);
		unset($this->lazy[$id]);
		unset($this->data[$id]);
	}
	public function has($id){
		if($this->has_datum($id) || $this->has_lazy($id) || $this->has_factory($id)){
			return true;
		}
		return false;
	}
	public function has_datum($id){
		if(isset($this->data[$id]) || array_key_exists($id, $this->data)){
			return true;
		}
		return false;
	}
	public function has_lazy($id){
		if(isset($this->lazy[$id]) || array_key_exists($id, $this->lazy)){
			return true;
		}
		return false;
	}
	public function has_factory($id){
		if(isset($this->factories[$id]) || array_key_exists($id, $this->factories)){
			return true;
		}
		return false;
	}

}
