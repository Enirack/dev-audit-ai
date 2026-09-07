import { Component, computed, input } from '@angular/core';
import { AuditScore } from '../../../core/models/audit.model';
import { FindingCategory } from '../../../core/models/finding.model';
import { categoryLabel, scoreColor } from '../../utils/format';

const CATEGORY_ORDER: FindingCategory[] = [
  'security',
  'architecture',
  'maintainability',
  'performance',
  'testing',
  'code_quality',
];

@Component({
  selector: 'app-category-score-list',
  template: `
    <div class="stack gap-3">
      @for (row of rows(); track row.category) {
        <div class="cat-row">
          <span class="cat-row__label text-sm">{{ categoryLabel(row.category) }}</span>
          <div class="cat-row__track">
            <div class="cat-row__fill" [style.width.%]="row.score ?? 0" [style.background]="colorFor(row.score)"></div>
          </div>
          <span class="cat-row__score mono text-sm" [style.color]="colorFor(row.score)">
            {{ row.score !== null ? row.score.toFixed(0) : '—' }}
          </span>
        </div>
      }
    </div>
  `,
  styles: `
    .cat-row {
      display: grid;
      grid-template-columns: 130px 1fr 34px;
      align-items: center;
      gap: var(--space-3);
    }
    .cat-row__label {
      color: var(--color-text-muted);
    }
    .cat-row__track {
      height: 8px;
      border-radius: 999px;
      background: var(--color-border);
      overflow: hidden;
    }
    .cat-row__fill {
      height: 100%;
      border-radius: 999px;
      transition: width 0.4s ease;
    }
    .cat-row__score {
      text-align: right;
      font-weight: 600;
    }
    @media (max-width: 520px) {
      .cat-row {
        grid-template-columns: 96px 1fr 30px;
      }
    }
  `,
})
export class CategoryScoreList {
  readonly scores = input<AuditScore[]>([]);

  protected readonly categoryLabel = categoryLabel;
  protected readonly colorFor = (score: number | null) => scoreColor(score);

  protected readonly rows = computed(() => {
    const byCategory = new Map(this.scores().map((s) => [s.category, s.score]));
    return CATEGORY_ORDER.map((category) => ({ category, score: byCategory.get(category) ?? null }));
  });
}
