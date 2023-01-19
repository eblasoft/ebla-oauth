<?php

namespace Espo\Modules\EblaOauth\OAuthProviders;


use Espo\Core\Exceptions\Error;
use Espo\Core\ORM\EntityManager;
use Espo\Core\Utils\Config;
use Espo\Core\Utils\Log;
use Espo\Core\Utils\Metadata;
use Espo\Entities\Integration;
use Espo\Modules\EblaOauth\Classes\OAuth\Provider;
use Exception;
use stdClass;

class Oauth implements Provider
{
    public const defaultScopes = "openid email";
    public string $providerName;
    protected EntityManager $entityManager;
    protected Metadata $metadata;
    protected Config $config;
    protected Log $log;

    public function __construct(
        EntityManager $entityManager,
        Metadata      $metadata,
        Config        $config,
        Log           $log,
        string        $providerName
    )
    {
        $this->entityManager = $entityManager;
        $this->metadata = $metadata;
        $this->config = $config;
        $this->log = $log;
        $this->providerName = $providerName;
    }

    public function getClientInfo(): ?stdClass
    {
        /* @var Integration $integration */
        $integration = $this->entityManager->getEntity('Integration', $this->providerName);

        if (!$integration || !$integration->get('enabled')) return null;

        return (object)[
            'path' => $this->metadata->get(['app', 'oAuthProviders', $this->providerName, 'authorizationUrl']),
            "color" => $this->metadata->get(['app', 'oAuthProviders', $this->providerName, 'color']) ?? '#000000',
            'buttonTitle' => $this->metadata->get(['app', 'oAuthProviders', $this->providerName, 'buttonTitle']) ?? 'Sign in with ' . $this->providerName,
            'params' => [
                'client_id' => $integration->get('clientId'),
                'response_type' => 'code',
                'access_type' => 'offline',
                'scope' => $this->metadata->get(['app', 'oAuthProviders', $this->providerName, 'scopes']) ?? self::defaultScopes,
                'redirect_uri' => $this->config->get('siteUrl') . '/oauth-callback.php',
            ],
        ];
    }

    /**
     * @throws Error
     */
    public function getAccessTokenFromAuthorizationCode(string $code): array
    {
        /* @var $integration Integration */
        $integration = $this->entityManager->getEntity('Integration', $this->providerName);

        $clintId = $integration->get('clientId');
        $clientSecret = $integration->get('clientSecret');
        $redirectUri = $this->config->get('siteUrl') . '/oauth-callback.php';
        $endpoint = $this->metadata->get(['app', 'oAuthProviders', $this->providerName, 'tokenUrl']);

        $requestData = 'grant_type=authorization_code&client_id=' .
            $clintId . '&redirect_uri=' .
            $redirectUri . '&code=' .
            $code . '&client_secret=' .
            $clientSecret;

        $this->log->debug($this->providerName . ': Authentication request data: ' . $requestData);

        $response = $this->postRequest($endpoint, $requestData);

        $this->log->debug($this->providerName . ': Authentication response data: ' . $response);

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
                $this->log->debug($this->providerName . ': id_token: ' . json_encode($idToken));

                $email = $idToken->email;
            }
        } catch (Exception $e) {
            $this->log->error($this->providerName . ': Error while getting email address from id_token: ' . $e->getMessage());
        }

        return $email;
    }
}
