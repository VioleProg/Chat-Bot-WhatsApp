# 🤖 Sistema de Chatbot para Gestão de Assinaturas IPTV

Um sistema completo de chatbot automatizado para WhatsApp que gerencia assinaturas de IPTV, enviando lembretes de pagamento e notificações de vencimento automaticamente.

## ✨ Funcionalidades

- **Automação de Mensagens**: Envio automático de lembretes de pagamento
- **Gestão de Clientes**: Interface web para cadastro e gerenciamento de clientes
- **Notificações Inteligentes**: Sistema de alertas baseado em datas de vencimento
- **Interface Web Responsiva**: Painel administrativo moderno e intuitivo
- **Integração WhatsApp**: Conexão direta com WhatsApp Web
- **Banco de Dados MySQL**: Armazenamento seguro de dados dos clientes

## 🚀 Tecnologias Utilizadas

- **Backend**: Node.js com Express
- **Frontend**: PHP, HTML, CSS, JavaScript
- **Banco de Dados**: MySQL
- **WhatsApp API**: whatsapp-web.js
- **QR Code**: qrcode-terminal
- **Processamento de Dados**: body-parser, moment-timezone

## 📋 Pré-requisitos

- Node.js (versão 14 ou superior)
- PHP (versão 7.4 ou superior)
- MySQL (versão 5.7 ou superior)
- Navegador web moderno
- Conta WhatsApp ativa

## 🔧 Instalação

### 1. Clone o repositório
```bash
git clone [URL_DO_SEU_REPOSITORIO]
cd BOT
```

### 2. Instale as dependências do Node.js
```bash
npm install
```

### 3. Configure o banco de dados MySQL
```sql
CREATE DATABASE whatsapp_bot;
USE whatsapp_bot;

CREATE TABLE clientes (
    id INT AUTO_INCREMENT PRIMARY KEY,
    nome VARCHAR(100) NOT NULL,
    sobrenome VARCHAR(100) NOT NULL,
    cpf VARCHAR(14) NOT NULL,
    email VARCHAR(100) NOT NULL,
    telefone VARCHAR(20) NOT NULL,
    data_inicio DATE NOT NULL,
    data_expiracao DATE NOT NULL,
    pago TINYINT(1) DEFAULT 0
);
```

### 4. Configure as credenciais do banco
Edite o arquivo `index.js` e `index.php` com suas credenciais do MySQL:
```javascript
const dbConfig = {
    host: 'localhost',
    user: 'seu_usuario',
    password: 'sua_senha',
    database: 'whatsapp_bot'
};
```

### 5. Inicie o servidor Node.js
```bash
node index.js
```

### 6. Acesse a interface web
Abra seu navegador e acesse: `http://localhost/index.php`

## 📱 Como Usar

### Primeira Configuração
1. Execute o arquivo `Ligar_BOT.bat` ou inicie o servidor Node.js
2. Escaneie o QR Code que aparecerá no terminal
3. Aguarde a conexão com o WhatsApp

### Gestão de Clientes
1. Acesse a interface web
2. Adicione novos clientes com suas informações
3. Configure datas de início e expiração das assinaturas
4. Gerencie o status de pagamento

### Envio de Mensagens
- **Automático**: O sistema envia lembretes automaticamente 5 dias antes do vencimento
- **Manual**: Selecione clientes e envie mensagens personalizadas
- **Tipos de Mensagem**:
  - Lembrete (5 dias antes)
  - Vencimento (no dia)
  - Atraso (após vencimento)

## 🔄 Funcionalidades Automáticas

- **Verificação Diária**: Sistema verifica assinaturas vencendo em 5 dias
- **Reset Mensal**: Status de pagamento é resetado no primeiro dia do mês
- **Reconexão Automática**: WhatsApp reconecta automaticamente se desconectado
- **Validação de Telefones**: Formatação automática de números brasileiros

## 📊 Estrutura do Projeto

```
BOT/
├── index.js              # Servidor Node.js principal
├── index.php             # Interface web PHP
├── messages.json         # Configuração de mensagens
├── package.json          # Dependências Node.js
├── Ligar_BOT.bat         # Script de inicialização
├── css/                  # Arquivos de estilo
├── js/                   # Scripts JavaScript
└── README.md            # Este arquivo
```

## ⚙️ Configuração de Mensagens

As mensagens podem ser personalizadas editando o arquivo `messages.json`:

```json
{
    "reminder": "Mensagem de lembrete personalizada",
    "due": "Mensagem de vencimento personalizada",
    "overdue": "Mensagem de atraso personalizada"
}
```

## 🔒 Segurança

- Validação de entrada de dados
- Sanitização de dados PHP
- Conexão segura com banco de dados
- Autenticação WhatsApp via QR Code

## 🐛 Solução de Problemas

### WhatsApp não conecta
- Verifique se o WhatsApp Web está funcionando
- Tente escanear o QR Code novamente
- Reinicie o servidor Node.js

### Erro de banco de dados
- Verifique as credenciais do MySQL
- Confirme se o banco `whatsapp_bot` existe
- Verifique se a tabela `clientes` foi criada

### Mensagens não são enviadas
- Confirme se o WhatsApp está conectado
- Verifique se os números de telefone estão no formato correto
- Confirme se as mensagens estão configuradas

## 📝 Licença

Este projeto está sob a licença ISC.

## 🤝 Contribuição

Contribuições são bem-vindas! Sinta-se à vontade para:
- Reportar bugs
- Sugerir novas funcionalidades
- Enviar pull requests

## 📞 Suporte

Para suporte técnico ou dúvidas, abra uma issue no repositório.

---

**Desenvolvido com ❤️ para automatizar a gestão de assinaturas IPTV** 

** Developer VioleProg**
** Se te ajudei e não for te fazer falta e puder doar qualquer valor já ajuda na motivação para atualizações e features futuras **
** PIX: violeprog@gmail.com **