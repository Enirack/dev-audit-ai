from typing import Any

import anthropic

from app.ai.provider import AIProviderError, AIProviderResponseError, AIProviderTimeoutError


class AnthropicProvider:
    """
    Structured output is forced via tool use: we register a single tool
    whose input_schema *is* the schema we want back, and force the model to
    call it with tool_choice. This means the SDK itself parses the model's
    JSON, so we never regex/parse free-form text out of a completion.
    """

    def __init__(self, api_key: str, model: str, timeout_seconds: float) -> None:
        self._client = anthropic.Anthropic(api_key=api_key, timeout=timeout_seconds)
        self._model = model

    def generate_structured(
        self,
        *,
        system_prompt: str,
        user_prompt: str,
        json_schema: dict[str, Any],
        schema_name: str,
        max_tokens: int = 1500,
    ) -> dict[str, Any]:
        try:
            response = self._client.messages.create(
                model=self._model,
                max_tokens=max_tokens,
                system=system_prompt,
                messages=[{"role": "user", "content": user_prompt}],
                tools=[
                    {
                        "name": schema_name,
                        "description": f"Return a {schema_name} result.",
                        "input_schema": json_schema,
                    }
                ],
                tool_choice={"type": "tool", "name": schema_name},
            )
        except anthropic.APITimeoutError as exc:
            raise AIProviderTimeoutError(f"Anthropic request timed out: {exc}") from exc
        except anthropic.APIError as exc:
            raise AIProviderError(f"Anthropic API error: {exc}") from exc

        for block in response.content:
            if block.type == "tool_use" and block.name == schema_name:
                if not isinstance(block.input, dict):
                    raise AIProviderResponseError("Anthropic tool_use input was not a JSON object.")
                return block.input

        raise AIProviderResponseError(
            f"Anthropic response did not contain a '{schema_name}' tool_use block "
            f"(stop_reason={response.stop_reason})."
        )
