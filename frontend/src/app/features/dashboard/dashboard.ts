import { Component, inject, signal } from '@angular/core';
import { ApiService, HealthResponse } from '../../core/services/api.service';

type LoadState = 'loading' | 'ok' | 'error';

@Component({
  selector: 'app-dashboard',
  imports: [],
  templateUrl: './dashboard.html',
  styleUrl: './dashboard.scss',
})
export class Dashboard {
  private readonly api = inject(ApiService);

  protected readonly state = signal<LoadState>('loading');
  protected readonly health = signal<HealthResponse | null>(null);
  protected readonly errorMessage = signal<string | null>(null);

  constructor() {
    this.api.checkHealth().subscribe({
      next: (response) => {
        this.health.set(response);
        this.state.set('ok');
      },
      error: () => {
        this.errorMessage.set('Unable to reach the DevAudit AI backend.');
        this.state.set('error');
      },
    });
  }
}
