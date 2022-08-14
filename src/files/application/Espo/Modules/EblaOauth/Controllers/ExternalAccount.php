<?php

namespace Espo\Modules\EblaOauth\Controllers;

use Espo\Core\{Api\Request,};
use Espo\Core\Exceptions\BadRequest;
use stdClass;

class ExternalAccount extends \Espo\Controllers\ExternalAccount
{
    /**
     * @throws BadRequest
     */
    public function getActionGetOAuth2Info(Request $request): ?stdClass
    {
        $method = $request->getQueryParam('method');

        if ($method === null) {
            throw new BadRequest();
        }

        return $this->getRecordService()->getActionGetOAuth2Info($method);
    }
}
