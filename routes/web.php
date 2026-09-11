<?php

use App\Http\Controllers\SimulacaoController;
use App\Http\Middleware\SetLocale;
use Illuminate\Support\Facades\Route;

Route::get('/', function () {
    return view('analise');
});

Route::get('/simulacao/{id}', [SimulacaoController::class, 'show']);

Route::get('/locale/{lang}', function (string $lang) {
    $locale = SetLocale::normalize($lang) ?? 'pt_BR';

    session(['locale' => $locale]);

    return redirect()->back()->withCookie(cookie('locale', $locale, 60 * 24 * 365));
})->whereIn('lang', ['pt_BR', 'pt-BR', 'pt', 'en'])->name('locale.switch');
