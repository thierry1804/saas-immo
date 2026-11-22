import { PasswordLogin } from '@/components/login-forms';
import { DEMO } from '@/lib/demo';

export default function OwnerLogin() {
  return <PasswordLogin title="Propriétaire" identifier={DEMO.owner} next="/proprietaire" />;
}
