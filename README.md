# CRM/ERP v2 (Laravel)

Plataforma SaaS de gestao CRM/ERP multi-tenant para empresas, com isolamento por `company_id`, controlo de acesso por roles/permissoes (Spatie) e modulos de vendas, compras, stock e obra.

## Indice

- [Visao geral](#visao-geral)
- [Stack tecnologica](#stack-tecnologica)
- [Modulos implementados](#modulos-implementados)
- [Arquitetura e padroes](#arquitetura-e-padroes)
- [Regras multi-tenant](#regras-multi-tenant)
- [Instalacao local](#instalacao-local)
- [Configuracao .env](#configuracao-env)
- [Migrations e seeders](#migrations-e-seeders)
- [Comandos uteis](#comandos-uteis)
- [Execucao de testes](#execucao-de-testes)
- [Permissoes e roles](#permissoes-e-roles)
- [Fluxos principais](#fluxos-principais)
- [Importacao/Exportacao CSV de artigos](#importacaoexportacao-csv-de-artigos)
- [Notas de seguranca](#notas-de-seguranca)
- [Guia rapido para novos developers](#guia-rapido-para-novos-developers)
- [Roadmap tecnico recomendado](#roadmap-tecnico-recomendado)

## Visao geral

Este projeto implementa um CRM/ERP empresarial com foco em:

- Multi-tenant por empresa (`company_id`)
- Seguranca e segregacao de dados entre tenants
- Processos de negocio completos (quotes, RFQ, compras, rececoes, stock, obra)
- Interface admin consistente em Blade
- Base para operacao em producao com testes feature

## Stack tecnologica

- PHP `^8.2`
- Laravel `^12`
- Spatie Laravel Permission `^6`
- Dompdf `3.1` (PDFs)
- Vite + Tailwind + Alpine.js
- PHPUnit `^11`

## Modulos implementados

- Gestao de empresas (super admin)
- Utilizadores e convites
- Configuracoes da empresa (inclui SMTP por empresa)
- Unidades, categorias, familias, marcas
- Artigos/produtos (ficha, anexos, stock, custos)
- Clientes e contactos
- Fornecedores e contactos
- Orcamentos (quotes) e dashboard
- RFQ (pedido de cotacao), comparacao e adjudicacao
- Encomendas a fornecedor (Purchase Orders)
- Rececoes de encomenda e integracao com stock
- Movimentos manuais de stock
- Obras (construction sites), diarios, consumos e horas
- Metodos de pagamento, condicoes de pagamento, tabelas de preco
- Taxas de IVA e motivos de isencao (com disponibilidade por empresa)

## Arquitetura e padroes

Padrao principal do backend:

1. `routes/web.php` define endpoints e middleware
2. `Controller` coordena fluxo HTTP
3. `FormRequest` valida e normaliza input
4. `Service` implementa logica de negocio
5. `Model` representa dados e scopes
6. `Policy` garante autorizacao por recurso
7. `Blade` apresenta UI

Praticas usadas no projeto:

- Queries sempre com scope de empresa
- `firstOrFail()` para recurso fora de tenant (resultado esperado: 404)
- Permissoes finas por acao com Spatie
- Regras de negocio em services (evita fat controllers)
- Testes feature por modulo

## Regras multi-tenant

O isolamento de tenant e obrigatorio em todo o projeto.

- Middleware `company.context` resolve empresa atual do utilizador
- Utilizadores de empresa so acedem a dados da propria `company_id`
- Recursos cross-tenant devem devolver `404` (nao `403`) quando aplicavel
- Policies validam ownership por `company_id`
- Scopes tipo `forCompany()` sao usados em consultas de dominio

Exemplo de rota admin protegida:

- Middleware: `auth`, `company.context`, `not.superadmin`
- Prefixo: `/admin`

## Instalacao local

### Requisitos

- PHP 8.2+
- Composer 2+
- Node.js 20+ e npm
- Base de dados (SQLite para dev rapido ou MySQL/MariaDB)

### Setup rapido

```bash
cd c:/xampp/htdocs/crm-erp-v2
composer install
copy .env.example .env
php artisan key:generate
php artisan migrate
php artisan db:seed
npm install
npm run build
```

Opcional (atalho de setup existente no `composer.json`):

```bash
composer run setup
```

> Nota: `composer run setup` nao inclui `php artisan db:seed` por defeito.

## Configuracao .env

Configuracoes minimas recomendadas para dev local:

```env
APP_NAME="CRM ERP"
APP_ENV=local
APP_DEBUG=true
APP_URL=http://localhost:8000

DB_CONNECTION=mysql
DB_HOST=127.0.0.1
DB_PORT=3306
DB_DATABASE=crm_erp_v2
DB_USERNAME=root
DB_PASSWORD=

QUEUE_CONNECTION=database
CACHE_STORE=database
SESSION_DRIVER=database

MAIL_MAILER=log
```

Para SMTP real por empresa, usar o ecran de configuracoes da empresa no admin.

## Migrations e seeders

```bash
php artisan migrate
php artisan db:seed
```

Reset completo em ambiente local:

```bash
php artisan migrate:fresh --seed
```

Seeder principal:

- `Database\\Seeders\\InitialSaasSeeder`

Dados demo criados:

- Role `super_admin`
- Role `company_admin`
- Role `company_user`
- Empresa demo
- Utilizador super admin e admin de empresa

## Comandos uteis

Ambiente de desenvolvimento completo:

```bash
composer run dev
```

Apenas backend:

```bash
php artisan serve
```

Apenas frontend:

```bash
npm run dev
```

Filas:

```bash
php artisan queue:listen --tries=1 --timeout=0
```

Logs (Laravel Pail):

```bash
php artisan pail --timeout=0
```

Limpeza de cache/config:

```bash
php artisan optimize:clear
php artisan config:clear
```

## Execucao de testes

Suite completa:

```bash
php artisan test
```

Teste por modulo (exemplos):

```bash
php artisan test --filter=ArticlesTest
php artisan test --filter=ArticlesImportExportTest
php artisan test --filter=PurchaseOrdersTest
php artisan test --filter=PurchaseOrderReceiptsTest
```

## Permissoes e roles

Sistema baseado em Spatie (`spatie/laravel-permission`):

- `super_admin`: gestao de plataforma
- `company_admin`: gestao total do tenant
- `company_user`: sem permissoes por defeito (atribuir conforme necessidade)

Permissoes seguem naming consistente, por exemplo:

- `company.articles.view`
- `company.articles.create`
- `company.articles.update`
- `company.articles.delete`

## Fluxos principais

### Vendas

1. Criacao de cliente
2. Criacao de quote
3. Geracao/download PDF
4. Envio por email

### Compras

1. Criacao de RFQ
2. Recolha/resposta de fornecedores
3. Comparacao e adjudicacao
4. Geracao de Purchase Order
5. Envio de PO e controlo de estado

### Rececao e stock

1. Criacao de rececao para PO
2. Resolucao de linhas pendentes (artigo existente ou novo)
3. Post da rececao
4. Geracao de movimentos de stock
5. Atualizacao de stock e custo do artigo

### Obras

1. Criacao de obra
2. Registo de diario/fotos/anexos
3. Registo de consumos de material
4. Registo de horas
5. Consolidacao economica

## Importacao/Exportacao CSV de artigos

Disponivel no modulo de artigos:

- Exportacao CSV (download direto)
- Importacao CSV com resumo final

Detalhes de exportacao:

- UTF-8
- Delimitador `;`
- Headers estaveis
- Scope por `company_id`
- Sanitizacao anti CSV formula injection (`=`, `+`, `-`, `@`)

Detalhes de importacao:

- Matching por `reference` (codigo do artigo)
- Atualiza artigo existente ou cria novo
- Cria familia/marca em falta no tenant atual
- Validacao linha a linha com continuacao em caso de erro
- Normalizacao decimal PT (ex: `12,50` -> `12.50`)

Headers suportados:

```text
reference;name;description;family;brand;unit;cost_price;sale_price;is_active;stock_current;stock_ordered_pending
```

## Notas de seguranca

- Nunca usar queries globais sem `company_id` em modulos de tenant
- Preferir `findCompany...OrFail` / `forCompany(...)->whereKey(...)->firstOrFail()`
- Validar autorizacao com Policy antes de operacoes sensiveis
- Uploads sempre validados por tipo/tamanho
- Registar eventos sensiveis com logging estruturado
- Para producao:
  - `APP_ENV=production`
  - `APP_DEBUG=false`
  - HTTPS obrigatorio
  - Rotacao de backups e auditoria de logs

## Guia rapido para novos developers

1. Fazer setup local e correr `php artisan migrate --seed`
2. Entrar com utilizador de empresa demo
3. Validar modulo de artigos e fluxo de stock
4. Executar testes feature do modulo alterado
5. Garantir regras multi-tenant em qualquer novo endpoint

Checklist para novos endpoints:

- Rota com middleware correto
- Policy aplicada
- Query scoped por `company_id`
- Cross-tenant devolve `404`
- Teste feature de autorizacao e isolamento

## Roadmap tecnico recomendado

Curto prazo:

- Cobertura adicional de testes para cenarios negativos e concorrencia
- Endurecimento de validacoes de importacao massiva
- Melhoria de observabilidade (metricas de jobs/importacoes)

Medio prazo:

- Paginacao e filtros avancados em modulos com maior volume
- Refactor incremental para query objects em relatorios complexos
- Hardening de politicas de ficheiros (retencao, limpeza e quotas)

Longo prazo:

- Audit trail funcional por entidade critica
- Jobs assincros para processos pesados (importacoes/exports maiores)
- Dashboards operacionais de KPI por tenant

---

Se precisares, posso tambem gerar uma versao `README-PT.md` (detalhada) e manter um `README.md` mais curto para visao executiva.
