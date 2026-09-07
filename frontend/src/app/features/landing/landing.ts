import { Component } from '@angular/core';
import { RouterLink } from '@angular/router';

@Component({
  selector: 'app-landing',
  imports: [RouterLink],
  templateUrl: './landing.html',
  styleUrl: './landing.scss',
})
export class Landing {
  protected readonly capabilities = [
    {
      title: 'Deterministic static analysis',
      description:
        'Rule-based analyzers for JavaScript, TypeScript, PHP, and Python surface security, architecture, maintainability, performance, testing, and code-quality issues — no guessing, every finding is reproducible.',
    },
    {
      title: 'Explainable scoring',
      description:
        'A documented, hand-verifiable formula turns findings into category scores and one overall score. Severity drives the score, not finding count — one critical issue outweighs a hundred low-severity ones.',
    },
    {
      title: 'AI that enriches, never replaces',
      description:
        'An AI layer explains findings, summarizes architecture, and answers questions about your repository — always grounded in the deterministic findings, never fabricating files or fixes.',
    },
    {
      title: 'Built for real repositories',
      description:
        'Point it at any public GitHub repository. Ingestion is sandboxed and read-only — DevAudit AI never clones with a shell, never executes analyzed code, and never modifies your repository.',
    },
  ];

  protected readonly steps = [
    {
      title: 'Connect a repository',
      description: 'Paste a GitHub URL. DevAudit AI securely ingests a snapshot of it.',
    },
    {
      title: 'Automated audit',
      description:
        'The scanner and static-analysis engine run deterministic rules across every source file.',
    },
    {
      title: 'Review your score',
      description:
        'Get an overall score, six category breakdowns, and a prioritized list of findings.',
    },
    {
      title: 'Ask the AI assistant',
      description:
        'Get plain-language explanations, an executive summary, and a refactoring plan — grounded in your audit.',
    },
  ];
}
