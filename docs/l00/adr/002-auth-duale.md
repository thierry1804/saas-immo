# ADR-002 — Authentification duale

## Statut

Accepté — 22 novembre 2026

## Contexte

Deux surfaces : back-office Twig et PWA agent Next.js, avec MFA, appareils et horizon 48 h.

## Décision

- **Admin Twig** : sessions Symfony Security (cookie HttpOnly, Secure, SameSite) + CSRF
- **Terrain / Next.js** : JWT d’accès court + refresh token **opaque** stocké côté serveur (révocation, rotation)
- Inscription appareil + PIN / OTP selon les contrats Identity
- Réutilisation des mêmes capacités (rôles, appartenances) quel que soit le mécanisme

## Conséquences

- Firewall / authenticators distincts dans Security
- Table de refresh tokens hachés, révocables
- Pas de JWT longue durée comme seul secret de session agent
