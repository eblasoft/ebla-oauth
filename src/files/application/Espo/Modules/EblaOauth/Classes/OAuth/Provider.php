<?php

namespace Espo\Modules\EblaOauth\Classes\OAuth;

use stdClass;

/**
 * An SMS sender.
 */
interface Provider
{
    public function getClientInfo(): ?stdClass;

    public function getAccessTokenFromAuthorizationCode(string $code): array;

    public function getEmailAddressFromResponseResult($response): string;
}
