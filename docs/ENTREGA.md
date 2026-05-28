# FlowCRM - Entrega

## Seguranca pre-entrega

O FlowCRM foi revisto antes da entrega para reduzir riscos equivalentes aos encontrados em outros projetos Laravel.

- Tenant isolation: modelos CRM usam `BelongsToTenant`, policies e middleware `tenant.selected`.
- Policies: entidades, pessoas, negocios, calendario, propostas, produtos, formularios, automacoes, chat AI e sugestoes AI exigem autorizacao.
- Uploads privados: propostas ficam no disk `local`, em `storage/app/private/deal-proposals`.
- Downloads autorizados: propostas passam por controller e policy; nao ha URL publica direta para ficheiros privados.
- Validacao MIME: uploads de propostas aceitam apenas `pdf`, `doc`, `docx`, `jpg`, `jpeg`, `png`, ate 10 MB.
- Passwords: controllers, seeders e factories usam `Hash::make(...)` explicitamente.
- Paginacao e limites: listagens principais usam `paginate()`; feeds/dropdowns grandes e mensagens de chat tem limites.
- Rate limiting: formularios publicos de leads e endpoints de Chat CRM/stream/actions usam `throttle`.
- Mail queue/jobs: envio de proposta ao cliente usa `Mail::queue()`; reminders/follow-ups correm via Job/Command.
- OpenAI segura: a API key fica apenas no backend; a IA classifica intents em allowlist e nunca executa SQL livre.
- Sessoes: `.env.example` usa `SESSION_ENCRYPT=true`, `SESSION_HTTP_ONLY=true` e `SESSION_SAME_SITE=lax`.

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

HTTPS deve ser obrigatorio em producao, especialmente para cookies de sessao, login, formularios publicos e endpoints de IA.
