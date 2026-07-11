import { useState, useEffect, useCallback } from 'react';
import {
  Box, Typography, Card, CardContent, Table, TableBody, TableCell,
  TableContainer, TableHead, TableRow, Button, Chip, IconButton,
  Tooltip, Alert, LinearProgress, Divider, Accordion, AccordionSummary, AccordionDetails
} from '@mui/material';
import {
  CloudDownload, Refresh, Storage, AccountCircle,
  Settings, CheckCircle, HourglassEmpty, Error,
  ExpandMore, Folder, Build
} from '@mui/icons-material';
import LoadingState from '../components/common/LoadingState';
import apiClient from '../api/client';

interface BackupFile {
  name: string;
  file: string;
  size: number;
  size_human: string;
  date: string;
  status: string;
  is_legacy?: boolean;
}

interface BackupGroup {
  id: string;
  label: string;
  date: string;
  total_size: number;
  total_size_human: string;
  accounts: BackupFile[];
  databases: BackupFile[];
  system: BackupFile[];
  configs: BackupFile[];
}

interface BackupData {
  groups: BackupGroup[];
  whm_running: boolean;
  current_account: string;
}

export default function BackupsPage() {
  const [data, setData] = useState<BackupData | null>(null);
  const [loading, setLoading] = useState(true);
  const [error, setError] = useState('');
  const [downloading, setDownloading] = useState<string | null>(null);

  const loadBackups = useCallback(async () => {
    try {
      setLoading(true);
      setError('');
      const { data: res } = await apiClient.get('/api/backups.php?action=list');
      if (res.error && !res.groups) {
        setError(res.error);
      } else {
        setData(res);
      }
    } catch (e: any) {
      setError(e.response?.data?.error || e.message || 'Failed to load backups');
    } finally {
      setLoading(false);
    }
  }, []);

  useEffect(() => { loadBackups(); }, [loadBackups]);

  const handleDownload = (file: BackupFile, type: string, groupId: string) => {
    setDownloading(file.file);
    const url = `/api/backups.php?action=download&type=${type}&file=${file.file}&group=${groupId}`;
    const a = document.createElement('a');
    a.href = url;
    a.download = file.file;
    document.body.appendChild(a);
    a.click();
    document.body.removeChild(a);
    setTimeout(() => setDownloading(null), 3000);
  };

  if (loading && !data) return <LoadingState message="Loading backup inventory..." />;

  const StatusChip = ({ status }: { status: string }) => {
    if (status === 'ready') return <Chip icon={<CheckCircle />} label="Ready" size="small" color="success" variant="outlined" />;
    if (status === 'in_progress') return <Chip icon={<HourglassEmpty />} label="In Progress" size="small" color="warning" variant="outlined" />;
    return <Chip icon={<Error />} label="Error" size="small" color="error" variant="outlined" />;
  };

  const renderFileTable = (files: BackupFile[], type: string, groupId: string) => {
    if (!files || files.length === 0) return null;
    return (
      <TableContainer>
        <Table size="small">
          <TableHead>
            <TableRow>
              <TableCell sx={{ fontWeight: 700, width: '35%' }}>Name</TableCell>
              <TableCell sx={{ fontWeight: 700, width: '15%' }}>Size</TableCell>
              <TableCell sx={{ fontWeight: 700, width: '20%' }}>Date</TableCell>
              <TableCell sx={{ fontWeight: 700, width: '15%' }}>Status</TableCell>
              <TableCell sx={{ fontWeight: 700, width: '15%' }} align="right">Action</TableCell>
            </TableRow>
          </TableHead>
          <TableBody>
            {files.map((f) => (
              <TableRow key={f.file + f.name} hover>
                <TableCell>
                  <Typography variant="body2" sx={{ fontFamily: 'monospace', fontWeight: 600 }}>
                    {f.name}
                  </Typography>
                </TableCell>
                <TableCell>
                  <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                    {f.size_human}
                  </Typography>
                </TableCell>
                <TableCell>
                  <Typography variant="body2" sx={{ color: 'text.secondary' }}>
                    {f.date}
                  </Typography>
                </TableCell>
                <TableCell><StatusChip status={f.status} /></TableCell>
                <TableCell align="right">
                  <Tooltip title={f.status === 'ready' ? `Download ${f.name}` : 'File still being created'}>
                    <span>
                      <IconButton
                        size="small"
                        color="primary"
                        disabled={f.status !== 'ready' || !!f.is_legacy}
                        onClick={() => handleDownload(f, type, groupId)}
                      >
                        <CloudDownload fontSize="small" />
                      </IconButton>
                    </span>
                  </Tooltip>
                  {f.is_legacy && (
                    <Tooltip title="Legacy extracted directory — use SSH/SCP to download">
                      <Chip label="SSH only" size="small" sx={{ ml: 1 }} />
                    </Tooltip>
                  )}
                </TableCell>
              </TableRow>
            ))}
          </TableBody>
        </Table>
      </TableContainer>
    );
  };

  const renderGroup = (group: BackupGroup, index: number) => {
    const hasAccounts = group.accounts && group.accounts.length > 0;
    const hasDatabases = group.databases && group.databases.length > 0;
    const hasSystem = group.system && group.system.length > 0;
    const hasConfigs = group.configs && group.configs.length > 0;
    const totalFiles = (group.accounts?.length || 0) + (group.databases?.length || 0) + (group.system?.length || 0) + (group.configs?.length || 0);

    return (
      <Accordion key={group.id} defaultExpanded={index === 0} sx={{ mb: 2 }}>
        <AccordionSummary expandIcon={<ExpandMore />}>
          <Box sx={{ display: 'flex', alignItems: 'center', gap: 2, width: '100%' }}>
            <Folder color="primary" />
            <Typography sx={{ fontWeight: 700, flex: 1 }}>{group.label}</Typography>
            <Chip label={`${totalFiles} files`} size="small" />
            <Chip label={group.total_size_human} size="small" color="info" variant="outlined" />
          </Box>
        </AccordionSummary>
        <AccordionDetails>
          {hasAccounts && (
            <Box sx={{ mb: 2 }}>
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1 }}>
                <AccountCircle color="primary" fontSize="small" />
                <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>cPanel Accounts</Typography>
              </Box>
              {renderFileTable(group.accounts, 'accounts', group.id)}
            </Box>
          )}

          {hasAccounts && hasDatabases && <Divider sx={{ my: 2 }} />}

          {hasDatabases && (
            <Box sx={{ mb: 2 }}>
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1 }}>
                <Storage color="secondary" fontSize="small" />
                <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>Database Dumps</Typography>
              </Box>
              {renderFileTable(group.databases, 'databases', group.id)}
            </Box>
          )}

          {(hasAccounts || hasDatabases) && hasSystem && <Divider sx={{ my: 2 }} />}

          {hasSystem && (
            <Box sx={{ mb: 2 }}>
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1 }}>
                <Settings fontSize="small" />
                <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>System Configs</Typography>
              </Box>
              {renderFileTable(group.system, 'system', group.id)}
            </Box>
          )}

          {(hasAccounts || hasDatabases || hasSystem) && hasConfigs && <Divider sx={{ my: 2 }} />}

          {hasConfigs && (
            <Box>
              <Box sx={{ display: 'flex', alignItems: 'center', gap: 1, mb: 1 }}>
                <Build color="warning" fontSize="small" />
                <Typography variant="subtitle2" sx={{ fontWeight: 700 }}>Server Configurations</Typography>
              </Box>
              {renderFileTable(group.configs, 'configs', group.id)}
            </Box>
          )}
        </AccordionDetails>
      </Accordion>
    );
  };

  const groups = data?.groups || [];

  return (
    <Box>
      <Box sx={{ mb: 3, display: 'flex', justifyContent: 'space-between', alignItems: 'center' }}>
        <Box>
          <Typography variant="h4" sx={{ fontWeight: 800, letterSpacing: '-0.03em', mb: 0.5 }}>
            Server Backups
          </Typography>
          <Typography variant="body2" sx={{ color: 'text.secondary' }}>
            {groups.length > 0
              ? `${groups.length} backup group${groups.length !== 1 ? 's' : ''} available — click to expand and download individually`
              : 'No backup data available'}
          </Typography>
        </Box>
        <Button startIcon={<Refresh />} variant="outlined" onClick={loadBackups} disabled={loading}>
          Refresh
        </Button>
      </Box>

      {data?.whm_running && (
        <Alert severity="info" sx={{ mb: 3 }} icon={<HourglassEmpty />}>
          WHM backup is currently running
          {data.current_account ? ` — processing: ${data.current_account}` : ''}
          . Refresh to see progress.
        </Alert>
      )}

      {error && <Alert severity="error" sx={{ mb: 3 }}>{error}</Alert>}

      {loading && <LinearProgress sx={{ mb: 2 }} />}

      {downloading && (
        <Alert severity="success" sx={{ mb: 2 }} onClose={() => setDownloading(null)}>
          Download started: {downloading}
        </Alert>
      )}

      {groups.map((group, i) => renderGroup(group, i))}

      {groups.length === 0 && !loading && !error && (
        <Alert severity="warning" sx={{ mb: 3 }}>
          No backups found on this server. Run a WHM backup to populate this page.
        </Alert>
      )}

      <Box sx={{ mt: 3, p: 2, background: 'rgba(0,0,0,0.2)', borderRadius: 1 }}>
        <Typography variant="caption" sx={{ color: 'text.disabled', display: 'block' }}>
          Backups generated by WHM (pkgacct), MariaDB 10.6.17 mysqldump on port 3307, and streamlined backup script (server configs).
        </Typography>
        <Typography variant="caption" sx={{ color: 'text.disabled', display: 'block' }}>
          Server: ded701.inmotionhosting.com | Use wget -c for resumable downloads of large files.
        </Typography>
      </Box>
    </Box>
  );
}
