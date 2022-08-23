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

    public function postRequest($endpoint, $data)
    {
        $ch = curl_init($endpoint);
        curl_setopt($ch, CURLOPT_POST, 1);
        curl_setopt($ch, CURLOPT_POSTFIELDS, $data);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);

        $response = curl_exec($ch);
        if ($cError = curl_error($ch)) {
            echo $this->errorMessage($cError);
            exit;
        }
        curl_close($ch);

        return $response;
    }

    protected function errorMessage($message): string
    {
        return '<!DOCTYPE html>
            <html lang="en">
            <head>
                    <meta name="viewport" content="width=device-width, initial-scale=1">
                    <title>Error</title>
                    <link rel="stylesheet" type="text/css" href="style.css" />
            </head>
            <body>
            <div id="fatalError"><div id="fatalErrorInner"><span>Something\'s gone wrong!</span>' . $message . '</div></div>
            </body>
            </html>';
    }

    /**
     * @throws Error
     */
    public function getEmailAddressFromResponseResult($response): string
    {
        $accessToken = $response['access_token'];

        if ($response['id_token']) {
            try {
                $idToken = json_decode(base64_decode(explode('.', $response['id_token'])[1]));

                if ($idToken && $idToken->preferred_username) {
                    $this->log->debug(self::METHOD . ': id_token: ' . json_encode($idToken));

                    return $idToken->preferred_username;
                }
            } catch (Exception $e) {
                // Exception handling
                $this->log->error(self::METHOD . ': Error while getting email address from id_token: ' . $e->getMessage());
            }
        }

        $profileData = $this->sendGetRequest('https://graph.microsoft.com/v1.0/me/', $accessToken);

        $this->log->debug(self::METHOD . ': Profile data: ' . json_encode($profileData));

        if (!$profileData) {
            throw new Error('Profile data not received');
        }

        $profileData = json_decode($profileData, true);
        $email = $profileData['mail'] ?? $profileData['userPrincipalName'];

        if (!$email) {
            throw new Error('Email not received' . print_r($profileData));
        }

        $this->log->debug(self::METHOD . ': Founded email: ' . $email);

        return $email;
    }

    protected function sendGetRequest($URL, $accessToken)
    {
        $ch = curl_init($URL);
        curl_setopt($ch, CURLOPT_HTTPHEADER, array('Authorization: Bearer ' . $accessToken, 'Content-Type: application/json'));
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $response = curl_exec($ch);
        curl_close($ch);

        return $response;
    }
}
