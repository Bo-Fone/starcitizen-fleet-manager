<?php

namespace App\Security;

use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Client\OAuth2ClientInterface;
use KnpU\OAuth2ClientBundle\Security\Authenticator\SocialAuthenticator;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\User\UserProviderInterface;
use Wohali\OAuth2\Client\Provider\DiscordResourceOwner;

class DiscordAuthenticator extends SocialAuthenticator
{
    private $clientRegistry;
    private $entityManager;

    public function __construct(ClientRegistry $clientRegistry, EntityManagerInterface $entityManager)
    {
        $this->clientRegistry = $clientRegistry;
        $this->entityManager = $entityManager;
    }

    public function supports(Request $request)
    {
        return $request->attributes->get('_route') === 'connect_discord_check';
    }

    public function getCredentials(Request $request)
    {
        return $this->fetchAccessToken($this->getDiscordClient());
    }

    public function getUser($credentials, UserProviderInterface $userProvider)
    {
        /** @var DiscordResourceOwner $resource */
        $resource = $this->getDiscordClient()->fetchUserFromToken($credentials);

        return $userProvider->loadUserByUsername($resource->getId());
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception)
    {
        $message = strtr($exception->getMessageKey(), $exception->getMessageData());

        return new Response($message, 403);
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, $providerKey)
    {
        return new RedirectResponse('/', 302);
    }

    public function start(Request $request, AuthenticationException $authException = null)
    {
        return new RedirectResponse('/login', 302);
    }

    private function getDiscordClient(): OAuth2ClientInterface
    {
        return $this->clientRegistry->getClient('discord');
    }
}
