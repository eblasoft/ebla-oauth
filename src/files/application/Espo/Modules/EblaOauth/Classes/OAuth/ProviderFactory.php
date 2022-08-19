<?php

namespace Espo\Modules\EblaOauth\Classes\OAuth;

use Espo\Core\Binding\Factory;
use Espo\Core\Exceptions\BadRequest;
use Espo\Core\Exceptions\Error;
use Espo\Core\InjectableFactory;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Metadata;
use RuntimeException;

class ProviderFactory implements Factory
{
    private Config $config;

    private Metadata $metadata;

    private InjectableFactory $injectableFactory;

    public function __construct(
        Config            $config,
        Metadata          $metadata,
        InjectableFactory $injectableFactory
    )
    {
        $this->config = $config;
        $this->metadata = $metadata;
        $this->injectableFactory = $injectableFactory;
    }

    /**
     * @throws Error
     */
    public function create(): Provider
    {
        $provider = $this->config->get('oAuthMethod');

        if (!$provider) {
            throw new Error("No `oAuthProvider` in config.");
        }

        /** @var ?class-string<Provider> */
        $className = $this->metadata->get(['app', 'oAuthProviders', $provider, 'implementationClassName']);

        if (!$className) {
            throw new Error("No `implementationClassName` for '{$provider}' provider.");
        }

        return $this->injectableFactory->create($className);
    }
}
