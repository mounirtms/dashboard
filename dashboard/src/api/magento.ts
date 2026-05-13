import apiClient from './client';

export interface MagentoStatus {
  environment: string;
  name: string;
  api_url: string;
  authenticated: boolean;
  type: string;
}

export async function fetchMagentoStatus(env: string = 'prod'): Promise<MagentoStatus> {
  const { data } = await apiClient.get(`/api/magento.php?action=status&env=${env}`);
  return data;
}

export async function fetchMagentoOrders(env: string = 'prod', page: number = 1): Promise<any> {
  const { data } = await apiClient.get(`/api/magento.php?action=orders&env=${env}&page=${page}`);
  return data;
}

export async function fetchMagentoProducts(env: string = 'prod', page: number = 1): Promise<any> {
  const { data } = await apiClient.get(`/api/magento.php?action=products&env=${env}&page=${page}`);
  return data;
}

export async function fetchMagentoStock(env: string = 'prod', page: number = 1): Promise<any> {
  const { data } = await apiClient.get(`/api/magento.php?action=stock&env=${env}&page=${page}`);
  return data;
}

export async function fetchMagentoIndexers(env: string = 'prod'): Promise<any> {
  const { data } = await apiClient.get(`/api/monitor.php?action=indexer&env=${env}`);
  if (data.error) throw new Error(data.error);
  return data.indexers || [];
}

export async function runMagentoIndexer(env: string = 'prod', indexerId: string): Promise<any> {
  const { data } = await apiClient.post(`/api/monitor.php?action=indexer_action&env=${env}&indexer=${indexerId}`, {
    indexer_id: indexerId,
    mode: 'reindex'
  });
  if (data.error) throw new Error(data.error);
  return data;
}
