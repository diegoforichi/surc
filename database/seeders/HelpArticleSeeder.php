<?php

namespace Database\Seeders;

use App\Models\HelpArticle;
use Illuminate\Database\Seeder;

class HelpArticleSeeder extends Seeder
{
    public function run(): void
    {
        $articles = [
            [
                'slug' => 'primeros-pasos',
                'title' => 'Cómo entrar y qué ve cada rol',
                'category' => HelpArticle::CATEGORY_START,
                'audience_roles' => [],
                'sort_order' => 10,
                'excerpt' => 'Todos entran por /admin. El menú cambia según el usuario, no según la URL.',
                'body' => '<p>SURC usa una sola URL de panel: <strong>/admin</strong>. Lo que cambia es el usuario.</p><p>En el <strong>perfil</strong> del panel puede cambiar su contraseña. Hágalo antes de cargar datos reales.</p><ul><li><strong>Dueño de plataforma:</strong> crea redes y publica estas guías.</li><li><strong>Admin de red:</strong> configura la red, el sitio público y las {{organization_plural}}.</li><li><strong>Admin de sede:</strong> opera su {{organization}}: actores, {{subject_plural}}, agendas y {{case_plural}}.</li><li><strong>Operador:</strong> recibe, confirma señas, avanza el {{case}} y carga el {{history}} si el módulo está activo.</li><li><strong>Especialista:</strong> ve solo sus agendas, si su actor tiene <em>Usuario vinculado</em>.</li></ul><p>Estas guías usan los nombres de <strong>su</strong> red. Puede descargar el artículo en PDF para imprimirlo.</p>',
            ],
            [
                'slug' => 'guia-de-sede',
                'title' => 'Ingreso, sede y privacidad',
                'category' => HelpArticle::CATEGORY_START,
                'audience_roles' => ['organization_admin', 'operator'],
                'sort_order' => 12,
                'excerpt' => 'Su usuario queda en una sola sede. El cuaderno interno no se publica ni lo lee el admin de red.',
                'body' => '<p>Entre a <strong>/admin</strong> con el correo de su {{organization}}. En el perfil cambie la contraseña de arranque.</p><p>El campo sede viene fijo: no crea {{subject_plural}} ni actores de otra {{organization}}.</p><ul><li>El {{history}} es interno de <strong>esa</strong> sede. Otras {{organization_plural}} no lo ven.</li><li>Si otra sede marca una agenda <em>abierta a la red</em>, puede sumar {{case_plural}} de <strong>sus</strong> {{subject_plural}} a esa visita. El {{subject}} y el {{history}} siguen siendo de su sede.</li><li>El admin de red habilita el módulo y los tipos; <strong>no lee</strong> el contenido.</li><li>El sitio público es para clientes: no muestra este cuaderno ni estas guías.</li></ul>',
            ],
            [
                'slug' => 'actor-y-usuario',
                'title' => 'Actor y usuario: no es lo mismo',
                'category' => HelpArticle::CATEGORY_ADMIN,
                'audience_roles' => ['platform_owner', 'network_admin', 'organization_admin'],
                'sort_order' => 20,
                'excerpt' => 'El nombre público va en el actor. El login va en el usuario, y se vinculan si esa persona entra al panel.',
                'body' => '<p>Puede crear tantos actores como necesite ({{client}}, especialistas u otros tipos de la red). El nombre que sale en agenda, constancia y sitio público es el del <strong>actor</strong>.</p><p>El <strong>usuario vinculado</strong> es opcional:</p><ul><li>Sin usuario: figura en agendas y directorio, pero no entra al sistema.</li><li>Con usuario y rol especialista: entra y ve solo sus visitas.</li></ul><p>El {{client}} casi nunca lleva usuario.</p>',
            ],
            [
                'slug' => 'alta-de-sujetos',
                'title' => 'Alta de {{client}} y {{subject}}',
                'category' => HelpArticle::CATEGORY_OPERATION,
                'audience_roles' => ['organization_admin', 'operator'],
                'sort_order' => 22,
                'excerpt' => 'El admin de sede crea la ficha. Puede dar de alta el {{client}} en el mismo formulario. El operador abre la ficha en solo lectura para el cuaderno.',
                'body' => '<ol><li>En <strong>Operativa → {{subject_plural}}</strong> cree el {{subject}}: nombre, sede (ya viene fija) y datos adicionales si la red los pide.</li><li>El {{client}} se elige o se crea ahí mismo (botón +): nombre, documento, teléfono. Si el documento ya existe en esa {{organization}}, se reutiliza; no duplica. No hace falta ir antes a Actores.</li><li>El código puede quedar vacío: el sistema lo genera.</li><li>En la ficha del {{client}} (Actores) ve todos sus {{subject_plural}}. Desde un {{subject}}, el nombre del {{client}} abre esa ficha; también lista los otros {{subject_plural}} del mismo {{client}}.</li></ol><p>El <strong>operador</strong> abre la ficha para cargar el {{history}}; no edita nombre, código ni {{client}}.</p>',
            ],
            [
                'slug' => 'senas-y-pagos',
                'title' => 'Registrar y confirmar una seña',
                'category' => HelpArticle::CATEGORY_OPERATION,
                'audience_roles' => ['network_admin', 'organization_admin', 'operator'],
                'sort_order' => 30,
                'excerpt' => 'Registrar deja la seña pendiente. Confirmar es el paso que habilita avanzar de etapa.',
                'body' => '<p>En el espacio de trabajo de un {{case}}:</p><ol><li>Indique monto y método y pulse <strong>Registrar</strong>. Queda <em>pending</em>.</li><li>Pulse <strong>Confirmar</strong> en esa seña. Recién ahí cuenta para el flujo.</li><li>Avance de etapa.</li></ol><p>El operador de sede y los administradores pueden confirmar. El especialista no confirma pagos.</p>',
            ],
            [
                'slug' => 'constancia-80mm',
                'title' => 'Constancia de inscripción',
                'category' => HelpArticle::CATEGORY_OPERATION,
                'audience_roles' => ['network_admin', 'organization_admin', 'operator'],
                'sort_order' => 40,
                'excerpt' => 'Se imprime cuando el {{case}} está en una agenda. El texto sale de la plantilla o de la agenda.',
                'body' => '<p>El botón <strong>Constancia</strong> aparece en {{case_plural}} con agenda. Incluye orden, indicaciones, consentimiento y espacio de firma.</p><p>El texto se edita en <strong>Plantillas de flujo</strong> (texto por defecto) o en la <strong>agenda</strong> (solo esa visita). Después se adjunta el papel firmado en el espacio de trabajo.</p>',
            ],
            [
                'slug' => 'espacio-de-trabajo',
                'title' => 'Agenda, {{case}} y espacio de trabajo',
                'category' => HelpArticle::CATEGORY_OPERATION,
                'audience_roles' => ['network_admin', 'organization_admin', 'operator', 'specialist'],
                'sort_order' => 50,
                'excerpt' => 'El admin de sede crea agenda y {{case}}. Operador y especialista avanzan etapas; no hacen el alta.',
                'body' => '<p>El admin de la {{organization}} anfitriona crea la <strong>agenda</strong> del día (dónde se atiende). Si marca <em>Abierta a la red</em>, otras {{organization_plural}} pueden sumar {{case_plural}} de <strong>sus</strong> {{subject_plural}} a esa visita. El {{case}} y el {{history}} siguen en la sede de origen; la anfitriona opera la atención y no lee el cuaderno ajeno.</p><p>El {{case}} se asocia al {{subject}} y, si aplica, a una agenda. La plantilla de flujo se elige al crear y queda fija.</p><p>En el <strong>espacio de trabajo</strong> complete el checklist de la etapa en curso y avance. Solo la etapa actual se edita. Adjuntos y constancia firmada se pueden cargar mientras el {{case}} esté abierto.</p><p>Al finalizar la etapa terminal el {{case}} queda cerrado y pasa a solo lectura.</p><p>El operador y el especialista ven los listados filtrados; no crean ni editan agendas ni {{case_plural}}.</p>',
            ],
            [
                'slug' => 'historial-longitudinal',
                'title' => '{{history}} de la sede',
                'category' => HelpArticle::CATEGORY_OPERATION,
                'audience_roles' => ['network_admin', 'organization_admin', 'operator'],
                'sort_order' => 55,
                'excerpt' => 'Cuaderno interno de la sede. El admin de red configura; la sede opera, imprime y comparte si elige.',
                'body' => '<p>No es una agenda interna. Cada {{organization}} lleva el {{history}} de <strong>sus</strong> {{subject_plural}}. La pestaña de {{case_plural}} asociados lista turnos; la pestaña <strong>{{history}}</strong> es el cuaderno.</p><p>Tipos de {{history_entry}} en esta red: <strong>{{history_types}}</strong>.</p><ol><li>El admin de red activa el módulo en Preferencias operativas, la sede y los tipos. Esa cuenta <strong>no lee</strong> el contenido.</li><li>Con la cuenta de sede, el admin crea el {{subject}}. El operador abre la ficha (no edita nombre ni {{client}}) y carga un {{history_entry}}: tipo, fecha, resumen, campos del tipo, adjuntos privados si hace falta.</li><li><strong>Finalice</strong>. Lo final no se edita: use <strong>Adenda</strong> (queda en el detalle, no como fila nueva).</li><li><strong>Descargar PDF</strong> de un {{history_entry}} final o <strong>Descargar ficha PDF</strong> de todos los finales de la sede. No incluye borradores ni archivos; sí lista nombres de adjuntos. Queda auditado.</li><li><strong>Compartir con {{case}}</strong> solo si quiere que esa atención vea <em>ese</em> {{history_entry}} (tipo, fecha y resumen), no el cuaderno ni los adjuntos.</li><li>Otras {{organization_plural}} no ven estos datos. El especialista, si no se compartió, tampoco.</li></ol>',
            ],
            [
                'slug' => 'guia-especialista',
                'title' => 'Guía del especialista',
                'category' => HelpArticle::CATEGORY_SPECIALIST,
                'audience_roles' => ['specialist', 'network_admin', 'organization_admin'],
                'sort_order' => 60,
                'excerpt' => 'Ve solo sus agendas. No confirma señas ni administra el sitio.',
                'body' => '<p>Para que un especialista vea {{case_plural}}, el actor correspondiente debe tener <strong>Usuario vinculado</strong>.</p><p>Puede operar la etapa de atención, cargar ficha y ver el <strong>{{history}} compartido</strong> con ese {{case}}. No ve el {{history}} completo del {{subject}} y no incorpora resultados.</p>',
            ],
            [
                'slug' => 'roles-de-operacion',
                'title' => 'Quién hace qué en la sede',
                'category' => HelpArticle::CATEGORY_OPERATION,
                'audience_roles' => ['network_admin', 'organization_admin', 'operator', 'specialist'],
                'sort_order' => 65,
                'excerpt' => 'Admin de sede da de alta. Operador opera el mostrador y el cuaderno. El especialista atiende lo suyo. El admin de red no lee el {{history}}.',
                'body' => '<ul><li><strong>Admin de sede:</strong> usuarios de su {{organization}}, actores, {{subject_plural}}, agendas y {{case_plural}}. Opera e imprime el {{history}}.</li><li><strong>Operador:</strong> listados, espacio de trabajo, señas y {{history}}. Abre la ficha del {{subject}} en solo lectura.</li><li><strong>Especialista:</strong> sus agendas y {{case_plural}}. No confirma pagos ni ve el cuaderno salvo lo compartido.</li><li><strong>Admin de red:</strong> configura tipos, flags y el sitio público. No lee, edita, comparte ni imprime el {{history}}.</li></ul>',
            ],
            [
                'slug' => 'sitio-publico',
                'title' => 'Sitio institucional de la red',
                'category' => HelpArticle::CATEGORY_ADMIN,
                'audience_roles' => ['platform_owner', 'network_admin'],
                'sort_order' => 70,
                'excerpt' => 'El sitio /red/{slug} es para clientes. Blog, {{organization_plural}} y especialistas se publican desde Sitio público.',
                'body' => '<p>Administre el perfil en <strong>Sitio público → Datos institucionales</strong> (logo del encabezado y foto de portada son archivos distintos), más carrusel, blog y páginas. Las fotos de {{organization}} y especialista se cargan en cada ficha. El WhatsApp de la portada es solo el de la red.</p><p>Los tutoriales internos no van en el sitio público: viven en <strong>Ayuda → Capacitación</strong> y se pueden descargar en PDF.</p>',
            ],
            [
                'slug' => 'dueno-plataforma',
                'title' => 'Tareas del dueño de plataforma',
                'category' => HelpArticle::CATEGORY_ADMIN,
                'audience_roles' => ['platform_owner'],
                'sort_order' => 90,
                'excerpt' => 'Crea redes y publica los manuales globales. No lee el contenido del {{history}}.',
                'body' => '<p>Desde <strong>Plataforma</strong> crea redes y, en <strong>Capacitación</strong>, los artículos y videos que ven los usuarios autenticados. Use tokens de terminología ({{subject}}, {{history}}, {{history_types}}) para que cada red lea sus propios nombres. El {{history}} de cada sede no es visible para este perfil.</p>',
            ],
            [
                'slug' => 'videos-de-uso',
                'title' => 'Videos de uso',
                'category' => HelpArticle::CATEGORY_VIDEOS,
                'audience_roles' => [],
                'sort_order' => 100,
                'excerpt' => 'Los videos se publican con URL de YouTube no listado o Vimeo, nunca como archivo en el servidor.',
                'body' => '<p>Cuando el dueño de plataforma cargue una URL válida en un artículo, el reproductor aparece aquí. Hasta entonces, use las guías de texto de su rol. Puede descargar cada guía en PDF.</p>',
            ],
        ];

        foreach ($articles as $article) {
            HelpArticle::query()->updateOrCreate(
                ['slug' => $article['slug']],
                [
                    ...$article,
                    'is_published' => true,
                    'published_at' => now(),
                ],
            );
        }
    }
}
