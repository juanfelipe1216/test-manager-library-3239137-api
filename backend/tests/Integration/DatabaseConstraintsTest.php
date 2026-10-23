<?php
namespace Tests\Integration;
use Tests\TestCase;
use Illuminate\Support\Facades\DB;
use Illuminate\Database\QueryException;
final class DatabaseConstraintsTest extends TestCase {
    public function test_database_rejects_zero_total_even_outside_domain(): void {
        DB::beginTransaction();
        try {
            $category=DB::table('library.category')->value('id');
            DB::table('library.book')->insert(['id'=>(string)\Illuminate\Support\Str::uuid(),'title'=>'Test','author'=>'Test','isbn'=>'0000000000000','category_id'=>$category,'total_copies'=>0,'available_copies'=>0,'cover_key'=>null,'deleted_at'=>null]);
            $this->fail('La base aceptó total cero.');
        } catch(QueryException $e) { $this->assertSame('23514',$e->errorInfo[0]); }
        finally { DB::rollBack(); }
    }
    public function test_database_rejects_unknown_role(): void {
        DB::beginTransaction();
        try {
            DB::table('library.user')->insert(['id'=>(string)\Illuminate\Support\Str::uuid(),'username'=>'invalid-test','password_hash'=>'test-placeholder','role'=>'owner']);
            $this->fail('La base aceptó rol inválido.');
        } catch(QueryException $e) { $this->assertSame('23514',$e->errorInfo[0]); }
        finally { DB::rollBack(); }
    }
}
