import hmac

from fastapi import Header, HTTPException

from app.config import get_settings


def verify_internal_secret(x_internal_auth: str | None = Header(default=None)) -> None:
    """
    Only the Symfony backend should ever call these endpoints. When
    AI_ENGINE_INTERNAL_SECRET is unset (local/dev only, never in
    docker-compose's default env), the check is skipped rather than locking
    everyone out of a fresh checkout.
    """
    settings = get_settings()
    if not settings.internal_secret:
        return

    if x_internal_auth is None or not hmac.compare_digest(x_internal_auth, settings.internal_secret):
        raise HTTPException(status_code=401, detail="Missing or invalid internal authentication.")
