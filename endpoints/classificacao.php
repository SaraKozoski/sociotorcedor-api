<?php
/**
 * Classificação
 *
 * GET classificacao.php                   -> campeonato 1
 * GET classificacao.php?campeonato_id=2
 */
require_once __DIR__ . '/../config/bootstrap.php';

if ($method !== 'GET') {
    erro("Método não permitido", 405);
}

$stmt = $db->prepare(
    "SELECT cl.posicao, cl.pontos, cl.jogos, cl.vitorias, cl.empates, cl.derrotas,
            cl.gols_pro, cl.gols_contra, (cl.gols_pro - cl.gols_contra) AS saldo_gols,
            c.id AS clube_id, c.nome AS clube_nome, c.sigla AS clube_sigla, c.escudo_url AS clube_escudo
     FROM classificacao cl JOIN clubes c ON c.id = cl.clube_id
     WHERE cl.campeonato_id = :cid
     ORDER BY cl.posicao"
);
$stmt->execute([':cid' => (int) ($_GET['campeonato_id'] ?? 1)]);

responder(array_map(function ($l) {
    return [
        "posicao"     => (int) $l['posicao'],
        "clube"       => [
            "id"         => (int) $l['clube_id'],
            "nome"       => $l['clube_nome'],
            "sigla"      => $l['clube_sigla'],
            "escudo_url" => $l['clube_escudo'],
        ],
        "pontos"      => (int) $l['pontos'],
        "jogos"       => (int) $l['jogos'],
        "vitorias"    => (int) $l['vitorias'],
        "empates"     => (int) $l['empates'],
        "derrotas"    => (int) $l['derrotas'],
        "gols_pro"    => (int) $l['gols_pro'],
        "gols_contra" => (int) $l['gols_contra'],
        "saldo_gols"  => (int) $l['saldo_gols'],
    ];
}, $stmt->fetchAll()));
