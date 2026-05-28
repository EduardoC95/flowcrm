# FlowCRM - Entrega Final

## Resumo do projeto

FlowCRM e uma aplicacao CRM comercial multi-tenant para gerir entidades, pessoas, calendario, negocios, propostas, produtos, automacoes, leads publicas e apoio comercial com AI.

O projeto esta preparado para demonstracao local com dados demo realistas e comandos de validacao automatizados.

## Stack

- Laravel 12
- Vue 3
- Inertia.js
- TypeScript
- Tailwind CSS
- UI estilo ShadcnVue/Radix Vue
- Pest
- MySQL
- Queue database
- Mail log local

## Credenciais demo

```text
URL: http://flowcrm.test
Email: demo@flowcrm.test
Password: password
```

## Instalacao local

```bash
composer install
npm install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
```

Em Herd, garantir que a pasta do projeto esta disponivel como:

```text
http://flowcrm.test
```

## Modulos implementados

- Dashboard comercial.
- Tenants, roles, policies, middleware e logs.
- Entidades.
- Pessoas/contactos individuais.
- Negocios com pipeline Kanban.
- Calendario e eventos.
- Propostas com upload privado e envio por email.
- Follow-up automatico na etapa Follow Up.
- Produtos em negocios e estatisticas/export CSV.
- Cronologia completa do negocio.
- Criacao rapida de atividades.
- Automacoes internas para negocios sem atividade.
- Notificacoes internas.
- Formularios publicos de leads com iframe embed.
- Chat CRM com intents seguras, streaming e fallback local.
- Agente Comercial AI com backlog de sugestoes e conversao em atividade.

## Seguranca e isolamento por tenant

- Todos os dados CRM principais tem `tenant_id`.
- Middleware `tenant.selected` protege os modulos autenticados.
- Policies validam acesso por tenant e role.
- Roles suportados: owner, manager, sales, viewer.
- Uploads privados ficam em `storage/app/private`.
- Downloads privados passam por controller e policy.
- CSV de estatisticas e streamed, nao gravado em `public`.
- Formularios publicos nao aceitam `tenant_id`, `owner_id` ou etapa manipulavel pelo utilizador.
- Logs respeitam tenant isolation.

## Comandos uteis

```bash
php artisan optimize:clear
php artisan migrate:fresh --seed
php artisan test
npm run build
npm run format:check
```

Automacoes e jobs:

```bash
php artisan queue:work
php artisan followups:send-due
php artisan automations:run
php artisan ai:analyze-commercial
php artisan schedule:run
```

## Integracoes externas

### OpenAI

OpenAI e opcional. Por defeito, o projeto vem com:

```env
OPENAI_ENABLED=false
OPENAI_API_KEY=
OPENAI_MODEL=gpt-5-nano
```

Com esta configuracao, o Chat CRM e o Agente Comercial AI funcionam com fallback local seguro.

### Captcha

Em local:

```env
CAPTCHA_DRIVER=null
```

Para producao, a aplicacao esta preparada para Turnstile:

```env
CAPTCHA_DRIVER=turnstile
TURNSTILE_SITE_KEY=...
TURNSTILE_SECRET_KEY=...
```

## Seguranca pre-entrega

- Tenant isolation aplicado nos modelos CRM.
- Policies/Gates nos modulos principais.
- Uploads privados e downloads autorizados.
- Validacao MIME em propostas.
- Passwords com `Hash::make(...)` explicito.
- Sem `$guarded = []`.
- Listagens principais paginadas.
- Feeds e dropdowns grandes limitados.
- Rate limiting em formularios publicos e AI chat.
- Mail de proposta em queue.
- OpenAI sem chave no frontend e sem SQL livre.
- `SESSION_ENCRYPT=true`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`.

Para producao:

```env
APP_ENV=production
APP_DEBUG=false
APP_URL=https://flowcrm.example.com
SESSION_SECURE_COOKIE=true
SESSION_ENCRYPT=true
SESSION_HTTP_ONLY=true
SESSION_SAME_SITE=lax
```

HTTPS deve ser obrigatorio em producao.

## Checklist final

- [x] Dependencias backend instalaveis por Composer.
- [x] Dependencias frontend instalaveis por npm.
- [x] `.env.example` preparado para local.
- [x] Seed demo completa.
- [x] Testes Pest passam.
- [x] Build frontend passa.
- [x] Format check passa.
- [x] Auditoria de seguranca documentada.
- [x] Guias de demo e apresentacao incluidos.
