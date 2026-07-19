<?php

declare(strict_types=1);

namespace Infrastructure\Providers;

use Domain\MunicipalityAmount\Repository\MunicipalityAmountRepositoryInterface;
use Illuminate\Support\ServiceProvider;
use Infrastructure\Repository\EloquentMunicipalityAmountRepository;

class RepositoryServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        $this->app->bind(
            MunicipalityAmountRepositoryInterface::class,
            EloquentMunicipalityAmountRepository::class
        );
    }
}
