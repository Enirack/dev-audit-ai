/**
 * These mirror the ai-engine's pydantic response schemas verbatim (snake_case) —
 * the Symfony AiController proxies them through unmodified. See docs/ai.md.
 */

export interface FindingExplanation {
  ai_interpretation: {
    problem: string;
    why_it_matters: string;
    potential_impact: string;
  };
  ai_recommendation: string;
  provider: string;
  context_truncated: boolean;
  warnings: string[];
}

export interface ExecutiveSummary {
  ai_interpretation: {
    strengths: string[];
    weaknesses: string[];
    critical_risks: string[];
  };
  ai_recommendation: string[];
  provider: string;
  context_truncated: boolean;
  warnings: string[];
}

export interface ArchitectureExplanation {
  ai_interpretation: {
    overview: string;
    main_components: string[];
    dependencies: string[];
    architectural_risks: string[];
  };
  provider: string;
  context_truncated: boolean;
  warnings: string[];
}

export interface RefactoringPriority {
  rank: number;
  title: string;
  rationale: string;
  referenced_files: string[];
  referenced_findings: string[];
}

export interface RefactoringPlan {
  ai_recommendation: RefactoringPriority[];
  provider: string;
  context_truncated: boolean;
  warnings: string[];
}

export interface ChatAnswer {
  ai_interpretation: { answer: string };
  referenced_files: string[];
  referenced_findings: string[];
  insufficient_context: boolean;
  provider: string;
  context_truncated: boolean;
  warnings: string[];
}

export interface ChatMessage {
  role: 'user' | 'assistant';
  content: string;
  createdAt: string;
}
