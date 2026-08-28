---
name: surc-config
description: Configura SURC en entorno local con flujo guiado para dueño de plataforma, admin de red y operador. Usa comandos artisan surc:* para crear redes, sedes, usuarios, actores y preparar hosting. Usar cuando el usuario pida configurar la app, limpiar datos demo o administrar estructura operativa.
disable-model-invocation: true
---

# SURC Config Assistant

## Objetivo

Configurar SURC de forma rápida y segura sin operar la UI de Filament, usando comandos artisan helper y un flujo de preguntas guiadas.

## Inicio obligatorio

1. Correr `php artisan surc:status` para leer el estado actual.
   - Si reporta migraciones pendientes: ejecutar `php artisan migrate --force` **antes de cualquier otra operación** y correr `surc:status` nuevamente.
2. Resumir al usuario el estado detectado.
3. Ofrecer este menú:
   - Crear red nueva (+ plantilla y dueño)
  - Agregar sede/clínica
   - Agregar sede/clínica
   - Crear usuario por rol
   - Crear actor (especialista/profesional/cliente)
   - Listar actores / sujetos / flujos
   - Ver estado actual
   - Exportar datos para pasar a hosting
   - Importar datos en hosting
   - Preparar entorno para hosting
   - Borrar una red (destructivo)
   - Reset demo / base local (destructivo)

## Reglas de interacción

- Hacer preguntas cortas y orientadas a completar datos faltantes.
- Confirmar con el usuario antes de ejecutar cada bloque de cambios.
- Después de cada operación, volver a mostrar estado con `surc:status` o un resumen puntual.
- Mantener trazabilidad: indicar qué comando se ejecutó y qué resultado produjo.

## Comandos disponibles

### Estado

- `php artisan surc:status`
- `php artisan surc:status {networkSlugOrId}`
- `php artisan surc:list-actors {networkSlugOrId?}`
- `php artisan surc:list-subjects {networkSlugOrId?}`
- `php artisan surc:list-workflows {networkSlugOrId?}`

### Creación

- `php artisan surc:new-instance` (red + pack + admin de red; pide contraseña, no inventa una)
- `php artisan surc:create-network {name} {slug} {template}`
- `php artisan surc:create-org {network} {name} {slug}`
- `php artisan surc:create-user {email} --role=... --network=... --org=... --password=...`
- `php artisan surc:create-user {email} --owner --password=...`
- `php artisan surc:create-actor {network} {type} {name} --org=... --link-user=... --workflow=...`
- `php artisan surc:export-template {network} --key=taller-mecanico --name="Taller mecánico"`

### Portabilidad de datos

- `php artisan surc:export-data`
- `php artisan surc:import-data --path=... --truncate`

### Destructivos (con confirmación obligatoria)

- `php artisan surc:delete-network {network}`
- `php artisan surc:reset-demo`
- `php artisan surc:reset-demo --fresh`

## Workflow recomendado

1. Crear red + plantilla + admin (`surc:new-instance`) o `surc:create-network` + `surc:create-user`.
2. Crear sedes.
3. Crear usuarios (owner/admin/operator/specialist) **siempre con contraseña informada**.
4. Crear actores y vínculos de especialista.
5. Revisar estado final.

Si la petición es “preparar para hosting”:
1. Inventario (`surc:status`).
2. Definir si se clonan datos (`surc:export-data`) o se arranca vacío con packs JSON (viajan con el deploy).
3. Definir si elimina demo local (`surc:delete-network` / `surc:reset-demo`) o conserva para respaldo.
4. En hosting: `migrate --force`, `db:seed --class=RolePermissionSeeder --force`, `db:seed --class=HelpArticleSeeder --force`. **No** correr `DatabaseSeeder`.
5. Crear owner real (`surc:create-user --owner --password=...`) y cada red con `surc:new-instance`.
6. Si hay bundle, `surc:import-data --truncate` en lugar del alta vacía de esa red.
7. Recordar checklist de producción de `docs/MANUAL_USO.md` Parte F.

## Guardas de seguridad

- Nunca ejecutar operaciones destructivas sin confirmación explícita del usuario.
- Para `surc:delete-network`, repetir nombre/slug de la red antes de ejecutar.
- Para `surc:reset-demo --fresh`, advertir que borra toda la base local.
- No crear scripts temporales para consultas: usar siempre comandos `surc:*`.
- No tocar git config, no hacer push, no manipular secretos.

## Errores y recuperación

- Si `surc:status` reporta migraciones pendientes: ejecutar `php artisan migrate --force` y reiniciar flujo.
- Si un comando falla con `table X has no column named Y`: hay migración pendiente; aplicar migrate y reintentar.
- Si un slug ya existe, proponer una variante y reintentar.
- Si falta rol, indicar que debe ejecutarse seeder de roles/permisos.
- Si no hay red para una operación dependiente, proponer crearla primero.
- Si falla un comando, detener cadena y mostrar causa + siguiente acción segura.

## Recursos adicionales

- Detalle de comandos y ejemplos: [reference.md](reference.md)
- Esquemas de comportamiento y flujos: [schemes.md](schemes.md)
