<?php

use App\Http\Controllers\Author\AuthController;
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
            Route::get('register', [AuthController::class, 'showChoose'])->name('register');
            Route::get('register/terms', [AuthController::class, 'showTerms'])->name('register.terms');
            Route::post('register/terms', [AuthController::class, 'acceptTerms'])->name('register.accept-terms');
            Route::get('register/start', [AuthController::class, 'showRegister'])->name('register.start');
            Route::post('register', [AuthController::class, 'register'])->middleware('throttle:10,1');

            Route::get('forgot-password', [PasswordResetController::class, 'showForgot'])->name('password.request');
            Route::post('forgot-password', [PasswordResetController::class, 'sendLink'])->middleware('throttle:5,1')->name('password.email');
            Route::get('reset-password/{token}', [PasswordResetController::class, 'showReset'])->name('password.reset');
            Route::post('reset-password', [PasswordResetController::class, 'reset'])->middleware('throttle:5,1')->name('password.update');
        });

        // Authenticated author
        Route::middleware('auth:author')->group(function () {
            Route::redirect('dashboard', '/author')->name('dashboard');

            Route::get('submissions/create', [SubmissionController::class, 'create'])->name('submissions.create');
            Route::get('submissions/{submission}', [SubmissionController::class, 'show'])->name('submissions.show');
            Route::get('submissions/{submission}/loa', [SubmissionController::class, 'loa'])->name('submissions.loa');
            Route::get('submissions/{submission}/extended-abstract/preview', [SubmissionController::class, 'previewExtendedAbstract'])->name('submissions.extended-abstract.preview');
            Route::post('submissions/{submission}/extended-abstract', [SubmissionController::class, 'submitExtendedAbstract'])->middleware('throttle:6,1')->name('submissions.extended-abstract');
            Route::post('submissions/{submission}/full-paper', [SubmissionController::class, 'submitFullPaper'])->middleware('throttle:6,1')->name('submissions.full-paper');
            Route::get('submissions/{submission}/full-paper/download', [SubmissionController::class, 'downloadFullPaper'])->name('submissions.full-paper.download');

            // Registrasi & pembayaran
            Route::get('registration/checkout', [RegistrationController::class, 'checkout'])->middleware('throttle:20,1')->name('registration.checkout');
            Route::get('registration/{registration}', [RegistrationController::class, 'show'])->name('registration.show');
            Route::patch('registration/{registration}/journal', [RegistrationController::class, 'changeJournalTarget'])->name('registration.journal');
            Route::post('registration/{registration}/pay', [RegistrationController::class, 'payGateway'])->middleware('throttle:6,1')->name('registration.pay');
        });
    });
});

Route::middleware(['auth'])->get(
    'admin/submissions/{submission}/extended-abstract/preview',
    [SubmissionController::class, 'previewExtendedAbstractForAdmin'],
)->name('admin.submissions.extended-abstract.preview');

/*
|--------------------------------------------------------------------------
| Payment gateway (Midtrans) — di luar grup lokal/auth.
| Webhook di-exempt dari CSRF (lihat bootstrap/app.php).
|--------------------------------------------------------------------------
*/
Route::post('payment/midtrans/notification', [MidtransController::class, 'notification'])->middleware('throttle:120,1')->name('payment.midtrans.notification');
Route::get('payment/midtrans/finish', [MidtransController::class, 'finish'])->name('payment.midtrans.finish');
