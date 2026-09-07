import { Component, inject, signal } from '@angular/core';
import { FormsModule } from '@angular/forms';
import { ActivatedRoute, Router, RouterLink } from '@angular/router';
import { Subject, debounceTime, distinctUntilChanged } from 'rxjs';
import { AuditService } from '../../../core/services/audit.service';
import { AuditFinding, FindingCategory, FindingFilters, FindingSeverity } from '../../../core/models/finding.model';
import { SeverityBadge } from '../../../shared/ui/severity-badge/severity-badge';
import { EmptyState } from '../../../shared/ui/empty-state/empty-state';
import { categoryLabel } from '../../../shared/utils/format';

type LoadState = 'loading' | 'ok' | 'error';

const CATEGORIES: FindingCategory[] = [
  'security',
  'architecture',
  'maintainability',
  'performance',
  'testing',
  'code_quality',
];
const SEVERITIES: FindingSeverity[] = ['critical', 'high', 'medium', 'low', 'info'];

@Component({
  selector: 'app-findings-list',
  imports: [FormsModule, RouterLink, SeverityBadge, EmptyState],
  templateUrl: './findings-list.html',
  styleUrl: './findings-list.scss',
})
export class FindingsList {
  private readonly route = inject(ActivatedRoute);
  private readonly router = inject(Router);
  private readonly auditService = inject(AuditService);

  protected readonly auditId = this.route.snapshot.paramMap.get('id')!;
  protected readonly categories = CATEGORIES;
  protected readonly severities = SEVERITIES;
  protected readonly categoryLabel = categoryLabel;

  protected readonly state = signal<LoadState>('loading');
  protected readonly findings = signal<AuditFinding[]>([]);
  protected readonly total = signal(0);
  protected readonly totalPages = signal(0);

  protected readonly category = signal<FindingCategory | ''>('');
  protected readonly severity = signal<FindingSeverity | ''>('');
  protected readonly search = signal('');
  protected readonly page = signal(1);

  private readonly searchInput$ = new Subject<string>();

  constructor() {
    const params = this.route.snapshot.queryParamMap;
    this.category.set((params.get('category') as FindingCategory) ?? '');
    this.severity.set((params.get('severity') as FindingSeverity) ?? '');
    this.search.set(params.get('file') ?? '');
    this.page.set(Number(params.get('page') ?? 1));

    this.searchInput$.pipe(debounceTime(350), distinctUntilChanged()).subscribe((value) => {
      this.search.set(value);
      this.page.set(1);
      this.load();
    });

    this.load();
  }

  onSearchInput(value: string): void {
    this.searchInput$.next(value);
  }

  setCategory(value: string): void {
    this.category.set(value as FindingCategory | '');
    this.page.set(1);
    this.load();
  }

  setSeverity(value: string): void {
    this.severity.set(value as FindingSeverity | '');
    this.page.set(1);
    this.load();
  }

  goToPage(page: number): void {
    if (page < 1 || page > this.totalPages()) return;
    this.page.set(page);
    this.load();
  }

  private load(): void {
    this.state.set('loading');
    this.syncUrl();

    const filters: FindingFilters = {
      category: this.category() || undefined,
      severity: this.severity() || undefined,
      file: this.search() || undefined,
      page: this.page(),
      perPage: 25,
    };

    this.auditService.findings(this.auditId, filters).subscribe({
      next: (result) => {
        this.findings.set(result.data);
        this.total.set(result.total);
        this.totalPages.set(result.totalPages);
        this.state.set('ok');
      },
      error: () => this.state.set('error'),
    });
  }

  private syncUrl(): void {
    this.router.navigate([], {
      relativeTo: this.route,
      queryParams: {
        category: this.category() || null,
        severity: this.severity() || null,
        file: this.search() || null,
        page: this.page() > 1 ? this.page() : null,
      },
      queryParamsHandling: 'merge',
      replaceUrl: true,
    });
  }
}
