<?php

declare(strict_types=1);

namespace Wenbo\PToken\Hyperf\Exceptions;

use Hyperf\Server\Exception\ServerException;

class PTokenAuthException extends ServerException
{
    public function __construct(string $message = 'Authentication failed', int $code = 0)
    {
        parent::__construct($message, 401);
    }
}
