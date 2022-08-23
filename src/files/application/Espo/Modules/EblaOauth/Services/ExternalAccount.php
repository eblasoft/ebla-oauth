<?php

namespace Espo\Modules\EblaOauth\Services;

use Espo\Modules\EblaOauth\Classes\OAuth\ProviderFactory;
use stdClass;

class ExternalAccount extends \Espo\Services\ExternalAccount
{
    public function getActionGetOAuth2Info(string $method): ?stdClass
    {
        $provider = $this->getProviderFactory()->create();

        return $provider->getClientInfo();
    }

    protected function getProviderFactory(): ProviderFactory
    {
        return $this->injectableFactory->create(ProviderFactory::class);
    }
}
