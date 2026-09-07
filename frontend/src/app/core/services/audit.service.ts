import { HttpClient, HttpParams } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { Audit, AuditScore, AuditStatistics } from '../models/audit.model';
import { AuditFinding, FindingFilters, PaginatedFindings } from '../models/finding.model';

@Injectable({ providedIn: 'root' })
export class AuditService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/audits`;

  get(id: string): Observable<Audit> {
    return this.http.get<Audit>(`${this.baseUrl}/${id}`);
  }

  scores(id: string): Observable<AuditScore[]> {
    return this.http.get<AuditScore[]>(`${this.baseUrl}/${id}/scores`);
  }

  statistics(id: string): Observable<AuditStatistics> {
    return this.http.get<AuditStatistics>(`${this.baseUrl}/${id}/statistics`);
  }

  findings(id: string, filters: FindingFilters): Observable<PaginatedFindings> {
    let params = new HttpParams();
    if (filters.category) params = params.set('category', filters.category);
    if (filters.severity) params = params.set('severity', filters.severity);
    if (filters.file) params = params.set('file', filters.file);
    if (filters.rule) params = params.set('rule', filters.rule);
    params = params.set('page', filters.page ?? 1);
    params = params.set('perPage', filters.perPage ?? 25);

    return this.http.get<PaginatedFindings>(`${this.baseUrl}/${id}/findings`, { params });
  }

  finding(auditId: string, findingId: string): Observable<AuditFinding> {
    return this.http.get<AuditFinding>(`${this.baseUrl}/${auditId}/findings/${findingId}`);
  }
}
