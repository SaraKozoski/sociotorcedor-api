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
        $stmt = $db->query("SELECT * FROM planos");
        $planos = $stmt->fetchAll(PDO::FETCH_ASSOC);

        foreach ($planos as &$plano) {
            $stmtDif = $db->prepare(
                "SELECT titulo, descricao FROM plano_diferenciais WHERE plano_id = :id ORDER BY ordem"
            );
            $stmtDif->execute([':id' => $plano['id']]);
            $plano['diferenciais'] = $stmtDif->fetchAll(PDO::FETCH_ASSOC);
        }

        echo json_encode($planos);
        break;

    case 'POST':
        $data = json_decode(file_get_contents("php://input"));

        $stmt = $db->prepare(
            "INSERT INTO planos (tipo, nome, cor, preco, parcelas, especificacoes)
             VALUES (:tipo, :nome, :cor, :preco, :parcelas, :especificacoes)"
        );
        $stmt->execute([
            ':tipo' => $data->tipo,
            ':nome' => $data->nome,
            ':cor' => $data->cor,
            ':preco' => $data->preco,
            ':parcelas' => $data->parcelas,
            ':especificacoes' => $data->especificacoes
        ]);

        $planoId = $db->lastInsertId();

        if (!empty($data->diferenciais)) {
            $stmtDif = $db->prepare(
                "INSERT INTO plano_diferenciais (plano_id, titulo, descricao, ordem)
                 VALUES (:plano_id, :titulo, :descricao, :ordem)"
            );
            foreach ($data->diferenciais as $i => $dif) {
                $stmtDif->execute([
                    ':plano_id' => $planoId,
                    ':titulo' => $dif->titulo,
                    ':descricao' => $dif->descricao,
                    ':ordem' => $i
                ]);
            }
        }

        echo json_encode(["message" => "Plano criado", "id" => $planoId]);
        break;
}
