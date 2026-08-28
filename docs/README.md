# Documentación SURC

Índice para quien entra al proyecto. Los nombres de menú (Clínica, Derivación, Animal, etc.) cambian según la plantilla de industria; acá se usan nombres neutrales y ejemplos veterinarios.

Hay **tres capas** de documentación:

| Capa | Quién la lee | Dónde |
|------|----------------|------|
| Producto (instalar, permisos, hosting) | Quien configura o desarrolla | Este índice y los markdown de `docs/` |
| Operación diaria | Admin de sede, operador, especialista | Panel **Ayuda → Capacitación** (login; términos de *su* red; PDF descargable) |
| Vocabulario y tipos del rubro | El sistema | Pack JSON en `database/industry-packs/` (terminología, flujo, campos, tipos de historial) |

La guía operativa **no** se copia dentro del pack ni se publica en el sitio institucional. Un rubro nuevo (por ejemplo informática) se resuelve creando/exportando un pack; Capacitación reutiliza los mismos artículos con otros nombres.

## Por audiencia

| Documento | Para quién | Qué cubre |
|-----------|------------|-----------|
| [MANUAL_USO.md](MANUAL_USO.md) | Quien instala y opera el día a día | Instalación, roles, alta de red/sede/usuarios/actores/sujetos/casos/agendas, espacio de trabajo, dashboard, checklist de hosting |
| [MANUAL_CONFIGURACION.md](MANUAL_CONFIGURACION.md) | Admin de red / quien configura | Plantillas de industria, terminología, flujos, campos, preferencias, permisos |
| [PILOTOS.md](PILOTOS.md) | Quien sale a producción | Criterios de aceptación por fase |
| [FLUJO_OPERATIVO.html](FLUJO_OPERATIVO.html) / [FLUJO_OPERATIVO.pdf](FLUJO_OPERATIVO.pdf) | Presentación o onboarding operativo | Campos de cada formulario, etapas, qué pide cada una, estados de agenda |
| [`../presentacion.html`](../presentacion.html) | Demo en vivo | Guion corto, usuarios demo, sede/agenda |

## Por tipo de trabajo

| Necesito… | Ir a |
|-----------|------|
| Entender el sitio público y la capacitación | MANUAL_USO Paso 10 / 10b; MANUAL_CONFIGURACION sección 11 |
| Entender sede, agenda y hora estimada | MANUAL_USO Paso 8 / 8b y presentación sección «Sede y agenda» |
| Entender plantilla de flujo vs especialista | MANUAL_USO Paso 6.2 y 8; MANUAL_CONFIGURACION sección 5 |
| Operar un caso etapa por etapa | MANUAL_USO Paso 9 y FLUJO_OPERATIVO |
| Subir a hosting | MANUAL_USO Parte F y skill `.cursor/skills/surc-config` |
| Contexto para un agente de código | `AGENTS.md` en la raíz + Boost MCP |

## Estado operativo documentado (agosto 2026)

Comportamiento vigente en código, no el deseado de versiones anteriores:

- La **plantilla de flujo se elige al crear el caso** y **queda fija en edición**.
- Las **plantillas de industria** (packs JSON en `database/industry-packs/`) definen terminología, flujo, campos y defaults de agenda. Veterinaria es el molde; genérico arranca un rubro nuevo. En hosting: `surc:new-instance` (sin credenciales por defecto).
- Al guardar, el sistema **respeta la plantilla elegida** (o la sugerida por el especialista). Solo usa la default de la red si el campo quedó vacío.
- **Operador y especialista** ven listados de Agenda y Casos (`cases.operate`) y el espacio de trabajo. **No** crean ni editan esos registros (`cases.manage`).
- El widget **Agendas pendientes** muestra columna **Título**, encabezado simple, y filtro de fecha con default «desde hoy». **Abrir agenda** solo en agendas de la sede del usuario (o el admin de red).
- Una agenda puede marcarse **Abierta a la red**: otras sedes suman derivaciones; el caso y el sujeto quedan en la sede de origen; la anfitriona opera la visita y no lee el cuaderno ajeno. Elegir agenda **ya no pisa** la sede del caso.
- La **constancia 80 mm** se emite al inscribir un caso en una agenda (orden, indicaciones, consentimiento, firma en papel).
- El **sitio institucional** vive en `/red/{slug}` con home resumida (slogan, descripción y contacto de la **red**) y páginas `/sedes`, `/especialistas`, `/blog`, `/ayuda`. El WhatsApp de la portada es solo el de la red. No hay turnos online. El admin de red lo edita en **Sitio público → Datos institucionales**.
- La **capacitación interna** vive en el panel (**Ayuda → Capacitación**): es global, la publica el dueño de plataforma, se filtra por rol y **adapta los nombres a la red** (tokens de terminología). Cada artículo se puede descargar en PDF autenticado. No va al sitio público.
- El **historial longitudinal** es opt-in por red y por sede: ficha con timeline, campos por tipo, adjuntos privados, PDF auditado de un registro o de la ficha completa, y compartir con un caso solo si se elige. El admin de red configura el módulo y no lee el contenido. Un usuario con sede fija no puede crear registros en otra clínica. No forma parte del flujo mínimo.
- La **orden de venta** es interna (PDF + CSV) para facturar después en el ERP. Nace de un registro clínico final de esa clínica; SURC no factura.
- El **operador** confirma señas (`payments.confirm`); el especialista no.

Si el código y este índice divergen, prevalece el código y hay que actualizar estos archivos juntos (`presentacion.html` incluido: regla `.cursor/rules/docs-presentacion-sync.mdc`).
