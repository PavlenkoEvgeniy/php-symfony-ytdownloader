<?php

declare(strict_types=1);

namespace App\Tests\EventSubscriber;

use App\Enum\AuditAction;
use App\Enum\AuditActorType;
use App\EventSubscriber\AuthenticationAuditListener;
use App\Service\AuditLoggerInterface;
use PHPUnit\Framework\TestCase;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\BadCredentialsException;
use Symfony\Component\Security\Http\Authenticator\AuthenticatorInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Event\LoginFailureEvent;
use Symfony\Component\Security\Http\Event\LoginSuccessEvent;
use Symfony\Component\Security\Http\Event\LogoutEvent;

final class AuthenticationAuditListenerTest extends TestCase
{
    /**
     * Verifies a successful login is audited.
     */
    public function testLoginSuccessIsAudited(): void
    {
        $auditLogger = $this->createMock(AuditLoggerInterface::class);
        $auditLogger->expects($this->once())
            ->method('log')
            ->with(AuditAction::LOGIN)
        ;

        $token = $this->createStub(TokenInterface::class);
        $event = new LoginSuccessEvent(
            $this->createStub(AuthenticatorInterface::class),
            $this->createStub(Passport::class),
            $token,
            new Request(),
            null,
            'main',
        );

        (new AuthenticationAuditListener($auditLogger))->onLoginSuccess($event);
    }

    /**
     * Verifies a failed login is audited without the password.
     */
    public function testLoginFailureIsAudited(): void
    {
        $auditLogger = $this->createMock(AuditLoggerInterface::class);
        $auditLogger->expects($this->once())
            ->method('log')
            ->with(
                AuditAction::LOGIN_FAILED,
                null,
                null,
                null,
                null,
                false,
                ['username' => 'bad@test.local', 'reason' => 'Invalid credentials.'],
                AuditActorType::ANONYMOUS,
            )
        ;

        $request = new Request([], ['email' => 'bad@test.local']);
        $event   = new LoginFailureEvent(
            new BadCredentialsException(),
            $this->createStub(AuthenticatorInterface::class),
            $request,
            null,
            'api_v1',
        );

        (new AuthenticationAuditListener($auditLogger))->onLoginFailure($event);
    }

    /**
     * Verifies a logout with a token is audited.
     */
    public function testLogoutWithTokenIsAudited(): void
    {
        $auditLogger = $this->createMock(AuditLoggerInterface::class);
        $auditLogger->expects($this->once())
            ->method('log')
            ->with(AuditAction::LOGOUT)
        ;

        $event = new LogoutEvent(new Request(), $this->createStub(TokenInterface::class));

        (new AuthenticationAuditListener($auditLogger))->onLogout($event);
    }

    /**
     * Verifies a logout without a token is not audited.
     */
    public function testLogoutWithoutTokenIsIgnored(): void
    {
        $auditLogger = $this->createMock(AuditLoggerInterface::class);
        $auditLogger->expects($this->never())->method('log');

        $event = new LogoutEvent(new Request(), null);

        (new AuthenticationAuditListener($auditLogger))->onLogout($event);
    }
}
