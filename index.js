const qrcode = require('qrcode-terminal');
const { Client, LocalAuth } = require('whatsapp-web.js');
const mysql = require('mysql2/promise');
const express = require('express');
const bodyParser = require('body-parser');
const path = require('path');
const app = express();
const port = 3000;

const dbConfig = {
    host: 'localhost',
    user: 'root',
    password: '46984698',
    database: 'whatsapp_bot'
};

const whatsappClient = new Client({
    authStrategy: new LocalAuth(),
    puppeteer: {
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    }
});

const pool = mysql.createPool(dbConfig);

let isWhatsAppReady = false;

whatsappClient.on('qr', qr => {
    console.log('Escaneie o QR Code:');
    qrcode.generate(qr, { small: true });
});

whatsappClient.on('ready', () => {
    console.log('WhatsApp conectado. ✅');
    isWhatsAppReady = true;
    checkSubscriptions();
});

whatsappClient.on('disconnected', (reason) => {
    console.log('WhatsApp desconectado:', reason);
    isWhatsAppReady = false;
    whatsappClient.initialize().catch(error => {
        console.error('Erro ao reinicializar o cliente WhatsApp:', error);
    });
});

function formatPhoneNumber(phone) {
    try {
        phone = phone.replace(/\D/g, '');
        if (!phone.startsWith('55')) {
            phone = '55' + phone;
        }
        if (phone.length < 12 || phone.length > 13) {
            throw new Error(`Número de telefone inválido: ${phone}`);
        }
        return phone + '@c.us';
    } catch (error) {
        console.error(`Erro ao formatar número de telefone ${phone}:`, error.message);
        return null;
    }
}

function getDaysDifference(date1, date2) {
    const diffTime = date1 - date2;
    return Math.ceil(diffTime / (1000 * 60 * 60 * 24));
}

async function checkSubscriptions() {
    if (!isWhatsAppReady) {
        console.log('WhatsApp não está conectado. Aguardando conexão para verificar assinaturas.');
        return;
    }

    try {
        console.log('Verificando assinaturas...');
        const [rows] = await pool.query(`
            SELECT * FROM clientes 
            WHERE data_expiracao >= CURDATE() 
            AND data_expiracao <= DATE_ADD(CURDATE(), INTERVAL 5 DAY)
            OR (pago = 0 AND data_expiracao < CURDATE())
        `);

        if (rows.length === 0) {
            console.log('Nenhum cliente com vencimento próximo ou em atraso.');
        }

        for (const client of rows) {
            const expirationDate = new Date(client.data_expiracao);
            const today = new Date();
            today.setHours(0, 0, 0, 0);
            const daysUntilExpiration = getDaysDifference(expirationDate, today);
            const phone = formatPhoneNumber(client.telefone);

            if (!phone) {
                console.error(`Número de telefone inválido para ${client.nome}: ${client.telefone}`);
                continue;
            }

            try {
                if (daysUntilExpiration === 5 && client.pago === 0) {
                    const message = `*BOT Marcio* Olá, ${client.nome} 😊\n\n` +
                        `⚠️*Lembrete:* sua assinatura de Revenda de IPTV vence em 5 dias (${expirationDate.toLocaleDateString('pt-BR')}).\n` +
                        `Por favor, regularize o pagamento para continuar aproveitando nossos serviços.`;
                    await whatsappClient.sendMessage(phone, message);
                    console.log(`Lembrete de 5 dias enviado para ${client.nome} (${phone})`);
                } else if (expirationDate.toDateString() === today.toDateString() && client.pago === 0) {
                    const message = `*BOT Marcio* Olá, ${client.nome} ⚠️\n\n` +
                        `Hoje é o dia de vencimento da sua assinatura de Revenda de IPTV (${expirationDate.toLocaleDateString('pt-BR')}).\n` +
                        `Por favor, efetue o pagamento para evitar interrupções.`;
                    await whatsappClient.sendMessage(phone, message);
                    console.log(`Mensagem de vencimento enviada para ${client.nome} (${phone})`);
                } else if (expirationDate < today && client.pago === 0) {
                    const message = `*BOT Marcio* Olá, ${client.nome} ⚠️\n\n` +
                        `Notamos que sua assinatura de Revenda de IPTV está em atraso desde ${expirationDate.toLocaleDateString('pt-BR')}.\n` +
                        `Regularize sua situação o quanto antes para evitar a suspensão do serviço.`;
                    await whatsappClient.sendMessage(phone, message);
                    console.log(`Mensagem de atraso enviada para ${client.nome} (${phone})`);
                }
            } catch (error) {
                console.error(`Erro ao enviar mensagem para ${client.nome} (${phone}):`, error.message);
            }
        }
    } catch (error) {
        console.error('Erro ao verificar assinaturas:', error);
    }
}

setInterval(checkSubscriptions, 24 * 60 * 60 * 1000);

app.use(bodyParser.json());
app.use(express.static(path.join(__dirname, 'public')));

app.get('/api/clients', async (req, res) => {
    try {
        const [rows] = await pool.query('SELECT * FROM clientes');
        console.log('Clientes retornados pela API:', rows);
        res.json(rows);
    } catch (error) {
        console.error('Erro ao buscar clientes:', error);
        res.status(500).json({ error: error.message || 'Erro ao buscar clientes' });
    }
});

app.post('/api/clients', async (req, res) => {
    try {
        const { nome, sobrenome, cpf, email, telefone, data_inicio, data_expiracao, pago } = req.body;
        await pool.query(
            'INSERT INTO clientes (nome, sobrenome, cpf, email, telefone, data_inicio, data_expiracao, pago) VALUES (?, ?, ?, ?, ?, ?, ?, ?)',
            [nome, sobrenome, cpf, email, telefone, data_inicio, data_expiracao, pago]
        );
        res.json({ success: true });
    } catch (error) {
        console.error('Erro ao adicionar cliente:', error);
        res.status(500).json({ error: error.message || 'Erro ao adicionar cliente' });
    }
});

app.post('/api/send-messages', async (req, res) => {
    if (!isWhatsAppReady) {
        return res.status(503).json({ error: 'WhatsApp não está conectado' });
    }

    try {
        const { clientIds, messageType } = req.body;
        if (!clientIds || clientIds.length === 0) {
            return res.status(400).json({ error: 'Nenhum cliente selecionado' });
        }

        const [clients] = await pool.query('SELECT * FROM clientes WHERE id IN (?)', [clientIds]);
        
        for (const client of clients) {
            const phone = formatPhoneNumber(client.telefone);
            if (!phone) {
                console.error(`Número inválido para ${client.nome}: ${client.telefone}`);
                continue;
            }
            
            let message;
            const expirationDate = new Date(client.data_expiracao);
            if (messageType === 'reminder') {
                message = `*BOT Marcio* Olá, ${client.nome} 😊\n\n` +
                    `⚠️*Lembrete:* sua assinatura de Revenda de IPTV vence em 5 dias (${expirationDate.toLocaleDateString('pt-BR')}).\n` +
                    `Por favor, regularize o pagamento para continuar aproveitando nossos serviços.`;
            } else if (messageType === 'due') {
                message = `*BOT Marcio* Olá, ${client.nome} ⚠️\n\n` +
                    `Hoje é o dia de vencimento da sua assinatura de Revenda de IPTV (${expirationDate.toLocaleDateString('pt-BR')}).\n` +
                    `Por favor, efetue o pagamento para evitar interrupções.`;
            } else if (messageType === 'overdue') {
                message = `*BOT Marcio* Olá, ${client.nome} ⚠️\n\n` +
                    `Notamos que sua assinatura de Revenda de IPTV está em atraso desde ${expirationDate.toLocaleDateString('pt-BR')}.\n` +
                    `Regularize sua situação o quanto antes para evitar a suspensão do serviço.`;
            }

            try {
                await whatsappClient.sendMessage(phone, message);
                console.log(`${messageType === 'reminder' ? 'Lembrete' : (messageType === 'due' ? 'Vencimento' : 'Atraso')} enviado para ${client.nome} (${phone})`);
            } catch (error) {
                console.error(`Erro ao enviar ${messageType} para ${client.nome} (${phone}):`, error.message);
            }
        }
        res.json({ success: true });
    } catch (error) {
        console.error('Erro ao enviar mensagens:', error);
        res.status(500).json({ error: error.message || 'Erro ao enviar mensagens' });
    }
});

app.post('/api/delete-clients', async (req, res) => {
    try {
        const { clientIds } = req.body;
        if (!clientIds || clientIds.length === 0) {
            return res.status(400).json({ error: 'Nenhum cliente selecionado' });
        }

        await pool.query('DELETE FROM clientes WHERE id IN (?)', [clientIds]);
        res.json({ success: true });
    } catch (error) {
        console.error('Erro ao deletar clientes:', error);
        res.status(500).json({ error: error.message || 'Erro ao deletar clientes' });
    }
});

app.post('/api/edit-client', async (req, res) => {
    try {
        const { id, telefone, data_expiracao } = req.body;
        await pool.query(
            'UPDATE clientes SET telefone = ?, data_expiracao = ? WHERE id = ?',
            [telefone, data_expiracao, id]
        );
        res.json({ success: true });
    } catch (error) {
        console.error('Erro ao editar cliente:', error);
        res.status(500).json({ error: error.message || 'Erro ao editar cliente' });
    }
});

app.post('/api/mark-as-paid', async (req, res) => {
    try {
        const { clientIds, newExpiration } = req.body;
        if (!clientIds || clientIds.length === 0) {
            return res.status(400).json({ error: 'Nenhum cliente selecionado' });
        }

        await pool.query(
            'UPDATE clientes SET pago = 1, data_expiracao = ? WHERE id IN (?)',
            [newExpiration, clientIds]
        );
        res.json({ success: true });
    } catch (error) {
        console.error('Erro ao marcar como pago:', error);
        res.status(500).json({ error: error.message || 'Erro ao marcar como pago' });
    }
});

async function resetPaidStatus() {
    const today = new Date();
    if (today.getDate() === 1) {
        try {
            await pool.query('UPDATE clientes SET pago = 0');
            console.log('Status pago resetado para todos os clientes.');
        } catch (error) {
            console.error('Erro ao resetar status pago:', error);
        }
    }
}

setInterval(resetPaidStatus, 24 * 60 * 60 * 1000);

whatsappClient.initialize().catch(error => {
    console.error('Erro ao inicializar o cliente WhatsApp:', error);
});

app.listen(port, () => {
    console.log(`Servidor API disponível em http://localhost:${port}`);
});