<?php
require_once './config/db_connection.php';

header('Content-Type: application/json');

// Verificar que sea una petición POST
if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    http_response_code(405);
    echo json_encode(['error' => 'Método no permitido']);
    exit;
}

// Obtener datos JSON
$input = json_decode(file_get_contents('php://input'), true);

if (!$input) {
    http_response_code(400);
    echo json_encode(['error' => 'Datos inválidos']);
    exit;
}

$search = isset($input['search']) ? trim($input['search']) : '';
$genre = isset($input['genre']) ? trim($input['genre']) : '';
$date = isset($input['date']) ? trim($input['date']) : '';

$db = getDB();

try {
    // Construir consulta con filtros
    $where_conditions = ["s.estado = 'activo'", "s.Fecha >= CURDATE()"];
    $params = [];

    if (!empty($search)) {
        $where_conditions[] = "(s.Nombre LIKE ? OR v.nombre LIKE ? OR a.Nombre LIKE ? OR a.Apellido LIKE ?)";
        $search_param = "%$search%";
        $params = array_merge($params, [$search_param, $search_param, $search_param, $search_param]);
    }

    if (!empty($genre)) {
        $where_conditions[] = "a.genero = ?";
        $params[] = $genre;
    }

    if (!empty($date)) {
        $where_conditions[] = "s.Fecha = ?";
        $params[] = $date;
    }

    $where_sql = implode(" AND ", $where_conditions);

    // Consulta principal
    $stmt = $db->prepare("
        SELECT DISTINCT s.*, v.nombre as venue_nombre, v.Direccion as venue_direccion,
               MIN(sec.Capacidad * (v.PrecioBase + (v.PrecioBase * sec.Porc_agr / 100))) as precio_minimo,
               GROUP_CONCAT(CONCAT(a.Nombre, ' ', a.Apellido) SEPARATOR ', ') as artistas_nombres
        FROM Shows s
        JOIN Venue v ON s.id_venue = v.id_venue
        JOIN Sector sec ON v.id_venue = sec.idVenue
        LEFT JOIN Show_Artistas sa ON s.id_show = sa.id_show
        LEFT JOIN Artistas a ON sa.id_artista = a.id_artista
        WHERE $where_sql
        GROUP BY s.id_show
        ORDER BY s.Fecha ASC, s.Horario ASC
        LIMIT 20
    ");
    
    $stmt->execute($params);
    $eventos = $stmt->fetchAll(PDO::FETCH_ASSOC);

    // Formatear los datos para el frontend
    $eventos_formateados = array_map(function($evento) {
        return [
            'id_show' => $evento['id_show'],
            'Nombre' => $evento['Nombre'],
            'Fecha' => $evento['Fecha'],
            'Horario' => $evento['Horario'],
            'venue_nombre' => $evento['venue_nombre'],
            'precio_minimo' => floatval($evento['precio_minimo']),
            'imagen' => $evento['imagen'],
            'artistas' => $evento['artistas_nombres'],
            'descripcion' => $evento['descripcion']
        ];
    }, $eventos);

    echo json_encode($eventos_formateados);

} catch (Exception $e) {
    http_response_code(500);
    echo json_encode(['error' => 'Error interno del servidor']);
}
?>