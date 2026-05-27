-- ═══════════════════════════════════════════════════════════
-- REI DA ESFIRRA — database/estrutura.sql
-- Importar no phpMyAdmin (cria o banco do zero, já pronto).
-- ═══════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS reydaesfirra_chatbot
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE reydaesfirra_chatbot;

-- ─── produtos ─────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS produtos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome VARCHAR(120) NOT NULL,
  descricao TEXT,
  preco DECIMAL(8,2) NOT NULL,
  categoria VARCHAR(60) NOT NULL,
  imagem VARCHAR(100) DEFAULT '',
  disponivel TINYINT(1) NOT NULL DEFAULT 1,
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_categoria (categoria),
  INDEX idx_disponivel (disponivel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO produtos (nome, descricao, preco, categoria, imagem) VALUES
('Esfirra de Carne',       'Carne moida temperada com cebola, tomate e ervas finas',               7.50,  'Tradicionais', 'carne.webp'),
('Esfirra de Frango',      'Frango desfiado com catupiry cremoso e ervas especiais',               7.50,  'Tradicionais', 'frango.webp'),
('Esfirra de Queijo',      'Mussarela derretida com oregano classica e irresistivel',              6.50,  'Tradicionais', 'queijo.webp'),
('Esfirra de Calabresa',   'Calabresa artesanal com cebola caramelizada e pimentao',               8.00,  'Tradicionais', 'calabresa.webp'),
('Esfirra de Palmito',     'Palmito pupunha cremoso com temperos especiais da casa',               8.50,  'Especiais',    'palmito.webp'),
('Esfirra de Atum',        'Atum especial com azeitona preta e cebola roxa',                       9.00,  'Especiais',    'atum.webp'),
('Esfirra de Camarao',     'Camarao refogado no alho e azeite com pimentao vermelho',              12.00, 'Premium',      'camarao.webp'),
('Esfirra Doce de Banana', 'Banana caramelizada com canela e leite condensado',                    7.00,  'Doces',        'banana.webp'),
('Esfirra de Chocolate',   'Chocolate meio amargo cremoso com granulado especial',                 7.50,  'Doces',        'chocolate.webp'),
('Combo 10 un. Carne',     'Dez esfirras de carne com desconto especial da casa',                  65.00, 'Combos',       'combo.webp'),
('Combo 20 Mix',           'Vinte esfirras mistas a sua escolha melhor custo-beneficio',           120.00,'Combos',       'combo-mix.webp'),
('Refrigerante Lata',      'Coca-Cola Guarana ou Sprite bem gelados 350ml',                        5.00,  'Bebidas',      'refri.webp'),
('Suco Natural 300ml',     'Laranja Limao ou Maracuja feito na hora',                              8.00,  'Bebidas',      'suco.webp'),
('Esfirra de Chocolate',       'Chocolate com morango',               5.99,  'Doces', 'chocalatemorango.webp'),
('Esfirra de Peito de Peru c/ Queijo',       'Peito de peru com Queijo',               5.49,  'Especiais', 'peruqueijo.webp'),
('Esfirra de Carne Seca c/ cream cheese',       'Carne seca e cream cheese',               5.99,  'Especiais', 'carnesecacream.webp'),
('Esfirra de Bacon',       'Bacon com queijo e catupiry',               5.99,  'Especiais', 'bacon.webp'),
('Áçai',       'Áçai copo 300ml',               14.90,  'Doces', 'acai.webp'),
('Água',       'Água Mineral sem gás 500ml',               4.00,  'Bebidas', 'agua.webp'),
('Coca Cola',       'Coca Cola 1L',              10.00,  'Bebidas', 'cocacola.webp'),
('Suco Del Valle - Uva',       'Del Valle - Uva 450ml',              7.00,  'Bebidas', 'valleuva.webp');

-- ─── pedidos ──────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pedidos (
  id INT AUTO_INCREMENT PRIMARY KEY,
  nome_cliente VARCHAR(120) NOT NULL,
  telefone VARCHAR(20) NOT NULL,
  endereco VARCHAR(255) NOT NULL,
  observacoes TEXT,
  total DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status ENUM('novo','confirmado','em_preparo','saiu','entregue','cancelado') NOT NULL DEFAULT 'novo',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status (status),
  INDEX idx_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

CREATE TABLE IF NOT EXISTS pedido_itens (
  id INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id INT NOT NULL,
  produto_id INT NOT NULL,
  quantidade SMALLINT NOT NULL DEFAULT 1,
  preco DECIMAL(8,2) NOT NULL,
  INDEX idx_pedido_id (pedido_id),
  INDEX idx_produto_id (produto_id),
  CONSTRAINT fk_item_pedido FOREIGN KEY (pedido_id) REFERENCES pedidos(id) ON DELETE CASCADE,
  CONSTRAINT fk_item_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Pedido de teste
INSERT INTO pedidos (nome_cliente, telefone, endereco, observacoes, total, status) VALUES
('Lucas T.', '(75) 99999-0000', 'Rua Principal, 123', 'Sem cebola', 15.00, 'novo');

INSERT INTO pedido_itens (pedido_id, produto_id, quantidade, preco) VALUES
(LAST_INSERT_ID(), 1, 2, 7.50);

-- ─── chat_conversations ───────────────────────────────────
CREATE TABLE IF NOT EXISTS chat_conversations (
  id INT AUTO_INCREMENT PRIMARY KEY,
  usuario_id INT DEFAULT NULL,
  status ENUM('ativa','finalizada') NOT NULL DEFAULT 'ativa',
  origem VARCHAR(50) DEFAULT 'web',
  criado_em DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_usuario_id (usuario_id),
  INDEX idx_status (status)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

INSERT INTO chat_conversations (usuario_id, status, origem) VALUES (NULL, 'ativa', 'web');

SET @conversation_id = LAST_INSERT_ID();

-- ─── chat_messages ────────────────────────────────────────
-- tipo já nasce com 'ai' permitido (antes era só 'user','bot' e a
-- gravação de respostas da IA falhava em modo estrito).
CREATE TABLE IF NOT EXISTS chat_messages (
  id INT AUTO_INCREMENT PRIMARY KEY,
  conversation_id INT NOT NULL,
  usuario_id INT DEFAULT NULL,
  mensagem TEXT NOT NULL,
  tipo ENUM('user','bot','ai') NOT NULL,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_conversation_id (conversation_id),
  INDEX idx_tipo (tipo),
  CONSTRAINT fk_chat_messages_conversation FOREIGN KEY (conversation_id) REFERENCES chat_conversations(id) ON DELETE CASCADE
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Mensagens de teste
INSERT INTO chat_messages (conversation_id, usuario_id, mensagem, tipo) VALUES
(@conversation_id, NULL, 'Olá! Bem-vindo ao Rei da Esfirra!', 'bot'),
(@conversation_id, NULL, 'Olá, quero ver o cardápio', 'user');

-- ─── chatbot_options ──────────────────────────────────────
-- Base de conhecimento do fluxo MANUAL.
-- A coluna `intencao` é única (uq_intencao) para evitar duplicatas.
-- Os valores de `intencao` precisam casar com o que detectarIntencao()
-- devolve: minúsculas e sem acento (cardapio, horarios, ...).
CREATE TABLE IF NOT EXISTS chatbot_options (
  id INT AUTO_INCREMENT PRIMARY KEY,
  intencao VARCHAR(100) NOT NULL,
  resposta TEXT NOT NULL,
  ativo TINYINT(1) NOT NULL DEFAULT 1,
  created_at DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  UNIQUE KEY uq_intencao (intencao),
  INDEX idx_ativo (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- Respostas manuais (o '\n' vira quebra de linha; o front converte em <br>)
INSERT INTO chatbot_options (intencao, resposta, ativo) VALUES
('cardapio',
 '👑 *Nosso Cardápio*\n\n🍕 *Tradicionais:* a partir de R$ 6,50 (Carne, Frango, Queijo, Calabresa)\n✨ *Especiais:* a partir de R$ 8,50 (Palmito, Atum)\n🍤 *Premium:* R$ 12,00 (Camarão)\n🍫 *Doces:* a partir de R$ 7,00 (Banana, Chocolate)\n🍟 *Combos:* 10 un. R$ 65,00 · 20 un. Mix R$ 120,00\n\nQual sabor vai matar sua fome hoje?',
 1),
('horarios',
 '⏰ *Horários*\n\nSeg a Qui: 13h às 22:30h\nSex e Sáb: 13h às 23h\nDomingo: 13h às 22h',
 1),
('localizacao',
 '📍 *Onde estamos*\n\nAvenida Principal, 580 — Centro\nCapoeiruçu — Bahia',
 1),
('precos',
 '💰 *Preços base*\n\nEsfirras a partir de R$ 6,50.\nRefrigerantes a partir de R$ 5,00.\nToque em *Cardápio* para ver tudo!',
 1),
('delivery',
 '🛵 *Delivery*\n\nTempo médio: 30 a 50 min.\nTaxa: R$ 5,00 (grátis acima de R$ 60).\nPedido mínimo: R$ 25,00.',
 1);
