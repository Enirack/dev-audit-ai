from typing import Any, Literal

from pydantic import BaseModel, Field


class FindingPayload(BaseModel):
    """
    Mirrors backend AuditFindingResponse. This is deterministic data already
    produced by the Phase 4/5 static-analysis-and-scoring pipeline — the AI
    layer never invents or alters any of these fields.
    """

    id: str
    rule_id: str
    category: str
    severity: str
    title: str
    description: str
    file_path: str | None = None
    start_line: int | None = None
    end_line: int | None = None
    recommendation: str | None = None
    confidence: float
    priority: int


class RepositorySummary(BaseModel):
    name: str
    primary_language: str | None = None
    total_files: int = 0
    total_size_bytes: int = 0
    language_stats: dict[str, Any] = Field(default_factory=dict)
    metadata_flags: dict[str, Any] = Field(default_factory=dict)


class AuditSummary(BaseModel):
    overall_score: float | None = None
    category_scores: dict[str, float] = Field(default_factory=dict)


class ChatMessagePayload(BaseModel):
    role: Literal["user", "assistant"]
    content: str


class FindingExplanationRequest(BaseModel):
    finding: FindingPayload
    repository: RepositorySummary


class ExecutiveSummaryRequest(BaseModel):
    audit: AuditSummary
    findings: list[FindingPayload]
    repository: RepositorySummary


class ArchitectureExplanationRequest(BaseModel):
    repository: RepositorySummary
    findings: list[FindingPayload] = Field(default_factory=list)


class RefactoringPlanRequest(BaseModel):
    audit: AuditSummary
    findings: list[FindingPayload]
    repository: RepositorySummary


class RepositoryChatRequest(BaseModel):
    question: str
    audit: AuditSummary
    findings: list[FindingPayload]
    repository: RepositorySummary
    conversation_history: list[ChatMessagePayload] = Field(default_factory=list)
