<?php

namespace App\Providers;

use App\Repositories\BranchRepository;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\CustomerDeviceRepositoryInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\DeviceBrandRepositoryInterface;
use App\Repositories\Contracts\DeviceModelRepositoryInterface;
use App\Repositories\Contracts\ProductCategoryRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\RepairTicketRepositoryInterface;
use App\Repositories\Contracts\ServiceCatalogRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\CustomerDeviceRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\DeviceBrandRepository;
use App\Repositories\DeviceModelRepository;
use App\Repositories\ProductCategoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\RepairTicketRepository;
use App\Repositories\ServiceCatalogRepository;
use App\Repositories\UserRepository;
use Illuminate\Support\ServiceProvider;

class RepositoryServiceProvider extends ServiceProvider
{
    /** @var array<class-string, class-string> */
    private const BINDINGS = [
        BranchRepositoryInterface::class => BranchRepository::class,
        UserRepositoryInterface::class => UserRepository::class,
        DeviceBrandRepositoryInterface::class => DeviceBrandRepository::class,
        DeviceModelRepositoryInterface::class => DeviceModelRepository::class,
        ServiceCatalogRepositoryInterface::class => ServiceCatalogRepository::class,
        ProductCategoryRepositoryInterface::class => ProductCategoryRepository::class,
        ProductRepositoryInterface::class => ProductRepository::class,
        CustomerRepositoryInterface::class => CustomerRepository::class,
        CustomerDeviceRepositoryInterface::class => CustomerDeviceRepository::class,
        RepairTicketRepositoryInterface::class => RepairTicketRepository::class,
    ];

    public function register(): void
    {
        foreach (self::BINDINGS as $interface => $concrete) {
            $this->app->bind($interface, $concrete);
        }
    }
}
