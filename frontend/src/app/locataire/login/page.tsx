import { PasswordLogin } from '@/components/login-forms';
import { DEMO } from '@/lib/demo';

export default function RenterLogin() {
  return <PasswordLogin title="Locataire" identifier={DEMO.renter} next="/locataire" />;
}
