import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import {
  ArchitectureExplanation,
  ChatAnswer,
  ChatMessage,
  ExecutiveSummary,
  FindingExplanation,
  RefactoringPlan,
} from '../models/ai.model';

@Injectable({ providedIn: 'root' })
export class AiService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = environment.apiUrl;

  explainFinding(
    auditId: string,
    findingId: string,
    refresh = false,
  ): Observable<FindingExplanation> {
    return this.http.post<FindingExplanation>(
      `${this.baseUrl}/audits/${auditId}/ai/findings/${findingId}/explain${refresh ? '?refresh=true' : ''}`,
      {},
    );
  }

  summary(auditId: string, refresh = false): Observable<ExecutiveSummary> {
    return this.http.post<ExecutiveSummary>(
      `${this.baseUrl}/audits/${auditId}/ai/summary${refresh ? '?refresh=true' : ''}`,
      {},
    );
  }

  architecture(auditId: string, refresh = false): Observable<ArchitectureExplanation> {
    return this.http.post<ArchitectureExplanation>(
      `${this.baseUrl}/audits/${auditId}/ai/architecture${refresh ? '?refresh=true' : ''}`,
      {},
    );
  }

  refactoringPlan(auditId: string, refresh = false): Observable<RefactoringPlan> {
    return this.http.post<RefactoringPlan>(
      `${this.baseUrl}/audits/${auditId}/ai/refactoring-plan${refresh ? '?refresh=true' : ''}`,
      {},
    );
  }

  ask(auditId: string, question: string): Observable<ChatAnswer> {
    return this.http.post<ChatAnswer>(`${this.baseUrl}/audits/${auditId}/ai/chat`, { question });
  }

  chatHistory(auditId: string): Observable<ChatMessage[]> {
    return this.http.get<ChatMessage[]>(`${this.baseUrl}/audits/${auditId}/ai/chat`);
  }
}
