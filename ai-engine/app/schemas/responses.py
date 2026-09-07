from pydantic import BaseModel, Field

from app.schemas.ai_content import RefactoringPriorityItem


class FindingInterpretation(BaseModel):
    problem: str
    why_it_matters: str
    potential_impact: str


class FindingExplanationResponse(BaseModel):
    ai_interpretation: FindingInterpretation
    ai_recommendation: str
    provider: str
    context_truncated: bool = False
    warnings: list[str] = Field(default_factory=list)


class ExecutiveSummaryInterpretation(BaseModel):
    strengths: list[str]
    weaknesses: list[str]
    critical_risks: list[str]


class ExecutiveSummaryResponse(BaseModel):
    ai_interpretation: ExecutiveSummaryInterpretation
    ai_recommendation: list[str]
    provider: str
    context_truncated: bool = False
    warnings: list[str] = Field(default_factory=list)


class ArchitectureExplanationResponse(BaseModel):
    ai_interpretation: dict
    provider: str
    context_truncated: bool = False
    warnings: list[str] = Field(default_factory=list)


class RefactoringPlanResponse(BaseModel):
    ai_recommendation: list[RefactoringPriorityItem]
    provider: str
    context_truncated: bool = False
    warnings: list[str] = Field(default_factory=list)


class RepositoryChatResponse(BaseModel):
    ai_interpretation: dict
    referenced_files: list[str]
    referenced_findings: list[str]
    insufficient_context: bool
    provider: str
    context_truncated: bool = False
    warnings: list[str] = Field(default_factory=list)
