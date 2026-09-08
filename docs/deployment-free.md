# Free deployment (Neon + Render + Vercel)

A zero-cost deployment using each platform's free tier: no credit card
required anywhere in this guide. Trade-off: Render's free web services
sleep after ~15 minutes of inactivity and take a few seconds to wake up on
the next request — acceptable for a public portfolio project, not for
real production traffic (see `docs/security.md` for what else must change
before that).

## 0. What you'll end up with

- **Database**: a free Neon Postgres project.
- **`devaudit-ai-engine`** and **`devaudit-backend`**: two free Render web
  services, built directly from this repo's Dockerfiles via
  `render.yaml`.
- **Frontend**: a free Vercel deployment built from `frontend/`.

Deploy in this order — each step needs a value produced by the previous
one.

## 1. Database — Neon

1. Go to [neon.tech](https://neon.tech), sign up with GitHub (no card).
2. Create a project (any name/region).
3. Open the project's **Connection string**, "Pooled connection" tab, and
   copy it. It looks like:
   `postgresql://<user>:<password>@<host>/<db>?sslmode=require`
4. Doctrine needs a `serverVersion` and `charset` query param too. Take the
   string Neon gave you and turn it into:
   `postgresql://<user>:<password>@<host>/<db>?serverVersion=16&charset=utf8&sslmode=require`
5. Keep this — it's the `DATABASE_URL` value for Render in the next step.

## 2. Backend + AI engine — Render (one Blueprint deploy)

1. Go to [render.com](https://render.com), sign up with GitHub (no card).
2. **New** → **Blueprint**, connect your GitHub account, pick this repo.
   Render reads `render.yaml` at the repo root and proposes two services:
   `devaudit-ai-engine` and `devaudit-backend`.
3. Before clicking deploy, Render will ask you to fill in every env var
   marked `sync: false` in `render.yaml`. Use these values (ask Claude for
   the actual generated secret values from this session — they were
   printed in chat and are not stored in any file):

   **`devaudit-ai-engine`**
   | Key | Value |
   |---|---|
   | `AI_ENGINE_ANTHROPIC_API_KEY` | leave empty for now (mock provider) |
   | `AI_ENGINE_INTERNAL_SECRET` | the generated value — **use the same value on both services** |

   **`devaudit-backend`**
   | Key | Value |
   |---|---|
   | `APP_SECRET` | the generated value |
   | `DATABASE_URL` | the Neon connection string from step 1 |
   | `JWT_PASSPHRASE` | the generated value |
   | `JWT_PRIVATE_KEY_B64` | the generated value |
   | `JWT_PUBLIC_KEY_B64` | the generated value |
   | `AI_ENGINE_INTERNAL_SECRET` | **same value as above** |
   | `AI_ENGINE_BASE_URL` | leave as a placeholder for now — see step 4 |
   | `GITHUB_TOKEN` | optional, leave empty (60 req/hr instead of 5000/hr) |
   | `CORS_ALLOW_ORIGIN` | leave as a placeholder for now — see step 4 |

4. Deploy. Once both services are live, Render shows each one's URL
   (`https://<service-name>.onrender.com` if the name was free, otherwise
   Render appended a suffix — use whatever it actually assigned).
5. **Tell Claude the two real URLs.** They're needed to:
   - set `devaudit-backend`'s `AI_ENGINE_BASE_URL` to the ai-engine's URL,
   - set `devaudit-backend`'s `CORS_ALLOW_ORIGIN` once the frontend's
     Vercel URL is known (step 3),
   - update `frontend/src/environments/environment.ts`'s `apiUrl` to
     `https://<your-backend-url>/api`.
6. After changing env vars in Render's dashboard, the service redeploys
   itself automatically — no need to push anything for that part.

## 3. Frontend — Vercel

1. Go to [vercel.com](https://vercel.com), sign up with GitHub (no card).
2. **Add New** → **Project**, import this repo.
3. Set **Root Directory** to `frontend`. Vercel will pick up
   `frontend/vercel.json` for the build command, output directory, and
   the SPA rewrite rule (client-side routing needs every path to fall
   back to `index.html`).
4. Deploy. Vercel gives you a URL like `https://<project>.vercel.app`.
5. **Tell Claude this URL** so `CORS_ALLOW_ORIGIN` on `devaudit-backend`
   can be set to match it (e.g. `^https://<project>\.vercel\.app$`).

## 4. Closing the loop

Once you have both Render URLs and the Vercel URL:

1. Claude updates `frontend/src/environments/environment.ts`'s `apiUrl`
   to the real backend URL and commits/pushes — Vercel redeploys
   automatically on push.
2. You update `AI_ENGINE_BASE_URL` and `CORS_ALLOW_ORIGIN` in Render's
   dashboard for `devaudit-backend` (redeploys automatically on save).
3. Visit the Vercel URL, register an account, add a small public
   repository, and run an audit to confirm the whole chain works.

## Known trade-offs of this specific free setup

- Both Render services sleep after inactivity; the first request after a
  while wakes them up (a few seconds' delay), including the *first* load
  of the dashboard if the backend was asleep.
- `AI_ENGINE_PROVIDER=mock` by default — set it to `anthropic` and add a
  real `AI_ENGINE_ANTHROPIC_API_KEY` on `devaudit-ai-engine` if you want
  genuine AI-generated text instead of the mock provider's canned
  responses.
- Render's free plan has no persistent disk — this is fine here, since
  the only things written to disk (the JWT keypair at boot, the ingestion
  workspace per scan) are either re-derived from env vars or meant to be
  ephemeral anyway.
