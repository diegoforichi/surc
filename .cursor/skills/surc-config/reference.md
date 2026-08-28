# Referencia de comandos `surc:*`

## Inventario y diagnóstico

### `surc:status`

Muestra resumen de redes, sedes, usuarios, actores y conteos operativos.

```bash
php artisan surc:status
php artisan surc:status red-veterinaria
```

### `surc:list-actors`

Lista actores con tipo, sede, usuario vinculado y cantidad de sujetos asignados.

```bash
php artisan surc:list-actors
php artisan surc:list-actors red-veterinaria
```

### `surc:list-subjects`

Lista sujetos con propietario, sede y estado.

```bash
php artisan surc:list-subjects
php artisan surc:list-subjects red-veterinaria
```

### `surc:list-workflows`

Lista plantillas de flujo con etapas y requisitos.

```bash
php artisan surc:list-workflows
php artisan surc:list-workflows red-veterinaria
```

## Portabilidad local -> hosting

### `surc:export-data`

Genera bundle portable (JSON + copia de `storage/app`) para migrar datos entre motores de base.

```bash
php artisan surc:export-data
php artisan surc:export-data --path=storage/app/surc-export
```

### `surc:import-data`

Importa bundle en destino preservando IDs. Se recomienda `--truncate`.

```bash
php artisan surc:import-data --path=storage/app/surc-export/20260702_120000 --truncate
```

## Alta de estructura

### `surc:new-instance`

Crea red, aplica pack de industria y da de alta al admin de red. Pide contraseña; no genera credenciales por defecto.

```bash
php artisan surc:new-instance
php artisan surc:new-instance --name="Red Norte" --slug=red-norte --template=veterinary --admin-name="Admin Norte" --admin-email=admin@red-norte.test --admin-password=... --org="Clínica Centro"
```

Opciones: `--name` `--slug` `--template` `--color` `--admin-name` `--admin-email` `--admin-password` `--org`.

Los packs viven en `database/industry-packs/*.json` (`veterinary`, `grooming`, `generic`). Para un rubro nuevo, configurar una red y `surc:export-template`.

### `surc:create-network`

Crea red y aplica plantilla (sin usuario).

```bash
php artisan surc:create-network "Red Norte" red-norte veterinary
```

Opciones:
- `--color=#0d9488`
- `--inactive`

### `surc:export-template`

Genera un pack JSON desde una red ya configurada.

```bash
php artisan surc:export-template red-norte --key=taller-mecanico --name="Taller mecánico"
```

Opciones:
- `--key=...`
- `--name=...`
- `--output=...` (por defecto `database/industry-packs/{key}.json`)
- `--force`

### `surc:create-org`

Crea sede/organización dentro de una red.

```bash
php artisan surc:create-org red-norte "Clínica Centro" clinica-centro --address="Calle 1"
```

Opciones:
- `--phone=...`
- `--email=...`
- `--inactive`
- `--hide-directory`

### `surc:create-user`

Crea usuario y asigna rol/alcance.

```bash
php artisan surc:create-user admin@red-norte.test --role=network_admin --network=red-norte --name="Admin Red Norte" --password=...
php artisan surc:create-user operador@centro.test --role=operator --network=red-norte --org=clinica-centro --password=...
php artisan surc:create-user owner@surc.test --owner --name="Dueño Plataforma" --password=...
```

Opciones:
- `--password=...` (obligatoria; no hay valor por defecto)
- `--role=platform_owner|network_admin|organization_admin|operator|specialist`

### `surc:create-actor`

Crea actor (especialista/profesional/cliente).

```bash
php artisan surc:create-actor red-norte specialist "Dra. García" --org=clinica-centro --link-user=especialista@centro.test --workflow="Flujo de derivación veterinaria"
php artisan surc:create-actor red-norte client "Juan Pérez" --phone="111234567"
```

Opciones:
- `--email=...`
- `--document=...`
- `--inactive`

## Operaciones destructivas

### `surc:delete-network`

Elimina una red y todos sus datos relacionados (cascade delete).

```bash
php artisan surc:delete-network red-veterinaria
php artisan surc:delete-network 3 --force
```

### `surc:reset-demo`

Limpia redes demo por slug/nombre.

```bash
php artisan surc:reset-demo
php artisan surc:reset-demo --force
```

### `surc:reset-demo --fresh`

Reinicia toda la base local y ejecuta seed.

```bash
php artisan surc:reset-demo --fresh
php artisan surc:reset-demo --fresh --force
```

## Matriz de roles recomendada

Roles existentes (seed):
- `platform_owner`
- `network_admin`
- `organization_admin`
- `operator`
- `specialist`

Permisos fuente: [database/seeders/RolePermissionSeeder.php](../../../database/seeders/RolePermissionSeeder.php)

## Flujo de preparación para hosting

1. Inventario:
   - `php artisan surc:status`
2. Exportar datos del entorno local:
   - `php artisan surc:export-data`
3. En hosting, preparar esquema y roles:
   - `php artisan migrate --force`
   - `php artisan db:seed --class=RolePermissionSeeder`
4. Importar bundle:
   - `php artisan surc:import-data --path=... --truncate`
5. Limpiar demo local (opcional):
   - `php artisan surc:delete-network red-prueba-skill`
   - o `php artisan surc:reset-demo --force`
6. Crear owner real:
   - `php artisan surc:create-user owner@tu-dominio.com --owner --name="Owner Real"`
7. Checklist de producción:
   - Revisar [docs/MANUAL_USO.md](../../../docs/MANUAL_USO.md), sección “Checklist producción demo”.
8. Optimización final:
   - `php artisan config:cache`
   - `php artisan route:cache`

## Fallback con tinker (si un helper no cubre un caso)

Usar solo cuando no exista comando `surc:*` equivalente y documentar lo hecho.
No crear scripts temporales para consultar datos si ya existe comando de lectura.

```bash
php artisan tinker
```

Crear red manual:

```php
$network = App\Models\Network::create([
  'name' => 'Red Ejemplo',
  'slug' => 'red-ejemplo',
  'industry_template_key' => 'veterinary',
  'is_active' => true,
]);
app(App\Actions\Templates\ApplyIndustryTemplate::class)->handle($network, 'veterinary');
```
