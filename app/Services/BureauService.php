<?php

namespace App\Services;

use App\Exceptions\BureauIndisponivelException;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class BureauService
{
    /**
     * Consulta o score de um CPF no Bureau externo.
     *
     * @throws BureauIndisponivelException
     */
    public function consultarScore(string $cpf): int
    {
        $baseUrl = rtrim((string) config('services.score_bureau.url'), '/');
        $timeout = (int) config('services.score_bureau.timeout', 3);
        $url = "{$baseUrl}/{$cpf}";

        try {
            $response = Http::timeout($timeout)->get($url);
        } catch (ConnectionException $e) {
            Log::warning('Bureau timeout/falha de conexão', [
                'cpf' => $cpf,
                'url' => $url,
                'error' => $e->getMessage(),
            ]);

            throw new BureauIndisponivelException(__('analise.bureau_indisponivel'), previous: $e);
        }

        if ($response->failed()) {
            Log::warning('Bureau retornou erro HTTP', [
                'cpf' => $cpf,
                'url' => $url,
                'status' => $response->status(),
            ]);

            throw new BureauIndisponivelException(__('analise.bureau_indisponivel'));
        }

        $score = $response->json('score');

        if (! is_numeric($score)) {
            Log::warning('Bureau retornou resposta malformada', [
                'cpf' => $cpf,
                'url' => $url,
                'body' => $response->body(),
            ]);

            throw new BureauIndisponivelException(__('analise.bureau_resposta_invalida'));
        }

        return (int) $score;
    }
}
