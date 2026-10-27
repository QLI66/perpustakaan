<?php

use App\Http\Controllers\AcquisitionController;
use App\Http\Controllers\AcquisitionStockOpnameReportController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\BookController;
use App\Http\Controllers\BookCopyController;
use App\Http\Controllers\BookIncidentController;
use App\Http\Controllers\CatalogController;
use App\Http\Controllers\CollectionTypeController;
use App\Http\Controllers\DashboardController;
use App\Http\Controllers\FinanceController;
use App\Http\Controllers\FineController;
use App\Http\Controllers\FineIncidentReportController;
use App\Http\Controllers\LoanController;
use App\Http\Controllers\LoanReportController;
use App\Http\Controllers\LocationController;
use App\Http\Controllers\MemberCatalogReportController;
use App\Http\Controllers\MemberController;
use App\Http\Controllers\MemberImportController;
use App\Http\Controllers\MemberReferenceController;
use App\Http\Controllers\PermissionController;
use App\Http\Controllers\RenewalController;
use App\Http\Controllers\ReportExportController;
use App\Http\Controllers\ReportingOverviewController;
use App\Http\Controllers\ReservationController;
use App\Http\Controllers\ReservationReportController;
use App\Http\Controllers\ReturnController;
use App\Http\Controllers\RoleController;
use App\Http\Controllers\SchoolIdentityController;
use App\Http\Controllers\StockOpnameController;
use App\Http\Controllers\UserController;

Route::post('/auth/login', [AuthController::class, 'login']);

// Public School Identity
Route::get('/settings/school-identity', [SchoolIdentityController::class, 'show']);
Route::get('/settings/school-identity/logo', [SchoolIdentityController::class, 'logo']);

Route::prefix('catalog')->group(function () {
    Route::get('/books', [CatalogController::class, 'books']);
    Route::get('/books/{book}', [CatalogController::class, 'showBook']);
    Route::get('/categories', [CatalogController::class, 'categories']);
    Route::post('/categories', [CatalogController::class, 'storeCategory'])->middleware('auth:sanctum');
    Route::get('/authors', [CatalogController::class, 'authors']);
    Route::post('/authors', [CatalogController::class, 'storeAuthor'])->middleware('auth:sanctum');
    Route::get('/publishers', [CatalogController::class, 'publishers']);
    Route::get('/collection-types', [CatalogController::class, 'collectionTypes']);
});

Route::middleware('auth:sanctum')->group(function () {

    Route::get(
        '/locations',
        [LocationController::class, 'index']
    );
    Route::post(
        '/locations',
        [LocationController::class, 'store']
    );
    Route::post('/auth/logout', [AuthController::class, 'logout']);
    Route::post('/auth/change-password', [AuthController::class, 'changePassword']);
    Route::get('/auth/me', [AuthController::class, 'me']);

    Route::apiResource('stock-opnames', StockOpnameController::class);

    Route::get(
        'dashboard/summary',
        [DashboardController::class, 'summary']
    );

    Route::get(
        'reports/reservations',
        [ReservationReportController::class, 'reservations']
    );

    Route::get(
        'reports/members',
        [MemberCatalogReportController::class, 'members']
    );

    Route::get(
        'reports/catalog',
        [MemberCatalogReportController::class, 'catalog']
    );

    Route::get(
        'reports/loans',
        [LoanReportController::class, 'loans']
    );

    Route::get(
        'reports/returns',
        [LoanReportController::class, 'returns']
    );

    Route::get(
        'reports/fines',
        [FineIncidentReportController::class, 'fines']
    );

    Route::get(
        'reports/incidents',
        [FineIncidentReportController::class, 'incidents']
    );

    Route::get(
        'reports/acquisitions',
        [AcquisitionStockOpnameReportController::class, 'acquisitions']
    );

    Route::get(
        'reports/stock-opnames',
        [AcquisitionStockOpnameReportController::class, 'stockOpnames']
    );

    Route::get(
        'reports/overview',
        [ReportingOverviewController::class, 'overview']
    );

    Route::get('finance/summary', [FinanceController::class, 'summary']);
    Route::get('finance/transactions', [FinanceController::class, 'transactions']);

    Route::post(
        'stock-opnames/{stockOpname}/start',
        [StockOpnameController::class, 'start']
    );

    Route::post(
        'stock-opnames/{stockOpname}/items/{item}/check',
        [StockOpnameController::class, 'checkItem']
    );

    Route::post(
        'stock-opnames/{stockOpname}/complete',
        [StockOpnameController::class, 'complete']
    );

    Route::get(
        'reports/export/{report}',
        [ReportExportController::class, 'export']
    );

    Route::post(
        'stock-opnames/{stockOpname}/cancel',
        [StockOpnameController::class, 'cancel']
    );
    // Member management (private)
    Route::post('members/import/preview', [MemberImportController::class, 'preview']);
    Route::post('members/import', [MemberImportController::class, 'import']);
    Route::apiResource('members', MemberController::class);

    // Self member profile
    Route::get('/me/member', [MemberController::class, 'myMember']);

    // Self loans
    Route::get('/me/loans', [LoanController::class, 'myLoans']);

    // Book Copy management (private)
    Route::get('book-copies/barcode/{barcode}', [BookCopyController::class, 'byBarcode'])->name('book-copies.by-barcode');
    Route::apiResource('book-copies', BookCopyController::class);
    // Acquisition management (private)
    Route::apiResource('acquisitions', AcquisitionController::class);

    // Loan management (private)
    Route::apiResource('loans', LoanController::class)->only(['index', 'show', 'store']);

    // Return operation (nested under loan)
    Route::post('loans/{loan}/items/{loanItem}/return', ReturnController::class);

    // Renewal operation (nested under loan)
    Route::post('loans/{loan}/items/{loanItem}/renew', RenewalController::class);

    // Fine & Payment management
    Route::get('me/fines', [FineController::class, 'myFines']);
    Route::get('fines', [FineController::class, 'index']);
    Route::get('fines/{fine}', [FineController::class, 'show']);
    Route::post('fines/{fine}/payments', [FineController::class, 'pay']);

    // Book Incidents management
    Route::get('me/book-incidents', [BookIncidentController::class, 'myIncidents']);
    Route::get('book-incidents', [BookIncidentController::class, 'index']);
    Route::get('book-incidents/{incident}', [BookIncidentController::class, 'show']);
    Route::post('book-incidents', [BookIncidentController::class, 'store']);
    Route::post('book-incidents/{incident}/resolve/payment', [BookIncidentController::class, 'resolvePayment']);
    Route::post('book-incidents/{incident}/resolve/replacement', [BookIncidentController::class, 'resolveReplacement']);

    // Reservations management
    Route::get('me/reservations', [ReservationController::class, 'myReservations']);
    Route::get('reservations', [ReservationController::class, 'index']);
    Route::get('reservations/{reservation}', [ReservationController::class, 'show']);
    Route::post('reservations', [ReservationController::class, 'store']);
    Route::post('reservations/{reservation}/cancel', [ReservationController::class, 'cancel']);
    Route::post('reservations/{reservation}/restore', [ReservationController::class, 'restore']);
    Route::post('reservations/{reservation}/assign', [ReservationController::class, 'assign']);

    // Collection Types admin CRUD
    Route::apiResource('collection-types', CollectionTypeController::class);

    // Admin APIs (Users, Roles, Permissions)
    Route::apiResource('users', UserController::class);
    Route::apiResource('roles', RoleController::class);
    Route::apiResource('permissions', PermissionController::class)->only(['index']);

    // School Identity Settings (Super Admin)
    Route::put('/settings/school-identity', [SchoolIdentityController::class, 'update']);
    Route::post('/settings/school-identity/logo', [SchoolIdentityController::class, 'uploadLogo']);
    Route::delete('/settings/school-identity/logo', [SchoolIdentityController::class, 'deleteLogo']);
});

/*
|--------------------------------------------------------------------------
| Book Management
|--------------------------------------------------------------------------
| Authenticated staff/admin CRUD for bibliographic book records.
*/
Route::middleware('auth:sanctum')->group(function () {

    Route::get(
        '/locations',
        [LocationController::class, 'index']
    );
    Route::post(
        '/locations',
        [LocationController::class, 'store']
    );
    Route::get(
        'books',
        [BookController::class, 'index']
    )->name('books.index');

    Route::post(
        'books',
        [BookController::class, 'store']
    )->name('books.store');

    Route::get(
        'books/{book}',
        [BookController::class, 'show']
    )->name('books.show');

    Route::patch(
        'books/{book}',
        [BookController::class, 'update']
    )->name('books.update');

    Route::delete(
        'books/{book}',
        [BookController::class, 'destroy']
    )->name('books.destroy');
});
/*
|--------------------------------------------------------------------------
| Member Master Reference API
|--------------------------------------------------------------------------
*/

Route::middleware('auth:sanctum')->group(function () {
    Route::get(
        '/member-types',
        [MemberReferenceController::class, 'memberTypes']
    )->name('member-types.index');

    Route::get(
        '/class-levels',
        [MemberReferenceController::class, 'classLevels']
    )->name('class-levels.index');

    Route::get(
        '/majors',
        [MemberReferenceController::class, 'majors']
    )->name('majors.index');
});
