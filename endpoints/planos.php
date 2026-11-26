<?php
/**
 * Planos de sócio
 *
 * GET  planos.php                 -> lista de planos com diferenciais
 * GET  planos.php?tipo=GOLD       -> um plano
 * POST planos.php?acao=assinar  { "tipo": "GOLD" }  (ou "plano_id")   [autenticado]
 * POST planos.php?acao=cancelar                                       [autenticado]
 * POST planos.php                 -> cria um plano (uso administrativo)
 */
require_once __DIR__ . '/../config/bootstrap.php';

function buscarPlanos(PDO $db, ?string $tipo = null): array {
    if ($tipo) {
        $stmt = $db->prepare("SELECT * FROM planos WHERE tipo = :tipo");
        $stmt->execute([':tipo' => strtoupper($tipo)]);
    } else {
        $stmt = $db->query("SELECT * FROM planos ORDER BY id");
    }
    $planos = $stmt->fetchAll();

    $stmtDif = $db->prepare(
        "SELECT titulo, descricao FROM plano_diferenciais WHERE plano_id = :id ORDER BY ordem"
    );
    foreach ($planos as &$plano) {
        $stmtDif->execute([':id' => $plano['id']]);
        $plano['diferenciais'] = $stmtDif->fetchAll();
    }
    return $planos;
}

$acao = $_GET['acao'] ?? '';

switch ($method) {
    case 'GET':
        if (isset($_GET['tipo'])) {
            $planos = buscarPlanos($db, $_GET['tipo']);
            if (!$planos) {
                erro("Plano não encontrado", 404);
            }
            responder($planos[0]);
        }
        responder(buscarPlanos($db));

    case 'POST':
        if ($acao === 'assinar') {
            $usuario = usuarioAutenticado($db);
            $data = lerJson();

            if (!empty($data['plano_id'])) {
                $stmt = $db->prepare("SELECT id, nome FROM planos WHERE id = :v");
                $stmt->execute([':v' => (int) $data['plano_id']]);
            } else {
                exigirCampos($data, ['tipo']);
                $stmt = $db->prepare("SELECT id, nome FROM planos WHERE tipo = :v");
                $stmt->execute([':v' => strtoupper($data['tipo'])]);
            }
            $plano = $stmt->fetch();
            if (!$plano) {
                erro("Plano não encontrado", 404);
            }

            emTransacao($db, function () use ($db, $usuario, $plano) {
                $db->prepare(
                    "UPDATE assinaturas SET status = 'cancelada', data_fim = CURDATE()
                     WHERE usuario_id = :uid AND status IN ('ativa','pendente')"
                )->execute([':uid' => $usuario['id']]);
                $db->prepare(
                    "INSERT INTO assinaturas (usuario_id, plano_id, status, data_inicio, data_fim)
                     VALUES (:uid, :pid, 'ativa', CURDATE(), DATE_ADD(CURDATE(), INTERVAL 1 YEAR))"
                )->execute([':uid' => $usuario['id'], ':pid' => $plano['id']]);
                $db->prepare("UPDATE usuarios SET plano_id = :pid WHERE id = :uid")
                   ->execute([':pid' => $plano['id'], ':uid' => $usuario['id']]);
            });

            responder([
                "message" => "Plano {$plano['nome']} assinado com sucesso",
                "usuario" => perfilUsuario($db, (int) $usuario['id']),
            ]);
        }

        if ($acao === 'cancelar') {
            $usuario = usuarioAutenticado($db);
            emTransacao($db, function () use ($db, $usuario) {
                $db->prepare(
                    "UPDATE assinaturas SET status = 'cancelada', data_fim = CURDATE()
                     WHERE usuario_id = :uid AND status IN ('ativa','pendente')"
                )->execute([':uid' => $usuario['id']]);
                $db->prepare("UPDATE usuarios SET plano_id = NULL WHERE id = :uid")
                   ->execute([':uid' => $usuario['id']]);
            });
            responder([
                "message" => "Assinatura cancelada",
                "usuario" => perfilUsuario($db, (int) $usuario['id']),
            ]);
        }

        // Criação de plano (administrativo)
        $data = lerJson();
        exigirCampos($data, ['tipo', 'nome']);
        $planoId = emTransacao($db, function () use ($db, $data) {
            $db->prepare(
                "INSERT INTO planos (tipo, nome, cor, preco, parcelas, especificacoes)
                 VALUES (:tipo, :nome, :cor, :preco, :parcelas, :especificacoes)"
            )->execute([
                ':tipo'           => strtoupper($data['tipo']),
                ':nome'           => $data['nome'],
                ':cor'            => $data['cor'] ?? null,
                ':preco'          => $data['preco'] ?? null,
                ':parcelas'       => $data['parcelas'] ?? null,
                ':especificacoes' => $data['especificacoes'] ?? null,
            ]);
            $planoId = (int) $db->lastInsertId();

            $stmtDif = $db->prepare(
                "INSERT INTO plano_diferenciais (plano_id, titulo, descricao, ordem)
                 VALUES (:plano_id, :titulo, :descricao, :ordem)"
            );
            foreach ($data['diferenciais'] ?? [] as $i => $dif) {
                $stmtDif->execute([
                    ':plano_id'  => $planoId,
                    ':titulo'    => $dif['titulo'],
                    ':descricao' => $dif['descricao'] ?? null,
                    ':ordem'     => $i,
                ]);
            }
            return $planoId;
        });
        responder(["message" => "Plano criado", "id" => $planoId], 201);

    default:
        erro("Método não permitido", 405);
}
