<?php

namespace Tests\Integration;

use App\Adapters\PostgresBookCatalog;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Tests\TestCase;

final class CatalogFiltersTest extends TestCase
{
    private string $prefix;

    private string $novel;

    private string $history;

    protected function setUp(): void
    {
        parent::setUp();
        DB::beginTransaction();
        $this->prefix = 'Filtro'.Str::random(12);
        $this->novel = DB::table('library.category')->where('name', 'Novela')->value('id');
        $this->history = DB::table('library.category')->where('name', 'Historia')->value('id');
        foreach ([$this->novel, $this->novel, $this->history, $this->novel] as $index => $category) {
            DB::table('library.book')->insert([
                'id' => (string) Str::uuid(), 'title' => $this->prefix.' Crónica '.$index,
                'author' => 'Gabriel García Márquez', 'isbn' => (string) random_int(1000000000000, 9999999999999),
                'category_id' => $category, 'total_copies' => 1, 'available_copies' => 1,
                'cover_key' => null, 'deleted_at' => $index === 3 ? now() : null,
            ]);
        }
    }

    protected function tearDown(): void
    {
        DB::rollBack();
        parent::tearDown();
    }

    public function test_search_ignores_accents_case_and_surrounding_spaces(): void
    {
        $catalog = new PostgresBookCatalog;
        $result = $catalog->search(['q' => '  '.$this->prefix.' CRONICA  ']);
        $this->assertSame(3, $result['total']);
        $authors = $catalog->search(['q' => '  GARCIA MARQUEZ  ']);
        $ids = array_column($authors['items'], 'title');
        $this->assertNotEmpty($ids);
        $this->assertSame($authors['total'], $catalog->search(['q' => 'García Márquez'])['total']);
    }

    public function test_category_and_text_are_combined_and_deleted_books_are_excluded(): void
    {
        $catalog = new PostgresBookCatalog;
        $result = $catalog->search(['q' => $this->prefix, 'category_id' => $this->history]);
        $this->assertSame(1, $result['total']);
        $this->assertCount(1, $result['items']);
        $this->assertSame($this->history, $result['items'][0]['category']['id']);
        $this->assertSame(2, $catalog->search(['q' => $this->prefix, 'category_id' => $this->novel])['total']);
    }

    public function test_filtered_pagination_keeps_total_and_returns_distinct_books(): void
    {
        $catalog = new PostgresBookCatalog;
        $filters = ['q' => $this->prefix, 'category_id' => $this->novel, 'size' => 1];
        $first = $catalog->search($filters + ['page' => 1]);
        $second = $catalog->search($filters + ['page' => 2]);
        $this->assertSame(2, $first['total']);
        $this->assertSame(2, $second['total']);
        $this->assertCount(1, $first['items']);
        $this->assertCount(1, $second['items']);
        $this->assertNotSame($first['items'][0]['id'], $second['items'][0]['id']);
    }

    public function test_category_counts_match_active_books(): void
    {
        foreach ((new PostgresBookCatalog)->categories() as $category) {
            $expected = DB::table('library.book')->where('category_id', $category['id'])->whereNull('deleted_at')->count();
            $this->assertSame($expected, (int) $category['book_count']);
        }
    }
}
