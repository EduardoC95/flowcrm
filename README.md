# FlowCRM

FlowCRM e um CRM comercial multi-tenant para gestao de entidades, pessoas, calendario, negocios, propostas, produtos, automacoes, formularios publicos de leads e apoio comercial com AI.

O projeto foi preparado para demonstracao local, com dados demo completos, isolamento por tenant, policies, logs de atividade, queue database, mail em log e integracoes externas configuraveis sem dependencia obrigatoria em ambiente local.

## Stack tecnica

- Laravel 12
- PHP 8.2+
- Vue 3
- Inertia.js
- TypeScript
- Tailwind CSS
- Componentes UI estilo ShadcnVue/Radix Vue
- Pest
- MySQL
- Vite
- Queue database
- Mail log em ambiente local

## Funcionalidades principais

- Tenants, roles por tenant e isolamento automatico de dados.
- Policies/Gates para controlo de acesso por modulo.
- Dashboard comercial com indicadores e atalhos.
- Entidades com CRUD, pesquisa, filtros, pessoas associadas, negocios e historico.
- Pessoas/contactos com CRUD, associacao a entidade e merge de duplicados.
- Negocios com pipeline Kanban, etapas configuraveis, drag-and-drop e responsavel.
- Calendario com tarefas, chamadas, reunioes, notas, lembretes e associacao a registos CRM.
- Propostas no detalhe do negocio, upload privado, download autorizado e envio por email.
- Follow-up automatico quando um negocio entra na etapa Follow Up.
- Produtos nos negocios, estatisticas por quantidade/valor e export CSV.
- Cronologia completa no negocio e criacao rapida de atividades.
- Automacoes para negocios sem atividade, com criacao automatica de atividades.
- Formularios publicos de leads com campos configuraveis, captcha preparado e iframe embed.
- Chat CRM com intents seguras, streaming/fallback local e acoes rapidas controladas.
- Agente Comercial AI com backlog de sugestoes, scoring e conversao em atividade.
- Logs de atividade e notificacoes internas.

## Requisitos locais

- PHP 8.2 ou superior
- Composer
- Node.js 20+ e npm
- MySQL
- Laravel Herd ou servidor local equivalente
- Base de dados MySQL chamada `flowcrm`

## Instalacao local

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
```

Criar a base de dados MySQL:

```sql
CREATE DATABASE flowcrm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

Aplicar migrations e seed demo:

```bash
php artisan migrate:fresh --seed
```

Em Laravel Herd, garantir que o site responde em:

```text
http://flowcrm.test
```

## Configuracao `.env`

Valores esperados para desenvolvimento local:

```env
APP_NAME=FlowCRM
APP_URL=http://flowcrm.test

DB_CONNECTION=mysql
DB_DATABASE=flowcrm
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
MAIL_MAILER=log
FILESYSTEM_DISK=local

OPENAI_ENABLED=false
OPENAI_API_KEY=
OPENAI_MODEL=gpt-5-nano
OPENAI_BASE_URL=https://api.openai.com/v1

CAPTCHA_DRIVER=null
TURNSTILE_SITE_KEY=
TURNSTILE_SECRET_KEY=

SESSION_ENCRYPT=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
SESSION_SECURE_COOKIE=false
```

## Comandos de desenvolvimento

Executar backend, queue, logs e frontend em paralelo:

```bash
composer dev
```

Executar frontend separadamente:

```bash
npm run dev
```

Executar queue local:

```bash
php artisan queue:work
```

Executar scheduler manualmente:

```bash
php artisan schedule:run
```

## Comandos de teste e build

```bash
php artisan optimize:clear
php artisan migrate:fresh --seed
php artisan test
npm run build
npm run format:check
```

## Comandos de automacao

Enviar follow-ups de negocios devidos:

```bash
php artisan followups:send-due
```

Executar automacoes de negocios sem atividade:

```bash
php artisan automations:run
```

Gerar sugestoes do Agente Comercial AI:

```bash
php artisan ai:analyze-commercial
```

Executar todas as tarefas agendadas:

```bash
php artisan schedule:run
```

## Credenciais demo

```text
Email: demo@flowcrm.test
Password: password
```

A seed cria tenant, user demo, entidades, pessoas, negocios, etapas, calendario, propostas, follow-ups, produtos, automacoes, formularios publicos, chat CRM, sugestoes AI, logs e notificacoes.

## OpenAI opcional

A integracao OpenAI e configuravel. Em local, `OPENAI_ENABLED=false` permite usar o Chat CRM e o Agente Comercial AI com fallback local seguro.

Para ativar em ambiente configurado:

```env
OPENAI_ENABLED=true
OPENAI_API_KEY=...
OPENAI_MODEL=gpt-5-nano
```

A chave nunca deve ser exposta no frontend. A aplicacao usa intents permitidas e nao executa SQL livre gerado por AI.

## Captcha local e producao

Em local/testing, `CAPTCHA_DRIVER=null` usa verificador nulo para manter a demonstracao sem servicos externos.

Em producao, configurar Turnstile:

```env
CAPTCHA_DRIVER=turnstile
TURNSTILE_SITE_KEY=...
TURNSTILE_SECRET_KEY=...
```

## Notas de seguranca essenciais

- Dados CRM sao isolados por tenant.
- Rotas CRM usam `auth`, `verified` e `tenant.selected`.
- Uploads de propostas ficam em storage privado.
- Downloads de propostas passam por policy.
- Uploads validam MIME e tamanho.
- Passwords usam `Hash::make(...)` explicitamente.
- Listagens principais usam paginacao.
- Endpoints publicos e AI usam rate limiting.
- Emails de propostas usam queue.
- Sessoes sao encriptadas por defeito no `.env.example`.

Para detalhes, consultar:

- `docs/SECURITY_AUDIT.md`
- `docs/ENTREGA.md`
- `docs/CHECKLIST_REQUISITOS.md`
- `docs/COMANDOS_DEMO.md`
- `docs/GUIAO_APRESENTACAO.md`
