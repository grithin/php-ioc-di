<?php

namespace Grithin\IoC;

use Psr\Container\ContainerExceptionInterface;
use Psg\Sr3\ComplexException;

class ContainerException extends ComplexException implements ContainerExceptionInterface{}