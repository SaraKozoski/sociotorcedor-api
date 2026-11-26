<?php
/**
 * Palpites - "Aposte aqui" (autenticado)
 *
 * GET  palpites.php               -> palpites do usuário (com o jogo)
 * GET  palpites.php?jogo_id=3     -> palpite do usuário para um jogo (ou null)
 * POST palpites.php { "jogo_id": 3, "gols_mandante": 2, "gols_visitante": 1, "pontos_apostados": 10 }
 *      -> cria/atualiza o palpite enquanto o jogo estiver "agendado"
 */
require_once __DIR__ . '/../config/bootstrap.php';

$usuario = usuarioAutenticado($db);
$uid = (int) $usuario['id'];

function formatarPalpite(array $p): array {
    return [
        "id"               => (int) $p['id'],
        "jogo_id"          => (int) $p['jogo_id'],
        "gols_mandante"    => (int) $p['gols_mandante'],
        "gols_visitante"   => (int) $p['gols_visitante'],
        "pontos_apostados" => (int) $p['pontos_apostados'],
        "criado_em"        => $p['criado_em'],
    ];
}

switch ($method) {
    case 'GET':
        if (isset($_GET['jogo_id'])) {
            $stmt = $db->prepare("SELECT * FROM palpites WHERE usuario_id = :u AND jogo_id = :j");
            $stmt->execute([':u' => $uid, ':j' => (int) $_GET['jogo_id']]);
            $p = $stmt->fetch();
            responder($p ? formatarPalpite($p) : null);
        }

        $stmt = $db->prepare("SELECT * FROM palpites WHERE usuario_id = :u ORDER BY criado_em DESC");
        $stmt->execute([':u' => $uid]);
        $stmtJogo = $db->prepare(SQL_JOGOS . " WHERE j.id = :id");
        responder(array_map(function ($p) use ($stmtJogo) {
            $stmtJogo->execute([':id' => $p['jogo_id']]);
            return formatarPalpite($p) + ["jogo" => formatarJogo($stmtJogo->fetch())];
        }, $stmt->fetchAll()));

    case 'POST':
        $data = lerJson();
        exigirCampos($data, ['jogo_id', 'gols_mandante', 'gols_visitante']);
        $golsM = (int) $data['gols_mandante'];
        $golsV = (int) $data['gols_visitante'];
        $pontos = max(0, (int) ($data['pontos_apostados'] ?? 0));
        if ($golsM < 0 || $golsV < 0 || $golsM > 30 || $golsV > 30) {
            erro("Placar inválido");
        }

        $palpite = emTransacao($db, function () use ($db, $uid, $data, $golsM, $golsV, $pontos) {
            $stmt = $db->prepare("SELECT status FROM jogos WHERE id = :id");
            $stmt->execute([':id' => (int) $data['jogo_id']]);
            $status = $stmt->fetchColumn();
            if ($status === false) {
                throw new DomainException("Jogo não encontrado");
            }
            if ($status !== 'agendado') {
                throw new DomainException("Palpites encerrados para este jogo");
            }

            $stmt = $db->prepare("SELECT * FROM palpites WHERE usuario_id = :u AND jogo_id = :j FOR UPDATE");
            $stmt->execute([':u' => $uid, ':j' => (int) $data['jogo_id']]);
            $anterior = $stmt->fetch();

            // Devolve os pontos do palpite anterior e debita os novos.
            $diferenca = $pontos - ($anterior ? (int) $anterior['pontos_apostados'] : 0);
            if ($diferenca !== 0) {
                movimentarPontos($db, $uid, -$diferenca, "Palpite jogo #{$data['jogo_id']}");
            }

            if ($anterior) {
                $db->prepare(
                    "UPDATE palpites SET gols_mandante = :gm, gols_visitante = :gv, pontos_apostados = :p WHERE id = :id"
                )->execute([':gm' => $golsM, ':gv' => $golsV, ':p' => $pontos, ':id' => $anterior['id']]);
                $id = (int) $anterior['id'];
            } else {
                $db->prepare(
                    "INSERT INTO palpites (usuario_id, jogo_id, gols_mandante, gols_visitante, pontos_apostados)
                     VALUES (:u, :j, :gm, :gv, :p)"
                )->execute([':u' => $uid, ':j' => (int) $data['jogo_id'], ':gm' => $golsM, ':gv' => $golsV, ':p' => $pontos]);
                $id = (int) $db->lastInsertId();
            }
            $stmt = $db->prepare("SELECT * FROM palpites WHERE id = :id");
            $stmt->execute([':id' => $id]);
            return $stmt->fetch();
        });

        responder([
            "message" => "Palpite registrado",
            "palpite" => formatarPalpite($palpite),
            "usuario" => perfilUsuario($db, $uid),
        ], 201);

    default:
        erro("Método não permitido", 405);
}
