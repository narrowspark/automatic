<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Exception;

use InvalidArgumentException as BaseInvalidArgumentException;
use Narrowspark\Discovery\Common\Contract\Exception;

class InvalidArgumentException extends BaseInvalidArgumentException implements Exception
{
}
