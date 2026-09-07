import { KeyValuePipe } from '@angular/common';
import { Component, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { HttpErrorResponse } from '@angular/common/http';
import { forkJoin } from 'rxjs';
import { AuditService } from '../../core/services/audit.service';
import { AiService } from '../../core/services/ai.service';
import { Audit, AuditScore, AuditStatistics } from '../../core/models/audit.model';
import { ExecutiveSummary } from '../../core/models/ai.model';
import { ScoreGauge } from '../../shared/ui/score-gauge/score-gauge';
import { CategoryScoreList } from '../../shared/ui/category-score-list/category-score-list';
import { EmptyState } from '../../shared/ui/empty-state/empty-state';
import { SeverityBadge } from '../../shared/ui/severity-badge/severity-badge';
import { formatBytes, formatDateTime } from '../../shared/utils/format';
import { FindingSeverity } from '../../core/models/finding.model';

type LoadState = 'loading' | 'ok' | 'error' | 'not-found';
type SummaryState = 'idle' | 'loading' | 'ok' | 'error';

@Component({
  selector: 'app-audit-overview',
  imports: [RouterLink, ScoreGauge, CategoryScoreList, EmptyState, SeverityBadge, KeyValuePipe],
  templateUrl: './audit-overview.html',
  styleUrl: './audit-overview.scss',
})
export class AuditOverview {
  private readonly route = inject(ActivatedRoute);
  private readonly auditService = inject(AuditService);
  private readonly aiService = inject(AiService);

  private readonly auditId = this.route.snapshot.paramMap.get('id')!;

  protected readonly state = signal<LoadState>('loading');
  protected readonly audit = signal<Audit | null>(null);
  protected readonly scores = signal<AuditScore[]>([]);
  protected readonly statistics = signal<AuditStatistics | null>(null);

  protected readonly summaryState = signal<SummaryState>('idle');
  protected readonly summary = signal<ExecutiveSummary | null>(null);

  protected readonly severityOrder: FindingSeverity[] = ['critical', 'high', 'medium', 'low', 'info'];

  constructor() {
    this.load();
  }

  private load(): void {
    this.state.set('loading');

    forkJoin({
      audit: this.auditService.get(this.auditId),
      scores: this.auditService.scores(this.auditId),
      statistics: this.auditService.statistics(this.auditId),
    }).subscribe({
      next: ({ audit, scores, statistics }) => {
        this.audit.set(audit);
        this.scores.set(scores);
        this.statistics.set(statistics);
        this.state.set('ok');
      },
      error: (error: HttpErrorResponse) => this.state.set(error.status === 404 ? 'not-found' : 'error'),
    });
  }

  generateSummary(): void {
    this.summaryState.set('loading');
    this.aiService.summary(this.auditId).subscribe({
      next: (summary) => {
        this.summary.set(summary);
        this.summaryState.set('ok');
      },
      error: () => this.summaryState.set('error'),
    });
  }

  protected readonly formatBytes = formatBytes;
  protected readonly formatDateTime = formatDateTime;
}
