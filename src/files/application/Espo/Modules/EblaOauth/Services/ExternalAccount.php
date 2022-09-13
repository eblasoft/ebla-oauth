<?php

namespace Espo\Modules\EblaOauth\Services;

use Espo\Core\Exceptions\Error;
use Espo\Modules\EblaOauth\Classes\OAuth\ProviderFactory;
use stdClass;

class ExternalAccount extends \Espo\Services\ExternalAccount
{
    /**
     * @throws Error
     */
    public function getActionGetOAuth2Info(): ?stdClass
    {
        $provider = $this->getProviderFactory()->create();

        return $provider->getClientInfo();
    }

    protected function getProviderFactory(): ProviderFactory
    {
        return $this->injectableFactory->create(ProviderFactory::class);
    }
}
