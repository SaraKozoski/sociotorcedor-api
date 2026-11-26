<?php
/**
 * Jogos
 *
 * GET jogos.php                     -> todos os jogos (mais recentes primeiro)
 * GET jogos.php?status=agendado     -> filtra por status (agendado | ao_vivo | encerrado)
 * GET jogos.php?clube_id=1          -> jogos de um clube
 * GET jogos.php?id=3                -> um jogo
 */
require_once __DIR__ . '/../config/bootstrap.php';

if ($method !== 'GET') {
    erro("Método não permitido", 405);
}

if (isset($_GET['id'])) {
    $stmt = $db->prepare(SQL_JOGOS . " WHERE j.id = :id");
    $stmt->execute([':id' => (int) $_GET['id']]);
    $jogo = $stmt->fetch();
    if (!$jogo) {
        erro("Jogo não encontrado", 404);
    }
    responder(formatarJogo($jogo));
}

$where = [];
$params = [];
if (!empty($_GET['status'])) {
    if (!in_array($_GET['status'], ['agendado', 'ao_vivo', 'encerrado'], true)) {
        erro("Status inválido");
    }
    $where[] = "j.status = :status";
    $params[':status'] = $_GET['status'];
}
if (!empty($_GET['clube_id'])) {
    $where[] = "(j.mandante_id = :clube OR j.visitante_id = :clube2)";
    $params[':clube'] = $params[':clube2'] = (int) $_GET['clube_id'];
}

$sql = SQL_JOGOS . ($where ? " WHERE " . implode(" AND ", $where) : "") . " ORDER BY j.data_hora DESC";
$stmt = $db->prepare($sql);
$stmt->execute($params);
responder(array_map('formatarJogo', $stmt->fetchAll()));
