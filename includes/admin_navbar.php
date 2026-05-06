<?php $relRoot = $relRoot ?? ''; ?>
<nav class="admin-navbar" role="navigation" aria-label="Navegación del panel de administración">
        <div class="container">
            <div class="navbar-inner">
                <!-- Marca: lleva al dashboard del propio admin -->
                <a href="index.php" class="brand">
                    <span aria-hidden="true">🫒</span>
                    San Juan <span class="brand-accent">Bautista</span>
                    <span class="admin-badge">
                        <i class="bi bi-shield-lock-fill" aria-hidden="true"></i> Admin
                    </span>
                </a>

                <!-- Acciones de usuario -->
                <div class="nav-actions">
                    <span class="nav-user">
                        Hola, <strong><?= htmlspecialchars($_SESSION['nombre']) ?></strong>
                    </span>
                    <a href="<?= $relRoot ?>index.php" class="btn-logout" title="Ir a la web pública">
                        <i class="bi bi-house" aria-hidden="true"></i> Web
                    </a>
                    <a href="<?= $relRoot ?>auth/logout.php" class="btn-logout" id="btn-cerrar-sesion" title="Cerrar sesión">
                        <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Salir
                    </a>
                </div>
            </div>
        </div>
    </nav>
