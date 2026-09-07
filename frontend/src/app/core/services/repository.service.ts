import { HttpClient } from '@angular/common/http';
import { Injectable, inject } from '@angular/core';
import { Observable } from 'rxjs';
import { environment } from '../../../environments/environment';
import { CreateRepositoryRequest, Repository } from '../models/repository.model';

@Injectable({ providedIn: 'root' })
export class RepositoryService {
  private readonly http = inject(HttpClient);
  private readonly baseUrl = `${environment.apiUrl}/repositories`;

  list(): Observable<Repository[]> {
    return this.http.get<Repository[]>(this.baseUrl);
  }

  get(id: string): Observable<Repository> {
    return this.http.get<Repository>(`${this.baseUrl}/${id}`);
  }

  create(request: CreateRepositoryRequest): Observable<Repository> {
    return this.http.post<Repository>(this.baseUrl, request);
  }
}
