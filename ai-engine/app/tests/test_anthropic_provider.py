from typing import Any
from unittest.mock import MagicMock

import anthropic
import httpx
import pytest

from app.ai.anthropic_provider import AnthropicProvider
from app.ai.provider import AIProviderError, AIProviderResponseError, AIProviderTimeoutError


class FakeBlock:
    def __init__(self, type_: str, name: str | None = None, input_: Any = None) -> None:
        self.type = type_
        self.name = name
        self.input = input_


class FakeResponse:
    def __init__(self, content: list[FakeBlock], stop_reason: str = "tool_use") -> None:
        self.content = content
        self.stop_reason = stop_reason


def make_provider() -> AnthropicProvider:
    return AnthropicProvider(api_key="test-key", model="claude-sonnet-5", timeout_seconds=5.0)


def test_successful_tool_use_response_returns_the_input_dict() -> None:
    provider = make_provider()
    expected = {"problem": "p", "why_it_matters": "w", "potential_impact": "i", "remediation": "r"}
    provider._client.messages.create = MagicMock(
        return_value=FakeResponse([FakeBlock("tool_use", "finding_explanation", expected)])
    )

    result = provider.generate_structured(
        system_prompt="sys", user_prompt="user", json_schema={}, schema_name="finding_explanation"
    )

    assert result == expected


def test_missing_tool_use_block_raises_response_error() -> None:
    provider = make_provider()
    provider._client.messages.create = MagicMock(
        return_value=FakeResponse([FakeBlock("text")], stop_reason="end_turn")
    )

    with pytest.raises(AIProviderResponseError):
        provider.generate_structured(
            system_prompt="sys", user_prompt="user", json_schema={}, schema_name="finding_explanation"
        )


def test_non_dict_tool_input_raises_response_error() -> None:
    provider = make_provider()
    provider._client.messages.create = MagicMock(
        return_value=FakeResponse([FakeBlock("tool_use", "finding_explanation", "not-a-dict")])
    )

    with pytest.raises(AIProviderResponseError):
        provider.generate_structured(
            system_prompt="sys", user_prompt="user", json_schema={}, schema_name="finding_explanation"
        )


def test_timeout_error_is_wrapped() -> None:
    provider = make_provider()
    request = httpx.Request("POST", "https://api.anthropic.com/v1/messages")
    provider._client.messages.create = MagicMock(side_effect=anthropic.APITimeoutError(request=request))

    with pytest.raises(AIProviderTimeoutError):
        provider.generate_structured(
            system_prompt="sys", user_prompt="user", json_schema={}, schema_name="finding_explanation"
        )


def test_api_error_is_wrapped() -> None:
    provider = make_provider()
    request = httpx.Request("POST", "https://api.anthropic.com/v1/messages")
    provider._client.messages.create = MagicMock(
        side_effect=anthropic.APIConnectionError(message="boom", request=request)
    )

    with pytest.raises(AIProviderError):
        provider.generate_structured(
            system_prompt="sys", user_prompt="user", json_schema={}, schema_name="finding_explanation"
        )
