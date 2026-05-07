import React from 'react';
import { Container } from '@mui/material';
import InfraMonitoring from '../components/InfraMonitoring';

const InfrastructurePage: React.FC = () => {
  return (
    <Container maxWidth="xl" sx={{ py: 4 }}>
      <InfraMonitoring />
    </Container>
  );
};

export default InfrastructurePage;
