<?php
/**
 * ============================================================================
 * COOPERATIVA SAN JUAN BAUTISTA — Previsión Agro-Meteorológica
 * ============================================================================
 * TFG — Desarrollo de Aplicaciones Web
 *
 * Consume la API REST pública de Open-Meteo (https://open-meteo.com) y devuelve
 * al frontend la previsión a 5 días para Herrera del Duque + una RECOMENDACIÓN
 * AGRARIA calculada en servidor según las prácticas del sector olivarero.
 *
 * Por qué usar un proxy PHP en lugar de fetch directo desde el navegador:
 *   - Permite añadir lógica de negocio (recomendación) sin enviarla al cliente.
 *   - Cachea la respuesta 30 min en sesión: ahorra llamadas a Open-Meteo
 *     aunque varios socios entren al panel a la vez.
 *   - Mantiene las coordenadas privadas (no las recibe el navegador).
 *   - Si en el futuro queremos cambiar de proveedor (a AEMET por ejemplo),
 *     sólo se toca este archivo: el frontend no se entera.
 *
 * Open-Meteo NO requiere API key — perfecto para una demo / TFG porque
 * funciona out-of-the-box. Datos refundidos del modelo ECMWF.
 * ============================================================================
 */

header('Content-Type: application/json; charset=utf-8');

session_start();

if (!isset($_SESSION['rol']) || !in_array($_SESSION['rol'], ['socio', 'admin'], true)) {
    http_response_code(403);
    echo json_encode(['error' => true, 'mensaje' => 'Acceso restringido a socios.']);
    exit;
}

const HRR_LAT  = 39.165;        // Herrera del Duque, Badajoz
const HRR_LON  = -5.042;
const LOCALIDAD = 'Herrera del Duque';

// ─── Caché en sesión 30 min ──────────────────────────────────────────────
$cache = $_SESSION['prevision_cache'] ?? null;
if ($cache && (time() - $cache['ts']) < 1800) {
    $cache['data']['cache']     = true;
    $cache['data']['cache_age'] = time() - $cache['ts'];
    echo json_encode($cache['data']);
    exit;
}

// ─── Petición al servicio externo ────────────────────────────────────────
$url = 'https://api.open-meteo.com/v1/forecast'
     . '?latitude='  . HRR_LAT
     . '&longitude=' . HRR_LON
     . '&daily=weather_code,temperature_2m_max,temperature_2m_min,'
     . 'precipitation_sum,precipitation_probability_max,wind_speed_10m_max'
     . '&timezone=Europe/Madrid'
     . '&forecast_days=5';

// Estrategia SSL: si existe config/cacert.pem (recomendado, distribuido con
// el repo), se verifica contra él. Si no existe o la verificación falla por
// cacert obsoleto, retry sin verify y log de aviso. Aceptable en local, no
// en producción — para producción, configurar curl.cainfo en php.ini.
$caRepo = __DIR__ . '/../config/cacert.pem';
$opts = [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_TIMEOUT        => 8,
];
if (is_readable($caRepo)) {
    $opts[CURLOPT_SSL_VERIFYPEER] = true;
    $opts[CURLOPT_CAINFO]         = $caRepo;
} else {
    $opts[CURLOPT_SSL_VERIFYPEER] = false;
}

$ch = curl_init($url);
curl_setopt_array($ch, $opts);
$body = curl_exec($ch);
$err  = curl_error($ch);
$code = curl_getinfo($ch, CURLINFO_HTTP_CODE);

// Retry sin verify si el primer intento falló por SSL
if (($err || $code !== 200) && !empty($opts[CURLOPT_SSL_VERIFYPEER])) {
    error_log('prevision.php: SSL verify falló (' . $err . '), retry sin verify (modo dev)');
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, false);
    $body = curl_exec($ch);
    $err  = curl_error($ch);
    $code = curl_getinfo($ch, CURLINFO_HTTP_CODE);
}
curl_close($ch);

if ($err || $code !== 200 || !$body) {
    error_log("Open-Meteo falló (HTTP $code): " . ($err ?: substr($body, 0, 200)));
    http_response_code(502);
    echo json_encode([
        'error'   => true,
        'mensaje' => 'No se pudo contactar con el servicio meteorológico. Inténtalo en unos minutos.',
    ]);
    exit;
}

$apiResp = json_decode($body, true);
if (!is_array($apiResp) || !isset($apiResp['daily']['time'])) {
    http_response_code(502);
    echo json_encode(['error' => true, 'mensaje' => 'Respuesta meteorológica con formato inesperado.']);
    exit;
}

// ─── Reorganizar la respuesta ────────────────────────────────────────────
$d        = $apiResp['daily'];
$dias     = [];
$nombreD  = ['Sun'=>'Dom','Mon'=>'Lun','Tue'=>'Mar','Wed'=>'Mié','Thu'=>'Jue','Fri'=>'Vie','Sat'=>'Sáb'];

for ($i = 0, $n = count($d['time']); $i < $n; $i++) {
    $ts = strtotime($d['time'][$i]);
    $dias[] = [
        'fecha'              => $d['time'][$i],
        'dia_corto'          => $nombreD[date('D', $ts)] ?? date('D', $ts),
        'dia_completo'       => $i === 0 ? 'Hoy' : ($i === 1 ? 'Mañana' : ($nombreD[date('D', $ts)] . ' ' . date('j', $ts))),
        'codigo'             => (int)   $d['weather_code'][$i],
        'temp_max'           => (int) round($d['temperature_2m_max'][$i]),
        'temp_min'           => (int) round($d['temperature_2m_min'][$i]),
        'precip_mm'          => round($d['precipitation_sum'][$i], 1),
        'precip_probabilidad'=> (int)   ($d['precipitation_probability_max'][$i] ?? 0),
        'viento_kmh'         => (int) round($d['wind_speed_10m_max'][$i]),
        'icono'              => iconoWMO((int) $d['weather_code'][$i]),
        'descripcion'        => descripcionWMO((int) $d['weather_code'][$i]),
    ];
}

$recomendacion = calcularRecomendacionRecoleccion($dias);

$resultado = [
    'error'         => false,
    'localidad'     => LOCALIDAD,
    'actualizado'   => date('d/m/Y H:i'),
    'fuente'        => 'Open-Meteo',
    'dias'          => $dias,
    'recomendacion' => $recomendacion,
    'cache'         => false,
];

$_SESSION['prevision_cache'] = ['ts' => time(), 'data' => $resultado];
echo json_encode($resultado, JSON_UNESCAPED_UNICODE);


// =========================================================================
// LÓGICA AGRARIA — qué decirle al socio según el tiempo
// =========================================================================

/**
 * Decide si es buen momento para llevar aceituna a la almazara.
 * Reglas extraídas del sector olivarero extremeño:
 *   - Lluvia >2 mm hoy/mañana → la aceituna se moja en el remolque y se pudre.
 *   - Viento >40 km/h         → peligroso para varear y para las redes.
 *   - Helada <2 °C            → daño en la aceituna y baja del rendimiento graso.
 *   - Probabilidad de lluvia >60 % → precaución, mejor adelantar la jornada.
 */
function calcularRecomendacionRecoleccion(array $dias): array
{
    if (count($dias) < 2) {
        return ['nivel'=>'desconocido', 'icono'=>'❓', 'titulo'=>'Sin datos suficientes',
                'mensaje'=>'No hay previsión disponible para los próximos días.'];
    }
    $hoy    = $dias[0];
    $manana = $dias[1];

    if ($hoy['precip_mm'] >= 2 || $manana['precip_mm'] >= 2) {
        $diaLluvia = $hoy['precip_mm'] >= 2 ? 'hoy' : 'mañana';
        $mm = $hoy['precip_mm'] >= 2 ? $hoy['precip_mm'] : $manana['precip_mm'];
        return [
            'nivel'   => 'malo',
            'icono'   => '🌧️',
            'titulo'  => 'NO llevar aceituna a la almazara',
            'mensaje' => "Se prevén $mm mm de lluvia $diaLluvia. La aceituna mojada en el remolque se pudre y baja el rendimiento. Espera a que pase el frente.",
        ];
    }
    if ($hoy['precip_probabilidad'] >= 60) {
        return [
            'nivel'   => 'precaucion',
            'icono'   => '⛅',
            'titulo'  => 'Cuidado con los chubascos',
            'mensaje' => 'Hay un ' . $hoy['precip_probabilidad'] . '% de probabilidad de lluvia. Adelanta la jornada y vigila el cielo.',
        ];
    }
    if ($hoy['viento_kmh'] >= 40) {
        return [
            'nivel'   => 'precaucion',
            'icono'   => '💨',
            'titulo'  => 'Viento fuerte',
            'mensaje' => 'Rachas de hasta ' . $hoy['viento_kmh'] . ' km/h. Mucho cuidado al varear y al colocar las redes.',
        ];
    }
    if ($hoy['temp_min'] < 2) {
        return [
            'nivel'   => 'precaucion',
            'icono'   => '❄️',
            'titulo'  => 'Riesgo de helada matinal',
            'mensaje' => 'Mínima prevista de ' . $hoy['temp_min'] . ' °C. Espera a media mañana — la aceituna helada pierde calidad.',
        ];
    }
    return [
        'nivel'   => 'bueno',
        'icono'   => '☀️',
        'titulo'  => 'Buen momento para recolectar',
        'mensaje' => 'Sin lluvia, sin viento fuerte y temperaturas estables. Condiciones óptimas para recolectar y transportar la aceituna.',
    ];
}

function iconoWMO(int $codigo): string
{
    return match (true) {
        $codigo === 0                         => '☀️',
        in_array($codigo, [1, 2], true)       => '🌤️',
        $codigo === 3                         => '☁️',
        in_array($codigo, [45, 48], true)     => '🌫️',
        in_array($codigo, [51, 53, 55], true) => '🌦️',
        in_array($codigo, [61, 63, 65], true) => '🌧️',
        in_array($codigo, [71, 73, 75], true) => '🌨️',
        in_array($codigo, [80, 81, 82], true) => '🌧️',
        in_array($codigo, [95, 96, 99], true) => '⛈️',
        default                               => '🌡️',
    };
}

function descripcionWMO(int $codigo): string
{
    return match (true) {
        $codigo === 0                         => 'Despejado',
        $codigo === 1                         => 'Mayormente soleado',
        $codigo === 2                         => 'Parcialmente nublado',
        $codigo === 3                         => 'Nublado',
        in_array($codigo, [45, 48], true)     => 'Niebla',
        in_array($codigo, [51, 53, 55], true) => 'Llovizna',
        in_array($codigo, [61, 63], true)     => 'Lluvia',
        $codigo === 65                        => 'Lluvia fuerte',
        in_array($codigo, [71, 73, 75], true) => 'Nieve',
        in_array($codigo, [80, 81], true)     => 'Chubascos',
        $codigo === 82                        => 'Chubascos fuertes',
        in_array($codigo, [95, 96, 99], true) => 'Tormenta',
        default                               => 'Desconocido',
    };
}
