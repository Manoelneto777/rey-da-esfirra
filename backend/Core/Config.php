<?php
/**
 * backend/Core/Config.php
 * Configuracoes centralizadas do sistema.
 */

namespace Core;

class Config
{
    // Banco de Dados
    public const DB_HOST    = 'localhost';
    public const DB_NAME    = 'chatbot'; 
    public const DB_USER    = 'root';   
    public const DB_PASS    = '';       
    public const DB_PORT    = '3307'; 
    public const DB_CHARSET = 'utf8mb4';

    // OpenRouter (IA)
    public const OPENROUTER_API_KEY  =''; //adicone a chave aqui 
    public const OPENROUTER_API_URL  = 'https://openrouter.ai/api/v1/chat/completions';
    public const OPENROUTER_MODEL = 'openrouter/free';

    // Chatbot
    public const CHATBOT_USE_AI     = true;
    public const CHATBOT_MAX_TOKENS = 500;

    // Prompt de sistema
    public const CHATBOT_SYSTEM_PROMPT = 'Voce e o Rei Bot, assistente virtual da lanchonete Rei da Esfirra em Capoeirucu - BA. Responda de forma simpatica, curta e objetiva em portugues. Horario: Seg-Dom 13h-22h30. Endereco: Capoeirucu - BA. Tel: (75) 99999-0000. Delivery 30-50min, taxa R$5, gratis acima R$60. Pagamento: PIX, Dinheiro, Debito, Credito. Seja breve (max 3 linhas) e use emojis ocasionalmente.';

    // Aplicacao
    public const APP_DEBUG = true;
}