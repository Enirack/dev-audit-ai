import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { TestBed } from '@angular/core/testing';
import { provideRouter } from '@angular/router';
import { environment } from '../../../environments/environment';
import { Dashboard } from './dashboard';

describe('Dashboard', () => {
  let httpMock: HttpTestingController;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [Dashboard],
      providers: [provideHttpClient(), provideHttpClientTesting(), provideRouter([])],
    }).compileComponents();

    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpMock.verify());

  it('shows an empty state when the user has no repositories', () => {
    const fixture = TestBed.createComponent(Dashboard);
    fixture.detectChanges();

    httpMock.expectOne(`${environment.apiUrl}/repositories`).flush([]);
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('No repositories yet');
  });

  it('shows a repository card with its latest audit score once loaded', () => {
    const fixture = TestBed.createComponent(Dashboard);
    fixture.detectChanges();

    httpMock.expectOne(`${environment.apiUrl}/repositories`).flush([
      { id: 'r1', name: 'acme/widgets', url: 'https://github.com/acme/widgets', provider: 'github', defaultBranch: 'main', description: null, createdAt: '2026-01-01T00:00:00Z', updatedAt: '2026-01-01T00:00:00Z' },
    ]);

    httpMock.expectOne(`${environment.apiUrl}/repositories/r1/scans`).flush([
      { id: 's1', repositoryId: 'r1', status: 'completed', commitSha: 'abc123', startedAt: null, finishedAt: null, errorMessage: null, createdAt: '2026-01-02T00:00:00Z', inventory: null, auditId: 'a1' },
    ]);

    httpMock.expectOne(`${environment.apiUrl}/audits/a1`).flush({
      id: 'a1',
      repositoryScanId: 's1',
      repositoryId: 'r1',
      repositoryName: 'acme/widgets',
      summary: null,
      overallScore: 82,
      generatedAt: '2026-01-02T00:05:00Z',
      severityDistribution: { info: 0, low: 1, medium: 0, high: 0, critical: 0 },
      findingsCount: 1,
    });

    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain('acme/widgets');
    expect(compiled.textContent).toContain('82');
  });

  it('shows an error state when the repository list fails to load', () => {
    const fixture = TestBed.createComponent(Dashboard);
    fixture.detectChanges();

    httpMock.expectOne(`${environment.apiUrl}/repositories`).error(new ProgressEvent('error'));
    fixture.detectChanges();

    const compiled = fixture.nativeElement as HTMLElement;
    expect(compiled.textContent).toContain("Couldn't load your dashboard");
  });
});
