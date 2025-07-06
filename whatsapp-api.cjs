const { Client, LocalAuth } = require('whatsapp-web.js');
const express = require('express');
const qrcode = require('qrcode-terminal');

// --- THIS IS THE KEY PART ---
// Use an environment variable for a unique client ID.
// This allows each instance to have its own session folder.
const clientId = process.env.WHATSAPP_CLIENT_ID || 'default';
console.log(`[WHATSAPP API - ${clientId}] Starting Service...`);
// --- END OF KEY PART ---

let isClientReady = false;

const client = new Client({
    // Use the clientId to create a unique session path
    authStrategy: new LocalAuth({ clientId: clientId }),
    puppeteer: {
        headless: true,
        args: ['--no-sandbox', '--disable-setuid-sandbox']
    }
});

client.on('qr', (qr) => {
    console.log(`[WHATSAPP API - ${clientId}] QR code needs to be scanned.`);
    isClientReady = false;
    qrcode.generate(qr, { small: true });
});

client.on('ready', () => {
    console.log(`[WHATSAPP API - ${clientId}] Client is ready and connected!`);
    isClientReady = true;
});

client.on('disconnected', (reason) => {
    console.log(`[WHATSAPP API - ${clientId}] Client was logged out`, reason);
    isClientReady = false;
    process.exit(1);
});

client.on('auth_failure', msg => {
    console.error(`[WHATSAPP API - ${clientId}] Authentication failure`, msg);
    isClientReady = false;
    process.exit(1);
});

try {
    console.log(`[WHATSAPP API - ${clientId}] Attempting to initialize client...`);
    client.initialize();
} catch (error) {
    console.error(`[WHATSAPP API - ${clientId}] FATAL ERROR during client.initialize():`, error);
    process.exit(1);
}

const app = express();
app.use(express.json());

const PORT = process.env.WHATSAPP_API_PORT || 3000;

app.get('/status', (req, res) => {
    if (isClientReady) {
        res.status(200).json({ status: 'ready', message: `WhatsApp client [${clientId}] is connected.` });
    } else {
        res.status(503).json({ status: 'initializing', message: `WhatsApp client [${clientId}] is not ready.` });
    }
});

app.post('/send-message', async (req, res) => {
    const { to, message } = req.body;
    if (!to || !message) { return res.status(400).json({ status: 'error', message: 'Missing "to" or "message".' }); }
    if (!isClientReady) { return res.status(503).json({ status: 'error', message: 'Client is not ready.' }); }
    const chatId = `${to}@c.us`;
    try {
        const msg = await client.sendMessage(chatId, message);
        console.log(`[WHATSAPP API - ${clientId}] Successfully sent message to ${to}.`);
        res.status(200).json({ status: 'success', message: 'Message sent successfully.' });
    } catch (error) {
        console.error(`[WHATSAPP API - ${clientId}] Failed to send message to ${to}:`, error);
        res.status(500).json({ status: 'error', message: 'Failed to send message.' });
    }
});

app.listen(PORT, () => {
    console.log(`[WHATSAPP API - ${clientId}] Server is listening on port ${PORT}`);
});

process.on('SIGINT', async () => {
    console.log(`[WHATSAPP API - ${clientId}] Shutting down...`);
    await client.destroy();
    process.exit(0);
});