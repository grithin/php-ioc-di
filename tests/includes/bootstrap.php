<?php
namespace Bootstrap;


$_ENV['root_folder'] = realpath(dirname(__FILE__).'/../../').'/';
require $_ENV['root_folder'] . '/vendor/autoload.php';

use \Grithin\Debug;

\Grithin\GlobalFunctions::init();
