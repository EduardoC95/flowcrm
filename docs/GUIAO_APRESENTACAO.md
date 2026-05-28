# FlowCRM - Guiao de Apresentacao

Duracao sugerida: 8 a 10 minutos.

## 1. Login e dashboard

- Abrir `http://flowcrm.test`.
- Entrar com `demo@flowcrm.test / password`.
- Mostrar o dashboard com indicadores, atalhos e sugestoes comerciais.

Mensagem-chave: o FlowCRM arranca diretamente numa visao operacional para equipa comercial.

## 2. Tenant ativo e seguranca

- Explicar que os dados pertencem ao tenant ativo.
- Referir roles: owner, manager, sales e viewer.
- Referir policies, middleware `tenant.selected` e logs.

Mensagem-chave: cada utilizador so acede aos dados do seu tenant e permissao.

## 3. Entidades e Pessoas

- Abrir Entidades.
- Mostrar pesquisa/filtros e detalhe com pessoas e negocios.
- Abrir Pessoas.
- Mostrar associacao a entidade e merge de duplicados.

Mensagem-chave: a base comercial esta estruturada entre empresas e contactos individuais.

## 4. Negocios/Kanban

- Abrir Negocios.
- Abrir Pipeline Kanban.
- Mostrar etapas: Lead, Proposta, Negociacao, Follow Up, Ganho, Perdido.
- Mencionar drag-and-drop e totais por etapa.

Mensagem-chave: o pipeline permite acompanhar oportunidades de forma visual e acionavel.

## 5. Detalhe do negocio

- Abrir um negocio demo.
- Mostrar proposta, produtos, timeline e atividades rapidas.
- Explicar que propostas sao privadas, downloads autorizados e emails ficam no historico.

Mensagem-chave: o detalhe do negocio concentra toda a operacao comercial.

## 6. Calendario

- Abrir Calendario.
- Mostrar tarefas, chamadas, reunioes, notas e lembretes.
- Mostrar associacao a entidades, pessoas ou negocios.

Mensagem-chave: atividades comerciais ficam ligadas ao contexto CRM.

## 7. Follow-up automatico

- Mostrar um negocio em Follow Up.
- Explicar ciclo de emails de dois em dois dias, templates alternados e horario util.
- Mostrar acoes: cancelar e marcar cliente respondeu.

Mensagem-chave: o sistema reduz esquecimentos sem tirar controlo ao utilizador.

## 8. Automacoes

- Abrir Automações.
- Mostrar regra de negocio sem atividade.
- Explicar que a automacao cria atividade no calendario e notificacao interna.

Mensagem-chave: regras simples transformam sinais de risco em tarefas concretas.

## 9. Formularios publicos de leads

- Abrir Formularios de Leads.
- Mostrar campos configuraveis, URL publica e iframe.
- Abrir formulario publico e explicar criacao automatica de pessoa e negocio.

Mensagem-chave: leads externas entram diretamente no CRM com origem e auditoria.

## 10. Chat CRM

- Abrir Chat CRM.
- Fazer pergunta exemplo: "Qual o volume de negocios no estado Negociacao?"
- Mostrar resposta, links para registos e fallback local.

Mensagem-chave: perguntas em linguagem natural viram consultas seguras ao CRM.

## 11. Agente Comercial AI

- Abrir Agente Comercial AI.
- Mostrar backlog de sugestoes, score, razao e acao sugerida.
- Converter uma sugestao em atividade.

Mensagem-chave: o sistema ajuda a equipa a decidir o proximo passo de maior valor.

## 12. Seguranca e auditoria final

- Referir `docs/SECURITY_AUDIT.md`.
- Destacar uploads privados, downloads autorizados, rate limiting, sessions seguras e ausencia de SQL livre vindo da AI.
- Referir testes e build finais.

Mensagem-chave: o projeto esta demonstravel e preparado para entrega com validacao tecnica documentada.
