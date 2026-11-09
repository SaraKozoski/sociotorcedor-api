<?php
header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

include_once '../config/database.php';

$database = new Database();
$db = $database->getConnection();

$method = $_SERVER['REQUEST_METHOD'];

switch ($method) {
    case 'GET':
        $stmt = $db->query("SELECT id, nome, email, cpf, saldo, pontos, plano_id, criado_em FROM usuarios");
        echo json_encode($stmt->fetchAll(PDO::FETCH_ASSOC));
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"));
        $stmt = $db->prepare("INSERT INTO usuarios (nome, email, cpf, senha) VALUES (:nome, :email, :cpf, :senha)");
        $stmt->execute([
            ':nome'  => $data->nome,
            ':email' => $data->email,
            ':cpf'   => $data->cpf ?? null,
            ':senha' => password_hash($data->senha, PASSWORD_BCRYPT)
        ]);
        echo json_encode(["message" => "Usuário criado com sucesso", "id" => $db->lastInsertId()]);
        break;

    case 'PUT':
        $data = json_decode(file_get_contents("php://input"));
        $stmt = $db->prepare("UPDATE usuarios SET nome = :nome, email = :email WHERE id = :id");
        $stmt->execute([
            ':nome'  => $data->nome,
            ':email' => $data->email,
            ':id'    => $data->id
        ]);
        echo json_encode(["message" => "Usuário atualizado com sucesso"]);
        break;

    case 'DELETE':
        $data = json_decode(file_get_contents("php://input"));
        $stmt = $db->prepare("DELETE FROM usuarios WHERE id = :id");
        $stmt->execute([':id' => $data->id]);
        echo json_encode(["message" => "Usuário deletado com sucesso"]);
        break;
}
