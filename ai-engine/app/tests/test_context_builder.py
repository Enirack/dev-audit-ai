from app.context.builder import ContextBuilder
from app.schemas.requests import (
    AuditSummary,
    ChatMessagePayload,
    ExecutiveSummaryRequest,
    FindingExplanationRequest,
    FindingPayload,
    RefactoringPlanRequest,
    RepositoryChatRequest,
    RepositorySummary,
)


def make_finding(i: int, description: str = "A generic finding description.") -> FindingPayload:
    return FindingPayload(
        id=f"finding-{i}",
        rule_id=f"rule.{i}",
        category="security",
        severity="high",
        title=f"Finding {i}",
        description=description,
        file_path=f"src/file{i}.py",
        start_line=1,
        end_line=2,
        recommendation="Fix it.",
        confidence=0.8,
        priority=4,
    )


REPO = RepositorySummary(name="acme/widgets", primary_language="python", total_files=42, total_size_bytes=1000)


def test_finding_explanation_context_includes_the_finding_and_its_file() -> None:
    builder = ContextBuilder(max_context_characters=24_000)
    request = FindingExplanationRequest(finding=make_finding(1), repository=REPO)

    ctx = builder.build_finding_explanation(request)

    assert "Finding 1" in ctx.user_prompt
    assert ctx.known_files == {"src/file1.py"}
    assert "finding-1" in ctx.known_findings
    assert "rule.1" in ctx.known_findings
    assert ctx.context_truncated is False


def test_executive_summary_truncates_when_over_budget() -> None:
    builder = ContextBuilder(max_context_characters=2000)
    findings = [make_finding(i, description="x" * 500) for i in range(10)]
    request = ExecutiveSummaryRequest(
        audit=AuditSummary(overall_score=70.0, category_scores={"security": 60.0}),
        findings=findings,
        repository=REPO,
    )

    ctx = builder.build_executive_summary(request)

    assert ctx.context_truncated is True
    assert len(ctx.known_files) < 10


def test_executive_summary_does_not_truncate_when_under_budget() -> None:
    builder = ContextBuilder(max_context_characters=24_000)
    findings = [make_finding(i) for i in range(3)]
    request = ExecutiveSummaryRequest(
        audit=AuditSummary(overall_score=70.0), findings=findings, repository=REPO
    )

    ctx = builder.build_executive_summary(request)

    assert ctx.context_truncated is False
    assert len(ctx.known_files) == 3


def test_secret_in_finding_description_is_redacted_and_warned() -> None:
    builder = ContextBuilder(max_context_characters=24_000)
    finding = make_finding(1, description="Found AKIAIOSFODNN7EXAMPLE hardcoded here.")
    request = FindingExplanationRequest(finding=finding, repository=REPO)

    ctx = builder.build_finding_explanation(request)

    assert "AKIAIOSFODNN7EXAMPLE" not in ctx.user_prompt
    assert any("redacted" in w for w in ctx.warnings)


def test_chat_context_redacts_question_and_history_and_caps_history_length() -> None:
    builder = ContextBuilder(max_context_characters=24_000)
    history = [ChatMessagePayload(role="user", content=f"message {i}") for i in range(20)]
    request = RepositoryChatRequest(
        question="Where is AKIAIOSFODNN7EXAMPLE used?",
        audit=AuditSummary(),
        findings=[make_finding(1)],
        repository=REPO,
        conversation_history=history,
    )

    ctx = builder.build_repository_chat(request)

    assert "AKIAIOSFODNN7EXAMPLE" not in ctx.user_prompt
    assert "message 0" not in ctx.user_prompt
    assert "message 19" in ctx.user_prompt


def test_refactoring_plan_known_findings_only_include_shown_findings() -> None:
    builder = ContextBuilder(max_context_characters=24_000)
    findings = [make_finding(i) for i in range(2)]
    request = RefactoringPlanRequest(audit=AuditSummary(), findings=findings, repository=REPO)

    ctx = builder.build_refactoring_plan(request)

    assert "finding-0" in ctx.known_findings
    assert "finding-1" in ctx.known_findings
    assert "finding-99" not in ctx.known_findings
