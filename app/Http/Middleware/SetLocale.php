<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\App;
use Symfony\Component\HttpFoundation\Response;

class SetLocale
{
    /**
     * Idiomas suportados pela aplicação.
     *
     * @var array<int, string>
     */
    public const LOCALES = ['pt_BR', 'en'];

    /**
     * Normaliza variações comuns para os locales suportados.
     */
    public static function normalize(?string $locale): ?string
    {
        if (! is_string($locale) || $locale === '') {
            return null;
        }

        $locale = strtolower(str_replace('-', '_', trim($locale)));

        if (in_array($locale, ['pt_br', 'pt'], true)) {
            return 'pt_BR';
        }

        if (in_array($locale, ['en', 'en_us'], true)) {
            return 'en';
        }

        return null;
    }

    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        $locale = self::normalize($request->header('X-Locale'))
            ?? self::normalize((string) $request->query('locale'))
            ?? self::normalize($request->cookie('locale'))
            ?? ($request->hasSession() ? self::normalize($request->session()->get('locale')) : null)
            ?? config('app.locale', 'pt_BR');

        if (! in_array($locale, self::LOCALES, true)) {
            $locale = 'pt_BR';
        }

        App::setLocale($locale);

        return $next($request);
    }
}
