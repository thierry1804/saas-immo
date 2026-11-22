# Veille — plateforme conciergerie

Monorepo plat : API Symfony (`backend/`) + PWA Next.js (`frontend/`).

## Documentation

| Emplacement | Rôle |
|---|---|
| [`specs/`](specs/) | Norme métier (fonctionnel, contrats, recette, stack) |
| [`docs/l00/`](docs/l00/) | Livrables exécutables L00 (OpenAPI, SQL/RLS, ADR, maquettes) |

Ordre de lecture L00 : [`docs/l00/README.md`](docs/l00/README.md).

## Prérequis

- Docker + Docker Compose
- PHP 8.3+ / Composer (dev local backend)
- Node 22+ / npm (dev local frontend)

## Démarrage infra

```bash
cp .env.example .env
docker compose up -d postgres redis minio mailpit
```

Schéma tranche 1 + RLS : montés dans Postgres au premier démarrage (`docs/l00/schema/`).

## Backend

```bash
cd backend
cp .env .env.local  # ou aligner DATABASE_URL sur .env.example racine
composer install
php -S 127.0.0.1:8000 -t public
```

Domaines stubs : `src/Identity`, `Property`, `Fieldwork`, `Evidence` (+ SharedKernel / Infrastructure).

## Frontend (PWA agent)

```bash
cd frontend
cp ../.env.example .env.local   # NEXT_PUBLIC_API_BASE_URL
npm install
npm run dev
```

## Maquettes HTML

Ouvrir [`docs/l00/ui/index.html`](docs/l00/ui/index.html) dans un navigateur.

## Stack retenue

Symfony 7 LTS, API Platform + commandes, Doctrine, Messenger, Twig admin, Next.js PWA, PostgreSQL 16, Redis, MinIO. Détails : [`specs/Veille-stack-technique.md`](specs/Veille-stack-technique.md).
