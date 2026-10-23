<?php

namespace Tests\Integration;

use App\Application\Ports\TokenIssuer;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class DeleteMemberTest extends TestCase
{
    private string $memberId;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        $this->memberId = (string) Str::uuid();
        DB::table('library.member')->insert(['id' => $this->memberId, 'full_name' => 'Socio de prueba', 'document_number' => 'DEL-'.Str::random(16)]);
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    private function authenticate(string $role = 'admin'): void
    {
        $token = app(TokenIssuer::class)->issue((string) Str::uuid(), $role, 60);
        $this->withHeader('Authorization', 'Bearer '.$token);
    }

    public function test_admin_deletes_member_without_loans_and_cannot_delete_twice(): void
    {
        $this->authenticate();
        $this->deleteJson('/api/v1/members/'.$this->memberId)->assertNoContent();
        $this->assertFalse(DB::table('library.member')->where('id', $this->memberId)->exists());
        $this->getJson('/api/v1/members/'.$this->memberId)->assertNotFound();
        $this->deleteJson('/api/v1/members/'.$this->memberId)->assertNotFound()->assertJsonPath('code', 'NOT_FOUND');
    }

    public function test_unauthenticated_request_cannot_delete_member(): void
    {
        $this->deleteJson('/api/v1/members/'.$this->memberId)->assertUnauthorized();
        $this->assertTrue(DB::table('library.member')->where('id', $this->memberId)->exists());
    }

    public function test_librarian_cannot_delete_member(): void
    {
        $this->authenticate('librarian');
        $this->deleteJson('/api/v1/members/'.$this->memberId)->assertForbidden()->assertJsonPath('code', 'FORBIDDEN_ROLE');
        $this->assertTrue(DB::table('library.member')->where('id', $this->memberId)->exists());
    }

    public function test_invalid_and_missing_member_return_not_found(): void
    {
        $this->authenticate();
        $this->deleteJson('/api/v1/members/invalid')->assertNotFound();
        $this->deleteJson('/api/v1/members/'.Str::uuid())->assertNotFound();
    }

    private function addLoan(bool $returned): string
    {
        $userId = (string) Str::uuid();
        $bookId = (string) Str::uuid();
        $loanId = (string) Str::uuid();
        DB::table('library.user')->insert(['id' => $userId, 'username' => 'delete-test-'.Str::random(12), 'password_hash' => 'unused-test-hash', 'role' => 'librarian']);
        DB::table('library.book')->insert(['id' => $bookId, 'title' => 'Libro de prueba', 'author' => 'Autor de prueba', 'isbn' => (string) random_int(1000000000000, 9999999999999), 'category_id' => DB::table('library.category')->value('id'), 'total_copies' => 1, 'available_copies' => $returned ? 1 : 0, 'deleted_at' => null, 'cover_key' => null]);
        DB::table('library.loan')->insert(['id' => $loanId, 'member_id' => $this->memberId, 'loaned_by_user_id' => $userId, 'loaned_at' => now()]);
        DB::table('library.loan_item')->insert(['id' => (string) Str::uuid(), 'loan_id' => $loanId, 'book_id' => $bookId, 'book_title' => 'Libro de prueba', 'isbn' => '0000000000000', 'category_name' => 'Novela', 'due_at' => now()->addDays(14), 'returned_at' => $returned ? now() : null]);

        return $loanId;
    }

    private function assertHistoryProtected(bool $returned): void
    {
        $loanId = $this->addLoan($returned);
        $this->authenticate();
        $this->deleteJson('/api/v1/members/'.$this->memberId)->assertConflict()->assertJsonPath('code', 'MEMBER_HAS_LOANS')->assertJsonStructure(['code', 'message', 'details']);
        $this->assertTrue(DB::table('library.member')->where('id', $this->memberId)->exists());
        $this->assertTrue(DB::table('library.loan')->where('id', $loanId)->exists());
        $this->assertSame(1, DB::table('library.loan_item')->where('loan_id', $loanId)->count());
    }

    public function test_open_loans_block_deletion_and_keep_history(): void
    {
        $this->assertHistoryProtected(false);
    }

    public function test_returned_loans_also_block_deletion_and_keep_history(): void
    {
        $this->assertHistoryProtected(true);
    }
}
