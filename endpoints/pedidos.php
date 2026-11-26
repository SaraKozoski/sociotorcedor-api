<?php
/**
 * Lanchonete - pedidos e resgates (todos autenticados)
 *
 * GET  pedidos.php                      -> pedidos do usuário com itens
 * POST pedidos.php {
 *        "itens": [ { "produto_id": 1, "quantidade": 2, "valor_adicionais": 3.0, "observacao": "sem cebola" } ],
 *        "pagar_com_saldo": true
 *      }
 *      -> cria o pedido; o preço vem do banco; ganha 1 ponto por real gasto
 * POST pedidos.php?acao=resgatar { "produto_id": 1 }  -> troca pontos pelo produto
 */
require_once __DIR__ . '/../config/bootstrap.php';

$usuario = usuarioAutenticado($db);
$uid = (int) $usuario['id'];

switch ($method) {
    case 'GET':
        $stmt = $db->prepare("SELECT * FROM pedidos WHERE usuario_id = :uid ORDER BY criado_em DESC");
        $stmt->execute([':uid' => $uid]);
        $pedidos = $stmt->fetchAll();

        $stmtItens = $db->prepare(
            "SELECT pi.produto_id, p.nome, pi.quantidade, pi.preco_unitario
             FROM pedido_itens pi JOIN produtos p ON p.id = pi.produto_id WHERE pi.pedido_id = :id"
        );
        foreach ($pedidos as &$pedido) {
            $stmtItens->execute([':id' => $pedido['id']]);
            $pedido['total'] = (float) $pedido['total'];
            $pedido['itens'] = array_map(fn($i) => [
                "produto_id"     => (int) $i['produto_id'],
                "nome"           => $i['nome'],
                "quantidade"     => (int) $i['quantidade'],
                "preco_unitario" => (float) $i['preco_unitario'],
            ], $stmtItens->fetchAll());
        }
        responder($pedidos);

    case 'POST':
        $data = lerJson();

        if (($_GET['acao'] ?? '') === 'resgatar') {
            exigirCampos($data, ['produto_id']);
            $stmt = $db->prepare("SELECT id, nome, pontos_resgate FROM produtos WHERE id = :id AND ativo = 1");
            $stmt->execute([':id' => (int) $data['produto_id']]);
            $produto = $stmt->fetch();
            if (!$produto) {
                erro("Produto não encontrado", 404);
            }
            if ($produto['pontos_resgate'] === null) {
                erro("Este produto não pode ser resgatado com pontos");
            }
            $pontos = (int) $produto['pontos_resgate'];

            $resgateId = emTransacao($db, function () use ($db, $uid, $produto, $pontos) {
                movimentarPontos($db, $uid, -$pontos, "Resgate: {$produto['nome']}");
                $db->prepare("INSERT INTO resgates (usuario_id, produto_id, pontos_usados) VALUES (:u, :p, :pts)")
                   ->execute([':u' => $uid, ':p' => $produto['id'], ':pts' => $pontos]);
                return (int) $db->lastInsertId();
            });

            responder([
                "message"    => "{$produto['nome']} resgatado com sucesso",
                "resgate_id" => $resgateId,
                "qr_code"    => "RESGATE-$resgateId",
                "usuario"    => perfilUsuario($db, $uid),
            ], 201);
        }

        if (empty($data['itens']) || !is_array($data['itens'])) {
            erro("O pedido precisa ter pelo menos um item");
        }
        $pagarComSaldo = !empty($data['pagar_com_saldo']);

        $pedido = emTransacao($db, function () use ($db, $uid, $data, $pagarComSaldo) {
            $stmtProd = $db->prepare("SELECT id, preco FROM produtos WHERE id = :id AND ativo = 1");
            $itens = [];
            $total = 0.0;
            foreach ($data['itens'] as $item) {
                $stmtProd->execute([':id' => (int) ($item['produto_id'] ?? 0)]);
                $produto = $stmtProd->fetch();
                if (!$produto) {
                    throw new DomainException("Produto {$item['produto_id']} não encontrado");
                }
                $qtd = max(1, (int) ($item['quantidade'] ?? 1));
                $unitario = round((float) $produto['preco'] + max(0, (float) ($item['valor_adicionais'] ?? 0)), 2);
                $itens[] = [$produto['id'], $qtd, $unitario];
                $total += $unitario * $qtd;
            }
            $total = round($total, 2);
            $pontosGanhos = (int) floor($total);

            $db->prepare(
                "INSERT INTO pedidos (usuario_id, total, pontos_ganhos, status) VALUES (:u, :t, :p, 'pago')"
            )->execute([':u' => $uid, ':t' => $total, ':p' => $pontosGanhos]);
            $pedidoId = (int) $db->lastInsertId();

            $stmtItem = $db->prepare(
                "INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco_unitario)
                 VALUES (:pid, :prod, :q, :pu)"
            );
            foreach ($itens as [$prodId, $qtd, $unitario]) {
                $stmtItem->execute([':pid' => $pedidoId, ':prod' => $prodId, ':q' => $qtd, ':pu' => $unitario]);
            }

            if ($pagarComSaldo) {
                movimentarCarteira($db, $uid, 'debito', $total, "Pedido lanchonete #$pedidoId");
            }
            if ($pontosGanhos > 0) {
                movimentarPontos($db, $uid, $pontosGanhos, "Pedido lanchonete #$pedidoId");
            }
            return ["id" => $pedidoId, "total" => $total, "pontos_ganhos" => $pontosGanhos];
        });

        responder([
            "message"       => "Pedido realizado com sucesso",
            "pedido_id"     => $pedido['id'],
            "total"         => $pedido['total'],
            "pontos_ganhos" => $pedido['pontos_ganhos'],
            "qr_code"       => "PEDIDO-{$pedido['id']}",
            "usuario"       => perfilUsuario($db, $uid),
        ], 201);

    default:
        erro("Método não permitido", 405);
}
