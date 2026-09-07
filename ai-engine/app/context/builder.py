from dataclasses import dataclass, field

from app.redaction.secrets import redact_secrets
from app.schemas.requests import (
    ArchitectureExplanationRequest,
    ChatMessagePayload,
    ExecutiveSummaryRequest,
    FindingExplanationRequest,
    FindingPayload,
    RefactoringPlanRequest,
    RepositoryChatRequest,
    RepositorySummary,
)

BASE_SYSTEM_PROMPT = (
    "You are the AI insight layer of DevAudit AI, an automated code-audit tool. "
    "You enrich deterministic static-analysis results; you never replace or override them.\n\n"
    "Rules you must follow exactly:\n"
    "- Never invent file paths, line numbers, function names, dependencies, or finding IDs "
    "that are not explicitly present in the context given to you.\n"
    "- Only reference files or findings that appear in the \"Known files\" / \"Known findings\" "
    "lists below. If none are listed, do not reference any.\n"
    "- If the provided context is not enough to answer confidently, say so explicitly "
    "instead of guessing.\n"
    "- Respond only through the structured tool you are given; do not add prose outside it."
)

# Conservative reservation so the rendered findings block, plus the fixed
# preamble/instructions text, never exceeds Settings.max_context_characters.
_PREAMBLE_RESERVE_CHARS = 1500
_MAX_CHAT_HISTORY_MESSAGES = 10


@dataclass
class BuiltContext:
    system_prompt: str
    user_prompt: str
    known_files: set[str]
    known_findings: set[str]
    context_truncated: bool
    warnings: list[str] = field(default_factory=list)


def _redact(text: str | None, warnings: list[str]) -> str | None:
    if not text:
        return text
    redacted, hit = redact_secrets(text)
    if hit:
        warnings.append("A secret-like value was redacted from the input before it was sent to the AI provider.")
    return redacted


def _redact_finding(finding: FindingPayload, warnings: list[str]) -> FindingPayload:
    return finding.model_copy(
        update={
            "title": _redact(finding.title, warnings),
            "description": _redact(finding.description, warnings),
            "recommendation": _redact(finding.recommendation, warnings),
        }
    )


def _render_finding(finding: FindingPayload) -> str:
    location = f"{finding.file_path}:{finding.start_line}" if finding.file_path else "(no file)"
    return (
        f"- [{finding.id}] rule={finding.rule_id} category={finding.category} "
        f"severity={finding.severity} confidence={finding.confidence:.2f} priority={finding.priority} "
        f"location={location}\n"
        f"  title: {finding.title}\n"
        f"  description: {finding.description}"
    )


def _budget_findings(
    findings: list[FindingPayload], max_chars: int
) -> tuple[str, list[FindingPayload], bool]:
    ordered = sorted(findings, key=lambda f: (f.priority, f.confidence), reverse=True)

    included: list[FindingPayload] = []
    blocks: list[str] = []
    used = 0
    truncated = False

    for finding in ordered:
        block = _render_finding(finding)
        cost = len(block) + 1
        if used + cost > max_chars:
            truncated = True
            continue
        blocks.append(block)
        included.append(finding)
        used += cost

    return "\n".join(blocks), included, truncated


def _render_repository(repository: RepositorySummary) -> str:
    return (
        f"Repository: {repository.name}\n"
        f"Primary language: {repository.primary_language or 'unknown'}\n"
        f"Total files: {repository.total_files}\n"
        f"Total size: {repository.total_size_bytes} bytes\n"
        f"Language breakdown: {repository.language_stats}\n"
        f"Metadata flags: {repository.metadata_flags}"
    )


def _known_sets(findings: list[FindingPayload]) -> tuple[set[str], set[str]]:
    known_files = {f.file_path for f in findings if f.file_path}
    known_findings = {f.id for f in findings} | {f.rule_id for f in findings}
    return known_files, known_findings


class ContextBuilder:
    """
    Turns a deterministic request payload (findings/repository/audit data
    already computed by Symfony) into a token-budgeted prompt, redacting
    secrets and recording which files/findings the model was actually shown
    so the hallucination guard can check its output against that exact set.

    There is no source-code snippet support: the ingested workspace is
    deleted once a scan finishes (see docs/ai.md), so only the structured
    Finding/RepositoryInventory data persisted in the database is ever
    available here.
    """

    def __init__(self, max_context_characters: int) -> None:
        self._max_chars = max_context_characters

    def build_finding_explanation(self, request: FindingExplanationRequest) -> BuiltContext:
        warnings: list[str] = []
        finding = _redact_finding(request.finding, warnings)

        user_prompt = (
            f"{_render_repository(request.repository)}\n\n"
            f"Finding to explain:\n{_render_finding(finding)}\n"
            f"existing recommendation on file: {finding.recommendation or '(none provided)'}\n\n"
            "Explain this single finding: the problem, why it matters, its potential impact, "
            "and a remediation suggestion."
        )
        known_files, known_findings = _known_sets([finding])

        return BuiltContext(
            system_prompt=BASE_SYSTEM_PROMPT,
            user_prompt=user_prompt,
            known_files=known_files,
            known_findings=known_findings,
            context_truncated=False,
            warnings=warnings,
        )

    def build_executive_summary(self, request: ExecutiveSummaryRequest) -> BuiltContext:
        warnings: list[str] = []
        redacted_findings = [_redact_finding(f, warnings) for f in request.findings]
        budget = self._max_chars - _PREAMBLE_RESERVE_CHARS
        findings_block, included, truncated = _budget_findings(redacted_findings, budget)

        user_prompt = (
            f"{_render_repository(request.repository)}\n\n"
            f"Audit overall score: {request.audit.overall_score}\n"
            f"Category scores: {request.audit.category_scores}\n\n"
            f"Findings ({len(included)} of {len(redacted_findings)} shown"
            f"{', truncated to fit context budget' if truncated else ''}):\n{findings_block}\n\n"
            "Produce an executive summary: strengths, weaknesses, critical risks, and "
            "recommended next steps, grounded only in the findings and metadata above."
        )
        known_files, known_findings = _known_sets(included)

        return BuiltContext(
            system_prompt=BASE_SYSTEM_PROMPT,
            user_prompt=user_prompt,
            known_files=known_files,
            known_findings=known_findings,
            context_truncated=truncated,
            warnings=warnings,
        )

    def build_architecture_explanation(self, request: ArchitectureExplanationRequest) -> BuiltContext:
        warnings: list[str] = []
        redacted_findings = [_redact_finding(f, warnings) for f in request.findings]
        budget = self._max_chars - _PREAMBLE_RESERVE_CHARS
        findings_block, included, truncated = _budget_findings(redacted_findings, budget)

        user_prompt = (
            f"{_render_repository(request.repository)}\n\n"
            f"Architecture-relevant findings ({len(included)} of {len(redacted_findings)} shown"
            f"{', truncated to fit context budget' if truncated else ''}):\n{findings_block}\n\n"
            "Based only on the repository metadata and findings above (there is no source code "
            "available), explain the likely application architecture, main components, "
            "dependencies, and potential architectural risks. If the metadata is too sparse to "
            "infer something, say so explicitly rather than guessing."
        )
        known_files, known_findings = _known_sets(included)

        return BuiltContext(
            system_prompt=BASE_SYSTEM_PROMPT,
            user_prompt=user_prompt,
            known_files=known_files,
            known_findings=known_findings,
            context_truncated=truncated,
            warnings=warnings,
        )

    def build_refactoring_plan(self, request: RefactoringPlanRequest) -> BuiltContext:
        warnings: list[str] = []
        redacted_findings = [_redact_finding(f, warnings) for f in request.findings]
        budget = self._max_chars - _PREAMBLE_RESERVE_CHARS
        findings_block, included, truncated = _budget_findings(redacted_findings, budget)

        user_prompt = (
            f"{_render_repository(request.repository)}\n\n"
            f"Audit overall score: {request.audit.overall_score}\n"
            f"Category scores: {request.audit.category_scores}\n\n"
            f"Findings ({len(included)} of {len(redacted_findings)} shown"
            f"{', truncated to fit context budget' if truncated else ''}):\n{findings_block}\n\n"
            "Produce a prioritized refactoring plan (rank 1 = do first). Each priority item must "
            "reference actual finding IDs and/or file paths from the list above wherever possible — "
            "do not invent files or findings that are not listed."
        )
        known_files, known_findings = _known_sets(included)

        return BuiltContext(
            system_prompt=BASE_SYSTEM_PROMPT,
            user_prompt=user_prompt,
            known_files=known_files,
            known_findings=known_findings,
            context_truncated=truncated,
            warnings=warnings,
        )

    def build_repository_chat(self, request: RepositoryChatRequest) -> BuiltContext:
        warnings: list[str] = []
        question = _redact(request.question, warnings) or ""
        history = self._redact_history(request.conversation_history, warnings)

        redacted_findings = [_redact_finding(f, warnings) for f in request.findings]
        budget = self._max_chars - _PREAMBLE_RESERVE_CHARS - len(question) - sum(len(m.content) for m in history)
        findings_block, included, truncated = _budget_findings(redacted_findings, max(budget, 0))

        history_block = "\n".join(f"{m.role}: {m.content}" for m in history) or "(no prior messages)"

        user_prompt = (
            f"{_render_repository(request.repository)}\n\n"
            f"Audit overall score: {request.audit.overall_score}\n"
            f"Category scores: {request.audit.category_scores}\n\n"
            f"Findings ({len(included)} of {len(redacted_findings)} shown"
            f"{', truncated to fit context budget' if truncated else ''}):\n{findings_block}\n\n"
            f"Conversation so far:\n{history_block}\n\n"
            f"User question: {question}\n\n"
            "Answer using only the findings and repository metadata above. If they don't contain "
            "enough information to answer, set insufficient_context to true and say so in the answer."
        )
        known_files, known_findings = _known_sets(included)

        return BuiltContext(
            system_prompt=BASE_SYSTEM_PROMPT,
            user_prompt=user_prompt,
            known_files=known_files,
            known_findings=known_findings,
            context_truncated=truncated,
            warnings=warnings,
        )

    def _redact_history(
        self, history: list[ChatMessagePayload], warnings: list[str]
    ) -> list[ChatMessagePayload]:
        recent = history[-_MAX_CHAT_HISTORY_MESSAGES:]
        return [m.model_copy(update={"content": _redact(m.content, warnings) or ""}) for m in recent]
