<?php

namespace App\Http\Controllers;

use App\Application\BusinessError;
use App\Application\Ports\BookCatalog;
use App\Application\Ports\Clock;
use App\Application\Ports\CoverStorage;
use App\Application\Ports\LoanLedger;
use App\Application\Ports\MemberRegistry;
use App\Application\Ports\ReportReader;
use App\Application\Services\ChangeCoverService;
use App\Application\Services\CreateBookService;
use App\Application\Services\CreateLibrarianService;
use App\Application\Services\CreateMemberService;
use App\Application\Services\DeleteBookService;
use App\Application\Services\LendBooksService;
use App\Application\Services\ReturnItemService;
use App\Application\Services\UpdateBookService;
use Illuminate\Http\Request;
use Illuminate\Support\Str;

final class LibraryController
{
    private function filters(Request $r, array $extra = []): array
    {
        return $r->validate(array_merge(['page' => 'sometimes|integer|min:1', 'size' => 'sometimes|integer|min:1|max:50', 'q' => 'sometimes|nullable|string|max:250'], $extra));
    }

    private function uuid(string $id): void
    {
        if (! Str::isUuid($id)) {
            throw new BusinessError('NOT_FOUND', 'Recurso no encontrado.', 404);
        }
    }

    public function categories(BookCatalog $books)
    {
        return response()->json($books->categories());
    }

    public function books(Request $r, BookCatalog $books)
    {
        return response()->json($books->search($this->filters($r, ['category_id' => 'sometimes|uuid'])));
    }

    public function book(string $id, BookCatalog $books)
    {
        $this->uuid($id);

        return response()->json($books->get($id)['response']);
    }

    private function bookData(Request $r, bool $creating): array
    {
        $rules = ['title' => 'required|string|max:250', 'author' => 'required|string|max:200', 'category_id' => 'required|uuid', 'total_copies' => 'required|integer|min:1'];
        if ($creating) {
            $rules['isbn'] = ['required', 'string', 'regex:/^[0-9]{13}$/D'];
        }

        return $r->validate($rules);
    }

    public function createBook(Request $r, CreateBookService $service)
    {
        return response()->json($service->execute((string) Str::uuid(), $this->bookData($r, true)), 201);
    }

    public function updateBook(string $id, Request $r, UpdateBookService $service)
    {
        $this->uuid($id);

        return response()->json($service->execute($id, $this->bookData($r, false)));
    }

    public function deleteBook(string $id, DeleteBookService $service)
    {
        $this->uuid($id);
        $service->execute($id);

        return response()->noContent();
    }

    public function members(Request $r, MemberRegistry $members)
    {
        return response()->json($members->search($this->filters($r)));
    }

    public function member(string $id, MemberRegistry $members)
    {
        $this->uuid($id);

        return response()->json($members->get($id));
    }

    public function createMember(Request $r, CreateMemberService $service)
    {
        $data = $r->validate(['full_name' => 'required|string|max:200', 'document_number' => 'required|string|max:30']);

        return response()->json($service->execute((string) Str::uuid(), $data), 201);
    }

    public function createUser(Request $r, CreateLibrarianService $service)
    {
        $data = $r->validate(['username' => 'required|string|max:120', 'password' => 'required|string|max:512', 'role' => 'required|in:librarian']);

        return response()->json($service->execute((string) Str::uuid(), $data), 201);
    }

    public function lend(Request $r, LendBooksService $service)
    {
        $data = $r->validate(['member_id' => 'required|uuid', 'items' => 'required|array|min:1|max:3', 'items.*.book_id' => 'required|uuid']);
        $items = array_map(fn ($i) => ['id' => (string) Str::uuid(), 'book_id' => $i['book_id']], $data['items']);

        return response()->json($service->execute((string) Str::uuid(), $data['member_id'], $r->attributes->get('identity')['sub'], $items), 201);
    }

    public function loans(Request $r, LoanLedger $loans, Clock $clock)
    {
        $f = $this->filters($r, ['from' => 'sometimes|date_format:Y-m-d', 'to' => 'sometimes|date_format:Y-m-d', 'status' => 'sometimes|in:open,overdue,closed', 'member_id' => 'sometimes|uuid']);
        if (isset($f['from'],$f['to']) && $f['to'] < $f['from']) {
            throw new BusinessError('INVALID_DATE_RANGE', 'El rango de fechas está invertido.');
        }

        return response()->json($loans->search($f, $clock->now()));
    }

    public function loan(string $id, LoanLedger $loans, Clock $clock)
    {
        $this->uuid($id);

        return response()->json($loans->get($id, $clock->now()));
    }

    public function returnItem(string $loanId, string $itemId, ReturnItemService $service)
    {
        $this->uuid($loanId);
        $this->uuid($itemId);

        return response()->json($service->execute($loanId, $itemId));
    }

    public function overdue(LoanLedger $loans, Clock $clock)
    {
        return response()->json($loans->overdue($clock->now()));
    }

    public function report(Request $r, ReportReader $reports)
    {
        $f = $r->validate(['from' => 'required|date_format:Y-m-d', 'to' => 'required|date_format:Y-m-d']);
        if ($f['to'] < $f['from']) {
            throw new BusinessError('INVALID_DATE_RANGE', 'El rango de fechas está invertido.');
        }

        return response()->json(['from' => $f['from'], 'to' => $f['to'], 'rows' => $reports->mostBorrowed($f['from'], $f['to'])]);
    }

    public function setCover(string $id, Request $r, ChangeCoverService $service)
    {
        $this->uuid($id);
        $r->validate(['file' => 'required|file|mimetypes:image/jpeg,image/png|max:2048']);
        $file = $r->file('file');
        $service->execute($id, $file->getContent(), $file->getMimeType());

        return response()->noContent();
    }

    public function deleteCover(string $id, ChangeCoverService $service)
    {
        $this->uuid($id);
        $service->execute($id);

        return response()->noContent();
    }

    public function cover(string $id, BookCatalog $books, CoverStorage $storage)
    {
        $this->uuid($id);
        $key = $books->get($id)['response']['cover_key'];
        $file = $key === null ? null : $storage->read($key);
        if (! $file) {
            throw new BusinessError('NOT_FOUND','Portada no encontrada.',404);
        }

        return response($file['bytes'],200,['Content-Type' => $file['mime']]);
    }
}
