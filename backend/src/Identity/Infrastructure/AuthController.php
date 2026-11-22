<?php

declare(strict_types=1);

namespace App\Identity\Infrastructure;

use App\Identity\Application\AuthService;
use App\SharedKernel\Http\ApiException;
use App\SharedKernel\Http\ApiResponder;
use App\SharedKernel\Http\Input;
use App\SharedKernel\Http\JsonBody;
use Symfony\Component\HttpFoundation\Cookie;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/api/v1/auth')]
final class AuthController
{
    public function __construct(
        private readonly AuthService $auth,
        private readonly ApiResponder $responder,
        private readonly JsonBody $json,
    ) {
    }

    #[Route('/password', methods: ['POST'])]
    public function password(Request $request): JsonResponse
    {
        $body = $this->json->object($request);

        return $this->responder->ok($this->auth->password(
            Input::text($body, 'identifier', 200),
            Input::text($body, 'password', 200),
        ));
    }

    #[Route('/mfa', methods: ['POST'])]
    public function mfa(Request $request): JsonResponse
    {
        $body = $this->json->object($request);
        $data = $this->auth->mfa(Input::text($body, 'mfa_token', 4000), Input::text($body, 'code', 12));

        return $this->withCookie($this->responder->ok($data), $data, $request);
    }

    #[Route('/otp/request', methods: ['POST'])]
    public function otpRequest(Request $request): JsonResponse
    {
        $body = $this->json->object($request);

        return $this->responder->ok($this->auth->requestOtp(
            Input::text($body, 'channel', 16),
            Input::text($body, 'destination', 200),
            Input::text($body, 'intent', 32),
        ));
    }

    #[Route('/otp/verify', methods: ['POST'])]
    public function otpVerify(Request $request): JsonResponse
    {
        $body = $this->json->object($request);
        $data = $this->auth->verifyOtp(Input::text($body, 'challenge_id', 80), Input::text($body, 'code', 12));

        return $this->withCookie($this->responder->ok($data), $data, $request);
    }

    #[Route('/token/refresh', methods: ['POST'])]
    public function refresh(Request $request): JsonResponse
    {
        $body = $this->json->object($request);
        $data = $this->auth->refresh(Input::text($body, 'refresh_token', 200));

        return $this->withCookie($this->responder->ok($data), $data, $request);
    }

    #[Route('/magic/request', methods: ['POST'])]
    public function magicRequest(Request $request): JsonResponse
    {
        $body = $this->json->object($request);
        $destination = $body['destination'] ?? $body['email'] ?? null;
        if (!is_string($destination) || trim($destination) === '') {
            throw new ApiException(422, 'UNPROCESSABLE', 'Adresse requise.', false, ['destination' => ['Texte requis.']]);
        }

        return $this->responder->ok($this->auth->requestMagic($destination));
    }

    #[Route('/magic/exchange', methods: ['POST'])]
    public function magicExchange(Request $request): JsonResponse
    {
        $body = $this->json->object($request);
        $data = $this->auth->exchangeMagic(Input::text($body, 'token', 200));

        return $this->withCookie($this->responder->ok($data), $data, $request);
    }

    /**
     * @param array<string, mixed> $data
     */
    private function withCookie(JsonResponse $response, array $data, Request $request): JsonResponse
    {
        $token = $data['access_token'] ?? null;
        $ttl = $data['expires_in'] ?? 900;
        if (!is_string($token) || !is_int($ttl)) {
            return $response;
        }
        $response->headers->setCookie(
            Cookie::create('veille_access', $token)
                ->withHttpOnly(true)
                ->withSecure($request->isSecure())
                ->withSameSite(Cookie::SAMESITE_LAX)
                ->withPath('/')
                ->withExpires(time() + $ttl),
        );

        return $response;
    }
}
