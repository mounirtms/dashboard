import {
  Box,
  Typography,
  Card,
  CardContent,
  Grid,
  Button,
  TextField,
  Chip,
  IconButton,
  Tooltip,
  Alert,
  Snackbar,
  InputAdornment,
} from '@mui/material';
import { DataGrid, type GridColDef, type GridRenderCellParams } from '@mui/x-data-grid';
import {
  FileDownload,
  Refresh,
  LocationOn,
  Store,
  ContentCopy,
  OpenInNew,
  Search,
  Public,
  GpsFixed,
} from '@mui/icons-material';
import { useState, useEffect, useCallback, useMemo } from 'react';
import { fetchStoreLocations, exportStoresCsvUrl, type StoreLocation } from '../api/magento';
import LoadingState from '../components/common/LoadingState';

export default function StoreLocationsPage() {
  const [stores, setStores] = useState<StoreLocation[]>([]);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState<string | null>(null);
  const [search, setSearch] = useState('');
  const [toast, setToast] = useState<{ message: string; severity: 'success' | 'info' | 'error' } | null>(null);

  const loadStores = useCallback(async () => {
    setLoading(true);
    setError(null);
    try {
      const data = await fetchStoreLocations();
      setStores(data.stores);
    } catch (e: any) {
      setError(e.message || 'Failed to load store locations');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => {
    loadStores();
  }, [loadStores]);

  const handleExportCsv = () => {
    window.open(exportStoresCsvUrl(), '_blank');
    setToast({ message: 'Store locations CSV export initiated', severity: 'success' });
  };

  const handleExportJson = () => {
    const jsonStr = JSON.stringify(stores, null, 2);
    const blob = new Blob([jsonStr], { type: 'application/json' });
    const url = URL.createObjectURL(blob);
    const a = document.createElement('a');
    a.href = url;
    a.download = `techno_stores_amasty_locator_${new Date().toISOString().slice(0, 10)}.json`;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    URL.revokeObjectURL(url);
    setToast({ message: 'Store locations JSON export downloaded', severity: 'success' });
  };

  const copyCoordinates = (lat: number | null, lng: number | null) => {
    if (lat === null || lng === null) return;
    navigator.clipboard.writeText(`${lat}, ${lng}`);
    setToast({ message: `Copied coordinates (${lat}, ${lng}) to clipboard!`, severity: 'info' });
  };

  const filteredStores = useMemo(() => {
    if (!search.trim()) return stores;
    const q = search.toLowerCase();
    return stores.filter(
      s =>
        s.name.toLowerCase().includes(q) ||
        s.city.toLowerCase().includes(q) ||
        s.region.toLowerCase().includes(q) ||
        s.address.toLowerCase().includes(q) ||
        s.phone.toLowerCase().includes(q)
    );
  }, [stores, search]);

  const distinctWilayas = useMemo(() => {
    const set = new Set<string>();
    stores.forEach(s => {
      if (s.region) set.add(s.region);
    });
    return set.size;
  }, [stores]);

  const columns: GridColDef[] = [
    { field: 'id', headerName: 'ID', width: 65 },
    {
      field: 'name',
      headerName: 'Store Name',
      flex: 1.5,
      minWidth: 220,
      renderCell: (params: GridRenderCellParams) => (
        <Box sx={{ display: 'flex', alignItems: 'center', gap: 1 }}>
          <Store sx={{ color: 'primary.main', fontSize: 20 }} />
          <Box>
            <Typography variant="body2" sx={{ fontWeight: 600 }}>
              {params.value}
            </Typography>
            <Typography variant="caption" sx={{ color: 'text.disabled' }}>
              {params.row.city}
            </Typography>
          </Box>
        </Box>
      ),
    },
    {
      field: 'region',
      headerName: 'Wilaya / Region',
      width: 140,
      renderCell: (params: GridRenderCellParams) => (
        <Chip label={params.value || 'N/A'} size="small" variant="outlined" color="primary" />
      ),
    },
    {
      field: 'address',
      headerName: 'Address',
      flex: 2,
      minWidth: 260,
      renderCell: (params: GridRenderCellParams) => (
        <Tooltip title={params.value || ''} arrow>
          <Typography variant="body2" noWrap sx={{ maxWidth: 350 }}>
            {params.value || '—'}
          </Typography>
        </Tooltip>
      ),
    },
    {
      field: 'lat',
      headerName: 'Latitude',
      width: 125,
      renderCell: (params: GridRenderCellParams) => (
        <Typography variant="body2" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>
          {params.value !== null ? params.value : '—'}
        </Typography>
      ),
    },
    {
      field: 'lng',
      headerName: 'Longitude',
      width: 125,
      renderCell: (params: GridRenderCellParams) => (
        <Typography variant="body2" sx={{ fontFamily: 'monospace', fontSize: '0.8rem' }}>
          {params.value !== null ? params.value : '—'}
        </Typography>
      ),
    },
    {
      field: 'phone',
      headerName: 'Phone',
      width: 170,
      renderCell: (params: GridRenderCellParams) => (
        <Typography variant="caption" sx={{ color: 'text.secondary', fontFamily: 'monospace' }}>
          {params.value || '—'}
        </Typography>
      ),
    },
    {
      field: 'status',
      headerName: 'Status',
      width: 95,
      renderCell: (params: GridRenderCellParams) => (
        <Chip
          label={params.value}
          size="small"
          color={params.value === 'Active' ? 'success' : 'default'}
          sx={{ fontWeight: 600 }}
        />
      ),
    },
    {
      field: 'actions',
      headerName: 'Maps / GPS',
      width: 130,
      sortable: false,
      renderCell: (params: GridRenderCellParams) => (
        <Box sx={{ display: 'flex', gap: 0.5, alignItems: 'center' }}>
          <Tooltip title="Copy GPS Coordinates">
            <IconButton
              size="small"
              onClick={() => copyCoordinates(params.row.lat, params.row.lng)}
              disabled={params.row.lat === null}
            >
              <ContentCopy fontSize="small" />
            </IconButton>
          </Tooltip>
          {params.row.google_maps && (
            <Tooltip title="Open in Google Maps">
              <IconButton
                size="small"
                component="a"
                href={params.row.google_maps}
                target="_blank"
                rel="noopener noreferrer"
                color="primary"
              >
                <OpenInNew fontSize="small" />
              </IconButton>
            </Tooltip>
          )}
        </Box>
      ),
    },
  ];

  if (loading && stores.length === 0) return <LoadingState />;

  return (
    <Box>
      {/* Top Header */}
      <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'flex-start', mb: 3, flexWrap: 'wrap', gap: 2 }}>
        <Box>
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 1.5, mb: 0.5 }}>
            <LocationOn sx={{ fontSize: 32, color: 'primary.main' }} />
            <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em' }}>
              Store Locations &amp; Coordinates
            </Typography>
          </Box>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            Amasty Store Locator (<code>amasty_amlocator_location</code>) — 27 physical stores across Algerian wilayas with GPS coordinates.
          </Typography>
        </Box>

        <Box sx={{ display: 'flex', gap: 1, flexWrap: 'wrap' }}>
          <Button
            variant="contained"
            color="primary"
            startIcon={<FileDownload />}
            onClick={handleExportCsv}
          >
            Export CSV
          </Button>
          <Button
            variant="outlined"
            startIcon={<FileDownload />}
            onClick={handleExportJson}
          >
            Export JSON
          </Button>
          <Button
            variant="outlined"
            startIcon={<Refresh />}
            onClick={loadStores}
            disabled={loading}
          >
            Refresh
          </Button>
        </Box>
      </Box>

      {error && (
        <Alert severity="error" sx={{ mb: 3 }} onClose={() => setError(null)}>
          {error}
        </Alert>
      )}

      {/* KPI Cards */}
      <Grid container spacing={2} sx={{ mb: 3 }}>
        <Grid size={{ xs: 6, sm: 3 }}>
          <Card>
            <CardContent sx={{ py: 2, '&:last-child': { pb: 2 } }}>
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 0.5 }}>
                <Store sx={{ color: 'primary.main' }} />
                <Typography variant="h5" sx={{ fontWeight: 800 }}>
                  {stores.length}
                </Typography>
              </Box>
              <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                Total Stores
              </Typography>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 6, sm: 3 }}>
          <Card>
            <CardContent sx={{ py: 2, '&:last-child': { pb: 2 } }}>
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 0.5 }}>
                <LocationOn sx={{ color: 'success.main' }} />
                <Typography variant="h5" sx={{ fontWeight: 800, color: 'success.main' }}>
                  {stores.filter(s => s.is_active).length}
                </Typography>
              </Box>
              <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                Active Stores
              </Typography>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 6, sm: 3 }}>
          <Card>
            <CardContent sx={{ py: 2, '&:last-child': { pb: 2 } }}>
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 0.5 }}>
                <Public sx={{ color: 'info.main' }} />
                <Typography variant="h5" sx={{ fontWeight: 800, color: 'info.main' }}>
                  {distinctWilayas}
                </Typography>
              </Box>
              <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                Wilayas Covered
              </Typography>
            </CardContent>
          </Card>
        </Grid>

        <Grid size={{ xs: 6, sm: 3 }}>
          <Card>
            <CardContent sx={{ py: 2, '&:last-child': { pb: 2 } }}>
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 0.5 }}>
                <GpsFixed sx={{ color: 'warning.main' }} />
                <Typography variant="h5" sx={{ fontWeight: 800, color: 'warning.main' }}>
                  {stores.filter(s => s.lat !== null && s.lng !== null).length} / {stores.length}
                </Typography>
              </Box>
              <Typography variant="caption" sx={{ color: 'text.secondary' }}>
                Geocoded (Lat/Lng)
              </Typography>
            </CardContent>
          </Card>
        </Grid>
      </Grid>

      {/* Search and Table */}
      <Card>
        <CardContent>
          <Box sx={{ display: 'flex', justifyContent: 'space-between', alignItems: 'center', mb: 2, flexWrap: 'wrap', gap: 1 }}>
            <TextField
              size="small"
              placeholder="Search store name, city, wilaya, address..."
              value={search}
              onChange={e => setSearch(e.target.value)}
              sx={{ width: { xs: '100%', sm: 340 } }}
              slotProps={{
                input: {
                  startAdornment: (
                    <InputAdornment position="start">
                      <Search sx={{ fontSize: 20, color: 'text.disabled' }} />
                    </InputAdornment>
                  ),
                },
              }}
            />
            <Typography variant="caption" sx={{ color: 'text.secondary' }}>
              Showing {filteredStores.length} of {stores.length} stores
            </Typography>
          </Box>

          <Box sx={{ height: 600, width: '100%' }}>
            <DataGrid
              rows={filteredStores}
              columns={columns}
              pageSizeOptions={[10, 25, 50]}
              initialState={{
                pagination: { paginationModel: { pageSize: 25 } },
              }}
              disableRowSelectionOnClick
              rowHeight={52}
            />
          </Box>
        </CardContent>
      </Card>

      {toast && (
        <Snackbar
          open
          autoHideDuration={4000}
          onClose={() => setToast(null)}
          anchorOrigin={{ vertical: 'bottom', horizontal: 'right' }}
        >
          <Alert severity={toast.severity} onClose={() => setToast(null)}>
            {toast.message}
          </Alert>
        </Snackbar>
      )}
    </Box>
  );
}
