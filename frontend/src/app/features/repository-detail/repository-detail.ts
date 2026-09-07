import { Component, computed, inject, signal } from '@angular/core';
import { ActivatedRoute, RouterLink } from '@angular/router';
import { HttpErrorResponse } from '@angular/common/http';
import { RepositoryService } from '../../core/services/repository.service';
import { ScanService } from '../../core/services/scan.service';
import { Repository } from '../../core/models/repository.model';
import { RepositoryScan } from '../../core/models/scan.model';
import { ScanStatusBadge } from '../../shared/ui/scan-status-badge/scan-status-badge';
import { EmptyState } from '../../shared/ui/empty-state/empty-state';
import { formatBytes, formatDateTime, languageColor, relativeTime } from '../../shared/utils/format';

type LoadState = 'loading' | 'ok' | 'error' | 'not-found';

@Component({
  selector: 'app-repository-detail',
  imports: [RouterLink, ScanStatusBadge, EmptyState],
  templateUrl: './repository-detail.html',
  styleUrl: './repository-detail.scss',
})
export class RepositoryDetail {
  private readonly route = inject(ActivatedRoute);
  private readonly repositoryService = inject(RepositoryService);
  private readonly scanService = inject(ScanService);

  private readonly repositoryId = this.route.snapshot.paramMap.get('id')!;

  protected readonly state = signal<LoadState>('loading');
  protected readonly repository = signal<Repository | null>(null);
  protected readonly scans = signal<RepositoryScan[]>([]);
  protected readonly starting = signal(false);
  protected readonly startError = signal<string | null>(null);

  protected readonly latestScan = computed(() => this.scans()[0] ?? null);
  protected readonly latestInventory = computed(() => this.latestScan()?.inventory ?? null);

  protected readonly languageRows = computed(() => {
    const stats = this.latestInventory()?.languageStats ?? {};
    const totalLines = Object.values(stats).reduce((sum, s) => sum + s.lines, 0);
    return Object.entries(stats)
      .map(([language, s]) => ({ language, ...s, percent: totalLines > 0 ? (s.lines / totalLines) * 100 : 0 }))
      .sort((a, b) => b.lines - a.lines);
  });

  constructor() {
    this.load();
  }

  private load(): void {
    this.state.set('loading');

    this.repositoryService.get(this.repositoryId).subscribe({
      next: (repository) => {
        this.repository.set(repository);
        this.scanService.list(this.repositoryId).subscribe({
          next: (scans) => {
            this.scans.set(scans);
            this.state.set('ok');
          },
          error: () => this.state.set('error'),
        });
      },
      error: (error: HttpErrorResponse) => this.state.set(error.status === 404 ? 'not-found' : 'error'),
    });
  }

  startAudit(): void {
    this.starting.set(true);
    this.startError.set(null);

    this.scanService.start(this.repositoryId).subscribe({
      next: (scan) => {
        this.scans.update((scans) => [scan, ...scans]);
        this.starting.set(false);
        if (scan.status === 'failed') {
          this.startError.set(scan.errorMessage ?? 'The audit failed. Please try again.');
        }
      },
      error: () => {
        this.starting.set(false);
        this.startError.set('Unable to start the audit right now. Please try again.');
      },
    });
  }

  protected readonly formatBytes = formatBytes;
  protected readonly formatDateTime = formatDateTime;
  protected readonly relativeTime = relativeTime;
  protected readonly languageColor = languageColor;
}
