import re

# Last line of defense before any text leaves this process toward an LLM
# provider. The repository workspace is already deleted by the time AI
# insights are requested (see docs/ai.md), so this is not scanning source
# code — it is guarding against a secret that leaked into persisted finding
# text (e.g. a hardcoded-secret finding's own description) or into free-form
# user input (the repository chat question).
_PATTERNS: list[tuple[str, re.Pattern[str]]] = [
    ("aws_access_key_id", re.compile(r"\bAKIA[0-9A-Z]{16}\b")),
    ("github_token", re.compile(r"\bgh[pousr]_[A-Za-z0-9]{36,}\b")),
    ("slack_token", re.compile(r"\bxox[baprs]-[A-Za-z0-9-]{10,}\b")),
    ("private_key_block", re.compile(r"-----BEGIN [A-Z ]*PRIVATE KEY-----[\s\S]*?-----END [A-Z ]*PRIVATE KEY-----")),
    ("bearer_token", re.compile(r"(?i)\bbearer\s+[A-Za-z0-9\-._~+/]{20,}=*")),
    (
        "assigned_credential",
        re.compile(
            r"(?i)\b(api[_-]?key|secret|token|password|passwd|access[_-]?key)\b\s*[:=]\s*"
            r"['\"]?([A-Za-z0-9+/_\-]{16,})['\"]?"
        ),
    ),
]

_PLACEHOLDER = re.compile(r"(?i)^(changeme|xxx+|example|test|dummy|placeholder|your[_-]?.*|<.*>|\$\{.*\}|%env\(.*\)%)$")


def redact_secrets(text: str) -> tuple[str, bool]:
    """Returns (redacted_text, was_anything_redacted)."""
    if not text:
        return text, False

    redacted = False
    result = text

    for name, pattern in _PATTERNS:
        def _replace(match: re.Match[str]) -> str:
            nonlocal redacted
            # For the "assigned_credential" pattern, don't redact obvious
            # placeholders — that would make error messages and docs
            # unreadable for no security benefit.
            if match.lastindex and match.lastindex >= 2 and _PLACEHOLDER.match(match.group(2)):
                return match.group(0)
            redacted = True
            return f"[REDACTED:{name}]"

        result = pattern.sub(_replace, result)

    return result, redacted
