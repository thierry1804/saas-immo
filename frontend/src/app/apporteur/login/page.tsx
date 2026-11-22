import { OtpLogin } from '@/components/login-forms';
import { DEMO } from '@/lib/demo';

export default function ReferrerLogin() {
  return <OtpLogin title="Apporteur" destination={DEMO.referrerPhone} next="/apporteur/commissions" />;
}
