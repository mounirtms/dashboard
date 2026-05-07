import apiClient from './client';

export interface EtlStatus {
  mdm_connected: boolean;
  cegid_connected: boolean;
  last_sync?: string;
  errors?: string[];
}

export async function fetchEtlStatus(): Promise<any> {
  // Try both endpoints
  const mdm = await apiClient.get('/api/mdm/connect');
  const cegid = await apiClient.get('/api/cegid/connect');
  return {
    mdm: mdm.data,
    cegid: cegid.data
  };
}

export async function fetchMdmInventory(): Promise<any> {
  const { data } = await apiClient.get('/api/mdm/inventory');
  return data;
}

export async function triggerPriceSync(): Promise<any> {
  const { data } = await apiClient.post('/api/techno/prices-sync', {});
  return data;
}
