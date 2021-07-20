<?php
namespace Grithin;

use Grithin\IoC\NotFound;

/**


service locator oddities considered while building
-	say we have a interface bound to a class, and that class is bound using singleton to some factory function.  In reality, we want the interface to point to the output of the factory.
-	circular dependencies
	-	DI requires SL, SL requires DI
	PHP will just error on recursion depth limit

*/


class ServiceLocator{
	public $services = [];
	public $services_reflections = [];
	public $services_options = []; #< options bound with the service

	public $singletons_ids = []; # list of singletons, as a hash/dictionary
	public $singletons = []; # list of singleton returns

	public $check_all; # whether SL will check all existing classes, or just those in services
	public $throw; # whether the throw exception or return
	public $silent = false; # whether the throw exception or return, for this call

	/** params
	< options >
		check_all: < t: bool , whether to check all classes >
		throw: < whether to throw exception or return it >
	*/
	public function __construct($options=[]){
		$defaults = ['check_all'=>false, 'throw'=>true];
		$options = array_merge($defaults, $options);
		$this->check_all = $options['check_all'];
		$this->throw = $options['throw'];

		$getter = function($key) { return $this->get_silent($key); };
		/**
		set up DI to use this ServiceLocator and to resolve parameters that are services
		*/
		$this->injector = new DependencyInjector($getter, ['service_resolve'=>true]);

		# bind PSR container
		$this->singleton('Psr\\Container\\ContainerInterface', 'Grithin\\PsrServiceLocator', ['with'=>[$this]]);
	}

	public function throw($exception){
		if($this->silent || !$this->throw){
			return $exception;
		}
		throw $exception;
	}

	public function injector_get(){
		return $this->injector;
	}
	public function injector_set($injector){
		$this->injector = $injector;
	}
	public function has($id){
		if(isset($this->services[$id])){
			return true;
		}
		return false;
	}
	/**
	If the singleton is already initializaed, the options won't have an effect
	*/
	public function singleton($id, $thing, $options=[]){
		$this->singletons[$id] = true;
		$this->bind($id, $thing, $options);
	}
	public function set($id, $thing){
		$this->bind($id, $thing);
	}

	public function bind($id, $thing=null, $options=[]){
		if($thing === null){
			$thing = $id;
		}
		$this->services[$id] = $thing;
		$this->services_options[$id] = $options;
	}
	public $getting = [];
	/** get, but don't throw exception if missing.  Return it instead .*/
	public function &get_silent($id, $options=[]){
		$this->silent = true;
		$result = $this->get($id);
		$this->silent = false;
		return $result;
	}

	public function &get(String $id, $options=[]){
		#+ check for circular dependency {
		if(count(array_keys($this->getting, $id)) > 1){
			$result = $this->throw(new IoC\Exception('Circular dependency'));
			return $result;
		}
		#+ }
		$this->getting[] = $id;
		try{
			$result = &$this->resolve($id);
		}catch(\Exception $e){
			/*
			need to clear the `getting` so if another exception handler
			continues the program.
			*/
			array_pop($this->getting);
			throw $e;
		}
		array_pop($this->getting);

		return $result;
	}
	/** params
	< id > < service id >
	< options > < options to pass in to the dependency injector when constructing the service >
	*/
	public function &resolve($id, $options=[]){
		if(isset($this->services[$id])){ #< service exists
			#+ check for singleton that has already been formed {
			$is_singleton = false;
			if(!empty($this->singletons_ids[$id])){
				if(isset($this->singletons[$id])){
					return $this->singletons[$id];
				}
				$is_singleton = true;
			}
			#+ }

			$service = $this->services[$id];
			$service_options = $this->services_options[$id];
			/* if both the service options exist and options were passed in, merge the two.
				Since options include other arrays `with` `default`, they will be overwritten
				on the merge, allowing the choice to clear out the options bound to the service
			*/
			$options = array_merge($service_options, $options);

			if(is_string($service)){
				#+ check if points to another service {
				if(isset($this->services[$service]) && $service != $id){
					return $this->get($service, $options);
				}
				#+ }

				#+ if class, make it and return {
				if(class_exists($service)){
					$resolved = $this->injector->class_construct($service, $options);
					if($is_singleton){
						$this->singletons[$id] = $resolved;
					}
					return $resolved;
				}
				#+ }
				$result = $this->throw(new Ioc\Exception('Could not make service from string "'.$service.'"'));
				return $result;
			}elseif($service instanceof \Closure){
				# probably a factory
				$resolved = $this->injector->call($service, $options);
				if($is_singleton){
					$this->singletons[$id] = &$resolved;
				}
				return $resolved;
			}elseif(is_object($service)){
				# services was provided as an object.  If it is not singletone, clone it
				if($is_singleton){
					$this->singletons[$id] = $service;
					return $service;
				}
				$clone = clone $service;
				return $clone;
			}else{
				# service is some othere data structure (like array).  Return reference if singleton
				if($is_singleton){
					$this->singletons[$id] = &$service;
					return $service;
				}
				return $service;
			}
		}else{ # service not found, try to resolve it
			$result = false;
			if(class_exists($id)){
				$reflect = new \ReflectionClass($id);
				$result = $this->by_class($id, $options);
			}elseif(interface_exists($id)){
				$reflect = new \ReflectionClass($id);
				$result = $this->by_interface($id, $options);
			}
			if(!$result){
				$result = $this->throw(new NotFound($id));
			}
			return $result;
		}
	}
	# cache the reflection instances
	public function reflection($id){
		if(!isset($this->services_reflections[$id])){
			if(class_exists($id)){
				$this->services_reflections[$id] = new \ReflectionClass($this->services[$id]);
			}else{
				$this->services_reflections[$id] = false;
			}
		}
		return $this->services_reflections[$id];
	}
	/** resolve a service by an interface */
	public function by_interface($interface, $options=[]){
		#+ check the existing services {
		foreach($this->services as $id=>$service){
			$reflect = $this->reflection($id);
			if($reflect && $reflect->implementsInterface($interface)
				&& !$reflect->isAbstract()
				&& !$reflect->isInterface()
				&& !$reflect->isTrait()
			){
				$this->bind($interface, $id);
				return $this->get($interface, $options);
			}
		}
		#+ }

		#+ check all existing classes {
		if($this->check_all){
			$classes = get_declared_classes();
			foreach($classes as $class) {
				$reflect = new \ReflectionClass($class);
				if($reflect->implementsInterface($interface)
					&& !$reflect->isAbstract()
					&& !$reflect->isInterface()
					&& !$reflect->isTrait()
				){
					$this->bind($interface, $class);
					return $this->get($class, $options);
				}
			}
		}
		#+ }
	}
	/** resolve a service by a class */
	public function by_class($class, $options=[]){
		#+ check the existing services {
		foreach($this->services as $id=>$service){
			$reflect = $this->reflection($id);
			if($reflect && ($reflect->getName() == $class || $reflect->isSubclassOf($class))){
				$this->bind($class, $id);
				return $this->get($class, $options);
			}
		}
		#+ }

		#+ just resolve itself to itself if possible {
		$reflect = new \ReflectionClass($class);
		if(!$reflect->isAbstract()
			&& !$reflect->isInterface()
			&& !$reflect->isTrait()
		){
			$this->bind($class);
			return $this->get($class, $options);
		}
		#+ }

		#+ check all existing classes {
		if($this->check_all){

			$class_target = $class;

			$classes = get_declared_classes();
			foreach($classes as $class) {
				$reflect = new \ReflectionClass($class);
				if(($reflect->getName() == $class_target || $reflect->isSubclassOf($class_target))
					&& !$reflect->isAbstract()
					&& !$reflect->isInterface()
					&& !$reflect->isTrait()
				){
					$this->bind($target_class, $class);
					return $this->get($target_class, $options);
				}
			}
		}
		#+ }
	}

}
