import pytest

from app.ai.mock_provider import MockProvider
from app.ai.provider import AIProviderError, AIProviderResponseError, AIProviderTimeoutError


def test_success_mode_returns_default_canned_response() -> None:
    provider = MockProvider()

    result = provider.generate_structured(
        system_prompt="sys",
        user_prompt="user",
        json_schema={},
        schema_name="finding_explanation",
    )

    assert result["problem"] == "Mock explanation of the problem."
    assert provider.calls[0]["schema_name"] == "finding_explanation"


def test_success_mode_uses_caller_supplied_response_over_default() -> None:
    custom = {"problem": "custom", "why_it_matters": "x", "potential_impact": "y", "remediation": "z"}
    provider = MockProvider(responses={"finding_explanation": custom})

    result = provider.generate_structured(
        system_prompt="sys", user_prompt="user", json_schema={}, schema_name="finding_explanation"
    )

    assert result == custom


def test_unknown_schema_name_raises_response_error() -> None:
    provider = MockProvider()

    with pytest.raises(AIProviderResponseError):
        provider.generate_structured(system_prompt="s", user_prompt="u", json_schema={}, schema_name="nonexistent")


def test_timeout_mode_raises_timeout_error() -> None:
    provider = MockProvider(mode="timeout")

    with pytest.raises(AIProviderTimeoutError):
        provider.generate_structured(
            system_prompt="s", user_prompt="u", json_schema={}, schema_name="finding_explanation"
        )


def test_failure_mode_raises_provider_error() -> None:
    provider = MockProvider(mode="failure")

    with pytest.raises(AIProviderError):
        provider.generate_structured(
            system_prompt="s", user_prompt="u", json_schema={}, schema_name="finding_explanation"
        )


def test_malformed_mode_returns_dict_missing_expected_fields() -> None:
    provider = MockProvider(mode="malformed")

    result = provider.generate_structured(
        system_prompt="s", user_prompt="u", json_schema={}, schema_name="finding_explanation"
    )

    assert "problem" not in result
