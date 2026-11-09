DROP DATABASE IF EXISTS sociotorcedor;
CREATE DATABASE sociotorcedor CHARACTER SET utf8mb4;
USE sociotorcedor;

-- ===== PLANOS =====
CREATE TABLE planos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  tipo VARCHAR(50) NOT NULL UNIQUE,
  nome VARCHAR(50) NOT NULL,
  cor VARCHAR(20),
  preco VARCHAR(20),
  parcelas VARCHAR(50),
  especificacoes TEXT,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP
);

CREATE TABLE plano_diferenciais (
  id INT AUTO_INCREMENT PRIMARY KEY,
  plano_id INT NOT NULL,
  titulo VARCHAR(150) NOT NULL,
  descricao VARCHAR(200),
  ordem INT DEFAULT 0,
  FOREIGN KEY (plano_id) REFERENCES planos(id)
);

-- ===== USUÁRIOS =====
CREATE TABLE usuarios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  email VARCHAR(100) UNIQUE NOT NULL,
  cpf VARCHAR(14) NULL UNIQUE,
  google_id VARCHAR(100) NULL UNIQUE,
  senha VARCHAR(255) NULL,
  plano_id INT NULL,
  saldo DECIMAL(10,2) NOT NULL DEFAULT 0,
  pontos INT NOT NULL DEFAULT 0,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (plano_id) REFERENCES planos(id)
);

CREATE TABLE assinaturas (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  plano_id INT NOT NULL,
  status ENUM('pendente','ativa','cancelada') NOT NULL DEFAULT 'pendente',
  data_inicio DATE,
  data_fim DATE,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  FOREIGN KEY (plano_id) REFERENCES planos(id)
);

-- ===== CLUBES, CAMPEONATOS, JOGOS, CLASSIFICAÇÃO =====
CREATE TABLE clubes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  sigla VARCHAR(5) NOT NULL,
  escudo_url VARCHAR(255),
  cor_primaria VARCHAR(9),
  cor_secundaria VARCHAR(9)
);

CREATE TABLE campeonatos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(100) NOT NULL,
  temporada SMALLINT NOT NULL
);

CREATE TABLE jogos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  campeonato_id INT NOT NULL,
  rodada VARCHAR(50),
  mandante_id INT NOT NULL,
  visitante_id INT NOT NULL,
  data_hora DATETIME NOT NULL,
  estadio VARCHAR(100),
  gols_mandante INT NULL,
  gols_visitante INT NULL,
  status ENUM('agendado','ao_vivo','encerrado') NOT NULL DEFAULT 'agendado',
  FOREIGN KEY (campeonato_id) REFERENCES campeonatos(id),
  FOREIGN KEY (mandante_id) REFERENCES clubes(id),
  FOREIGN KEY (visitante_id) REFERENCES clubes(id)
);

CREATE TABLE classificacao (
  id INT AUTO_INCREMENT PRIMARY KEY,
  campeonato_id INT NOT NULL,
  clube_id INT NOT NULL,
  posicao INT NOT NULL,
  pontos INT NOT NULL DEFAULT 0,
  jogos INT NOT NULL DEFAULT 0,
  vitorias INT NOT NULL DEFAULT 0,
  empates INT NOT NULL DEFAULT 0,
  derrotas INT NOT NULL DEFAULT 0,
  gols_pro INT NOT NULL DEFAULT 0,
  gols_contra INT NOT NULL DEFAULT 0,
  UNIQUE (campeonato_id, clube_id),
  FOREIGN KEY (campeonato_id) REFERENCES campeonatos(id),
  FOREIGN KEY (clube_id) REFERENCES clubes(id)
);

-- ===== ELENCO =====
CREATE TABLE jogadores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  clube_id INT NOT NULL,
  numero INT,
  nome VARCHAR(100) NOT NULL,
  posicao ENUM('goleiro','defensor','meia','atacante') NOT NULL,
  foto_url VARCHAR(255),
  data_nascimento DATE,
  local_nascimento VARCHAR(100),
  altura DECIMAL(3,2),
  peso DECIMAL(5,2) NULL,
  pe ENUM('direito','esquerdo','ambidestro'),
  historia TEXT,
  FOREIGN KEY (clube_id) REFERENCES clubes(id)
);

-- ===== INGRESSOS =====
CREATE TABLE setores (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(50) NOT NULL
);

CREATE TABLE ingressos_jogo (
  id INT AUTO_INCREMENT PRIMARY KEY,
  jogo_id INT NOT NULL,
  setor_id INT NOT NULL,
  preco DECIMAL(10,2) NOT NULL,
  preco_original DECIMAL(10,2),
  quantidade_total INT NOT NULL DEFAULT 0,
  quantidade_vendida INT NOT NULL DEFAULT 0,
  status ENUM('em_breve','disponivel','esgotado') NOT NULL DEFAULT 'em_breve',
  UNIQUE (jogo_id, setor_id),
  FOREIGN KEY (jogo_id) REFERENCES jogos(id),
  FOREIGN KEY (setor_id) REFERENCES setores(id)
);

CREATE TABLE ingressos_usuario (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  ingresso_jogo_id INT NOT NULL,
  fila VARCHAR(10),
  cadeira VARCHAR(10),
  status ENUM('pendente','confirmado','cancelado','utilizado') NOT NULL DEFAULT 'pendente',
  qr_code VARCHAR(100) UNIQUE,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  FOREIGN KEY (ingresso_jogo_id) REFERENCES ingressos_jogo(id)
);

-- ===== BENEFÍCIOS =====
CREATE TABLE beneficios (
  id INT AUTO_INCREMENT PRIMARY KEY,
  titulo VARCHAR(100) NOT NULL,
  descricao VARCHAR(255),
  desconto_percentual TINYINT NOT NULL,
  codigo VARCHAR(30) NOT NULL UNIQUE,
  valido_ate DATE,
  plano_minimo_id INT NULL,
  FOREIGN KEY (plano_minimo_id) REFERENCES planos(id)
);

-- ===== LANCHONETE =====
CREATE TABLE categorias_produto (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(50) NOT NULL
);

CREATE TABLE produtos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  categoria_id INT NOT NULL,
  nome VARCHAR(100) NOT NULL,
  descricao VARCHAR(255),
  preco DECIMAL(10,2) NOT NULL,
  pontos_resgate INT NULL,
  imagem_url VARCHAR(255),
  ativo BOOLEAN NOT NULL DEFAULT TRUE,
  FOREIGN KEY (categoria_id) REFERENCES categorias_produto(id)
);

CREATE TABLE pedidos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  total DECIMAL(10,2) NOT NULL,
  pontos_ganhos INT NOT NULL DEFAULT 0,
  status ENUM('pendente','pago','entregue','cancelado') NOT NULL DEFAULT 'pendente',
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

CREATE TABLE pedido_itens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  produto_id INT NOT NULL,
  quantidade INT NOT NULL DEFAULT 1,
  preco_unitario DECIMAL(10,2) NOT NULL,
  FOREIGN KEY (pedido_id) REFERENCES pedidos(id),
  FOREIGN KEY (produto_id) REFERENCES produtos(id)
);

CREATE TABLE resgates (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  produto_id INT NOT NULL,
  pontos_usados INT NOT NULL,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  FOREIGN KEY (produto_id) REFERENCES produtos(id)
);

-- ===== CARTEIRA E PONTOS =====
CREATE TABLE carteira_transacoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  tipo ENUM('credito','debito') NOT NULL,
  valor DECIMAL(10,2) NOT NULL,
  descricao VARCHAR(150),
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

CREATE TABLE pontos_transacoes (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  pontos INT NOT NULL,
  motivo VARCHAR(150),
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id)
);

-- ===== PALPITES ("APOSTE AQUI") =====
CREATE TABLE palpites (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT NOT NULL,
  jogo_id INT NOT NULL,
  gols_mandante INT NOT NULL,
  gols_visitante INT NOT NULL,
  pontos_apostados INT NOT NULL DEFAULT 0,
  criado_em TIMESTAMP DEFAULT CURRENT_TIMESTAMP,
  UNIQUE (usuario_id, jogo_id),
  FOREIGN KEY (usuario_id) REFERENCES usuarios(id),
  FOREIGN KEY (jogo_id) REFERENCES jogos(id)
);

-- ===== DADOS INICIAIS =====
INSERT INTO planos (tipo, nome, cor, preco, parcelas, especificacoes) VALUES
('RED', 'RED', 'tierRed', '119,99', '12X DE R$ 69,90',
 'Tenha direito a dois ingressos para todos os jogos, acompanhando o time de perto em todas as competições, sem preocupação; Aproveite descontos e vantagens exclusivas em comércios parceiros da região, como restaurantes, lojas, serviços e muito mais; Receba um copo personalizado e exclusivo, feito especialmente para quem faz parte da nossa torcida oficial; Participe de ações especiais, sorteios, promoções e eventos voltados apenas para sócios torcedores'),
('GOLD', 'GOLD', 'tierGold', '229,99', '12X DE R$ 119,90',
 'Tenha direito a quatro ingressos para todos os jogos, acompanhando o time de perto em todas as competições, sem preocupação; Aproveite descontos e vantagens exclusivas em comércios parceiros da região; Receba um copo personalizado e exclusivo; Participe de ações especiais, sorteios, promoções e eventos voltados apenas para sócios torcedores'),
('BLACK', 'BLACK', 'tierBlack', '339,99', '12X DE R$ 169,90',
 'Tenha direito a seis ingressos VIP para todos os jogos, com acesso ao camarote exclusivo; Aproveite todos os benefícios RED e GOLD mais acesso ao lounge VIP; Receba kit exclusivo com camiseta, cachecol e copo personalizado; Participe de meet & greet com jogadores do elenco');

INSERT INTO plano_diferenciais (plano_id, titulo, descricao, ordem) VALUES
((SELECT id FROM planos WHERE tipo = 'RED'), 'Ganhe 2 copos Exclusivos', 'Entrada exclusiva no WD', 1),
((SELECT id FROM planos WHERE tipo = 'RED'), 'Experiências exclusivas', 'Descontos de 5% em produtos da loja', 2),
((SELECT id FROM planos WHERE tipo = 'RED'), 'Camiseta Oficial', '1 ingresso no setor de área coberta', 3),
((SELECT id FROM planos WHERE tipo = 'GOLD'), 'Ganhe 4 copos Exclusivos', 'Entrada exclusiva no WD', 1),
((SELECT id FROM planos WHERE tipo = 'GOLD'), 'Experiências exclusivas', 'Descontos de 10% em produtos da loja', 2),
((SELECT id FROM planos WHERE tipo = 'GOLD'), 'Camiseta Oficial', '2 ingressos no setor de área coberta', 3),
((SELECT id FROM planos WHERE tipo = 'BLACK'), 'Kit VIP Exclusivo', 'Acesso ao Camarote VIP', 1),
((SELECT id FROM planos WHERE tipo = 'BLACK'), 'Meet & Greet jogadores', 'Descontos de 20% na loja', 2),
((SELECT id FROM planos WHERE tipo = 'BLACK'), '6 Ingressos por jogo', 'Estacionamento reservado', 3);

INSERT INTO clubes (id, nome, sigla, cor_primaria, cor_secundaria) VALUES
(1, 'Athletico Paranaense', 'CAP', '#C8102E', '#000000'),
(2, 'Coritiba', 'CFC', '#00553A', '#FFFFFF'),
(3, 'Flamengo', 'FLA', '#C8102E', '#000000'),
(4, 'Palmeiras', 'PAL', '#006437', '#FFFFFF'),
(5, 'Corinthians', 'COR', '#000000', '#FFFFFF');

INSERT INTO campeonatos (id, nome, temporada) VALUES
(1, 'Serie A - Brasileirão', 2026),
(2, 'Copa do Brasil - Oitavas', 2026);

INSERT INTO jogos (id, campeonato_id, rodada, mandante_id, visitante_id, data_hora, estadio, gols_mandante, gols_visitante, status) VALUES
(1, 1, 'Rodada 14', 1, 2, NOW(), 'Arena MRV', 0, 0, 'ao_vivo'),
(2, 1, 'Rodada 10', 1, 5, '2026-06-15 16:00:00', 'Arena MRV', 3, 1, 'encerrado'),
(3, 1, 'Rodada 14', 1, 3, '2027-02-07 18:00:00', 'Arena MRV', NULL, NULL, 'agendado');

INSERT INTO classificacao (campeonato_id, clube_id, posicao, pontos, jogos, vitorias, empates, derrotas, gols_pro, gols_contra) VALUES
(1, 3, 1, 70, 38, 20, 10, 8, 61, 42),
(1, 4, 2, 66, 38, 19, 9, 10, 60, 33),
(1, 1, 3, 60, 38, 17, 9, 12, 52, 40);

INSERT INTO jogadores (clube_id, numero, nome, posicao, data_nascimento, local_nascimento, altura, pe, historia) VALUES
(1, 4, 'Arthur Dias', 'defensor', '2000-10-20', 'Ubatuba, SP', 1.80, 'direito', 'Cresceu nas ruas de Ubatuba, onde a bola nunca desgrudava do pé.');

INSERT INTO setores (id, nome) VALUES (1, 'Setor Leste'), (2, 'Setor Norte'), (3, 'Área Coberta');

INSERT INTO ingressos_jogo (jogo_id, setor_id, preco, preco_original, quantidade_total, quantidade_vendida, status) VALUES
(3, 1, 45.00, 60.00, 500, 0, 'disponivel'),
(3, 3, 90.00, 120.00, 200, 200, 'esgotado');

INSERT INTO beneficios (titulo, descricao, desconto_percentual, codigo, valido_ate) VALUES
('Camisa Oficial', 'Desconto na camisa oficial na loja parceira', 20, 'SOCIO20CAM', '2027-06-30'),
('Produtos de Linha', 'Desconto em produtos selecionados da loja oficial', 15, 'SOCIO15PRO', '2027-05-31');

INSERT INTO categorias_produto (id, nome) VALUES (1, 'Lanches'), (2, 'Bebidas'), (3, 'Sobremesas');

INSERT INTO produtos (categoria_id, nome, preco, pontos_resgate) VALUES
(1, 'Hambúrguer', 24.90, 10),
(1, 'Pizza', 49.90, NULL),
(2, 'Suco', 8.50, NULL),
(3, 'Sorvete', 12.00, NULL),
(2, 'Água', 5.00, NULL);
