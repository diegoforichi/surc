# Manual de uso SURC — Desde instalación limpia

Guía paso a paso para poner en marcha SURC y operar el primer caso. Pensado para quien **usa** el sistema, no para desarrolladores.

**Mapa de documentos:** [README.md](README.md)

**Relacionados:** [MANUAL_CONFIGURACION.md](MANUAL_CONFIGURACION.md) (opciones de configuración) · [FLUJO_OPERATIVO.html](FLUJO_OPERATIVO.html) (campos y etapas en detalle) · [`presentacion.html`](../presentacion.html) (guion de demo).

---

## Parte A — Instalación y primer acceso

### A.1 Requisitos

- PHP 8.2+, Composer, MySQL 8 (o SQLite en local)
- Extensiones PHP habituales de Laravel (mbstring, openssl, pdo, etc.)

### A.2 Instalación limpia (servidor / producción demo)

```bash
composer install --no-dev --optimize-autoloader
cp .env.example .env
php artisan key:generate
```

Configurar `.env` (mínimo):

```env
APP_URL=https://tu-dominio.com
DB_CONNECTION=mysql
DB_DATABASE=surc
DB_USERNAME=...
DB_PASSWORD=...

QUEUE_CONNECTION=database
CACHE_STORE=file
SESSION_DRIVER=database
```

```bash
php artisan migrate --force
npm install && npm run build
```

**Cron** (hosting compartido): cada minuto ejecutar `php artisan schedule:run`.

**Cola** (imports en background): periódicamente `php artisan queue:work --stop-when-empty` o un cron que lo invoque.

### A.3 Instalación local con datos de prueba

```bash
composer install
cp .env.example .env
php artisan key:generate
php artisan migrate:fresh --seed
php artisan storage:link
npm install && npm run build
php artisan serve --port=8001
```

En `.env` local: `APP_LOCALE=es` (si el panel sale en inglés, falta este valor). Después de actualizar permisos en una base ya existente: `php artisan surc:sync-roles` (historial y órdenes de venta: sede y operador; el admin de red sigue sin leer el cuaderno ni las órdenes).

Panel: `http://127.0.0.1:8001/admin`

### A.4 Primer usuario (instalación SIN seed)

Si migraste **sin** `--seed`, debés crear el dueño de plataforma manualmente (consola o tinker). Con `--seed` ya existe:

| Email | Rol | Contraseña |
|---|---|---|
| `owner@surc.test` | Dueño plataforma | `password` |

**Importante:** cambiar contraseñas antes de producción demo real.

### A.5 URL única de entrada

Todo el personal entra por la misma dirección:

```
https://tu-dominio.com/admin
```

No hay URL distinta por rol. Lo que cambia es el **usuario y contraseña**.

---

## Parte B — Quién hace qué (orden recomendado)

```
1. Dueño plataforma     → Crea la red
2. Admin de red         → Configura red, sedes, usuarios, actores, flujo
3. Admin de clínica     → Usuarios de su sede, casos y agendas
4. Operador / especialista → Operan casos en el espacio de trabajo
```

| Rol | Qué ve en el panel | Tarea principal | Crear/editar operativa |
|---|---|---|---|
| Dueño plataforma | Plataforma → Redes (y el resto según red) | Crear redes | Sí |
| Admin de red | Configuración, Red, Operativa, Sitio público | Dejar la red lista. **No** lee el cuaderno interno | Sí (`cases.manage`). **No** tiene `history.view` |
| Admin de clínica | Red (usuarios), Operativa | Casos, agendas, **historial interno** y **órdenes de venta** de su sede | Sí (`cases.manage` + `history.*` + catálogo/órdenes) |
| Operador | Dashboard, Agenda y Casos (listados), espacio de trabajo, Capacitación. Si el historial está activo: ficha de sujetos en solo lectura | Recibir, confirmar señas y avanzar casos de su sede. Cargar, finalizar, imprimir y compartir registros del historial interno. Preparar, emitir y exportar órdenes de su sede | No crea ni edita agendas/casos/sujetos: `cases.operate` + `payments.confirm`. Sí `history.*` y `sales.orders.*` si aplica. **No** cambia precios del catálogo |
| Especialista | Dashboard, Agenda y Casos **propios**, Capacitación | Atender sus visitas | No: solo `cases.operate`. No confirma pagos |

Operador y especialista **sí ven** los listados de Agenda y Casos (los de su sede, las agendas **abiertas a la red**, y los casos en una agenda de su sede). **No** pueden crear ni editar esos registros: el alta la hace un perfil con `cases.manage`. El especialista no ve Actores ni Sujetos. El **operador**, si la red y la sede tienen el historial activo, puede **abrir** la ficha del sujeto (solo lectura) para cargar el cuaderno interno; no edita nombre, código ni dueño.

En un negocio de **una sola sede**, use la cuenta de **admin de red** para configurar (módulo, tipos, terminología) y la cuenta de **admin de sede u operador** para el cuaderno. El admin de red no abre el historial.

### Sede en formularios y listados

La **sede** del sujeto y del caso es la de origen. La agenda tiene una sede **anfitriona** (dónde se atiende).

| Perfil | Campo Sede en formularios | Listados operativos |
|---|---|---|
| Admin de red / dueño plataforma | Elige entre todas las sedes de la red | Ve toda la red |
| Admin de clínica, operador u otros con sede asignada | Viene **fija** (no se cambia) | Sujetos y actores de **su sede**. Agendas propias y **abiertas a la red**. Casos propios y los que están en una agenda suya |

El alta de sujeto o caso siempre queda en la sede del usuario. Elegir una agenda abierta de otra clínica no mueve el animal.

---

## Parte C — Puesta en marcha completa (paso a paso)

Ejemplo: **red de veterinarias**. Repetir lógica para peluquería u otro rubro cambiando la plantilla.

### Paso 1 — Crear la red (Dueño plataforma)

1. Ingresar con `owner@surc.test` / `password`.
2. **Plataforma → Redes → Crear**.
3. Completar:
   - **Nombre:** ej. Red Veterinaria Norte
   - **Slug:** ej. `red-vet-norte` (se usa en la URL pública `/red/red-vet-norte`)
   - **Plantilla:** `veterinary` (o `grooming`, `generic`, u otro pack instalado)
   - **Color primario:** opcional
4. Guardar. Al crear, se aplica la plantilla automáticamente (terminología, tipos de actores, flujo de 4 etapas, campos sugeridos, defaults de agenda).

En hosting, en lugar de los pasos 1 y 2, usar `php artisan surc:new-instance` para crear la red y al dueño de la red en un solo paso, eligiendo email y contraseña.

### Paso 2 — Crear admin de la red (Dueño plataforma)

1. **Red → Usuarios → Crear**.
2. Completar nombre, email, contraseña.
3. **Red:** seleccionar la red recién creada.
4. **Roles:** `network_admin`.
5. Guardar y comunicar credenciales al responsable de la red.

A partir de aquí el **admin de red** puede continuar solo.

### Paso 3 — Revisar configuración heredada de la plantilla (Admin de red)

Ingresar con el admin de red creado. Revisar sin obligación de cambiar:

| Menú | Qué verificar |
|---|---|
| **Configuración → Terminología** | Clínica, Animal, Derivación, Especialista, etc. |
| **Configuración → Tipos de actores** | Veterinario encargado, Especialista, Propietario |
| **Configuración → Plantillas de flujo** | 4 etapas con requisitos (seña, ayuno, ficha, consulta) |
| **Configuración → Campos personalizados** | Especie, raza, diagnóstico, tratamiento (visibles al crear/editar) |

Solo editar si el rubro lo requiere. Ver [MANUAL_CONFIGURACION.md](MANUAL_CONFIGURACION.md) para detalle de cada ítem.

### Paso 4 — Registrar sedes / clínicas (Admin de red)

1. **Red → Clínicas** (o el nombre que muestre la terminología) **→ Crear**.
2. Completar: nombre, slug, dirección, teléfono, email.
3. Activar **Mostrar en directorio** si debe aparecer en el sitio público.
4. Repetir por cada clínica de la red.

### Paso 5 — Crear usuarios de cada clínica (Admin de red o admin de clínica)

**Red → Usuarios → Crear**

**Admin de clínica** (gestiona una sede):

- Red: (ya asignada si entrás como admin de red)
- **Sede:** Clínica Norte
- Rol: `organization_admin`

**Operador** (mostrador / agenda):

- Misma sede
- Rol: `operator`

Opcional: **Especialista** con rol `specialist` si tendrá login propio (puede ser usuario distinto al de la clínica).

#### Vincular especialista a su usuario

1. Crear usuario en **Red → Usuarios** con rol `specialist`.
2. En **Operativa → Actores**, editar el especialista y elegir **Usuario vinculado**.
3. Ese usuario verá solo **sus agendas y casos** (visitas donde él es el especialista asignado).

### Paso 6 — Registrar actores (Admin de red o admin de clínica)

Los **actores** son las personas o entidades que participan en los casos. Primero existen los **tipos** (ya vienen de la plantilla); después se cargan las **personas**.

**Operativa → Actores → Crear**

#### 6.1 Propietario (cliente / titular)

- Tipo: **Propietario**
- Nombre: Juan Pérez
- Teléfono, email, documento
- Dirección (si está activa como campo configurable)
- Sede: la clínica (fija si el usuario es de sede)

También se puede dar de alta **desde el formulario del animal**, sin pasar por Actores. El documento evita duplicados **en esa clínica**; otra sede puede tener su propio registro del mismo documento.

#### 6.2 Especialista

- Tipo: **Especialista**
- Nombre: Dra. García
- Biografía: texto para el directorio público
- Sede: opcional
- **Usuario vinculado:** si tendrá login propio, elegir el usuario con rol `specialist`
- **Plantilla de flujo por defecto:** opcional. Si se define, al elegir una agenda de ese especialista en un caso **nuevo**, se sugerirá esa plantilla **solo si el campo Plantilla estaba vacío**.
- El tipo "Especialista" ya tiene **Mostrar en directorio** activo → aparecerá en `/red/{slug}`

> **Nota:** no hay catálogo fijo de "especialidades" (cardiología, etc.). Podés usar la biografía o agregar un campo personalizado en Configuración → Campos personalizados para actores.

#### 6.3 Veterinario encargado (profesional de la clínica)

- Tipo: **Veterinario encargado**
- Nombre, datos de contacto
- Sede: la clínica donde trabaja

### Paso 7 — Registrar sujetos (animales / mascotas / etc.)

**Operativa → Sujetos** (nombre según terminología) **→ Crear**

- **Nombre:** Firulais
- **Código:** opcional (si lo deja vacío se genera automático, por ejemplo `AN-0001`)
- **Sede:** Clínica Norte
- **Propietario:** se puede elegir uno existente o **crearlo ahí mismo** (botón +). Si el documento ya está en esa clínica, se reutiliza y no se duplica.
- Campos extra si la plantilla los trae: especie, raza, sexo, edad (sección **Datos adicionales (configurables)** del formulario)

En **Operativa → Sujetos** puede buscar por **nombre y código**, y filtrar por sede, propietario y estado activo. El nombre del propietario abre su ficha (admin de sede). En esa ficha, la pestaña de animales lista todos los de ese dueño. En la ficha de un animal aparecen los **otros** animales del mismo propietario.

### Paso 8 — Crear el primer caso (derivación / turno)

**Operativa → Casos → Crear**

| Campo | Obligatorio | Ejemplo / comportamiento |
|---|---|---|
| Sede | Sí | Clínica Norte (preseleccionada si el usuario tiene sede fija) |
| Sujeto | No | Firulais (búsqueda por nombre y código) |
| **Plantilla de flujo** | Sí | Se elige **al crear**. En edición queda **fija** (solo lectura). |
| Código | No | Si vacío se genera automático (`CASE-0001`, …) |
| Título | Sí | Interconsulta cardiología |
| Estado | Sí | Default: Abierto |
| Resumen | No | Se suele completar después, en el espacio de trabajo |
| Agenda | No | Solo agendas **Planificada** o **Confirmada** |
| Hora estimada | No | Turno de *este* caso dentro de la agenda |
| Datos adicionales | Según red | Campos personalizados activos |

**Al guardar un caso nuevo**, el sistema:

1. Asigna la red del usuario y genera el código si faltaba.
2. **Respeta la plantilla elegida** en el formulario. Si el campo quedó vacío, usa la plantilla default de la red y, si no hay, la primera activa.
3. Asigna la **primera etapa** de esa plantilla (`in_progress`) y registra la fecha de apertura.

La plantilla **no se puede cambiar después**: las etapas ya están inicializadas. Si hace falta otro flujo, hay que crear otro caso.

Al elegir una **agenda** en el formulario del caso, el sistema completa **solo los campos vacíos**:

- **Plantilla de flujo** — si el especialista de la agenda tiene una por defecto.
- **Hora estimada** — según hora de inicio de la agenda + minutos por paciente × casos ya asignados.

La **sede del caso no cambia**: sigue siendo la clínica de origen (dueña del animal, la seña y el cuaderno). Si la agenda es de otra sede y está **abierta a la red**, el listado muestra **Se atiende en** y la constancia indica dónde presentarse.

En el selector de agenda se muestra **título (si existe) + fecha + especialista + sede**. Si está abierta a la red, el texto termina en **abierta a la red**.

Los valores ya cargados no se sobrescriben. Podés ajustar la hora estimada manualmente.

### Paso 8b — Agenda del día (visita multi-paciente)

Cuando un especialista atiende varios pacientes el mismo día **en una sede**:

1. **Operativa → Agenda → Crear** (usuario de la clínica **anfitriona**, donde se atiende)
   - Sede (fija si el usuario es de esa clínica), especialista, **título**, fecha, hora de inicio, minutos por paciente, estado.
   - **Abierta a la red** (apagada por defecto): otras sedes de la misma red pueden sumar **sus** derivaciones a esta visita. El historial de cada sede sigue siendo privado.
2. Editar la agenda → pestaña **Casos asignados** → **Asignar caso**
   - Solo aparecen casos **sin agenda de esa sede**. La **hora estimada** se propone automáticamente (ej. 09:00, 09:30). La columna **Sede de origen** indica de qué clínica viene cada paciente.
3. Otras clínicas no editan esa agenda: crean el caso en **Operativa → Casos**, eligen la agenda abierta y el animal queda en su sede.
4. La lista de agendas permite filtrar por fecha y por **Abierta a la red**.

La anfitriona opera el espacio de trabajo (etapas, constancia, seña) de los casos que le derivaron. **No** abre la ficha ni el cuaderno del animal ajeno; solo ve un registro si la clínica de origen lo **compartió** con esa derivación.

El admin de red puede crear agendas de cualquier sede; el flujo diario es de la clínica anfitriona.

Sobre el estado de agenda:
- Si `agenda.auto_done` está activo, la agenda pasa a **Realizada** cuando todos sus casos quedan **cerrados o cancelados**.
- También se puede **Marcar realizada** manualmente (con aviso si aún hay casos abiertos).
- Al **Cancelar** una agenda, el sistema muestra un resumen de casos cerrados/cancelados/abiertos antes de confirmar.

Con el seed demo (`migrate:fresh --seed`) ya existe una agenda de ejemplo con dos casos en Clínica Norte para el día siguiente.

**Especialista demo:** `especialista@clinica-norte.test` / `password` — ve solo su agenda y entra a cada caso desde **Espacio de trabajo**.

### Paso 9 — Operar el caso (Espacio de trabajo)

En el listado de casos o desde la agenda (pestaña **Casos asignados** → **Espacio de trabajo**), abrir `/cases/{id}`.

Detalle de campos y requisitos: [FLUJO_OPERATIVO.html](FLUJO_OPERATIVO.html) (también [PDF](FLUJO_OPERATIVO.pdf)).

El espacio de trabajo **guía el flujo por etapas**:
- Arriba hay un **stepper** con las etapas del caso (completada / en curso / pendiente).
- Solo la **etapa actual** es editable; las pasadas se ven en solo lectura; las futuras están bloqueadas.
- Excepción: la sección **Archivos adjuntos** permite agregar/quitar archivos mientras el caso esté abierto, aunque ya hayas avanzado de etapa.
- Cada acción queda registrada en el panel **Auditoría** (quién hizo qué y cuándo), con scroll para revisar historiales largos.
- Si el caso ya terminó, se muestra el banner de cierre según el rubro (ej.: **Atendido**, **Entregado** o **Resuelto**) con fecha/hora y autor del cierre.

Si el caso tiene agenda asignada, arriba verá la fecha, hora estimada y especialista de la visita. El ticket 80mm también incluye fecha/hora del turno.

> **Confirmar pago:** usuarios con `payments.confirm` (admin de red, admin de sede y **operador**) ven el botón Confirmar. El especialista no confirma señas.

Si una instalación anterior no deja confirmar al operador, ejecutar `php artisan surc:sync-roles`.

#### Etapa 1 — Preagenda e intención

1. En **Pagos / Seña:** monto + método → **Registrar**.
2. Clic en **Confirmar** en ese pago.
3. Clic en **Avanzar etapa**.

#### Etapa 2 — Confirmación

1. Marcar checklist: **Ayuno indicado**.
2. En **Archivos adjuntos**, subir estudios previos (radiografía, etc.).
3. Verificar pago confirmado.
4. **Avanzar etapa**.

#### Archivos adjuntos (durante todo el caso abierto)

- Se gestionan en la sección **Archivos adjuntos** del workspace.
- Puede subir uno o varios archivos y descargarlos desde la lista.
- Puede eliminar archivos cargados por error mientras el caso esté abierto.
- Al cerrar o cancelar el caso, los adjuntos quedan en solo lectura.

#### Etapa 3 — Ficha resumida

1. Escribir el resumen clínico en el cuadro de texto.
2. Completar campos personalizados si los hay (diagnóstico previo, etc.).
3. **Guardar ficha**.
4. **Avanzar etapa**.

#### Etapa 4 — Día de consulta

1. Cargar diagnóstico y tratamiento en **Día de consulta**.
2. Elegir **Especialista** del listado o escribir nombre libre (técnico sin usuario), según reglas de la red.
3. **Finalizar consulta / Finalizar servicio / Finalizar y cerrar** (según rubro) → pide confirmación y cierra el caso en un solo paso.
4. Después del cierre, el caso queda en **solo lectura** (sin edición de ficha, consulta ni adjuntos).

### Textos operativos por rubro (UX)

SURC permite adaptar textos de botones/estados desde **Configuración → Terminología** con claves `ux.*`.

Claves principales:

- `ux.status_attended`: etiqueta visual del caso cerrado (veterinaria: *Atendido*, peluquería: *Entregado*, genérico: *Resuelto*).
- `ux.action_finish`: botón de cierre en etapa terminal.
- `ux.consultation_title`: título de la sección terminal del espacio de trabajo.
- `ux.agenda_confirmed_warn`: mensaje de confirmación de agenda con observaciones.

#### Constancia de inscripción (80 mm)

Botón **Constancia 80mm** (espacio de trabajo, caso y agenda) → imprimir desde el navegador o descargar PDF.

Incluye sede, código, número de orden en la agenda, sujeto, responsable, fecha, servicio, especialista, hora estimada o “horario a confirmar”, indicaciones (de la plantilla de flujo, con ajuste opcional por agenda), consentimiento y espacio de firma en papel. Cada emisión queda en auditoría. Luego se puede adjuntar el papel firmado en el espacio de trabajo.

#### Informe completo del caso

Botón **Descargar informe** → genera un PDF A4 con datos completos:

- Datos generales del caso y agenda.
- Ficha resumida y campos personalizados.
- Consulta (diagnóstico, tratamiento, responsable, notas).
- Pagos, adjuntos y eventos/auditoría.

### Dashboard diario

En el panel inicial verá:

- **Agendas pendientes** — visitas Planificadas/Confirmadas. Columnas: **título**, fecha, sede, especialista, estado y progreso “casos listos / total”. El filtro de fecha viene por defecto **desde hoy** (el chip lo indica); se puede cambiar o limpiar para ver otros días. **Abrir agenda** solo lo ven perfiles con `cases.manage`.
- **Casos del día** — casos con hora estimada de hoy **o** cuya agenda es hoy, con acceso directo al espacio de trabajo.

### Paso 10 — Sitio público de la red (opcional, Admin de red)

**URL:** `https://tu-dominio.com/red/{slug}`

Contenido (para clientes, sin login):

- Home resumida con slogan y descripción de la red, novedades, destacados y enlace a WhatsApp **solo si la red tiene número propio** (nunca se usa el de una clínica)
- Páginas de listado: `/sedes`, `/especialistas`, `/blog`
- Ficha de cada sede (foto, contacto, WhatsApp, sitio web) y de cada especialista
- Páginas de ayuda/legales de **esa** red
- Las secciones de especialistas, blog y ayuda siguen visibles aunque estén vacías

Se alimenta con clínicas/actores que tengan **mostrar en directorio** y con contenidos publicados en **Sitio público** (carrusel, blog, páginas). El admin de red carga el perfil institucional en **Sitio público → Datos institucionales** (logo, foto de portada, slogan, descripción, teléfono, correo, WhatsApp y dirección). Los borradores no se ven. El HTML se muestra sanitizado.

La raíz `/` sigue llevando al panel de SURC (`/admin`). Este sitio es institucional: no pide turnos online. Cada usuario cambia su contraseña en el **perfil** del panel.

Los **tutoriales internos** no se publican en el sitio: están en el panel, **Ayuda → Capacitación**. La URL antigua `/red/{slug}/tutoriales` redirige ahí (requiere login).

### Paso 10b — Capacitación interna (usuarios autenticados)

En el panel: **Ayuda → Capacitación**. Es la guía operativa del personal (no del cliente). Cada usuario ve las guías de su rol (y las comunes). Los textos usan la **terminología de la red**: en veterinaria se lee Animal / Historia clínica / Derivación; en otro rubro, los nombres del pack. Los tipos de registro (Consulta, Control, etc.) salen del catálogo de esa red.

Hay un botón **Descargar guía PDF** por artículo (login; mismo recorte por rol). No incluye credenciales ni datos clínicos.

Los videos se cargan con URL de YouTube no listado o Vimeo; no se suben archivos de video.

El **dueño de plataforma** crea y publica los artículos en **Plataforma → Capacitación**. El contenido es global (no una copia por red). Para que un rubro nuevo se lea bien, se configura o exporta un pack de industria; no se duplica el manual.

### Paso 11 — Historial longitudinal (opcional)

No es una historia clínica veterinaria fija ni una agenda interna: es un **cuaderno del sujeto** (animal, vehículo, equipo o lo que nombre la red), **de esa sede**. Otras sedes no lo ven. La pestaña **Casos asociados** lista las derivaciones; la pestaña de historia (el nombre lo da la terminología de la red: en veterinaria, **Historia clínica**) es el cuaderno interno.

1. En **Preferencias operativas**, activar **Habilitar** el módulo a nivel red (lo hace el admin de red).
2. En cada sede, activar **Usar** el mismo módulo.
3. En **Operativa → Sujetos**, abrir la ficha con una cuenta de **sede** (admin de clínica u operador). El admin de red configura tipos y flags; **no** lee, edita, comparte ni imprime el contenido.
4. Cargar un registro desde la **ficha de historial** (botón en la ficha del sujeto): acciones rápidas por tipo (Consulta, Control, Vacuna, Estudio u otros de la red), fecha, resumen (si queda vacío se propone desde hallazgos/producto/resultados al finalizar), campos extra y adjuntos privados. El borrador puede quedar incompleto; **Finalizar** exige los campos obligatorios del tipo. Lo finalizado no se edita: se corrige con **adenda**.
5. **Descargar PDF** (un registro final) o **Descargar ficha PDF** (carátula con identidad, último peso y próximos controles, luego los finales de esa sede con adendas). No incluye borradores ni archivos binarios; sí lista los nombres de adjuntos. Cada descarga queda en el registro de actividad.
6. **Compartir con caso** es opt-in y solo en registros **finalizados**. Si no se comparte, el especialista no ve nada de ese cuaderno. El destino aparece en **Compartido con** y, en el espacio de trabajo, como **Historial compartido** (tipo, fecha y resumen; sin adjuntos ni cuaderno completo).
7. **Incorporar resultado al historial** solo en un caso **cerrado**, una vez.
8. El dashboard muestra **Próximos de mi clínica** (vencidos, 7 y 30 días) solo con registros **finales** de la sede del usuario.

### Paso 12 — Orden de venta para el ERP (opt-in de la sede)

SURC **no factura**. Desde un **registro clínico final** de la sede dueña del historial se puede preparar una **orden de venta interna** (PDF + CSV genérico UTF-8) para facturar después en el ERP.

1. El admin de clínica carga el **catálogo de venta** de su sede (código ERP, producto/servicio, precio, impuesto informativo, moneda ISO). Ninguna sede ve precios de otra. El operador usa ítems activos; no cambia el maestro.
2. En la ficha de la sede: **Datos comerciales** (moneda, p. ej. `UYU`, prefijo `OV`, razón social). No reutiliza la moneda de las señas.
3. En un registro **final**, **Orden de venta**. Se sugiere el catálogo ligado a ese tipo clínico; se pueden agregar líneas manuales. No copia diagnóstico, tratamiento, resumen ni adjuntos. Si el registro viene de un caso con seña confirmada, la seña aparece como **referencia informativa**, no como producto.
4. Borrador editable; **Emitir** asigna número correlativo (`OV-000001`) y deja la orden inmutable. Una sola orden activa por registro (idempotente). Tras anular un borrador se puede armar otra.
5. **PDF** (sin historia clínica) y **CSV** (una fila por línea, identificador estable `order_uid`). Cada descarga se audita. El CSV no se guarda en disco. Campo **Referencia ERP** para anotar luego el número del facturador.

La orden pertenece a la **sede del historial**, aunque el caso se haya atendido en una agenda abierta de otra clínica. Anfitriona, especialista, admin de red y dueño de plataforma no la abren. Agenda, workflow y señas no cambian.

Si el módulo de historial está apagado, la operativa mínima (agenda + caso) no cambia. El dueño de plataforma y el admin de red no leen el contenido del historial ni las órdenes clínicas.

---

## Parte D — Requisitos de cada etapa (referencia rápida)

Configurables en **Configuración → Plantillas de flujo → Etapas**.

| Tipo | Cómo se cumple en el caso |
|---|---|
| Casilla | Marcar checkbox en el espacio de trabajo |
| Pago / Seña | Registrar pago y **confirmarlo** |
| Archivo | Subir adjunto (estudios previos) |
| Campo | Depende de la clave: ficha cargada, técnico interviniente |

Plantilla veterinaria por defecto:

1. Preagenda → seña/pago  
2. Confirmación → pago confirmado, ayuno, estudios  
3. Ficha resumida → resumen clínico  
4. Consulta → técnico interviniente  

---

## Parte E — Importación masiva (opcional)

**Importación → Nuevo lote**

**Sujetos** (`subjects`):

```csv
label_name,code,species,breed
Firulais,AN-001,Canino,Labrador
```

**Actores** (`parties`) — necesitás el `actor_type_id` del tipo (ver en Configuración → Tipos de actores):

```csv
actor_type_id,display_name,email,phone
2,Dra. García,dra@ejemplo.com,111222333
```

El procesamiento corre en cola (`database`). Revisar el lote para ver filas OK y errores.

---

## Parte F — Checklist producción demo

Los packs de industria (`database/industry-packs/*.json`) viajan con el código: no hay que cargarlos a mano en el hosting. La plantilla veterinaria es el ejemplo de referencia; `generic` sirve para arrancar un rubro nuevo.

- [ ] En local: inventario (`php artisan surc:status`). Si se quiere clonar una red con datos, export (`php artisan surc:export-data`)
- [ ] Copiar al hosting el código (incluye `database/industry-packs/`). Si hay bundle de datos, copiar también el export y `storage/app`
- [ ] Hosting: `.env` con `APP_ENV=production`, `APP_DEBUG=false`, `APP_NAME=SURC`, `APP_LOCALE=es`, `APP_TIMEZONE=America/Argentina/Buenos_Aires`, `APP_URL` https y MySQL
- [ ] Hosting: `php artisan migrate --force`
- [ ] Hosting: `php artisan db:seed --class=RolePermissionSeeder --force` y `php artisan surc:sync-roles` (**no** correr `DatabaseSeeder` en producción: crea redes, usuarios y contraseñas de demo)
- [ ] Hosting: `php artisan db:seed --class=HelpArticleSeeder --force` para publicar las guías globales (o cargarlas a mano en Plataforma → Capacitación)
- [ ] Owner real creado con `php artisan surc:create-user --owner --password=...` (la contraseña es obligatoria; no hay valor por defecto)
- [ ] Cada red: `php artisan surc:new-instance` (elige pack, email y contraseña del dueño de la red; no inventa credenciales)
- [ ] Alternativa si hay bundle: `php artisan surc:import-data --path=... --truncate` en lugar de `surc:new-instance` para esa red
- [ ] Hosting: `php artisan storage:link`
- [ ] Hosting: `php artisan config:cache` y `route:cache`
- [ ] Recuperación de contraseña: configurar correo real (`MAIL_MAILER`)
- [ ] Backup de MySQL + `storage/app` y prueba de restauración
- [ ] Probar un caso completo de punta a punta (incluye constancia)
- [ ] Verificar sitio público `/red/{slug}` si la red lo usa
- [ ] Cron `schedule:run` + worker de cola si se usa importación CSV
- [ ] HTTPS y monitoreo de `/up`

### Limpieza opcional de redes demo (local)

Si no querés conservar datos de demo en local:

- Eliminar una red puntual: `php artisan surc:delete-network {slug}`
- Limpiar demos por slug/nombre: `php artisan surc:reset-demo --force`
- Reiniciar todo desde cero: `php artisan surc:reset-demo --fresh --force`

---

## Parte G — Usuarios demo (solo entorno local con seed)

| Email | Rol | Uso |
|---|---|---|
| `owner@surc.test` | Plataforma | Crear redes |
| `admin@red-veterinaria.test` | Admin red | Configurar red veterinaria demo |
| `admin@clinica-norte.test` | Admin clínica | Casos en Clínica Norte |
| `operador@clinica-norte.test` | Operador | Espacio de trabajo |
| `especialista@clinica-norte.test` | Especialista | Su agenda y casos asignados |
| `admin@red-peluqueria.test` | Admin red | Red peluquería demo |

Contraseña en todos: `password`

**Sitios demo:**

- Veterinaria: `/red/red-veterinaria`
- Peluquería: `/red/red-peluqueria`

---

## Resumen en una frase

**Dueño crea red → Admin de red carga clínicas, usuarios y actores → Admin crea propietario, animal, agenda y caso (plantilla fija al crear) → Asigna hora → Operador/especialista avanza etapas en el espacio de trabajo hasta cerrar el caso.**
