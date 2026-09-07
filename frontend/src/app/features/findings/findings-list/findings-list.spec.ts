import { provideHttpClient } from '@angular/common/http';
import { HttpTestingController, provideHttpClientTesting } from '@angular/common/http/testing';
import { ComponentFixture, TestBed } from '@angular/core/testing';
import { ActivatedRoute, convertToParamMap, provideRouter } from '@angular/router';
import { environment } from '../../../../environments/environment';
import { FindingsList } from './findings-list';

const EMPTY_PAGE = { data: [], page: 1, perPage: 25, total: 0, totalPages: 0 };

function makeFinding(id: string, overrides: Partial<Record<string, unknown>> = {}) {
  return {
    id,
    ruleId: 'security.hardcoded-secret',
    category: 'security',
    severity: 'high',
    priority: 4,
    confidence: 0.8,
    title: `Finding ${id}`,
    description: 'desc',
    filePath: 'src/a.php',
    startLine: 1,
    endLine: 1,
    recommendation: null,
    analyzer: 'php',
    metadata: null,
    createdAt: '2026-01-01T00:00:00Z',
    ...overrides,
  };
}

describe('FindingsList', () => {
  let httpMock: HttpTestingController;
  let fixture: ComponentFixture<FindingsList>;

  beforeEach(async () => {
    await TestBed.configureTestingModule({
      imports: [FindingsList],
      providers: [
        provideHttpClient(),
        provideHttpClientTesting(),
        provideRouter([]),
        {
          provide: ActivatedRoute,
          useValue: {
            snapshot: {
              paramMap: convertToParamMap({ id: 'audit-1' }),
              queryParamMap: convertToParamMap({}),
            },
          },
        },
      ],
    }).compileComponents();

    httpMock = TestBed.inject(HttpTestingController);
  });

  afterEach(() => httpMock.verify());

  it('loads findings for the audit on init with default filters', () => {
    fixture = TestBed.createComponent(FindingsList);
    fixture.detectChanges();

    const req = httpMock.expectOne(
      (r) =>
        r.url === `${environment.apiUrl}/audits/audit-1/findings` && r.params.get('page') === '1',
    );
    req.flush({ ...EMPTY_PAGE, data: [makeFinding('f1')], total: 1, totalPages: 1 });
    fixture.detectChanges();

    expect((fixture.nativeElement as HTMLElement).textContent).toContain('Finding f1');
  });

  it('shows an empty state when no findings match', () => {
    fixture = TestBed.createComponent(FindingsList);
    fixture.detectChanges();

    httpMock
      .expectOne((r) => r.url === `${environment.apiUrl}/audits/audit-1/findings`)
      .flush(EMPTY_PAGE);
    fixture.detectChanges();

    expect((fixture.nativeElement as HTMLElement).textContent).toContain(
      'No findings match these filters',
    );
  });

  it('refetches with the category filter when the category select changes', () => {
    fixture = TestBed.createComponent(FindingsList);
    fixture.detectChanges();
    httpMock
      .expectOne((r) => r.url === `${environment.apiUrl}/audits/audit-1/findings`)
      .flush(EMPTY_PAGE);

    fixture.componentInstance.setCategory('architecture');

    const req = httpMock.expectOne(
      (r) =>
        r.url === `${environment.apiUrl}/audits/audit-1/findings` &&
        r.params.get('category') === 'architecture',
    );
    req.flush(EMPTY_PAGE);
    fixture.detectChanges();

    const select = fixture.nativeElement.querySelector('#category') as HTMLSelectElement;
    expect(select.value).toBe('architecture');
  });

  it('advances the page and refetches when "Next" is used', () => {
    fixture = TestBed.createComponent(FindingsList);
    fixture.detectChanges();
    httpMock
      .expectOne((r) => r.url === `${environment.apiUrl}/audits/audit-1/findings`)
      .flush({ ...EMPTY_PAGE, data: [makeFinding('f1')], total: 30, totalPages: 2 });
    fixture.detectChanges();

    fixture.componentInstance.goToPage(2);

    const req = httpMock.expectOne(
      (r) =>
        r.url === `${environment.apiUrl}/audits/audit-1/findings` && r.params.get('page') === '2',
    );
    req.flush({ ...EMPTY_PAGE, data: [makeFinding('f2')], total: 30, totalPages: 2, page: 2 });
    fixture.detectChanges();

    expect((fixture.nativeElement as HTMLElement).textContent).toContain('Page 2 of 2');
  });

  it('does not go past the last page', () => {
    fixture = TestBed.createComponent(FindingsList);
    fixture.detectChanges();
    httpMock
      .expectOne((r) => r.url === `${environment.apiUrl}/audits/audit-1/findings`)
      .flush({ ...EMPTY_PAGE, totalPages: 1 });

    fixture.componentInstance.goToPage(2);

    httpMock.expectNone((r) => r.params.get('page') === '2');
  });
});
