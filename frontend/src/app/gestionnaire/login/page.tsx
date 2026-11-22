import { PasswordLogin } from '@/components/login-forms';
import { DEMO } from '@/lib/demo';

export default function ManagerLogin() {
  return (
    <PasswordLogin
      title="Gestionnaire"
      identifier={DEMO.manager}
      next="/gestionnaire"
    />
  );
}
