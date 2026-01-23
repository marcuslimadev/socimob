import { useEffect, useRef, useCallback } from 'react';

interface RealtimeMessage {
  type: 'lead_created' | 'lead_updated' | 'message_sent' | 'notification' | 'property_updated';
  data: any;
  timestamp: string;
}

interface RealtimeOptions {
  onMessage?: (message: RealtimeMessage) => void;
  onConnect?: () => void;
  onDisconnect?: () => void;
  onError?: (error: Error) => void;
}

/**
 * Hook para sincronização em tempo real via WebSocket
 * Conecta ao backend para receber atualizações em tempo real
 */
export function useRealtime(options: RealtimeOptions = {}) {
  const wsRef = useRef<WebSocket | null>(null);
  const reconnectTimeoutRef = useRef<NodeJS.Timeout | null>(null);
  const reconnectAttemptsRef = useRef(0);
  const maxReconnectAttempts = 5;

  const connect = useCallback(() => {
    try {
      const wsUrl = import.meta.env.VITE_WS_URL || 
        `${window.location.protocol === 'https:' ? 'wss:' : 'ws:'}//${window.location.host}/ws`;
      
      const token = localStorage.getItem('socimob_auth_token');
      if (!token) {
        console.warn('Token não disponível para conexão WebSocket');
        return;
      }

      wsRef.current = new WebSocket(`${wsUrl}?token=${token}`);

      wsRef.current.onopen = () => {
        console.log('WebSocket conectado');
        reconnectAttemptsRef.current = 0;
        options.onConnect?.();
      };

      wsRef.current.onmessage = (event) => {
        try {
          const message: RealtimeMessage = JSON.parse(event.data);
          options.onMessage?.(message);
        } catch (error) {
          console.error('Erro ao processar mensagem WebSocket:', error);
        }
      };

      wsRef.current.onerror = (error) => {
        console.error('Erro WebSocket:', error);
        options.onError?.(new Error('Erro de conexão WebSocket'));
      };

      wsRef.current.onclose = () => {
        console.log('WebSocket desconectado');
        options.onDisconnect?.();
        
        // Tentar reconectar
        if (reconnectAttemptsRef.current < maxReconnectAttempts) {
          reconnectAttemptsRef.current++;
          const delay = Math.min(1000 * Math.pow(2, reconnectAttemptsRef.current), 30000);
          
          reconnectTimeoutRef.current = setTimeout(() => {
            console.log(`Tentando reconectar... (${reconnectAttemptsRef.current}/${maxReconnectAttempts})`);
            connect();
          }, delay);
        }
      };
    } catch (error) {
      console.error('Erro ao conectar WebSocket:', error);
      options.onError?.(error as Error);
    }
  }, [options]);

  const disconnect = useCallback(() => {
    if (reconnectTimeoutRef.current) {
      clearTimeout(reconnectTimeoutRef.current);
    }
    if (wsRef.current) {
      wsRef.current.close();
      wsRef.current = null;
    }
  }, []);

  const send = useCallback((message: any) => {
    if (wsRef.current?.readyState === WebSocket.OPEN) {
      wsRef.current.send(JSON.stringify(message));
    } else {
      console.warn('WebSocket não está conectado');
    }
  }, []);

  const isConnected = wsRef.current?.readyState === WebSocket.OPEN;

  useEffect(() => {
    connect();
    return () => disconnect();
  }, [connect, disconnect]);

  return { send, isConnected, disconnect };
}

/**
 * Hook para ouvir eventos específicos em tempo real
 */
export function useRealtimeEvent(
  eventType: RealtimeMessage['type'],
  callback: (data: any) => void
) {
  useRealtime({
    onMessage: (message) => {
      if (message.type === eventType) {
        callback(message.data);
      }
    },
  });
}
