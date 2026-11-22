import { OtpLogin } from '@/components/login-forms';
import { DEMO } from '@/lib/demo';

export default function AgentLogin() {
  return <OtpLogin title="Agent" destination={DEMO.agentPhone} next="/agent/mission" />;
}
