<?php
/**
 * Elenco
 *
 * GET jogadores.php                -> elenco do clube 1
 * GET jogadores.php?clube_id=1
 * GET jogadores.php?id=1           -> um jogador
 */
require_once __DIR__ . '/../config/bootstrap.php';

if ($method !== 'GET') {
    erro("Método não permitido", 405);
}

function formatarJogador(array $j): array {
    $j['id']       = (int) $j['id'];
    $j['clube_id'] = (int) $j['clube_id'];
    $j['numero']   = $j['numero'] !== null ? (int) $j['numero'] : null;
    $j['altura']   = $j['altura'] !== null ? (float) $j['altura'] : null;
    $j['peso']     = $j['peso'] !== null ? (float) $j['peso'] : null;
    return $j;
}

if (isset($_GET['id'])) {
    $stmt = $db->prepare("SELECT * FROM jogadores WHERE id = :id");
    $stmt->execute([':id' => (int) $_GET['id']]);
    $jogador = $stmt->fetch();
    if (!$jogador) {
        erro("Jogador não encontrado", 404);
    }
    responder(formatarJogador($jogador));
}

$stmt = $db->prepare(
    "SELECT * FROM jogadores WHERE clube_id = :cid
     ORDER BY FIELD(posicao, 'goleiro','defensor','meia','atacante'), numero"
);
$stmt->execute([':cid' => (int) ($_GET['clube_id'] ?? 1)]);
responder(array_map('formatarJogador', $stmt->fetchAll()));
