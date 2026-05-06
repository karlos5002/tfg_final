-- Nuevo rol 'operario': empleado de la almazara que registra entregas de
-- aceituna sin acceder al panel completo de admin.
-- Idempotente: re-ejecutable sin romper la BD.

USE cooperativa_sjb;

-- 1) Ampliar el ENUM de rol con 'operario'.
--    El orden importa: dejamos 'operario' entre 'socio' y 'admin' a nivel
--    semántico (privilegios crecientes), pero MySQL acepta cualquier orden.
ALTER TABLE usuarios
    MODIFY COLUMN rol ENUM('admin','operario','socio','cliente')
        NOT NULL DEFAULT 'cliente'
        COMMENT 'admin|operario|socio|cliente — nivel de acceso';

-- 2) Reordenar el FIELD() de la vista del admin para que operarios salgan
--    justo después de admins (siguiendo el orden de privilegios).
--    No hay vista que tocar, pero esto queda documentado para admin_usuarios.php
--    cuando reordene su SELECT.

-- 3) Sembrar un operario de prueba — sólo si no existe ya.
INSERT INTO usuarios (dni, nombre, apellidos, email, password, telefono, rol)
SELECT '55555555E', 'Pepe', 'Romero Núñez', 'operario@almazara.es',
       '$2y$10$h80VSP8qS.kPMCwpUlbMreMMbz1oLvs79CbLoNAeaif3BKMzDij1K',
       '600999000', 'operario'
WHERE NOT EXISTS (
    SELECT 1 FROM usuarios WHERE email = 'operario@almazara.es'
);
