from functools import lru_cache

from pydantic_settings import BaseSettings, SettingsConfigDict


class Settings(BaseSettings):
    model_config = SettingsConfigDict(env_prefix="AI_ENGINE_")

    service_name: str = "devaudit-ai-engine"

    # "mock" (default, no external calls) or "anthropic".
    provider: str = "mock"
    anthropic_api_key: str | None = None
    anthropic_model: str = "claude-sonnet-5"
    request_timeout_seconds: float = 30.0

    # Shared secret the backend must send as X-Internal-Auth. When unset,
    # internal auth is disabled (local/dev only) — see security/internal_auth.py.
    internal_secret: str | None = None

    # Approximate context budget, in characters, enforced by the context builder.
    # Character count is used instead of a real tokenizer to avoid an extra
    # dependency; ~4 chars/token is a conservative English-text approximation,
    # so this comfortably fits within typical model context windows.
    max_context_characters: int = 24_000


@lru_cache
def get_settings() -> Settings:
    return Settings()
