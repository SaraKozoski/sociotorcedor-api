<?php
/**
 * Carteira e pontos (autenticado)
 *
 * GET  carteira.php                    -> { saldo, pontos, transacoes: [...], pontos_transacoes: [...] }
 * POST carteira.php { "valor": 50.0 }  -> adiciona crédito (recarga)
 */
require_once __DIR__ . '/../config/bootstrap.php';

$usuario = usuarioAutenticado($db);
$uid = (int) $usuario['id'];

switch ($method) {
    case 'GET':
        $stmt = $db->prepare(
            "SELECT id, tipo, valor, descricao, criado_em FROM carteira_transacoes
             WHERE usuario_id = :uid ORDER BY criado_em DESC, id DESC LIMIT 50"
        );
        $stmt->execute([':uid' => $uid]);
        $transacoes = array_map(function ($t) {
            $t['valor'] = (float) $t['valor'];
            return $t;
        }, $stmt->fetchAll());

        $stmt = $db->prepare(
            "SELECT id, pontos, motivo, criado_em FROM pontos_transacoes
             WHERE usuario_id = :uid ORDER BY criado_em DESC, id DESC LIMIT 50"
        );
        $stmt->execute([':uid' => $uid]);

        $perfil = perfilUsuario($db, $uid);
        responder([
            "saldo"             => $perfil['saldo'],
            "pontos"            => (int) $perfil['pontos'],
            "transacoes"        => $transacoes,
            "pontos_transacoes" => $stmt->fetchAll(),
        ]);

    case 'POST':
        $data = lerJson();
        $valor = round((float) ($data['valor'] ?? 0), 2);
        if ($valor <= 0 || $valor > 5000) {
            erro("Valor de recarga inválido (entre R$ 0,01 e R$ 5.000,00)");
        }
        emTransacao($db, fn() => movimentarCarteira($db, $uid, 'credito', $valor,
            $data['descricao'] ?? "Recarga de créditos"));
        responder([
            "message" => "Crédito adicionado",
            "usuario" => perfilUsuario($db, $uid),
        ], 201);

    default:
        erro("Método não permitido", 405);
}
