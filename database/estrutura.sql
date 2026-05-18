-- ═══════════════════════════════════════════════════════════
-- REI DA ESFIRRA — database/estrutura.sql
-- Execute no phpMyAdmin: Importar > selecionar este arquivo
-- ═══════════════════════════════════════════════════════════

CREATE DATABASE IF NOT EXISTS Chatbot
  CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;

USE Chatbot;

-- ─── produtos ───────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS produtos (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  nome       VARCHAR(120)  NOT NULL,
  descricao  TEXT,
  preco      DECIMAL(8,2)  NOT NULL,
  categoria  VARCHAR(60)   NOT NULL,
  imagem     VARCHAR(100)  DEFAULT '',
  disponivel TINYINT(1)    NOT NULL DEFAULT 1,
  criado_em  DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_categoria  (categoria),
  INDEX idx_disponivel (disponivel)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── pedidos ────────────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pedidos (
  id           INT AUTO_INCREMENT PRIMARY KEY,
  nome_cliente VARCHAR(120)  NOT NULL,
  telefone     VARCHAR(20)   NOT NULL,
  endereco     VARCHAR(255)  NOT NULL,
  observacoes  TEXT,
  total        DECIMAL(10,2) NOT NULL DEFAULT 0.00,
  status       ENUM('novo','confirmado','em_preparo','saiu','entregue','cancelado') NOT NULL DEFAULT 'novo',
  criado_em    DATETIME      NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_status    (status),
  INDEX idx_criado_em (criado_em)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── pedido_itens ───────────────────────────────────────────
CREATE TABLE IF NOT EXISTS pedido_itens (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  pedido_id  INT           NOT NULL,
  produto_id INT           NOT NULL,
  quantidade SMALLINT      NOT NULL DEFAULT 1,
  preco      DECIMAL(8,2)  NOT NULL,
  INDEX idx_pedido_id  (pedido_id),
  INDEX idx_produto_id (produto_id),
  CONSTRAINT fk_item_pedido  FOREIGN KEY (pedido_id)  REFERENCES pedidos(id)  ON DELETE CASCADE,
  CONSTRAINT fk_item_produto FOREIGN KEY (produto_id) REFERENCES produtos(id) ON DELETE RESTRICT
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── chat_messages ──────────────────────────────────────────
-- Histórico de todas as mensagens do chatbot
CREATE TABLE IF NOT EXISTS chat_messages (
  id         INT AUTO_INCREMENT PRIMARY KEY,
  message    TEXT         NOT NULL,
  sender     ENUM('user','bot','ai') NOT NULL DEFAULT 'user',
  sessao_id  VARCHAR(60)  DEFAULT NULL,
  created_at DATETIME     NOT NULL DEFAULT CURRENT_TIMESTAMP,
  INDEX idx_sender     (sender),
  INDEX idx_sessao     (sessao_id),
  INDEX idx_created_at (created_at)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── chatbot_options ────────────────────────────────────────
-- Base de conhecimento: palavra-chave → resposta
CREATE TABLE IF NOT EXISTS chatbot_options (
  id       INT AUTO_INCREMENT PRIMARY KEY,
  keyword  VARCHAR(120) NOT NULL COMMENT 'Palavra-chave detectada na mensagem',
  response TEXT         NOT NULL COMMENT 'Resposta retornada ao usuario',
  ativo    TINYINT(1)   NOT NULL DEFAULT 1,
  INDEX idx_keyword (keyword),
  INDEX idx_ativo   (ativo)
) ENGINE=InnoDB DEFAULT CHARSET=utf8mb4;

-- ─── Produtos de exemplo ────────────────────────────────────
INSERT INTO produtos (nome, descricao, preco, categoria, imagem) VALUES
('Esfirra de Carne',       'Carne moida temperada com cebola, tomate e ervas finas',               7.50,  'Tradicionais', 'carne.svg'),
('Esfirra de Frango',      'Frango desfiado com catupiry cremoso e ervas especiais',               7.50,  'Tradicionais', 'frango.svg'),
('Esfirra de Queijo',      'Mussarela derretida com oregano classica e irresistivel',              6.50,  'Tradicionais', 'queijo.svg'),
('Esfirra de Calabresa',   'Calabresa artesanal com cebola caramelizada e pimentao',               8.00,  'Tradicionais', 'calabresa.svg'),
('Esfirra de Palmito',     'Palmito pupunha cremoso com temperos especiais da casa',               8.50,  'Especiais',    'palmito.svg'),
('Esfirra de Atum',        'Atum especial com azeitona preta e cebola roxa',                       9.00,  'Especiais',    'atum.svg'),
('Esfirra de Camarao',     'Camarao refogado no alho e azeite com pimentao vermelho',              12.00, 'Premium',      'camarao.svg'),
('Esfirra Doce de Banana', 'Banana caramelizada com canela e leite condensado',                    7.00,  'Doces',        'banana.svg'),
('Esfirra de Chocolate',   'Chocolate meio amargo cremoso com granulado especial',                 7.50,  'Doces',        'chocolate.svg'),
('Combo 10 un. Carne',     'Dez esfirras de carne com desconto especial da casa',                  65.00, 'Combos',       'combo.svg'),
('Combo 20 Mix',           'Vinte esfirras mistas a sua escolha melhor custo-beneficio',           120.00,'Combos',       'combo-mix.svg'),
('Refrigerante Lata',      'Coca-Cola Guarana ou Sprite bem gelados 350ml',                        5.00,  'Bebidas',      'refri.svg'),
('Suco Natural 300ml',     'Laranja Limao ou Maracuja feito na hora',                              8.00,  'Bebidas',      'suco.svg');

-- ─── Palavras-chave do chatbot ──────────────────────────────
INSERT INTO chatbot_options (keyword, response) VALUES
('oi',        'Ola! Bem-vindo ao Rei da Esfirra! Como posso te ajudar?\nCardapio | Horarios | Localizacao | Precos'),
('ola',       'Ola! Bem-vindo ao Rei da Esfirra! Como posso te ajudar?\nCardapio | Horarios | Localizacao | Precos'),
('bom dia',   'Bom dia! Que bom ter voce aqui. Posso ajudar com cardapio, precos ou fazer um pedido?'),
('boa tarde', 'Boa tarde! Pronto para pedir suas esfirras favoritas?'),
('boa noite', 'Boa noite! Ainda estamos abertos com esfirras quentinhas saindo do forno!'),
('cardapio',  'Nosso Cardapio:\nTradicionais a partir de R$ 6,50: Carne, Frango, Queijo, Calabresa\nEspeciais: Palmito, Atum\nPremium: Camarao R$ 12,00\nDoces: Banana, Chocolate\nCombos com desconto\nBebidas a partir de R$ 5,00'),
('menu',      'Temos esfirras Tradicionais, Especiais, Premium, Doces, Combos e Bebidas! Veja o cardapio na pagina.'),
('esfirra',   'Temos esfirras de Carne, Frango, Queijo, Calabresa, Palmito, Atum, Camarao, Banana e Chocolate. Qual prefere?'),
('preco',     'Precos: Tradicionais R$ 6,50+ | Especiais R$ 8,50+ | Premium R$ 12,00 | Combos com desconto!'),
('valor',     'Valores: Tradicionais R$ 6,50+, Especiais R$ 8,50+, Premium R$ 12,00, Combos com desconto!'),
('quanto',    'Esfirras a partir de R$ 6,50. Quer saber o preco de algum sabor especifico?'),
('horario',   'Horarios: Seg-Dom das 13h as 22h30. Abertos todos os dias!'),
('abre',      'Abrimos as 13h todos os dias, ate as 22h30!'),
('fecha',     'Fechamos as 22h30 todos os dias.'),
('endereco',  'Estamos em Capoeirucu, Bahia. Tel: (75) 99999-0000. Fazemos delivery!'),
('onde',      'Ficamos em Capoeirucu - BA! Delivery disponivel ou pode buscar no local.'),
('delivery',  'Delivery disponivel! Prazo: 30-50 minutos. Taxa a partir de R$ 5,00. Gratis acima de R$ 60,00. Minimo: R$ 25,00.'),
('entrega',   'Fazemos entrega em toda a regiao! Taxa a partir de R$ 5,00, gratis acima de R$ 60.'),
('frete',     'Frete a partir de R$ 5,00. Gratis em pedidos acima de R$ 60,00!'),
('pagamento', 'Aceitamos: PIX, Dinheiro, Cartao de Debito e Credito (todas as bandeiras)!'),
('pix',       'Sim! Aceitamos PIX. Chave: (75) 99999-0000.'),
('cartao',    'Aceitamos cartao de debito e credito de todas as bandeiras!'),
('recomenda', 'Mais pedidos: 1o Esfirra de Carne | 2o Frango com Catupiry | 3o Combo 20 Mix. Experimente o Camarao!'),
('melhor',    'A mais pedida e a Esfirra de Carne! Mas o Combo 20 Mix tem o melhor custo-beneficio.'),
('promocao',  'Combos com desconto e frete gratis acima de R$ 60! Siga nossas redes sociais!'),
('desconto',  'Combos com desconto especial! O Combo 20 Mix e o que tem melhor desconto por unidade.'),
('whatsapp',  'Nosso WhatsApp: (75) 99999-0000. Clique no botao verde na tela!'),
('telefone',  'Nosso telefone: (75) 99999-0000. Pode ligar ou mandar mensagem!'),
('tchau',     'Tchau! Foi um prazer te atender. Volte sempre ao Rei da Esfirra!'),
('obrigado',  'Nos que agradecemos! Qualquer duvida, e so chamar. Bom apetite!'),
('valeu',     'Valeu mesmo! Estamos a disposicao. Volte sempre!');
