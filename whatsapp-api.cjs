const { Client, LocalAuth } = require('whatsapp-web.js');
const express = require('express');
const qrcode = require('qrcode-terminal');

console.log('Starting WhatsApp API Service...');

// Global status flag to track if the client is ready
let isClientReady = false;

// --- WhatsApp Client Initialization ---
const client = new Client({
    authStrategy: new LocalAuth(), // Use local session storage
    puppeteer: {
        headless: true, // Run in headless mode
        args: ['--no-sandbox', '--disable-setuid-sandbox'] // Necessary for running in some environments (e.g., Docker)
    }
});

client.on('qr', (qr) => {
    console.log('[WHATSAPP API] QR code needs to be scanned.');
    isClientReady = false; // Set status to not ready
    qrcode.generate(qr, { small: true });
});

client.on('ready', () => {
    console.log('[WHATSAPP API] Client is ready and connected!');
    isClientReady = true; // Set status to ready
});

client.on('disconnected', (reason) => {
    console.log('[WHATSAPP API] Client was logged out', reason);
    isClientReady = false; // Set status to not ready
    // You might want to add logic here to exit the process or attempt to re-initialize
    process.exit(1); 
});

client.on('auth_failure', msg => {
    console.error('[WHATSAPP API] Authentication failure', msg);
    isClientReady = false;
    process.exit(1);
});


// Initialize the client and handle potential errors during startup
try {
    console.log('[WHATSAPP API] Attempting to initialize client...');
    client.initialize();
} catch (error) {
    console.error('[WHATSAPP API] FATAL ERROR during client.initialize():', error);
    process.exit(1);
}


// --- API Server Initialization (using Express) ---
const app = express();
app.use(express.json()); // Middleware to parse JSON bodies

const PORT = process.env.WHATSAPP_API_PORT || 3000;

// API endpoint to check the client's status
app.get('/status', (req, res) => {
    if (isClientReady) {
        res.status(200).json({ status: 'ready', message: 'WhatsApp client is connected and ready.' });
    } else {
        res.status(503).json({ status: 'initializing', message: 'WhatsApp client is not ready. Please wait.' });
    }
});


// API endpoint to send a message
app.post('/send-message', async (req, res) => {
    const { to, message } = req.body;

    if (!to || !message) {
        return res.status(400).json({ status: 'error', message: 'Missing "to" or "message" in request body.' });
    }

    // Defensive check: ensure client is ready before attempting to send
    if (!isClientReady) {
        return res.status(503).json({ status: 'error', message: 'Client is not ready. Cannot send message.' });
    }
    
    const chatId = `${to}@c.us`;

    try {
        const msg = await client.sendMessage(chatId, message);

        // Robust check: Ensure 'msg' object and its 'id' property exist before using them.
        if (msg && msg.id && msg.id._serialized) {
            console.log(`[WHATSAPP API] Successfully sent message to ${to}. Message ID: ${msg.id._serialized}`);
            res.status(200).json({
                status: 'success',
                message: 'Message sent successfully.',
                messageId: msg.id._serialized
            });
        } else {
            // Handles cases where the library sends the message but doesn't return the expected object.
            console.log(`[WHATSAPP API] Message sent to ${to}, but no valid message object was returned by the library.`);
            res.status(200).json({
                status: 'success',
                message: 'Message sent, but no confirmation ID was received.'
            });
        }

    } catch (error) {
        // This catch block handles errors thrown by the sendMessage function itself.
        console.error(`[WHATSAPP API] Failed to send message to ${to}:`, error);
        res.status(500).json({ status: 'error', message: 'Failed to send message.', error: error.toString() });
    }
});

app.listen(PORT, () => {
    console.log(`[WHATSAPP API] Server is listening on port ${PORT}`);
});

// Graceful shutdown
process.on('SIGINT', async () => {
    console.log('[WHATSAPP API] Shutting down...');
    await client.destroy();
    process.exit(0);
});

// Catch any other unhandled errors to prevent silent crashes
process.on('unhandledRejection', (reason, promise) => {
    console.error('[WHATSAPP API] Unhandled Rejection at:', promise, 'reason:', reason);
});