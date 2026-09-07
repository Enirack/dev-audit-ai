import { Component, inject, signal } from '@angular/core';
import { FormBuilder, ReactiveFormsModule, Validators } from '@angular/forms';
import { Router, RouterLink } from '@angular/router';
import { HttpErrorResponse } from '@angular/common/http';
import { forkJoin, map, of, switchMap } from 'rxjs';
import { RepositoryService } from '../../core/services/repository.service';
import { ScanService } from '../../core/services/scan.service';
import { AuditService } from '../../core/services/audit.service';
import { Repository } from '../../core/models/repository.model';
import { RepositoryScan } from '../../core/models/scan.model';
import { Audit } from '../../core/models/audit.model';
import { ScoreGauge } from '../../shared/ui/score-gauge/score-gauge';
import { ScanStatusBadge } from '../../shared/ui/scan-status-badge/scan-status-badge';
import { EmptyState } from '../../shared/ui/empty-state/empty-state';
import { relativeTime } from '../../shared/utils/format';

interface RepositoryRow {
  repository: Repository;
  latestScan: RepositoryScan | null;
  latestAudit: Audit | null;
}

type LoadState = 'loading' | 'ok' | 'error';

@Component({
  selector: 'app-dashboard',
  imports: [ReactiveFormsModule, RouterLink, ScoreGauge, ScanStatusBadge, EmptyState],
  templateUrl: './dashboard.html',
  styleUrl: './dashboard.scss',
})
export class Dashboard {
  private readonly repositoryService = inject(RepositoryService);
  private readonly scanService = inject(ScanService);
  private readonly auditService = inject(AuditService);
  private readonly fb = inject(FormBuilder);
  private readonly router = inject(Router);

  protected readonly state = signal<LoadState>('loading');
  protected readonly rows = signal<RepositoryRow[]>([]);
  protected readonly showAddForm = signal(false);
  protected readonly addSubmitting = signal(false);
  protected readonly addError = signal<string | null>(null);

  protected readonly addForm = this.fb.nonNullable.group({
    url: ['', [Validators.required, Validators.pattern(/^https:\/\/github\.com\/[^/]+\/[^/]+\/?$/)]],
  });

  protected readonly repositoryCount = () => this.rows().length;
  protected readonly criticalRows = () =>
    this.rows()
      .filter((row) => (row.latestAudit?.severityDistribution.critical ?? 0) > 0)
      .sort((a, b) => (b.latestAudit!.severityDistribution.critical) - (a.latestAudit!.severityDistribution.critical));

  protected readonly recentActivity = () =>
    this.rows()
      .filter((row) => row.latestAudit !== null)
      .sort((a, b) => new Date(b.latestAudit!.generatedAt).getTime() - new Date(a.latestAudit!.generatedAt).getTime())
      .slice(0, 6);

  protected readonly averageScore = () => {
    const scored = this.rows().filter((row) => row.latestAudit?.overallScore != null);
    if (scored.length === 0) return null;
    const sum = scored.reduce((acc, row) => acc + row.latestAudit!.overallScore!, 0);
    return sum / scored.length;
  };

  constructor() {
    this.load();
  }

  private load(): void {
    this.state.set('loading');

    this.repositoryService.list().subscribe({
      next: (repositories) => {
        if (repositories.length === 0) {
          this.rows.set([]);
          this.state.set('ok');
          return;
        }

        forkJoin(repositories.map((repository) => this.loadRow(repository))).subscribe({
          next: (rows) => {
            this.rows.set(rows);
            this.state.set('ok');
          },
          error: () => this.state.set('error'),
        });
      },
      error: () => this.state.set('error'),
    });
  }

  private loadRow(repository: Repository) {
    return this.scanService.list(repository.id).pipe(
      map((scans) => scans[0] ?? null),
      switchMap((latestScan) => {
        if (!latestScan?.auditId) {
          return of<RepositoryRow>({ repository, latestScan, latestAudit: null });
        }
        return this.auditService
          .get(latestScan.auditId)
          .pipe(map((audit) => ({ repository, latestScan, latestAudit: audit })));
      }),
    );
  }

  toggleAddForm(): void {
    this.showAddForm.update((open) => !open);
    this.addError.set(null);
  }

  submitAdd(): void {
    if (this.addForm.invalid) {
      this.addForm.markAllAsTouched();
      return;
    }

    this.addSubmitting.set(true);
    this.addError.set(null);

    this.repositoryService.create({ url: this.addForm.getRawValue().url }).subscribe({
      next: (repository) => this.router.navigate(['/repositories', repository.id]),
      error: (error: HttpErrorResponse) => {
        this.addSubmitting.set(false);
        const serverMessage = (error.error as { error?: string } | null)?.error;
        if (error.status === 409) {
          this.addError.set(serverMessage ?? 'You have already added this repository.');
        } else if (error.status === 422) {
          this.addError.set(serverMessage ?? 'That does not look like a valid GitHub repository URL.');
        } else if (error.status === 503) {
          this.addError.set('Unable to reach GitHub right now. Please try again shortly.');
        } else {
          this.addError.set('Unable to add this repository. Please try again.');
        }
      },
    });
  }

  protected readonly relativeTime = relativeTime;
}
