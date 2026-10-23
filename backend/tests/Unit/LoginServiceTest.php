<?php

namespace Tests\Unit;

use App\Application\Ports\PasswordHasher;
use App\Application\Ports\TokenIssuer;
use App\Application\Ports\UserRepository;
use App\Application\Services\LoginService;
use App\Domain\User;
use DomainException;
use PHPUnit\Framework\TestCase;

final class LoginServiceTest extends TestCase
{
    public function test_valid_login_returns_eight_hour_token(): void
    {
        $user = new User(
            'user-1', 'ana', 'test-only-placeholder', 'librarian',
        );

        $users = $this->createMock(UserRepository::class);
        $users->expects($this->once())
            ->method('findByUsername')
            ->with('ana')
            ->willReturn($user);

        $passwords = $this->createMock(PasswordHasher::class);
        $passwords->expects($this->once())
            ->method('verify')
            ->with('test-password', 'test-only-placeholder')
            ->willReturn(true);

        $tokens = $this->createMock(TokenIssuer::class);
        $tokens->expects($this->once())
            ->method('issue')
            ->with('user-1', 'librarian', 28800)
            ->willReturn('test-token');

        $service = new LoginService($users, $passwords, $tokens);
        $result = $service->execute('Ana ', 'test-password');

        $this->assertSame([
            'access_token' => 'test-token',
            'token_type' => 'Bearer',
            'expires_in' => 28800,
            'role' => 'librarian',
        ], $result);
    }

    public function test_unknown_user_does_not_receive_token(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->method('findByUsername')->willReturn(null);

        $passwords = $this->createMock(PasswordHasher::class);
        $passwords->expects($this->never())->method('verify');

        $tokens = $this->createMock(TokenIssuer::class);
        $tokens->expects($this->never())->method('issue');

        $service = new LoginService($users, $passwords, $tokens);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Credenciales inválidas.');

        $service->execute('unknown', 'test-password');
    }

    public function test_wrong_password_does_not_receive_token(): void
    {
        $users = $this->createMock(UserRepository::class);
        $users->method('findByUsername')->willReturn(
            new User('user-1', 'ana', 'test-only-placeholder', 'librarian'),
        );

        $passwords = $this->createMock(PasswordHasher::class);
        $passwords->method('verify')->willReturn(false);

        $tokens = $this->createMock(TokenIssuer::class);
        $tokens->expects($this->never())->method('issue');

        $service = new LoginService($users, $passwords, $tokens);

        $this->expectException(DomainException::class);
        $this->expectExceptionMessage('Credenciales inválidas.');

        $service->execute('ana', 'wrong-password');
    }
}