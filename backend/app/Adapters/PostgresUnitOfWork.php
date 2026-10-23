<?php
namespace App\Adapters;
use App\Application\Ports\UnitOfWork;
use Illuminate\Support\Facades\DB;
final class PostgresUnitOfWork implements UnitOfWork {
    public function run(callable $operation): mixed { return DB::transaction($operation); }
}