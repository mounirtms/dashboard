import { useState, useEffect, useCallback } from 'react';
import apiClient from '../api/client';

declare global {
  interface Window {
    webpushr: any;
  }
}

interface PushSubscriptionState {
  isSupported: boolean;
  isSubscribed: boolean;
  isLoading: boolean;
  error: string | null;
}

export function useWebpushrSubscription() {
  const [state, setState] = useState<PushSubscriptionState>({
    isSupported: false,
    isSubscribed: false,
    isLoading: true,
    error: null,
  });

  useEffect(() => {
    // Check if Webpushr SDK is loaded
    const checkSdk = setInterval(() => {
      if (typeof window.webpushr === 'function') {
        clearInterval(checkSdk);
        setState(prev => ({ ...prev, isSupported: true, isLoading: false }));
      }
    }, 200);

    setTimeout(() => {
      clearInterval(checkSdk);
      setState(prev => ({ ...prev, isLoading: false }));
    }, 5000);

    return () => clearInterval(checkSdk);
  }, []);

  const subscribe = useCallback(async () => {
    setState(prev => ({ ...prev, isLoading: true, error: null }));
    
    try {
      // Trigger Webpushr subscription prompt
      if (typeof window.webpushr === 'function') {
        window.webpushr('subscribe');
        
        // Wait a moment for subscription to complete
        await new Promise(resolve => setTimeout(resolve, 2000));
        
        // Notify backend that user subscribed
        await apiClient.post('/api/webpushr.php?action=subscribe', {
          endpoint: 'pending', // Webpushr handles this internally
          domain: 'dashboard',
        });
        
        setState(prev => ({ ...prev, isSubscribed: true, isLoading: false }));
      }
    } catch (err: any) {
      setState(prev => ({
        ...prev,
        error: err.response?.data?.error || 'Failed to subscribe',
        isLoading: false,
      }));
    }
  }, []);

  const unsubscribe = useCallback(async (endpoint?: string) => {
    setState(prev => ({ ...prev, isLoading: true, error: null }));
    
    try {
      if (typeof window.webpushr === 'function' && !endpoint) {
        // Unsubscribe from Webpushr entirely
        window.webpushr('unsubscribe');
      }
      
      // Remove from backend
      await apiClient.post('/api/webpushr.php?action=unsubscribe', {
        endpoint: endpoint || 'all',
      });
      
      setState(prev => ({ ...prev, isSubscribed: false, isLoading: false }));
    } catch (err: any) {
      setState(prev => ({
        ...prev,
        error: err.response?.data?.error || 'Failed to unsubscribe',
        isLoading: false,
      }));
    }
  }, []);

  return {
    ...state,
    subscribe,
    unsubscribe,
  };
}
