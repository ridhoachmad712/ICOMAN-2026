<?php

use App\Http\Controllers\Author\AuthController;
use App\Http\Controllers\Author\DashboardController;
use App\Http\Controllers\Author\PasswordResetController;
use App\Http\Controllers\Author\RegistrationController;
use App\Http\Controllers\Author\SubmissionController;
use App\Http\Controllers\LocaleController;
use App\Http\Controllers\NewsController;
use App\Http\Controllers\Payment\MidtransController;
use App\Http\Controllers\PublicController;
use App\Http\Controllers\SitemapController;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', [SitemapController::class, 'index'])->name('sitemap');

Route::middleware('setlocale')->group(function () {
    Route::get('/', [PublicController::class, 'home'])->name('home');

    Route::get('/about', [PublicController::class, 'page'])->defaults('slug', 'about')->name('about');
    Route::get('/venue', [PublicController::class, 'page'])->defaults('slug', 'venue')->name('venue');

    Route::get('/committee', [PublicController::class, 'committee'])->name('committee');
    Route::get('/speakers', [PublicController::class, 'speakers'])->name('speakers');
    Route::get('/call-for-papers', [PublicController::class, 'callForPapers'])->name('call-for-papers');
    Route::get('/important-dates', [PublicController::class, 'importantDates'])->name('important-dates');
    Route::get('/registration', [PublicController::class, 'registration'])->name('registration');
    Route::get('/author-guidelines', [PublicController::class, 'downloads'])->name('author-guidelines');
    Route::get('/program', [PublicController::class, 'schedule'])->name('program');
    Route::get('/faq', [PublicController::class, 'faq'])->name('faq');
    Route::get('/contact', [PublicController::class, 'contact'])->name('contact');

    Route::get('/news', [NewsController::class, 'index'])->name('news.index');
    Route::get('/news/{slug}', [NewsController::class, 'show'])->name('news.show');

    // Halaman CMS dinamis lain (fallback by slug).
    Route::get('/p/{slug}', [PublicController::class, 'page'])->name('page');

    Route::get('/lang/{locale}', [LocaleController::class, 'switch'])->name('locale.switch');

    /*
    |--------------------------------------------------------------------------
    | Portal Author (guard `author`, terpisah dari admin Filament)
    |--------------------------------------------------------------------------
    */
    Route::prefix('author')->name('author.')->group(function () {
        // Guest (belum login sebagai author)
        Route::middleware('guest:author')->group(function () {
            Route::get('register', [AuthController::class, 'showRegister'])->name('register');
            Route::post('register', [AuthController::class, 'register']);
            Route::get('login', [AuthController::class, 'showLogin'])->name('login');
            Route::post('login', [AuthController::class, 'login']);

            Route::get('forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
            Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->name('password.email');
            Route::get('reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
            Route::post('reset-password', [PasswordResetController::class, 'reset'])->name('password.update');
        });

        // Authenticated author
        Route::middleware('auth:author')->group(function () {
            Route::post('logout', [AuthController::class, 'logout'])->name('logout');
            Route::get('dashboard', [DashboardController::class, 'index'])->name('dashboard');

            Route::get('submissions/create', [SubmissionController::class, 'create'])->name('submissions.create');
            Route::get('submissions/{submission}', [SubmissionController::class, 'show'])->name('submissions.show');
            Route::post('submissions/{submission}/camera-ready', [SubmissionController::class, 'uploadCameraReady'])->name('submissions.camera-ready');

            // Registrasi & pembayaran
            Route::get('registration/create', [RegistrationController::class, 'create'])->name('registration.create');
            Route::post('registration', [RegistrationController::class, 'store'])->name('registration.store');
            Route::get('registration/{registration}', [RegistrationController::class, 'show'])->name('registration.show');
            Route::post('registration/{registration}/proof', [RegistrationController::class, 'uploadProof'])->name('registration.proof');
            Route::post('registration/{registration}/pay', [RegistrationController::class, 'payGateway'])->name('registration.pay');
        });
    });
});

/*
|--------------------------------------------------------------------------
| Payment gateway (Midtrans) — di luar grup lokal/auth.
| Webhook di-exempt dari CSRF (lihat bootstrap/app.php).
|--------------------------------------------------------------------------
*/
Route::post('payment/midtrans/notification', [MidtransController::class, 'notification'])->name('payment.midtrans.notification');
Route::get('payment/midtrans/finish', [MidtransController::class, 'finish'])->name('payment.midtrans.finish');
