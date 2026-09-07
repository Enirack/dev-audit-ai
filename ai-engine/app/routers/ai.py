from typing import TypeVar

from fastapi import APIRouter, Depends, HTTPException
from pydantic import BaseModel, ValidationError

from app.ai.provider import AIProvider, AIProviderError, AIProviderTimeoutError
from app.config import Settings, get_settings
from app.context.builder import BuiltContext, ContextBuilder
from app.dependencies import get_provider
from app.schemas.ai_content import (
    ArchitectureExplanationContent,
    ChatAnswerContent,
    ExecutiveSummaryContent,
    FindingExplanationContent,
    RefactoringPlanContent,
)
from app.schemas.requests import (
    ArchitectureExplanationRequest,
    ExecutiveSummaryRequest,
    FindingExplanationRequest,
    RefactoringPlanRequest,
    RepositoryChatRequest,
)
from app.schemas.responses import (
    ArchitectureExplanationResponse,
    ExecutiveSummaryInterpretation,
    ExecutiveSummaryResponse,
    FindingExplanationResponse,
    FindingInterpretation,
    RefactoringPlanResponse,
    RepositoryChatResponse,
)
from app.security.internal_auth import verify_internal_secret
from app.validation.hallucination_guard import filter_known_references

router = APIRouter(prefix="/ai", tags=["ai"], dependencies=[Depends(verify_internal_secret)])

T = TypeVar("T", bound=BaseModel)


def _generate(
    provider: AIProvider,
    ctx: BuiltContext,
    content_model: type[T],
    schema_name: str,
    max_tokens: int = 1500,
) -> T:
    try:
        raw = provider.generate_structured(
            system_prompt=ctx.system_prompt,
            user_prompt=ctx.user_prompt,
            json_schema=content_model.model_json_schema(),
            schema_name=schema_name,
            max_tokens=max_tokens,
        )
    except AIProviderTimeoutError as exc:
        raise HTTPException(status_code=504, detail=f"AI provider timed out: {exc}") from exc
    except AIProviderError as exc:
        raise HTTPException(status_code=502, detail=f"AI provider failed: {exc}") from exc

    try:
        return content_model.model_validate(raw)
    except ValidationError as exc:
        raise HTTPException(status_code=502, detail=f"AI provider returned malformed output: {exc}") from exc


@router.post("/findings/explain", response_model=FindingExplanationResponse)
def explain_finding(
    request: FindingExplanationRequest,
    provider: AIProvider = Depends(get_provider),
    settings: Settings = Depends(get_settings),
) -> FindingExplanationResponse:
    builder = ContextBuilder(settings.max_context_characters)
    ctx = builder.build_finding_explanation(request)
    content = _generate(provider, ctx, FindingExplanationContent, "finding_explanation")

    return FindingExplanationResponse(
        ai_interpretation=FindingInterpretation(
            problem=content.problem,
            why_it_matters=content.why_it_matters,
            potential_impact=content.potential_impact,
        ),
        ai_recommendation=content.remediation,
        provider=settings.provider,
        context_truncated=ctx.context_truncated,
        warnings=ctx.warnings,
    )


@router.post("/summary", response_model=ExecutiveSummaryResponse)
def executive_summary(
    request: ExecutiveSummaryRequest,
    provider: AIProvider = Depends(get_provider),
    settings: Settings = Depends(get_settings),
) -> ExecutiveSummaryResponse:
    builder = ContextBuilder(settings.max_context_characters)
    ctx = builder.build_executive_summary(request)
    content = _generate(provider, ctx, ExecutiveSummaryContent, "executive_summary")

    return ExecutiveSummaryResponse(
        ai_interpretation=ExecutiveSummaryInterpretation(
            strengths=content.strengths,
            weaknesses=content.weaknesses,
            critical_risks=content.critical_risks,
        ),
        ai_recommendation=content.recommended_next_steps,
        provider=settings.provider,
        context_truncated=ctx.context_truncated,
        warnings=ctx.warnings,
    )


@router.post("/architecture", response_model=ArchitectureExplanationResponse)
def architecture_explanation(
    request: ArchitectureExplanationRequest,
    provider: AIProvider = Depends(get_provider),
    settings: Settings = Depends(get_settings),
) -> ArchitectureExplanationResponse:
    builder = ContextBuilder(settings.max_context_characters)
    ctx = builder.build_architecture_explanation(request)
    content = _generate(provider, ctx, ArchitectureExplanationContent, "architecture_explanation")

    return ArchitectureExplanationResponse(
        ai_interpretation=content.model_dump(),
        provider=settings.provider,
        context_truncated=ctx.context_truncated,
        warnings=ctx.warnings,
    )


@router.post("/refactoring-plan", response_model=RefactoringPlanResponse)
def refactoring_plan(
    request: RefactoringPlanRequest,
    provider: AIProvider = Depends(get_provider),
    settings: Settings = Depends(get_settings),
) -> RefactoringPlanResponse:
    builder = ContextBuilder(settings.max_context_characters)
    ctx = builder.build_refactoring_plan(request)
    content = _generate(provider, ctx, RefactoringPlanContent, "refactoring_plan", max_tokens=2000)

    guard_warnings: list[str] = []
    filtered_priorities = []
    for item in content.priorities:
        files, file_warnings = filter_known_references(item.referenced_files, ctx.known_files, "file")
        findings, finding_warnings = filter_known_references(
            item.referenced_findings, ctx.known_findings, "finding"
        )
        guard_warnings.extend(file_warnings + finding_warnings)
        filtered_priorities.append(item.model_copy(update={"referenced_files": files, "referenced_findings": findings}))

    return RefactoringPlanResponse(
        ai_recommendation=filtered_priorities,
        provider=settings.provider,
        context_truncated=ctx.context_truncated,
        warnings=ctx.warnings + guard_warnings,
    )


@router.post("/chat", response_model=RepositoryChatResponse)
def repository_chat(
    request: RepositoryChatRequest,
    provider: AIProvider = Depends(get_provider),
    settings: Settings = Depends(get_settings),
) -> RepositoryChatResponse:
    builder = ContextBuilder(settings.max_context_characters)
    ctx = builder.build_repository_chat(request)
    content = _generate(provider, ctx, ChatAnswerContent, "repository_chat_answer")

    files, file_warnings = filter_known_references(content.referenced_files, ctx.known_files, "file")
    findings, finding_warnings = filter_known_references(content.referenced_findings, ctx.known_findings, "finding")

    return RepositoryChatResponse(
        ai_interpretation={"answer": content.answer},
        referenced_files=files,
        referenced_findings=findings,
        insufficient_context=content.insufficient_context,
        provider=settings.provider,
        context_truncated=ctx.context_truncated,
        warnings=ctx.warnings + file_warnings + finding_warnings,
    )
