import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { HttpErrorResponse } from '@angular/common/http';
import { AuditService } from '../../../core/services/audit.service';
import { AiService } from '../../../core/services/ai.service';
import { AuditFinding } from '../../../core/models/finding.model';
import { FindingExplanation } from '../../../core/models/ai.model';
import { SeverityBadge } from '../../../shared/ui/severity-badge/severity-badge';
import { EmptyState } from '../../../shared/ui/empty-state/empty-state';
import { categoryLabel, formatDateTime } from '../../../shared/utils/format';

type LoadState = 'loading' | 'ok' | 'error' | 'not-found';
type ExplanationState = 'idle' | 'loading' | 'ok' | 'error';

@Component({
  selector: 'app-finding-detail',
  imports: [RouterLink, SeverityBadge, EmptyState],
  templateUrl: './finding-detail.html',
  styleUrl: './finding-detail.scss',
})
export class FindingDetail {
  private readonly route = inject(ActivatedRoute);
  private readonly auditService = inject(AuditService);
  private readonly aiService = inject(AiService);

  protected readonly auditId = this.route.snapshot.paramMap.get('id')!;
  private readonly findingId = this.route.snapshot.paramMap.get('findingId')!;

  protected readonly state = signal<LoadState>('loading');
  protected readonly finding = signal<AuditFinding | null>(null);

  protected readonly explanationState = signal<ExplanationState>('idle');
  protected readonly explanation = signal<FindingExplanation | null>(null);

  protected readonly categoryLabel = categoryLabel;
  protected readonly formatDateTime = formatDateTime;

  constructor() {
    this.auditService.finding(this.auditId, this.findingId).subscribe({
      next: (finding) => {
        this.finding.set(finding);
        this.state.set('ok');
      },
      error: (error: HttpErrorResponse) => this.state.set(error.status === 404 ? 'not-found' : 'error'),
    });
  }

  explainWithAi(): void {
    this.explanationState.set('loading');
    this.aiService.explainFinding(this.auditId, this.findingId).subscribe({
      next: (explanation) => {
        this.explanation.set(explanation);
        this.explanationState.set('ok');
      },
      error: () => this.explanationState.set('error'),
    });
  }
}
