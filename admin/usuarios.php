<?php
/**
 * ============================================================================
 * COOPERATIVA SAN JUAN BAUTISTA — Gestión de Usuarios (admin_usuarios.php)
 * ============================================================================
 * TFG — Desarrollo de Aplicaciones Web
 *
 * Vista exclusiva del administrador. Lista todos los usuarios del sistema y
 * permite modificar su rol (cliente | socio | admin) mediante un flujo de
 * confirmación explícita.
 *
 * Por qué un flujo de confirmación y no un guardado inmediato:
 *   Cambiar un rol NO es una acción cosmética. Promover a "socio" abre el
 *   acceso a la intranet privada (fincas, entregas, calculadora agrícola);
 *   promover a "admin" otorga control total sobre la base de datos. Un clic
 *   accidental sobre un <select> no puede tener consecuencias irreversibles.
 *   Por eso el flujo exige:
 *     1) Modal explicativo del impacto en lenguaje natural.
 *     2) Token CSRF validado en servidor (evita forjado de peticiones).
 *     3) Re-verificación de rol 'admin' en la API (nunca confiar en JS).
 *     4) Auto-protección: un admin no puede cambiarse el rol a sí mismo.
 *
 * ============================================================================
 */

session_start();

// ─── SEGURIDAD: sólo administradores ──────────────────────────────────────
if (!isset($_SESSION['rol']) || $_SESSION['rol'] !== 'admin') {
    header('Location: ../index.php');
    exit;
}

require_once __DIR__ . '/../config/db.php';

// Token CSRF compartido con la API (se genera una vez por sesión)
if (empty($_SESSION['csrf_token'])) {
    $_SESSION['csrf_token'] = bin2hex(random_bytes(32));
}

$mensaje_error = '';

try {
    $pdo = getConexion();

    // Traemos también usuarios inactivos: un admin debe poder auditar y
    // reactivar cuentas, no sólo ver las "vivas".
    $stmt = $pdo->query('
        SELECT id, dni, nombre, apellidos, email, rol, activo, created_at
        FROM usuarios
        ORDER BY
            FIELD(rol, "admin", "operario", "socio", "cliente"),
            apellidos, nombre
    ');
    $usuarios = $stmt->fetchAll();

} catch (PDOException $e) {
    error_log('Error en admin_usuarios.php: ' . $e->getMessage());
    $usuarios = [];
    $mensaje_error = 'No se pudieron cargar los usuarios.';
}

$idAdminActual = (int) ($_SESSION['usuario_id'] ?? 0);

$esAdminPanel = true;
$relRoot      = '../';
$pageTitle    = 'Gestión de Usuarios | Panel Admin';
$adminCssVer  = @filemtime(__DIR__ . '/../assets/css/admin.css') ?: '1';
$extraHead    = '<link rel="stylesheet" href="../assets/css/admin.css?v=' . $adminCssVer . '">';
require_once '../includes/header.php';
?>

<?php require_once '../includes/admin_navbar.php'; ?>

<style>
    /* El <select> de rol cambia de color según la opción seleccionada (lo
       aplica el JS): cliente=gris, operario=naranja, socio=verde, admin=oscuro. */
    .select-rol               { font-weight: 600; transition: background-color .2s, color .2s; }
    .select-rol.rol-cliente   { background-color: #6c757d; color: #fff; border-color: #6c757d; } /* secondary */
    .select-rol.rol-operario  { background-color: #E67E22; color: #fff; border-color: #E67E22; } /* orange    */
    .select-rol.rol-socio     { background-color: #198754; color: #fff; border-color: #198754; } /* success   */
    .select-rol.rol-admin     { background-color: #212529; color: #fff; border-color: #212529; } /* dark      */
    .select-rol:disabled      { opacity: .75; cursor: not-allowed; }

    .tabla-usuarios td        { vertical-align: middle; }
    .badge-self               { font-size: .7rem; margin-left: .35rem; }
</style>

<main class="container" id="contenido-principal">

    <div class="page-header d-flex justify-content-between align-items-start flex-wrap gap-3">
        <div>
            <h1>
                <i class="bi bi-people-fill" style="color: var(--color-accent-dark);" aria-hidden="true"></i>
                Gestión de <em>Usuarios</em>
            </h1>
            <p>Administra los roles y el acceso de los usuarios de la cooperativa.</p>
        </div>
        <a href="index.php" class="btn btn-outline-secondary">
            <i class="bi bi-arrow-left" aria-hidden="true"></i> Volver al Panel
        </a>
    </div>

    <?php if ($mensaje_error): ?>
        <div class="alert alert-custom alert-danger alert-dismissible fade show" role="alert">
            <i class="bi bi-exclamation-triangle-fill" aria-hidden="true"></i>
            <?= htmlspecialchars($mensaje_error) ?>
            <button type="button" class="btn-close" data-bs-dismiss="alert" aria-label="Cerrar"></button>
        </div>
    <?php endif; ?>

    <div class="table-card">
        <div class="table-card-header">
            <div class="header-left">
                <i class="bi bi-table" aria-hidden="true"></i>
                <h2>Usuarios registrados</h2>
            </div>
            <span class="badge-count">
                <?= count($usuarios) ?> usuario<?= count($usuarios) !== 1 ? 's' : '' ?>
            </span>
        </div>

        <div class="table-responsive">
            <?php if (empty($usuarios)): ?>
                <div class="table-empty">
                    <i class="bi bi-inbox" aria-hidden="true"></i>
                    <p>No hay usuarios registrados.</p>
                </div>
            <?php else: ?>
                <table class="table table-hover table-admin tabla-usuarios mb-0">
                    <thead>
                        <tr>
                            <th scope="col">ID</th>
                            <th scope="col">DNI</th>
                            <th scope="col">Nombre</th>
                            <th scope="col">Email</th>
                            <th scope="col" style="min-width: 160px;">Rol</th>
                            <th scope="col" class="text-center">Acciones</th>
                        </tr>
                    </thead>
                    <tbody>
                        <?php foreach ($usuarios as $u): ?>
                            <?php
                                $esYoMismo = ((int) $u['id'] === $idAdminActual);
                                // Datos requeridos para roles con peso fiscal (socio/admin):
                                // sin DNI o apellidos no se puede emitir un albarán o liquidación
                                // a nombre de esa persona. El badge visual lo deja claro.
                                $faltaDni  = empty($u['dni']);
                                $faltaApe  = empty($u['apellidos']);
                                $datosIncompletos = ($faltaDni || $faltaApe);
                            ?>
                            <tr>
                                <td class="text-muted">#<?= (int) $u['id'] ?></td>
                                <td>
                                    <?php if ($faltaDni): ?>
                                        <span class="badge bg-warning text-dark" title="Sin DNI registrado">
                                            <i class="bi bi-exclamation-triangle-fill"></i> sin DNI
                                        </span>
                                    <?php else: ?>
                                        <code><?= htmlspecialchars($u['dni']) ?></code>
                                    <?php endif; ?>
                                </td>
                                <td>
                                    <?php
                                        $nombreCompleto = trim(($u['apellidos'] ?? '') !== ''
                                            ? $u['apellidos'] . ', ' . $u['nombre']
                                            : $u['nombre']);
                                    ?>
                                    <strong><?= htmlspecialchars($nombreCompleto) ?></strong>
                                    <?php if ($faltaApe): ?>
                                        <span class="badge bg-warning text-dark badge-self" title="Sin apellidos">
                                            <i class="bi bi-exclamation-triangle-fill"></i> sin apellidos
                                        </span>
                                    <?php endif; ?>
                                    <?php if ($esYoMismo): ?>
                                        <span class="badge bg-info text-dark badge-self">
                                            <i class="bi bi-person-check" aria-hidden="true"></i> tú
                                        </span>
                                    <?php endif; ?>
                                    <?php if (!$u['activo']): ?>
                                        <span class="badge bg-secondary badge-self">inactivo</span>
                                    <?php endif; ?>
                                </td>
                                <td class="text-muted"><?= htmlspecialchars($u['email']) ?></td>

                                <!-- ── Selector de rol con color dinámico ── -->
                                <td>
                                    <select class="form-select form-select-sm select-rol rol-<?= $u['rol'] ?>"
                                            data-id-usuario="<?= (int) $u['id'] ?>"
                                            data-rol-original="<?= htmlspecialchars($u['rol']) ?>"
                                            data-nombre="<?= htmlspecialchars(trim(($u['nombre'] ?? '') . ' ' . ($u['apellidos'] ?? ''))) ?>"
                                            data-email="<?= htmlspecialchars($u['email']) ?>"
                                            data-dni="<?= htmlspecialchars($u['dni'] ?? '') ?>"
                                            data-apellidos="<?= htmlspecialchars($u['apellidos'] ?? '') ?>"
                                            data-nombre-pila="<?= htmlspecialchars($u['nombre']) ?>"
                                            data-incompleto="<?= $datosIncompletos ? '1' : '0' ?>"
                                            <?= $esYoMismo ? 'disabled title="No puedes modificar tu propio rol"' : '' ?>>
                                        <option value="cliente"  <?= $u['rol'] === 'cliente'  ? 'selected' : '' ?>>Cliente</option>
                                        <option value="operario" <?= $u['rol'] === 'operario' ? 'selected' : '' ?>>Operario</option>
                                        <option value="socio"    <?= $u['rol'] === 'socio'    ? 'selected' : '' ?>>Socio</option>
                                        <option value="admin"    <?= $u['rol'] === 'admin'    ? 'selected' : '' ?>>Admin</option>
                                    </select>
                                </td>

                                <!-- ── Acción contextual (depende del rol) ── -->
                                <td class="text-center">
                                    <?php if ($u['rol'] === 'socio'): ?>
                                        <a href="index.php?filtro_socio=<?= (int) $u['id'] ?>"
                                           class="btn btn-sm btn-outline-success">
                                            <i class="bi bi-truck" aria-hidden="true"></i> Ver Entregas
                                        </a>
                                    <?php elseif ($u['rol'] === 'cliente'): ?>
                                        <a href="index.php?filtro_cliente=<?= (int) $u['id'] ?>"
                                           class="btn btn-sm btn-outline-primary">
                                            <i class="bi bi-bag-check" aria-hidden="true"></i> Ver Pedidos
                                        </a>
                                    <?php elseif ($u['rol'] === 'operario'): ?>
                                        <span class="text-muted small">
                                            <i class="bi bi-person-workspace" aria-hidden="true" style="color:#E67E22;"></i> Empleado de almazara
                                        </span>
                                    <?php else: ?>
                                        <span class="text-muted small">
                                            <i class="bi bi-shield-lock" aria-hidden="true"></i> Administrador
                                        </span>
                                    <?php endif; ?>
                                </td>
                            </tr>
                        <?php endforeach; ?>
                    </tbody>
                </table>
            <?php endif; ?>
        </div>
    </div>

    <div style="height: 3rem;"></div>
</main>

<!-- ─── SweetAlert2 (modales elegantes con promesas) ─── -->
<script src="https://cdn.jsdelivr.net/npm/sweetalert2@11"></script>
<script src="https://cdn.jsdelivr.net/npm/bootstrap@5.3.3/dist/js/bootstrap.bundle.min.js"
        integrity="sha384-YvpcrYf0tY3lHB60NNkmXc5s9fDVZLESaAA55NDzOxhy9GkcIdslK1eN7N6jIeHz"
        crossorigin="anonymous"></script>

<script>
/**
 * ============================================================================
 * Gestor de cambio de rol — flujo de confirmación explícita.
 * ============================================================================
 * El cambio del <select> NO guarda directamente. Flujo real:
 *   1) El admin cambia el <select>.
 *   2) Se abre un modal con el impacto concreto de esa transición.
 *   3) Si acepta → fetch POST a la API, que re-valida todo en servidor.
 *   4) Si cancela → el <select> se revierte al valor anterior.
 * ============================================================================
 */
(function () {
    const CSRF_TOKEN = <?= json_encode($_SESSION['csrf_token']) ?>;

    // Matriz de mensajes: explica EL IMPACTO, no sólo "¿estás seguro?".
    // La clave es "origen->destino"; así el texto es fiel a la transición real.
    const IMPACTOS = {
        'cliente->socio': {
            titulo: '¿Convertir en Socio de la Cooperativa?',
            html:   'Al promover a <b>Socio</b>, este usuario obtendrá acceso al '
                  + '<b>panel agrícola privado</b>: podrá registrar fincas, consultar '
                  + 'sus entregas de aceituna y usar la calculadora agrícola.',
            icono:  'question',
            color:  '#198754'
        },
        'cliente->operario': {
            titulo: '¿Dar de alta como Operario de la almazara?',
            html:   'El usuario podrá acceder a la <b>mesa de entregas</b> para registrar '
                  + 'aportes de aceituna de los socios. <b>No</b> tendrá acceso al panel '
                  + 'de admin ni al cambio de roles.',
            icono:  'question',
            color:  '#E67E22'
        },
        'cliente->admin': {
            titulo: '¿Promover a Administrador?',
            html:   '<b>ATENCIÓN:</b> concederás <b>control total</b> sobre la base de '
                  + 'datos. Podrá registrar entregas, modificar roles de otros usuarios '
                  + 'y acceder a todas las estadísticas de la cooperativa.',
            icono:  'warning',
            color:  '#dc3545'
        },
        'operario->cliente': {
            titulo: '¿Dar de baja al Operario?',
            html:   'El usuario <b>perderá el acceso</b> a la mesa de entregas. '
                  + 'Conservará su histórico de pedidos en tienda como cliente.',
            icono:  'warning',
            color:  '#fd7e14'
        },
        'operario->socio': {
            titulo: '¿Convertir Operario en Socio?',
            html:   'Pasará de <b>empleado</b> a <b>socio cooperativista</b>: dejará '
                  + 'la mesa de entregas y obtendrá acceso al panel agrícola privado.',
            icono:  'question',
            color:  '#198754'
        },
        'operario->admin': {
            titulo: '¿Promover Operario a Administrador?',
            html:   '<b>ATENCIÓN:</b> concederás <b>control total</b> del sistema. '
                  + 'Además de la mesa de entregas, podrá gestionar usuarios, '
                  + 'votaciones, visitas y stock.',
            icono:  'warning',
            color:  '#dc3545'
        },
        'socio->cliente': {
            titulo: '¿Revocar la condición de Socio?',
            html:   'Al degradar a <b>Cliente</b>, este usuario <b>perderá el acceso</b> '
                  + 'a la intranet privada: no podrá consultar sus fincas, entregas ni '
                  + 'usar la calculadora. Conservará su histórico de pedidos en tienda.',
            icono:  'warning',
            color:  '#fd7e14'
        },
        'socio->operario': {
            titulo: '¿Convertir Socio en Operario?',
            html:   'Dejará de ser cooperativista para pasar a personal asalariado de la '
                  + 'almazara. <b>Perderá</b> el acceso a su panel agrícola privado.',
            icono:  'warning',
            color:  '#fd7e14'
        },
        'socio->admin': {
            titulo: '¿Promover Socio a Administrador?',
            html:   '<b>ATENCIÓN:</b> concederás <b>control total</b> sobre la base de '
                  + 'datos. Además de sus privilegios de socio, podrá modificar cualquier '
                  + 'registro del sistema.',
            icono:  'warning',
            color:  '#dc3545'
        },
        'admin->operario': {
            titulo: '¿Degradar Administrador a Operario?',
            html:   'Este usuario <b>perderá los privilegios de admin</b> y conservará '
                  + 'sólo el acceso a la mesa de entregas. Asegúrate de que queda otro admin activo.',
            icono:  'warning',
            color:  '#fd7e14'
        },
        'admin->socio': {
            titulo: '¿Revocar privilegios de Administrador?',
            html:   'Este usuario <b>dejará de poder administrar</b> el sistema. '
                  + 'Asegúrate de que queda al menos otro administrador activo.',
            icono:  'warning',
            color:  '#fd7e14'
        },
        'admin->cliente': {
            titulo: '¿Degradar de Administrador a Cliente?',
            html:   'Este usuario <b>perderá TODOS los privilegios</b> administrativos y '
                  + 'de socio en una sola acción. Asegúrate de que queda otro admin activo.',
            icono:  'warning',
            color:  '#dc3545'
        }
    };

    // Refleja el color del select en el momento en que se elige una opción.
    function pintarSelect(sel) {
        sel.classList.remove('rol-cliente', 'rol-operario', 'rol-socio', 'rol-admin');
        sel.classList.add('rol-' + sel.value);
    }

    // Roles que requieren ficha completa (DNI + apellidos):
    //   socio → albaranes y liquidaciones a su nombre
    //   admin/operario → personal con contrato laboral en la cooperativa
    const ROLES_CON_FICHA = ['socio', 'admin', 'operario'];

    /**
     * Modal-formulario que se abre cuando el admin intenta promocionar a un
     * usuario con datos incompletos. Sustituye al modal de confirmación.
     * Llama a api/promocionar_socio.php (datos + rol en transacción).
     */
    async function abrirFormCompletarDatos(sel, nuevo) {
        const idUser    = sel.dataset.idUsuario;
        const nombrePila = sel.dataset.nombrePila || sel.dataset.nombre || '';
        const apellidos  = sel.dataset.apellidos  || '';
        const dni        = sel.dataset.dni        || '';

        const { value: form } = await Swal.fire({
            title: `Completa los datos para promover a ${nuevo}`,
            html: `
              <p style="text-align:left;margin-bottom:1rem;color:#555;font-size:.92rem;">
                <b>${nombrePila}</b> aún no tiene ficha completa. Para emitir albaranes y
                liquidaciones a su nombre, completa estos datos:
              </p>
              <input  id="swal-dni"  class="swal2-input" placeholder="DNI / NIE / CIF (ej: 12345678A)"
                      maxlength="9" value="${dni}" autocomplete="off">
              <input  id="swal-ape"  class="swal2-input" placeholder="Apellidos"
                      maxlength="100" value="${apellidos}">
              <input  id="swal-tel"  class="swal2-input" placeholder="Teléfono (opcional)"
                      maxlength="20" autocomplete="off">
            `,
            focusConfirm:       false,
            showCancelButton:   true,
            confirmButtonText:  '<i class="bi bi-check-lg"></i> Guardar y promover',
            cancelButtonText:   'Cancelar',
            confirmButtonColor: '#198754',
            cancelButtonColor:  '#6c757d',
            preConfirm: () => {
                const v = id => document.getElementById(id).value.trim().toUpperCase();
                const dni = v('swal-dni');
                const ape = document.getElementById('swal-ape').value.trim();
                const tel = document.getElementById('swal-tel').value.trim();
                // Mismo regex que la API (defensa en profundidad: la API es la fuente de verdad,
                // pero aquí evitamos un round-trip si el formato no es siquiera plausible).
                const reNif = /^\d{8}[A-HJ-NP-TV-Z]$/;
                const reNie = /^[XYZ]\d{7}[A-HJ-NP-TV-Z]$/;
                const reCif = /^[ABCDEFGHJKLMNPQRSUVW]\d{7}[0-9A-J]$/;
                if (!reNif.test(dni) && !reNie.test(dni) && !reCif.test(dni)) {
                    Swal.showValidationMessage('DNI/NIE/CIF con formato inválido (ej: 12345678A)');
                    return false;
                }
                if (ape.length < 2) {
                    Swal.showValidationMessage('Los apellidos son obligatorios');
                    return false;
                }
                return { dni, apellidos: ape, telefono: tel };
            }
        });

        if (!form) return false;

        // Llamada a la API que actualiza datos + rol en transacción
        const resp = await fetch('../api/promocionar_socio.php', {
            method:  'POST',
            headers: { 'Content-Type': 'application/json' },
            body:    JSON.stringify({
                id_usuario: idUser,
                nuevo_rol:  nuevo,
                ...form,
                csrf_token: CSRF_TOKEN
            })
        });
        const data = await resp.json();
        if (!resp.ok || data.error) {
            throw new Error(data.mensaje || `Error HTTP ${resp.status}`);
        }

        // Refrescamos los datos del select y la fila — los badges de ⚠ "sin DNI"
        // quedarían como zombies si no recargamos. Más simple: recarga completa.
        Swal.fire({
            icon:'success',
            title:'Datos completados y rol actualizado',
            text:'La página se va a recargar para reflejar los cambios.',
            timer: 1800,
            showConfirmButton: false,
        }).then(() => window.location.reload());

        return true;
    }

    document.querySelectorAll('.select-rol').forEach(sel => {
        sel.addEventListener('change', async (e) => {
            const anterior = sel.dataset.rolOriginal;
            const nuevo    = sel.value;
            const idUser   = sel.dataset.idUsuario;
            const nombre   = sel.dataset.nombre || 'este usuario';
            const incompleto = sel.dataset.incompleto === '1';

            // El propio cambio pinta el select inmediatamente (feedback visual).
            pintarSelect(sel);

            if (anterior === nuevo) return;

            // ─── CASO ESPECIAL: promoción a rol "fiscal" con ficha incompleta ───
            // Abrimos un form para completar DNI/apellidos al vuelo en lugar de
            // bloquear con un error. Es lo que haría un admin en una cooperativa.
            if (incompleto && ROLES_CON_FICHA.includes(nuevo)) {
                try {
                    const ok = await abrirFormCompletarDatos(sel, nuevo);
                    if (!ok) {
                        sel.value = anterior;
                        pintarSelect(sel);
                    }
                } catch (err) {
                    sel.value = anterior;
                    pintarSelect(sel);
                    Swal.fire({ icon:'error', title:'No se pudo completar la promoción',
                                text: err.message || 'Error del servidor.' });
                }
                return;
            }

            const impacto = IMPACTOS[`${anterior}->${nuevo}`] || {
                titulo: '¿Confirmar cambio de rol?',
                html:   `Vas a cambiar el rol de <b>${nombre}</b> de <b>${anterior}</b> a <b>${nuevo}</b>.`,
                icono:  'question',
                color:  '#0d6efd'
            };

            const res = await Swal.fire({
                title:              impacto.titulo,
                html:               `<p class="mb-2">Usuario: <b>${nombre}</b></p>${impacto.html}`,
                icon:               impacto.icono,
                showCancelButton:   true,
                confirmButtonText:  '<i class="bi bi-check-lg"></i> Sí, aplicar cambio',
                cancelButtonText:   'Cancelar',
                confirmButtonColor: impacto.color,
                cancelButtonColor:  '#6c757d',
                focusCancel:        true   // por defecto, cancelar (ante la duda, no cambiar)
            });

            if (!res.isConfirmed) {
                // Revertimos: el admin canceló o cerró el modal.
                sel.value = anterior;
                pintarSelect(sel);
                return;
            }

            // ─── Llamada a la API de actualización ───
            try {
                const resp = await fetch('../api/actualizar_rol.php', {
                    method:  'POST',
                    headers: { 'Content-Type': 'application/json' },
                    body:    JSON.stringify({
                        id_usuario: idUser,
                        nuevo_rol:  nuevo,
                        csrf_token: CSRF_TOKEN
                    })
                });
                const data = await resp.json();

                if (!resp.ok || data.error) {
                    // Si el servidor devolvió 422 con datos faltantes (caso borde
                    // si el HTML no se había refrescado), abrimos el form igualmente.
                    if (resp.status === 422 && data.datos_faltantes?.length) {
                        try {
                            await abrirFormCompletarDatos(sel, nuevo);
                            return;
                        } catch (e2) {
                            throw e2;
                        }
                    }
                    throw new Error(data.mensaje || `Error HTTP ${resp.status}`);
                }

                // Éxito: consolidamos el nuevo rol como "valor base" y recalculamos
                // el botón de acción sin recargar la página entera.
                sel.dataset.rolOriginal = nuevo;
                actualizarBotonAccion(sel, nuevo);

                Swal.fire({
                    icon:              'success',
                    title:             'Rol actualizado',
                    text:              data.mensaje || 'Cambio aplicado correctamente.',
                    timer:             2000,
                    showConfirmButton: false
                });

            } catch (err) {
                console.error(err);
                sel.value = anterior;
                pintarSelect(sel);
                Swal.fire({
                    icon:  'error',
                    title: 'No se pudo actualizar el rol',
                    text:  err.message || 'Error de red o del servidor.'
                });
            }
        });
    });

    // Redibuja el botón contextual de la columna "Acciones" tras un cambio de rol.
    function actualizarBotonAccion(sel, rol) {
        const celda = sel.closest('tr').querySelector('td:last-child');
        const id    = sel.dataset.idUsuario;
        if (rol === 'socio') {
            celda.innerHTML = `<a href="index.php?filtro_socio=${id}" class="btn btn-sm btn-outline-success">
                                  <i class="bi bi-truck"></i> Ver Entregas</a>`;
        } else if (rol === 'cliente') {
            celda.innerHTML = `<a href="index.php?filtro_cliente=${id}" class="btn btn-sm btn-outline-primary">
                                  <i class="bi bi-bag-check"></i> Ver Pedidos</a>`;
        } else if (rol === 'operario') {
            celda.innerHTML = `<span class="text-muted small">
                                  <i class="bi bi-person-workspace" style="color:#E67E22;"></i> Empleado de almazara</span>`;
        } else {
            celda.innerHTML = `<span class="text-muted small">
                                  <i class="bi bi-shield-lock"></i> Administrador</span>`;
        }
    }
})();
</script>

</body>
</html>
