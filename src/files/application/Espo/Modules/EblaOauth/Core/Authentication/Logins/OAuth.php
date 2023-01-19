<?php

namespace Espo\Modules\EblaOauth\Core\Authentication\Logins;

use Espo\Core\{Api\Request,
    Authentication\AuthToken\AuthToken,
    Authentication\Login,
    Authentication\Login\Data,
    Authentication\Logins\Espo,
    Authentication\Result,
    Authentication\Result\FailReason,
    InjectableFactory,
    ORM\EntityManager,
    Utils\Log,
    Utils\Metadata
};
use Espo\Entities\User;
use Espo\Modules\EblaOauth\Classes\OAuth\Provider;
use Espo\Modules\EblaOauth\OAuthProviders\Oauth as OauthProvider;

class OAuth implements Login
{
    private EntityManager $entityManager;
    private Log $log;
    private Espo $baseLogin;
    private InjectableFactory $injectableFactory;
    private Metadata $metadata;

    public function __construct(
        EntityManager     $entityManager,
        Log               $log,
        Espo              $baseLogin,
        InjectableFactory $injectableFactory,
        Metadata          $metadata
    )
    {
        $this->entityManager = $entityManager;
        $this->log = $log;
        $this->baseLogin = $baseLogin;
        $this->injectableFactory = $injectableFactory;
        $this->metadata = $metadata;
    }

    /**
     * @param Data $data
     * @param Request $request
     * @return Result
     */
    public function login(Data $data, Request $request): Result
    {
        $username = $data->getUsername();
        $code = $data->getPassword() ?? '';
        $authToken = $data->getAuthToken();

        if ($authToken) {
            return $this->checkAuthToken($username, $authToken);
        }
        if (substr($username, 0, 1) !== '$') {
            return $this->baseLogin->login($data, $request);
        }

        $providerName = substr($username, 1);

        return $this->doLogin($code, $providerName);
    }

    /**
     * @param string|null $username
     * @param AuthToken $authToken
     * @return Result
     */
    protected function checkAuthToken(?string $username, AuthToken $authToken): Result
    {
        $user = $this->loginByToken($username, $authToken);

        if ($user) {
            return Result::success($user);
        } else {
            return Result::fail(FailReason::WRONG_CREDENTIALS);
        }
    }

    private function loginByToken(?string $username, AuthToken $authToken = null): ?User
    {
        if (isset($authToken) && isset($username)) {
            $userId = $authToken->getUserId();

            $user = $this->entityManager->getEntity('User', $userId);

            if ($user) {
                $tokenUsername = $user->get('userName');

                if (strtolower($username) != strtolower($tokenUsername)) {
                    $ip = $_SERVER['REMOTE_ADDR'] ?? '';

                    $this->log->alert(
                        'Unauthorized access attempt for user [' . $username . '] from IP [' . $ip . ']'
                    );

                    return null;
                }

                /** @var ?User */
                return $this->entityManager
                    ->getRDBRepository('User')
                    ->where([
                        'userName' => $username,
                    ])
                    ->findOne();
            }
        }

        return null;
    }

    /**
     * @param string $code
     * @param string $providerName
     * @return Result
     */
    protected function doLogin(string $code, string $providerName): Result
    {
        $provider = $this->getProvider($providerName);

        $response = $provider->getAccessTokenFromAuthorizationCode($code);

        if (!$response['id_token']) {
            return Result::fail(FailReason::CODE_NOT_VERIFIED);
        }

        $emailAddress = $provider->getEmailAddressFromResponseResult($response);

        $user = $this->entityManager
            ->getRDBRepository('User')
            ->where([
                'userName' => $emailAddress,
                'type!=' => ['api', 'system'],
            ])
            ->findOne();

        if (!$user) {
            $this->log->warning(
                "OAuth: Authentication success for user $providerName, but user is not created in EspoCRM."
            );

            return Result::fail(FailReason::USER_NOT_FOUND);
        }

        return Result::success($user);
    }

    protected function getProvider(string $providerName): Provider
    {
        /* @var Provider $className */
        $className = $this->metadata->get(['app', 'oAuthProviders', $providerName, 'implementationClassName']);

        if (!$className) {
            $className = OauthProvider::class;
        }

        return $this->injectableFactory->createWith($className, [
            'providerName' => $providerName,
        ]);
    }
}
