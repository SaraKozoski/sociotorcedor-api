<?php
/**
 * Benefícios do sócio (autenticado)
 *
 * GET beneficios.php -> benefícios válidos liberados para o plano do usuário
 *                       (benefício sem plano_minimo_id vale para qualquer sócio com plano)
 */
require_once __DIR__ . '/../config/bootstrap.php';

if ($method !== 'GET') {
    erro("Método não permitido", 405);
}

$usuario = usuarioAutenticado($db);
if (!$usuario['plano_id']) {
    responder([]);
}

// Planos são ordenados por id (RED < GOLD < BLACK): plano maior libera benefícios dos menores.
$stmt = $db->prepare(
    "SELECT id, titulo, descricao, desconto_percentual, codigo, valido_ate
     FROM beneficios
     WHERE (valido_ate IS NULL OR valido_ate >= CURDATE())
       AND (plano_minimo_id IS NULL OR plano_minimo_id <= :plano)
     ORDER BY desconto_percentual DESC"
);
$stmt->execute([':plano' => $usuario['plano_id']]);
responder(array_map(function ($b) {
    $b['id'] = (int) $b['id'];
    $b['desconto_percentual'] = (int) $b['desconto_percentual'];
    return $b;
}, $stmt->fetchAll()));
