import { Component, input } from '@angular/core';
import { ScanStatus } from '../../../core/models/scan.model';
import { scanStatusLabel } from '../../utils/format';

@Component({
  selector: 'app-scan-status-badge',
  template: `
    <span class="badge status-badge" [class]="'status-badge--' + status()">
      @if (status() === 'cloning' || status() === 'scanning') {
        <span class="status-badge__spinner"></span>
      }
      {{ scanStatusLabel(status()) }}
    </span>
  `,
  styles: `
    .status-badge {
      background: var(--color-surface-raised);
      color: var(--color-text-muted);
      border-color: var(--color-border-strong);
    }
    .status-badge--completed {
      color: var(--color-success);
      border-color: color-mix(in srgb, var(--color-success) 35%, transparent);
      background: color-mix(in srgb, var(--color-success) 12%, transparent);
    }
    .status-badge--failed {
      color: var(--color-danger);
      border-color: color-mix(in srgb, var(--color-danger) 35%, transparent);
      background: color-mix(in srgb, var(--color-danger) 12%, transparent);
    }
    .status-badge--cloning,
    .status-badge--scanning {
      color: var(--color-accent);
      border-color: color-mix(in srgb, var(--color-accent) 35%, transparent);
      background: var(--color-accent-muted);
    }
    .status-badge__spinner {
      width: 8px;
      height: 8px;
      border-radius: 50%;
      border: 2px solid currentColor;
      border-right-color: transparent;
      animation: spin 0.7s linear infinite;
    }
    @keyframes spin {
      to {
        transform: rotate(360deg);
      }
    }
    @media (prefers-reduced-motion: reduce) {
      .status-badge__spinner {
        animation: none;
      }
    }
  `,
})
export class ScanStatusBadge {
  readonly status = input.required<ScanStatus>();
  protected readonly scanStatusLabel = scanStatusLabel;
}
