from app.redaction.secrets import redact_secrets


def test_no_secret_returns_text_unchanged() -> None:
    text, hit = redact_secrets("This is a normal finding description with no secrets.")

    assert text == "This is a normal finding description with no secrets."
    assert hit is False


def test_aws_access_key_is_redacted() -> None:
    text, hit = redact_secrets("Found AKIAIOSFODNN7EXAMPLE hardcoded in config.py")

    assert "AKIAIOSFODNN7EXAMPLE" not in text
    assert hit is True


def test_github_token_is_redacted() -> None:
    text, hit = redact_secrets("token = ghp_" + "a" * 36)

    assert "ghp_" not in text
    assert hit is True


def test_private_key_block_is_redacted() -> None:
    block = "-----BEGIN RSA PRIVATE KEY-----\nMIIBogIBAAKCAQEA\n-----END RSA PRIVATE KEY-----"

    text, hit = redact_secrets(block)

    assert "MIIBogIBAAKCAQEA" not in text
    assert hit is True


def test_assigned_credential_with_high_entropy_value_is_redacted() -> None:
    fake_value = "Qw7zP9xR2mL5vN8tKj3h"
    text, hit = redact_secrets(f'api_key = "{fake_value}"')

    assert fake_value not in text
    assert hit is True


def test_placeholder_value_is_not_redacted() -> None:
    original = 'api_key = "xxxxxxxxxxxxxxxxxxxx"'

    text, hit = redact_secrets(original)

    assert text == original
    assert hit is False


def test_empty_string_is_returned_unchanged() -> None:
    text, hit = redact_secrets("")

    assert text == ""
    assert hit is False
