<?php
// Script de mantenimiento de un solo uso: detecta y elimina líneas que han
// quedado huérfanas tras limpiar bloques <!-- ════ ... ════ -->.
// Uso: php scratch/limpiar_huerfanas.php [--apply]
//      sin --apply imprime un dry-run.

$archivos = [
    'admin/index.php', 'admin/usuarios.php', 'admin/visitas.php', 'admin/votaciones.php',
    'admin/votacion_resultados.php', 'index.php', 'tienda.php', 'calculadora.php',
    'votaciones.php', 'panel_socio.php', 'mis_entregas.php', 'exito.php',
    'includes/footer.php',
];
$base = __DIR__ . '/../';
$apply = in_array('--apply', $argv ?? [], true);


// Set de líneas que están EXCLUSIVAMENTE en HTML inline (no PHP).
// token_get_all distingue T_INLINE_HTML de los tokens PHP, así que las strings
// SQL en multilínea se quedan correctamente clasificadas como PHP.
function lineasSoloHtml(string $file): array {
    $tokens = token_get_all(file_get_contents($file));
    $php = [];
    $html = [];
    foreach ($tokens as $t) {
        if (!is_array($t)) continue;
        $type  = $t[0];
        $text  = $t[1];
        $line  = $t[2];
        $cnt   = substr_count($text, "\n");
        for ($i = 0; $i <= $cnt; $i++) {
            $ln = $line + $i;
            if ($type === T_INLINE_HTML) $html[$ln] = true;
            else                          $php[$ln]  = true;
        }
    }
    $solo = [];
    foreach ($html as $ln => $_) {
        if (!isset($php[$ln])) $solo[$ln] = true;
    }
    return $solo;
}


// Lista blanca de palabras que solo aparecían como TÍTULOS de bloques `<!-- ═══ -->`.
// Cualquier línea HTML inline que arranque con una de estas palabras (en mayúsculas
// y sin tags ni código) se considera huérfana de comentario y se elimina.
$palabras = [
    'SKIP LINK', 'NAVBAR', 'HERO', 'CONTENIDO PRINCIPAL', 'SECCIÓN', 'SECCION',
    'TARJETAS', 'FILTROS', 'ACCESOS', 'SEPARADOR', 'DETALLE', 'MODAL', 'VISTA',
    'FORMULARIO', 'FOOTER', 'CABECERA', 'COL ', 'HISTORIAL', 'SCRIPTS', 'SCRIPT',
    'TABLA', 'BANNER', 'ANALÍTICA', 'CONFIGURACIÓN', 'MARGEN', 'CTA ', 'MAPA',
    'TOAST', 'PWA', 'MENSAJES',
];

function esTituloBloque(string $linea, array $palabras): bool {
    $t = trim($linea);
    if ($t === '') return false;
    if (!preg_match('/^[ \t]+/', $linea)) return false;
    // Si contiene cualquier carácter de código → no es huérfana
    if (strpbrk($t, '<>=;{}[]"\\')   !== false) return false;
    // Comentario válido o JS minúsculo no es título huérfano
    if ($t[0] === '/' || $t[0] === '#' || $t[0] === '*') return false;
    if (ctype_lower($t[0])) return false;
    foreach ($palabras as $p) {
        if (stripos($t, $p) === 0) return true;
    }
    return false;
}


$totalBorrar = 0;
foreach ($archivos as $rel) {
    $f = $base . $rel;
    if (!is_file($f)) continue;

    $solo  = lineasSoloHtml($f);
    $lines = file($f);
    $aBorrar = [];
    $out = [];
    foreach ($lines as $i => $line) {
        $ln = $i + 1;
        if (isset($solo[$ln]) && esTituloBloque($line, $palabras)) {
            $aBorrar[] = $ln . ': ' . trim($line);
            continue;
        }
        $out[] = $line;
    }
    if ($aBorrar) {
        printf("\n--- %s — %d huérfanas ---\n", $rel, count($aBorrar));
        foreach (array_slice($aBorrar, 0, 8) as $l) echo '  ' . $l . PHP_EOL;
        if (count($aBorrar) > 8) echo '  ... +' . (count($aBorrar) - 8) . PHP_EOL;
        $totalBorrar += count($aBorrar);
        if ($apply) {
            file_put_contents($f, implode('', $out));
        }
    }
}
echo "\nTotal: $totalBorrar líneas " . ($apply ? 'BORRADAS' : 'a borrar (dry-run)') . PHP_EOL;
