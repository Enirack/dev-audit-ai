import { Component, input } from '@angular/core';
import { FindingSeverity } from '../../../core/models/finding.model';
import { severityLabel } from '../../utils/format';

@Component({
  selector: 'app-severity-badge',
  template: `
    <span class="badge sev-badge" [class]="'sev-badge--' + severity()">
      <span class="sev-badge__dot"></span>
      {{ severityLabel(severity()) }}
    </span>
  `,
  styles: `
    .sev-badge {
      background: color-mix(in srgb, var(--sev-color) 16%, transparent);
      color: var(--sev-color);
      border-color: color-mix(in srgb, var(--sev-color) 35%, transparent);
    }
    .sev-badge__dot {
      width: 6px;
      height: 6px;
      border-radius: 50%;
      background: var(--sev-color);
    }
    .sev-badge--critical {
      --sev-color: var(--sev-critical);
    }
    .sev-badge--high {
      --sev-color: var(--sev-high);
    }
    .sev-badge--medium {
      --sev-color: var(--sev-medium);
    }
    .sev-badge--low {
      --sev-color: var(--sev-low);
    }
    .sev-badge--info {
      --sev-color: var(--sev-info);
    }
  `,
})
export class SeverityBadge {
  readonly severity = input.required<FindingSeverity>();
  protected readonly severityLabel = severityLabel;
}
