<?php

namespace Espo\Modules\EblaOauth\OAuthProviders\Azure;

use Espo\Core\Exceptions\Error;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Log;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Integration;
use Espo\Modules\EblaOauth\Classes\OAuth\Provider;
use Exception;
use stdClass;

class AzureOAuth implements Provider
{
    public const METHOD = 'Azure';

    protected EntityManager $entityManager;

    protected Metadata $metadata;

    protected Config $config;

    protected Log $log;

    public function __construct(
        EntityManager $entityManager,
        Metadata      $metadata,
        Config        $config,
        Log           $log
    )
    {
        $this->entityManager = $entityManager;
        $this->metadata = $metadata;
        $this->config = $config;
        $this->log = $log;
    }

    public function getClientInfo(): ?stdClass
    {
        /* @var $integration Integration */
        $integration = $this->entityManager->getEntity('Integration', self::METHOD);

        if (!$integration) {
            return null;
        }

        $scopes = $this->metadata->get(['app', 'oAuthProviders', self::METHOD, 'scopes']);

        $tenantId = $integration->get('tenantId');

        $url = "https://login.microsoftonline.com/$tenantId/oauth2/v2.0/authorize";

        return (object)[
            'path' => $url,
            'params' => [
                'client_id' => $integration->get('clientId'),
                'tenant_id' => $integration->get('tenantId'),
                'response_type' => 'code',
                'access_type' => 'offline',
                'scope' => $scopes,
                'redirect_uri' => $this->config->get('siteUrl') . '/oauth-callback.php',
            ]
        ];
    }

    public function getAccessTokenFromAuthorizationCode(string $code): array
    {
        $integration = $this->entityManager->getEntity('Integration', self::METHOD);

        $clintId = $integration->get('clientId');
        $clientSecret = $integration->get('clientSecret');
        $tenantId = $integration->get('tenantId');
        $redirectUri = $this->config->get('siteUrl') . '/oauth-callback.php';
        $endpoint = 'https://login.microsoftonline.com/' . $tenantId . '/oauth2/v2.0/token';

        $requestData = 'grant_type=authorization_code&client_id=' .
            $clintId . '&redirect_uri=' .
            $redirectUri . '&code=' .
            $code . '&client_secret=' .
            $clientSecret;

        $this->log->debug(self::METHOD . ': Authentication request data: ' . $requestData);

        $response = $this->postRequest($endpoint, $requestData);

        $this->log->debug(self::METHOD . ': Authentication response data: ' . $response);

        return json_decode($response, true);
    }

    /**
     * @throws Error
     */
    public function postRequest($endpoint, $data)
    {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        if ($cError = curl_error($ch)) {
            throw new Error('Something\'s gone wrong ' . $cError);
        }
        curl_close($ch);

        return $response;
    }

    public function getEmailAddressFromResponseResult($response): string
    {
        $email = "";

        try {
            $idToken = json_decode(base64_decode(str_replace('_', '/', str_replace('-', '+', explode('.', $response['id_token'])[1]))));

            if ($idToken && $idToken->email) {
                $this->log->debug(self::METHOD . ': id_token: ' . json_encode($idToken));

                $email = $idToken->email;
            }
        } catch (Exception $e) {
            $this->log->error(self::METHOD . ': Error while getting email address from id_token: ' . $e->getMessage());
        }

        return $email;
    }
}
