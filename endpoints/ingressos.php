<?php
/**
 * Ingressos
 *
 * GET  ingressos.php                 -> jogos com ingressos à venda (setores, preços, status)
 * GET  ingressos.php?jogo_id=3       -> ingressos de um jogo
 * GET  ingressos.php?meus=1          -> ingressos comprados pelo usuário            [autenticado]
 * POST ingressos.php { "ingresso_jogo_id": 1, "quantidade": 1, "pagar_com_saldo": true } [autenticado]
 */
require_once __DIR__ . '/../config/bootstrap.php';

/** Monta a lista de jogos com seus setores de ingresso. */
function jogosComIngressos(PDO $db, ?int $jogoId = null): array {
    $sql = SQL_JOGOS . " WHERE EXISTS (SELECT 1 FROM ingressos_jogo ij WHERE ij.jogo_id = j.id)";
    $params = [];
    if ($jogoId) {
        $sql .= " AND j.id = :id";
        $params[':id'] = $jogoId;
    }
    $stmt = $db->prepare($sql . " ORDER BY j.data_hora");
    $stmt->execute($params);
    $jogos = $stmt->fetchAll();

    $stmtSet = $db->prepare(
        "SELECT ij.id, ij.setor_id, s.nome AS setor, ij.preco, ij.preco_original,
                ij.quantidade_total, ij.quantidade_vendida, ij.status
         FROM ingressos_jogo ij JOIN setores s ON s.id = ij.setor_id
         WHERE ij.jogo_id = :jid ORDER BY ij.preco"
    );

    $resultado = [];
    foreach ($jogos as $j) {
        $stmtSet->execute([':jid' => $j['id']]);
        $setores = array_map(function ($s) {
            return [
                "ingresso_jogo_id" => (int) $s['id'],
                "setor_id"         => (int) $s['setor_id'],
                "setor"            => $s['setor'],
                "preco"            => (float) $s['preco'],
                "preco_original"   => $s['preco_original'] !== null ? (float) $s['preco_original'] : null,
                "disponiveis"      => max(0, (int) $s['quantidade_total'] - (int) $s['quantidade_vendida']),
                "status"           => $s['status'],
            ];
        }, $stmtSet->fetchAll());

        $disponiveis = array_filter($setores, fn($s) => $s['status'] === 'disponivel');
        $statuses = array_column($setores, 'status');
        $status = $disponiveis ? 'disponivel'
                : (in_array('em_breve', $statuses, true) ? 'em_breve' : 'esgotado');
        $maisBarato = $disponiveis ? array_values($disponiveis)[0] : null;

        $resultado[] = formatarJogo($j) + [
            "status_ingresso" => $status,
            "preco_minimo"    => $maisBarato['preco'] ?? null,
            "preco_original"  => $maisBarato['preco_original'] ?? null,
            "setores"         => $setores,
        ];
    }
    return $resultado;
}

switch ($method) {
    case 'GET':
        if (!empty($_GET['meus'])) {
            $usuario = usuarioAutenticado($db);
            $stmt = $db->prepare(
                "SELECT iu.id, iu.fila, iu.cadeira, iu.status, iu.qr_code, iu.criado_em,
                        s.nome AS setor, ij.preco, ij.jogo_id
                 FROM ingressos_usuario iu
                 JOIN ingressos_jogo ij ON ij.id = iu.ingresso_jogo_id
                 JOIN setores s ON s.id = ij.setor_id
                 WHERE iu.usuario_id = :uid
                 ORDER BY iu.criado_em DESC"
            );
            $stmt->execute([':uid' => $usuario['id']]);
            $ingressos = $stmt->fetchAll();

            $stmtJogo = $db->prepare(SQL_JOGOS . " WHERE j.id = :id");
            responder(array_map(function ($i) use ($stmtJogo) {
                $stmtJogo->execute([':id' => $i['jogo_id']]);
                return [
                    "id"        => (int) $i['id'],
                    "setor"     => $i['setor'],
                    "fila"      => $i['fila'],
                    "cadeira"   => $i['cadeira'],
                    "status"    => $i['status'],
                    "qr_code"   => $i['qr_code'],
                    "preco"     => (float) $i['preco'],
                    "criado_em" => $i['criado_em'],
                    "jogo"      => formatarJogo($stmtJogo->fetch()),
                ];
            }, $ingressos));
        }

        if (isset($_GET['jogo_id'])) {
            $jogos = jogosComIngressos($db, (int) $_GET['jogo_id']);
            if (!$jogos) {
                erro("Nenhum ingresso para este jogo", 404);
            }
            responder($jogos[0]);
        }

        responder(jogosComIngressos($db));

    case 'POST':
        $usuario = usuarioAutenticado($db);
        $data = lerJson();
        exigirCampos($data, ['ingresso_jogo_id']);
        $quantidade = max(1, (int) ($data['quantidade'] ?? 1));
        if ($quantidade > 10) {
            erro("Máximo de 10 ingressos por compra");
        }
        $pagarComSaldo = !empty($data['pagar_com_saldo']);

        $comprados = emTransacao($db, function () use ($db, $usuario, $data, $quantidade, $pagarComSaldo) {
            $stmt = $db->prepare(
                "SELECT ij.*, s.nome AS setor FROM ingressos_jogo ij JOIN setores s ON s.id = ij.setor_id
                 WHERE ij.id = :id FOR UPDATE"
            );
            $stmt->execute([':id' => (int) $data['ingresso_jogo_id']]);
            $ij = $stmt->fetch();
            if (!$ij) {
                throw new DomainException("Ingresso não encontrado");
            }
            if ($ij['status'] !== 'disponivel') {
                throw new DomainException($ij['status'] === 'esgotado' ? "Ingresso esgotado" : "Vendas ainda não abertas");
            }
            $restantes = (int) $ij['quantidade_total'] - (int) $ij['quantidade_vendida'];
            if ($restantes < $quantidade) {
                throw new DomainException("Restam apenas $restantes ingresso(s) neste setor");
            }

            $total = (float) $ij['preco'] * $quantidade;
            if ($pagarComSaldo) {
                movimentarCarteira($db, (int) $usuario['id'], 'debito', $total,
                    "Ingresso(s) - {$ij['setor']} (jogo #{$ij['jogo_id']})");
            }

            $insert = $db->prepare(
                "INSERT INTO ingressos_usuario (usuario_id, ingresso_jogo_id, fila, cadeira, status, qr_code)
                 VALUES (:uid, :ijid, :fila, :cadeira, 'confirmado', :qr)"
            );
            $ids = [];
            for ($n = (int) $ij['quantidade_vendida']; $n < (int) $ij['quantidade_vendida'] + $quantidade; $n++) {
                $insert->execute([
                    ':uid'     => $usuario['id'],
                    ':ijid'    => $ij['id'],
                    ':fila'    => (string) (intdiv($n, 20) + 1),
                    ':cadeira' => (string) ($n % 20 + 1),
                    ':qr'      => "ST-" . strtoupper(bin2hex(random_bytes(12))),
                ]);
                $ids[] = (int) $db->lastInsertId();
            }

            $novoVendido = (int) $ij['quantidade_vendida'] + $quantidade;
            $db->prepare(
                "UPDATE ingressos_jogo SET quantidade_vendida = :v,
                 status = IF(:v2 >= quantidade_total, 'esgotado', status) WHERE id = :id"
            )->execute([':v' => $novoVendido, ':v2' => $novoVendido, ':id' => $ij['id']]);

            return ["ids" => $ids, "total" => $total];
        });

        responder([
            "message"   => "Compra realizada com sucesso",
            "ingressos" => $comprados['ids'],
            "total"     => $comprados['total'],
            "usuario"   => perfilUsuario($db, (int) $usuario['id']),
        ], 201);

    default:
        erro("Método não permitido", 405);
}
