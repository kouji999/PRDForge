# PRDForge

**AI Product Discovery & PRD Generation Platform** — berdiskusi dengan AI untuk menggali requirement, lalu ubah percakapan menjadi PRD terstruktur, versioned, dan siap jadi blueprint development.

<p align="center"><strong>Ide mentah → Discovery → Requirement → Readiness → PRD → Review → Approve v1.0</strong></p>

---

## Fitur

- **AI Discovery Chat** — streaming SSE, context-aware (project context + requirement + PRD state selalu jadi source of truth)
- **Requirement Extraction** — AI ekstrak percakapan → konteks terstruktur + requirement (status: proposed → confirmed/rejected, user selalu di kendali)
- **Readiness Engine** — criteria-based (6 kriteria), bukan angka random; project baru bisa generate PRD saat ready
- **PRD Generation** — 21 section canonical, background job chunked + progress bar + auto-resume
- **PRD Workspace** — section-level edit, AI actions (rewrite/expand/simplify/review/contradictions) dengan **safety flow** (proposal → diff preview → confirm → apply)
- **Immutable Versioning** — snapshot v0.1, v0.2… v1.0 saat approve
- **AI Review** — gaps, contradictions, suggestions, verdict
- **Provider Agnostic** — semua provider OpenAI-compatible (Tokenrouter, 9Router, OpenAI, Groq, custom gateway); API key encrypted at rest, tidak pernah dikirim balik ke frontend
- **Test Connection** — validasi URL/key/model dengan error ter-normalisasi (invalid key / bad URL / unknown model / timeout / rate limit)
- **Observability** — AI usage log per operation (token, latency, error category; no secrets, no raw prompts)
- **Export** — PRD → Markdown siap share

## Stack

| Layer | Teknologi |
|---|---|
| Backend | Laravel 13, PHP 8.3, SQLite (default) |
| Frontend | Inertia 2, React 19, TypeScript strict, Tailwind CSS 4 |
| AI | OpenAI-compatible adapter (provider-agnostic) |
| Queue | Database driver (PRD generation chunked background job) |

## Setup

```bash
# 1. Install dependencies
composer install
npm install

# 2. Environment
cp .env.example .env
php artisan key:generate

# Konfigurasi AI provider default (opsional — user bisa tambah via UI Settings):
# AI_DEFAULT_BASE_URL=https://api.tokenrouter.com/v1
# AI_DEFAULT_API_KEY=sk-...
# AI_DEFAULT_MODEL=z-ai/glm-5.3-free

# 3. Database + build
php artisan migrate
npm run build

# 4. Jalankan
php artisan serve --port=8187        # app
php artisan queue:work               # PRD generation worker (WAJIB berjalan)
```

Atau satu perintah di Windows: `./dev.ps1` (start/stop/status semua proses).

## Struktur

```
app/
├── Domain/                    # Business logic — controllers tetap tipis
│   ├── Ai/                    # Provider adapter, ContextBuilder, PromptLibrary,
│   │   │                      # ErrorNormalizer, AiLogger (observability)
│   │   └── Support/
│   ├── Conversation/          # Chat engine (streaming + persist)
│   ├── Project/               # Status lifecycle (transition map, domain-guarded)
│   ├── Prd/                   # SectionRegistry, actions, versioning
│   └── Requirement/           # Extraction, ReadinessEngine (criteria-based)
├── Http/Controllers/          # Tipis: validasi + authorize + delegate
├── Jobs/GeneratePrdJob.php    # Chunked generation + progress + resume
├── Policies/                  # Ownership (resource → project → user)
└── Services/PrdGenerationService.php
```

## Testing

```bash
php artisan test    # 27 tests / 62 assertions
npx tsc --noEmit    # strict, zero error
vendor/bin/pint     # PSR-12
```

## Keamanan

- Session auth + CSRF; semua resource scoped per-user via Policy
- API key provider: `encrypted` cast (AES-256), masked di semua response/log
- Rate limit per-operation di semua AI endpoint (`throttle.ai:chat|extract|generate|action|review|test`)
- Error provider dinormalisasi — tidak pernah bocorkan raw error/key ke user
- Prompt injection defense di system prompt + structured output validation sebelum masuk DB

---

**Author:** Raliq Hidayat BM3
