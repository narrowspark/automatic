<?php
declare(strict_types=1);
namespace Narrowspark\Automatic\Common\Contract\Exception;

use UnexpectedValueException as BaseUnexpectedValueException;

final class UnexpectedValueException extends BaseUnexpectedValueException implements Exception
{
}
