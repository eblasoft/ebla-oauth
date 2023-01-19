<?php

namespace Espo\Modules\EblaOauth\Controllers;

use Espo\Core\{Api\Request, Exceptions\Error};
use Espo\Core\Exceptions\BadRequest;
use Espo\Modules\EblaOauth\Services\ExternalAccount as Service;
use stdClass;

class ExternalAccount extends \Espo\Controllers\ExternalAccount
{
    /**
     * @throws BadRequest
     * @throws Error
     */
    public function getActionGetOAuth2Info(Request $request): ?stdClass
    {
        /* @var $service Service */
        $service = $this->getRecordService();

        return $service->getOAuthProvidersData();
    }
}
