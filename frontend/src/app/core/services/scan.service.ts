import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { RepositoryScan } from '../models/scan.model';

@Injectable({ providedIn: 'root' })
export class ScanService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = environment.apiUrl;

  list(repositoryId: string): Observable<RepositoryScan[]> {
    return this.http.get<RepositoryScan[]>(`${this.baseUrl}/repositories/${repositoryId}/scans`);
  }

  get(repositoryId: string, scanId: string): Observable<RepositoryScan> {
    return this.http.get<RepositoryScan>(
      `${this.baseUrl}/repositories/${repositoryId}/scans/${scanId}`,
    );
  }

  /** Blocks until ingestion + analysis finish — there is no queue in v1, see docs/development.md. */
  start(repositoryId: string): Observable<RepositoryScan> {
    return this.http.post<RepositoryScan>(`${this.baseUrl}/repositories/${repositoryId}/scans`, {});
  }
}
