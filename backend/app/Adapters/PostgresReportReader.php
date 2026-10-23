<?php

namespace App\Adapters;

use App\Application\Ports\ReportReader;
use Illuminate\Support\Facades\DB;

final class PostgresReportReader implements ReportReader
{
    public function mostBorrowed(string $from, string $to): array
    {
        $end = (new \DateTimeImmutable($to.'T00:00:00Z'))->modify('+1 day')->format(DATE_ATOM);

        return DB::table('library.loan_item as i')->join('library.loan as l', 'l.id', '=', 'i.loan_id')
            ->where('l.loaned_at', '>=', $from.'T00:00:00Z')->where('l.loaned_at', '<', $end)
            ->select('i.book_id', 'i.book_title', 'i.category_name')->selectRaw('COUNT(*)::integer AS times_borrowed')
            ->groupBy('i.book_id', 'i.book_title', 'i.category_name')->orderByDesc('times_borrowed')->orderBy('i.book_title')
            ->get()->map(fn ($r) => (array) $r)->all();
    }
}
