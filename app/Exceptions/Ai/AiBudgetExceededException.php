<?php

namespace App\Exceptions\Ai;

use RuntimeException;

class AiBudgetExceededException extends RuntimeException
{
    public function __construct()
    {
        parent::__construct('Limite mensal de AI atingido para esta empresa.');
    }
}

