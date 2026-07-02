import apiClient from './client';

export interface MagentoStatus {
  environment: string;
  name: string;
  api_url: string;
  authenticated: boolean;
  type: string;
}

export interface MagentoProduct {
  id?: number;
  sku: string;
  name: string;
  price: number;
  status: number;
  visibility: number;
  type_id: string;
  extension_attributes?: any;
  custom_attributes?: { attribute_code: string; value: any }[];
  media_gallery_entries?: any[];
}

export interface MagentoCustomer {
  id?: number;
  email: string;
  firstname: string;
  lastname: string;
  group_id?: number;
  store_id?: number;
  addresses?: any[];
}

export interface MagentoCmsPage {
  id?: number;
  title: string;
  identifier: string;
  content?: string;
  is_active?: boolean;
  store_id?: number[];
}

export interface MagentoCmsBlock {
  id?: number;
  title: string;
  identifier: string;
  content?: string;
  is_active?: boolean;
  store_id?: number[];
}

export interface PaginatedResult<T> {
  items: T[];
  total_count: number;
  search_criteria?: any;
}

export async function fetchMagentoStatus(env: string = 'prod'): Promise<MagentoStatus> {
  const { data } = await apiClient.get(`/api/magento.php?action=status&env=${env}`);
  return data;
}

export async function fetchMagentoOrders(env: string = 'prod', page: number = 1, pageSize: number = 20): Promise<PaginatedResult<any>> {
  const { data } = await apiClient.get(`/api/magento.php?action=orders&env=${env}&page=${page}&pageSize=${pageSize}`);
  return data;
}

export async function fetchMagentoProducts(env: string = 'prod', page: number = 1, pageSize: number = 20, search?: string): Promise<PaginatedResult<MagentoProduct>> {
  let url = `/api/magento.php?action=products&env=${env}&page=${page}&pageSize=${pageSize}`;
  if (search) {
    url += `&searchCriteria[filterGroups][0][filters][0][field]=name&searchCriteria[filterGroups][0][filters][0][value]=%25${encodeURIComponent(search)}%25&searchCriteria[filterGroups][0][filters][0][conditionType]=like`;
  }
  const { data } = await apiClient.get(url);
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

export async function fetchMagentoCustomers(env: string = 'prod', page: number = 1, pageSize: number = 20, search?: string): Promise<PaginatedResult<MagentoCustomer>> {
  let url = `/api/magento.php?action=customers&env=${env}&page=${page}&pageSize=${pageSize}`;
  if (search) {
    url += `&searchCriteria[filterGroups][0][filters][0][field]=email&searchCriteria[filterGroups][0][filters][0][value]=%25${encodeURIComponent(search)}%25&searchCriteria[filterGroups][0][filters][0][conditionType]=like`;
  }
  const { data } = await apiClient.get(url);
  return data;
}

export async function fetchMagentoCategories(env: string = 'prod'): Promise<any> {
  const { data } = await apiClient.get(`/api/magento.php?action=categories_tree&env=${env}`);
  return data;
}

export async function fetchMagentoCmsPages(env: string = 'prod', page: number = 1, pageSize: number = 20): Promise<PaginatedResult<MagentoCmsPage>> {
  const { data } = await apiClient.get(`/api/magento.php?action=cms&env=${env}&page=${page}&pageSize=${pageSize}`);
  return data;
}

export async function fetchMagentoCmsBlocks(env: string = 'prod', page: number = 1, pageSize: number = 20): Promise<PaginatedResult<MagentoCmsBlock>> {
  const { data } = await apiClient.get(`/api/magento.php?action=cms_blocks&env=${env}&page=${page}&pageSize=${pageSize}`);
  return data;
}

export async function saveMagentoProduct(product: Partial<MagentoProduct>, env: string = 'prod'): Promise<any> {
  const { data } = await apiClient.post(`/api/magento.php?action=product_save&env=${env}`, { product });
  if (data.error) throw new Error(data.error);
  return data;
}

export async function deleteMagentoProduct(sku: string, env: string = 'prod'): Promise<any> {
  const { data } = await apiClient.post(`/api/magento.php?action=product_delete&env=${env}&sku=${encodeURIComponent(sku)}`);
  if (data.error) throw new Error(data.error);
  return data;
}

export async function bulkMagentoProducts(operations: any, env: string = 'prod'): Promise<any> {
  const { data } = await apiClient.post(`/api/magento.php?action=product_bulk&env=${env}`, operations);
  if (data.error) throw new Error(data.error);
  return data;
}

export async function saveMagentoCustomer(customer: Partial<MagentoCustomer>, env: string = 'prod'): Promise<any> {
  const { data } = await apiClient.post(`/api/magento.php?action=customer_save&env=${env}`, { customer });
  if (data.error) throw new Error(data.error);
  return data;
}

export async function deleteMagentoCustomer(id: number, env: string = 'prod'): Promise<any> {
  const { data } = await apiClient.post(`/api/magento.php?action=customer_delete&env=${env}&id=${id}`);
  if (data.error) throw new Error(data.error);
  return data;
}

export async function performOrderAction(orderId: number, op: 'cancel' | 'hold' | 'unhold' | 'ship' | 'invoice' | 'comment', env: string = 'prod', body?: any): Promise<any> {
  const { data } = await apiClient.post(`/api/magento.php?action=order_action&env=${env}&id=${orderId}&op=${op}`, body || {});
  if (data.error) throw new Error(data.error);
  return data;
}

export async function saveMagentoCmsPage(page: Partial<MagentoCmsPage>, env: string = 'prod'): Promise<any> {
  const { data } = await apiClient.post(`/api/magento.php?action=cms_page_save&env=${env}`, { page });
  if (data.error) throw new Error(data.error);
  return data;
}

export async function deleteMagentoCmsPage(id: number, env: string = 'prod'): Promise<any> {
  const { data } = await apiClient.post(`/api/magento.php?action=cms_page_delete&env=${env}&id=${id}`);
  if (data.error) throw new Error(data.error);
  return data;
}

export async function saveMagentoCmsBlock(block: Partial<MagentoCmsBlock>, env: string = 'prod'): Promise<any> {
  const { data } = await apiClient.post(`/api/magento.php?action=cms_block_save&env=${env}`, { block });
  if (data.error) throw new Error(data.error);
  return data;
}

export async function deleteMagentoCmsBlock(id: number, env: string = 'prod'): Promise<any> {
  const { data } = await apiClient.post(`/api/magento.php?action=cms_block_delete&env=${env}&id=${id}`);
  if (data.error) throw new Error(data.error);
  return data;
}

export async function uploadProductMedia(sku: string, entry: { media_type: string; label: string; file: { name: string; base64_encoded_data: string } }, env: string = 'prod'): Promise<any> {
  const { data } = await apiClient.post(`/api/magento.php?action=media_upload&env=${env}&sku=${encodeURIComponent(sku)}`, { entry });
  if (data.error) throw new Error(data.error);
  return data;
}

export async function fetchStoreConfig(env: string = 'prod'): Promise<any> {
  const { data } = await apiClient.get(`/api/magento.php?action=store_config&env=${env}`);
  return data;
}
