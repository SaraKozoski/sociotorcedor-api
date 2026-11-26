<?php
/**
 * Perfil do usuário logado (header Authorization: Bearer <token>)
 *
 * GET    usuarios.php                          -> perfil (saldo, pontos, plano...)
 * POST   usuarios.php                          -> cadastro (mesmo que auth.php?acao=cadastro)
 * PUT    usuarios.php  { "nome"?, "email"? }   -> atualiza o perfil
 * PUT    usuarios.php?acao=senha { "senha_atual", "nova_senha" }
 * DELETE usuarios.php                          -> exclui a própria conta
 */
if ($_SERVER['REQUEST_METHOD'] === 'POST') {
    $_GET['acao'] = 'cadastro';
    require __DIR__ . '/auth.php';
    exit;
}

require_once __DIR__ . '/../config/bootstrap.php';

$usuario = usuarioAutenticado($db);
$uid = (int) $usuario['id'];

switch ($method) {
    case 'GET':
        responder(perfilUsuario($db, $uid));

    case 'PUT':
        $data = lerJson();

        if (($_GET['acao'] ?? '') === 'senha') {
            exigirCampos($data, ['senha_atual', 'nova_senha']);
            if (!password_verify($data['senha_atual'], $usuario['senha'] ?? '')) {
                erro("Senha atual incorreta", 401);
            }
            if (strlen($data['nova_senha']) < 6) {
                erro("A senha deve ter no mínimo 6 caracteres");
            }
            $db->prepare("UPDATE usuarios SET senha = :s WHERE id = :id")
               ->execute([':s' => password_hash($data['nova_senha'], PASSWORD_BCRYPT), ':id' => $uid]);
            responder(["message" => "Senha alterada com sucesso"]);
        }

        $nome  = isset($data['nome']) ? trim($data['nome']) : $usuario['nome'];
        $email = isset($data['email']) ? trim($data['email']) : $usuario['email'];
        if ($nome === '' || !filter_var($email, FILTER_VALIDATE_EMAIL)) {
            erro("Nome ou e-mail inválido");
        }
        $stmt = $db->prepare("SELECT id FROM usuarios WHERE email = :email AND id <> :id");
        $stmt->execute([':email' => $email, ':id' => $uid]);
        if ($stmt->fetch()) {
            erro("E-mail já cadastrado", 409);
        }
        $db->prepare("UPDATE usuarios SET nome = :nome, email = :email WHERE id = :id")
           ->execute([':nome' => $nome, ':email' => $email, ':id' => $uid]);
        responder(perfilUsuario($db, $uid));

    case 'DELETE':
        // Remove dados dependentes antes do usuário (chaves estrangeiras).
        emTransacao($db, function () use ($db, $uid) {
            foreach ([
                "DELETE pi FROM pedido_itens pi JOIN pedidos p ON p.id = pi.pedido_id WHERE p.usuario_id = :id",
                "DELETE FROM pedidos WHERE usuario_id = :id",
                "DELETE FROM resgates WHERE usuario_id = :id",
                "DELETE FROM palpites WHERE usuario_id = :id",
                "DELETE FROM ingressos_usuario WHERE usuario_id = :id",
                "DELETE FROM assinaturas WHERE usuario_id = :id",
                "DELETE FROM carteira_transacoes WHERE usuario_id = :id",
                "DELETE FROM pontos_transacoes WHERE usuario_id = :id",
                "DELETE FROM usuarios WHERE id = :id",
            ] as $sql) {
                $db->prepare($sql)->execute([':id' => $uid]);
            }
        });
        responder(["message" => "Conta excluída com sucesso"]);

    default:
        erro("Método não permitido", 405);
}
