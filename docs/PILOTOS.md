# Pilotos de salida

Cada fase se valida con un piloto antes de ampliar el uso. No mezclar alcance.

## Piloto 1 — Operativa endurecida

- Una sede, datos reales controlados.
- Criterios: login, alta de actor/sujeto/caso/agenda, espacio de trabajo, seña, cierre, informe PDF, backup/restauración, usuarios sin privilegios extra.
- Gate: suite PHPUnit verde y checklist de hosting (MANUAL_USO Parte F).

## Piloto 2 — Constancia y sitio público

- Una red.
- Criterios: constancia con orden e indicaciones, reimpresión auditada, adjunto firmado, home con perfil institucional de la red, listados `/sedes` `/especialistas` `/blog` `/ayuda` (vacíos visibles), aislamiento entre redes, WhatsApp de portada solo si la **red** lo cargó (nunca fallback a una clínica).

## Piloto 3 — Historial opt-in

- Una clínica voluntaria; el resto sigue con agenda y datos mínimos.
- Criterios: flags red+sede; ficha del sujeto por sede; admin de red configura sin ver contenido; admin de clínica/operador operan el cuaderno; operador ve y carga historial sin editar datos base; campos del tipo y adjuntos privados; borrador → final → adenda (adenda no es fila principal); PDF de registro final y ficha completa auditados; compartir solo finales con destino visible (sin adjuntos ni cuaderno); incorporar una vez desde caso cerrado; otra sede/especialista/dueño de plataforma/admin de red sin el cuaderno; un usuario de sede no puede dar de alta sujetos ni casos en otra clínica.
- Agenda abierta a la red: la clínica de origen deriva a la anfitriona; la anfitriona opera workspace/constancia/seña y recibe 403 en el PDF de ficha ajena; sin la casilla, el caso ajeno sigue invisible.

En CIVETDUR (agosto 2026) el control de historial se hizo en Animalia con un animal ficticio (borrador → final → adenda → PDF → compartir) y 403/404 desde otra clínica; esa ficha de ensayo se borró. Agenda abierta a la red: Dumbo derivó a una visita de Animalia; la anfitriona operó el workspace sin leer el cuaderno. Las otras clínicas pueden habilitar el módulo y hacer un smoke sin datos clínicos reales. No reaplicar la plantilla de industria.

## Piloto 4 — Capacitación interna

- Criterios: usuario autenticado ve **Ayuda → Capacitación** filtrada por rol; una cuenta de sede lee los nombres de su pack (p. ej. Animal / Historia clínica) y los tipos activos; un operador no ve guías exclusivas de plataforma; un rol no destinatario recibe 403 al PDF; `/red/{slug}/tutoriales` redirige al centro interno; las guías **no** aparecen en el sitio público.

En cada piloto registrar errores, tiempos de alta, reimpresiones, uso de adjuntos, incidentes de permisos y feedback. Promover a producción solo con esos criterios cumplidos.
