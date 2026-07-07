/**
 * Extract a human-readable error message from an unknown catch value.
 * Handles Axios errors (response.data.error / response.data.message),
 * plain Error instances, and anything stringifiable.
 */
export function getErrMsg(e: unknown): string {
  if (e && typeof e === 'object') {
    const obj = e as Record<string, unknown>;
    // Axios-style error
    const resp = obj['response'] as Record<string, unknown> | undefined;
    if (resp) {
      const d = resp['data'] as Record<string, unknown> | undefined;
      if (d) {
        if (typeof d['error'] === 'string') return d['error'];
        if (typeof d['message'] === 'string') return d['message'];
      }
    }
    if (typeof obj['message'] === 'string') return obj['message'];
  }
  if (typeof e === 'string') return e;
  return 'An unknown error occurred';
}

export function formatBytes(bytes: number): string {
  if (bytes >= 1073741824) return (bytes / 1073741824).toFixed(1) + ' GB';
  if (bytes >= 1048576) return (bytes / 1048576).toFixed(1) + ' MB';
  if (bytes >= 1024) return (bytes / 1024).toFixed(1) + ' KB';
  return bytes + ' B';
}

export function formatNumber(n: number | null | undefined): string {
  return (n ?? 0).toLocaleString();
}

export function formatDate(dateStr: string): string {
  return new Date(dateStr).toLocaleDateString('en-US', {
    month: 'short',
    day: 'numeric',
    year: 'numeric',
  });
}

export function formatTime(datetime: string): string {
  return datetime.slice(11, 16);
}
