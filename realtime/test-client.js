const WebSocket = require('ws');

const saleEventId = 'test-sale-event-123';
const ws = new WebSocket(`ws://127.0.0.1:8081/ws?saleEventId=${saleEventId}`);

let receivedBroadcast = false;

ws.on('open', () => {
    console.log('CLIENT: connected');
});

ws.on('message', (data) => {
    const parsed = JSON.parse(data.toString());
    console.log('CLIENT RECEIVED:', JSON.stringify(parsed));
    if (parsed.event === 'bid_placed') {
        receivedBroadcast = true;
        console.log('TEST_RESULT: PASS - broadcast received with correct data');
        process.exit(0);
    }
});

ws.on('error', (err) => {
    console.log('CLIENT ERROR:', err.message);
    process.exit(1);
});

setTimeout(() => {
    if (!receivedBroadcast) {
        console.log('TEST_RESULT: FAIL - no broadcast received within timeout');
        process.exit(1);
    }
}, 5000);
