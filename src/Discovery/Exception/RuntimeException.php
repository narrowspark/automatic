<?php
declare(strict_types=1);
namespace Narrowspark\Discovery\Exception;

use Narrowspark\Discovery\Common\Contract\Exception;
use RuntimeException as BaseRuntimeException;

class RuntimeException extends BaseRuntimeException implements Exception
{
}
