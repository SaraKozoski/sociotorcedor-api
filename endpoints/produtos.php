<?php
/**
 * Lanchonete - produtos
 *
 * GET produtos.php                 -> produtos ativos (com categoria)
 * GET produtos.php?categoria_id=1
 * GET produtos.php?id=1
 * GET produtos.php?acao=categorias -> lista de categorias
 */
require_once __DIR__ . '/../config/bootstrap.php';

if ($method !== 'GET') {
    erro("Método não permitido", 405);
}

if (($_GET['acao'] ?? '') === 'categorias') {
    responder($db->query("SELECT id, nome FROM categorias_produto ORDER BY id")->fetchAll());
}

function formatarProduto(array $p): array {
    return [
        "id"             => (int) $p['id'],
        "nome"           => $p['nome'],
        "descricao"      => $p['descricao'],
        "preco"          => (float) $p['preco'],
        "pontos_resgate" => $p['pontos_resgate'] !== null ? (int) $p['pontos_resgate'] : null,
        "imagem_url"     => $p['imagem_url'],
        "categoria_id"   => (int) $p['categoria_id'],
        "categoria"      => $p['categoria'],
    ];
}

$sql = "SELECT p.*, c.nome AS categoria FROM produtos p
        JOIN categorias_produto c ON c.id = p.categoria_id WHERE p.ativo = 1";

if (isset($_GET['id'])) {
    $stmt = $db->prepare($sql . " AND p.id = :id");
    $stmt->execute([':id' => (int) $_GET['id']]);
    $produto = $stmt->fetch();
    if (!$produto) {
        erro("Produto não encontrado", 404);
    }
    responder(formatarProduto($produto));
}

$params = [];
if (!empty($_GET['categoria_id'])) {
    $sql .= " AND p.categoria_id = :cid";
    $params[':cid'] = (int) $_GET['categoria_id'];
}
$stmt = $db->prepare($sql . " ORDER BY p.categoria_id, p.id");
$stmt->execute($params);
responder(array_map('formatarProduto', $stmt->fetchAll()));
