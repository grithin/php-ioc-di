<?php
namespace Grithin;

use Grithin\IoC\{MissingParam, MethodVisibility, ContainerException, Service, Datum, SpecialTypeInterface};


/**
Consideration

Say we have some parameter (App $app).  There are three scenarios of injection:
1.	we want to directly inject, not relying on a service locator
2.	we want to auto inject, using a service locator
3.	we want to inject something only if the service locator did not find a service
A `default` options allows for the 3rd and a `with` option allows the 1st.

if a parameter is optional, this could mean:
1.	code wants a dependency but shouldn't fail if it doesn't have it
2.	code wants to use it's own value unless it is forced to use provided value
In the case of #2, it is likely that the parameter is not typed to something DI will inject, and so, DI can attempt it anyway without problem (it will end up not injecting anything)

*/


class DependencyInjector{
	public $sl;
	/** set service locator */
	public function __construct($ServiceLocator){
		$this->sl = $ServiceLocator;
	}
	/**
	 * @param Bob $key
	 * @param mixed $options=[]
	 *
	 */
	public function get($key, $options=[]){
		return $this->sl->get($key, $options);
	}
	public function parameter_by_type($param){
		$type = $param->getType();
		if(!$type){
			return new ParamTypeUnusable;
		}
		if($type instanceof \ReflectionUnionType){
			# just get the first type of a union
			$type = $type->getTypes()[0];
		}
		if($type->isBuiltin()){ # don't try to resolve a Builtin type
			return new ParamTypeUnusable;
		}
		$type_name = $type->getName();
		return $this->get($type_name);
	}

	/** call with positional and named parameters */
	public function call_with($thing, $with, $options=[]){
		$options['with'] = $with;
		return $this->call($thing, $options);
	}

	/** call a thing with DI */
	# see parameters_resolve for $options

	/** General purpose call, for calling methods, functions, or constructing classes
	 * @param mixed $thing
	 * @param mixed $options=[]
	 *
	 * @return mixed
	 */
	public function call($thing, $options=[]){
		if(is_string($thing)){
			if(class_exists($thing)){
				return $this->class_construct($thing, $options);
			}
			if(function_exists($thing)){
				return $this->function_call($thing, $options);
			}
			$parts = explode('::', $thing);
			if(count($parts) == 2){
				return $this->uncertain_method_call($parts[0], $parts[1]);
			}
		}elseif($thing instanceof \Closure){
			return $this->function_call($thing, $options);
		}elseif(is_object($thing)){
			if($thing instanceof IoC\Call){
				return $this->call($thing->callable);
			}
			if(method_exists($thing, '__invoke')){
				return $this->method_call($thing, '__invoke', $options);
			}else{
				throw new IoC\InjectionUncallable(func_get_args(), 'object uncallable: '.Tool::flat_json_encode(func_get_args()));
			}
		}elseif(is_array($thing)){
			return $this->method_call($thing[0], $thing[1], $options);
		}
		throw new IoC\InjectionUncallable(func_get_args(), 'uncallable: '.Tool::flat_json_encode(func_get_args()));

	}
	public function uncertain_method_call($class, $method, $options=[]){
		if(class_exists($class) && method_exists($class, $method)){
			$reflect = new \ReflectionMethod($class, $method);
			if($reflect->isStatic()){
				return $this->static_method_call($class, $method, $options);
			}
		}
		# welp.  Try to get an object from SL
		$service = $this->sl->get($class, $options);
		if(is_object($service)){
			return $this->method_call($service, $method, $options);
		}else{
			throw new IoC\InjectionUncallable(func_get_args(), 'uncallable: '.Tool::flat_json_encode(func_get_args()));
		}
	}

	public function class_construct($class, $options=[]){
		$params = $this->class_resolve_parameters($class, $options);
		$reflect = new \ReflectionClass($class);
		return $reflect->newInstanceArgs($params);
	}

	# see parameters_resolve for $options
	public function class_resolve_parameters($class, $options=[]){
		$class = new \ReflectionClass($class);
		$reflect = $class->getConstructor();
		if(!$reflect){
			return [];
		}
		$params = $reflect->getParameters();
		return $this->parameters_resolve($params, $options);
	}
	public function static_method_call($class, $method, $options=[]){

		$reflect = new \ReflectionMethod($class, $method);
		if(!$reflect->isPublic()){
			throw new MethodVisibility([$class, $method], 'Method not visible: '.$method);
		}
		$params = $this->parameters_resolve($reflect->getParameters(), $options);

		return $reflect->invokeArgs(null, $params);
	}
	public function method_call($object, $method, $options=[]){
		#+ allow handling of static methods {
		if(!is_object($object)){
			return $this->static_method_call($object, $method, $options);
		}
		#+ }

		$reflect = new \ReflectionMethod($object, $method);
		if(!$reflect->isPublic()){
			throw new MethodVisibility([$object, $method], 'Method not visible: '.$method);
		}
		$params = $this->parameters_resolve($reflect->getParameters(), $options);
		return $reflect->invokeArgs($object, $params);
	}
	# see parameters_resolve for $options
	public function method_resolve_parameters($class, $method, $options=[]){
		$reflect = new \ReflectionMethod($class, $method);
		$params = $reflect->getParameters();
		return $this->parameters_resolve($params, $options);
	}
	public function function_call($function, $options=[]){
		$params = $this->function_resolve_parameters($function, $options);
		$reflect = new \ReflectionFunction($function);
		return $reflect->invokeArgs($params);
	}

	# see parameters_resolve for $options
	public function function_resolve_parameters($function, $options=[]){
		$reflect = new \ReflectionFunction($function);
		$params = $reflect->getParameters();
		return $this->parameters_resolve($params, $options);
	}

	/**
	< options >
		with: < dictionary of parameters to inject by position or name, ahead of type declaration injection >;
		defaults:  < dictionary of parameters to inject by position or name, if type declaration fails >;
	*/
	/** Based method definitio, services, and passed options, resolve parameter values
	 * @param \ReflectionParameter[] $params
	 * @param (array{
	 * 	with?: array,
	 * 	defaults?: array
	 *  }) $options
	 *
	 * @return array
	 */
	public function parameters_resolve($params, $options){
		$defaultss = ['defaults'=>[], 'with'=>[]];

		extract(array_merge($defaultss, $options), EXTR_SKIP);

		$params_to_inject = [];
		foreach($params as $k=>$param){
			#+ handle injecting name or positional specific parameters {
			if(isset($with[$k])){
				$with[$k] = $this->special_resolve($with[$k]);
				# don't insert wrong types
				if($this->type_match($param, $with[$k])){
					$params_to_inject[$k] = $with[$k];
					continue;
				}

			}
			$name = $param->getName();
			if(isset($with[$name])){
				$with[$name] = $this->special_resolve($with[$name]);
				# don't insert wrong types
				if($this->type_match($param, $with[$name])){
					$params_to_inject[$k] = $with[$name];
					continue;
				}
			}

			#+ }
			#+ handle type declared parameters {
			$container_exception = null;
			try{
				$value = $this->parameter_by_type($param);
				if(!($value instanceof ParamTypeUnusable)){ # check for fail signal
					$params_to_inject[$k] = $value;
					continue;
				}
			}catch(\Exception $e){
				if($e instanceof ContainerException){
					$container_exception = $e;
				}
			}
			#+ }
			#+ handle injection based variable starting with upper case {
			if(preg_match('/^[A-Z]/', $name)){
				/*
				Service is checked before datum because:
				-	naming should be handled so as not to conflict
				-	for something like Request, it can be expected to be
				injected either by type or by name.  As such, the two
				should match.  Must check service first to prevent
				mismatch, where a data key of `Request` is set
				*/
				try{
					# next see if a service exists
					$value = $this->sl->get($name);
					if($this->type_match($param, $value)){
						$params_to_inject[$k] = $value;
						continue;
					}
				}catch(\Exception $e){
					/* need to check if it is the same $name as requested.  It is
					possible that the name pointed to something else that did not resolve,
					and we should present that error.
					*/
					if($e instanceof ContainerException){
						if($e->getDetails() != $name){
							# some subsequent resolution failed, this exception should escalate
							throw $e;
						}
					}else{
						throw $e;
					}
				}
				try{
					# first see if a datum exists
					$value = $this->sl->data_locator->get($name);
					if($this->type_match($param, $value)){
						$params_to_inject[$k] = $value;
						continue;
					}
				}catch(\Exception $e){
					if($e instanceof ContainerException){
						if($e->getDetails() != $name){
							# some subsequent resolution failed, this exception should escalate
							throw $e;
						}
					}else{
						throw $e;
					}
				}
			}
			#+ }
			#+ use defaulting name or positional values {
			if(isset($defaults[$k])){
				$defaults[$k] = $this->special_resolve($defaultss[$k]);
				# don't insert wrong types
				if($this->type_match($param, $defaults[$k])){
					$params_to_inject[$k] = $defaults[$k];
					continue;
				}
			}
			if(isset($defaults[$name])){
				$defaults[$name] = $this->special_resolve($defaults[$name]);
				# don't insert wrong types
				if($this->type_match($param, $defaults[$name])){
					$params_to_inject[$k] = $defaults[$name];
					continue;
				}
			}

			#+ }
			#+ use the default provided by the function {
			if($param->isOptional()){
				$params_to_inject[$k] = $param->getDefaultValue();
				continue;
			}
			#+ }

			#+ parameter is missing, throw exception {\}
			if($container_exception){ # if SL returned exception, use that
				throw $container_exception;
			}
			throw new MissingParam($param, 'Missing parameter: '.$param->getName());
			#+ }

		}
		return $params_to_inject;
	}

	/** resolve parameters that are service objects */
	public function special_resolve($v){
		if($v instanceof SpecialTypeInterface){
			return $this->sl->interpret_special($v);
		}
		return $v;
	}

	/** check if a value matches the type of a parameter */
	public function type_match($param, $value){
		$type = $param->getType();
		if(!$type){
			return true;
		}
		if($type instanceof \ReflectionUnionType){
			$types = $type->getTypes();
		}else{
			$types = [$type];
		}
		$value_type = gettype($value);
		foreach($types as $type){
			if($type->isBuiltin()){
				if($value_type == $type->getName()){
					return true;
				}
			}elseif(is_a($value, $type->getName())){
				return true;
			}
		}
	}
}


class ParamTypeUnusable{}
