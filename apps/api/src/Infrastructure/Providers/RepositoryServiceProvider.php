<?php

declare(strict_types=1);

namespace Infrastructure\Providers;

use Domain\MunicipalityAmount\Repository\MunicipalityAmountRepositoryInterface;
use Domain\Reinfolib\Repository\ReinfolibApiClientInterface;
use GuzzleHttp\Client;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Repository\EloquentMunicipalityAmountRepository;
use Infrastructure\ExternalApi\ReinfolibApiClient;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        // Municipality Amount Repository
        $this->app->bind(
            MunicipalityAmountRepositoryInterface::class,
            EloquentMunicipalityAmountRepository::class
        );

        // Reinfolib API Client
        $this->app->singleton(ReinfolibApiClientInterface::class, function ($app) {
            return new ReinfolibApiClient(
                httpClient: new Client(),
                apiKey: config('services.reinfolib.api_key')
            );
        });
    }
}
