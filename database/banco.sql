-- ═══════════════════════════════════════════════════
-- REI DA ESFIRRA — banco.sql
-- Execute no phpMyAdmin: Importar > selecionar este arquivo
-- ═══════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS reydaesfirra_chatbot
  CHARACTER SET utf8mb4
  COLLATE utf8mb4_unicode_ci;

USE reydaesfirra_chatbot;

-- ─── Tabela: produtos ───────────────────────────
CREATE TABLE IF NOT EXISTS produtos (
  id          INT           NOT NULL AUTO_INCREMENT,
  nome        VARCHAR(120)  NOT NULL,
  descricao   TEXT,
  preco       DECIMAL(8,2)  NOT NULL,
  categoria   VARCHAR(60)   NOT NULL,
  imagem      VARCHAR(100)  DEFAULT '',
  disponivel  TINYINT(1)    NOT NULL DEFAULT 1,
  criado_em   DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_categoria  (categoria),
  INDEX idx_disponivel (disponivel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Tabela: pedidos ────────────────────────────
CREATE TABLE IF NOT EXISTS pedidos (
  id             INT           NOT NULL AUTO_INCREMENT,
  nome_cliente   VARCHAR(120)  NOT NULL,
  telefone       VARCHAR(20)   NOT NULL,
  endereco       VARCHAR(255)  NOT NULL,
  observacoes    TEXT,
  total          DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status         ENUM('novo','confirmado','em_preparo','saiu','entregue','cancelado')
                               NOT NULL DEFAULT 'novo',
  criado_em      DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id),
  INDEX idx_status    (status),
  INDEX idx_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Tabela: pedido_itens ───────────────────────
CREATE TABLE IF NOT EXISTS pedido_itens (
  id          INT           NOT NULL AUTO_INCREMENT,
  pedido_id   INT           NOT NULL,
  produto_id  INT           NOT NULL,
  quantidade  SMALLINT      NOT NULL DEFAULT 1,
  preco       DECIMAL(8,2)  NOT NULL,
  PRIMARY KEY (id),
  INDEX idx_pedido_id  (pedido_id),
  INDEX idx_produto_id (produto_id),
  CONSTRAINT fk_item_pedido  FOREIGN KEY (pedido_id)  REFERENCES pedidos(id)  ON DELETE CASCADE,
  CONSTRAINT fk_item_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Tabela: chatbot_logs ───────────────────────
CREATE TABLE IF NOT EXISTS chatbot_logs (
  id               INT  NOT NULL AUTO_INCREMENT,
  sessao_id        VARCHAR(40),
  mensagem_usuario TEXT,
  resposta_bot     TEXT,
  criado_em        DATETIME NOT NULL DEFAULT CURRENT_TIMESTAMP,
  PRIMARY KEY (id)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Produtos de exemplo ────────────────────────
INSERT INTO produtos (nome, descricao, preco, categoria, imagem) VALUES
('Esfirra de Carne',       'Carne moída temperada com cebola, tomate e ervas finas',               7.50,  'Tradicionais', 'carne.webp'),
('Esfirra de Frango',      'Frango desfiado com catupiry cremoso e ervas especiais',               7.50,  'Tradicionais', 'frango.webp'),
('Esfirra de Queijo',      'Mussarela derretida com orégano — clássica e irresistível',            6.50,  'Tradicionais', 'queijo.webp'),
('Esfirra de Calabresa',   'Calabresa artesanal com cebola caramelizada e pimentão',               8.00,  'Tradicionais', 'calabresa.webp'),
('Esfirra de Palmito',     'Palmito pupunha cremoso com temperos especiais da casa',               8.50,  'Especiais',    'palmito.webp'),
('Esfirra de Atum',        'Atum especial com azeitona preta e cebola roxa',                       9.00,  'Especiais',    'atum.webp'),
('Esfirra de Camarão',     'Camarão refogado no alho e azeite com pimentão vermelho',              12.00, 'Premium',      'camarao.webp'),
('Esfirra Doce de Banana', 'Banana caramelizada com canela e leite condensado',                    7.00,  'Doces',        'banana.webp'),
('Esfirra de Chocolate',   'Chocolate meio amargo cremoso com granulado especial',                 7.50,  'Doces',        'chocolate.webp'),
('Combo 10 un. Carne',     'Dez esfirras de carne com desconto especial da casa',                  65.00, 'Combos',       'combo.webp'),
('Combo 20 Mix',           'Vinte esfirras mistas à sua escolha — melhor custo-benefício',         120.00,'Combos',       'combo-mix.webp'),
('Refrigerante Lata',      'Coca-Cola, Guaraná ou Sprite bem gelados — 350ml',                     5.00,  'Bebidas',      'refri.webp'),
('Suco Natural 300ml',     'Laranja, Limão ou Maracujá feito na hora',                             8.00,  'Bebidas',      'suco.webp');
