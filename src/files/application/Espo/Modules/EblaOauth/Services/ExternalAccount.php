<?php

namespace Espo\Modules\EblaOauth\Services;

use Espo\Core\Exceptions\Error;
use Espo\Modules\EblaOauth\Classes\OAuth\Provider;
use Espo\Modules\EblaOauth\OAuthProviders\Oauth;
use stdClass;

class ExternalAccount extends \Espo\Services\ExternalAccount
{
    /**
     * @throws Error
     */
    public function getOAuthProvidersData(): ?stdClass
    {
        $providers = $this->config->get('oAuthProviders');

        if (!$providers) {
            throw new Error("No `oAuthProvider` in config.");
        }

        $data = (object)[];
        foreach ($providers as $providerName) {
            $provider = $this->getProvider($providerName);

            $providerData = $provider->getClientInfo();

            if (!$providerData) continue;

            $data->$providerName = $providerData;
        }

        return $data;
    }

    protected function getProvider(string $providerName): Provider
    {
        /* @var Provider $className */
        $className = $this->metadata->get(['app', 'oAuthProviders', $providerName, 'implementationClassName']);

        if (!$className) {
            $className = Oauth::class;
        }

        return $this->injectableFactory->createWith($className, [
            'providerName' => $providerName,
        ]);
    }
}
