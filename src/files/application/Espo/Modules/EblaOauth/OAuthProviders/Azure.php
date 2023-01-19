<?php

namespace Espo\Modules\EblaOauth\OAuthProviders;

use Espo\Entities\Integration;
use stdClass;

class Azure extends Oauth
{
    public function getClientInfo(): ?stdClass
    {
        /* @var Integration $integration */
        $integration = $this->entityManager->getEntity('Integration', $this->providerName);

        if (!$integration || !$integration->get('enabled')) return null;

        $tenantId = $integration->get('tenantId');

        $url = "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/authorize";

        return (object)[
            'path' => $url,
            "color" => $this->metadata->get(['app', 'oAuthProviders', $this->providerName, 'color']) ?? '#000000',
            'buttonTitle' => $this->metadata->get(['app', 'oAuthProviders', $this->providerName, 'buttonTitle']) ?? 'Sign in with ' . $this->providerName,
            'params' => [
                'client_id' => $integration->get('clientId'),
                'tenant_id' => $integration->get('tenantId'),
                'response_type' => 'code',
                'access_type' => 'offline',
                'scope' => $this->metadata->get(['app', 'oAuthProviders', $this->providerName, 'scopes']) ?? self::defaultScopes,
                'redirect_uri' => $this->config->get('siteUrl') . '/oauth-callback.php',
            ],
        ];
    }

    public function getAccessTokenFromAuthorizationCode(string $code): array
    {
        $integration = $this->entityManager->getEntity('Integration', $this->providerName);

        $clintId = $integration->get('clientId');
        $clientSecret = $integration->get('clientSecret');
        $tenantId = $integration->get('tenantId');
        $redirectUri = $this->config->get('siteUrl') . '/oauth-callback.php';
        $endpoint = 'https://login.microsoftonline.com/' . $tenantId . '/oauth2/v2.0/token';

        $requestData = 'grant_type=authorization_code&client_id=' . $clintId .
            '&redirect_uri=' . $redirectUri .
            '&code=' . $code .
            '&client_secret=' . $clientSecret;

        $this->log->debug($this->providerName . ': Authentication request data: ' . $requestData);

        $response = $this->postRequest($endpoint, $requestData);

        $this->log->debug($this->providerName . ': Authentication response data: ' . $response);

        return json_decode($response, true);
    }
}
