<?php

use App\Http\Controllers\Storefront\CategoryController;
use App\Http\Controllers\Storefront\HomeController;
use App\Http\Controllers\Storefront\ProductController;
use App\Http\Controllers\Storefront\SitemapController;
use App\Http\Middleware\SetLocale;
use App\Support\Locales;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');

/*
|--------------------------------------------------------------------------
| Storefront
|--------------------------------------------------------------------------
|
| Every public page carries its locale in the URL, so a page can be linked,
| cached and indexed per language. The pattern rejects anything that is not a
| supported locale before a controller runs.
|
*/

Route::prefix('{locale}')
    ->where(['locale' => Locales::routePattern()])
    ->middleware(SetLocale::class)
    ->name('storefront.')
    ->group(function (): void {
        Route::get('/', HomeController::class)->name('home');

        Route::get('/categories', [CategoryController::class, 'index'])->name('categories.index');
        Route::get('/categories/{slug}', [CategoryController::class, 'show'])->name('categories.show');

        Route::get('/products', [ProductController::class, 'index'])->name('products.index');
        Route::get('/products/{slug}', [ProductController::class, 'show'])->name('products.show');
    });

/*
| The bare root has no language of its own: send the visitor to the language
| they last read, otherwise to the one they ask for, otherwise to the default.
*/
Route::get('/', function (Request $request) {
    $remembered = $request->cookie(SetLocale::COOKIE);

    $locale = Locales::isSupported(is_string($remembered) ? $remembered : null)
        ? $remembered
        : ($request->getPreferredLanguage(Locales::supported()) ?: Locales::fallback());

    return redirect()->route('storefront.home', ['locale' => $locale]);
})->name('root');
