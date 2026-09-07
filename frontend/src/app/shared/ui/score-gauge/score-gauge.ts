import { Component, computed, input } from '@angular/core';
import { scoreColor } from '../../utils/format';

@Component({
  selector: 'app-score-gauge',
  template: `
    <div class="gauge" [class.gauge--sm]="size() === 'sm'" [style.--gauge-color]="color()" [style.--gauge-pct]="pct()">
      <div class="gauge__ring">
        <div class="gauge__hole">
          @if (value() !== null) {
            <span class="gauge__value">{{ value()!.toFixed(0) }}</span>
          } @else {
            <span class="gauge__value gauge__value--empty">—</span>
          }
        </div>
      </div>
      @if (label()) {
        <span class="gauge__label text-sm text-muted">{{ label() }}</span>
      }
    </div>
  `,
  styles: `
    .gauge {
      display: inline-flex;
      flex-direction: column;
      align-items: center;
      gap: var(--space-2);
    }
    .gauge__ring {
      width: 108px;
      height: 108px;
      border-radius: 50%;
      background: conic-gradient(var(--gauge-color) calc(var(--gauge-pct) * 3.6deg), var(--color-border) 0deg);
      display: flex;
      align-items: center;
      justify-content: center;
      flex-shrink: 0;
    }
    .gauge--sm .gauge__ring {
      width: 60px;
      height: 60px;
    }
    .gauge__hole {
      width: calc(100% - 14px);
      height: calc(100% - 14px);
      border-radius: 50%;
      background: var(--color-surface);
      display: flex;
      align-items: center;
      justify-content: center;
    }
    .gauge--sm .gauge__hole {
      width: calc(100% - 8px);
      height: calc(100% - 8px);
    }
    .gauge__value {
      font-size: 1.9rem;
      font-weight: 700;
      color: var(--gauge-color);
      font-variant-numeric: tabular-nums;
    }
    .gauge--sm .gauge__value {
      font-size: 1.1rem;
    }
    .gauge__value--empty {
      color: var(--color-text-faint);
    }
    .gauge__label {
      text-align: center;
    }
  `,
})
export class ScoreGauge {
  readonly value = input<number | null>(null);
  readonly label = input<string | null>(null);
  readonly size = input<'sm' | 'md'>('md');

  protected readonly pct = computed(() => Math.max(0, Math.min(100, this.value() ?? 0)));
  protected readonly color = computed(() => scoreColor(this.value()));
}
