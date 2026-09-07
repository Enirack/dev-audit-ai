from typing import Any

from app.ai.provider import AIProviderError, AIProviderResponseError, AIProviderTimeoutError

# Deterministic canned payloads, keyed by schema_name, used when the caller
# does not supply its own. They exist so "success" mode round-trips through
# a real endpoint end-to-end in tests without needing a real LLM.
_DEFAULT_RESPONSES: dict[str, dict[str, Any]] = {
    "finding_explanation": {
        "problem": "Mock explanation of the problem.",
        "why_it_matters": "Mock explanation of why it matters.",
        "potential_impact": "Mock explanation of potential impact.",
        "remediation": "Mock remediation suggestion.",
    },
    "executive_summary": {
        "strengths": ["Mock strength."],
        "weaknesses": ["Mock weakness."],
        "critical_risks": ["Mock critical risk."],
        "recommended_next_steps": ["Mock next step."],
    },
    "architecture_explanation": {
        "overview": "Mock architecture overview.",
        "main_components": ["Mock component."],
        "dependencies": ["Mock dependency."],
        "architectural_risks": ["Mock architectural risk."],
    },
    "refactoring_plan": {
        "priorities": [
            {
                "rank": 1,
                "title": "Mock priority 1.",
                "rationale": "Mock rationale.",
                "referenced_files": [],
                "referenced_findings": [],
            },
        ],
    },
    "repository_chat_answer": {
        "answer": "Mock answer.",
        "referenced_files": [],
        "referenced_findings": [],
        "insufficient_context": False,
    },
}


class MockProvider:
    """
    Test/offline provider. Never makes a network call. Deterministic by
    design so tests can assert exact output.

    `mode` simulates provider failure classes so callers (context builder,
    hallucination guard, router error handling) can be tested without a real
    LLM:
      - "success" (default): returns a canned or caller-supplied dict.
      - "timeout": raises AIProviderTimeoutError.
      - "failure": raises AIProviderError.
      - "malformed": returns a dict missing required keys, to exercise
        downstream schema-validation error handling.
    """

    def __init__(
        self,
        mode: str = "success",
        responses: dict[str, dict[str, Any]] | None = None,
    ) -> None:
        self.mode = mode
        self._responses = responses or {}
        self.calls: list[dict[str, Any]] = []

    def generate_structured(
        self,
        *,
        system_prompt: str,
        user_prompt: str,
        json_schema: dict[str, Any],
        schema_name: str,
        max_tokens: int = 1500,
    ) -> dict[str, Any]:
        self.calls.append(
            {
                "system_prompt": system_prompt,
                "user_prompt": user_prompt,
                "schema_name": schema_name,
                "max_tokens": max_tokens,
            }
        )

        if self.mode == "timeout":
            raise AIProviderTimeoutError("mock provider simulated a timeout")
        if self.mode == "failure":
            raise AIProviderError("mock provider simulated a provider failure")
        if self.mode == "malformed":
            return {"unexpected_field": "this does not match any schema"}

        if schema_name in self._responses:
            return self._responses[schema_name]
        if schema_name in _DEFAULT_RESPONSES:
            return _DEFAULT_RESPONSES[schema_name]

        raise AIProviderResponseError(f"mock provider has no canned response for schema '{schema_name}'")
