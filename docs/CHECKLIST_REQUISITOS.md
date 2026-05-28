# FlowCRM - Checklist de Requisitos

Estados usados:

- Implementado
- Implementado com fallback local
- Preparado para integracao futura
- Nice-to-have nao obrigatorio

| Requisito | Estado | Evidencia |
| --- | --- | --- |
| Tenants, roles, policies e logs | Implementado | Middleware `tenant.selected`, trait `BelongsToTenant`, roles owner/manager/sales/viewer, policies e `activity_logs`. |
| Entidades | Implementado | CRUD, pesquisa/filtros, pessoas associadas, negocios e historico. |
| Pessoas/contactos | Implementado | CRUD, associacao a entidade, eventos/negocios e merge de duplicados. |
| Calendario | Implementado | Eventos, tarefas, chamadas, reunioes, notas, lembretes e associacao a entidades/pessoas/negocios. |
| Negocios/Kanban | Implementado | Pipeline Lead, Proposta, Negociacao, Follow Up, Ganho e Perdido com board Kanban. |
| Drag-and-drop no Kanban | Implementado | Endpoint `deals.move-stage` e UI do board atualizam etapa. |
| Owner/responsavel do negocio | Implementado | `owner_id` nos negocios e filtros por responsavel. |
| Propostas no negocio | Implementado | Upload privado, download autorizado, preview de email e envio ao cliente. |
| Cliente recebe proposta em anexo | Implementado | `DealProposalMail` anexa ficheiro guardado em storage privado. |
| Historico de envio de propostas | Implementado | Registo em proposta, logs e timeline do negocio. |
| Follow-up automatico na etapa Follow Up | Implementado | `DealFollowUp`, templates, command `followups:send-due` e historico de emails. |
| Parar follow-up ao sair da etapa | Implementado | Integracao no movimento de etapa do negocio. |
| Marcar cliente respondeu | Implementado | Acao manual para parar ciclo como `replied`. |
| Produtos nos negocios | Implementado | `products` e `deal_products`, gestao no detalhe do negocio. |
| Estatisticas de produtos | Implementado | Ranking por quantidade/valor, filtros e export CSV. |
| Automações de negocios sem atividade | Implementado | Regras configuraveis, command `automations:run`, atividades e notificacoes. |
| Cronologia completa do negocio | Implementado | Timeline agrega logs, eventos, notas, propostas, follow-ups e produtos. |
| Atividades rapidas no negocio | Implementado | Criacao de nota, tarefa, chamada, reuniao e lembrete no detalhe. |
| Formularios publicos de leads | Implementado | Campos configuraveis, submissao publica, criacao automatica de Person/Deal e embed iframe. |
| Captcha | Implementado com fallback local | `CAPTCHA_DRIVER=null` local/testing e Turnstile preparado para producao. |
| Chat CRM | Implementado com fallback local | Intents seguras, historico, sugestoes, streaming simulado/fallback e acoes rapidas. |
| Streaming de resposta | Implementado com fallback local | SSE em `/ai-chat/{conversation}/stream`; se OpenAI nao estiver ativa, stream do texto gerado localmente. |
| OpenAI gpt-5-nano | Implementado com fallback local | `OPENAI_MODEL=gpt-5-nano`, `OPENAI_ENABLED=false` por defeito, fallback local seguro. |
| Agente Comercial AI | Implementado com fallback local | Backlog de sugestoes, command `ai:analyze-commercial`, scoring e conversao em atividade. |
| Aprendizagem por aceitar/ignorar/adiar/arquivar | Implementado | Historico ajusta score por tipo de sugestao para o utilizador. |
| Monitorizacao de nova atividade/negocio/contacto | Implementado | Observers para Deal, CalendarEvent, DealNote e Person. |
| Google Calendar OAuth | Preparado para integracao futura | O dominio de calendario esta isolado e pronto para sincronizacao futura; OAuth nao e requisito obrigatorio implementado nesta entrega. |
| AI generativa para criar propostas completas | Nice-to-have nao obrigatorio | O modulo de propostas suporta upload/envio; geracao completa por AI fica fora do escopo atual. |
