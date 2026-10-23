<?php

namespace Tests\Unit;

use App\Domain\Member;
use App\Domain\User;
use InvalidArgumentException;
use PHPUnit\Framework\TestCase;

final class PeopleTest extends TestCase
{
    public function test_username_is_trimmed_and_lowercased(): void
    {
        $user = new User('user-1', 'Ana ', 'test-only-placeholder', 'librarian');

        $this->assertSame('ana', $user->username);
    }

    public function test_invalid_role_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new User('user-1', 'ana', 'test-only-placeholder', 'owner');
    }

    public function test_empty_username_is_rejected(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new User('user-1', '   ', 'test-only-placeholder', 'librarian');
    }

    public function test_password_hash_is_not_a_public_property(): void
    {
        $user = new User('user-1', 'ana', 'test-only-placeholder', 'librarian');

        $this->assertArrayNotHasKey('passwordHash', get_object_vars($user));
    }

    public function test_member_values_are_trimmed(): void
    {
        $member = new Member('member-1', ' Ana Pérez ', ' 12345 ');

        $this->assertSame('Ana Pérez', $member->fullName);
        $this->assertSame('12345', $member->documentNumber);
    }

    public function test_member_requires_document(): void
    {
        $this->expectException(InvalidArgumentException::class);

        new Member('member-1', 'Ana Pérez', ' ');
    }
}