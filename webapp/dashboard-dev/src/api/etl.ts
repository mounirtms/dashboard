import apiClient from './client';

export interface EtlStatus {
  mdm_connected: boolean;
  cegid_connected: boolean;
  last_sync?: string;
  errors?: string[];
}

export async function fetchEtlStatus(): Promise<any> {
  // Run both in parallel; treat individual failures as disconnected (not a crash)
  const [mdmResult, cegidResult] = await Promise.allSettled([
    apiClient.get('/api/mdm/connect'),
    apiClient.get('/api/cegid/connect'),
  ]);

  const mdm = mdmResult.status === 'fulfilled'
    ? mdmResult.value.data
    : { success: false, source: 'mdm', message: (mdmResult.reason as any)?.message ?? 'Unreachable' };

  const cegid = cegidResult.status === 'fulfilled'
    ? cegidResult.value.data
    : { success: false, source: 'cegid', message: (cegidResult.reason as any)?.message ?? 'Unreachable' };

  return { mdm, cegid };
}

export async function fetchMdmInventory(): Promise<any> {
  const { data } = await apiClient.get('/api/mdm/inventory');
  return data;
}

export async function triggerPriceSync(): Promise<any> {
  const { data } = await apiClient.post('/api/techno/prices-sync', {});
  return data;
}
