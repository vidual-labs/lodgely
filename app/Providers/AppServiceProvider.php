<?php

namespace App\Providers;

use App\Domain\Reporting\Contracts\AdMetricsSource;
use App\Importers\Contracts\LeadSource;
use App\Importers\Csv\CsvLeadSource;
use App\Importers\Email\ImapLeadSource;
use App\Importers\EmailMock\EmailMockLeadSource;
use App\Importers\GoogleMock\GoogleMockAdMetricsSource;
use App\Importers\Manual\ManualLeadSource;
use App\Importers\MetaMock\MetaMockAdMetricsSource;
use Illuminate\Pagination\Paginator;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    /**
     * Importer adapters known to the app. Adding a new source means: drop a
     * class in app/Importers/<Name>/, register it here, and add UI to wire it.
     *
     * @var array<string, class-string<LeadSource>>
     */
    public const IMPORTERS = [
        'csv'         => CsvLeadSource::class,
        'email_mock'  => EmailMockLeadSource::class,
        'email_imap'  => ImapLeadSource::class,
        'manual'      => ManualLeadSource::class,
    ];

    /**
     * Ad metrics source adapters. Each fetches aggregate spend/click data from
     * an ad platform for a given date and returns AdMetricsSnapshot DTOs.
     *
     * @var array<string, class-string<AdMetricsSource>>
     */
    public const AD_METRICS_SOURCES = [
        'meta_mock'   => MetaMockAdMetricsSource::class,
        'google_mock' => GoogleMockAdMetricsSource::class,
    ];

    public function register(): void
    {
        foreach (self::IMPORTERS as $class) {
            $this->app->singleton($class);
        }

        foreach (self::AD_METRICS_SOURCES as $class) {
            $this->app->singleton($class);
        }
    }

    public function boot(): void
    {
        Paginator::useTailwind();

        if ($this->app->environment('production')) {
            URL::forceScheme('https');
        }
    }
}
