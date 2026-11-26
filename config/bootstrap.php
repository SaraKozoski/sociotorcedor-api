<?php
/**
 * Arquivo comum incluído por todos os endpoints.
 * - Cabeçalhos JSON + CORS
 * - Conexão com o banco ($db)
 * - Funções auxiliares: responder(), erro(), lerJson(), exigirCampos(), usuarioAutenticado()
 */

header("Content-Type: application/json; charset=UTF-8");
header("Access-Control-Allow-Origin: *");
header("Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS");
header("Access-Control-Allow-Headers: Content-Type, Authorization");

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

require_once __DIR__ . '/database.php';

// Qualquer erro não tratado vira um JSON com status 500 (o app sempre recebe JSON).
set_exception_handler(function (Throwable $e) {
    error_log($e);
    http_response_code(500);
    echo json_encode(["error" => "Erro interno do servidor"], JSON_UNESCAPED_UNICODE);
    exit;
});

$database = new Database();
$db = $database->getConnection();
if (!$db) {
    http_response_code(500);
    echo json_encode(["error" => "Não foi possível conectar ao banco de dados"], JSON_UNESCAPED_UNICODE);
    exit;
}
$db->setAttribute(PDO::ATTR_DEFAULT_FETCH_MODE, PDO::FETCH_ASSOC);
// Faz o MySQL devolver números como números (e não strings) no JSON.
$db->setAttribute(PDO::ATTR_EMULATE_PREPARES, false);
$db->setAttribute(PDO::ATTR_STRINGIFY_FETCHES, false);

$method = $_SERVER['REQUEST_METHOD'];

function responder($dados, int $status = 200): void {
    http_response_code($status);
    echo json_encode($dados, JSON_UNESCAPED_UNICODE | JSON_PRESERVE_ZERO_FRACTION);
    exit;
}

function erro(string $mensagem, int $status = 400): void {
    responder(["error" => $mensagem], $status);
}

/** Lê o corpo JSON da requisição como array associativo. */
function lerJson(): array {
    $raw = file_get_contents("php://input");
    if ($raw === '' || $raw === false) {
        return [];
    }
    $data = json_decode($raw, true);
    if (!is_array($data)) {
        erro("JSON inválido");
    }
    return $data;
}

function exigirCampos(array $data, array $campos): void {
    foreach ($campos as $campo) {
        if (!isset($data[$campo]) || (is_string($data[$campo]) && trim($data[$campo]) === '')) {
            erro("Campo obrigatório: $campo");
        }
    }
}

function somenteDigitos(?string $valor): ?string {
    if ($valor === null) return null;
    $d = preg_replace('/\D/', '', $valor);
    return $d === '' ? null : $d;
}

/** Gera um token aleatório, salva no banco e devolve. */
function gerarToken(PDO $db, int $usuarioId): string {
    $token = bin2hex(random_bytes(32));
    $stmt = $db->prepare(
        "INSERT INTO tokens (usuario_id, token, expira_em)
         VALUES (:uid, :token, DATE_ADD(NOW(), INTERVAL 30 DAY))"
    );
    $stmt->execute([':uid' => $usuarioId, ':token' => $token]);
    return $token;
}

function tokenDaRequisicao(): ?string {
    $header = $_SERVER['HTTP_AUTHORIZATION']
        ?? $_SERVER['REDIRECT_HTTP_AUTHORIZATION']
        ?? (function_exists('apache_request_headers') ? (apache_request_headers()['Authorization'] ?? null) : null);
    if ($header && preg_match('/Bearer\s+(\S+)/i', $header, $m)) {
        return $m[1];
    }
    return null;
}

/** Retorna o usuário logado (pelo header Authorization: Bearer <token>) ou responde 401. */
function usuarioAutenticado(PDO $db): array {
    $token = tokenDaRequisicao();
    if (!$token) {
        erro("Não autenticado", 401);
    }
    $stmt = $db->prepare(
        "SELECT u.* FROM tokens t JOIN usuarios u ON u.id = t.usuario_id
         WHERE t.token = :token AND t.expira_em > NOW()"
    );
    $stmt->execute([':token' => $token]);
    $usuario = $stmt->fetch();
    if (!$usuario) {
        erro("Sessão expirada, faça login novamente", 401);
    }
    return $usuario;
}

/** Dados públicos do usuário (sem senha), com o plano aninhado. */
function perfilUsuario(PDO $db, int $usuarioId): array {
    $stmt = $db->prepare(
        "SELECT u.id, u.nome, u.email, u.cpf, u.saldo, u.pontos, u.plano_id, u.criado_em,
                p.tipo AS plano_tipo, p.nome AS plano_nome
         FROM usuarios u LEFT JOIN planos p ON p.id = u.plano_id
         WHERE u.id = :id"
    );
    $stmt->execute([':id' => $usuarioId]);
    $u = $stmt->fetch();
    if (!$u) {
        erro("Usuário não encontrado", 404);
    }
    $u['saldo'] = (float) $u['saldo'];
    $u['numero_cadastro'] = str_pad((string) $u['id'], 4, '0', STR_PAD_LEFT);
    return $u;
}

/** Registra crédito/débito na carteira e atualiza o saldo. Use dentro de uma transação. */
function movimentarCarteira(PDO $db, int $usuarioId, string $tipo, float $valor, string $descricao): void {
    if ($tipo === 'debito') {
        $stmt = $db->prepare("SELECT saldo FROM usuarios WHERE id = :id FOR UPDATE");
        $stmt->execute([':id' => $usuarioId]);
        if ((float) $stmt->fetchColumn() < $valor) {
            throw new DomainException("Saldo insuficiente");
        }
    }
    $sinal = $tipo === 'credito' ? '+' : '-';
    $db->prepare("UPDATE usuarios SET saldo = saldo $sinal :valor WHERE id = :id")
       ->execute([':valor' => $valor, ':id' => $usuarioId]);
    $db->prepare(
        "INSERT INTO carteira_transacoes (usuario_id, tipo, valor, descricao)
         VALUES (:uid, :tipo, :valor, :descricao)"
    )->execute([':uid' => $usuarioId, ':tipo' => $tipo, ':valor' => $valor, ':descricao' => $descricao]);
}

/** Soma (ou subtrai, se negativo) pontos do usuário. Use dentro de uma transação. */
function movimentarPontos(PDO $db, int $usuarioId, int $pontos, string $motivo): void {
    if ($pontos < 0) {
        $stmt = $db->prepare("SELECT pontos FROM usuarios WHERE id = :id FOR UPDATE");
        $stmt->execute([':id' => $usuarioId]);
        if ((int) $stmt->fetchColumn() < -$pontos) {
            throw new DomainException("Pontos insuficientes");
        }
    }
    $db->prepare("UPDATE usuarios SET pontos = pontos + :p WHERE id = :id")
       ->execute([':p' => $pontos, ':id' => $usuarioId]);
    $db->prepare("INSERT INTO pontos_transacoes (usuario_id, pontos, motivo) VALUES (:uid, :p, :m)")
       ->execute([':uid' => $usuarioId, ':p' => $pontos, ':m' => $motivo]);
}

/**
 * Executa $fn dentro de uma transação. DomainException vira erro 400/409 com a mensagem.
 */
function emTransacao(PDO $db, callable $fn) {
    $db->beginTransaction();
    try {
        $resultado = $fn();
        $db->commit();
        return $resultado;
    } catch (DomainException $e) {
        $db->rollBack();
        erro($e->getMessage(), 409);
    } catch (Throwable $e) {
        $db->rollBack();
        throw $e;
    }
}

/** Formata um jogo (linha do SELECT padrão de jogos) com os clubes aninhados. */
function formatarJogo(array $j): array {
    return [
        "id"             => (int) $j['id'],
        "rodada"         => $j['rodada'],
        "data_hora"      => $j['data_hora'],
        "estadio"        => $j['estadio'],
        "status"         => $j['status'],
        "gols_mandante"  => $j['gols_mandante'] !== null ? (int) $j['gols_mandante'] : null,
        "gols_visitante" => $j['gols_visitante'] !== null ? (int) $j['gols_visitante'] : null,
        "campeonato"     => [
            "id"        => (int) $j['campeonato_id'],
            "nome"      => $j['campeonato_nome'],
            "temporada" => (int) $j['campeonato_temporada'],
        ],
        "mandante" => [
            "id"             => (int) $j['mandante_id'],
            "nome"           => $j['mandante_nome'],
            "sigla"          => $j['mandante_sigla'],
            "escudo_url"     => $j['mandante_escudo'],
        ],
        "visitante" => [
            "id"             => (int) $j['visitante_id'],
            "nome"           => $j['visitante_nome'],
            "sigla"          => $j['visitante_sigla'],
            "escudo_url"     => $j['visitante_escudo'],
        ],
    ];
}

const SQL_JOGOS = "
    SELECT j.*, c.nome AS campeonato_nome, c.temporada AS campeonato_temporada,
           m.nome AS mandante_nome, m.sigla AS mandante_sigla, m.escudo_url AS mandante_escudo,
           v.nome AS visitante_nome, v.sigla AS visitante_sigla, v.escudo_url AS visitante_escudo
    FROM jogos j
    JOIN campeonatos c ON c.id = j.campeonato_id
    JOIN clubes m ON m.id = j.mandante_id
    JOIN clubes v ON v.id = j.visitante_id
";
