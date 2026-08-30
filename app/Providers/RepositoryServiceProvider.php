<?php

namespace App\Providers;

use App\Repositories\AcquisitionRepository;
use App\Repositories\BranchRepository;
use App\Repositories\Contracts\AcquisitionRepositoryInterface;
use App\Repositories\Contracts\BranchRepositoryInterface;
use App\Repositories\Contracts\CustomerDeviceRepositoryInterface;
use App\Repositories\Contracts\CustomerRepositoryInterface;
use App\Repositories\Contracts\DeviceBrandRepositoryInterface;
use App\Repositories\Contracts\DeviceModelRepositoryInterface;
use App\Repositories\Contracts\GoodsReceiptRepositoryInterface;
use App\Repositories\Contracts\InstallmentPlanRepositoryInterface;
use App\Repositories\Contracts\MessageTemplateRepositoryInterface;
use App\Repositories\Contracts\ProductCategoryRepositoryInterface;
use App\Repositories\Contracts\ProductRepositoryInterface;
use App\Repositories\Contracts\PurchaseOrderRepositoryInterface;
use App\Repositories\Contracts\RefurbJobRepositoryInterface;
use App\Repositories\Contracts\RepairTicketRepositoryInterface;
use App\Repositories\Contracts\SaleRepositoryInterface;
use App\Repositories\Contracts\SerializedUnitRepositoryInterface;
use App\Repositories\Contracts\ServiceCatalogRepositoryInterface;
use App\Repositories\Contracts\SettingRepositoryInterface;
use App\Repositories\Contracts\ShiftRepositoryInterface;
use App\Repositories\Contracts\StockAdjustmentRepositoryInterface;
use App\Repositories\Contracts\StockLevelRepositoryInterface;
use App\Repositories\Contracts\StockMovementRepositoryInterface;
use App\Repositories\Contracts\SupplierRepositoryInterface;
use App\Repositories\Contracts\UserRepositoryInterface;
use App\Repositories\CustomerDeviceRepository;
use App\Repositories\CustomerRepository;
use App\Repositories\DeviceBrandRepository;
use App\Repositories\DeviceModelRepository;
use App\Repositories\GoodsReceiptRepository;
use App\Repositories\InstallmentPlanRepository;
use App\Repositories\MessageTemplateRepository;
use App\Repositories\ProductCategoryRepository;
use App\Repositories\ProductRepository;
use App\Repositories\PurchaseOrderRepository;
use App\Repositories\RefurbJobRepository;
use App\Repositories\RepairTicketRepository;
use App\Repositories\SaleRepository;
use App\Repositories\SerializedUnitRepository;
use App\Repositories\ServiceCatalogRepository;
use App\Repositories\SettingRepository;
use App\Repositories\ShiftRepository;
use App\Repositories\StockAdjustmentRepository;
use App\Repositories\StockLevelRepository;
use App\Repositories\StockMovementRepository;
use App\Repositories\SupplierRepository;
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
        SupplierRepositoryInterface::class => SupplierRepository::class,
        SerializedUnitRepositoryInterface::class => SerializedUnitRepository::class,
        StockAdjustmentRepositoryInterface::class => StockAdjustmentRepository::class,
        StockLevelRepositoryInterface::class => StockLevelRepository::class,
        StockMovementRepositoryInterface::class => StockMovementRepository::class,
        ShiftRepositoryInterface::class => ShiftRepository::class,
        SaleRepositoryInterface::class => SaleRepository::class,
        PurchaseOrderRepositoryInterface::class => PurchaseOrderRepository::class,
        GoodsReceiptRepositoryInterface::class => GoodsReceiptRepository::class,
        AcquisitionRepositoryInterface::class => AcquisitionRepository::class,
        RefurbJobRepositoryInterface::class => RefurbJobRepository::class,
        InstallmentPlanRepositoryInterface::class => InstallmentPlanRepository::class,
        SettingRepositoryInterface::class => SettingRepository::class,
        MessageTemplateRepositoryInterface::class => MessageTemplateRepository::class,
    ];

    public function register(): void
    {
        foreach (self::BINDINGS as $interface => $concrete) {
            $this->app->bind($interface, $concrete);
        }
    }
}
