<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\Auth;
use App\Http\Controllers\Client;
use App\Http\Controllers as Breeze;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Site public
| Les sept entrées du menu de la maquette, plus les pages légales.
|--------------------------------------------------------------------------
*/
Route::get('/', Client\HomeController::class)->name('home');
Route::get('/prestations', [Client\ServiceController::class, 'index'])->name('services');
Route::get('/only-sayajin-store', [Client\StoreController::class, 'index'])->name('store');
Route::get('/galerie', [Client\GalleryController::class, 'index'])->name('gallery');
Route::get('/qui-suis-je', [Client\AboutController::class, 'index'])->name('about');
Route::get('/faq', [Client\FaqController::class, 'index'])->name('faq');
Route::get('/contact', [Client\ContactController::class, 'index'])->name('contact');
Route::get('/p/{page:slug}', [Client\PageController::class, 'show'])->name('page');

/*
|--------------------------------------------------------------------------
| Accès client : réseaux sociaux ou lien magique
|--------------------------------------------------------------------------
*/
Route::get('/connexion', [Auth\MagicLinkController::class, 'create'])->name('client.login');
// 5 envois par minute était trop serré : un client qui ne voit pas l'email
// arriver réessaie deux ou trois fois, et se retrouve bloqué sans comprendre.
Route::post('/lien-magique', [Auth\MagicLinkController::class, 'send'])
    ->middleware('throttle:10,1')->name('magic.send');
Route::get('/lien-magique/{token}', [Auth\MagicLinkController::class, 'consume'])->name('magic.consume');

Route::get('/auth/{provider}/redirect', [Auth\SocialLoginController::class, 'redirect'])
    ->whereIn('provider', ['instagram', 'facebook', 'tiktok'])->name('social.redirect');
Route::get('/auth/{provider}/callback', [Auth\SocialLoginController::class, 'callback'])->name('social.callback');

/*
|--------------------------------------------------------------------------
| Espace client
|--------------------------------------------------------------------------
*/
Route::middleware('auth')->group(function () {
    /**
     * Route attendue par Breeze après connexion et par RedirectIfAuthenticated.
     * Elle n'affiche rien : elle aiguille selon le rôle, le barbier vers son
     * back office et le client vers son espace.
     */
    Route::get('/tableau-de-bord', fn () => redirect()->route(
        auth()->user()->is_admin ? 'admin.dashboard' : 'account',
    ))->name('dashboard');

    // Profil : contrôleur et vue générés par Breeze
    Route::get('/profil', [Breeze\ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profil', [Breeze\ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profil', [Breeze\ProfileController::class, 'destroy'])->name('profile.destroy');

    Route::get('/mon-espace', [Client\AccountController::class, 'index'])->name('account');
    Route::post('/rendez-vous/{appointment}/annuler', [Client\AccountController::class, 'cancel'])->name('appointments.cancel');
    Route::post('/propositions/{proposal}/accepter', [Client\AccountController::class, 'acceptProposal'])->name('proposals.accept');
    Route::post('/rendez-vous/{appointment}/refuser-proposition', [Client\AccountController::class, 'refuseProposal'])->name('proposals.refuse');

    // Tunnel de réservation — un client bloqué ne peut plus réserver
    Route::middleware('not.blocked')->prefix('reserver')->name('booking.')->group(function () {
        Route::get('/', [Client\BookingController::class, 'services'])->name('services');
        Route::get('/{service:slug}/creneaux', [Client\BookingController::class, 'slots'])->name('slots');
        Route::get('/{service:slug}/confirmation', [Client\BookingController::class, 'confirm'])->name('confirm');
        Route::post('/{service:slug}', [Client\BookingController::class, 'store'])->name('store');
    });
});

/*
|--------------------------------------------------------------------------
| Back office
|--------------------------------------------------------------------------
*/
Route::middleware(['auth', 'admin'])->prefix('admin')->name('admin.')->group(function () {
    Route::get('/', Admin\DashboardController::class)->name('dashboard');

    // Rendez-vous : validation, refus, contre-proposition, passage en réalisé
    Route::get('rendez-vous', [Admin\AppointmentController::class, 'index'])->name('appointments.index');
    Route::post('rendez-vous/{appointment}/confirmer', [Admin\AppointmentController::class, 'confirm'])->name('appointments.confirm');
    Route::post('rendez-vous/{appointment}/refuser', [Admin\AppointmentController::class, 'refuse'])->name('appointments.refuse');
    // Créneaux réellement libres pour la prestation du rendez-vous, servis en
    // JSON : la contre-proposition ne doit pas laisser saisir n'importe quoi.
    Route::get('rendez-vous/{appointment}/creneaux', [Admin\AppointmentController::class, 'availableSlots'])->name('appointments.slots');
    Route::post('rendez-vous/{appointment}/contre-proposer', [Admin\AppointmentController::class, 'counterPropose'])->name('appointments.counter');
    Route::post('rendez-vous/{appointment}/realise', [Admin\AppointmentController::class, 'complete'])->name('appointments.complete');

    // Disponibilités
    Route::get('disponibilites', [Admin\AvailabilityController::class, 'index'])->name('availability.index');
    Route::post('disponibilites/hebdo', [Admin\AvailabilityController::class, 'saveWeekly'])->name('availability.weekly');
    Route::post('disponibilites/pauses', [Admin\AvailabilityController::class, 'saveBreaks'])->name('availability.breaks');
    Route::post('disponibilites/exceptions', [Admin\AvailabilityController::class, 'storeException'])->name('availability.exceptions.store');
    Route::delete('disponibilites/exceptions/{exception}', [Admin\AvailabilityController::class, 'destroyException'])->name('availability.exceptions.destroy');

    // Clients : dettes, blocage, fidélité
    Route::get('clients', [Admin\ClientController::class, 'index'])->name('clients.index');
    Route::post('clients', [Admin\ClientController::class, 'store'])->name('clients.store');
    Route::post('clients/{client}/dette', [Admin\ClientController::class, 'updateDebt'])->name('clients.debt');
    Route::post('clients/{client}/blocage', [Admin\ClientController::class, 'toggleBlock'])->name('clients.block');
    Route::post('clients/{client}/lien-magique', [Admin\ClientController::class, 'sendMagicLink'])->name('clients.magic');

    // Catalogue
    Route::resource('services', Admin\ServiceController::class)->except('show');
    Route::resource('products', Admin\ProductController::class)->except('show');
    Route::resource('sales', Admin\SaleController::class)->only(['index', 'store', 'destroy']);
    Route::resource('gallery', Admin\GalleryController::class)->only(['index', 'store', 'update', 'destroy']);
    Route::resource('faqs', Admin\FaqController::class)->only(['index', 'store', 'update', 'destroy']);

    // Statistiques
    Route::get('statistiques', Admin\StatsController::class)->name('stats');

    // Contenu du site
    Route::get('contenu', [Admin\ContentController::class, 'index'])->name('content.index');
    Route::post('contenu', [Admin\ContentController::class, 'update'])->name('content.update');
    // {page:id} explicite : le modèle Page utilise le slug comme clé de route
    // pour les URL publiques, ce qui ferait chercher ici une page dont le slug
    // vaut « 5 » — et renverrait un 404.
    Route::post('pages/{page:id}', [Admin\ContentController::class, 'updatePage'])->name('pages.update');
});

require __DIR__ . '/auth.php';
