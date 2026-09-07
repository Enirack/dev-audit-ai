"""
Flat pydantic models used two ways: (1) their JSON schema is handed to the
LLM provider as the tool/output schema it must fill in, and (2) the same
model validates whatever the provider actually returns. If a provider
returns something that doesn't fit, model_validate raises and the router
turns that into a 502 — it never reaches the caller as if it were real data.
"""

from pydantic import BaseModel, Field


class FindingExplanationContent(BaseModel):
    problem: str
    why_it_matters: str
    potential_impact: str
    remediation: str


class ExecutiveSummaryContent(BaseModel):
    strengths: list[str]
    weaknesses: list[str]
    critical_risks: list[str]
    recommended_next_steps: list[str]


class ArchitectureExplanationContent(BaseModel):
    overview: str
    main_components: list[str]
    dependencies: list[str]
    architectural_risks: list[str]


class RefactoringPriorityItem(BaseModel):
    rank: int
    title: str
    rationale: str
    referenced_files: list[str] = Field(default_factory=list)
    referenced_findings: list[str] = Field(default_factory=list)


class RefactoringPlanContent(BaseModel):
    priorities: list[RefactoringPriorityItem]


class ChatAnswerContent(BaseModel):
    answer: str
    referenced_files: list[str] = Field(default_factory=list)
    referenced_findings: list[str] = Field(default_factory=list)
    insufficient_context: bool = False
