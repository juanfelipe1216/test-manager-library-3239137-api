<?php
namespace App\Providers;
use Illuminate\Support\ServiceProvider;
use App\Application\Ports;
use App\Adapters;
class AppServiceProvider extends ServiceProvider {
    public function register(): void {
        foreach([
            Ports\UserRepository::class=>Adapters\PostgresUserRepository::class,
            Ports\PasswordHasher::class=>Adapters\ArgonPasswordHasher::class,
            Ports\Clock::class=>Adapters\SystemClock::class,
            Ports\BookCatalog::class=>Adapters\PostgresBookCatalog::class,
            Ports\MemberRegistry::class=>Adapters\PostgresMemberRegistry::class,
            Ports\LoanLedger::class=>Adapters\PostgresLoanLedger::class,
            Ports\ReportReader::class=>Adapters\PostgresReportReader::class,
            Ports\UnitOfWork::class=>Adapters\PostgresUnitOfWork::class,
        ] as $port=>$adapter) $this->app->bind($port,$adapter);
        $this->app->bind(Ports\Clock::class,fn()=>new Adapters\SystemClock(app()->environment('local') ? storage_path('app/demo-clock.txt') : null));
        $this->app->bind(Ports\TokenIssuer::class,fn($app)=>new Adapters\JwtTokenIssuer((string)config('library.jwt_secret'),$app->make(Ports\Clock::class)));
        $this->app->bind(Ports\TokenVerifier::class,fn()=>new Adapters\JwtTokenVerifier((string)config('library.jwt_secret')));
        $this->app->bind(Ports\CoverStorage::class,fn()=>new Adapters\DiskCoverStorage(storage_path('app/covers')));
    }
    public function boot(): void {}
}