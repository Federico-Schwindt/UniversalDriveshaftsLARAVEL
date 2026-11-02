<?php

use App\Http\Controllers\Admin\CategoriesController;
use App\Http\Controllers\Admin\CouponsController;
use App\Http\Controllers\Admin\CustomersController;
use App\Http\Controllers\Admin\DashboardController;
use App\Http\Controllers\Admin\DriveshaftsController;
use App\Http\Controllers\Admin\InventoryController;
use App\Http\Controllers\Admin\LabelsController;
use App\Http\Controllers\Admin\LeadsController;
use App\Http\Controllers\Admin\MediaController;
use App\Http\Controllers\Admin\OrdersController;
use App\Http\Controllers\Admin\ProductsController;
use App\Http\Controllers\Admin\ReportsController;
use App\Http\Controllers\Admin\SettingsController;
use Illuminate\Support\Facades\Route;

/* ---------- Admin v2 ---------- */
Route::middleware(['admin'])->prefix('admin')->group(function () {
    Route::get('/', [DashboardController::class, 'index']);
    Route::get('/search', [DashboardController::class, 'search']);
    Route::get('/api/search', [DashboardController::class, 'apiSearch']);

    Route::middleware('admin.section:orders')->group(function () {
        Route::get('/orders', [OrdersController::class, 'index']);
        Route::get('/orders/new', [OrdersController::class, 'create']);
        Route::post('/orders/new', [OrdersController::class, 'store']);
        Route::post('/orders/quote', [OrdersController::class, 'quote']);
        Route::post('/orders/bulk', [OrdersController::class, 'bulk']);
        Route::get('/orders/print', [OrdersController::class, 'printBatch']);
        Route::get('/orders/{id}', [OrdersController::class, 'show'])->whereNumber('id');
        Route::post('/orders/{id}', [OrdersController::class, 'update'])->whereNumber('id');
        Route::post('/orders/{id}/tracking', [OrdersController::class, 'tracking']);
        Route::post('/orders/{id}/delivered', [OrdersController::class, 'delivered']);
        Route::post('/orders/{id}/pay-link', [OrdersController::class, 'payLink']);
        Route::post('/orders/{id}/items', [OrdersController::class, 'items']);
        Route::post('/orders/{id}/totals', [OrdersController::class, 'totals']);
        Route::post('/orders/{id}/address', [OrdersController::class, 'address']);
        Route::post('/orders/{id}/note', [OrdersController::class, 'note']);
        Route::post('/orders/{id}/note/{noteId}/delete', [OrdersController::class, 'deleteNote']);
        Route::post('/orders/{id}/email', [OrdersController::class, 'email']);
        Route::post('/orders/{id}/refund', [OrdersController::class, 'refund'])->middleware('admin.section:refunds');   // real Stripe money
        Route::get('/orders/{id}/invoice', [OrdersController::class, 'invoice']);
        Route::get('/orders/{id}/packing-slip', [OrdersController::class, 'packingSlip']);
        // shipping + return labels bought straight from the order page
        Route::post('/orders/{id}/labels', [LabelsController::class, 'store'])->whereNumber('id');
        Route::post('/orders/{id}/labels/rates', [LabelsController::class, 'rates'])->whereNumber('id');
        Route::get('/labels/{label}/pdf', [LabelsController::class, 'pdf'])->whereNumber('label');
        Route::post('/labels/{label}/void', [LabelsController::class, 'void'])->whereNumber('label');
        // LTL freight booked through Warp
        Route::post('/orders/{id}/freight/quotes', [\App\Http\Controllers\Admin\FreightBookingsController::class, 'quotes'])->whereNumber('id');
        Route::post('/orders/{id}/freight', [\App\Http\Controllers\Admin\FreightBookingsController::class, 'store'])->whereNumber('id')->middleware('admin.section:purchases');
        Route::post('/freight/{booking}/refresh', [\App\Http\Controllers\Admin\FreightBookingsController::class, 'refresh'])->whereNumber('booking');
        // UDS Assistant conversations: read, take over, hand back
        Route::get('/chats', [\App\Http\Controllers\Admin\ChatsController::class, 'index']);
        Route::get('/chats/{id}', [\App\Http\Controllers\Admin\ChatsController::class, 'show'])->whereNumber('id');
        Route::get('/chats/{id}/messages', [\App\Http\Controllers\Admin\ChatsController::class, 'messages'])->whereNumber('id');
        Route::post('/chats/{id}/reply', [\App\Http\Controllers\Admin\ChatsController::class, 'reply'])->whereNumber('id');
        Route::post('/chats/{id}/takeover', [\App\Http\Controllers\Admin\ChatsController::class, 'takeover'])->whereNumber('id');
        Route::post('/chats/{id}/release', [\App\Http\Controllers\Admin\ChatsController::class, 'release'])->whereNumber('id');
        Route::post('/chats/{id}/close', [\App\Http\Controllers\Admin\ChatsController::class, 'close'])->whereNumber('id');
        // dropship from Powertrain Industries — only when someone clicks it
        Route::post('/orders/{id}/pti-dropship/prepare', [\App\Http\Controllers\Admin\PtiDropshipController::class, 'prepare'])->whereNumber('id');
        Route::post('/pti-dropship/{dropship}/place', [\App\Http\Controllers\Admin\PtiDropshipController::class, 'place'])->whereNumber('dropship')->middleware('admin.section:purchases');
        Route::post('/pti-dropship/{dropship}/cancel', [\App\Http\Controllers\Admin\PtiDropshipController::class, 'cancel'])->whereNumber('dropship');
        Route::post('/pti-dropship/{dropship}/tracking', [\App\Http\Controllers\Admin\PtiDropshipController::class, 'tracking'])->whereNumber('dropship');
        Route::get('/api/products', [OrdersController::class, 'apiProducts']);
        Route::get('/api/customers', [OrdersController::class, 'apiCustomers']);
        Route::get('/api/customers/{id}/address', [OrdersController::class, 'apiCustomerAddress']);
    });

    Route::middleware('admin.section:products')->group(function () {
        Route::get('/products', [ProductsController::class, 'index']);
        Route::get('/products/new', [ProductsController::class, 'create']);
        Route::post('/products', [ProductsController::class, 'store']);
        // mass edits / imports / deletes rewrite the live catalog: catalog_write only
        Route::post('/products/bulk', [ProductsController::class, 'bulk'])->middleware('admin.section:catalog_write');
        Route::post('/products/import', [ProductsController::class, 'import'])->middleware('admin.section:catalog_write');
        Route::get('/products/{id}', [ProductsController::class, 'edit'])->whereNumber('id');
        Route::post('/products/{id}', [ProductsController::class, 'update'])->whereNumber('id');
        Route::post('/products/{id}/delete', [ProductsController::class, 'destroy'])->middleware('admin.section:catalog_write');
        Route::post('/products/{id}/duplicate', [ProductsController::class, 'duplicate']);
        Route::post('/products/{id}/quick', [ProductsController::class, 'quick']);
        Route::get('/api/terms', [ProductsController::class, 'apiTerms']);
        Route::post('/media/upload', [MediaController::class, 'upload']);
        Route::get('/categories', [CategoriesController::class, 'index']);
        Route::get('/categories/new', [CategoriesController::class, 'create']);
        Route::post('/categories', [CategoriesController::class, 'store']);
        Route::get('/categories/{slug}', [CategoriesController::class, 'edit']);
        Route::post('/categories/{slug}', [CategoriesController::class, 'update']);
        Route::post('/categories/{slug}/delete', [CategoriesController::class, 'destroy'])->middleware('admin.section:catalog_write');
    });

    Route::middleware('admin.section:driveshafts')->group(function () {
        Route::get('/driveshafts', [DriveshaftsController::class, 'index']);
        Route::get('/driveshafts/lookup', [DriveshaftsController::class, 'lookup']);
        Route::post('/driveshafts/vehicle-note', [DriveshaftsController::class, 'vehicleNote']);
        Route::post('/driveshafts/vehicle-note/{id}/delete', [DriveshaftsController::class, 'deleteVehicleNote']);
        Route::get('/driveshafts/{pn}', [DriveshaftsController::class, 'show'])->where('pn', '[A-Za-z0-9._ -]+');
        Route::post('/driveshafts/{pn}', [DriveshaftsController::class, 'update'])->where('pn', '[A-Za-z0-9._ -]+');
        Route::post('/driveshafts/{pn}/spec', [DriveshaftsController::class, 'spec'])->where('pn', '[A-Za-z0-9._ -]+');
        Route::post('/driveshafts/{pn}/spec/copy', [DriveshaftsController::class, 'copySpec'])->where('pn', '[A-Za-z0-9._ -]+');
        Route::get('/driveshafts/{pn}/sheet', [DriveshaftsController::class, 'printSheet'])->where('pn', '[A-Za-z0-9._ -]+');
    });

    Route::middleware('admin.section:inventory')->group(function () {
        Route::get('/inventory', [InventoryController::class, 'index']);
        Route::post('/inventory/bulk', [InventoryController::class, 'bulk']);
        Route::post('/inventory/{id}', [InventoryController::class, 'update'])->whereNumber('id');
        Route::post('/inventory/{id}/adjust', [InventoryController::class, 'adjust']);
        Route::get('/inventory/{id}/history', [InventoryController::class, 'history']);
    });

    Route::middleware('admin.section:customers')->group(function () {
        Route::get('/customers', [CustomersController::class, 'index']);
        Route::get('/customers/new', [CustomersController::class, 'create']);
        Route::post('/customers', [CustomersController::class, 'store']);
        Route::post('/customers/note/{id}/delete', [CustomersController::class, 'deleteNote']);
        Route::post('/customers/{email}/note', [CustomersController::class, 'note'])->where('email', '.+@.+');
        Route::get('/customers/{key}', [CustomersController::class, 'show']);
        Route::post('/customers/{id}', [CustomersController::class, 'update'])->whereNumber('id');
        Route::post('/customers/{id}/reset-link', [CustomersController::class, 'resetLink']);
    });
    Route::middleware('admin.section:wholesale')->get('/wholesale', [CustomersController::class, 'wholesale']);

    Route::middleware('admin.section:coupons')->group(function () {
        Route::get('/coupons', [CouponsController::class, 'index']);
        Route::get('/coupons/new', [CouponsController::class, 'create']);
        Route::post('/coupons', [CouponsController::class, 'store']);
        Route::get('/coupons/{id}', [CouponsController::class, 'edit'])->whereNumber('id');
        Route::post('/coupons/{id}', [CouponsController::class, 'update'])->whereNumber('id');
        Route::post('/coupons/{id}/toggle', [CouponsController::class, 'toggle']);
        Route::post('/coupons/{id}/delete', [CouponsController::class, 'destroy']);
    });

    Route::middleware('admin.section:leads')->group(function () {
        Route::get('/leads', [LeadsController::class, 'index']);
        Route::get('/leads/{id}', [LeadsController::class, 'show'])->whereNumber('id');
        Route::post('/leads/{id}', [LeadsController::class, 'update'])->whereNumber('id');
        Route::post('/leads/{id}/reply', [LeadsController::class, 'reply']);
        Route::post('/leads/{id}/delete', [LeadsController::class, 'destroy']);
        Route::get('/designs', [LeadsController::class, 'designs']);
        Route::get('/rma', [\App\Http\Controllers\RmaController::class, 'adminList']);
        Route::post('/rma/{id}', [\App\Http\Controllers\RmaController::class, 'adminUpdate']);
    });
    Route::middleware('admin.section:recovery')->get('/recovery', [\App\Http\Controllers\RecoveryController::class, 'admin']);
    Route::middleware('admin.section:reports')->get('/reports', [ReportsController::class, 'index']);
    Route::middleware('admin.section:activity')->get('/activity', [SettingsController::class, 'activity']);

    Route::middleware('admin.section:settings')->group(function () {
        Route::get('/settings', [SettingsController::class, 'index']);
        Route::post('/settings', [SettingsController::class, 'update']);
        Route::post('/settings/users', [SettingsController::class, 'addUser']);
        Route::post('/settings/users/{id}', [SettingsController::class, 'updateUser']);
        Route::post('/settings/cache', [SettingsController::class, 'clearCache']);
        Route::post('/settings/mail-test', [SettingsController::class, 'mailTest']);
        Route::post('/settings/brevo-sender', [SettingsController::class, 'brevoSender']);
        Route::get('/settings/email-preview/{tpl}', [SettingsController::class, 'emailPreview']);
    });
});
