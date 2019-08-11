<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common\Contract\Exception;

use InvalidArgumentException as BaseInvalidArgumentException;

final class InvalidArgumentException extends BaseInvalidArgumentException implements Exception
{
}
