<?php
/**
 * Autenticação
 *
 * POST auth.php?acao=login     { "cpf": "12345678901", "senha": "123456" }   (ou "email" no lugar de "cpf")
 * POST auth.php?acao=cadastro  { "nome", "email", "cpf", "senha", "data_nascimento"?, "logradouro"?, "numero"?, "cep"?, "cidade"?, "uf"? }
 * POST auth.php?acao=logout    (header Authorization: Bearer <token>)
 *
 * login e cadastro respondem: { "token": "...", "usuario": { ...perfil } }
 */
require_once __DIR__ . '/../config/bootstrap.php';

if ($method !== 'POST') {
    erro("Método não permitido", 405);
}

$acao = $_GET['acao'] ?? '';
$data = lerJson();

switch ($acao) {
    case 'login':
        exigirCampos($data, ['senha']);
        $cpf = somenteDigitos($data['cpf'] ?? null);
        $email = isset($data['email']) ? trim($data['email']) : null;
        if (!$cpf && !$email) {
            erro("Informe o CPF ou o e-mail");
        }

        $stmt = $cpf
            ? $db->prepare("SELECT id, senha FROM usuarios WHERE cpf = :v")
            : $db->prepare("SELECT id, senha FROM usuarios WHERE email = :v");
        $stmt->execute([':v' => $cpf ?: $email]);
        $usuario = $stmt->fetch();

        if (!$usuario || !$usuario['senha'] || !password_verify($data['senha'], $usuario['senha'])) {
            erro("CPF ou senha incorretos", 401);
        }

        responder([
            "token"   => gerarToken($db, (int) $usuario['id']),
            "usuario" => perfilUsuario($db, (int) $usuario['id']),
        ]);

    case 'cadastro':
        exigirCampos($data, ['nome', 'email', 'cpf', 'senha']);
        $cpf = somenteDigitos($data['cpf']);
        if (strlen($cpf ?? '') !== 11) {
            erro("CPF deve conter 11 dígitos");
        }
        if (!filter_var($data['email'], FILTER_VALIDATE_EMAIL)) {
            erro("E-mail inválido");
        }
        if (strlen($data['senha']) < 6) {
            erro("A senha deve ter no mínimo 6 caracteres");
        }

        $stmt = $db->prepare("SELECT cpf FROM usuarios WHERE cpf = :cpf OR email = :email");
        $stmt->execute([':cpf' => $cpf, ':email' => $data['email']]);
        if ($existente = $stmt->fetch()) {
            erro($existente['cpf'] === $cpf ? "CPF já cadastrado" : "E-mail já cadastrado", 409);
        }

        // Aceita data no formato dd/mm/aaaa (como o app digita) ou aaaa-mm-dd.
        $dataNasc = null;
        if (!empty($data['data_nascimento'])) {
            $d = DateTime::createFromFormat('d/m/Y', $data['data_nascimento'])
              ?: DateTime::createFromFormat('Y-m-d', $data['data_nascimento'])
              ?: DateTime::createFromFormat('dmY', somenteDigitos($data['data_nascimento']) ?? '');
            $dataNasc = $d ? $d->format('Y-m-d') : null;
        }

        $stmt = $db->prepare(
            "INSERT INTO usuarios (nome, email, cpf, senha, data_nascimento, logradouro, numero, cep, cidade, uf)
             VALUES (:nome, :email, :cpf, :senha, :data_nascimento, :logradouro, :numero, :cep, :cidade, :uf)"
        );
        $stmt->execute([
            ':nome'            => trim($data['nome']),
            ':email'           => trim($data['email']),
            ':cpf'             => $cpf,
            ':senha'           => password_hash($data['senha'], PASSWORD_BCRYPT),
            ':data_nascimento' => $dataNasc,
            ':logradouro'      => $data['logradouro'] ?? null,
            ':numero'          => $data['numero'] ?? null,
            ':cep'             => somenteDigitos($data['cep'] ?? null),
            ':cidade'          => $data['cidade'] ?? null,
            ':uf'              => isset($data['uf']) ? strtoupper(substr($data['uf'], 0, 2)) : null,
        ]);
        $id = (int) $db->lastInsertId();

        responder([
            "token"   => gerarToken($db, $id),
            "usuario" => perfilUsuario($db, $id),
        ], 201);

    case 'logout':
        $token = tokenDaRequisicao();
        if ($token) {
            $db->prepare("DELETE FROM tokens WHERE token = :t")->execute([':t' => $token]);
        }
        responder(["message" => "Logout realizado"]);

    default:
        erro("Ação inválida. Use ?acao=login, ?acao=cadastro ou ?acao=logout", 404);
}
