<?php

declare(strict_types=1);

namespace App\Identity\Application;

use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Ids\Uuid;
use App\SharedKernel\Persistence\Database;
use App\SharedKernel\Security\AccessTokenCodec;
use App\SharedKernel\Security\Membership;

final class AuthService
{
    private readonly string $dummyHash;

    public function __construct(
        private readonly Database $database,
        private readonly AccessTokenCodec $tokens,
        private readonly string $appEnv,
    ) {
        $this->dummyHash = password_hash('not-a-veille-password', PASSWORD_BCRYPT);
    }

    /**
     * @return array<string, mixed>
     */
    public function password(string $identifier, string $password): array
    {
        $this->hit('password:'.strtolower(trim($identifier)));
        [$kind, $value] = $this->normalize($identifier);
        $account = $kind === 'email'
            ? $this->database->fetch('SELECT id, password_hash, status FROM account WHERE email_normalized = :v', ['v' => $value])
            : $this->database->fetch('SELECT id, password_hash, status FROM account WHERE phone_normalized = :v', ['v' => $value]);
        $hash = is_array($account) && is_string($account['password_hash']) ? $account['password_hash'] : $this->dummyHash;
        $valid = password_verify($password, $hash);
        if (!is_array($account) || $account['status'] !== 'active' || !is_string($account['password_hash']) || !$valid) {
            throw new ApiException(401, 'UNAUTHORIZED', 'Identifiants refusés.');
        }
        $this->clearHit('password:'.strtolower(trim($identifier)));
        $challengeId = Uuid::v4();
        $this->database->execute(
            'INSERT INTO auth_challenge (id, account_id, purpose, code_hash, attempts, expires_at)
             VALUES (:id, :account, \'mfa\', :hash, 0, now() + interval \'5 minutes\')',
            [
                'id' => $challengeId,
                'account' => $account['id'],
                'hash' => password_hash((string) random_int(100000, 999999), PASSWORD_BCRYPT),
            ],
        );
        $mfa = $this->tokens->issue([
            'sub' => $account['id'],
            'typ' => 'mfa',
            'challenge_id' => $challengeId,
            'exp' => time() + 300,
            'iat' => time(),
        ]);

        return ['mfa_required' => true, 'mfa_token' => $mfa];
    }

    /**
     * @return array<string, mixed>
     */
    public function mfa(string $mfaToken, string $code): array
    {
        $claims = $this->tokens->parse($mfaToken, 'mfa');
        $challengeId = $claims['challenge_id'] ?? null;
        if (!is_string($challengeId)) {
            throw new ApiException(401, 'UNAUTHORIZED', 'Second facteur refusé.');
        }
        $accountId = $this->consumeCode($challengeId, $code, 'mfa');

        return $this->issueSession($accountId);
    }

    /**
     * @return array<string, mixed>
     */
    public function requestOtp(string $channel, string $destination, string $intent): array
    {
        if (!in_array($channel, ['sms', 'email'], true) || !in_array($intent, ['login', 'device_enroll', 'magic_link'], true)) {
            throw new ApiException(422, 'UNPROCESSABLE', 'Canal ou intention invalide.');
        }
        [$kind, $value] = $channel === 'email'
            ? ['email', strtolower(trim($destination))]
            : $this->normalize($destination);
        if ($channel === 'sms') {
            [, $value] = $this->normalize($destination);
            $kind = 'phone';
        }
        $this->hit('otp:'.hash('sha256', $kind.':'.$value));
        $account = $kind === 'email'
            ? $this->database->fetch('SELECT id, status FROM account WHERE email_normalized = :v', ['v' => $value])
            : $this->database->fetch('SELECT id, status FROM account WHERE phone_normalized = :v', ['v' => $value]);
        $challengeId = Uuid::v4();
        $accountId = is_array($account) && $account['status'] === 'active' ? $account['id'] : null;
        $this->database->execute(
            'INSERT INTO auth_challenge (id, account_id, purpose, destination_hash, code_hash, attempts, expires_at)
             VALUES (:id, NULLIF(:account, \'\')::uuid, \'otp\', :dest, :hash, 0, now() + interval \'5 minutes\')',
            [
                'id' => $challengeId,
                'account' => $accountId ?? '',
                'dest' => hash('sha256', $kind.':'.$value),
                'hash' => password_hash((string) random_int(100000, 999999), PASSWORD_BCRYPT),
            ],
        );

        return ['accepted' => true, 'challenge_id' => $challengeId];
    }

    /**
     * @return array<string, mixed>
     */
    public function verifyOtp(string $challengeId, string $code): array
    {
        if (preg_match('/^\d{6}$/', $code) !== 1 || !Uuid::is($challengeId)) {
            throw new ApiException(401, 'UNAUTHORIZED', 'Code refusé.');
        }
        $accountId = $this->consumeCode($challengeId, $code, 'otp');

        return $this->issueSession($accountId);
    }

    /**
     * @return array<string, mixed>
     */
    public function requestMagic(string $destination): array
    {
        $email = strtolower(trim($destination));
        $this->hit('magic:'.$email);
        $account = $this->database->fetch(
            'SELECT id, status FROM account WHERE email_normalized = :v',
            ['v' => $email],
        );
        $token = bin2hex(random_bytes(32));
        $accountId = is_array($account) && $account['status'] === 'active' ? $account['id'] : null;
        $this->database->execute(
            'INSERT INTO auth_challenge (id, account_id, purpose, destination_hash, token_hash, attempts, expires_at)
             VALUES (:id, NULLIF(:account, \'\')::uuid, \'magic\', :dest, :hash, 0, now() + interval \'15 minutes\')',
            [
                'id' => Uuid::v4(),
                'account' => $accountId ?? '',
                'dest' => hash('sha256', 'email:'.$email),
                'hash' => hash('sha256', $token),
            ],
        );
        $data = ['accepted' => true];
        if ($this->appEnv === 'dev') {
            $data['dev_token'] = $accountId === null ? null : $token;
        }

        return $data;
    }

    /**
     * @return array<string, mixed>
     */
    public function exchangeMagic(string $token): array
    {
        if (strlen($token) < 32) {
            throw new ApiException(401, 'UNAUTHORIZED', 'Lien refusé.');
        }
        $hash = hash('sha256', $token);

        return $this->database->connection()->transactional(function () use ($hash): array {
            $row = $this->database->fetch(
                'SELECT id, account_id, consumed_at, expires_at FROM auth_challenge
                 WHERE token_hash = :hash AND purpose = \'magic\'
                 FOR UPDATE',
                ['hash' => $hash],
            );
            if ($row === null || $row['consumed_at'] !== null || $row['account_id'] === null) {
                throw new ApiException(401, 'UNAUTHORIZED', 'Lien refusé.');
            }
            if (strtotime((string) $row['expires_at']) < time()) {
                throw new ApiException(401, 'UNAUTHORIZED', 'Lien expiré.');
            }
            $updated = $this->database->execute(
                'UPDATE auth_challenge SET consumed_at = now() WHERE id = :id AND consumed_at IS NULL',
                ['id' => $row['id']],
            );
            if ($updated !== 1) {
                throw new ApiException(401, 'UNAUTHORIZED', 'Lien déjà utilisé.');
            }

            return $this->issueSession((string) $row['account_id']);
        });
    }

    /**
     * @return array<string, mixed>
     */
    public function refresh(string $refreshToken): array
    {
        $hash = hash('sha256', $refreshToken);

        return $this->database->connection()->transactional(function () use ($hash): array {
            $row = $this->database->fetch(
                'SELECT id, account_id, expires_at, revoked_at FROM refresh_token WHERE token_hash = :hash FOR UPDATE',
                ['hash' => $hash],
            );
            if ($row === null || $row['revoked_at'] !== null || strtotime((string) $row['expires_at']) < time()) {
                throw new ApiException(401, 'UNAUTHORIZED', 'Session absente ou expirée.');
            }
            $this->database->execute(
                'UPDATE refresh_token SET revoked_at = now() WHERE id = :id',
                ['id' => $row['id']],
            );

            return $this->issueSession((string) $row['account_id']);
        });
    }

    public function adapterToken(): string
    {
        return hash('sha256', $this->appSecret.':simulated-adapter');
    }

    /**
     * @return array<string, mixed>
     */
    private function issueSession(string $accountId): array
    {
        $rows = $this->database->fetchAll(
            'SELECT m.organization_id, m.roles, m.permissions, o.name
             FROM membership m
             JOIN organization o ON o.id = m.organization_id
             WHERE m.account_id = :id AND m.status = \'active\'
             ORDER BY o.name',
            ['id' => $accountId],
        );
        $refresh = bin2hex(random_bytes(32));
        if ($rows !== []) {
            $this->database->execute(
                'INSERT INTO refresh_token (id, organization_id, account_id, token_hash, expires_at)
                 VALUES (:id, :org, :account, :hash, now() + interval \'7 days\')',
                [
                    'id' => Uuid::v4(),
                    'org' => $rows[0]['organization_id'],
                    'account' => $accountId,
                    'hash' => hash('sha256', $refresh),
                ],
            );
        }
        $authTime = time();
        $access = $this->tokens->issue([
            'sub' => $accountId,
            'typ' => 'access',
            'auth_time' => $authTime,
            'iat' => $authTime,
            'exp' => $authTime + 900,
            'jti' => Uuid::v4(),
        ]);
        $memberships = [];
        foreach ($rows as $row) {
            $memberships[] = [
                'organization_id' => $row['organization_id'],
                'name' => $row['name'],
                'roles' => Membership::pgArray((string) $row['roles']),
            ];
        }

        return [
            'access_token' => $access,
            'token_type' => 'Bearer',
            'expires_in' => 900,
            'refresh_token' => $rows === [] ? null : $refresh,
            'memberships' => $memberships,
        ];
    }

    private function consumeCode(string $challengeId, string $code, string $purpose): string
    {
        return $this->database->connection()->transactional(function () use ($challengeId, $code, $purpose): string {
            $row = $this->database->fetch(
                'SELECT id, account_id, purpose, code_hash, attempts, expires_at, consumed_at
                 FROM auth_challenge WHERE id = :id FOR UPDATE',
                ['id' => $challengeId],
            );
            if (
                $row === null
                || $row['purpose'] !== $purpose
                || $row['consumed_at'] !== null
                || $row['account_id'] === null
                || strtotime((string) $row['expires_at']) < time()
            ) {
                throw new ApiException(401, 'UNAUTHORIZED', 'Code refusé.');
            }
            if ((int) $row['attempts'] >= 5) {
                throw new ApiException(401, 'UNAUTHORIZED', 'Code refusé.');
            }
            $devBypass = $this->appEnv === 'dev' && $code === '000000';
            $hashOk = is_string($row['code_hash']) && password_verify($code, $row['code_hash']);
            if (!$devBypass && !$hashOk) {
                $attempts = (int) $row['attempts'] + 1;
                $this->database->execute(
                    'UPDATE auth_challenge SET attempts = :attempts, consumed_at = CASE WHEN :attempts >= 5 THEN now() ELSE consumed_at END WHERE id = :id',
                    ['attempts' => $attempts, 'id' => $row['id']],
                );
                throw new ApiException(401, 'UNAUTHORIZED', 'Code refusé.');
            }
            $this->database->execute(
                'UPDATE auth_challenge SET consumed_at = now() WHERE id = :id AND consumed_at IS NULL',
                ['id' => $row['id']],
            );

            return (string) $row['account_id'];
        });
    }

    /**
     * @return array{0: string, 1: string}
     */
    private function normalize(string $identifier): array
    {
        $value = trim($identifier);
        if (str_contains($value, '@')) {
            return ['email', strtolower($value)];
        }
        $phone = preg_replace('/[^\d+]/', '', $value) ?? '';

        return ['phone', $phone];
    }

    private function hit(string $key): void
    {
        $hash = hash('sha256', $key);
        $row = $this->database->fetch(
            'SELECT hits, window_started, blocked_until FROM auth_throttle WHERE key_hash = :h',
            ['h' => $hash],
        );
        if ($row !== null && $row['blocked_until'] !== null && strtotime((string) $row['blocked_until']) > time()) {
            throw new ApiException(429, 'TOO_MANY_REQUESTS', 'Trop de tentatives. Réessayez plus tard.', true);
        }
        $windowFresh = $row === null || strtotime((string) $row['window_started']) < time() - 300;
        if ($windowFresh) {
            $this->database->execute(
                'INSERT INTO auth_throttle (key_hash, hits, window_started, blocked_until)
                 VALUES (:h, 1, now(), NULL)
                 ON CONFLICT (key_hash) DO UPDATE SET hits = 1, window_started = now(), blocked_until = NULL',
                ['h' => $hash],
            );

            return;
        }
        $hits = (int) $row['hits'] + 1;
        $blocked = $hits >= 5 ? (new \DateTimeImmutable('+15 minutes'))->format('Y-m-d H:i:sP') : '';
        $this->database->execute(
            'UPDATE auth_throttle SET hits = :hits, blocked_until = NULLIF(:blocked, \'\')::timestamptz WHERE key_hash = :h',
            ['hits' => $hits, 'blocked' => $blocked, 'h' => $hash],
        );
        if ($hits >= 5) {
            throw new ApiException(429, 'TOO_MANY_REQUESTS', 'Trop de tentatives. Réessayez plus tard.', true);
        }
    }

    private function clearHit(string $key): void
    {
        $this->database->execute(
            'DELETE FROM auth_throttle WHERE key_hash = :h',
            ['h' => hash('sha256', $key)],
        );
    }
}
