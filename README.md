# Coop0156 — Sistema de Análise de Crédito Cooperativo

Sistema para uma cooperativa organizar a jornada de crédito: cadastra clientes, simula condições consultando um bureau de crédito e confirma a contratação — com regras de elegibilidade, processamento em segundo plano e telas em português e inglês.

## Como funciona

1. O atendente preenche nome, CPF, renda, tipo e valor desejado na tela inicial.
2. O sistema localiza o cliente pelo CPF (ou cadastra automaticamente) e consulta o score no bureau.
3. Aplica as regras: renda mínima de R$ 1.500, faixas de score que definem a taxa (4,5% ou 2,9% ao mês) e limite de 30% da renda por parcela, em 12x com juros simples.
4. Se aprovado, o atendente abre a tela de simulação, revisa taxa, parcela e comprometimento de renda, e confirma.
5. A confirmação entra numa fila e é finalizada em background, sem travar a tela.

## Funcionalidades

- Cadastro de clientes (criar, listar com paginação, ver, atualizar, remover) com validações e mensagens claras de erro.
- Solicitação de análise com consulta ao bureau, tratamento de instabilidade (erro, lentidão ou resposta incompleta) sem quebrar a aplicação.
- Tela de simulação com score, taxa, parcelas e comprometimento de renda.
- Contratação assíncrona: a tela responde na hora e um trabalhador finaliza em segundo plano.
- Telas em PT-BR e EN com seletor de idioma; a API também responde no idioma escolhido.
- Máscaras de CPF e valores, loading entre tentativas e validações amigáveis no formulário.
- 25 testes automatizados cobrindo os fluxos principais.

## Como rodar (Docker)

Pré-requisito: Docker Desktop aberto.

```powershell
docker run --rm -v ${PWD}:/var/www/html -w /var/www/html laravelsail/php84-composer:latest composer install --ignore-platform-reqs
Copy-Item .env.example .env
docker compose up -d --build
docker compose exec laravel.test php artisan key:generate
docker compose exec laravel.test php artisan migrate --force
```

Acesse `http://localhost:8000`. Para a fila funcionar na demonstração, deixe um segundo terminal rodando `docker compose exec laravel.test php artisan queue:work`. Para desligar: `docker compose down`.

**Dicas se algo travar:**

- Se o `up --build` reclamar de `WWWGROUP`, confira no `.env` as linhas `WWWGROUP=1000`, `WWWUSER=1000` e `APP_PORT=8000`.
- Se a análise demorar e cair como indisponível no Windows, suba `SCORE_BUREAU_TIMEOUT` para `10` no `.env` local e tente de novo (o banco local responde devagar pelo compartilhamento de arquivos).
- Se o `migrate` disser que o banco não existe, crie o arquivo vazio `database/database.sqlite` e rode de novo.
- Se o log reclamar de permissão, rode `docker compose exec laravel.test chmod -R 775 storage bootstrap/cache`.

## Testes

O que está coberto, em linguagem simples: criar cliente válido e recusar CPF/e-mail duplicado ou campo faltando; aprovar com score alto e baixo (taxas diferentes); recusar por renda baixa, score baixo e parcela que estoura 30% da renda; continuar respondendo bem mesmo com o bureau fora do ar; contratar uma análise aprovada; criar o cliente sozinho quando chega CPF novo; trocar de idioma e processar a fila.

```powershell
docker compose exec laravel.test php artisan test                        # tudo (25)
docker compose exec laravel.test php artisan test --filter=ClienteTest   # clientes (10)
docker compose exec laravel.test php artisan test --filter=AnaliseCreditoTest  # análise e fila (11)
docker compose exec laravel.test php artisan test --filter=LocaleTest    # idiomas (2)
```

Para brincar manualmente, o bureau de mentira responde pelo último dígito do CPF: `1` score baixo · `2` médio · `3` alto · `4` erro · `5` demora · `6` resposta incompleta · demais, score médio padrão.

## Decisões de construção

- Validações ficam em classes próprias de pedido, então o controlador só orquestra e as mensagens de erro saem padronizadas.
- A conversa com o bureau fica isolada num serviço com tempo limite e registra avisos em log; as regras de crédito ficam noutro serviço só com números, fácil de testar.
- Textos da API e das telas vivem em arquivos de tradução (`lang/pt_BR` e `lang/en`), com o idioma guardado em sessão/cookie e um seletor no topo das telas.
- Código formatado no padrão da comunidade (Pint) e histórico de commits por fatia funcional.

## Onde fica cada coisa

```
app/Http/Controllers/   → portas de entrada da API e das telas
  ClienteController      → CRUD de clientes
  AnaliseCreditoController → análise + contratação
  MockBureauController   → bureau de mentira (só para demonstração)
  SimulacaoController    → abre a tela de simulação
app/Http/Requests/      → regras de validação de cada formulário
app/Http/Middleware/    → troca de idioma (PT/EN)
app/Services/           → BureauService (fala com o bureau) e
                           AnaliseCreditoService (as contas e regras)
app/Jobs/               → trabalho de fundo que finaliza a contratação
app/Enums/              → status (pendente, aprovado...) e tipos de crédito
routes/                 → api.php (dados) e web.php (telas + troca de idioma)
lang/pt_BR · lang/en    → textos em cada idioma
resources/views/        → analise.blade.php (tela inicial) e
                           simulacao.blade.php (revisão e confirmação)
tests/Feature/          → ClienteTest · AnaliseCreditoTest · LocaleTest
database/migrations/    → criação das tabelas (o banco SQLite é local)
```

## Melhorias futuras

Ideias mapeadas que não couberam nesta versão:

- Campos de e-mail e telefone no formulário de análise (hoje vão só pela API; na tela o e-mail é gerado automaticamente).
- Aviso antecipado de duplicidade: checar CPF/e-mail enquanto digita e sugerir aproveitar o cadastro existente.
- Uso real dos tipos de crédito nas regras (ex.: condições ou taxas por pessoal, imobiliário e automotivo).
- Tela de clientes (listar, buscar e editar sem sair do navegador).
- Acompanhamento ao vivo da contratação na tela de simulação até o trabalho de fundo concluir.
- Histórico de análises por cliente.

---
Documento de referência original: [`README.original.md`](README.original.md).
