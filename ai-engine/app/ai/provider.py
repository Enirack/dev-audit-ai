from typing import Any, Protocol


class AIProviderError(Exception):
    """Base class for every failure a provider can raise."""


class AIProviderTimeoutError(AIProviderError):
    """The provider did not respond within the configured timeout."""


class AIProviderResponseError(AIProviderError):
    """The provider responded, but not with valid structured output."""


class AIProvider(Protocol):
    """
    Abstraction over any LLM vendor. Every provider must return a dict that
    validates against the given JSON schema — callers never see raw model
    text, so a bad provider response fails loudly here rather than leaking
    malformed data downstream.
    """

    def generate_structured(
        self,
        *,
        system_prompt: str,
        user_prompt: str,
        json_schema: dict[str, Any],
        schema_name: str,
        max_tokens: int = 1500,
    ) -> dict[str, Any]:
        """
        Ask the model to produce output conforming to json_schema.

        Raises AIProviderTimeoutError on timeout, AIProviderResponseError when
        the provider's output cannot be parsed as valid JSON matching the
        schema, or AIProviderError for any other provider-side failure.
        """
        ...
