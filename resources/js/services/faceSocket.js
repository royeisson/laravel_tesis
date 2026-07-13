// Usar la IP del servidor automaticamente para acceso desde celular/tablet
const WS_HOST = window.location.hostname || '127.0.0.1';
const WS_URL = `ws://${WS_HOST}:5002`;
const RECONNECT_DELAY = 2000;

class FaceSocket {
    constructor() {
        this.ws = null;
        this.connected = false;
        this.onMessage = null;
        this.onConnect = null;
        this.onDisconnect = null;
        this._intentionalClose = false;
    }

    connect() {
        if (this.ws && (this.ws.readyState === WebSocket.CONNECTING || this.ws.readyState === WebSocket.OPEN)) {
            return;
        }

        try {
            this.ws = new WebSocket(WS_URL);
            this.ws.binaryType = 'arraybuffer';

            this.ws.onopen = () => {
                this.connected = true;
                console.log('[FaceSocket] Conectado al servidor facial WebSocket');
                if (this.onConnect) this.onConnect();
            };

            this.ws.onmessage = (event) => {
                try {
                    const data = JSON.parse(event.data);
                    if (this.onMessage) this.onMessage(data);
                } catch (e) {
                    console.error('[FaceSocket] Error parseando mensaje:', e);
                }
            };

            this.ws.onclose = () => {
                const wasConnected = this.connected;
                this.connected = false;
                this.ws = null;
                if (wasConnected && this.onDisconnect) this.onDisconnect();
                if (!this._intentionalClose) {
                    setTimeout(() => this.connect(), RECONNECT_DELAY);
                }
            };

            this.ws.onerror = (err) => {
                console.error('[FaceSocket] Error:', err);
            };
        } catch (e) {
            console.error('[FaceSocket] Error creando WebSocket:', e);
            setTimeout(() => this.connect(), RECONNECT_DELAY);
        }
    }

    sendFrame(blob) {
        if (!this.connected || !this.ws || this.ws.readyState !== WebSocket.OPEN) {
            return false;
        }
        blob.arrayBuffer().then((buffer) => {
            if (this.ws && this.ws.readyState === WebSocket.OPEN) {
                this.ws.send(buffer);
            }
        }).catch(() => {});
        return true;
    }

    sendReload() {
        if (this.connected && this.ws && this.ws.readyState === WebSocket.OPEN) {
            this.ws.send(JSON.stringify({ action: 'reload' }));
        }
    }

    disconnect() {
        this._intentionalClose = true;
        if (this.ws) {
            this.ws.close();
            this.ws = null;
        }
        this.connected = false;
    }
}

export const faceSocket = new FaceSocket();
export default faceSocket;
