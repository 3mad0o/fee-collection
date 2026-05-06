<?php


namespace Emad\FeeCollection\Providers;

use Emad\FeeCollection\Contracts\AccountStatementServiceInterface;
use Emad\FeeCollection\Contracts\PaymentSplitterServiceInterface;
use Emad\FeeCollection\Contracts\StatementPdfServiceInterface;
use Emad\FeeCollection\Contracts\WalletServiceInterface;
use Emad\FeeCollection\Services\AccountStatementService;
use Emad\FeeCollection\Services\PaymentSplitterService;
use Emad\FeeCollection\Services\StatementPdfService;
use Emad\FeeCollection\Services\WalletService;
use Illuminate\Support\ServiceProvider;

class FeeCollectionServiceProvider extends ServiceProvider
{
    /**
     * Register services.
     */
    public function register(): void
    {
        $this->app->bind(AccountStatementServiceInterface::class, AccountStatementService::class);
        $this->app->bind(PaymentSplitterServiceInterface::class, PaymentSplitterService::class);
        $this->app->bind(StatementPdfServiceInterface::class, StatementPdfService::class);
        $this->app->bind(WalletServiceInterface::class, WalletService::class);
        $this->mergeConfigFrom(__DIR__ . '/../../config/fee_collection.php', 'fee_collection');
    }

    /**
     * Bootstrap services.
     */
    public function boot(): void
    {


        $this->loadMigrationsFrom(__DIR__ . '/../../database/migrations/');
        $this->loadViewsFrom(__DIR__ . '/../../resources/views', 'fee-collection');

        $this->publishes([
            __DIR__ . '/../../config/fee_collection.php' => config_path('fee_collection.php'),
        ], 'config');
        $this->publishes([
            __DIR__ . '/../../resources/views' => resource_path('views/vendor/fee-collection'),
        ], 'views');
    }
}
