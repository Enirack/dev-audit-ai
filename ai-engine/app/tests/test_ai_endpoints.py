import pytest
from fastapi.testclient import TestClient

from app.ai.mock_provider import MockProvider
from app.dependencies import get_provider
from app.main import app

client = TestClient(app)


@pytest.fixture(autouse=True)
def _reset_overrides():
    yield
    app.dependency_overrides.clear()


def _override_provider(provider: MockProvider) -> None:
    app.dependency_overrides[get_provider] = lambda: provider


FINDING = {
    "id": "f1",
    "rule_id": "security.hardcoded-secret",
    "category": "security",
    "severity": "critical",
    "title": "Hardcoded secret",
    "description": "A secret is hardcoded.",
    "file_path": "src/config.py",
    "start_line": 10,
    "end_line": 10,
    "recommendation": "Move it to an env var.",
    "confidence": 0.9,
    "priority": 5,
}
REPOSITORY = {"name": "acme/widgets", "primary_language": "python", "total_files": 10, "total_size_bytes": 1000}


def test_explain_finding_success() -> None:
    _override_provider(MockProvider())

    response = client.post("/ai/findings/explain", json={"finding": FINDING, "repository": REPOSITORY})

    assert response.status_code == 200
    body = response.json()
    assert body["ai_interpretation"]["problem"] == "Mock explanation of the problem."
    assert body["ai_recommendation"] == "Mock remediation suggestion."
    assert body["provider"] == "mock"


def test_explain_finding_timeout_returns_504() -> None:
    _override_provider(MockProvider(mode="timeout"))

    response = client.post("/ai/findings/explain", json={"finding": FINDING, "repository": REPOSITORY})

    assert response.status_code == 504


def test_explain_finding_provider_failure_returns_502() -> None:
    _override_provider(MockProvider(mode="failure"))

    response = client.post("/ai/findings/explain", json={"finding": FINDING, "repository": REPOSITORY})

    assert response.status_code == 502


def test_explain_finding_malformed_response_returns_502() -> None:
    _override_provider(MockProvider(mode="malformed"))

    response = client.post("/ai/findings/explain", json={"finding": FINDING, "repository": REPOSITORY})

    assert response.status_code == 502


def test_executive_summary_success() -> None:
    _override_provider(MockProvider())

    response = client.post(
        "/ai/summary",
        json={"audit": {"overall_score": 71.2, "category_scores": {}}, "findings": [FINDING], "repository": REPOSITORY},
    )

    assert response.status_code == 200
    body = response.json()
    assert body["ai_recommendation"] == ["Mock next step."]


def test_architecture_explanation_success() -> None:
    _override_provider(MockProvider())

    response = client.post("/ai/architecture", json={"repository": REPOSITORY, "findings": []})

    assert response.status_code == 200
    assert response.json()["ai_interpretation"]["overview"] == "Mock architecture overview."


def test_refactoring_plan_drops_hallucinated_file_reference() -> None:
    hallucinated = {
        "priorities": [
            {
                "rank": 1,
                "title": "Fix it",
                "rationale": "Because.",
                "referenced_files": ["src/config.py", "src/invented-file-that-does-not-exist.py"],
                "referenced_findings": ["f1", "invented-finding-id"],
            }
        ]
    }
    _override_provider(MockProvider(responses={"refactoring_plan": hallucinated}))

    response = client.post(
        "/ai/refactoring-plan",
        json={"audit": {"overall_score": 71.2, "category_scores": {}}, "findings": [FINDING], "repository": REPOSITORY},
    )

    assert response.status_code == 200
    body = response.json()
    priority = body["ai_recommendation"][0]
    assert priority["referenced_files"] == ["src/config.py"]
    assert priority["referenced_findings"] == ["f1"]
    assert any("invented-file-that-does-not-exist.py" in w for w in body["warnings"])
    assert any("invented-finding-id" in w for w in body["warnings"])


def test_chat_success_and_hallucination_filtering() -> None:
    hallucinated_answer = {
        "answer": "The secret is in src/config.py.",
        "referenced_files": ["src/config.py", "src/made-up.py"],
        "referenced_findings": ["f1"],
        "insufficient_context": False,
    }
    _override_provider(MockProvider(responses={"repository_chat_answer": hallucinated_answer}))

    response = client.post(
        "/ai/chat",
        json={
            "question": "Where is the secret?",
            "audit": {"overall_score": 71.2, "category_scores": {}},
            "findings": [FINDING],
            "repository": REPOSITORY,
            "conversation_history": [],
        },
    )

    assert response.status_code == 200
    body = response.json()
    assert body["referenced_files"] == ["src/config.py"]
    assert body["insufficient_context"] is False


def test_internal_auth_rejects_wrong_secret(monkeypatch: pytest.MonkeyPatch) -> None:
    from app.config import get_settings

    get_settings.cache_clear()
    monkeypatch.setenv("AI_ENGINE_INTERNAL_SECRET", "correct-secret")
    _override_provider(MockProvider())

    try:
        response = client.post(
            "/ai/findings/explain",
            json={"finding": FINDING, "repository": REPOSITORY},
            headers={"X-Internal-Auth": "wrong-secret"},
        )
        assert response.status_code == 401

        response_ok = client.post(
            "/ai/findings/explain",
            json={"finding": FINDING, "repository": REPOSITORY},
            headers={"X-Internal-Auth": "correct-secret"},
        )
        assert response_ok.status_code == 200
    finally:
        get_settings.cache_clear()
