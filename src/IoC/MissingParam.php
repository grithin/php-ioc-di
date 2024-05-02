<?php

namespace Grithin\IoC;

use Psg\Sr3\ComplexException;

class MissingParam extends ComplexException implements InjectionCallExceptionInterface{};