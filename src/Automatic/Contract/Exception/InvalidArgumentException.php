<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Contract\Exception;

use InvalidArgumentException as BaseInvalidArgumentException;

class InvalidArgumentException extends BaseInvalidArgumentException implements Exception
{
}
