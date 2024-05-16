# IoC DI
A small IoC Dependency Injector and Service Locator with experimental features, including a Data Locator.

```sh
composer require grithin/ioc-di
```


## Use


### HEADER
For the code examples, use this header to run
```php
# HEADER #
use \Grithin\{ServiceLocator, DependencyInjector};
use \Grithin\IoC\{NotFound, ContainerException, MissingParam, Datum, Service, Call, Factory};

$sl = new ServiceLocator;
$di = $sl->injector();

```


### Basic
Service Locator injection of "NameInterface" typed parameter into newly constructed "Creature"
```php
## INSERT HEADER ##

class Creature{
	public $name;
	public function __construct(NameInterface $name){
		$this->name = $name;
	}
}
interface NameInterface{ }
class Name implements NameInterface{
	public $text;
	public function __construct($text='bob'){
		$this->text = $text;
	}
}

$sl = new ServiceLocator;
$di = $sl->injector();

# add "Name" into services
$sl->bind('Name');

# construct "Creature" with injected Name instance
$bob = $di->call('Creature');
echo $bob->name->text;
#> 'bob'
```

### Mixing Service Locator And Provided Parameters
Sometimes it is useful to allow the ServiceLocator to find one parameter, to but provide another parameter instead of using the ServiceLocator for it.


```php
## INSERT HEADER ##

class Creature{
	public $name;
	public $age;
	public function __construct(Name $name, Age $age){
		$this->name = $name;
		$this->age = $age;
	}
}
class Name{
	public $text;
	public function __construct($text='bob'){
		$this->text = $text;
	}
}
class Age{
	public $text;
	public function __construct($text='20'){
		$this->text = $text;
	}
}


# add "Age" into services
$sl->bind('Age');

# pass in the $age parameter by matching the parameter name
$bob = $di->call_with('Creature', ['age'=> new Age('30')]);
echo $bob->age->text;
#> 30

# pass in the $age parameter by matching the position
$bob = $di->call_with('Creature', [1=> new Age('30')]);
echo $bob->age->text;
#> 30
```

### Defaults
If the DependencyInjector can't resolve a dependency, you can provide a fallback default for the parameter.

```php
## INSERT HEADER ##

class Creature{
	public $name;
	public function __construct(NameInterface $name){
		$this->name = $name;
	}
}
interface NameInterface{ }
class Name implements NameInterface{
	public $text;
	public function __construct($text='bob'){
		$this->text = $text;
	}
}


# use a default for the "name" parameter
$bob = $di->call('Creature', ['defaults'=>['name'=> new Name('Sue')]]);
# since "Name" has not been registered as a service, ServiceLocator will fail to find it, and use the default provided
echo $bob->name->text;
#> sue
```

Because it may be undesirable to first instantiate a default prior to it being used, special classes are available to prevent pre-instantiation.

These special classes include
- \Grithin\IoC\Service
- \Grithin\IoC\Datum
- \Grithin\IoC\Call
- \Grithin\IoC\Factory

See the [Special Types](#special-types)





## Special Types
Using Grithin\IoC\SpecialTypeInterface

### Service Object
Used by: SL, DI
- If you want to point to a service with options.
- If you want to provide a parameter default as an object, but don't want to instantiate the object unless it is for default


Example of using `Service` for a default
```php
## INSERT HEADER ##

interface NameInterface{}
class Name implements NameInterface
{
	public $text;
	public function __construct($text = 'bob')
	{
		$this->text = $text;
	}
}
function sayName(NameInterface $name){
	echo $name->text;
}

# create a service with pre-set construction options
$name_service = new Service(Name::class, ['with'=>['text'=>'sue']]);

# injector will fall back on the default, $name_service, since no NameInterface is registered
$di->call('sayName', ['defaults'=> ['name'=>$name_service]]);
#> sue
```

### Datum
Used by: SL, DI
- If you want configure a service to be instantialized using datum that might be set later or change

```php
## INSERT HEADER ##

class Person{
	public $name;
	function __construct($name){
		$this->name = $name;
	}
	function sayName(){
		echo $this->name;
	}
}


# Bind service id Person to the Person class, with constructor parameter using Datum "name"
$sl->bind('Person', Person::class,  ['with'=>['name'=>new Datum('name')]]);

# define the Datum for the DataLocator
# this is one benefit to using Datum - it can be defined after a service is bound to use it
$sl->data_locator->set('name', 'sue');

function sayName(Person $person){
	return $person->sayName();
}

# getName will cause ServiceLocator to create Person instance, which causes DataLocator to pull "name"
$di->call('sayName');
#> sue

# change Datum resolution
$sl->data_locator->set('name', 'bob');

# a new Person instance will be made, and Datum "name" will now resolve to "bob"
$di->call('sayName');
#> bob
```

### Call
Used by: SL, DI, DL

- If you want to point to the result of some callable.


```php
## INSERT HEADER ##

class Person{
	public $name;
	function __construct($name){
		$this->name = $name;
	}
	function sayName(){
		echo $this->name;
	}
}


function getName() {
	return 'bob';
}

# Invoke getName when instantiating "Person" for parameter $name
$sl->bind('Person', Person::class,  ['with'=>['name'=>new Call('getName')]]);

function sayName(Person $person){
	return $person->sayName();
}

$di->call('sayName');
#> bob
```


### Factory
Used by: SL, DI, DL

- Used to indicate to DataLocator it should use thing as a factory.

Whereas, with `Call`, a datum will be resolved by calling, with `Factory`, it will be resolved by calling every time the data is accessed from the DataLocator

```php
## INSERT HEADER ##

$random = function(){ return rand(0,20000); };
$sl->data_locator->set('rand', new Call($random));
echo $sl->data_locator->get('rand');
#> 1231

# the DataLocator only runs the callable once, so it will return the same value here
echo $sl->data_locator->get('rand');
#> 1231

# a factory can be used to get new data every time
$sl->data_locator->set('rand', new Factory($random));

echo '|';
echo $sl->data_locator->get('rand');
echo '|';
#> 3421
echo $sl->data_locator->get('rand');
#> 8291
```





## Resolution Of Parameters

For some method definition, how does the injector resolve the injection values?

- if `with` variant/option is used (`call_with` and `call(..., ['with'=>[]]))`, use `with` array
	- by position
	- by name
- by type declaration (ServiceLocator)
- if parameter starts with uppercase, attempt to match against
	- Service
	- Datum
- if `defaults` option is present, use it
- use the default provided in the method/constructor definition




## Parameter Name Based Resolution
As a shortcut for well-known frequently used expected parameters, injection can use the name alone so long as that name starts with a capital letter.  And, in this case, both services and data are checked.

```php
## INSERT HEADER ##

$sl->data_locator->set('Server', 'localhost');

function printServer($Server){
	echo $Server;
}

$bob = $di->call('printServer');
#> localhost
```

Static analysis and autocompletion within editors has made this usually a bad option.



## ServiceLocator
_Beyond The Basics_


### Singleton
For something like a Database, it is desirable that only one primary instance be created.

```php
## INSERT HEADER ##
class Database{
	function __construct(){
		echo 'connecting... ';
		# ... expensive connection stuff ...
	}
	function query(){
		return 'bob';
	}
}

$sl->singleton('Database');

function getUserName(Database $db){
	echo $db->query();
}

$di->call('getUserName');
#> connecting... bob
$di->call('getUserName');
#> bob
```


You can also just provide an existing singleton object to the ServiceLocator
```php
## INSERT HEADER ##
class Database{
	public $host;
	function __construct($host = 'local_899') {
		$this->host = $host;
	}
}

function getDb(Database $db){
	echo $db->host;
}

# Can use the `Service` class to override service config
$config = ['i'=>0];
$sl->singleton('Database', new Database('remote'));
$di->call('getDb');
#> remote
```



### Links
It may be useful to link one service id to another service

```php
## INSERT HEADER ##
interface DatabaseInterface {}
class Database implements DatabaseInterface{
	function query(){
		return 'bob';
	}
}

$sl->bind('DatabaseInterface', 'LinkMiddleMan');
$sl->bind('LinkMiddleMan', 'Database');


function getUserName(DatabaseInterface $db){
	echo $db->query();
}

# ServiceLocator will resolve DatabaseInterface to LinkMiddleMan, and then LinkMiddleMan to Database
$di->call('getUserName');
#> bob

```


### Special Types
It may be useful to resolve a service using a special type.

Here, a function is used to get a service
```php
## INSERT HEADER ##

class Database{
	function query(){
		return 'bob';
	}
}
function getDatabase(){
	return new Database;
}

function getUserName(Database $db){
	echo $db->query();
}


# Using the `Call` class
$sl->bind('Database', new Call('getDatabase'));
$di->call('getUserName');
#> bob

# Using a closure
$sl->bind('Database', function(){ return getDatabase(); });
$di->call('getUserName');
#> bob
```


Using `Service` for special configuration
```php
interface DatabaseInterface {}
class Database implements DatabaseInterface {
	public $host;
	function __construct($host='local_899'){
		$this->host = $host;
	}
}

function getDb(DatabaseInterface $db){
	echo $db->host;
}


# Can use the `Service` class to override service config
$sl->bind('Database');
$sl->bind('DatabaseInterface', new Service('Database', ['with'=>['host'=>'remote_123']]));
$di->call('getDb');
#> remote_123

```



## Data Locator
A service locator for data.

```php
# basic
$sl->data_locator->set('x', '123');

# lazy loaded (called once)
$sl->data_locator->set('y', function(){ return '456'; });
function return_456(){
	return '456'
}
$sl->data_locator->set('y', new Call('return_456'));

# factory (called every time)
$sl->data_locator->set('y', new Factory('return_456'));
```


## Notes

By default, SL does not check all classes to resolve an interface or abstract class, it only checks what is within the services (by id) or available without further file inclusion.  You can make it check everything though:
```php
$sl = new ServiceLocator(['check_all'=>true]);
```
