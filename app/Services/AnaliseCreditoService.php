<?php

namespace App\Services;

use App\Enums\StatusAnalise;

class AnaliseCreditoService
{
    public const RENDA_MINIMA = 1500.0;

    public const SCORE_MINIMO = 400;

    public const SCORE_TAXA_REDUZIDA = 700;

    public const TAXA_PADRAO = 4.5;

    public const TAXA_REDUZIDA = 2.9;

    public const PARCELAS = 12;

    public const COMPROMETIMENTO_MAXIMO = 0.30;

    /**
     * Aplica as regras de negócio e retorna o resultado da avaliação.
     *
     * @return array{status: StatusAnalise, taxa_juros: ?float, valor_parcela: ?float, motivo_rejeicao: ?string}
     */
    public function avaliar(float $rendaMensal, int $score, float $valorSolicitado): array
    {
        if ($rendaMensal < self::RENDA_MINIMA) {
            return $this->reprovado(__('analise.renda_minima'));
        }

        if ($score < self::SCORE_MINIMO) {
            return $this->reprovado(__('analise.score_baixo'));
        }

        $taxa = $score >= self::SCORE_TAXA_REDUZIDA
            ? self::TAXA_REDUZIDA
            : self::TAXA_PADRAO;

        $valorParcela = $this->calcularParcela($valorSolicitado, $taxa);

        if ($valorParcela > $rendaMensal * self::COMPROMETIMENTO_MAXIMO) {
            $limitePercentual = rtrim(rtrim(number_format(self::COMPROMETIMENTO_MAXIMO * 100, 1, '.', ''), '0'), '.');

            return $this->reprovado(
                __('analise.comprometimento', ['limite' => $limitePercentual])
            );
        }

        return [
            'status' => StatusAnalise::APROVADO,
            'taxa_juros' => $taxa,
            'valor_parcela' => $valorParcela,
            'motivo_rejeicao' => null,
        ];
    }

    /**
     * Juros simples em 12 parcelas fixas:
     * total = valor + (valor * taxa% * 12) | parcela = total / 12
     */
    public function calcularParcela(float $valorSolicitado, float $taxaMensal): float
    {
        $jurosTotal = $valorSolicitado * ($taxaMensal / 100) * self::PARCELAS;

        return round(($valorSolicitado + $jurosTotal) / self::PARCELAS, 2);
    }

    /**
     * @return array{status: StatusAnalise, taxa_juros: ?float, valor_parcela: ?float, motivo_rejeicao: ?string}
     */
    private function reprovado(string $motivo): array
    {
        return [
            'status' => StatusAnalise::REPROVADO,
            'taxa_juros' => null,
            'valor_parcela' => null,
            'motivo_rejeicao' => $motivo,
        ];
    }
}
