<?php

declare(strict_types=1);

namespace App\EventSubscriber;

use App\Enum\AuditAction;
use App\Enum\AuditActorType;
use App\Service\AuditLoggerInterface;
use Symfony\Component\EventDispatcher\Attribute\AsEventListener;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final readonly class AuthenticationAuditListener
{
    public function __construct(
        private AuditLoggerInterface $auditLogger,
    ) {
    }

    #[AsEventListener(event: LoginSuccessEvent::class)]
    public function onLoginSuccess(LoginSuccessEvent $event): void
    {
        $this->auditLogger->log(
            action: AuditAction::LOGIN,
            targetType: 'user',
            targetLabel: $event->getUser()->getUserIdentifier(),
        );
    }

    #[AsEventListener(event: LoginFailureEvent::class)]
    public function onLoginFailure(LoginFailureEvent $event): void
    {
        $request = $event->getRequest();

        $this->auditLogger->log(
            action: AuditAction::LOGIN_FAILED,
            success: false,
            metadata: [
                'username' => $request->getPayload()->getString('email'),
                'reason'   => $event->getException()->getMessageKey(),
            ],
            actorType: AuditActorType::ANONYMOUS,
        );
    }

    #[AsEventListener(event: LogoutEvent::class)]
    public function onLogout(LogoutEvent $event): void
    {
        $token = $event->getToken();

        if (!$token instanceof TokenInterface) {
            return;
        }

        $this->auditLogger->log(
            action: AuditAction::LOGOUT,
            targetType: 'user',
            targetLabel: $token->getUser()?->getUserIdentifier(),
        );
    }
}
