<nav class="admin-navbar" role="navigation" aria-label="Navegación del panel de operario">
        <div class="container">
            <div class="navbar-inner">
                <a href="operario.php" class="brand">
                    <span aria-hidden="true">🫒</span>
                    San Juan <span class="brand-accent">Bautista</span>
                    <span class="admin-badge admin-badge-operario">
                        <i class="bi bi-person-workspace" aria-hidden="true"></i> Operario
                    </span>
                </a>

                <div class="nav-actions">
                    <span class="nav-user">
                        Hola, <strong><?= htmlspecialchars($_SESSION['nombre']) ?></strong>
                    </span>
                    <a href="index.php" class="btn-logout" title="Ir a la web pública">
                        <i class="bi bi-house" aria-hidden="true"></i> Web
                    </a>
                    <a href="auth/logout.php" class="btn-logout" id="btn-cerrar-sesion" title="Cerrar sesión">
                        <i class="bi bi-box-arrow-right" aria-hidden="true"></i> Salir
                    </a>
                </div>
            </div>
        </div>
    </nav>
