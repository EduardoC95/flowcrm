# FlowCRM - Comandos de Demo

## Instalar dependencias

```bash
composer install
npm install
```

## Preparar ambiente

```bash
cp .env.example .env
php artisan key:generate
```

Criar base de dados MySQL:

```sql
CREATE DATABASE flowcrm CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
```

## Reset de base de dados e seed

```bash
php artisan migrate:fresh --seed
```

## Correr aplicacao

Com Laravel Herd, abrir:

```text
http://flowcrm.test
```

Alternativa com servidor artisan:

```bash
php artisan serve
```

## Correr frontend

```bash
npm run dev
```

## Correr tudo em desenvolvimento

```bash
composer dev
```

## Correr queue

```bash
php artisan queue:work
```

## Testes e build

```bash
php artisan test
npm run build
npm run format:check
```

## Comandos de automacao

Enviar follow-ups automaticos vencidos:

```bash
php artisan followups:send-due
```

Executar automacoes internas:

```bash
php artisan automations:run
```

Gerar sugestoes do Agente Comercial AI:

```bash
php artisan ai:analyze-commercial
```

Executar scheduler:

```bash
php artisan schedule:run
```

## Login demo

```text
Email: demo@flowcrm.test
Password: password
```

## Fluxo rapido de demonstracao

1. `php artisan migrate:fresh --seed`
2. `npm run dev`
3. Abrir `http://flowcrm.test`
4. Login com `demo@flowcrm.test / password`
5. Mostrar dashboard, negocios, detalhe de negocio, calendario, automacoes, formularios publicos, Chat CRM e Agente Comercial AI.
