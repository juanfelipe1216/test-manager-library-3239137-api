<?php

namespace App\Adapters;

use App\Application\BusinessError;
use App\Application\Ports\BookCatalog;
use App\Domain\Book;
use App\Domain\Isbn;
use Illuminate\Support\Facades\DB;

final class PostgresBookCatalog implements BookCatalog
{
    private function query()
    {
        return DB::table('library.book as b')->join('library.category as c', 'c.id', '=', 'b.category_id')
            ->whereNull('b.deleted_at')->selectRaw('b.*, b.xmin::text AS version, c.name AS category_name');
    }

    private function response($r): array
    {
        return ['id' => $r->id, 'title' => $r->title, 'author' => $r->author, 'isbn' => $r->isbn,
            'category' => ['id' => $r->category_id, 'name' => $r->category_name],
            'total_copies' => (int) $r->total_copies, 'available_copies' => (int) $r->available_copies, 'cover_key' => $r->cover_key];
    }

    public function categories(): array
    {
        return DB::table('library.category as c')->select('c.id', 'c.name')
            ->selectSub(function ($query) {
                $query->from('library.book as b')->selectRaw('COUNT(*)::integer')
                    ->whereColumn('b.category_id', 'c.id')->whereNull('b.deleted_at');
            }, 'book_count')
            ->orderBy('c.name')->get()->map(fn ($r) => (array) $r)->all();
    }

    public function get(string $id): array
    {
        $r = $this->query()->where('b.id', $id)->first();
        if (! $r) {
            throw new BusinessError('NOT_FOUND', 'Libro no encontrado.', 404);
        }

        return ['entity' => new Book($r->id, $r->title, new Isbn($r->isbn), (int) $r->total_copies, (int) $r->available_copies),
            'version' => $r->version, 'category_id' => $r->category_id, 'category_name' => $r->category_name, 'author' => $r->author, 'response' => $this->response($r)];
    }

    public function search(array $filters): array
    {
        $q = $this->query();
        $term = trim((string) ($filters['q'] ?? ''));
        if ($term !== '') {
            $term = strtr($term, ['á' => 'a', 'é' => 'e', 'í' => 'i', 'ó' => 'o', 'ú' => 'u', 'ü' => 'u', 'ñ' => 'n', 'Á' => 'A', 'É' => 'E', 'Í' => 'I', 'Ó' => 'O', 'Ú' => 'U', 'Ü' => 'U', 'Ñ' => 'N']);
            $term = str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $term);
            $q->where(function ($query) use ($term) {
                $query->whereRaw("translate(b.title, 'ÁÉÍÓÚÜÑáéíóúüñ', 'AEIOUUNaeiouun') ILIKE ?", ['%'.$term.'%'])
                    ->orWhereRaw("translate(b.author, 'ÁÉÍÓÚÜÑáéíóúüñ', 'AEIOUUNaeiouun') ILIKE ?", ['%'.$term.'%']);
            });
        }
        if (! empty($filters['category_id'])) {
            $q->where('b.category_id', $filters['category_id']);
        }
        $page = (int) ($filters['page'] ?? 1);
        $size = (int) ($filters['size'] ?? 20);
        $total = (clone $q)->count();

        return ['items' => $q->orderBy('b.title')->orderBy('b.id')->offset(($page - 1) * $size)->limit($size)->get()->map(fn ($r) => $this->response($r))->all(),
            'page' => $page, 'size' => $size, 'total' => $total];
    }

    private function categoryExists(string $id): void
    {
        if (! DB::table('library.category')->where('id', $id)->exists()) {
            throw new BusinessError('VALIDATION_ERROR', 'La categoría no existe.');
        }
    }

    public function create(Book $book, array $data): void
    {
        $this->categoryExists($data['category_id']);
        DB::table('library.book')->insert(['id' => $book->id, 'title' => $book->title(), 'author' => trim($data['author']),
            'isbn' => $book->isbn->value, 'category_id' => $data['category_id'], 'total_copies' => $book->totalCopies(),
            'available_copies' => $book->availableCopies(), 'cover_key' => null, 'deleted_at' => null]);
    }

    public function save(Book $book, array $original, array $changes = []): void
    {
        if (isset($changes['category_id'])) {
            $this->categoryExists($changes['category_id']);
        }
        $data = ['title' => $book->title(), 'total_copies' => $book->totalCopies(), 'available_copies' => $book->availableCopies(),
            'deleted_at' => $book->deletedAt()?->format(DATE_ATOM)];
        foreach (['author', 'category_id', 'cover_key'] as $key) {
            if (array_key_exists($key, $changes)) {
                $data[$key] = $changes[$key];
            }
        }
        $count = DB::table('library.book')->where('id', $book->id)->whereNull('deleted_at')
            ->whereRaw('xmin::text = ?', [$original['version']])->update($data);
        if ($count !== 1) {
            throw new BusinessError('BOOK_NOT_AVAILABLE', 'Las existencias cambiaron; vuelve a intentarlo.', 409);
        }
    }
}
