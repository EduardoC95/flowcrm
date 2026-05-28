# FlowCRM - Auditoria de Seguranca Pre-Entrega

Auditoria executada sobre o FlowCRM apos o commit `d46c89e Add commercial AI suggestion backlog`.

| Item auditado | Estado | Evidencia | Correcao aplicada | Observacoes |
| --- | --- | --- | --- | --- |
| 1. Rotas publicas indevidas | Corrigido | `php artisan route:list -vv` mostra os modulos CRM sob `auth`, `verified` e `tenant.selected`. Rotas publicas esperadas: auth do starter, `/`, e `/public/lead-forms/{slug}` com throttle. | Removido healthcheck publico `/up` em `bootstrap/app.php`. | A rota `/` e apenas pagina publica sem dados CRM. |
| 2. Assets/documentos privados | Corrigido | Propostas sao guardadas no disk `local` em `storage/app/private/deal-proposals`; download passa por `DealProposalController@download` e `DealProposalPolicy@download`. | `config/filesystems.php` alterado para `local.serve=false`, removendo a rota publica `storage/{path}` para o disk privado. | Export CSV usa `streamDownload`, sem gravar ficheiro publico. |
| 3. Uploads com validacao MIME | OK | `StoreDealProposalRequest` valida `required`, `file`, `mimes:pdf,doc,docx,jpg,jpeg,png`, `max:10240`. | Nenhuma correcao adicional necessaria. | `rg "file\\(|hasFile|mimes:|mimetypes:|max:" app` nao encontrou outro upload sem MIME. |
| 4. XML Injection / SOAP / VIES | Nao aplicavel | `rg "SOAP|soap|xml|SimpleXMLElement|DOMDocument|VIES|vat|country_code|vat_number|<\\?xml|payload" app config routes resources` nao encontrou integracao SOAP/XML/VIES. | Nenhuma. | Nao existe integracao SOAP/XML/VIES no FlowCRM, logo o risco especifico de XML Injection nao se aplica. |
| 5. Password hashing explicito | OK | `RegisteredUserController`, `NewPasswordController`, `Settings/PasswordController`, `DatabaseSeeder` e `UserFactory` usam `Hash::make(...)`. | Nenhuma. | O cast `hashed` no `User` existe, mas nao e a unica protecao. |
| 6. Mass assignment | OK | `rg "guarded\\s*=\\s*\\[\\]" app database tests` devolveu zero ocorrencias. | Nenhuma. | Modelos auditados usam `$fillable`. |
| 7. Paginacao/listagens | Corrigido | Index principais usam `paginate()`: entidades, pessoas, negocios, calendario, produtos, automacoes, formularios, notificacoes, sugestoes AI. | Limitados feeds/dropdowns grandes em `CalendarEventController` e historico do chat ativo em `AIChatController`. | `get()` mantido apenas em dropdowns/colecoes controladas, relatatorios agregados ou queries com `limit()`. |
| 8. Race conditions/codigos/slugs | OK | `tenants.slug` e `lead_forms.slug` tem unique index nas migrations. `LeadFormController` e `TenantOnboardingController` resolvem slug antes de gravar. | Nenhuma correcao estrutural necessaria. | Nao ha numeracao sequencial critica de propostas/negocios. Em colisao concorrente, o unique index impede duplicado. |
| 9. Rate limiting | OK | `POST /public/lead-forms/{slug}` tem `throttle:10,1`; `GET` publico tem `throttle:30,1`; AI chat mensagens/actions/stream/suggestions tem throttle. | Nenhuma. | Endpoints com OpenAI passam pelo backend e estao rate limited no chat. |
| 10. Emails sincronos no request | Corrigido | `DealProposalController@send` usava `Mail::send()` no request HTTP. | Alterado para `Mail::queue()` e teste atualizado para `Mail::assertQueued()`. | Emails de reminders/follow-ups correm em Job/Command, fora do request HTTP. |
| 11. Downloads/PDF/export com autorizacao | OK | Proposal download chama `authorizeProposalForDeal(..., 'download')` e valida `deal_id`/tenant; Product Stats export chama `Gate::authorize('viewAny', Product::class)`. | Nenhuma adicional. | CSV e streamed; nao fica em `public`. |
| 12. Sessoes seguras no `.env.example` | Corrigido | `.env.example` tinha `SESSION_ENCRYPT=false`. | Alterado para `SESSION_ENCRYPT=true`, `SESSION_SECURE_COOKIE=false`, `SESSION_HTTP_ONLY=true`, `SESSION_SAME_SITE=lax`. | Em producao, usar `SESSION_SECURE_COOKIE=true` e `APP_URL=https://...`. |
| 13. Controllers demasiado grandes | Observado | Maiores controllers: `DealController` ~574 linhas, `CalendarEventController` ~379, `AISuggestionController` ~318. | Sem refactor gigante; logica nova permanece em services/form requests. | Risco aceite para entrega porque testes passam e responsabilidades principais ja foram extraidas para services. |
| 14. Logs e dados pessoais/RGPD | Corrigido | Submissoes de lead mostravam IP completo e `user_agent` no backend enviado ao frontend. | `LeadFormController@show` agora mascara IP e nao envia `user_agent` para a pagina. | Logs internos continuam guardados por auditoria, respeitando tenant isolation. |
| 15. Lookups/datasets em memoria | Corrigido | `rg "->get\\(" app/Http/Controllers app/Services` indicou feeds e mensagens de chat sem limite. | `CalendarEventController` limita feed/opcoes a 500; `AIChatController` limita conversa ativa a 100 mensagens. | Outros `get()` sao agregacoes, dropdowns por tenant ou queries com `limit()`. |
| 16. Testes Feature | OK | Existem testes: `TenantFoundationTest`, `EntityModuleTest`, `PersonModuleTest`, `DealModuleTest`, `CalendarEventModuleTest`, `DealProposalTest`, `DealFollowUpTest`, `ProductModuleTest`, `DealTimelineTest`, `AutomationWorkflowTest`, `LeadFormModuleTest`, `AIChatModuleTest`, `CommercialAgentSuggestionTest`. | Teste de proposta atualizado para confirmar queue. | Cobrem isolamento, roles, uploads/downloads, IA, automacoes e modulos principais. |
| 17. OpenAI/IA segura | OK | `OPENAI_API_KEY` nao aparece em `resources/js`; intents sao allowlist em `CRMQueryIntentService`; fallback local em `LocalCRMIntentParser`; SQL livre da IA nao existe. | Nenhuma. | `DB::raw` existe apenas em `ProductStatsService` para agregacoes fixas, sem input arbitrario. |

## Comandos de auditoria usados

```bash
php artisan route:list -vv
rg "Storage::url|asset\\(|public_path|storage_path|response\\(\\)->file|download" app routes resources database
rg "file\\(|hasFile|mimes:|mimetypes:|max:" app
rg "SOAP|soap|xml|SimpleXMLElement|DOMDocument|VIES|vat|country_code|vat_number|<\\?xml|payload" app config routes resources
rg "password" app database tests
rg "guarded\\s*=\\s*\\[\\]" app database tests
rg -- "->get\\(" app/Http/Controllers app/Services
rg "Mail::send|Mail::to|queue\\(|later\\(|send\\(" app
rg "download|streamDownload|response\\(\\)->stream|pdf|csv|export" app routes
rg "OPENAI_API_KEY|api_key|Authorization" resources app
rg "DB::raw|statement\\(|selectRaw|whereRaw|unprepared" app/Services app/Http
```

## Validacao final de seguranca

Comandos finais executados na auditoria:

```bash
php artisan optimize:clear
php artisan migrate:fresh --seed
php artisan route:list
php artisan test
npm run build
npm run format:check
rg "guarded\\s*=\\s*\\[\\]" app database tests
rg "OPENAI_API_KEY" resources
git diff --check
```

Resultado esperado apos a auditoria:

- `php artisan route:list` nao apresenta `/up` nem `storage/{path}`.
- `php artisan test` passa a suite Feature/Unit.
- `npm run build` compila assets de producao.
- `npm run format:check` confirma formatacao dos recursos frontend.
- Pesquisa por `$guarded = []` devolve zero ocorrencias.
- Pesquisa por `OPENAI_API_KEY` em `resources` devolve zero ocorrencias.
