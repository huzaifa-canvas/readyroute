<?php

namespace App\Services;

use RuntimeException;

/**
 * Raised when Firebase reports that a device token no longer exists, which
 * happens once an app is uninstalled or its data is cleared. The token is
 * cleared rather than retried, because it will never work again.
 */
class DeadDeviceTokenException extends RuntimeException
{
}
