/**
 * InfrastructurePage — thin wrapper that renders InfraMonitoring
 * without the extra Container that was adding unwanted padding/constraints.
 */
import InfraMonitoring from '../components/InfraMonitoring';

export default function InfrastructurePage() {
  return <InfraMonitoring />;
}
