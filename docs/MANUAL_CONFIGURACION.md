# Manual de configuración SURC — Administrador de Red

> **¿Primera vez con el sistema?** Usá primero [MANUAL_USO.md](MANUAL_USO.md) (instalación limpia y operación paso a paso). Este documento es referencia de **opciones de configuración**.

**Mapa de documentos:** [README.md](README.md) · detalle de campos y etapas: [FLUJO_OPERATIVO.html](FLUJO_OPERATIVO.html).

Este manual describe cómo configurar una red para cualquier rubro (veterinaria, peluquería, taller, etc.) sin modificar código. Las plantillas de industria son archivos JSON en `database/industry-packs/` y viajan con el deploy.

## 1. Crear o seleccionar la red

**Dueño de plataforma** (en local de demo: `owner@surc.test`):
1. Ingresar a `/admin`.
2. Ir a **Plataforma → Redes**.
3. Crear red con nombre, slug y plantilla de industria (el select lista los packs instalados).
4. Al crear, se aplica automáticamente la plantilla.

En hosting, el alta típica es un solo comando que pide las credenciales del dueño de la red (sin usuarios ni contraseñas por defecto):

```bash
php artisan surc:new-instance
```

Pregunta nombre y slug de la red, pack a aplicar, color, nombre/email/contraseña del admin de red y, opcionalmente, la primera sede.

## 2. Aplicar o cambiar plantilla

En **Redes → Aplicar plantilla** se recargan:
- Nomenclatura (cómo se llaman sedes, sujetos, casos, etc.)
- Tipos de actores
- Flujo de trabajo (etapas y requisitos)
- Campos personalizados sugeridos
- Preferencias (agenda, cierre de caso, historial)
- Defaults de agenda (duración de turno, hora de inicio, instrucciones y consentimiento)

**Atención:** esto reemplaza la configuración existente de la red.

Packs incluidos (archivos en `database/industry-packs/`):
- `veterinary` — Red de clínicas veterinarias (molde de referencia)
- `grooming` — Peluquerías
- `generic` — Base mínima editable, sin campos de un rubro concreto

Para sumar un rubro nuevo (taller mecánico, electrónica, celulares): configurá una red desde el panel y exportala:

```bash
php artisan surc:export-template red-configurada --key=taller-mecanico --name="Taller mecánico"
```

El JSON queda en `database/industry-packs/` y, en el próximo deploy, aparece en el select. Sobrescribir un pack existente requiere `--force`.

La **guía del personal** no viaja en ese JSON: Capacitación es un circuito aparte, con tokens (`{{subject}}`, `{{history}}`, `{{history_types}}`, …) que se resuelven con la terminología y los tipos de **la red de quien lee**. Ejemplo de las mismas claves:

| Clave | Veterinaria (pack actual) | Informática (cuando exista el pack) |
|---|---|---|
| organization | Clínica | Taller o sucursal |
| subject | Animal | Equipo |
| case | Derivación | Ticket u orden |
| history | Historia clínica | Historial técnico |
| history_entry_types | Consulta, Control, Vacuna, Estudio | Los que defina ese pack |

No hace falta un segundo manual ni meter la Capacitación dentro de `veterinary.json`. Hasta no haber una red informática real, no se publica un pack de ese rubro.

Comando para reaplicar un pack a una red ya creada:
```bash
php artisan surc:apply-template red-veterinaria veterinary
```

## 3. Configurar nomenclatura

**Configuración → Terminología**

| Clave | Uso |
|---|---|
| organization | Nombre de las sedes |
| subject | Entidad atendida |
| client | Titular / cliente |
| case | Caso / turno / derivación |
| specialist | Recurso experto |
| agenda | Visita que agrupa varios casos en una fecha |
| history | Cuaderno interno (historia clínica, historial técnico, etc.) |
| history_entry | Un registro de ese cuaderno |

### Claves UX configurables (`ux.*`)

Estas claves controlan textos del flujo operativo:

| Clave | Dónde se usa | Default veterinary | Default grooming | Default generic |
|---|---|---|---|---|
| ux.status_attended | Banner/estado visual del caso cerrado | Atendido | Entregado | Resuelto |
| ux.action_finish | Botón de cierre en etapa terminal | Finalizar consulta | Finalizar servicio | Finalizar y cerrar |
| ux.consultation_title | Título de la sección terminal del workspace | Día de consulta | Entrega | Cierre |
| ux.agenda_confirmed_warn | Título al confirmar agenda con pendientes | Agenda confirmada con observaciones | Agenda confirmada con observaciones | Agenda confirmada con observaciones |
| ux.case_diagnosis | Campo de cierre del caso (hallazgos) | Diagnóstico | Observaciones del servicio | Hallazgos |
| ux.case_treatment | Campo de cierre del caso (trabajo o indicaciones) | Tratamiento | Indicaciones | Trabajo a realizar |

`label_plural` puede dejarse vacío en claves `ux.*` porque representan mensajes, no entidades.

## 4. Configurar actores

**Configuración → Tipos de actores**

Defina quién participa: profesionales, especialistas, clientes, etc.
- **Vinculable a usuario**: si puede tener login propio.
- **Mostrar en directorio**: aparece en el sitio público de la red.

En actores de categoría **especialista/profesional**, puede definir una **plantilla de flujo por defecto**. Se usa como sugerencia al elegir una agenda de ese especialista en un caso **nuevo**, y **solo si** el campo Plantilla del caso está vacío.

Luego cree actores concretos en **Operativa → Actores**.

## 5. Configurar flujo de trabajo

**Configuración → Plantillas de flujo**

Cada plantilla tiene etapas ordenadas. En cada etapa configure requisitos:
- **Casilla** — confirmación manual
- **Pago / Seña** — exige pago **confirmado** (no alcanza con registrarlo)
- **Archivo** — adjunto obligatorio
- **Campo** — dato cargado (resumen, técnico interviniente)

### Cómo se asigna al caso

1. Al **crear** el caso el campo Plantilla es obligatorio.
2. Si el usuario elige una agenda cuyo especialista tiene plantilla por defecto, se sugiere esa plantilla **solo si el campo estaba vacío**.
3. Al guardar, el sistema **respeta la plantilla elegida**. Si por algún motivo quedó vacía, usa la default de la red (`is_default`) y, si no hay, la primera activa.
4. En **edición** el campo queda bloqueado (solo lectura): cambiarla dejaría etapas inconsistentes.

### Claves del espacio de trabajo

El workspace mapea 4 secciones de UI a claves de etapa. Si la clave no existe, usa el orden (índice 0–3):

| Sección UI | Clave preferida | Fallback (orden) |
|---|---|---|
| Pagos / Seña | `pre_intent` | 1.ª etapa |
| Checklist | `confirmation` | 2.ª etapa |
| Ficha resumida | `summary` | 3.ª etapa |
| Cierre / consulta | `consultation` | 4.ª etapa (terminal) |

Plantilla veterinaria: `pre_intent` → `confirmation` → `summary` → `consultation`.  
Peluquería: `reservation` → `confirmation` → `service` → `delivery` (las secciones caen por orden).  
Genérico: `intent` → `confirmation` → `summary` → `execution`.

### Claves reservadas del flujo (importante)

Si una plantilla ya tiene casos asociados, no conviene renombrar ni eliminar las claves que el código usa para validar:

- Etapas veterinaria: `pre_intent`, `confirmation`, `summary`, `consultation`
- Requisitos: `prior_studies`, `payment_confirmed`, `deposit_registered`, `technical_responsible`, `summary_loaded`

Estas claves están conectadas con validaciones y secciones del espacio de trabajo.

## 6. Campos personalizados

**Configuración → Campos personalizados**

Agregue campos extra para actores, sujetos o casos. Los valores se guardan en metadata JSON.

**Importante:** los campos activos aparecen automáticamente en los formularios de **Operativa → Actores**, **Sujetos** y **Casos**, en la sección **Datos adicionales**. En actores, el tipo elegido determina qué campos se muestran.

Plantillas base incluyen sugerencias listas para usar:
- Veterinaria: en sujetos (`species`, `breed`, `sex`, `age`) y en actores (`address`).
- Peluquería: en sujetos (`breed`, `sex`, `age`) y en actores (`address`).

## 7. Agenda (visitas multi-paciente)

**Operativa → Agenda** (nombre según terminología)

Una agenda agrupa varios casos bajo la visita de un especialista **a una sede anfitriona** en una fecha. El caso y el sujeto siguen siendo de la **sede de origen**.

1. Crear la agenda: sede anfitriona, especialista, **título** (opcional), fecha, hora de inicio, **minutos por paciente**, estado y casilla **Abierta a la red** (apagada por defecto).
2. En la edición, pestaña **Casos asignados**: asignar casos **de esa sede** aún sin agenda, con **hora estimada sugerida**. Se ve la sede de origen de cada caso.
3. Otras sedes de la red, si la agenda está abierta, eligen esa visita al crear el caso. No editan la agenda ajena.
4. La lista principal permite filtrar por fecha y por **Abierta a la red**.

También puede asignar agenda desde **Operativa → Casos** al crear o editar un caso. Al elegir la agenda, el sistema completa **solo los campos vacíos**:

- **Plantilla de flujo** (si el especialista tiene una por defecto).
- **Hora estimada** (misma fórmula que arriba).

La sede del caso **no se pisa** con la de la agenda: el animal, la seña y el cuaderno quedan en la clínica de origen. La anfitriona opera la visita; no lee el historial ajeno.

La etiqueta de agenda en selectores combina **título (si existe), fecha, especialista y sede**. Si está abierta a la red, se agrega ese texto.

La hora sugerida se calcula como:
- `hora_inicio + (cantidad_asignados * minutos_por_paciente)`.
- Puede editarse manualmente por paciente si lo necesitás.

### Confirmación manual de agenda (configurable)

La agenda siempre se confirma manualmente, pero podés definir el comportamiento en **Configuración → Preferencias operativas**:

- **Estricto**: bloquea confirmación si hay casos incompletos.
- **Con alerta**: permite confirmar y avisa qué casos no cumplieron.
- **Libre**: confirma sin validaciones.

También podés elegir qué significa “caso listo” (`pago confirmado` o `etapa de confirmación completada`) y si la agenda pasa a **Realizada** automáticamente cuando todos sus casos quedan cerrados o cancelados.

## 8. Preferencias operativas

En **Configuración → Preferencias operativas** configure:

- Modo de confirmación de agenda.
- Criterio de “caso listo”.
- Auto marcado de agenda en estado Realizada (cuando todos los casos quedan **cerrados o cancelados**).
- Si la finalización de consulta exige diagnóstico.
- Si la finalización de consulta exige técnico responsable.
- Si la entidad sujeto/animal está activa (si se desactiva, el menú de sujetos no aparece).
- Si el **historial longitudinal** está habilitado a nivel red (cada sede lo activa por separado).

## 9. Operativa diaria

1. Crear **actores** (propietario, especialista) y **sujetos**.
   - En sujetos, la tabla permite búsqueda por nombre/código y filtros por sede/propietario/activo.
2. Crear **caso** desde Operativa → Casos.
   - Elegir **plantilla de flujo** (queda fija después). Si no informás código, se genera automático (por red) en formato legible.
3. Asignar el caso a una **agenda** con hora estimada (opcional pero recomendado). Desde el caso o desde la agenda; en ambos casos la hora se puede sugerir automáticamente.
4. Abrir **Espacio de trabajo** para gestionar etapas, pagos, ficha y consulta.
5. Imprimir **constancia 80mm** desde el espacio de trabajo, el caso o la agenda.

## 10. Importación CSV

**Importación → Nuevo lote**

Formato sugerido para sujetos:
```csv
label_name,code,species,breed
Firulais,AN-001,Canino,Labrador
```

Formato para actores:
```csv
actor_type_id,display_name,email,phone
1,Dr. Pérez,dr@ejemplo.com,111222333
```

## 11. Sitio público de la red

URL: `/red/{slug-de-la-red}`

Gestione contenido en **Sitio público**:
- **Datos institucionales** (logo del encabezado, foto de portada, slogan, descripción, teléfono, correo, WhatsApp y dirección de la **red**; el WhatsApp de la portada es solo este, nunca el de una clínica)
- Carrusel
- Blog (complete **fecha de publicación** al publicar; el HTML del editor se muestra sanitizado)
- Páginas (ayuda, legales)

Las fotos de red/sede/especialista se cargan en cada ficha (si el widget queda en “Loading”, no guardar: el archivo suele estar; recargar la ficha). Complete WhatsApp en sedes y especialistas para sus fichas. Las sedes y especialistas se listan si tienen "mostrar en directorio".

El sitio público tiene home resumida y listados en `/sedes`, `/especialistas`, `/blog` y `/ayuda`. Las secciones vacías de especialistas, blog y ayuda siguen visibles.

Los **manuales y videos internos** no se editan acá: el dueño de plataforma los publica en **Plataforma → Capacitación**. Los usuarios autenticados los leen en **Ayuda → Capacitación**, filtrados por rol y con los nombres de su red; pueden descargar un PDF del artículo. Videos: URL de YouTube no listado o Vimeo. El sitio público no muestra esas guías.

Si hay tutoriales viejos en `public_content`, pasarlos a borradores con `php artisan surc:migrate-tutorials-to-help`.

## 11b. Historial longitudinal (opt-in)

Módulo genérico del **sujeto**, no atado a un rubro. Cada sede es dueña de su cuaderno. Los **tipos de registro** (en veterinaria: Consulta, Control, Vacuna, Estudio) y sus **campos** (peso, temperatura, diagnóstico/hallazgos, tratamiento, producto/lote, próxima fecha, resultados) se configuran por red en **Configuración → Tipos de registro**. El admin de red habilita el módulo, las sedes y los tipos; **no** abre el contenido. La operación (cargar, finalizar, adenda, compartir, imprimir) queda en admin de sede y operador. Adjuntos privados (PDF/foto) viven en el registro. Lo finalizado es inmutable (adendas agrupadas en el detalle). Hay PDF A4 de un registro final y de la ficha completa (solo finales de esa sede; se audita cada descarga). **Compartir con caso** es manual y no envía adjuntos ni el historial completo. El operador puede abrir la ficha en solo lectura; no crea sujetos. Cuotas, e-factura y ERP quedan para una etapa posterior.

Para actualizar tipos y etiquetas de historia en una red ya existente, sincronizar por clave de tipo y de terminología. **No** volver a pulsar «Aplicar plantilla»: reconstruye terminología, actores y flujos.

## 12. Roles y permisos operativos

| Rol | Alcance | Permisos clave |
|---|---|---|
| platform_owner | Toda la plataforma | Operativa completa. **No** tiene acceso de lectura al contenido del historial |
| network_admin | Configuración y operativa de la red | `cases.manage`, `config.manage`, `organizations.manage` — crea/edita operativa y habilita el módulo. **Sin** `history.*`: no lee, edita, comparte ni imprime el cuaderno |
| organization_admin | Su sede | `cases.manage`, `payments.confirm`, `history.view/manage/finalize/share/print` — listados y formularios enfocados en su sede; opera e imprime el historial |
| operator | Operar casos de su sede (si tiene sede asignada) | `cases.operate`, `payments.confirm`, `history.view/manage/finalize/share/print` — ve listados de Agenda/Casos y workspace; **no** crea ni edita agendas/casos/sujetos. **Sí** confirma señas. Con historial activo, abre la ficha del sujeto en solo lectura y opera el cuaderno |
| specialist | Sus casos (agendas donde es el especialista) | `cases.operate` — ve solo sus agendas/casos vinculados por **Usuario vinculado** en el actor. No confirma pagos |

### Alcance por sede

Usuarios con **sede asignada** (`organization_admin`, `operator` y otros perfiles operativos con `organization_id`):

- En formularios de operativa (agenda, caso, actor, sujeto, usuario), el campo **Sede** queda **fijo** en la suya: no se puede cambiar en pantalla ni enviando otro valor.
- Las opciones de sujetos, actores y usuarios se limitan a esa sede.
- Los listados de sujetos y actores muestran solo esa sede.
- **Agenda:** ven las de su sede y las de otras sedes marcadas **Abierta a la red**. Solo editan, confirman o cancelan las de **su** sede.
- **Casos:** ven los de su sede y los que están en una agenda de su sede (derivaciones recibidas). El alta y la ficha del sujeto siguen en la clínica de origen.

El **admin de red** ve y opera todas las sedes de la red (casos, agendas, sujetos) y edita **Datos institucionales**. El cuaderno interno de historial queda en las cuentas de cada sede.

**Diferencia importante:**
- `cases.manage` — crear/editar actores, sujetos, casos, agendas.
- `cases.operate` — ver agenda/casos asignados y usar el **espacio de trabajo**.
- `payments.confirm` — confirmar señas/pagos (acción sensible, no todos los roles).

En una instalación ya existente, sincronizar roles y permisos sin borrar datos: `php artisan surc:sync-roles`. El panel usa `APP_LOCALE=es`.

Dentro del espacio de trabajo, quien tenga acceso puede editar la etapa actual; todas las acciones quedan en **Auditoría** (activity log + eventos del caso).

### Dashboard operativo

El dashboard muestra widgets de:

- **Agendas pendientes** — agendas Planificadas/Confirmadas. Incluye columna **Título**. Filtro de fecha editable; por defecto aplica `desde hoy`, pero puede cambiarse o limpiarse. Abrir agenda: solo `cases.manage`.
- **Casos del día** — con `scheduled_at` de hoy **o** agenda con fecha de hoy; acceso directo al espacio de trabajo.

### Vinculo usuario-especialista

En **Operativa → Actores**, al editar un especialista o profesional vinculable, elegir **Usuario vinculado**. Sin este vínculo, el usuario con rol `specialist` no verá casos en su agenda.

## Usuarios demo

| Email | Rol | Password |
|---|---|---|
| owner@surc.test | Plataforma | password |
| admin@red-veterinaria.test | Admin red vet | password |
| admin@red-peluqueria.test | Admin red peluquería | password |
| admin@clinica-norte.test | Admin clínica | password |
| operador@clinica-norte.test | Operador | password |
| especialista@clinica-norte.test | Especialista (agenda demo) | password |
