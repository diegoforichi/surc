---
alwaysApply: true
---

# SURC — contexto para agentes

Laravel 12 / PHP 8.2 / Filament 3.3 / Livewire / PHPUnit 11. Producto multi-red (SaaS) para casos, agendas y flujos por plantilla de industria.

## Dónde está parado el producto

Leer primero:

- [`docs/README.md`](docs/README.md) — índice
- [`docs/MANUAL_USO.md`](docs/MANUAL_USO.md) — operación
- [`docs/MANUAL_CONFIGURACION.md`](docs/MANUAL_CONFIGURACION.md) — configuración
- [`docs/FLUJO_OPERATIVO.html`](docs/FLUJO_OPERATIVO.html) — campos y etapas
- [`presentacion.html`](presentacion.html) — demo
- Skill de setup/hosting: [`.cursor/skills/surc-config/SKILL.md`](.cursor/skills/surc-config/SKILL.md)
- Sync docs ↔ presentación: [`.cursor/rules/docs-presentacion-sync.mdc`](.cursor/rules/docs-presentacion-sync.mdc)

Respuestas al usuario en **español**. Textos de UI: terminología de red vía `terminology()`, no atar copy a un solo rubro.

## Stack y normas

- Tests: **PHPUnit** (no Pest). `php artisan make:test --phpunit`. Cada cambio de comportamiento lleva test.
- Estilo: Laravel Pint, preset `laravel` (`pint.json`). `vendor/bin/pint --dirty`.
- Archivos de aplicación: preferir **menos de 900 líneas**.
- Comandos de estructura del producto: `php artisan surc:*` (no scripts temporales para inventario).
- Hosting: checklist en MANUAL_USO Parte F; no push ni secretos. SSH/deploy **solo** `civetdur.uy` en `public_html/website_bb9e69c3` — regla [`.cursor/rules/hosting-civetdur.mdc`](.cursor/rules/hosting-civetdur.mdc). La cuenta `puntoco2` tiene otros sitios: no tocarlos.

## Laravel Boost (obligatorio en este repo)

MCP `laravel-boost` está instalado. Antes de escribir código Laravel/Filament/Livewire:

1. Usar `search-docs` (Boost) con queries temáticas, no adivinar APIs.
2. Usar `application-info` / `database-schema` cuando haga falta versión o tablas.
3. Preferir Boost a tinker suelto o SQL a ciegas.

PHPUnit, no Pest. `php artisan test --compact` con filtro mínimo; suite completa al cerrar un cambio.

<laravel-boost-guidelines>

## Boost

- Prefer Boost MCP tools over shell guesses for app URLs, schema, logs, and package docs.
- Use `search-docs` before code changes. Multiple broad queries. Do not put package names in the query string.
- Run Artisan with `--no-interaction` and explicit options.

## Laravel 12

- `bootstrap/app.php` registers middleware, exceptions, and routing.
- `bootstrap/providers.php` lists application providers.
- Console commands in `app/Console/Commands` are auto-discovered.
- Eloquent casts live in `casts()` when following this app's models.

## PHPUnit

- This application uses PHPUnit. Create tests with `php artisan make:test --phpunit {name} --no-interaction`.
- Do not write Pest tests.
- Cover happy path, failure path, and relevant edge cases.
- Do not delete tests without explicit approval.
- After updating a test, run that file or `--filter` first.

## Pint

- `pint.json` uses the `laravel` preset.
- Check style with `vendor/bin/pint --test` or fix with `vendor/bin/pint --dirty`.

## Testing habit

- Every behavior change must be programmatically tested.
- Use model factories; check existing factory states before hand-rolling models.
- Named routes via `route()`.

## SURC domain

- Network tenancy: scope queries with existing concerns (`ScopesToUserNetwork`, `HasNetworkFormFields`, `CaseOperationalAccess`).
- `User::fixedOrganizationId()` is null for platform owner and network admin; otherwise the user's organization.
- Case workflow: `InitializeCaseWorkflow` must respect `workflow_template_id` already set on the case; fall back to network default only if blank. Do not overwrite the chosen template.
- Case template field is locked on edit in Filament (`disabled` + `dehydrated`).
- Agenda labels: `Agenda::optionLabel()`. Suggested slot: `Agenda::suggestedScheduledAtForNextCase()`.
- Historial interno: opt-in red+sede. `network_admin` configura (`config.manage`) y **no** lee el cuaderno (`HistoryAccess` bloquea el rol). Operan `organization_admin` y `operator`. Ficha `/{record}/history` (timeline). PDFs A4 de registro final y ficha completa vía `history.print`, auditados. Compartir con un caso es opt-in y no entrega adjuntos.
- Órdenes de venta: catálogo y moneda por clínica. Nacen de un registro clínico **final** de la sede dueña del historial (`SalesAccess`). Admin de clínica administra catálogo; admin y operador emiten/exportan. Especialista, anfitriona, admin de red y platform owner no leen órdenes. CSV/PDF internos; SURC no factura ni habla con el ERP por API. La seña no es línea de venta.

</laravel-boost-guidelines>
