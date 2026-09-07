import { Component, input } from '@angular/core';

@Component({
  selector: 'app-empty-state',
  template: `
    <div class="state-panel" [class.state-panel--error]="variant() === 'error'">
      @if (icon()) {
        <span class="empty-icon" aria-hidden="true">{{ icon() }}</span>
      }
      <h3>{{ title() }}</h3>
      @if (message()) {
        <p class="text-sm">{{ message() }}</p>
      }
      <ng-content />
    </div>
  `,
  styles: `
    .empty-icon {
      font-size: 2rem;
      line-height: 1;
    }
    h3 {
      font-size: 1rem;
      color: var(--color-text);
    }
  `,
})
export class EmptyState {
  readonly title = input.required<string>();
  readonly message = input<string | null>(null);
  readonly icon = input<string | null>(null);
  readonly variant = input<'default' | 'error'>('default');
}
