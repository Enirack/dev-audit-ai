from app.ai.provider import AIProvider
from app.config import Settings


class UnknownProviderError(Exception):
    pass


def build_provider(settings: Settings) -> AIProvider:
    if settings.provider == "mock":
        from app.ai.mock_provider import MockProvider

        return MockProvider()

    if settings.provider == "anthropic":
        if not settings.anthropic_api_key:
            raise UnknownProviderError(
                "AI_ENGINE_PROVIDER=anthropic requires AI_ENGINE_ANTHROPIC_API_KEY to be set."
            )
        from app.ai.anthropic_provider import AnthropicProvider

        return AnthropicProvider(
            api_key=settings.anthropic_api_key,
            model=settings.anthropic_model,
            timeout_seconds=settings.request_timeout_seconds,
        )

    raise UnknownProviderError(f"Unknown AI_ENGINE_PROVIDER '{settings.provider}'. Expected 'mock' or 'anthropic'.")
